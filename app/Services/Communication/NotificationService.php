<?php

namespace App\Services\Communication;

use App\Models\Communication\EmailOutbox;
use App\Models\Communication\UserNotification;
use App\Models\Core\Profile;
use App\Models\Finance\CoachPayout;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionMessage;
use App\Models\Session\SessionResource;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public function createForUser(User $user, array $payload): UserNotification
    {
        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $notification = UserNotification::create([
            'user_id' => $user->id,
            'session_id' => $payload['session_id'] ?? null,
            'coach_payout_id' => $payload['coach_payout_id'] ?? null,
            'category' => $payload['category'] ?? 'general',
            'priority' => $payload['priority'] ?? 'normal',
            'title' => $payload['title'],
            'body' => $payload['body'],
            'action_url' => $payload['action_url'] ?? null,
            'channel' => $profile->notification_method,
            'delivery_status' => 'stored',
            'metadata' => $payload['metadata'] ?? null,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        if (in_array($profile->notification_method, ['email', 'both'], true) && filled($user->email)) {
            $this->queueEmail($user, $notification, $payload);
            $notification->update(['delivery_status' => 'queued']);
        }

        return $notification;
    }

    public function sessionBooked(CoachingSession $session): void
    {
        $session->loadMissing(['coach.user', 'client']);

        if ($session->coach?->user) {
            $this->createForUser($session->coach->user, [
                'session_id' => $session->id,
                'category' => 'session_booked',
                'priority' => 'high',
                'title' => 'New coaching session booked',
                'body' => sprintf(
                    '%s booked a %d-minute session for %s.',
                    $session->client?->name ?? 'A client',
                    (int) ($session->duration_minutes ?? 15),
                    optional($session->scheduled_time)->format('M d, Y h:i A') ?? 'the scheduled time'
                ),
                'action_url' => $this->coachSessionActionUrl($session),
                'metadata' => [
                    'session_status' => $session->status,
                    'actor' => 'client',
                ],
            ]);
        }

        if ($session->client) {
            $this->createForUser($session->client, [
                'session_id' => $session->id,
                'category' => 'session_booked',
                'priority' => 'normal',
                'title' => 'Session confirmed',
                'body' => sprintf(
                    'Your session with %s is confirmed for %s.',
                    $session->coach?->name ?? 'your coach',
                    optional($session->scheduled_time)->format('M d, Y h:i A') ?? 'the scheduled time'
                ),
                'action_url' => "/session/{$session->id}",
                'metadata' => [
                    'session_status' => $session->status,
                    'actor' => 'system',
                ],
            ]);
        }
    }

    public function sessionStateChanged(CoachingSession $session, string $fromState, string $toState, ?int $actorUserId = null): void
    {
        $session->loadMissing(['coach.user', 'client']);

        $targets = [];
        if ($session->client) {
            $targets[] = $session->client;
        }
        if ($session->coach?->user) {
            $targets[] = $session->coach->user;
        }

        $title = match ($toState) {
            'live' => 'Session is now live',
            'interrupted' => 'Session interrupted',
            'completed' => 'Session completed',
            'failed' => 'Session marked failed',
            default => 'Session status updated',
        };

        foreach ($targets as $user) {
            if ($actorUserId && (int) $user->id === (int) $actorUserId) {
                continue;
            }

            $isCoach = $session->coach && (int) $session->coach->user_id === (int) $user->id;
            $actionUrl = $isCoach ? $this->coachSessionActionUrl($session) : $this->clientSessionActionUrl($session);

            $this->createForUser($user, [
                'session_id' => $session->id,
                'category' => 'session_status',
                'priority' => in_array($toState, ['live', 'interrupted', 'failed'], true) ? 'high' : 'normal',
                'title' => $title,
                'body' => sprintf(
                    'Session #%d changed from %s to %s.',
                    $session->id,
                    Str::headline($fromState),
                    Str::headline($toState)
                ),
                'action_url' => $actionUrl,
                'metadata' => [
                    'from_state' => $fromState,
                    'to_state' => $toState,
                ],
            ]);
        }
    }

    public function sessionMessage(SessionMessage $message): void
    {
        $message->loadMissing(['sender', 'session.coach.user', 'session.client']);
        $session = $message->session;
        if (!$session) {
            return;
        }

        $recipient = (int) $message->sender_id === (int) $session->client_id
            ? $session->coach?->user
            : $session->client;

        if (!$recipient) {
            return;
        }

        $isCoach = $session->coach && (int) $session->coach->user_id === (int) $recipient->id;

        $this->createForUser($recipient, [
            'session_id' => $session->id,
            'category' => 'session_message',
            'priority' => 'normal',
            'title' => 'New session message',
            'body' => sprintf('%s sent you a new message in session #%d.', $message->sender?->name ?? 'Someone', $session->id),
            'action_url' => $isCoach ? $this->coachSessionActionUrl($session) : $this->clientSessionActionUrl($session),
            'metadata' => [
                'preview' => Str::limit($message->message, 120),
            ],
        ]);
    }

    public function sessionResource(SessionResource $resource): void
    {
        $resource->loadMissing(['creator', 'session.coach.user', 'session.client']);
        $session = $resource->session;
        if (!$session) {
            return;
        }

        $targets = [];
        if ($session->client && (int) $resource->created_by !== (int) $session->client_id) {
            $targets[] = $session->client;
        }
        if ($session->coach?->user && (int) $resource->created_by !== (int) $session->coach->user_id) {
            $targets[] = $session->coach->user;
        }

        foreach ($targets as $user) {
            $isCoach = $session->coach && (int) $session->coach->user_id === (int) $user->id;

            $this->createForUser($user, [
                'session_id' => $session->id,
                'category' => 'session_resource',
                'priority' => 'normal',
                'title' => 'New session resource shared',
                'body' => sprintf('%s shared "%s" in session #%d.', $resource->creator?->name ?? 'Someone', $resource->title, $session->id),
                'action_url' => $isCoach ? $this->coachSessionActionUrl($session) : $this->clientSessionActionUrl($session),
                'metadata' => [
                    'resource_type' => $resource->resource_type,
                    'resource_title' => $resource->title,
                ],
            ]);
        }
    }

    public function payoutStatus(CoachPayout $payout, string $status): void
    {
        $payout->loadMissing(['coach.user', 'payoutCycle']);
        $user = $payout->coach?->user;

        if (!$user) {
            return;
        }

        $title = $status === 'paid' ? 'Coach payout paid' : 'Coach payout approved';
        $body = sprintf(
            'Your payout for %s is now %s. Amount: $%0.2f.',
            $payout->payoutCycle?->month_key ?? 'the selected month',
            $status,
            (float) $payout->payout_amount
        );

        $this->createForUser($user, [
            'coach_payout_id' => $payout->id,
            'category' => 'coach_payout',
            'priority' => 'normal',
            'title' => $title,
            'body' => $body,
            'action_url' => '/coach/earnings',
            'metadata' => [
                'month_key' => $payout->payoutCycle?->month_key,
                'status' => $status,
                'payout_amount' => (float) $payout->payout_amount,
            ],
        ]);
    }

    private function coachSessionActionUrl(CoachingSession $session): string
    {
        return "/session/{$session->id}/coach-join";
    }

    private function clientSessionActionUrl(CoachingSession $session): string
    {
        return "/session/{$session->id}";
    }

    private function queueEmail(User $user, UserNotification $notification, array $payload): void
    {
        $dedupKey = implode(':', [
            'notification',
            $notification->user_id,
            $notification->category,
            $notification->session_id ?: 'none',
            $notification->coach_payout_id ?: 'none',
            now()->format('YmdHisv'),
        ]);

        EmailOutbox::create([
            'dedup_key' => $dedupKey,
            'template_name' => 'generic_notification',
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $payload['title'],
            'payload' => [
                'title' => $payload['title'],
                'body' => $payload['body'],
                'action_url' => $payload['action_url'] ?? null,
                'notification_id' => $notification->id,
            ],
            'status' => 'pending',
            'scheduled_for' => now(),
        ]);
    }
}
