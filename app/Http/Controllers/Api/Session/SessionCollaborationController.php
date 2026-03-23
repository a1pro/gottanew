<?php

namespace App\Http\Controllers\Api\Session;

use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionMessage;
use App\Models\Session\SessionResource;
use App\Services\Communication\NotificationService;
use Illuminate\Http\Request;

class SessionCollaborationController extends BaseController
{
    public function __construct(private NotificationService $notificationService)
    {
    }
    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated');

        $session = CoachingSession::with(['coach', 'client'])->findOrFail($id);

        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

        abort_unless($isClient || $isCoach || $isAdmin, 403, 'Unauthorized access to session');

        return $session;
    }

    private function isCoachOrAdmin(Request $request, CoachingSession $session): bool
    {
        $user = $request->user();

        return (int) optional($session->coach)->user_id === (int) $user->id
            || (method_exists($user, 'isAdmin') ? $user->isAdmin() : false);
    }

    public function messages(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $messages = SessionMessage::with(['sender:id,name,email'])
            ->where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (SessionMessage $message) use ($session) {
                return [
                    'id' => $message->id,
                    'session_id' => $message->session_id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => optional($message->sender)->name,
                    'sender_email' => optional($message->sender)->email,
                    'sender_role' => (int) $message->sender_id === (int) $session->client_id ? 'client' : 'coach',
                    'message' => $message->message,
                    'attachments' => $message->attachments ?? [],
                    'created_at' => optional($message->created_at)->toISOString(),
                    'updated_at' => optional($message->updated_at)->toISOString(),
                ];
            })
            ->values();

        return $this->success($messages);
    }

    public function storeMessage(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
        ]);

        $message = SessionMessage::create([
            'session_id' => $session->id,
            'sender_id' => $request->user()->id,
            'message' => trim($validated['message']),
            'attachments' => $validated['attachments'] ?? null,
        ])->load('sender:id,name,email');

        $this->notificationService->sessionMessage($message);

        return $this->success([
            'id' => $message->id,
            'session_id' => $message->session_id,
            'sender_id' => $message->sender_id,
            'sender_name' => optional($message->sender)->name,
            'sender_email' => optional($message->sender)->email,
            'sender_role' => (int) $message->sender_id === (int) $session->client_id ? 'client' : 'coach',
            'message' => $message->message,
            'attachments' => $message->attachments ?? [],
            'created_at' => optional($message->created_at)->toISOString(),
            'updated_at' => optional($message->updated_at)->toISOString(),
        ], 'Message sent', 201);
    }

    public function resources(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $resources = SessionResource::with(['creator:id,name,email'])
            ->where('session_id', $session->id)
            ->latest()
            ->get()
            ->map(function (SessionResource $resource) use ($session) {
                return [
                    'id' => $resource->id,
                    'session_id' => $resource->session_id,
                    'created_by' => $resource->created_by,
                    'created_by_name' => optional($resource->creator)->name,
                    'created_by_role' => (int) $resource->created_by === (int) $session->client_id ? 'client' : 'coach',
                    'resource_type' => $resource->resource_type,
                    'title' => $resource->title,
                    'url' => $resource->url,
                    'description' => $resource->description,
                    'metadata' => $resource->metadata ?? [],
                    'created_at' => optional($resource->created_at)->toISOString(),
                    'updated_at' => optional($resource->updated_at)->toISOString(),
                ];
            })
            ->values();

        return $this->success($resources);
    }

    public function storeResource(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        abort_unless($this->isCoachOrAdmin($request, $session), 403, 'Only coaches can share session resources');

        $validated = $request->validate([
            'resource_type' => ['nullable', 'string', 'in:link,note'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (($validated['resource_type'] ?? 'link') === 'link' && empty($validated['url'])) {
            return $this->error('A link URL is required for link resources', 422);
        }

        $resource = SessionResource::create([
            'session_id' => $session->id,
            'created_by' => $request->user()->id,
            'resource_type' => $validated['resource_type'] ?? 'link',
            'title' => trim($validated['title']),
            'url' => $validated['url'] ?? null,
            'description' => $validated['description'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ])->load('creator:id,name,email');

        $this->notificationService->sessionResource($resource);

        return $this->success([
            'id' => $resource->id,
            'session_id' => $resource->session_id,
            'created_by' => $resource->created_by,
            'created_by_name' => optional($resource->creator)->name,
            'created_by_role' => (int) $resource->created_by === (int) $session->client_id ? 'client' : 'coach',
            'resource_type' => $resource->resource_type,
            'title' => $resource->title,
            'url' => $resource->url,
            'description' => $resource->description,
            'metadata' => $resource->metadata ?? [],
            'created_at' => optional($resource->created_at)->toISOString(),
            'updated_at' => optional($resource->updated_at)->toISOString(),
        ], 'Resource shared', 201);
    }

    public function destroyResource(Request $request, $id, $resourceId)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $user = $request->user();

        $resource = SessionResource::where('session_id', $session->id)->findOrFail($resourceId);

        $canDelete = (int) $resource->created_by === (int) $user->id
            || $this->isCoachOrAdmin($request, $session);

        abort_unless($canDelete, 403, 'You cannot delete this resource');

        $resource->delete();

        return $this->success([], 'Resource removed');
    }
}
