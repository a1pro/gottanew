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
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SessionController extends BaseController
{
    private const FIXED_DURATION_MINUTES = 15;
    private const TOKEN_COST = 1;

    public function __construct(
        private CoachAvailabilityService $availabilityService,
        private NotificationService $notificationService
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
            'viewer_timezone' => ['required', 'string', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'in:15'],
        ]);

        $validated['viewer_timezone'] = Timezone::normalize($validated['viewer_timezone']);

        $coach = Coach::findOrFail($validated['coach_id']);

        if (!$coach->is_active) {
            return $this->error('Coach is not currently available for booking.', 422);
        }

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        if (!app()->environment('local') && $wallet->coin_balance < self::TOKEN_COST) {
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

        $room = $this->createDailyRoom();

        if (empty($room['name']) || empty($room['url'])) {
            return $this->error('Could not create video room', 500);
        }

        $session = DB::transaction(function () use ($user, $coach, $validated, $room) {
            $session = CoachingSession::create([
                'client_id' => $user->id,
                'coach_id' => $coach->id,
                'duration_minutes' => self::FIXED_DURATION_MINUTES,
                'scheduled_time' => $validated['scheduled_time'],
                'status' => 'scheduled',
                'price_amount' => self::TOKEN_COST,
                'price_currency' => 'TOKEN',
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
                'change_reason' => 'Session booked',
                'metadata' => [
                    'scheduled_time' => $validated['scheduled_time'],
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'viewer_timezone' => $validated['viewer_timezone'],
                ],
            ]);

            return $session->load(['coach', 'videoDetail']);
        });

        $this->notificationService->sessionBooked($session);

        return $this->success($session, 'Session booked successfully');
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

            $wallet = UserWallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'coin_balance' => 0,
                    'total_coins_purchased' => 0,
                    'total_coins_spent' => 0,
                ]
            );

            if (!app()->environment('local') && $wallet->coin_balance < self::TOKEN_COST) {
                return $this->error('Insufficient tokens', 422);
            }

            $room = $this->createDailyRoom();

            if (empty($room['name']) || empty($room['url'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not create video room',
                    'room_response' => $room,
                ], 500);
            }

            $session = DB::transaction(function () use ($user, $coach, $wallet, $room) {
                $session = CoachingSession::create([
                    'client_id' => $user->id,
                    'coach_id' => $coach->id,
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'scheduled_time' => now(),
                    'status' => 'live',
                    'price_amount' => self::TOKEN_COST,
                    'price_currency' => 'TOKEN',
                ]);

                SessionVideoDetail::create([
                    'session_id' => $session->id,
                    'video_room_id' => $room['id'] ?? null,
                    'video_join_url' => $room['url'],
                    'daily_room_name' => $room['name'],
                    'room_created_at' => now(),
                ]);

                if (!app()->environment('local')) {
                    $wallet->decrement('coin_balance', self::TOKEN_COST);

                    Transaction::create([
                        'user_id' => $user->id,
                        'coach_id' => $coach->id,
                        'session_id' => $session->id,
                        'transaction_type' => 'coach_payment',
                        'coin_amount' => self::TOKEN_COST,
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
                    'change_reason' => 'Instant session started',
                    'metadata' => [
                        'started_at' => now()->toISOString(),
                    ],
                ]);

                return $session->load(['coach', 'videoDetail']);
            });

            $this->notificationService->sessionBooked($session);

            return $this->success($session, 'Instant session created');
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

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $session = CoachingSession::with([
            'coach',
            'client',
            'videoDetail',
        ])->findOrFail($id);

        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;

        if (!$isClient && !$isCoach) {
            return $this->error('Unauthorized access to session', 403);
        }

        return $this->success($session);
    }

    private function createDailyRoom(): array
    {
        if (app()->environment('local') && filter_var(env('DAILY_USE_FAKE_ROOM', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'id' => 'local-test-room',
                'name' => 'local_test_room',
                'url' => 'https://example.daily.co/local-test-room',
            ];
        }

        $apiKey = config('services.daily.api_key');

        if (empty($apiKey)) {
            \Log::error('DAILY_API_KEY is missing');

            return [
                'error' => true,
                'status' => 500,
                'body' => ['error' => 'missing-daily-api-key'],
            ];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.daily.co/v1/rooms', [
                'name' => 'session_' . uniqid(),
                'privacy' => 'public',
                'properties' => [
                    'max_participants' => 2,
                    'enable_chat' => true,
                    'enable_screenshare' => true,
                    'start_video_off' => false,
                    'start_audio_off' => false,
                ],
            ]);

        if (!$response->successful()) {
            \Log::error('Daily room creation failed', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);

            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ];
        }

        $data = $response->json();

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'url' => $data['url'] ?? null,
        ];
    }
}