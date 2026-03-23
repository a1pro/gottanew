<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Api\BaseController;
use App\Models\Communication\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
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
