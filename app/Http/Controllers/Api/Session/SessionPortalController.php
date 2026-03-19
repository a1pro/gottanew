<?php

namespace App\Http\Controllers\Api\Session;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\Transaction;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Models\Session\SessionStateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionPortalController extends BaseController
{
    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated');

        $session = CoachingSession::with([
            'coach',
            'client',
            'videoDetail',
            'recording',
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

        return $this->success($session);
    }

    public function join(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $joinUrl = optional($session->videoDetail)->video_join_url;

        if (!$joinUrl) {
            return $this->error('Video room link not found', 404);
        }

        return $this->success([
            'session_id' => $session->id,
            'video_join_url' => $joinUrl,
            'daily_room_name' => optional($session->videoDetail)->daily_room_name,
            'status' => $session->status,
            'recording' => $session->recording,
        ]);
    }

    public function saveConsent(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $validated = $request->validate([
            'recording_enabled' => ['nullable', 'boolean'],
            'transcription_consent' => ['required', 'in:full,basic,none'],
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
                $session->fresh(['coach', 'client', 'videoDetail', 'recording']),
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

            $session->update([
                'status' => $toState,
            ]);

            SessionStateLog::create([
                'session_id' => $session->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => optional($request->user())->id,
                'change_reason' => $validated['reason'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            DB::commit();

            return $this->success(
                $session->fresh(['coach', 'client', 'videoDetail', 'recording']),
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
            $session->fresh(['coach', 'client', 'videoDetail', 'recording']),
            'Session notes saved'
        );
    }

    public function end(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        if ($session->status === 'completed') {
            return $this->success(
                $session->fresh(['coach', 'client', 'videoDetail', 'recording']),
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
            ]);

            SessionStateLog::create([
                'session_id' => $session->id,
                'from_state' => $fromState,
                'to_state' => 'completed',
                'changed_by' => optional($request->user())->id,
                'change_reason' => 'Session ended by participant',
                'metadata' => [
                    'ended_at' => now()->toISOString(),
                ],
            ]);

            $recording = $this->ensureSessionRecording($session);
            if (($recording->privacy_settings['transcription_consent'] ?? 'none') === 'full' && empty($recording->transcript)) {
                $recording->update([
                    'transcription_status' => 'completed',
                    'ai_summary' => $recording->ai_summary ?: 'Transcript/AI summary pipeline pending implementation.',
                ]);
            }

            DB::commit();

            return $this->success(
                $session->fresh(['coach', 'client', 'videoDetail', 'recording']),
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
        if (app()->environment('local')) {
            return null;
        }

        $existingPayment = $this->findLatestPaymentTransaction($session);

        if ($existingPayment && in_array($existingPayment->status, ['pending', 'completed'], true)) {
            return null;
        }

        $tokenCost = max(1, (int) round((float) ($session->price_amount ?? 1)));

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
        if (app()->environment('local')) {
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
        if (app()->environment('local')) {
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
}