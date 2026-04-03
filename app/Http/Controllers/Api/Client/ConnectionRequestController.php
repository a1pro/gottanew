<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseController;
use App\Rules\TimezoneIdentifier;
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

           $query = SessionRequest::query()
               ->with([
                   'preferredCoach:id,name,title,timezone',
                   'assignedCoach:id,name,title,timezone',
                   'approvedSession:id,status,scheduled_time,duration_minutes',
               ])
               ->where('client_id', $user->id)
               ->latest();

           $summary = [
               'total' => (clone $query)->count(),
               'pending' => (clone $query)->where('status', 'pending')->count(),
               'approved' => (clone $query)->where('status', 'approved')->count(),

           'rejected' => (clone $query)->where('status', 'rejected')->count(),
      ];

      $requests = $query->paginate((int) $request->get('per_page', 10));

      $requests->setCollection(
          $requests->getCollection()->map(fn (SessionRequest $sessionRequest)
=> $this->serializeRequest($sessionRequest))
      );

      return $this->success([
          'data' => $requests,
          'summary' => $summary,
      ]);
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
          'viewer_timezone' => ['nullable', 'string', 'max:100', new
TimezoneIdentifier()],
      ]);

      $openRequestExists = SessionRequest::query()
          ->where('client_id', $user->id)
          ->where('status', 'pending')
          ->exists();

      if ($openRequestExists) {
          return $this->error('You already have a pending free intro
request.', 422);
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
          'viewer_timezone' =>
Timezone::normalize($validated['viewer_timezone'] ?? 'UTC', 'UTC'),
      ]);

      $this->notificationService->sessionRequestSubmitted($sessionRequest->load(['client', 'preferredCoach']));

      return $this->success(
          $this->serializeRequest($sessionRequest->load(['preferredCoach',
'assignedCoach', 'approvedSession'])),
          'Free intro request submitted successfully.',
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
          'reviewed_at' => optional($sessionRequest->reviewed_at)?->toISOString(),
          'approved_at' => optional($sessionRequest->approved_at)?->toISOString(),
          'rejected_at' => optional($sessionRequest->rejected_at)?->toISOString(),
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
