<?php

namespace App\Services\Communication;

use App\Models\Communication\MessageOutbox;
use App\Models\Communication\UserNotification;
use Illuminate\Support\Facades\DB;

class MessageOutboxService
{
    public function __construct(private TwilioMessagingService $twilioMessagingService)
    {
    }

    public function sendDueMessages(int $limit = 100): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $cancelled = 0;

        MessageOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(function ($query) {
                $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereColumn('attempts', '<', 'max_attempts')
            ->orderByRaw('CASE WHEN scheduled_for IS NULL THEN 0 ELSE 1 END')
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit(max(1, min(1000, $limit)))
            ->get()
            ->each(function (MessageOutbox $message) use (&$processed, &$sent, &$failed, &$cancelled) {
                $processed++;

                DB::transaction(function () use ($message, &$sent, &$failed, &$cancelled) {
                    $locked = MessageOutbox::query()->lockForUpdate()->find($message->id);
                    if (!$locked || !in_array($locked->status, ['pending', 'failed'], true)) {
                        return;
                    }

                    if ($locked->expires_at && now()->greaterThanOrEqualTo($locked->expires_at)) {
                        $locked->update([
                            'status' => 'cancelled',
                            'last_error' => 'Message expired before it could be sent.',
                        ]);
                        $this->updateNotificationStatus($locked->notification, 'cancelled');
                        $cancelled++;
                        return;
                    }

                    $locked->update([
                        'status' => 'sending',
                        'attempts' => (int) $locked->attempts + 1,
                        'last_attempt_at' => now(),
                        'last_error' => null,
                    ]);

                    try {
                        $result = $this->twilioMessagingService->send([
                            'channel' => $locked->channel,
                            'to' => $locked->recipient_phone,
                            'from' => $locked->sender_id,
                            'body' => $locked->body,
                        ]);

                        $status = $this->mapProviderStatus((string) ($result['status'] ?? 'queued'));

                        $locked->update([
                            'provider_message_id' => $result['sid'] ?? null,
                            'provider_status' => $result['status'] ?? null,
                            'status' => $status,
                            'sent_at' => in_array($status, ['sent', 'delivered', 'read'], true) ? now() : $locked->sent_at,
                            'payload' => array_merge($locked->payload ?? [], ['twilio_response' => $result['raw'] ?? null]),
                        ]);

                        $this->updateNotificationStatus($locked->notification, $status);
                        $sent++;
                    } catch (\Throwable $e) {
                        $finalStatus = ((int) $locked->attempts >= (int) $locked->max_attempts) ? 'cancelled' : 'failed';
                        $locked->update([
                            'status' => $finalStatus,
                            'last_error' => $e->getMessage(),
                        ]);
                        $this->updateNotificationStatus($locked->notification, $finalStatus === 'cancelled' ? 'failed' : 'queued');
                        $failed++;
                    }
                });
            });

        return compact('processed', 'sent', 'failed', 'cancelled');
    }

    public function handleProviderStatusCallback(array $payload): ?MessageOutbox
    {
        $sid = (string) ($payload['MessageSid'] ?? '');
        if ($sid === '') {
            return null;
        }

        /** @var MessageOutbox|null $message */
        $message = MessageOutbox::query()->where('provider_message_id', $sid)->first();
        if (!$message) {
            return null;
        }

        $twilioStatus = (string) ($payload['MessageStatus'] ?? '');
        $status = $this->mapProviderStatus($twilioStatus);
        $updates = [
            'provider_status' => $twilioStatus !== '' ? $twilioStatus : $message->provider_status,
            'status' => $status,
            'last_error' => $payload['ErrorMessage'] ?? $message->last_error,
            'payload' => array_merge($message->payload ?? [], ['twilio_last_callback' => $payload]),
        ];

        if (in_array($status, ['sent', 'delivered', 'read'], true) && $message->sent_at === null) {
            $updates['sent_at'] = now();
        }
        if ($status === 'delivered') {
            $updates['delivered_at'] = now();
        }
        if ($status === 'read') {
            $updates['read_at'] = now();
            $updates['delivered_at'] = $message->delivered_at ?? now();
        }

        $message->update($updates);
        $this->updateNotificationStatus($message->notification, $status);

        return $message->fresh();
    }

    private function mapProviderStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'queued', 'accepted', 'scheduled', 'sending' => 'sent',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'undelivered' => 'undelivered',
            'failed', 'canceled', 'cancelled' => 'failed',
            default => 'sent',
        };
    }

    private function updateNotificationStatus(?UserNotification $notification, string $status): void
    {
        if (!$notification) {
            return;
        }

        $deliveryStatus = match ($status) {
            'sent' => 'queued',
            'delivered', 'read' => 'sent',
            'undelivered', 'failed', 'cancelled' => 'failed',
            default => $notification->delivery_status,
        };

        $notification->update(['delivery_status' => $deliveryStatus]);
    }
}
