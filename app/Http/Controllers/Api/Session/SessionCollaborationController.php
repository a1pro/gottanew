<?php

namespace App\Http\Controllers\Api\Session;

use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionMessage;
use App\Models\Session\SessionResource;
use App\Models\User;
use App\Services\Communication\NotificationService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SessionCollaborationController extends BaseController
{
    private const STREAM_DURATION_SECONDS = 55;
    private const STREAM_POLL_MICROSECONDS = 1000000;
    private const STREAM_HEARTBEAT_SECONDS = 15;
    private const STREAM_MESSAGE_LIMIT = 150;
    private const STREAM_RESOURCE_LIMIT = 100;

    public function __construct(private NotificationService $notificationService)
    {
    }

    private function sessionRelations(): array
    {
        return [
            'coach',
            'client',
            'videoDetail',
            'recording',
            'participants',
            'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
        ];
    }

    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated');

        return $this->getAuthorizedSessionForUser($user, $id);
    }

    private function getAuthorizedSessionForUser(User $user, $id): CoachingSession
    {
        $session = CoachingSession::with($this->sessionRelations())->findOrFail($id);

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

    public function stream(Request $request, $id)
    {
        $user = $request->user() ?: $this->resolveStreamUser($request);

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $session = $this->getAuthorizedSessionForUser($user, $id);
        $sessionId = (int) $session->id;
        $lastFingerprint = (string) ($request->header('Last-Event-ID') ?: $request->query('last_event_id', ''));

        return response()->stream(function () use ($user, $sessionId, $lastFingerprint) {
            ignore_user_abort(true);
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $startedAt = microtime(true);
            $lastHeartbeatAt = microtime(true);
            $fingerprint = $lastFingerprint;
            $sentInitial = false;

            echo "retry: 5000\n\n";
            @flush();

            while (!connection_aborted() && (microtime(true) - $startedAt) < self::STREAM_DURATION_SECONDS) {
                $summary = $this->collaborationSummary($sessionId);

                if (!$sentInitial || $summary['version'] !== $fingerprint) {
                    $snapshot = $this->collaborationSnapshot($sessionId, $summary);
                    $this->emitSseEvent('session.sync', $snapshot, $snapshot['version']);
                    $fingerprint = $snapshot['version'];
                    $sentInitial = true;
                    $lastHeartbeatAt = microtime(true);
                } elseif ((microtime(true) - $lastHeartbeatAt) >= self::STREAM_HEARTBEAT_SECONDS) {
                    $this->emitSseEvent('heartbeat', [
                        'version' => $fingerprint,
                        'server_time' => now()->toISOString(),
                    ], $fingerprint ?: null);
                    $lastHeartbeatAt = microtime(true);
                }

                usleep(self::STREAM_POLL_MICROSECONDS);
            }

            $this->emitSseEvent('stream.end', [
                'reason' => 'reconnect',
                'version' => $fingerprint,
            ], $fingerprint ?: null);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function messages(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $messages = SessionMessage::with(['sender:id,name,email'])
            ->where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (SessionMessage $message) => $this->serializeMessage($message, $session))
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

        return $this->success($this->serializeMessage($message, $session), 'Message sent', 201);
    }

    public function resources(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $resources = SessionResource::with(['creator:id,name,email'])
            ->where('session_id', $session->id)
            ->latest()
            ->get()
            ->map(fn (SessionResource $resource) => $this->serializeResource($resource, $session))
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

        return $this->success($this->serializeResource($resource, $session), 'Resource shared', 201);
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

    private function collaborationSummary(int $sessionId): array
    {
        $session = CoachingSession::query()
            ->select('id', 'status', 'updated_at', 'last_activity_at')
            ->findOrFail($sessionId);

        $messageSummary = SessionMessage::query()
            ->where('session_id', $sessionId)
            ->selectRaw('COALESCE(MAX(id), 0) as latest_id')
            ->selectRaw('COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0) as latest_updated_at_ts')
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        $resourceSummary = SessionResource::query()
            ->where('session_id', $sessionId)
            ->selectRaw('COALESCE(MAX(id), 0) as latest_id')
            ->selectRaw('COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0) as latest_updated_at_ts')
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        $sessionUpdatedAtTs = max(
            optional($session->updated_at)?->timestamp ?? 0,
            optional($session->last_activity_at)?->timestamp ?? 0,
        );

        return [
            'session_status' => (string) $session->status,
            'session_updated_at_ts' => $sessionUpdatedAtTs,
            'message_latest_id' => (int) ($messageSummary?->latest_id ?? 0),
            'message_latest_updated_at_ts' => (int) ($messageSummary?->latest_updated_at_ts ?? 0),
            'message_total_count' => (int) ($messageSummary?->total_count ?? 0),
            'resource_latest_id' => (int) ($resourceSummary?->latest_id ?? 0),
            'resource_latest_updated_at_ts' => (int) ($resourceSummary?->latest_updated_at_ts ?? 0),
            'resource_total_count' => (int) ($resourceSummary?->total_count ?? 0),
            'version' => implode(':', [
                (string) $session->status,
                $sessionUpdatedAtTs,
                (int) ($messageSummary?->latest_id ?? 0),
                (int) ($messageSummary?->latest_updated_at_ts ?? 0),
                (int) ($messageSummary?->total_count ?? 0),
                (int) ($resourceSummary?->latest_id ?? 0),
                (int) ($resourceSummary?->latest_updated_at_ts ?? 0),
                (int) ($resourceSummary?->total_count ?? 0),
            ]),
        ];
    }

    private function collaborationSnapshot(int $sessionId, ?array $summary = null): array
    {
        $summary = $summary ?: $this->collaborationSummary($sessionId);
        $session = CoachingSession::with($this->sessionRelations())->findOrFail($sessionId);

        $messages = SessionMessage::with(['sender:id,name,email'])
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->limit(self::STREAM_MESSAGE_LIMIT)
            ->get()
            ->map(fn (SessionMessage $message) => $this->serializeMessage($message, $session))
            ->values()
            ->all();

        $resources = SessionResource::with(['creator:id,name,email'])
            ->where('session_id', $sessionId)
            ->latest()
            ->limit(self::STREAM_RESOURCE_LIMIT)
            ->get()
            ->map(fn (SessionResource $resource) => $this->serializeResource($resource, $session))
            ->values()
            ->all();

        return [
            'version' => $summary['version'],
            'session' => $this->serializeSession($session),
            'messages' => $messages,
            'resources' => $resources,
            'server_time' => now()->toISOString(),
        ];
    }

    private function resolveStreamUser(Request $request): ?User
    {
        $plainTextToken = (string) ($request->query('access_token') ?: $request->bearerToken());

        if ($plainTextToken === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (!$accessToken || !$accessToken->tokenable instanceof User) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        if (isset($tokenable->is_active) && !$tokenable->is_active) {
            return null;
        }

        return $tokenable;
    }

    private function serializeSession(CoachingSession $session): array
    {
        $fresh = $session->fresh($this->sessionRelations()) ?? $session;
        $payload = $fresh->toArray();
        $payload['source_request'] = $this->serializeSourceRequest($fresh->introRequest);
        $payload['intro_request'] = $payload['source_request'];

        return $payload;
    }

    private function serializeMessage(SessionMessage $message, CoachingSession $session): array
    {
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
    }

    private function serializeResource(SessionResource $resource, CoachingSession $session): array
    {
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
    }

    private function emitSseEvent(string $event, array $payload, ?string $eventId = null): void
    {
        if ($eventId !== null && $eventId !== '') {
            echo 'id: ' . str_replace(["\n", "\r"], '', $eventId) . "\n";
        }

        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";

        @flush();
    }
}
