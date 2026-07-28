<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseController;
use App\Rules\TimezoneIdentifier;
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
use App\Models\Session\SessionRequest;
use App\Models\Goal\UserGoal;

class SessionController extends BaseController
{
    private const FIXED_DURATION_MINUTES = 15;

    public function __construct(
        private CoachAvailabilityService $availabilityService,
        private NotificationService $notificationService,
        private SessionPricingService $sessionPricingService,
        private DailyRestApiService $dailyService
    ) {}

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
            ->map(fn(CoachingSession $session) => $this->serializeSession($session));

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
                'stateLogs' => fn($query) => $query->latest('created_at')->limit(10),
                'introRequest.preferredCoach:id,name,title,timezone',
                'introRequest.assignedCoach:id,name,title,timezone',
            ])
            ->where('client_id', $user->id)
            ->findOrFail($id);

      return $this->success($this->serializeSession($session, true));
  }

  //-------------- old store method functionality ------------ //

//   public function store(Request $request)
//   {
//       $user = $request->user();

//       if (!$user) {
//           return $this->error('Unauthenticated', 401);
//       }

//       $validated = $request->validate([
//           'coach_id' => ['required', 'exists:coaches,id'],
//           'goal_id' => ['required', 'exists:user_goals,id'],
//           'scheduled_time' => ['required', 'date', 'after:now'],
//           'viewer_timezone' => ['required', 'string', 'max:100', new TimezoneIdentifier()],
//           'duration_minutes' => ['nullable', 'integer', 'in:15'],
//       ]);

//       $coach = Coach::findOrFail($validated['coach_id']);

//       if (!$coach->is_active) {
//           return $this->error('Coach is not currently available for
//   booking.', 422);
//       }

//       $pricing = $this->sessionPricingService->preview((int) $user->id, (int)
//  $coach->id);
//       $tokenCost = (int) ($pricing['token_cost'] ??
//  SessionPricingService::STANDARD_TOKEN_COST);
//       $isIntroSession = (bool) ($pricing['is_intro_eligible'] ?? false);

//       $wallet = UserWallet::firstOrCreate(
//           ['user_id' => $user->id],
//           [
//               'coin_balance' => 0,
//               'total_coins_purchased' => 0,
//               'total_coins_spent' => 0,

//            ]
//       );

//       if (!$this->shouldSkipTokenChecks() && $tokenCost > 0 && $wallet->coin_balance < $tokenCost) {
//           return $this->error('You need at least 1 token in your wallet to
//  book this session.', 422);
//       }

//       $viewerTimezone = Timezone::normalize($validated['viewer_timezone'] ??
//  'UTC', 'UTC');
//       $scheduledStart = CarbonImmutable::parse($validated['scheduled_time']);
//       $scheduledStartUtc = $scheduledStart->setTimezone('UTC');

//       $slotError = $this->availabilityService->validateRequestedSlot(
//           $coach,
//           $scheduledStart,
//           $viewerTimezone,
//           self::FIXED_DURATION_MINUTES
//       );

//       if ($slotError) {
//           return $this->error($slotError, 422);
//       }

//       $room = $this->dailyService->createRoom();

//       if (empty($room['name']) || empty($room['url'])) {
//           return $this->error('Could not create video room', 500);
//       }

//       $session = DB::transaction(function () use ($user, $coach, $room,
//  $tokenCost, $isIntroSession, $pricing, $scheduledStartUtc, $viewerTimezone, $validated) {
//           $session = CoachingSession::create([
//               'client_id' => $user->id,
//               'coach_id' => $coach->id,
//               'duration_minutes' => self::FIXED_DURATION_MINUTES,
//                  'scheduled_time' => $scheduledStartUtc->toDateTimeString(),
//                  'scheduled_timezone' => $viewerTimezone,
//                  'status' => 'scheduled',
//                  'price_amount' => $tokenCost,
//                  'price_currency' => 'TOKEN',
//                  'is_intro_session' => $isIntroSession,
//            ]);

//           UserGoal::where('id', $validated['goal_id'])
//               ->where('user_id', $user->id)
//               ->update([
//                   'source_session_id' => $session->id,
//               ]);

//            SessionVideoDetail::create([
//                'session_id' => $session->id,
//                'video_room_id' => $room['id'] ?? null,

//                   'video_join_url' => $room['url'],
//                   'daily_room_name' => $room['name'],
//                   'room_created_at' => now(),
//             ]);

//             SessionStateLog::create([
//                 'session_id' => $session->id,
//                 'from_state' => null,
//                 'to_state' => 'scheduled',
//               'changed_by' => $user->id,
//               'change_reason' => $isIntroSession ? 'Free intro session
//  booked' : 'Session booked',
//               'metadata' => [
//                   'scheduled_time' => $scheduledStartUtc->toIso8601String(),
//                   'scheduled_time_in_viewer_timezone' => $scheduledStartUtc->setTimezone($viewerTimezone)->toIso8601String(),
//                   'duration_minutes' => self::FIXED_DURATION_MINUTES,
//                   'viewer_timezone' => $viewerTimezone,
//                   'billing' => $pricing,
//               ],
//           ]);

//             return $session->load([
//                 'coach',
//                 'videoDetail',
//                 'recording',
//                 'introRequest.preferredCoach:id,name,title,timezone',
//                 'introRequest.assignedCoach:id,name,title,timezone',
//             ]);
//       });

//       $this->notificationService->sessionBooked($session);

//       return $this->success(
//           $this->serializeSession($session),
//           $isIntroSession ? 'Free intro session booked successfully' :
//  'Session booked successfully'
//       );
//   }


  //-------------- new store method functionality ------------ //

  public function store(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return $this->error('Unauthenticated', 401);
    }

    $validated = $request->validate([
        'coach_id' => ['required', 'exists:coaches,id'],

        // CHANGED: accept goal object instead of goal_id
        'goal.title' => ['required', 'string', 'max:255'],
        'goal.category' => ['required', 'string', 'max:100'],
        'goal.description' => ['nullable', 'string'],

        'scheduled_time' => ['required', 'date', 'after:now'],
        'viewer_timezone' => [
            'required',
            'string',
            'max:100',
            new TimezoneIdentifier()
        ],
        'duration_minutes' => ['nullable', 'integer', 'in:15'],
    ]);

    $coach = Coach::findOrFail($validated['coach_id']);

    if (!$coach->is_active) {
        return $this->error('Coach is not currently available for booking.', 422);
    }

    $pricing = $this->sessionPricingService->preview(
        (int) $user->id,
        (int) $coach->id
    );

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

    if (
        !$this->shouldSkipTokenChecks() &&
        $tokenCost > 0 &&
        $wallet->coin_balance < $tokenCost
    ) {
        return $this->error(
            'You need at least 1 token in your wallet to book this session.',
            422
        );
    }

    $viewerTimezone = Timezone::normalize(
        $validated['viewer_timezone'] ?? 'UTC',
        'UTC'
    );

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

    $session = DB::transaction(function () use (
        $user,
        $coach,
        $room,
        $tokenCost,
        $isIntroSession,
        $pricing,
        $scheduledStartUtc,
        $viewerTimezone,
        $validated
    ) {

        $session = CoachingSession::create([
            'client_id' => $user->id,
            'coach_id' => $coach->id,
            'duration_minutes' => self::FIXED_DURATION_MINUTES,
            'scheduled_time' => $scheduledStartUtc->toDateTimeString(),
            'scheduled_timezone' => $viewerTimezone,
            'status' => 'scheduled',
            'price_amount' => $tokenCost,
            'price_currency' => 'TOKEN',
            'is_intro_session' => $isIntroSession,
        ]);

        // NEW: Goal is created ONLY after booking is successful
    
       UserGoal::create([
           'user_id' => $user->id,
           'title' => $validated['goal']['title'],
           'category' => $validated['goal']['category'],
           'description' => $validated['goal']['description'] ?? null,
           'progress_percentage' => 0,
           'status' => 'active',
           'source_session_id' => $session->id,
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
            'change_reason' => $isIntroSession
                ? 'Free intro session booked'
                : 'Session booked',
            'metadata' => [
                'scheduled_time' => $scheduledStartUtc->toIso8601String(),
                'scheduled_time_in_viewer_timezone' => $scheduledStartUtc
                    ->setTimezone($viewerTimezone)
                    ->toIso8601String(),
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
        $isIntroSession
            ? 'Free intro session booked successfully'
            : 'Session booked successfully'
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
          
              'goal.title' => ['required', 'string', 'max:255'],
              'goal.category' => ['required', 'string', 'max:100'],
              'goal.description' => ['nullable', 'string'],
          
              'goal_summary' => ['nullable', 'string'],
              'request_notes' => ['nullable', 'string'],
          ]);

          $coach = Coach::findOrFail($validated['coach_id']);

        //   if (!$coach->available_now) {
        //       return $this->error('Coach is not available right now', 422);
        //   }

             if (!$coach->immediate_availability) {
                 return $this->error('Coach is not available right now', 422);
             }

          $pricing = $this->sessionPricingService->preview((int) $user->id,
(int) $coach->id);
          $tokenCost = (int) ($pricing['token_cost'] ??
SessionPricingService::STANDARD_TOKEN_COST);
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
       
          $session = DB::transaction(function () use ($user, $coach, $wallet,
$room, $tokenCost, $isIntroSession, $pricing, $validated) {
              $session = CoachingSession::create([
                  'client_id' => $user->id,
                  'coach_id' => $coach->id,
                  'duration_minutes' => self::FIXED_DURATION_MINUTES,
                  'scheduled_time' => now()->toDateTimeString(),

                  'scheduled_timezone' => 'UTC',
                  'status' => 'scheduled',
                  'price_amount' => $tokenCost,
                  'price_currency' => 'TOKEN',
                  'is_intro_session' => $isIntroSession,
            ]);

            UserGoal::create([
                'user_id' => $user->id,
                'title' => $validated['goal']['title'],
                'category' => $validated['goal']['category'],
                'description' => $validated['goal']['description'] ?? null,
                'progress_percentage' => 0,
                'status' => 'active',
                'source_session_id' => $session->id,
            ]);

            SessionRequest::create([
                'client_id' => $user->id,
                'preferred_coach_id' => $coach->id,
                'assigned_coach_id' => $coach->id,
                'approved_session_id' => $session->id,
                'status' => 'approved',
                'goal_summary' => $validated['goal_summary'] ?? '',
                'request_notes' => $validated['request_notes'] ?? '',
                'viewer_timezone' => 'UTC',
                'scheduled_time' => now(),
                'approved_at' => now(),
            ]);

            if (!$coach->available_now) {
                return $this->error('Coach is not available right now', 422);
            }

            $pricing = $this->sessionPricingService->preview(
                (int) $user->id,
                (int) $coach->id
            );
            $tokenCost = (int) ($pricing['token_cost'] ??
                SessionPricingService::STANDARD_TOKEN_COST);
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

            $session = DB::transaction(function () use (
                $user,
                $coach,
                $wallet,
                $room,
                $tokenCost,
                $isIntroSession,
                $pricing
            ) {
                $session = CoachingSession::create([
                    'client_id' => $user->id,
                    'coach_id' => $coach->id,
                    'duration_minutes' => self::FIXED_DURATION_MINUTES,
                    'scheduled_time' => now()->toDateTimeString(),

                    'scheduled_timezone' => 'UTC',
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
                    'change_reason' => 'Instant session booked',
                    'metadata' => [
                        'scheduled_time' => now()->toIso8601String(),
                        'duration_minutes' => self::FIXED_DURATION_MINUTES,
                        'viewer_timezone' => 'UTC',
                        'billing' => $pricing,
                    ],
                ]);

                return $session->load(['coach', 'videoDetail', 'recording']);
            });

            $this->notificationService->sessionBooked($session);

            return $this->success(
                $this->serializeSession($session),
                'Instant session booked successfully'
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to book instant session',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    private function serializeSession(CoachingSession $session, bool

    $includeStateLogs = false): array
    {
        $recording = $session->recording;

        $data = [
            'id' => (int) $session->id,
            'status' => $session->status,
            'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
            'scheduled_time_in_viewer_timezone' => optional($session->scheduled_time)?->copy()->setTimezone(Timezone::normalize($session->scheduled_timezone, 'UTC'))->toIso8601String(),
            'duration_minutes' => (int) ($session->duration_minutes ??
                self::FIXED_DURATION_MINUTES),
            'is_intro_session' => (bool) $session->is_intro_session,
            'coach' => $session->coach ? [
                'id' => (int) $session->coach->id,
                'name' => $session->coach->name,
                'title' => $session->coach->title,
                'timezone' => $session->coach->timezone,
            ] : null,
            'video_detail' => $session->videoDetail ? [
                'video_join_url' => $session->videoDetail->video_join_url,
                'daily_room_name' => $session->videoDetail->daily_room_name,
            ] : null,
            'recording' => $recording ? [
                'transcription_status' => $recording->transcription_status,
                'transcript' => $recording->transcript,
                'transcript_available' => filled($recording->transcript),
                'recording_url' => $recording->recording_url,
            ] : null,
            'created_at' => optional($session->created_at)?->toISOString(),
        ];

        if ($includeStateLogs) {
            $data['state_logs'] = $session->stateLogs->map(fn($log) => [
                'id' => (int) $log->id,
                'from_state' => $log->from_state,
                'to_state' => $log->to_state,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)?->toISOString(),
            ])->values()->all();
        }

        return $data;
    }

    private function shouldSkipTokenChecks(): bool

    {
        return (bool) env('SKIP_TOKEN_CHECKS', false);
    }
}
