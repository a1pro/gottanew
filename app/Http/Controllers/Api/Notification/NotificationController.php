<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Api\BaseController;
use App\Models\Communication\UserNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class NotificationController extends BaseController
{
    private const STREAM_DURATION_SECONDS = 55;
    private const STREAM_POLL_MICROSECONDS = 1000000;
    private const STREAM_HEARTBEAT_SECONDS = 15;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));

        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->when($request->filled('unread'), fn ($q) => $q->where('is_read', filter_var($request->get('unread'), FILTER_VALIDATE_BOOLEAN)))
            ->orderByRaw('CASE WHEN is_read = 0 THEN 0 ELSE 1 END')
            ->latest();

        $notifications = $query->paginate($perPage);
        $notifications->getCollection()->transform(fn (UserNotification $notification) => $this->serialize($notification));

        return $this->success([
            'items' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => UserNotification::query()->where('user_id', $user->id)->where('is_read', false)->count(),
        ]);
    }

    public function stream(Request $request)
    {
        $user = $request->user() ?: $this->resolveStreamUser($request);

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $lastFingerprint = (string) ($request->header('Last-Event-ID') ?: $request->query('last_event_id', ''));

        return response()->stream(function () use ($user, $lastFingerprint) {
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
                $summary = $this->notificationSummary((int) $user->id);

                if (!$sentInitial || $summary['version'] !== $fingerprint) {
                    $snapshot = $this->notificationSnapshot((int) $user->id, $summary);
                    $this->emitSseEvent('notifications.sync', $snapshot, $snapshot['version']);
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

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        return $this->success([
            'unread_count' => UserNotification::query()->where('user_id', $user->id)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $notification = UserNotification::query()->where('user_id', $user->id)->findOrFail($id);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->success($this->serialize($notification->fresh()), 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->success([], 'All notifications marked as read.');
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

    private function notificationSummary(int $userId): array
    {
        $summary = UserNotification::query()
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(MAX(id), 0) as latest_id')
            ->selectRaw('COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0) as latest_updated_at_ts')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) as unread_count')
            ->first();

        $latestId = (int) ($summary?->latest_id ?? 0);
        $latestUpdatedAtTs = (int) ($summary?->latest_updated_at_ts ?? 0);
        $totalCount = (int) ($summary?->total_count ?? 0);
        $unreadCount = (int) ($summary?->unread_count ?? 0);

        return [
            'latest_id' => $latestId,
            'latest_updated_at_ts' => $latestUpdatedAtTs,
            'total_count' => $totalCount,
            'unread_count' => $unreadCount,
            'version' => implode(':', [$latestId, $latestUpdatedAtTs, $unreadCount, $totalCount]),
        ];
    }

    private function notificationSnapshot(int $userId, ?array $summary = null): array
    {
        $summary = $summary ?: $this->notificationSummary($userId);

        $items = UserNotification::query()
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN is_read = 0 THEN 0 ELSE 1 END')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (UserNotification $notification) => $this->serialize($notification))
            ->values()
            ->all();

        return [
            'version' => $summary['version'],
            'unread_count' => $summary['unread_count'],
            'total_count' => $summary['total_count'],
            'items' => $items,
            'server_time' => now()->toISOString(),
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

    private function serialize(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'channel' => $notification->channel,
            'delivery_status' => $notification->delivery_status,
            'session_id' => $notification->session_id,
            'coach_payout_id' => $notification->coach_payout_id,
            'metadata' => $notification->metadata ?? [],
            'is_read' => (bool) $notification->is_read,
            'read_at' => optional($notification->read_at)->toISOString(),
            'sent_at' => optional($notification->sent_at)->toISOString(),
            'created_at' => optional($notification->created_at)->toISOString(),
        ];
    }
}
