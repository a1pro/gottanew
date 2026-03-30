<?php

namespace App\Http\Controllers\Api\Session;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\Transaction;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionParticipant;
use App\Models\Session\SessionRecording;
use App\Models\Session\SessionStateLog;
use App\Models\Session\SessionVideoDetail;
use App\Services\Communication\NotificationService;
use App\Services\Video\DailyRestApiService;
use App\Support\Timezone;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionPortalController extends BaseController
{
    private const PRE_JOIN_MINUTES = 5;
    private const RECOVERY_BUFFER_MINUTES = 20;
    private const SESSION_CONSENT_VERSION = '2026-03';

    public function __construct(
        private NotificationService $notificationService,
        private DailyRestApiService $dailyService,
    ) {
    }

    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated');

        $session = CoachingSession::with([
            'coach',
            'client',
            'videoDetail',
            'recording',
            'participants',
            'introRequest.preferredCoach:id,name,title,timezone',
            'introRequest.assignedCoach:id,name,title,timezone',
            'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
        ])->findOrFail($id);

        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

        abort_unless($isClient || $isCoach || $isAdmin, 403, 'Unauthorized access to session');

        return $session;
    }

    public function show(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $this->expireInterruptedSessionIfNeeded($session, optional($request->user())->id, 'show');

        return $this->success($this->presentSession($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $request->user()));
    }

    public function join(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $this->expireInterruptedSessionIfNeeded($session, optional($request->user())->id, 'join');
        $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);
        $validation = $this->buildValidationSnapshot($session, $request->user());

        if (!$validation['can_join']) {
            return $this->error($validation['message'] ?: 'This session cannot be joined right now.', 422);
        }

        $videoDetail = $session->videoDetail;

        if (!$videoDetail || empty($videoDetail->video_join_url)) {
            $videoDetail = $this->createOrRefreshVideoRoom($session, true);
            $session->setRelation('videoDetail', $videoDetail);
        }

        $role = $this->resolveRole($session, $request->user());
        $participant = $this->touchParticipant($session, $request->user(), $role, [
            'meeting_token_issued_at' => now(),
            'joined_at' => now(),
        ]);

        $session->update([
            'last_activity_at' => now(),
        ]);

        return $this->success($this->buildJoinPayload(
            $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']),
            $request->user(),
            $participant
        ));
    }

    public function reconnect(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();
        $this->expireInterruptedSessionIfNeeded($session, optional($user)->id, 'reconnect');
        $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);

        $validated = $request->validate([
            'force_refresh_room' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
        ]);

        $validation = $this->buildValidationSnapshot($session, $user);

        if (!$validation['can_join'] && !$validation['manual_recovery_allowed']) {
            return $this->error($validation['message'] ?: 'This session is outside the reconnect window.', 422);
        }

        DB::beginTransaction();

        try {
            $fromState = $session->status;

            if (($validated['force_refresh_room'] ?? false) || !$session->videoDetail || empty($session->videoDetail->video_join_url)) {
                $videoDetail = $this->createOrRefreshVideoRoom($session, (bool) ($validated['force_refresh_room'] ?? false));
                $session->setRelation('videoDetail', $videoDetail);
            }

            $updates = [
                'last_activity_at' => now(),
                'recovery_attempts' => (int) ($session->recovery_attempts ?? 0) + 1,
                'failure_reason' => null,
                'recovery_context' => $this->mergeRecoveryContext(
                    $session->recovery_context,
                    [
                        'last_reconnect_at' => now()->toISOString(),
                        'last_reconnect_by_user_id' => $user->id,
                        'last_reconnect_reason' => $validated['reason'] ?? 'Participant requested reconnect',
                    ]
                ),
            ];

            if ($fromState === 'interrupted') {
                $updates['status'] = 'live';
                $updates['recovery_deadline_at'] = null;
                $updates['last_interrupted_at'] = null;
                $updates['actual_started_at'] = $session->actual_started_at ?: now();
            }

            $session->update($updates);

            if ($fromState === 'interrupted') {
                $this->logStateTransition($session, $fromState, 'live', $user->id, $validated['reason'] ?? 'Session reconnected', [
                    'reconnected_at' => now()->toISOString(),
                    'source' => 'reconnect_endpoint',
                ]);
            }

            $role = $this->resolveRole($session, $user);
            $participant = $this->touchParticipant($session, $user, $role, [
                'meeting_token_issued_at' => now(),
                'joined_at' => now(),
                'left_at' => null,
            ]);

            DB::commit();

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);

            if ($fromState === 'interrupted') {
                $this->notificationService->sessionStateChanged($session, $fromState, 'live', $user->id);
            }

            return $this->success([
                'session' => $this->presentSession($session, $user),
                'join' => $this->buildJoinPayload($session, $user, $participant),
                'validation' => $this->buildValidationSnapshot($session, $user),
            ], 'Session reconnect ready');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare reconnect',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function markInterrupted(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (in_array($session->status, ['completed', 'failed'], true)) {
            return $this->error('Completed or failed sessions cannot be marked interrupted.', 422);
        }

        if ($session->status === 'interrupted') {
            $session->update([
                'last_activity_at' => now(),
                'last_interrupted_at' => now(),
                'recovery_deadline_at' => now()->addMinutes(self::RECOVERY_BUFFER_MINUTES),
                'failure_reason' => $validated['reason'] ?? $session->failure_reason,
                'recovery_context' => $this->mergeRecoveryContext(
                    $session->recovery_context,
                    $validated['metadata'] ?? []
                ),
            ]);

            return $this->success($this->presentSession($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $user), 'Session interruption refreshed');
        }

        DB::beginTransaction();

        try {
            $fromState = $session->status;

            $session->update([
                'status' => 'interrupted',
                'last_activity_at' => now(),
                'last_interrupted_at' => now(),
                'recovery_deadline_at' => now()->addMinutes(self::RECOVERY_BUFFER_MINUTES),
                'failure_reason' => $validated['reason'] ?? 'Connection interrupted',
                'recovery_context' => $this->mergeRecoveryContext(
                    $session->recovery_context,
                    array_merge($validated['metadata'] ?? [], [
                        'interrupted_at' => now()->toISOString(),
                        'interrupted_by_user_id' => $user->id,
                    ])
                ),
            ]);

            $participant = $this->touchParticipant($session, $user, $this->resolveRole($session, $user), [
                'left_at' => now(),
            ]);

            $this->logStateTransition($session, $fromState, 'interrupted', $user->id, $validated['reason'] ?? 'Connection interrupted', [
                'participant_id' => $participant?->id,
                'source' => 'interrupt_endpoint',
            ]);

            DB::commit();

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);
            $this->notificationService->sessionStateChanged($session, $fromState, 'interrupted', $user->id);

            return $this->success($this->presentSession($session, $user), 'Session marked interrupted');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark session interrupted',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function recover(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        abort_unless($this->canManualRecover($session, $user), 403, 'Only the coach or admin can manually recover sessions.');

        $validated = $request->validate([
            'action' => ['required', 'in:resume,regenerate_room,mark_failed'],
            'reason' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $fromState = $session->status;
            $action = $validated['action'];
            $message = 'Recovery action completed';
            $videoDetail = $session->videoDetail;

            if ($action === 'regenerate_room') {
                $videoDetail = $this->createOrRefreshVideoRoom($session, true);
                $session->setRelation('videoDetail', $videoDetail);
                $session->update([
                    'last_activity_at' => now(),
                    'recovery_attempts' => (int) ($session->recovery_attempts ?? 0) + 1,
                    'recovery_context' => $this->mergeRecoveryContext(
                        $session->recovery_context,
                        [
                            'last_manual_recovery_at' => now()->toISOString(),
                            'last_manual_recovery_by_user_id' => $user->id,
                            'last_manual_action' => 'regenerate_room',
                        ]
                    ),
                ]);
                $message = 'Session room refreshed';
            }

            if ($action === 'resume') {
                if (!$videoDetail || empty($videoDetail->video_join_url)) {
                    $videoDetail = $this->createOrRefreshVideoRoom($session, true);
                    $session->setRelation('videoDetail', $videoDetail);
                }

                $nextState = $this->buildValidationSnapshot($session, $user)['can_join'] ? 'live' : 'scheduled';

                $session->update([
                    'status' => $nextState,
                    'last_activity_at' => now(),
                    'failure_reason' => null,
                    'recovery_deadline_at' => null,
                    'last_interrupted_at' => null,
                    'recovery_attempts' => (int) ($session->recovery_attempts ?? 0) + 1,
                    'actual_started_at' => $nextState === 'live' ? ($session->actual_started_at ?: now()) : $session->actual_started_at,
                    'recovery_context' => $this->mergeRecoveryContext(
                        $session->recovery_context,
                        [
                            'last_manual_recovery_at' => now()->toISOString(),
                            'last_manual_recovery_by_user_id' => $user->id,
                            'last_manual_action' => 'resume',
                        ]
                    ),
                ]);

                if ($fromState !== $nextState) {
                    $this->logStateTransition($session, $fromState, $nextState, $user->id, $validated['reason'] ?? 'Manual session recovery', [
                        'source' => 'manual_recovery',
                    ]);
                }

                $message = $nextState === 'live' ? 'Session resumed' : 'Session reset to scheduled';
            }

            if ($action === 'mark_failed') {
                $this->refundReservedTokenIfNeeded($session);

                $session->update([
                    'status' => 'failed',
                    'actual_ended_at' => now(),
                    'last_activity_at' => now(),
                    'failure_reason' => $validated['reason'] ?? 'Marked failed during manual recovery',
                    'recovery_deadline_at' => null,
                    'recovery_context' => $this->mergeRecoveryContext(
                        $session->recovery_context,
                        [
                            'last_manual_recovery_at' => now()->toISOString(),
                            'last_manual_recovery_by_user_id' => $user->id,
                            'last_manual_action' => 'mark_failed',
                        ]
                    ),
                ]);

                if ($fromState !== 'failed') {
                    $this->logStateTransition($session, $fromState, 'failed', $user->id, $validated['reason'] ?? 'Marked failed during manual recovery', [
                        'source' => 'manual_recovery',
                    ]);
                }

                $message = 'Session marked failed';
            }

            DB::commit();

            if ($action === 'mark_failed') {
                $this->stopDailyCaptureIfNeeded($session->fresh(['videoDetail', 'recording']));
            }

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);

            if ($fromState !== $session->status) {
                $this->notificationService->sessionStateChanged($session, $fromState, $session->status, $user->id);
            }

            return $this->success([
                'session' => $this->presentSession($session, $user),
                'join' => $this->buildJoinPayload($session, $user),
                'validation' => $this->buildValidationSnapshot($session, $user),
            ], $message);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete recovery action',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveConsent(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'recording_enabled' => ['nullable', 'boolean'],
            'transcription_consent' => ['required', 'in:full,basic,none'],
            'acknowledge_coaching_disclaimer' => ['required', 'accepted'],
            'confirm_informed_consent' => ['required', 'accepted'],
        ]);

        $recordingEnabled = array_key_exists('recording_enabled', $validated)
            ? (bool) $validated['recording_enabled']
            : $validated['transcription_consent'] !== 'none';

        if ($validated['transcription_consent'] === 'full') {
            $recordingEnabled = true;
        }

        $recording = $this->ensureSessionRecording($session);

        $privacySettings = array_merge($recording->privacy_settings ?? [], [
            'recording_enabled' => $recordingEnabled,
            'transcription_consent' => $validated['transcription_consent'],
            'consented_by_user_id' => $user->id,
            'consented_at' => now()->toISOString(),
            'consent_source' => 'session_lobby',
            'coaching_disclaimer_acknowledged' => true,
            'informed_recording_consent_confirmed' => true,
            'consent_version' => self::SESSION_CONSENT_VERSION,
        ]);

        $recording->update([
            'privacy_settings' => $privacySettings,
            'transcription_status' => $validated['transcription_consent'] === 'full' ? 'active' : 'inactive',
        ]);

        return $this->success(
            $recording->fresh(),
            'Session consent saved'
        );
    }

    public function updateRecording(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'recording_url' => ['nullable', 'url'],
            'transcript' => ['nullable', 'string'],
            'ai_summary' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'file_size_bytes' => ['nullable', 'integer', 'min:0'],
            'sentiment_analysis' => ['nullable', 'array'],
            'key_topics' => ['nullable', 'array'],
            'personality_insights' => ['nullable', 'array'],
            'emotional_journey' => ['nullable', 'array'],
            'coaching_effectiveness_score' => ['nullable', 'numeric', 'between:0,100'],
            'transcription_status' => ['nullable', 'in:inactive,active,paused,completed'],
            'transcription_paused_segments' => ['nullable', 'array'],
        ]);

        $recording = $this->ensureSessionRecording($session);

        $updates = $validated;

        if (!isset($updates['transcription_status']) && !empty($updates['transcript'])) {
            $updates['transcription_status'] = 'completed';
        }

        $recording->update($updates);

        return $this->success(
            $recording->fresh(),
            'Session recording data updated'
        );
    }

    public function updateState(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'new_state' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $fromState = $session->status;
        $toState = $this->normalizeState($validated['new_state']);

        if (!in_array($toState, ['scheduled', 'live', 'interrupted', 'completed', 'failed'], true)) {
            return $this->error('Invalid session state', 422);
        }

        if ($fromState === $toState) {
            return $this->success(
                $this->presentSession($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $request->user()),
                'Session state unchanged'
            );
        }

        DB::beginTransaction();

        try {
            if ($toState === 'live') {
                $reserveError = $this->reserveTokenIfNeeded($session);

                if ($reserveError) {
                    DB::rollBack();
                    return $this->error($reserveError, 422);
                }
            }

            if ($toState === 'completed') {
                $this->completeReservedTokenIfNeeded($session);
            }

            if ($toState === 'failed') {
                $this->refundReservedTokenIfNeeded($session);
            }

            $updates = [
                'status' => $toState,
                'last_activity_at' => now(),
            ];

            if ($toState === 'live') {
                $updates['actual_started_at'] = $session->actual_started_at ?: now();
                $updates['last_interrupted_at'] = null;
                $updates['recovery_deadline_at'] = null;
                $updates['failure_reason'] = null;
            }

            if ($toState === 'interrupted') {
                $updates['last_interrupted_at'] = now();
                $updates['recovery_deadline_at'] = now()->addMinutes(self::RECOVERY_BUFFER_MINUTES);
                $updates['failure_reason'] = $validated['reason'] ?? 'Connection interrupted';
            }

            if (in_array($toState, ['completed', 'failed'], true)) {
                $updates['actual_ended_at'] = now();
                $updates['recovery_deadline_at'] = null;
            }

            $updates['recovery_context'] = $this->mergeRecoveryContext(
                $session->recovery_context,
                $validated['metadata'] ?? []
            );

            $session->update($updates);

            $this->logStateTransition($session, $fromState, $toState, optional($request->user())->id, $validated['reason'] ?? null, $validated['metadata'] ?? null);

            DB::commit();

            if (in_array($toState, ['completed', 'failed'], true)) {
                $this->stopDailyCaptureIfNeeded($session->fresh(['videoDetail', 'recording']));
            }

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);
            $this->notificationService->sessionStateChanged($session, $fromState, $toState, optional($request->user())->id);

            return $this->success(
                $this->presentSession($session, $request->user()),
                'Session state updated'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update session state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveNotes(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'client_notes' => ['nullable', 'string'],
            'coach_notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;

        $updates = [];

        if ($isClient && array_key_exists('client_notes', $validated)) {
            $updates['client_notes'] = $validated['client_notes'];
        }

        if ($isCoach && array_key_exists('coach_notes', $validated)) {
            $updates['coach_notes'] = $validated['coach_notes'];
        }

        if (!empty($updates)) {
            $session->update($updates);
        }

        return $this->success(
            $this->presentSession($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $user),
            'Session notes saved'
        );
    }

    public function end(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        if ($session->status === 'completed') {
            return $this->success(
                $this->presentSession($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $request->user()),
                'Session already completed'
            );
        }

        if ($session->status === 'failed') {
            return $this->error('Failed sessions cannot be completed.', 422);
        }

        $fromState = $session->status;

        DB::beginTransaction();

        try {
            $this->completeReservedTokenIfNeeded($session);

            $session->update([
                'status' => 'completed',
                'actual_ended_at' => now(),
                'last_activity_at' => now(),
                'recovery_deadline_at' => null,
            ]);

            $this->logStateTransition($session, $fromState, 'completed', optional($request->user())->id, 'Session ended by participant', [
                'ended_at' => now()->toISOString(),
            ]);

            DB::commit();

            $this->stopDailyCaptureIfNeeded($session->fresh(['videoDetail', 'recording']));

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);
            $this->notificationService->sessionStateChanged($session, $fromState, 'completed', optional($request->user())->id);

            return $this->success(
                $this->presentSession($session, $request->user()),
                'Session ended successfully'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validateSession(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $this->expireInterruptedSessionIfNeeded($session, optional($request->user())->id, 'validate');

        return $this->success($this->buildValidationSnapshot($session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']), $request->user()));
    }

    public function saveFeedback(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_notes' => ['nullable', 'string'],
        ]);

        $recording = $this->ensureSessionRecording($session);
        $recording->update([
            'feedback_rating' => $validated['feedback_rating'] ?? null,
            'feedback_notes' => $validated['feedback_notes'] ?? null,
            'feedback_submitted_by_user_id' => $user->id,
        ]);

        return $this->success($recording->fresh(), 'Feedback submitted successfully');
    }

    public function coachResponse(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'action' => ['required', 'in:accept,accept_5min,accept_10min,decline,reschedule'],
        ]);

        $action = $validated['action'];
        $fromState = $session->status;
        $nextState = $fromState;

        if (in_array($action, ['decline', 'reschedule'], true)) {
            $nextState = 'failed';
        }

        DB::beginTransaction();

        try {
            if ($nextState === 'failed') {
                $this->refundReservedTokenIfNeeded($session);
                $session->update([
                    'status' => 'failed',
                    'actual_ended_at' => now(),
                    'failure_reason' => 'Coach declined or requested reschedule',
                ]);
            }

            $this->logStateTransition($session, $fromState, $nextState, $user->id, 'Coach response recorded', [
                'action' => $action,
                'responded_at' => now()->toISOString(),
            ]);

            DB::commit();

            $session = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);

            if ($fromState !== $nextState) {
                $this->notificationService->sessionStateChanged($session, $fromState, $nextState, $user->id);
            }

            return $this->success($this->presentSession($session, $user), 'Coach response recorded');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to record coach response',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private function expireInterruptedSessionIfNeeded(CoachingSession $session, ?int $changedBy = null, string $source = 'system'): void
    {
        if ($this->normalizeState((string) $session->status) !== 'interrupted') {
            return;
        }

        if (!$session->recovery_deadline_at || now()->lessThanOrEqualTo($session->recovery_deadline_at)) {
            return;
        }

        DB::transaction(function () use ($session, $changedBy, $source) {
            $fresh = CoachingSession::query()->lockForUpdate()->find($session->id);

            if (!$fresh || $this->normalizeState((string) $fresh->status) !== 'interrupted') {
                return;
            }

            if (!$fresh->recovery_deadline_at || now()->lessThanOrEqualTo($fresh->recovery_deadline_at)) {
                return;
            }

            $fromState = $fresh->status;

            $this->refundReservedTokenIfNeeded($fresh);

            $fresh->update([
                'status' => 'failed',
                'actual_ended_at' => $fresh->actual_ended_at ?: now(),
                'last_activity_at' => now(),
                'recovery_deadline_at' => null,
                'failure_reason' => $fresh->failure_reason ?: 'Recovery window expired after interruption.',
                'recovery_context' => $this->mergeRecoveryContext(
                    $fresh->recovery_context,
                    [
                        'expired_to_failed_at' => now()->toISOString(),
                        'expired_to_failed_source' => $source,
                    ]
                ),
            ]);

            $this->logStateTransition(
                $fresh,
                $fromState,
                'failed',
                $changedBy,
                'Recovery window expired after interruption.',
                ['source' => $source]
            );
        });

        $refreshed = $session->fresh(['coach', 'client', 'videoDetail', 'recording', 'participants', 'stateLogs', 'introRequest.preferredCoach:id,name,title,timezone', 'introRequest.assignedCoach:id,name,title,timezone']);
        if ($refreshed) {
            $this->stopDailyCaptureIfNeeded($refreshed->fresh(['videoDetail', 'recording']));
            $this->notificationService->sessionStateChanged($refreshed, 'interrupted', 'failed', $changedBy);
            $session->fill($refreshed->getAttributes());
            $session->setRelations($refreshed->getRelations());
        }
    }

    private function shouldSkipTokenLifecycle(): bool
    {
        return app()->environment('local')
            && !filter_var((string) env('ENABLE_LOCAL_SESSION_BILLING', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function presentSession(CoachingSession $session, $user): array
    {
        $fresh = $session->fresh([
            'coach',
            'client',
            'videoDetail',
            'recording',
            'participants',
            'introRequest.preferredCoach:id,name,title,timezone',
            'introRequest.assignedCoach:id,name,title,timezone',
            'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
        ]);

        $displayTimezone = $this->resolvePresentationTimezone($fresh, $user);
        $payload = $fresh->toArray();
        $payload['status'] = $this->normalizeState((string) $fresh->status);
        $payload['raw_status'] = $fresh->status;
        $payload['scheduled_time'] = optional($fresh->scheduled_time)?->toISOString();
        $payload['scheduled_time_local'] = optional($fresh->scheduled_time)?->copy()->setTimezone($displayTimezone)->toIso8601String();
        $payload['actual_started_at'] = optional($fresh->actual_started_at)?->toISOString();
        $payload['actual_ended_at'] = optional($fresh->actual_ended_at)?->toISOString();
        $payload['last_activity_at'] = optional($fresh->last_activity_at)?->toISOString();
        $payload['last_interrupted_at'] = optional($fresh->last_interrupted_at)?->toISOString();
        $payload['recovery_deadline_at'] = optional($fresh->recovery_deadline_at)?->toISOString();
        $payload['timezone_context'] = [
            'display_timezone' => $displayTimezone,
            'viewer_timezone' => $displayTimezone,
            'coach_timezone' => Timezone::normalize($fresh->coach?->timezone, 'UTC'),
            'client_requested_timezone' => $this->resolveClientRequestedTimezone($fresh),
            'scheduled_time_for_viewer' => optional($fresh->scheduled_time)?->copy()->setTimezone($displayTimezone)->toIso8601String(),
        ];
        $payload['recording'] = $this->serializeRecording($fresh->recording);
        $payload['source_request'] = $this->serializeSourceRequest($fresh->introRequest);
        $payload['intro_request'] = $payload['source_request'];
        $payload['join_validation'] = $this->buildValidationSnapshot($fresh, $user);
        $payload['recovery'] = [
            'recovery_attempts' => (int) ($fresh->recovery_attempts ?? 0),
            'last_interrupted_at' => optional($fresh->last_interrupted_at)?->toISOString(),
            'recovery_deadline_at' => optional($fresh->recovery_deadline_at)?->toISOString(),
            'failure_reason' => $fresh->failure_reason,
        ];
        $payload['billing'] = [
            'token_cost' => $this->tokenCostForSession($fresh),
            'is_intro_session' => (bool) ($fresh->is_intro_session ?? false),
            'display_price' => $this->tokenCostForSession($fresh) === 0 ? 'Free' : $this->tokenCostForSession($fresh) . ' token',
        ];

        return $payload;
    }

    private function buildJoinPayload(CoachingSession $session, $user, ?SessionParticipant $participant = null): array
    {
        $role = $this->resolveRole($session, $user);

        $meetingToken = $this->buildMeetingToken($session, $user);

        return [
            'session_id' => $session->id,
            'video_join_url' => optional($session->videoDetail)->video_join_url,
            'video_join_url_with_token' => optional($session->videoDetail)->video_join_url,
            'meeting_token' => $meetingToken,
            'daily_room_name' => optional($session->videoDetail)->daily_room_name,
            'status' => $session->status,
            'recording' => $session->recording,
            'display_name' => $user->name,
            'role' => $role,
            'is_owner' => $role === 'coach',
            'expires_at' => optional($session->recovery_deadline_at)->toISOString(),
            'participant' => $participant?->fresh(),
            'validation' => $this->buildValidationSnapshot($session, $user),
            'billing' => [
                'token_cost' => $this->tokenCostForSession($session),
                'is_intro_session' => (bool) ($session->is_intro_session ?? false),
                'display_price' => $this->tokenCostForSession($session) === 0 ? 'Free' : $this->tokenCostForSession($session) . ' token',
            ],
        ];
    }

    private function resolvePresentationTimezone(CoachingSession $session, $user): string
    {
        $role = $this->resolveRole($session, $user);

        if ($role === 'coach') {
            return Timezone::normalize($session->coach?->timezone, 'UTC');
        }

        if ($role === 'client') {
            return $this->resolveClientRequestedTimezone($session);
        }

        return $this->resolveClientRequestedTimezone($session);
    }

    private function resolveClientRequestedTimezone(CoachingSession $session): string
    {
        if ($session->introRequest && filled($session->introRequest->viewer_timezone)) {
            return Timezone::normalize((string) $session->introRequest->viewer_timezone, 'UTC');
        }

        $scheduledLog = $session->relationLoaded('stateLogs')
            ? $session->stateLogs
                ->sortByDesc(fn ($log) => optional($log->created_at)?->getTimestamp() ?? 0)
                ->first(fn ($log) => $this->normalizeState((string) $log->to_state) === 'scheduled')
            : $session->stateLogs()
                ->where('to_state', 'scheduled')
                ->orderByDesc('created_at')
                ->first();

        return Timezone::normalize(data_get($scheduledLog?->metadata ?? [], 'viewer_timezone'), 'UTC');
    }

    private function serializeSourceRequest($introRequest): ?array
    {
        if (!$introRequest) {
            return null;
        }

        return [
            'id' => (int) $introRequest->id,
            'status' => $introRequest->status,
            'goal_summary' => $introRequest->goal_summary,
            'request_notes' => $introRequest->request_notes,
            'admin_notes' => $introRequest->admin_notes,
            'viewer_timezone' => $introRequest->viewer_timezone,
            'preferred_coach' => $introRequest->preferredCoach ? [
                'id' => (int) $introRequest->preferredCoach->id,
                'name' => $introRequest->preferredCoach->name,
                'title' => $introRequest->preferredCoach->title,
            ] : null,
            'assigned_coach' => $introRequest->assignedCoach ? [
                'id' => (int) $introRequest->assignedCoach->id,
                'name' => $introRequest->assignedCoach->name,
                'title' => $introRequest->assignedCoach->title,
            ] : null,
        ];
    }

    private function serializeRecording(?SessionRecording $recording): ?array
    {
        if (!$recording) {
            return null;
        }

        return [
            'transcription_status' => $recording->transcription_status,
            'transcript' => $recording->transcript,
            'transcript_available' => filled($recording->transcript),
            'transcript_preview' => filled($recording->transcript) ? Str::limit((string) $recording->transcript, 220) : null,
            'transcript_word_count' => str_word_count((string) $recording->transcript),
            'summary_ready' => filled($recording->post_session_summary) || filled($recording->ai_summary) || filled($recording->pre_session_summary),
            'recording_url' => $recording->recording_url,
            'ai_summary' => $recording->ai_summary,
            'pre_session_summary' => $recording->pre_session_summary,
            'post_session_summary' => $recording->post_session_summary,
            'next_actions' => is_array($recording->next_actions) ? $recording->next_actions : [],
            'key_topics' => is_array($recording->key_topics) ? $recording->key_topics : [],
            'privacy_settings' => $recording->privacy_settings ?? [],
            'feedback_rating' => $recording->feedback_rating,
        ];
    }

    private function buildValidationSnapshot(CoachingSession $session, $user): array
    {
        $now = now();
        $scheduledTime = $session->scheduled_time;
        $joinOpensAt = $scheduledTime?->copy()->subMinutes(self::PRE_JOIN_MINUTES);
        $joinClosesAt = $scheduledTime?->copy()->addMinutes(max((int) $session->duration_minutes, 15) + self::RECOVERY_BUFFER_MINUTES);

        $status = $this->normalizeState($session->status);
        $isCompleted = $status === 'completed';
        $isExpired = $joinClosesAt ? $now->greaterThan($joinClosesAt) : false;
        $roomReady = !empty(optional($session->videoDetail)->video_join_url);
        $manualRecoveryAllowed = $this->canManualRecover($session, $user);

        $canJoinByStatus = in_array($status, ['scheduled', 'live', 'interrupted'], true);
        $canJoinByTime = !$joinOpensAt || !$joinClosesAt
            ? true
            : $now->greaterThanOrEqualTo($joinOpensAt) && $now->lessThanOrEqualTo($joinClosesAt);

        $canJoin = !$isCompleted && !$isExpired && $canJoinByStatus && $canJoinByTime;

        $message = null;
        $recommendedAction = 'join_now';

        if ($isCompleted) {
            $recommendedAction = 'view_summary';
            $message = 'This session has already completed.';
        } elseif ($status === 'failed') {
            $recommendedAction = $manualRecoveryAllowed ? 'manual_recovery' : 'contact_support';
            $message = $session->failure_reason ?: 'This session has been marked failed.';
            $canJoin = false;
        } elseif ($status === 'interrupted') {
            $recommendedAction = 'resume_session';
            $message = 'This session was interrupted and can be resumed.';
        } elseif ($joinOpensAt && $now->lessThan($joinOpensAt)) {
            $recommendedAction = 'wait_for_session_time';
            $message = 'The join window has not opened yet.';
            $canJoin = false;
        } elseif ($isExpired) {
            $recommendedAction = $manualRecoveryAllowed ? 'manual_recovery' : 'contact_support';
            $message = 'The session join window has expired.';
            $canJoin = false;
        } elseif (!$roomReady) {
            $recommendedAction = $manualRecoveryAllowed ? 'refresh_room' : 'contact_support';
            $message = 'The session room needs to be refreshed before joining.';
            $canJoin = $manualRecoveryAllowed;
        } elseif ($status === 'live') {
            $recommendedAction = 'rejoin_now';
            $message = 'The session is live and ready to rejoin.';
        }

        $recoveryAvailableUntil = $session->recovery_deadline_at ?: $joinClosesAt;

        return [
            'valid' => $canJoin || !$isCompleted,
            'can_join' => $canJoin,
            'session_id' => $session->id,
            'session_status' => $status,
            'scheduled_time' => optional($scheduledTime)?->toISOString(),
            'actual_started_at' => optional($session->actual_started_at)?->toISOString(),
            'actual_ended_at' => optional($session->actual_ended_at)?->toISOString(),
            'join_window' => [
                'opens_at' => optional($joinOpensAt)?->toISOString(),
                'closes_at' => optional($joinClosesAt)?->toISOString(),
            ],
            'room_ready' => $roomReady,
            'needs_recovery' => $status === 'interrupted' || !$roomReady || ((int) ($session->recovery_attempts ?? 0) > 0 && $status !== 'completed'),
            'manual_recovery_allowed' => $manualRecoveryAllowed,
            'recommended_action' => $recommendedAction,
            'message' => $message,
            'last_activity_at' => optional($session->last_activity_at)?->toISOString(),
            'last_interrupted_at' => optional($session->last_interrupted_at)?->toISOString(),
            'recovery_available_until' => optional($recoveryAvailableUntil)?->toISOString(),
            'failure_reason' => $session->failure_reason,
            'recovery_attempts' => (int) ($session->recovery_attempts ?? 0),
            'role' => $this->resolveRole($session, $user),
        ];
    }

    private function canManualRecover(CoachingSession $session, $user): bool
    {
        if (!$user) {
            return false;
        }

        return (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (int) optional($session->coach)->user_id === (int) $user->id;
    }

    private function resolveRole(CoachingSession $session, $user): string
    {
        if ((int) optional($session->coach)->user_id === (int) $user->id) {
            return 'coach';
        }

        if ((int) $session->client_id === (int) $user->id) {
            return 'client';
        }

        return 'admin';
    }

    private function logStateTransition(CoachingSession $session, ?string $fromState, string $toState, ?int $changedBy, ?string $reason = null, ?array $metadata = null): void
    {
        SessionStateLog::create([
            'session_id' => $session->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    private function touchParticipant(CoachingSession $session, $user, string $role, array $attributes = []): SessionParticipant
    {
        $participant = SessionParticipant::firstOrCreate(
            [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'role' => in_array($role, ['coach', 'client'], true) ? $role : 'guest',
            ],
            [
                'display_name' => $user->name,
            ]
        );

        $participant->update(array_merge([
            'display_name' => $user->name,
        ], $attributes));

        return $participant;
    }

    private function createOrRefreshVideoRoom(CoachingSession $session, bool $forceRefresh = false): SessionVideoDetail
    {
        if (!$forceRefresh && $session->videoDetail && !empty($session->videoDetail->video_join_url)) {
            return $session->videoDetail;
        }

        $room = $this->dailyService->createRoom();

        return SessionVideoDetail::updateOrCreate(
            ['session_id' => $session->id],
            [
                'video_room_id' => $room['id'] ?? optional($session->videoDetail)->video_room_id,
                'video_join_url' => $room['url'],
                'daily_room_name' => $room['name'],
                'room_created_at' => now(),
            ]
        );
    }

    private function buildMeetingToken(CoachingSession $session, $user): ?string
    {
        $roomName = optional($session->videoDetail)->daily_room_name;

        if (!$roomName || $this->dailyService->usingFakeRoom() || !$this->dailyService->isConfigured()) {
            return null;
        }

        try {
            return $this->dailyService->createMeetingToken($roomName, [
                'nbf' => now()->subMinutes(10)->timestamp,
                'exp' => now()->addHours(4)->timestamp,
                'is_owner' => (int) optional($session->coach)->user_id === (int) $user->id,
                'user_name' => (string) ($user->name ?? 'Participant'),
                'user_id' => (string) $user->id,
                'eject_after_elapsed' => max(1800, (((int) ($session->duration_minutes ?? 15)) + 20) * 60),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Daily meeting token generation failed', [
                'session_id' => $session->id,
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function stopDailyCaptureIfNeeded(CoachingSession $session): void
    {
        $roomName = optional($session->videoDetail)->daily_room_name;
        $recording = $session->recording ?: $this->ensureSessionRecording($session);
        $privacy = $recording->privacy_settings ?? [];

        if (!$roomName || $this->dailyService->usingFakeRoom() || !$this->dailyService->isConfigured()) {
            return;
        }

        if (($privacy['transcription_consent'] ?? 'none') === 'full') {
            try {
                $payload = array_filter([
                    'instanceId' => $recording->daily_transcript_instance_id,
                ]);
                $this->dailyService->stopTranscription($roomName, $payload);
            } catch (\Throwable $e) {
                Log::info('Daily transcription stop skipped', [
                    'session_id' => $session->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (($privacy['recording_enabled'] ?? false) === true) {
            try {
                $payload = array_filter([
                    'instanceId' => $recording->daily_recording_instance_id,
                    'type' => 'cloud',
                ]);
                $this->dailyService->stopRecording($roomName, $payload);
            } catch (\Throwable $e) {
                Log::info('Daily recording stop skipped', [
                    'session_id' => $session->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function mergeRecoveryContext($existing, array $extra): array
    {
        $base = is_array($existing) ? $existing : [];

        return array_merge($base, $extra);
    }

    private function normalizeState(string $state): string
    {
        return match ($state) {
            'in_progress' => 'live',
            'cancelled', 'no_show' => 'failed',
            default => $state,
        };
    }

    private function ensureSessionRecording(CoachingSession $session): SessionRecording
    {
        return SessionRecording::firstOrCreate(
            ['session_id' => $session->id],
            [
                'provider_name' => 'daily',
                'transcription_status' => 'inactive',
                'privacy_settings' => [
                    'recording_enabled' => false,
                    'transcription_consent' => 'none',
                ],
            ]
        );
    }

    private function findLatestPaymentTransaction(CoachingSession $session): ?Transaction
    {
        return Transaction::where('session_id', $session->id)
            ->where('transaction_type', 'coach_payment')
            ->latest()
            ->first();
    }

    private function reserveTokenIfNeeded(CoachingSession $session): ?string
    {
        if ($this->shouldSkipTokenLifecycle()) {
            return null;
        }

        $existingPayment = $this->findLatestPaymentTransaction($session);

        if ($existingPayment && in_array($existingPayment->status, ['pending', 'completed'], true)) {
            return null;
        }

        $tokenCost = $this->tokenCostForSession($session);

        if ($tokenCost <= 0) {
            return null;
        }

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $session->client_id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        if ($wallet->coin_balance < $tokenCost) {
            return 'Insufficient tokens to start this session.';
        }

        $wallet->decrement('coin_balance', $tokenCost);

        Transaction::create([
            'user_id' => $session->client_id,
            'coach_id' => $session->coach_id,
            'session_id' => $session->id,
            'transaction_type' => 'coach_payment',
            'coin_amount' => $tokenCost,
            'amount_currency' => 'TOKEN',
            'amount_fiat' => null,
            'status' => 'pending',
        ]);

        return null;
    }

    private function completeReservedTokenIfNeeded(CoachingSession $session): void
    {
        if ($this->shouldSkipTokenLifecycle()) {
            return;
        }

        if ($this->tokenCostForSession($session) <= 0) {
            return;
        }

        $payment = $this->findLatestPaymentTransaction($session);

        if (!$payment || $payment->status !== 'pending') {
            return;
        }

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $session->client_id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        $wallet->increment('total_coins_spent', $payment->coin_amount);

        $payment->update([
            'status' => 'completed',
        ]);
    }

    private function refundReservedTokenIfNeeded(CoachingSession $session): void
    {
        if ($this->shouldSkipTokenLifecycle()) {
            return;
        }

        if ($this->tokenCostForSession($session) <= 0) {
            return;
        }

        $payment = $this->findLatestPaymentTransaction($session);

        if (!$payment || $payment->status !== 'pending') {
            return;
        }

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $session->client_id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        $wallet->increment('coin_balance', $payment->coin_amount);

        $payment->update([
            'status' => 'refunded',
        ]);

        Transaction::create([
            'user_id' => $session->client_id,
            'coach_id' => $session->coach_id,
            'session_id' => $session->id,
            'transaction_type' => 'refund',
            'coin_amount' => $payment->coin_amount,
            'amount_currency' => 'TOKEN',
            'amount_fiat' => null,
            'status' => 'completed',
        ]);
    }

    private function tokenCostForSession(CoachingSession $session): int
    {
        return max(0, (int) round((float) ($session->price_amount ?? 1)));
    }
}
