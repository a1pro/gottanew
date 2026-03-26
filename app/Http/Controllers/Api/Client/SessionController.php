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
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $sessions = $user->clientSessions()
            ->with([
                'coach',
                'videoDetail',
                'recording',
                'introRequest.preferredCoach:id,name,title,timezone',
                'introRequest.assignedCoach:id,name,title,timezone',
            ])
            ->latest('scheduled_time')
            ->get()
            ->map(fn (CoachingSession $session) => $this->serializeSession($session));

        return $this->success($sessions);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $session = CoachingSession::query()
            ->with([
                'coach',
                'client:id,name,email',
                'videoDetail',
                'recording',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
                'introRequest.preferredCoach:id,name,title,timezone',
                'introRequest.assignedCoach:id,name,title,timezone',
            ])
            ->where('client_id', $user->id)
            ->findOrFail($id);

        return $this->success($this->serializeSession($session, true));
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

        $viewerTimezone = Timezone::normalize($validated['viewer_timezone'] ?? 'UTC', 'UTC');
        $scheduledStart = CarbonImmutable::parse($validated['scheduled_time']);
        $scheduledStartUtc = $scheduledStart->setTimezone('UTC');

        $slotError = $this->availabilityService->validateRequestedSlot(
            $coach,
            $scheduledStart,
            $viewerTimezone,
            self::FIXED_DURATION_MINUTES
        );

        if ($slotError) {
            return $this->error($slotError, 422);
        }

        $room = $this->dailyService->createRoom();

        if (empty($room['name']) || empty($room['url'])) {
            return $this->error('Could not create video room', 500);
        }

        $session = DB::transaction(function () use ($user, $coach, $room, $tokenCost, $isIntroSession, $pricing, $scheduledStartUtc, $viewerTimezone) {
            $session = CoachingSession::create([
                'client_id' => $user->id,
                'coach_id' => $coach->id,
                'duration_minutes' => self::FIXED_DURATION_MINUTES,
                'scheduled_time' => $scheduledStartUtc->toDateTimeString(),
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
                    'scheduled_time' => $scheduledStartUtc->toIso8601String(),
                    'scheduled_time_in_viewer_timezone' => $scheduledStartUtc->setTimezone($viewerTimezone)->toIso8601String(),
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'viewer_timezone' => $viewerTimezone,
                    'billing' => $pricing,
                ],
            ]);

            return $session->load([
                'coach',
                'videoDetail',
                'recording',
                'introRequest.preferredCoach:id,name,title,timezone',
                'introRequest.assignedCoach:id,name,title,timezone',
            ]);
        });

        $this->notificationService->sessionBooked($session);

        return $this->success(
            $this->serializeSession($session),
            $isIntroSession ? 'Free intro session booked successfully' : 'Session booked successfully'
        );
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

                return $session->load([
                    'coach',
                    'videoDetail',
                    'recording',
                    'introRequest.preferredCoach:id,name,title,timezone',
                    'introRequest.assignedCoach:id,name,title,timezone',
                ]);
            });

            $this->notificationService->sessionBooked($session);

            return $this->success($this->serializeSession($session), $isIntroSession ? 'Free intro session created' : 'Instant session created');
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

    private function serializeSession(CoachingSession $session, bool $includeStateLogs = false): array
    {
        $recording = $session->recording;
        $introRequest = $session->introRequest;

        $payload = [
            'id' => (int) $session->id,
            'status' => $session->status,
            'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
            'duration_minutes' => (int) ($session->duration_minutes ?? self::FIXED_DURATION_MINUTES),
            'price_amount' => $session->price_amount,
            'price_currency' => $session->price_currency,
            'is_intro_session' => (bool) $session->is_intro_session,
            'client_notes' => $session->client_notes,
            'coach_notes' => $session->coach_notes,
            'created_at' => optional($session->created_at)?->toISOString(),
            'updated_at' => optional($session->updated_at)?->toISOString(),
            'coach' => $session->coach ? [
                'id' => (int) $session->coach->id,
                'name' => $session->coach->name,
                'title' => $session->coach->title,
                'timezone' => $session->coach->timezone,
                'avatar_url' => $session->coach->avatar_url ?? null,
            ] : null,
            'video_detail' => $session->videoDetail ? [
                'video_join_url' => $session->videoDetail->video_join_url,
                'daily_room_name' => $session->videoDetail->daily_room_name,
                'room_created_at' => optional($session->videoDetail->room_created_at)?->toISOString(),
            ] : null,
            'recording' => $recording ? [
                'transcription_status' => $recording->transcription_status,
                'transcript_available' => filled($recording->transcript),
                'transcript_preview' => filled($recording->transcript) ? Str::limit((string) $recording->transcript, 220) : null,
                'ai_summary' => $recording->ai_summary,
                'pre_session_summary' => $recording->pre_session_summary,
                'post_session_summary' => $recording->post_session_summary,
                'next_actions' => is_array($recording->next_actions) ? $recording->next_actions : [],
                'key_topics' => is_array($recording->key_topics) ? $recording->key_topics : [],
                'privacy_settings' => $recording->privacy_settings,
                'feedback_rating' => $recording->feedback_rating,
            ] : null,
            'source_request' => $introRequest ? [
                'id' => (int) $introRequest->id,
                'status' => $introRequest->status,
                'goal_summary' => $introRequest->goal_summary,
                'request_notes' => $introRequest->request_notes,
                'admin_notes' => $introRequest->admin_notes,
                'viewer_timezone' => $introRequest->viewer_timezone,
                'approved_at' => optional($introRequest->approved_at)?->toISOString(),
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
            ] : null,
        ];

        if ($includeStateLogs) {
            $payload['state_logs'] = $session->stateLogs->map(fn (SessionStateLog $log) => [
                'id' => (int) $log->id,
                'from_state' => $log->from_state,
                'to_state' => $log->to_state,
                'change_reason' => $log->change_reason,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)?->toISOString(),
            ])->values()->all();
        }

        return $payload;
    }

    private function shouldSkipTokenChecks(): bool
    {
        return app()->environment('local')
            && !filter_var((string) env('ENABLE_LOCAL_SESSION_BILLING', false), FILTER_VALIDATE_BOOLEAN);
    }
}
