<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRequest;
use App\Models\Session\SessionStateLog;
use App\Models\Session\SessionVideoDetail;
use App\Services\Coach\CoachAvailabilityService;
use App\Services\Communication\NotificationService;
use App\Services\Video\DailyRestApiService;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionRequestAdminController extends BaseController
{
    public function __construct(
        private CoachAvailabilityService $availabilityService,
        private NotificationService $notificationService,
        private DailyRestApiService $dailyService,
    ) {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $requests = SessionRequest::query()
            ->with([
                'client:id,name,email',
                'preferredCoach:id,name,title,timezone',
                'assignedCoach:id,name,title,timezone,user_id,notification_email',
                'approvedSession:id,status,scheduled_time,duration_minutes',
                'reviewer:id,name,email',
            ])
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('goal_summary', 'like', "%{$q}%")
                        ->orWhere('request_notes', 'like', "%{$q}%")
                        ->orWhereHas('client', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        })
                        ->orWhereHas('preferredCoach', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('assignedCoach', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%");
                        });
                });
            })
            ->latest()
            ->paginate((int) $request->get('per_page', 12));

        $requests->setCollection(
            $requests->getCollection()->map(fn (SessionRequest $sessionRequest) => $this->serializeRequest($sessionRequest))
        );

        return $this->success($requests);
    }

    public function assignableCoaches()
    {
        $coaches = Coach::query()
            ->where('is_active', true)
            ->with('user:id,email')
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'title', 'timezone', 'notification_email', 'available_now']);

        return $this->success($coaches->map(fn (Coach $coach) => [
            'id' => (int) $coach->id,
            'name' => $coach->name,
            'title' => $coach->title,
            'timezone' => $coach->timezone ?: 'UTC',
            'email' => $coach->notification_email ?: $coach->user?->email,
            'available_now' => (bool) $coach->available_now,
        ])->values()->all());
    }

    public function approve(Request $request, int $id)
    {
        $admin = $request->user();
        $sessionRequest = SessionRequest::query()->with(['client', 'preferredCoach'])->findOrFail($id);

        if ($sessionRequest->status !== 'pending') {
            return $this->error('Only pending requests can be approved.', 422);
        }

        $validated = $request->validate([
            'assigned_coach_id' => ['required', 'exists:coaches,id'],
            'scheduled_time' => ['required', 'string', 'max:100'],
            'viewer_timezone' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $coach = Coach::query()->findOrFail($validated['assigned_coach_id']);

        if (!$coach->is_active) {
            return $this->error('Assigned coach is not active.', 422);
        }

        $viewerTimezone = Timezone::normalize(
            $validated['viewer_timezone'] ?? $sessionRequest->viewer_timezone ?? 'UTC',
            'UTC'
        );

        $scheduledStartUtc = $this->parseRequestedTime((string) $validated['scheduled_time'], $viewerTimezone);

        if (!$scheduledStartUtc || $scheduledStartUtc->lte(now('UTC'))) {
            return $this->error('Please choose a future session time.', 422);
        }

        $slotError = $this->availabilityService->validateRequestedSlot(
            $coach,
            $scheduledStartUtc,
            $viewerTimezone,
            15
        );

        if ($slotError) {
            return $this->error($slotError, 422);
        }

        try {
            $room = $this->dailyService->createRoom();
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage(), 500);
        }

        if (empty($room['name']) || empty($room['url'])) {
            return $this->error('Could not create video room.', 500);
        }

        $session = DB::transaction(function () use ($sessionRequest, $coach, $admin, $validated, $scheduledStartUtc, $room, $viewerTimezone) {
            $session = CoachingSession::create([
                'client_id' => $sessionRequest->client_id,
                'coach_id' => $coach->id,
                'duration_minutes' => 15,
                'scheduled_time' => $scheduledStartUtc->toDateTimeString(),
                'status' => 'scheduled',
                'price_amount' => 0,
                'price_currency' => 'TOKEN',
                'is_intro_session' => true,
                'client_notes' => $sessionRequest->request_notes,
                'coach_notes' => $validated['admin_notes'] ?? null,
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
                'changed_by' => $admin?->id,
                'change_reason' => 'Admin approved free intro request',
                'metadata' => [
                    'viewer_timezone' => $viewerTimezone,
                    'scheduled_time' => $scheduledStartUtc->toIso8601String(),
                    'request_id' => $sessionRequest->id,
                    'goal_summary' => $sessionRequest->goal_summary,
                ],
            ]);

            $sessionRequest->update([
                'status' => 'approved',
                'assigned_coach_id' => $coach->id,
                'approved_session_id' => $session->id,
                'scheduled_time' => $scheduledStartUtc->toDateTimeString(),
                'admin_notes' => $validated['admin_notes'] ?? null,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
            ]);

            return $session->load(['coach.user', 'client', 'videoDetail', 'stateLogs']);
        });

        $this->notificationService->sessionBooked($session);

        return $this->success([
            'request' => $this->serializeRequest($sessionRequest->fresh(['client', 'preferredCoach', 'assignedCoach', 'approvedSession', 'reviewer'])),
            'session_id' => (int) $session->id,
        ], 'Free intro request approved and scheduled successfully.');
    }

    public function reject(Request $request, int $id)
    {
        $admin = $request->user();
        $sessionRequest = SessionRequest::query()->with('client')->findOrFail($id);

        if ($sessionRequest->status !== 'pending') {
            return $this->error('Only pending requests can be rejected.', 422);
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:3000'],
        ]);

        $sessionRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
            'rejected_at' => now(),
        ]);

        if ($sessionRequest->client) {
            $this->notificationService->createForUser($sessionRequest->client, [
                'category' => 'session_request',
                'priority' => 'normal',
                'title' => 'Free intro request updated',
                'body' => 'Your free intro request could not be scheduled yet. Please review the admin note and submit another request if needed.',
                'action_url' => '/dashboard',
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'status' => 'rejected',
                    'admin_notes' => $validated['admin_notes'],
                ],
            ]);
        }

        return $this->success($this->serializeRequest($sessionRequest->fresh(['client', 'preferredCoach', 'assignedCoach', 'approvedSession', 'reviewer'])), 'Request rejected.');
    }

    private function serializeRequest(SessionRequest $sessionRequest): array
    {
        return [
            'id' => (int) $sessionRequest->id,
            'status' => $sessionRequest->status,
            'goal_summary' => $sessionRequest->goal_summary,
            'request_notes' => $sessionRequest->request_notes,
            'admin_notes' => $sessionRequest->admin_notes,
            'viewer_timezone' => $sessionRequest->viewer_timezone,
            'scheduled_time' => optional($sessionRequest->scheduled_time)?->toISOString(),
            'created_at' => optional($sessionRequest->created_at)?->toISOString(),
            'reviewed_at' => optional($sessionRequest->reviewed_at)?->toISOString(),
            'approved_at' => optional($sessionRequest->approved_at)?->toISOString(),
            'rejected_at' => optional($sessionRequest->rejected_at)?->toISOString(),
            'client' => $sessionRequest->client ? [
                'id' => (int) $sessionRequest->client->id,
                'name' => $sessionRequest->client->name,
                'email' => $sessionRequest->client->email,
            ] : null,
            'preferred_coach' => $sessionRequest->preferredCoach ? [
                'id' => (int) $sessionRequest->preferredCoach->id,
                'name' => $sessionRequest->preferredCoach->name,
                'title' => $sessionRequest->preferredCoach->title,
                'timezone' => $sessionRequest->preferredCoach->timezone,
            ] : null,
            'assigned_coach' => $sessionRequest->assignedCoach ? [
                'id' => (int) $sessionRequest->assignedCoach->id,
                'name' => $sessionRequest->assignedCoach->name,
                'title' => $sessionRequest->assignedCoach->title,
                'timezone' => $sessionRequest->assignedCoach->timezone,
                'email' => $sessionRequest->assignedCoach->notification_email,
            ] : null,
            'approved_session' => $sessionRequest->approvedSession ? [
                'id' => (int) $sessionRequest->approvedSession->id,
                'status' => $sessionRequest->approvedSession->status,
                'scheduled_time' => optional($sessionRequest->approvedSession->scheduled_time)?->toISOString(),
                'duration_minutes' => (int) ($sessionRequest->approvedSession->duration_minutes ?? 15),
            ] : null,
            'reviewer' => $sessionRequest->reviewer ? [
                'id' => (int) $sessionRequest->reviewer->id,
                'name' => $sessionRequest->reviewer->name,
                'email' => $sessionRequest->reviewer->email,
            ] : null,
        ];
    }

    private function parseRequestedTime(string $rawScheduledTime, string $viewerTimezone): ?CarbonImmutable
    {
        $rawScheduledTime = trim($rawScheduledTime);
        if ($rawScheduledTime === '') {
            return null;
        }

        try {
            if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $rawScheduledTime)) {
                return CarbonImmutable::parse($rawScheduledTime)->setTimezone('UTC');
            }

            foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i'] as $format) {
                try {
                    return CarbonImmutable::createFromFormat($format, $rawScheduledTime, $viewerTimezone)->setTimezone('UTC');
                } catch (\Throwable) {
                    // Try next format.
                }
            }

            return CarbonImmutable::parse($rawScheduledTime, $viewerTimezone)->setTimezone('UTC');
        } catch (\Throwable) {
            return null;
        }
    }
}
