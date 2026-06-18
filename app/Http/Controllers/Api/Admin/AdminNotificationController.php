<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Communication\UserNotification;
use Illuminate\Http\Request;

class AdminNotificationController extends BaseController
{
    public function deliveryLogs(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $queryText = trim((string) $request->get('q', ''));
        $channel = trim((string) $request->get('channel', ''));
        $status = trim((string) $request->get('status', ''));
        $userRole = trim((string) $request->get('role', ''));

        $query = UserNotification::query()
            ->with([
                'user:id,name,email',
                'session:id,status,created_at',
                'emailOutboxes:id,user_notification_id,recipient_email,status,attempts,last_error,sent_at,scheduled_for,created_at',
                'messageOutboxes:id,user_notification_id,channel,recipient_phone,status,provider_status,provider_message_id,attempts,last_error,sent_at,delivered_at,read_at,created_at',
            ])
            ->when($queryText !== '', function ($builder) use ($queryText) {
                $builder->where(function ($inner) use ($queryText) {
                    $inner->where('title', 'like', "%{$queryText}%")
                        ->orWhere('body', 'like', "%{$queryText}%")
                        ->orWhere('category', 'like', "%{$queryText}%")
                        ->orWhereHas('user', function ($userQuery) use ($queryText) {
                            $userQuery->where('name', 'like', "%{$queryText}%")
                                ->orWhere('email', 'like', "%{$queryText}%");
                        });
                });
            })
            ->when($channel !== '' && $channel !== 'all', function ($builder) use ($channel) {
                $builder->where(function ($inner) use ($channel) {
                    if ($channel === 'email') {
                        $inner->whereHas('emailOutboxes');
                        return;
                    }

                    if (in_array($channel, ['sms', 'whatsapp'], true)) {
                        $inner->whereHas('messageOutboxes', fn ($messageQuery) => $messageQuery->where('channel', $channel));
                        return;
                    }

                    $inner->where('channel', $channel);
                });
            })
            ->when($status !== '' && $status !== 'all', function ($builder) use ($status) {
                $builder->where(function ($inner) use ($status) {
                    $inner->where('delivery_status', $status)
                        ->orWhereHas('emailOutboxes', fn ($emailQuery) => $emailQuery->where('status', $status))
                        ->orWhereHas('messageOutboxes', fn ($messageQuery) => $messageQuery->where('status', $status));
                });
            })
            ->when($userRole !== '' && $userRole !== 'all', function ($builder) use ($userRole) {
                $builder->whereHas('user.roles', fn ($roleQuery) => $roleQuery->where('name', $userRole));
            })
            ->latest();

        $notifications = $query->paginate($perPage);
        $notifications->getCollection()->transform(fn (UserNotification $notification) => $this->serialize($notification));

        $countsBase = UserNotification::query();

        return $this->success([
            'items' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'summary' => [
                'total_notifications' => (clone $countsBase)->count(),
                'unread_notifications' => (clone $countsBase)->where('is_read', false)->count(),
                'queued_notifications' => (clone $countsBase)->where('delivery_status', 'queued')->count(),
                'failed_notifications' => (clone $countsBase)->where('delivery_status', 'failed')->count(),
            ],
        ]);
    }

    private function serialize(UserNotification $notification): array
    {
        $emailOutbox = $notification->emailOutboxes;
        $messageOutbox = $notification->messageOutboxes;

        return [
            'id' => $notification->id,
            'user' => $notification->user ? [
                'id' => $notification->user->id,
                'name' => $notification->user->name,
                'email' => $notification->user->email,
            ] : null,
            'title' => $notification->title,
            'body' => $notification->body,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'channel' => $notification->channel,
            'delivery_status' => $notification->delivery_status,
            'session' => $notification->session ? [
                'id' => $notification->session->id,
                'status' => $notification->session->status,
                'created_at' => optional($notification->session->created_at)->toISOString(),
            ] : null,
            'metadata' => $notification->metadata ?? [],
            'is_read' => (bool) $notification->is_read,
            'read_at' => optional($notification->read_at)->toISOString(),
            'sent_at' => optional($notification->sent_at)->toISOString(),
            'created_at' => optional($notification->created_at)->toISOString(),
            'email_delivery' => $emailOutbox ? [
                'recipient' => $emailOutbox->recipient_email,
                'status' => $emailOutbox->status,
                'attempts' => (int) $emailOutbox->attempts,
                'last_error' => $emailOutbox->last_error,
                'scheduled_for' => optional($emailOutbox->scheduled_for)->toISOString(),
                'sent_at' => optional($emailOutbox->sent_at)->toISOString(),
                'created_at' => optional($emailOutbox->created_at)->toISOString(),
            ] : null,
            'message_delivery' => $messageOutbox ? [
                'channel' => $messageOutbox->channel,
                'recipient' => $messageOutbox->recipient_phone,
                'status' => $messageOutbox->status,
                'provider_status' => $messageOutbox->provider_status,
                'provider_message_id' => $messageOutbox->provider_message_id,
                'attempts' => (int) $messageOutbox->attempts,
                'last_error' => $messageOutbox->last_error,
                'sent_at' => optional($messageOutbox->sent_at)->toISOString(),
                'delivered_at' => optional($messageOutbox->delivered_at)->toISOString(),
                'read_at' => optional($messageOutbox->read_at)->toISOString(),
                'created_at' => optional($messageOutbox->created_at)->toISOString(),
            ] : null,
        ];
    }
}
