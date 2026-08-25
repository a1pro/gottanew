<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Mail\CoachInvitationMail;
use App\Models\Coach\Coach;
use App\Models\Coach\PendingCoachApplication;
use App\Models\CoachInformationRequest;
use App\Models\Core\UserRole;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Models\User;
use App\Services\Ai\SessionInsightService;
use App\Services\Video\DailyRestApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\Communication\UserNotification;

class AdminController extends BaseController
{
    public function __construct(
        private DailyRestApiService $dailyService,
        private SessionInsightService $sessionInsightService,
    ) {
    }

    public function users(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $role = trim((string) $request->get('role', ''));

        $users = User::query()
            ->with([
                'roles:id,user_id,role',
                'wallet:user_id,coin_balance,total_coins_purchased,total_coins_spent',
                'coachProfile:id,user_id,title,is_active,timezone',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->whereHas('roles', function ($sub) use ($role) {
                    $sub->where('role', $role);
                });
            })
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        $users->setCollection(
            $users->getCollection()->map(fn (User $user) => $this->serializeUser($user))
        );

        return $this->success($users);
    }

    public function changeUserRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,coach,client'],
        ]);

        $user = User::findOrFail($id);

        DB::beginTransaction();

        try {
            UserRole::where('user_id', $user->id)->delete();

            $role = UserRole::create([
                'user_id' => $user->id,
                'role' => $validated['role'],
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
            ]);

            if ($validated['role'] === 'coach' && !$user->coachProfile()->exists()) {
                Coach::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $user->name,
                        'title' => 'Coach',
                        'bio' => 'Coach profile created by admin.',
                        'years_experience' => 1,
                        'specialties' => [],
                        'similar_experiences' => [],
                        'notification_email' => $user->email,
                        'timezone' => 'UTC',
                        'is_active' => true,
                        'available_now' => false,
                    ]
                );
            }

            DB::commit();

            return $this->success([
                'user' => $user->load(['roles:id,user_id,role']),
                'role' => $role->role,
            ], 'User role updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Failed to update user role', 500);
        }
    }

    public function coaches(Request $request)
    {
        $coaches = Coach::query()
            ->with([
                'coachApplication',
                'informationRequests' => function ($query) {
                    $query->latest();
                },
            ])
            ->latest()
            ->paginate((int) $request->get('per_page', 10));
    
        return $this->success($coaches);
    }

    public function updateStatus(Request $request, $id)
    {
        $coach = Coach::findOrFail($id);

        $coach->is_active = $request->is_active;
        $coach->save();

        return response()->json([
            'message' => 'Coach status updated successfully'
        ]);
    }

    public function coachInformationRequests($id)
    {
        $coach = Coach::with([
            'informationRequests' => function ($query) {
                $query->latest();
            }
        ])->findOrFail($id);
    
        return $this->success([
            'coach' => $coach,
            'information_requests' => $coach->informationRequests,
        ]);
    }
    
   public function pendingApplications(Request $request)
   {
       $applications = PendingCoachApplication::whereIn('status', [
           'pending',
           'invited',
           'needs_information'
       ])
       ->latest()
       ->paginate((int) $request->get('per_page', 10));
   
       return $this->success($applications);
   }

    public function sessions(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,user_id,notification_email',
                'coach.user:id,email',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings,recording_url,feedback_rating',
                'transactions:id,session_id,status,transaction_type,coin_amount,amount_fiat,amount_currency,created_at',
            ])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('id', $q)
                        ->orWhere('client_notes', 'like', "%{$q}%")
                        ->orWhere('coach_notes', 'like', "%{$q}%")
                        ->orWhereHas('client', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        })
                        ->orWhereHas('coach', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%")
                                ->orWhere('notification_email', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        $sessions->setCollection(
            $sessions->getCollection()->map(fn (CoachingSession $session) => $this->serializeSession($session))
        );

        return $this->success($sessions);
    }

    public function failedSessions(Request $request)
    {
        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,user_id',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(5),
            ])
            ->whereIn('status', ['interrupted', 'failed', 'cancelled', 'no_show'])
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($sessions);
    }

    public function transcripts(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $query = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,title',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
                'introRequest:id,approved_session_id,status,goal_summary,request_notes,admin_notes,viewer_timezone,approved_at',
            ])
            ->whereHas('recording');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder
                    ->whereHas('client', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->orWhereHas('coach', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('title', 'like', "%{$q}%");
                    })
                    ->orWhereHas('recording', function ($sub) use ($q) {
                        $sub->where('transcript', 'like', "%{$q}%")
                            ->orWhere('ai_summary', 'like', "%{$q}%")
                            ->orWhere('pre_session_summary', 'like', "%{$q}%")
                            ->orWhere('post_session_summary', 'like', "%{$q}%");
                    })
                    ->orWhereHas('introRequest', function ($sub) use ($q) {
                        $sub->where('goal_summary', 'like', "%{$q}%")
                            ->orWhere('request_notes', 'like', "%{$q}%")
                            ->orWhere('admin_notes', 'like', "%{$q}%");
                    });
            });
        }

        // $summaryBase = CoachingSession::query()->whereHas('recording');
        $summaryBase = clone $query;

        $sessions = $query
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success([
            'data' => $sessions,
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'intro_sessions' => (clone $summaryBase)->where('is_intro_session', true)->count(),
                'with_transcript' => (clone $summaryBase)->whereHas('recording', fn ($sub) => $sub->whereNotNull('transcript')->where('transcript', '!=', ''))->count(),
                'with_ai_summary' => (clone $summaryBase)->whereHas('recording', fn ($sub) => $sub->whereNotNull('ai_summary')->where('ai_summary', '!=', ''))->count(),
            ],
        ]);
    }

    public function transcript($id)
    {
        $session = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,title',
                'recording',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'introRequest:id,approved_session_id,status,goal_summary,request_notes,admin_notes,viewer_timezone,approved_at',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
            ])
            ->findOrFail($id);

        return $this->success($session);
    }

    public function syncTranscript(Request $request, $id)
    {
        $validated = $request->validate([
            'start_capture' => ['nullable', 'boolean'],
            'force_capture_restart' => ['nullable', 'boolean'],
            'generate_ai_summary' => ['nullable', 'boolean'],
        ]);

        $session = $this->loadTranscriptSession((int) $id);

        if ($this->dailyService->usingFakeRoom()) {
            return $this->error('Daily sync is unavailable while fake Daily rooms are enabled.', 422);
        }

        if (!$this->dailyService->isConfigured()) {
            return $this->error('Daily is not configured on this environment.', 422);
        }

        $recording = $session->recording ?: $this->ensureRecording($session);
        $captureStarted = ['recording' => false, 'transcription' => false];

        if ($validated['start_capture'] ?? false) {
            $captureStarted = $this->startCaptureIfNeeded($session, $recording, (bool) ($validated['force_capture_restart'] ?? false));
            $session = $this->loadTranscriptSession((int) $id);
            $recording = $session->recording ?: $recording->fresh();
        }

        [$recording, $syncMeta] = $this->syncDailyAssetsForSession($session, $recording, (bool) ($validated['generate_ai_summary'] ?? true));
        $session = $this->loadTranscriptSession((int) $id);

        return $this->success([
            'session' => $session,
            'synced' => array_merge($syncMeta, [
                'capture_started' => $captureStarted,
            ]),
            'verification' => $this->buildDailyVerification($session),
        ], ($syncMeta['transcript'] || $captureStarted['recording'] || $captureStarted['transcription']) ? 'Transcript assets refreshed successfully' : 'No new Daily assets were available to sync');
    }

    public function generateSummary(Request $request, $id)
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:pre,post,both'],
        ]);

        $mode = (string) ($validated['mode'] ?? 'post');
        $session = CoachingSession::query()
            ->with(['client', 'coach', 'recording'])
            ->findOrFail($id);

        if (in_array($mode, ['pre', 'both'], true)) {
            $this->sessionInsightService->generatePreSessionSummary($session->fresh(['client', 'coach', 'recording']), true);
        }

        if (in_array($mode, ['post', 'both'], true)) {
            $this->sessionInsightService->generatePostSessionSummary($session->fresh(['client', 'coach', 'recording']), true);
        }

        return $this->success($this->loadTranscriptSession((int) $id), 'AI summaries regenerated successfully');
    }

    public function verifyDaily(Request $request, $id)
    {
        $session = $this->loadTranscriptSession((int) $id);

        return $this->success([
            'session' => $session,
            'verification' => $this->buildDailyVerification($session),
        ]);
    }

    private function loadTranscriptSession(int $id): CoachingSession
    {
        return CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,title,timezone,user_id,notification_email',
                'coach.user:id,email',
                'recording',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'introRequest:id,approved_session_id,status,goal_summary,request_notes,admin_notes,viewer_timezone,approved_at',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
            ])
            ->findOrFail($id);
    }

    private function ensureRecording(CoachingSession $session): SessionRecording
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

    private function startCaptureIfNeeded(CoachingSession $session, SessionRecording $recording, bool $force = false): array
    {
        $roomName = optional($session->videoDetail)->daily_room_name;
        $privacy = is_array($recording->privacy_settings) ? $recording->privacy_settings : [];
        $providerMetadata = is_array($recording->provider_metadata) ? $recording->provider_metadata : [];
        $captureStarted = [
            'recording' => false,
            'transcription' => false,
        ];

        if (!$roomName || $this->dailyService->usingFakeRoom() || !$this->dailyService->isConfigured()) {
            return $captureStarted;
        }

        $recordingStatus = data_get($providerMetadata, 'daily.recording.status');
        $transcriptionStatus = data_get($providerMetadata, 'daily.transcript.status');

        if (($privacy['recording_enabled'] ?? false) === true && ($force || !in_array($recordingStatus, ['active', 'completed'], true))) {
            try {
                $response = $this->dailyService->startRecording($roomName, ['type' => 'cloud']);
                $captureStarted['recording'] = true;
                $providerMetadata = array_replace_recursive($providerMetadata, [
                    'daily' => [
                        'recording' => [
                            'status' => 'active',
                            'started_at' => now()->toISOString(),
                            'start_response' => $response,
                        ],
                    ],
                ]);

                $recording->update([
                    'provider_name' => 'daily',
                    'daily_recording_id' => $response['recording_id'] ?? $recording->daily_recording_id,
                    'daily_recording_instance_id' => $response['instanceId'] ?? $recording->daily_recording_instance_id,
                    'provider_metadata' => $providerMetadata,
                ]);
            } catch (\Throwable $e) {
                $captureStarted['recording_error'] = $e->getMessage();
            }
        }

        if (($privacy['transcription_consent'] ?? 'none') === 'full' && ($force || !in_array($transcriptionStatus, ['active', 'completed'], true))) {
            try {
                $response = $this->dailyService->startTranscription($roomName);
                $captureStarted['transcription'] = true;
                $recording->refresh();
                $providerMetadata = array_replace_recursive(is_array($recording->provider_metadata) ? $recording->provider_metadata : $providerMetadata, [
                    'daily' => [
                        'transcript' => [
                            'status' => 'active',
                            'started_at' => now()->toISOString(),
                            'start_response' => $response,
                        ],
                    ],
                ]);

                $recording->update([
                    'provider_name' => 'daily',
                    'daily_transcript_id' => $response['id'] ?? $recording->daily_transcript_id,
                    'daily_transcript_instance_id' => $response['instanceId'] ?? $recording->daily_transcript_instance_id,
                    'transcription_status' => 'active',
                    'provider_metadata' => $providerMetadata,
                ]);
            } catch (\Throwable $e) {
                $captureStarted['transcription_error'] = $e->getMessage();
            }
        }

        return $captureStarted;
    }

    private function syncDailyAssetsForSession(CoachingSession $session, SessionRecording $recording, bool $generateAiSummary = true): array
    {
        $provider = is_array($recording->provider_metadata) ? $recording->provider_metadata : [];
        $dailyMetadata = is_array($provider['daily'] ?? null) ? $provider['daily'] : [];
        $transcriptMetadata = is_array($dailyMetadata['transcript'] ?? null) ? $dailyMetadata['transcript'] : [];
        $recordingMetadata = is_array($dailyMetadata['recording'] ?? null) ? $dailyMetadata['recording'] : [];

        $transcriptText = $this->dailyService->resolveTranscriptText(
            $recording->daily_transcript_id,
            Arr::first([
                $transcriptMetadata['access_link'] ?? null,
                $transcriptMetadata['download_link'] ?? null,
                $transcriptMetadata['link'] ?? null,
                data_get($transcriptMetadata, 'out_params.access_link'),
                data_get($transcriptMetadata, 'out_params.download_link'),
                data_get($transcriptMetadata, 'out_params.link'),
            ], static fn ($value) => is_string($value) && trim($value) !== '')
        );

        $downloadLink = $this->dailyService->resolveRecordingDownloadLink($recording->daily_recording_id)
            ?? Arr::first([
                $recording->recording_url,
                $recordingMetadata['access_link'] ?? null,
                $recordingMetadata['download_link'] ?? null,
            ], static fn ($value) => is_string($value) && trim($value) !== '');

        $updates = [
            'provider_name' => 'daily',
        ];

        if (is_string($transcriptText) && trim($transcriptText) !== '') {
            $updates['transcript'] = trim($transcriptText);
            $updates['transcription_status'] = 'completed';
        }

        if (is_string($downloadLink) && trim($downloadLink) !== '') {
            $updates['recording_url'] = trim($downloadLink);
        }

        if (count($updates) > 1) {
            $recording->update($updates);
            $recording = $recording->fresh();
        }

        if ($generateAiSummary && is_string($transcriptText) && trim($transcriptText) !== '') {
            try {
                $this->sessionInsightService->generatePostSessionSummary($session->fresh(['client', 'coach', 'recording']), true);
            } catch (\Throwable) {
                // Best effort only.
            }
        }

        return [$recording, [
            'transcript' => is_string($transcriptText) && trim($transcriptText) !== '',
            'recording_url' => is_string($downloadLink) && trim($downloadLink) !== '',
            'transcript_word_count' => is_string($transcriptText) ? str_word_count($transcriptText) : 0,
        ]];
    }

    private function buildDailyVerification(CoachingSession $session): array
    {
        $recording = $session->recording ?: $this->ensureRecording($session);
        $roomName = (string) (optional($session->videoDetail)->daily_room_name ?? '');
        $roomReport = [
            'checked' => false,
            'exists' => false,
            'privacy' => null,
            'error' => null,
            'webhooks_detected' => null,
        ];

        if ($this->dailyService->isConfigured()) {
            if ($roomName !== '') {
                try {
                    $room = $this->dailyService->getRoom($roomName);
                    $roomReport['checked'] = true;
                    $roomReport['exists'] = true;
                    $roomReport['privacy'] = data_get($room, 'privacy') ?? data_get($room, 'config.privacy') ?? data_get($room, 'properties.privacy');
                    $roomReport['raw'] = $room;
                } catch (\Throwable $e) {
                    $roomReport['checked'] = true;
                    $roomReport['error'] = $e->getMessage();
                }
            }

            try {
                $webhooks = $this->dailyService->listWebhooks();
                $items = array_values(array_filter(is_array($webhooks) ? $webhooks : [], static fn ($item) => is_array($item)));
                $roomReport['webhooks_detected'] = count($items);
            } catch (\Throwable $e) {
                $roomReport['webhooks_error'] = $e->getMessage();
            }
        }

        $transcriptText = is_string($recording->transcript) ? trim($recording->transcript) : '';
        $privacySettings = is_array($recording->privacy_settings) ? $recording->privacy_settings : [];
        $providerMetadata = is_array($recording->provider_metadata) ? $recording->provider_metadata : [];

        $checks = [
            'daily_api_configured' => $this->dailyService->isConfigured(),
            'daily_fake_room_disabled' => !$this->dailyService->usingFakeRoom(),
            'daily_room_name_present' => $roomName !== '',
            'daily_room_url_present' => filled(optional($session->videoDetail)->video_join_url),
            'webhook_hmac_configured' => filled(config('services.daily.webhook_hmac')),
            'transcription_consent_full' => ($privacySettings['transcription_consent'] ?? 'none') === 'full',
            'recording_enabled' => ($privacySettings['recording_enabled'] ?? false) === true,
            'daily_transcript_id_present' => filled($recording->daily_transcript_id),
            'daily_recording_id_present' => filled($recording->daily_recording_id),
            'transcript_available' => $transcriptText !== '',
            'recording_url_available' => filled($recording->recording_url),
            'ai_summary_available' => filled($recording->post_session_summary) || filled($recording->ai_summary),
        ];

        if ($roomReport['privacy'] !== null) {
            $checks['daily_room_private'] = $roomReport['privacy'] === 'private';
        }

        $recommendations = [];

        if (!$checks['daily_api_configured']) {
            $recommendations[] = 'Set DAILY_API_KEY on the backend before testing transcript flow.';
        }
        if (!$checks['daily_fake_room_disabled']) {
            $recommendations[] = 'Set DAILY_USE_FAKE_ROOM=false for real Daily calls and webhooks.';
        }
        if (!$checks['daily_room_name_present']) {
            $recommendations[] = 'This session has no Daily room name yet. Regenerate the room before testing.';
        }
        if (!$checks['webhook_hmac_configured']) {
            $recommendations[] = 'Configure DAILY_WEBHOOK_HMAC so Daily webhook signatures can be verified.';
        }
        if (!$checks['transcription_consent_full']) {
            $recommendations[] = 'Client consent is not set to full transcription, so transcript capture will not complete.';
        }
        if (($checks['daily_api_configured'] ?? false) && ($checks['daily_room_name_present'] ?? false) && !$checks['daily_transcript_id_present'] && !$checks['transcript_available']) {
            $recommendations[] = 'Start capture or rejoin the coach workspace so Daily transcription can begin.';
        }
        if ($checks['daily_transcript_id_present'] && !$checks['transcript_available']) {
            $recommendations[] = 'Transcript job exists but text is not stored yet. Use Refresh transcript or check Daily webhook delivery.';
        }
        if ($checks['transcript_available'] && !$checks['ai_summary_available']) {
            $recommendations[] = 'Transcript exists but AI summary is missing. Run Generate summary.';
        }

        return [
            'checks' => $checks,
            'room' => $roomReport,
            'status' => [
                'session_status' => $session->status,
                'transcription_status' => $recording->transcription_status,
                'transcript_word_count' => $transcriptText !== '' ? str_word_count($transcriptText) : 0,
                'provider_metadata' => $providerMetadata,
            ],
            'recommendations' => $recommendations,
        ];
    }

    public function inviteCoach(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        DB::beginTransaction();

        try {
            $existingApplication = PendingCoachApplication::where('email', $validated['email'])->first();

            $application = PendingCoachApplication::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $existingApplication?->name ?? 'Coach Invite',
                    'phone' => $existingApplication?->phone,
                    'experience' => $existingApplication?->experience ?? 'Pending',
                    'specialties' => $existingApplication?->specialties ?? [],
                    'message' => $existingApplication?->message ?? 'Invited by admin',
                    'status' => 'invited',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]
            );

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $application->name ?: 'Coach',
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->send(new CoachInvitationMail($user->email, $plainToken));

            DB::commit();

            return $this->success([
                'application' => $application,
                'user' => $user,
            ], 'Coach invitation sent successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send coach invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approveApplication($id)
    {
        $application = PendingCoachApplication::findOrFail($id);

        DB::beginTransaction();

        try {
            $user = User::firstOrCreate(
                ['email' => $application->email],
                [
                    'name' => $application->name,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            if (!$user->name && $application->name) {
                $user->update(['name' => $application->name]);
            }

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            Coach::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $application->name,
                    'title' => 'Coach',
                    'bio' => $application->message ?: 'Coach profile pending completion.',
                    'years_experience' => is_numeric($application->experience) ? (int) $application->experience : 1,
                    'specialties' => $application->specialties ?? [],
                    'similar_experiences' => [],
                    'notification_email' => $user->email,
                    'timezone' => 'UTC',
                    'is_active' => true,
                    'available_now' => false,
                ]
            );

            $application->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->send(new CoachInvitationMail($user->email, $plainToken));

            DB::commit();

            return $this->success([], 'Coach approved successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve coach application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function requestInformation(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
    
        $coach = Coach::findOrFail($id);
    
        DB::beginTransaction();
    
        try {
            $informationRequest = CoachInformationRequest::create([
                'coach_id' => $coach->id,
                'admin_id' => Auth::id(),
                'message' => $validated['message'],
                'status' => 'pending',
            ]);
    
            // Create notification for the coach
            UserNotification::create([
                'user_id' => $coach->user_id,
                'category' => 'coach_communication',
                'priority' => 'high',
                'title' => 'Additional Information Requested',
                'body' => $validated['message'],
                'action_url' => '/coach-signup-request/' . $informationRequest->id,
                'channel' => 'in_app',
                'delivery_status' => 'sent',
                'sent_at' => now(),
                'metadata' => [
                    'information_request_id' => $informationRequest->id,
                    'coach_id' => $coach->id,
                ],
            ]);
    
            DB::commit();
    
            return $this->success(
                $informationRequest,
                'Information request sent successfully.'
            );
    
        } catch (\Throwable $e) {
            DB::rollBack();
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to request information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function coachCommunications($coachId)
    {
        $coach = Coach::findOrFail($coachId);
    
        $communications = CoachInformationRequest::query()
            ->where('coach_id', $coach->id)
            ->with('admin:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get();
    
        return $this->success([
            'coach' => [
                'id' => $coach->id,
                'name' => $coach->name,
                'email' => $coach->notification_email,
            ],
            'communications' => $communications,
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) ($user->is_active ?? true),
            'last_login_at' => optional($user->last_login_at)?->toISOString(),
            'created_at' => optional($user->created_at)?->toISOString(),
            'roles' => $user->roles->pluck('role')->values()->all(),
            'wallet' => $user->wallet ? [
                'coin_balance' => (int) ($user->wallet->coin_balance ?? 0),
                'total_coins_purchased' => (int) ($user->wallet->total_coins_purchased ?? 0),
                'total_coins_spent' => (int) ($user->wallet->total_coins_spent ?? 0),
            ] : null,
            'coach_profile' => $user->coachProfile ? [
                'title' => $user->coachProfile->title,
                'is_active' => (bool) $user->coachProfile->is_active,
                'timezone' => $user->coachProfile->timezone,
            ] : null,
        ];
    }

    private function serializeSession(CoachingSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'status' => $session->status,
            'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
            'duration_minutes' => (int) ($session->duration_minutes ?? 15),
            'price_amount' => (float) ($session->price_amount ?? 0),
            'price_currency' => $session->price_currency ?? 'USD',
            'is_intro_session' => (bool) ($session->is_intro_session ?? false),
            'client_notes' => $session->client_notes,
            'coach_notes' => $session->coach_notes,
            'failure_reason' => $session->failure_reason,
            'recovery_attempts' => (int) ($session->recovery_attempts ?? 0),
            'recovery_deadline_at' => optional($session->recovery_deadline_at)?->toISOString(),
            'actual_started_at' => optional($session->actual_started_at)?->toISOString(),
            'actual_ended_at' => optional($session->actual_ended_at)?->toISOString(),
            'client' => $session->client ? [
                'id' => (int) $session->client->id,
                'name' => $session->client->name,
                'email' => $session->client->email,
            ] : null,
            'coach' => $session->coach ? [
                'id' => (int) $session->coach->id,
                'name' => $session->coach->name,
                'email' => $session->coach->notification_email ?: $session->coach->user?->email,
                'user_id' => $session->coach->user_id ? (int) $session->coach->user_id : null,
            ] : null,
            'video_detail' => $session->videoDetail ? [
                'video_join_url' => $session->videoDetail->video_join_url,
                'daily_room_name' => $session->videoDetail->daily_room_name,
                'room_created_at' => optional($session->videoDetail->room_created_at)?->toISOString(),
            ] : null,
            'recording' => $session->recording ? [
                'transcription_status' => $session->recording->transcription_status,
                'transcript' => $session->recording->transcript,
                'ai_summary' => $session->recording->ai_summary,
                'pre_session_summary' => $session->recording->pre_session_summary,
                'post_session_summary' => $session->recording->post_session_summary,
                'next_actions' => $session->recording->next_actions ?? [],
                'key_topics' => $session->recording->key_topics ?? [],
                'privacy_settings' => $session->recording->privacy_settings ?? [],
                'recording_url' => $session->recording->recording_url,
                'feedback_rating' => $session->recording->feedback_rating,
                'has_ai_outputs' => (bool) ($session->recording->pre_session_summary || $session->recording->post_session_summary || $session->recording->ai_summary),
            ] : null,
            'transactions' => $session->transactions->map(fn ($transaction) => [
                'id' => (int) $transaction->id,
                'status' => $transaction->status,
                'type' => $transaction->transaction_type,
                'coin_amount' => (int) ($transaction->coin_amount ?? 0),
                'amount' => $transaction->amount_fiat,
                'currency' => $transaction->amount_currency,
                'created_at' => optional($transaction->created_at)?->toISOString(),
            ])->values()->all(),
        ];
    }
}
