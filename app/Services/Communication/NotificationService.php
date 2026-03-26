<?php

namespace App\Services\Communication;

use App\Models\Communication\EmailOutbox;
use App\Models\Communication\UserNotification;
use App\Models\Core\Profile;
use App\Models\Finance\CoachPayout;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionMessage;
use App\Models\Session\SessionRequest;
use App\Models\Session\SessionResource;
use App\Models\User;
use App\Support\Timezone;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private SessionReminderService $sessionReminderService,
    ) {
    }

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

        if (($profile->notification_method ?? 'email') !== 'email') {
            $profile->forceFill(['notification_method' => 'email'])->save();
        }

        $notification = UserNotification::create([
            'user_id' => $user->id,
            'session_id' => $payload['session_id'] ?? null,
            'coach_payout_id' => $payload['coach_payout_id'] ?? null,
            'category' => $payload['category'] ?? 'general',
            'priority' => $payload['priority'] ?? 'normal',
            'title' => $payload['title'],
            'body' => $payload['body'],
            'action_url' => $payload['action_url'] ?? null,
            'channel' => 'email',
            'delivery_status' => 'stored',
            'metadata' => $payload['metadata'] ?? null,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        if (filled($user->email)) {
            $this->queueEmail($user, $notification, $payload);
            $notification->update(['delivery_status' => 'queued']);
        }

        return $notification;
    }

    public function sessionRequestSubmitted(SessionRequest $sessionRequest): void
    {
        $sessionRequest->loadMissing(['client', 'preferredCoach']);

        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role', 'admin'))
            ->get();

        foreach ($admins as $admin) {
            $this->createForUser($admin, [
                'category' => 'session_request',
                'priority' => 'high',
                'title' => 'New free intro request',
                'body' => sprintf(
                    '%s requested a free intro session%s.',
                    $sessionRequest->client?->name ?: $sessionRequest->client?->email ?: 'A client',
                    $sessionRequest->preferredCoach ? ' and prefers ' . $sessionRequest->preferredCoach->name : ''
                ),
                'action_url' => '/admin/session-requests',
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'client_id' => $sessionRequest->client_id,
                    'preferred_coach_id' => $sessionRequest->preferred_coach_id,
                    'status' => $sessionRequest->status,
                ],
            ]);
        }

        if ($sessionRequest->client) {
            $this->createForUser($sessionRequest->client, [
                'category' => 'session_request',
                'priority' => 'normal',
                'title' => 'Free intro request received',
                'body' => 'Your request is in the admin review queue. We will assign a coach and confirm the session time soon.',
                'action_url' => '/dashboard',
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'status' => $sessionRequest->status,
                ],
            ]);
        }
    }

    public function sessionRequestApproved(SessionRequest $sessionRequest, CoachingSession $session): void
    {
        $sessionRequest->loadMissing(['client', 'assignedCoach.user', 'approvedSession.stateLogs']);
        $session->loadMissing(['coach.user', 'client', 'stateLogs']);

        if ($sessionRequest->client) {
            $body = sprintf(
                'Your free intro request has been approved. %s is scheduled for %s.',
                $sessionRequest->assignedCoach?->name ?? 'Your coach',
                $this->formatSessionTimeForAudience($session, $sessionRequest->client)
            );

            if (filled($sessionRequest->admin_notes)) {
                $body .= ' Admin note: ' . Str::limit($sessionRequest->admin_notes, 120);
            }

            $this->createForUser($sessionRequest->client, [
                'session_id' => $session->id,
                'category' => 'session_request',
                'priority' => 'high',
                'title' => 'Free intro request approved',
                'body' => $body,
                'action_url' => $this->clientSessionActionUrl($session),
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'status' => $sessionRequest->status,
                    'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
                    'admin_notes' => $sessionRequest->admin_notes,
                ],
            ]);
        }

        if ($sessionRequest->assignedCoach?->user) {
            $coachBody = sprintf(
                'Admin approved a free intro request with %s for %s.',
                $sessionRequest->client?->name ?: 'a client',
                $this->formatSessionTimeForAudience($session, $sessionRequest->assignedCoach->user)
            );

            if (filled($sessionRequest->goal_summary)) {
                $coachBody .= ' Goal: ' . Str::limit($sessionRequest->goal_summary, 120);
            }

            $this->createForUser($sessionRequest->assignedCoach->user, [
                'session_id' => $session->id,
                'category' => 'session_request',
                'priority' => 'high',
                'title' => 'New intro session assigned',
                'body' => $coachBody,
                'action_url' => $this->coachSessionActionUrl($session),
                'metadata' => [
                    'session_request_id' => $sessionRequest->id,
                    'status' => $sessionRequest->status,
                    'goal_summary' => $sessionRequest->goal_summary,
                    'request_notes' => $sessionRequest->request_notes,
                ],
            ]);
        }
    }

    public function sessionRequestRejected(SessionRequest $sessionRequest): void
    {
        $sessionRequest->loadMissing(['client']);

        if (!$sessionRequest->client) {
            return;
        }

        $body = 'Your free intro request could not be scheduled yet. Please review the admin note and submit another request if needed.';
        if (filled($sessionRequest->admin_notes)) {
            $body .= ' Admin note: ' . Str::limit($sessionRequest->admin_notes, 160);
        }

        $this->createForUser($sessionRequest->client, [
            'category' => 'session_request',
            'priority' => 'normal',
            'title' => 'Free intro request updated',
            'body' => $body,
            'action_url' => '/dashboard',
            'metadata' => [
                'session_request_id' => $sessionRequest->id,
                'status' => $sessionRequest->status,
                'admin_notes' => $sessionRequest->admin_notes,
            ],
        ]);
    }

    public function sessionBooked(CoachingSession $session): void
    {
        $session->loadMissing(['coach.user', 'client', 'stateLogs']);

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
                    $this->formatSessionTimeForAudience($session, $session->coach->user)
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
                    $this->formatSessionTimeForAudience($session, $session->client)
                ),
                'action_url' => $this->clientSessionActionUrl($session),
                'metadata' => [
                    'session_status' => $session->status,
                    'actor' => 'system',
                ],
            ]);
        }

        $this->sessionReminderService->syncForSession($session);
    }

    public function syncSessionReminders(CoachingSession $session): void
    {
        $this->sessionReminderService->syncForSession($session);
    }

    public function sessionStateChanged(CoachingSession $session, string $fromState, string $toState, ?int $actorUserId = null): void
    {
        $session->loadMissing(['coach.user', 'client', 'stateLogs']);

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

        if ($this->normalizeState($toState) === 'scheduled') {
            $this->sessionReminderService->syncForSession($session);
        }

        if (in_array($this->normalizeState($toState), ['live', 'interrupted', 'completed', 'failed'], true)) {
            $this->sessionReminderService->cancelPendingForSession($session, 'Session moved out of scheduled state.');
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

    private function formatSessionTimeForAudience(CoachingSession $session, User $recipient): string
    {
        $scheduledTime = $session->scheduled_time;

        if (!$scheduledTime) {
            return 'the scheduled time';
        }

        $isCoachRecipient = $session->coach && (int) $session->coach->user_id === (int) $recipient->id;
        $timezone = $isCoachRecipient
            ? Timezone::normalize($session->coach?->timezone, 'UTC')
            : Timezone::normalize($this->resolveViewerTimezone($session), 'UTC');

        return $scheduledTime->copy()->setTimezone($timezone)->format('M d, Y h:i A');
    }

    private function resolveViewerTimezone(CoachingSession $session): ?string
    {
        $stateLog = $session->relationLoaded('stateLogs')
            ? $session->stateLogs
                ->sortByDesc(fn ($log) => optional($log->created_at)?->getTimestamp() ?? 0)
                ->first(fn ($log) => $log->to_state === 'scheduled')
            : $session->stateLogs()
                ->where('to_state', 'scheduled')
                ->orderByDesc('created_at')
                ->first();

        $viewerTimezone = data_get($stateLog?->metadata ?? [], 'viewer_timezone');

        return is_string($viewerTimezone) && trim($viewerTimezone) !== ''
            ? $viewerTimezone
            : null;
    }

    private function normalizeState(string $state): string
    {
        $normalized = trim(strtolower($state));

        return match ($normalized) {
            'in_progress' => 'live',
            default => $normalized,
        };
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
            'email',
            $notification->id,
        ]);

        EmailOutbox::updateOrCreate(
            ['dedup_key' => $dedupKey],
            [
                'user_notification_id' => $notification->id,
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
            ]
        );
    }
}
