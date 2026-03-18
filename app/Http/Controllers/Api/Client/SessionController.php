<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\Transaction;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionStateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends BaseController
{
    private function getCoach(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $coach = $user->coachProfile;
        abort_unless($coach, 403, 'Coach profile not found');

        return $coach;
    }

    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $coach = $this->getCoach($request);

        return CoachingSession::with(['client', 'coach', 'videoDetail'])
            ->where('coach_id', $coach->id)
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $coach = $this->getCoach($request);

        $sessions = CoachingSession::with(['client', 'videoDetail'])
            ->where('coach_id', $coach->id)
            ->orderBy('scheduled_time')
            ->get()
            ->map(function (CoachingSession $session) {
                return [
                    'id' => $session->id,
                    'status' => $session->status,
                    'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
                    'duration_minutes' => $session->duration_minutes,
                    'client_name' => optional($session->client)->name,
                    'client' => $session->client,
                    'video_detail' => $session->videoDetail,
                    'created_at' => optional($session->created_at)?->toISOString(),
                ];
            });

        return $this->success($sessions);
    }

    public function show(Request $request, $id)
    {
        return $this->success($this->getAuthorizedSession($request, $id));
    }

    public function saveNotes(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $session->update([
            'coach_notes' => $validated['notes'] ?? null,
        ]);

        return $this->success($session->fresh(['client', 'coach', 'videoDetail']), 'Notes saved');
    }

    public function start(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $fromState = $session->status;

        if (in_array($fromState, ['completed', 'failed'], true)) {
            return $this->error('This session cannot be started.', 422);
        }

        DB::beginTransaction();

        try {
            $reserveError = $this->reserveTokenIfNeeded($session);

            if ($reserveError) {
                DB::rollBack();
                return $this->error($reserveError, 422);
            }

            if ($fromState !== 'live') {
                $session->update(['status' => 'live']);

                SessionStateLog::create([
                    'session_id' => $session->id,
                    'from_state' => $fromState,
                    'to_state' => 'live',
                    'changed_by' => optional($request->user())->id,
                    'change_reason' => 'Session started by coach',
                    'metadata' => [
                        'started_at' => now()->toISOString(),
                    ],
                ]);
            }

            DB::commit();

            return $this->success([
                'session' => $session->fresh(['client', 'coach', 'videoDetail']),
                'video_join_url' => optional($session->videoDetail)->video_join_url,
            ], 'Session started');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to start session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function reserveTokenIfNeeded(CoachingSession $session): ?string
    {
        if (app()->environment('local')) {
            return null;
        }

        $existingPayment = Transaction::where('session_id', $session->id)
            ->where('transaction_type', 'coach_payment')
            ->latest()
            ->first();

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
            return 'Client has insufficient tokens to start this session.';
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
}