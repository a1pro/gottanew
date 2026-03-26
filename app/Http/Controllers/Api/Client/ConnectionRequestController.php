<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Session\SessionRequest;
use App\Services\Communication\NotificationService;
use App\Support\Timezone;
use Illuminate\Http\Request;

class ConnectionRequestController extends BaseController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $requests = SessionRequest::query()
            ->with([
                'preferredCoach:id,name,title,timezone',
                'assignedCoach:id,name,title,timezone',
                'approvedSession:id,status,scheduled_time,duration_minutes',
            ])
            ->where('client_id', $user->id)
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        $requests->setCollection(
            $requests->getCollection()->map(fn (SessionRequest $sessionRequest) => $this->serializeRequest($sessionRequest))
        );

        return $this->success($requests);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $validated = $request->validate([
            'preferred_coach_id' => ['nullable', 'exists:coaches,id'],
            'goal_summary' => ['nullable', 'string', 'max:255'],
            'request_notes' => ['nullable', 'string', 'max:3000'],
            'viewer_timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $openRequestExists = SessionRequest::query()
            ->where('client_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($openRequestExists) {
            return $this->error('You already have a pending free intro request.', 422);
        }

        $preferredCoach = null;
        if (!empty($validated['preferred_coach_id'])) {
            $preferredCoach = Coach::query()->findOrFail($validated['preferred_coach_id']);
        }

        $sessionRequest = SessionRequest::create([
            'client_id' => $user->id,
            'preferred_coach_id' => $preferredCoach?->id,
            'status' => 'pending',
            'goal_summary' => $validated['goal_summary'] ?? null,
            'request_notes' => $validated['request_notes'] ?? null,
            'viewer_timezone' => Timezone::normalize($validated['viewer_timezone'] ?? 'UTC', 'UTC'),
        ]);

        $admins = \App\Models\User::query()
            ->whereHas('roles', fn ($query) => $query->where('role', 'admin'))
            ->get();

        foreach ($admins as $admin) {
            $this->notificationService->createForUser($admin, [
                'category' => 'session_request',
                'priority' => 'high',
                'title' => 'New free intro request',
                'body' => sprintf(
                    '%s requested a free intro session%s.',
                    $user->name ?: $user->email,
                    $preferredCoach ? ' and prefers ' . $preferredCoach->name : ''
                ),
                'action_url' => '/admin/session-requests',
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'client_id' => $user->id,
                    'preferred_coach_id' => $preferredCoach?->id,
                ],
            ]);
        }

        $this->notificationService->createForUser($user, [
            'category' => 'session_request',
            'priority' => 'normal',
            'title' => 'Free intro request received',
            'body' => 'Your request is in the admin review queue. We will assign a coach and confirm the session time soon.',
            'action_url' => '/dashboard',
            'metadata' => [
                'session_request_id' => $sessionRequest->id,
                'status' => 'pending',
            ],
        ]);

        return $this->success(
            $this->serializeRequest($sessionRequest->load(['preferredCoach', 'assignedCoach', 'approvedSession'])),
            'Free intro request submitted successfully.',
            201
        );
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
            'updated_at' => optional($sessionRequest->updated_at)?->toISOString(),
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
            ] : null,
            'approved_session' => $sessionRequest->approvedSession ? [
                'id' => (int) $sessionRequest->approvedSession->id,
                'status' => $sessionRequest->approvedSession->status,
                'scheduled_time' => optional($sessionRequest->approvedSession->scheduled_time)?->toISOString(),
                'duration_minutes' => (int) ($sessionRequest->approvedSession->duration_minutes ?? 15),
            ] : null,
        ];
    }
}
