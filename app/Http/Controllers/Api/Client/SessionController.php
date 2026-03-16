<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionVideoDetail;
use App\Models\Coach\Coach;
use App\Models\Finance\UserWallet;
use App\Models\Finance\Transaction;

class SessionController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return $this->success(
            $user->clientSessions()->with(['coach', 'videoDetail'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate([
            'coach_id' => ['required', 'exists:coaches,id'],
            'scheduled_time' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:120'],
        ]);

        $coach = Coach::findOrFail($validated['coach_id']);

        $coinCost = max(
            1,
            (int) round((($coach->hourly_coin_cost ?? 1) * $validated['duration_minutes']) / 60)
        );

        $wallet = UserWallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );

        if (!app()->environment('local') && $wallet->coin_balance < $coinCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient coins',
            ], 422);
        }

        $room = $this->createDailyRoom();

        if (empty($room['name']) || empty($room['url'])) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create video room',
            ], 500);
        }

        $session = DB::transaction(function () use ($user, $coach, $validated, $coinCost, $wallet, $room) {
            $session = CoachingSession::create([
                'client_id' => $user->id,
                'coach_id' => $coach->id,
                'duration_minutes' => $validated['duration_minutes'],
                'scheduled_time' => $validated['scheduled_time'],
                'status' => 'scheduled',
                'price_amount' => $coinCost,
                'price_currency' => 'COIN',
            ]);

            SessionVideoDetail::create([
                'session_id' => $session->id,
                'video_room_id' => $room['id'] ?? null,
                'video_join_url' => $room['url'],
                'daily_room_name' => $room['name'],
                'room_created_at' => now(),
            ]);

            if (!app()->environment('local') && $coinCost > 0) {
                $wallet->decrement('coin_balance', $coinCost);
                $wallet->increment('total_coins_spent', $coinCost);

                Transaction::create([
                    'user_id' => $user->id,
                    'coach_id' => $coach->id,
                    'transaction_type' => 'coach_payment',
                    'coin_amount' => $coinCost,
                    'amount_currency' => 'COIN',
                    'amount_fiat' => null,
                    'status' => 'completed',
                ]);
            }

            return $session->load(['coach', 'videoDetail']);
        });

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function instant(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $validated = $request->validate([
                'coach_id' => ['required', 'exists:coaches,id'],
            ]);

            $coach = Coach::findOrFail($validated['coach_id']);

            if (!$coach->available_now) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coach is not available right now',
                ], 422);
            }

            $coinCost = app()->environment('local') ? 0 : 1;

            $wallet = UserWallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'coin_balance' => 0,
                    'total_coins_purchased' => 0,
                    'total_coins_spent' => 0,
                ]
            );

            if (!app()->environment('local') && $wallet->coin_balance < $coinCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient coins',
                ], 422);
            }

            $room = $this->createDailyRoom();

           if (empty($room['name']) || empty($room['url'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not create video room',
                    'room_response' => $room,
                ], 500);
            }

            $session = DB::transaction(function () use ($user, $coach, $coinCost, $wallet, $room) {
                $session = CoachingSession::create([
                    'client_id' => $user->id,
                    'coach_id' => $coach->id,
                    'duration_minutes' => 15,
                    'scheduled_time' => now(),
                    'status' => 'in_progress',
                    'price_amount' => $coinCost,
                    'price_currency' => 'COIN',
                ]);

                SessionVideoDetail::create([
                    'session_id' => $session->id,
                    'video_room_id' => $room['id'] ?? null,
                    'video_join_url' => $room['url'],
                    'daily_room_name' => $room['name'],
                    'room_created_at' => now(),
                ]);

                if (!app()->environment('local') && $coinCost > 0) {
                    $wallet->decrement('coin_balance', $coinCost);
                    $wallet->increment('total_coins_spent', $coinCost);

                    Transaction::create([
                        'user_id' => $user->id,
                        'coach_id' => $coach->id,
                        'transaction_type' => 'coach_payment',
                        'coin_amount' => $coinCost,
                        'amount_currency' => 'COIN',
                        'amount_fiat' => null,
                        'status' => 'completed',
                    ]);
                }

                return $session->load(['coach', 'videoDetail']);
            });

            return response()->json([
                'success' => true,
                'data' => $session,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Instant session failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $session = CoachingSession::with([
            'coach',
            'client',
            'videoDetail',
        ])->findOrFail($id);

        $isClient = $session->client_id === $user->id;
        $isCoach = optional($session->coach)->user_id === $user->id;

        if (!$isClient && !$isCoach) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to session',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    private function createDailyRoom(): array
    {
        $apiKey = config('services.daily.api_key');

        if (empty($apiKey)) {
            \Log::error('DAILY_API_KEY is missing');

            return [
                'error' => true,
                'status' => 500,
                'body' => ['error' => 'missing-daily-api-key'],
            ];
        }

        \Log::info('Daily key loaded', [
            'exists' => true,
            'prefix' => substr($apiKey, 0, 8),
        ]);

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

    // private function createDailyRoom(): array
    // {
    //     if (filter_var(env('DAILY_USE_FAKE_ROOM', false), FILTER_VALIDATE_BOOLEAN)) {
    //         return [
    //             'id' => 'local-test-room',
    //             'name' => 'local_test_room',
    //             'url' => 'https://example.daily.co/local-test-room',
    //         ];
    //     }

    //     $apiKey = config('services.daily.api_key');

    //     if (empty($apiKey)) {
    //         return [
    //             'error' => true,
    //             'status' => 500,
    //             'body' => ['error' => 'missing-daily-api-key'],
    //         ];
    //     }

    //       \Log::info('Daily API key loaded', [
    //                 'exists' => !empty($apiKey),
    //                 'prefix' => $apiKey ? substr($apiKey, 0, 8) : null,
    //             ]);

    //     $response = Http::withToken($apiKey)
    //         ->acceptJson()
    //         ->post('https://api.daily.co/v1/rooms', [
    //             'name' => 'session_' . uniqid(),
    //             'privacy' => 'public',
    //             'properties' => [
    //                 'max_participants' => 2,
    //             ],
    //         ]);

    //     if (!$response->successful()) {
    //         return [
    //             'error' => true,
    //             'status' => $response->status(),
    //             'body' => $response->json() ?: $response->body(),
    //         ];
    //     }

    //     $data = $response->json();

    //     return [
    //         'id' => $data['id'] ?? null,
    //         'name' => $data['name'] ?? null,
    //         'url' => $data['url'] ?? null,
    //     ];
    // }

    // private function createDailyRoom(): array
    //     {
    //         if (app()->environment('local')) {
    //             return [
    //                 'id' => 'local-test-room',
    //                 'name' => 'local_test_room',
    //                 'url' => 'https://example.daily.co/local-test-room',
    //             ];
    //         }

    //         $apiKey = config('services.daily.api_key');

    //         if (empty($apiKey)) {
    //             \Log::error('DAILY_API_KEY is missing');
    //             return [
    //                 'error' => true,
    //                 'status' => 500,
    //                 'body' => ['error' => 'missing-daily-api-key'],
    //             ];
    //         }

    //         \Log::info('Daily API key loaded', [
    //                 'exists' => !empty($apiKey),
    //                 'prefix' => $apiKey ? substr($apiKey, 0, 8) : null,
    //             ]);

    //         $response = Http::withToken($apiKey)
    //             ->acceptJson()
    //             ->post('https://api.daily.co/v1/rooms', [
    //                 'name' => 'session_' . uniqid(),
    //                 'privacy' => 'public',
    //                 'properties' => [
    //                     'max_participants' => 2,
    //                     'enable_chat' => true,
    //                     'enable_screenshare' => true,
    //                     'start_video_off' => false,
    //                     'start_audio_off' => false,
    //                 ],
    //             ]);

    //         if (!$response->successful()) {
    //             \Log::error('Daily room creation failed', [
    //                 'status' => $response->status(),
    //                 'body' => $response->json() ?: $response->body(),
    //             ]);

    //             return [
    //                 'error' => true,
    //                 'status' => $response->status(),
    //                 'body' => $response->json() ?: $response->body(),
    //             ];
    //         }

    //         $data = $response->json();

    //         return [
    //             'id' => $data['id'] ?? null,
    //             'name' => $data['name'] ?? null,
    //             'url' => $data['url'] ?? null,
    //         ];
    //     }


    // private function createDailyRoom(): array
    // {
    //     if (app()->environment('local')) {
    //         return [
    //             'id' => 'local-test-room',
    //             'name' => 'local_test_room',
    //             'url' => 'https://example.daily.co/local-test-room',
    //         ];
    //     }

    //     $apiKey = env('DAILY_API_KEY');

    //     if (!$apiKey) {
    //         \Log::error('DAILY_API_KEY is missing');
    //         return [];
    //     }

    //     $response = Http::withToken($apiKey)
    //         ->post('https://api.daily.co/v1/rooms', [
    //             'name' => 'session_' . uniqid(),
    //             'properties' => [
    //                 'max_participants' => 2,
    //             ],
    //         ]);

    //     if (!$response->successful()) {
    //         \Log::error('Daily room creation failed', [
    //             'status' => $response->status(),
    //             'body' => $response->body(),
    //         ]);
    //         return [];
    //     }

    //     return $response->json();
    // }
}