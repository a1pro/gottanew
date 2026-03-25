<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Finance\Transaction;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionStateLog;
use App\Models\Session\SessionVideoDetail;
use App\Services\Coach\CoachAvailabilityService;
use App\Services\Communication\NotificationService;
use App\Services\Session\SessionPricingService;
use App\Services\Video\DailyRestApiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends BaseController
{
    private const FIXED_DURATION_MINUTES = 15;

    public function __construct(
        private CoachAvailabilityService $availabilityService,
        private NotificationService $notificationService,
        private SessionPricingService $sessionPricingService,
        private DailyRestApiService $dailyService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        return $this->success(
            $user->clientSessions()->with(['coach', 'videoDetail'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $validated = $request->validate([
            'coach_id' => ['required', 'exists:coaches,id'],
            'scheduled_time' => ['required', 'date', 'after:now'],
            'viewer_timezone' => ['required', 'timezone'],
            'duration_minutes' => ['nullable', 'integer', 'in:15'],
        ]);

        $coach = Coach::findOrFail($validated['coach_id']);

        if (!$coach->is_active) {
            return $this->error('Coach is not currently available for booking.', 422);
        }

        $pricing = $this->sessionPricingService->preview((int) $user->id, (int) $coach->id);
        $tokenCost = (int) ($pricing['token_cost'] ?? SessionPricingService::STANDARD_TOKEN_COST);
        $isIntroSession = (bool) ($pricing['is_intro_eligible'] ?? false);

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        if (!$this->shouldSkipTokenChecks() && $tokenCost > 0 && $wallet->coin_balance < $tokenCost) {
            return $this->error('You need at least 1 token in your wallet to book this session.', 422);
        }

        $scheduledStart = CarbonImmutable::parse($validated['scheduled_time']);
        $slotError = $this->availabilityService->validateRequestedSlot(
            $coach,
            $scheduledStart,
            $validated['viewer_timezone'],
            self::FIXED_DURATION_MINUTES
        );

        if ($slotError) {
            return $this->error($slotError, 422);
        }

        $room = $this->dailyService->createRoom();

        if (empty($room['name']) || empty($room['url'])) {
            return $this->error('Could not create video room', 500);
        }

        $session = DB::transaction(function () use ($user, $coach, $validated, $room, $tokenCost, $isIntroSession, $pricing) {
            $session = CoachingSession::create([
                'client_id' => $user->id,
                'coach_id' => $coach->id,
                'duration_minutes' => self::FIXED_DURATION_MINUTES,
                'scheduled_time' => $validated['scheduled_time'],
                'status' => 'scheduled',
                'price_amount' => $tokenCost,
                'price_currency' => 'TOKEN',
                'is_intro_session' => $isIntroSession,
            ]);

            SessionVideoDetail::create([
                'session_id' => $session->id,
                'video_room_id' => $room['id'] ?? null,
                'video_join_url' => $room['url'],
                'daily_room_name' => $room['name'],
                'room_created_at' => now(),
            ]);

            SessionStateLog::create([
                'session_id' => $session->id,
                'from_state' => null,
                'to_state' => 'scheduled',
                'changed_by' => $user->id,
                'change_reason' => $isIntroSession ? 'Free intro session booked' : 'Session booked',
                'metadata' => [
                    'scheduled_time' => $validated['scheduled_time'],
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'viewer_timezone' => $validated['viewer_timezone'],
                    'billing' => $pricing,
                ],
            ]);

            return $session->load(['coach', 'videoDetail']);
        });

        $this->notificationService->sessionBooked($session);

        return $this->success($session, $isIntroSession ? 'Free intro session booked successfully' : 'Session booked successfully');
    }

    public function instant(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->error('Unauthenticated', 401);
            }

            $validated = $request->validate([
                'coach_id' => ['required', 'exists:coaches,id'],
            ]);

            $coach = Coach::findOrFail($validated['coach_id']);

            if (!$coach->available_now) {
                return $this->error('Coach is not available right now', 422);
            }

            $pricing = $this->sessionPricingService->preview((int) $user->id, (int) $coach->id);
            $tokenCost = (int) ($pricing['token_cost'] ?? SessionPricingService::STANDARD_TOKEN_COST);
            $isIntroSession = (bool) ($pricing['is_intro_eligible'] ?? false);

            $wallet = UserWallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'coin_balance' => 0,
                    'total_coins_purchased' => 0,
                    'total_coins_spent' => 0,
                ]
            );

            if (!$this->shouldSkipTokenChecks() && $tokenCost > 0 && $wallet->coin_balance < $tokenCost) {
                return $this->error('Insufficient tokens', 422);
            }

            $room = $this->dailyService->createRoom();

            if (empty($room['name']) || empty($room['url'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not create video room',
                    'room_response' => $room,
                ], 500);
            }

            $session = DB::transaction(function () use ($user, $coach, $wallet, $room, $tokenCost, $isIntroSession, $pricing) {
                $session = CoachingSession::create([
                    'client_id' => $user->id,
                    'coach_id' => $coach->id,
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'scheduled_time' => now(),
                    'status' => 'live',
                    'price_amount' => $tokenCost,
                    'price_currency' => 'TOKEN',
                    'is_intro_session' => $isIntroSession,
                ]);

                SessionVideoDetail::create([
                    'session_id' => $session->id,
                    'video_room_id' => $room['id'] ?? null,
                    'video_join_url' => $room['url'],
                    'daily_room_name' => $room['name'],
                    'room_created_at' => now(),
                ]);

                if (!$this->shouldSkipTokenChecks() && $tokenCost > 0) {
                    $wallet->decrement('coin_balance', $tokenCost);

                    Transaction::create([
                        'user_id' => $user->id,
                        'coach_id' => $coach->id,
                        'session_id' => $session->id,
                        'transaction_type' => 'coach_payment',
                        'coin_amount' => $tokenCost,
                        'amount_currency' => 'TOKEN',
                        'amount_fiat' => null,
                        'status' => 'pending',
                    ]);
                }

                SessionStateLog::create([
                    'session_id' => $session->id,
                    'from_state' => null,
                    'to_state' => 'live',
                    'changed_by' => $user->id,
                    'change_reason' => $isIntroSession ? 'Free intro instant session started' : 'Instant session started',
                    'metadata' => [
                        'started_at' => now()->toISOString(),
                        'billing' => $pricing,
                    ],
                ]);

                return $session->load(['coach', 'videoDetail']);
            });

            $this->notificationService->sessionBooked($session);

            return $this->success($session, $isIntroSession ? 'Free intro session created' : 'Instant session created');
        } catch (\Throwable $e) {
            \Log::error('Instant session failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function shouldSkipTokenChecks(): bool
    {
        return app()->environment('local')
            && !filter_var((string) env('ENABLE_LOCAL_SESSION_BILLING', false), FILTER_VALIDATE_BOOLEAN);
    }

}
