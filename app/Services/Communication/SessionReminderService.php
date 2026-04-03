<?php

namespace App\Services\Communication;

use App\Models\Communication\ScheduledNotification;
use App\Models\Session\CoachingSession;
use App\Models\User;
use App\Support\Timezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionReminderService
{
    private const REMINDER_WINDOWS = [
        ['key' => '24h', 'minutes_before' => 1440, 'label' => '24 hours'],
        ['key' => '1h', 'minutes_before' => 60, 'label' => '1 hour'],
        ['key' => '10m', 'minutes_before' => 10, 'label' => '10 minutes'],
    ];

    public function syncForSession(CoachingSession $session): void
    {
        $session->loadMissing(['coach.user', 'client']);

        if (!$session->scheduled_time || $this->normalizeState((string) $session->status) !== 'scheduled') {
            $this->cancelPendingForSession($session, 'Session is no longer in scheduled state.');
            return;
        }

        $targets = collect([
            [
                'user' => $session->client,
                'action_url' => "/session/{$session->id}",
                'role' => 'client',
            ],
            [
                'user' => $session->coach?->user,
                'action_url' => "/session/{$session->id}/coach-join",
                'role' => 'coach',
            ],
        ])->filter(fn (array $entry) => $entry['user'] instanceof User)->values();

        if ($targets->isEmpty()) {
            return;
        }

        $keepIds = [];

        foreach ($targets as $target) {
            /** @var User $user */
            $user = $target['user'];

            foreach (self::REMINDER_WINDOWS as $window) {
                $sendAt = $session->scheduled_time->copy()->subMinutes((int) $window['minutes_before']);

                if ($sendAt->lessThanOrEqualTo(now())) {
                    continue;
                }

                $entry = ScheduledNotification::query()->firstOrNew([
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'reminder_key' => (string) $window['key'],
                ]);

                if ($entry->exists && in_array($entry->status, ['sent'], true)) {
                    $keepIds[] = $entry->id;
                    continue;
                }

                $entry->fill([
                    'category' => 'session_reminder',
                    'priority' => (string) $window['key'] === '10m' ? 'high' : 'normal',
                    'title' => $this->titleForWindow((string) $window['key']),
                    'body' => $this->bodyForWindow($session, $user, (string) $window['label']),
                    'action_url' => (string) $target['action_url'],
                    'metadata' => [
                        'role' => $target['role'],
                        'reminder_key' => $window['key'],
                        'minutes_before' => $window['minutes_before'],
                        'scheduled_time' => optional($session->scheduled_time)->toISOString(),
                        'session_status' => $session->status,
                        'coach_name' => $session->coach?->name,
                        'client_name' => $session->client?->name,
                    ],
                    'send_at' => $sendAt,
                    'status' => 'pending',
                    'cancelled_at' => null,
                    'failed_at' => null,
                    'last_error' => null,
                ]);
                $entry->save();
                $keepIds[] = $entry->id;
            }
        }

        ScheduledNotification::query()
            ->where('session_id', $session->id)
            ->whereIn('status', ['pending', 'failed'])
            ->when(!empty($keepIds), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'last_error' => 'Cancelled because reminder no longer matches the current session schedule.',
                'updated_at' => now(),
            ]);
    }

    public function cancelPendingForSession(CoachingSession $session, ?string $reason = null): int
    {
        return ScheduledNotification::query()
            ->where('session_id', $session->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'last_error' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function dispatchDueReminders(int $limit = 200): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $cancelled = 0;

        ScheduledNotification::query()
            ->with(['user', 'session.coach.user', 'session.client'])
            ->whereIn('status', ['pending', 'failed'])
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit(max(1, min(1000, $limit)))
            ->get()
            ->each(function (ScheduledNotification $scheduled) use (&$processed, &$sent, &$failed, &$cancelled) {
                $processed++;

                DB::transaction(function () use ($scheduled, &$sent, &$failed, &$cancelled) {
                    $locked = ScheduledNotification::query()->lockForUpdate()->find($scheduled->id);

                    if (!$locked || !in_array($locked->status, ['pending', 'failed'], true)) {
                        return;
                    }

                    $session = $locked->session;
                    $user = $locked->user;

                    if (!$user || !$session || $this->normalizeState((string) $session->status) !== 'scheduled') {
                        $locked->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'last_error' => 'Reminder cancelled because the user or scheduled session is no longer available.',
                        ]);
                        $cancelled++;
                        return;
                    }

                    $locked->update([
                        'status' => 'sending',
                        'failed_at' => null,
                        'last_error' => null,
                    ]);

                    try {
                        /** @var \App\Services\Communication\NotificationService $notificationService */
                        $notificationService = app(NotificationService::class);

                        $notification = $notificationService->createForUser($user, [
                            'session_id' => $session->id,
                            'category' => $locked->category,
                            'priority' => $locked->priority,
                            'title' => $locked->title,
                            'body' => $locked->body,
                            'action_url' => $locked->action_url,
                            'metadata' => array_merge($locked->metadata ?? [], [
                                'scheduled_notification_id' => $locked->id,
                                'send_at' => optional($locked->send_at)->toISOString(),
                            ]),
                        ]);

                        $locked->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                            'user_notification_id' => $notification->id,
                        ]);
                        $sent++;
                    } catch (\Throwable $e) {
                        $locked->update([
                            'status' => 'failed',
                            'failed_at' => now(),
                            'last_error' => $e->getMessage(),
                        ]);
                        $failed++;
                    }
                });
            });

        return compact('processed', 'sent', 'failed', 'cancelled');
    }

    public function upcomingForSession(CoachingSession $session): Collection
    {
        return ScheduledNotification::query()
            ->where('session_id', $session->id)
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('send_at')
            ->get();
    }

    private function titleForWindow(string $key): string
    {
        return match ($key) {
            '24h' => 'Session reminder for tomorrow',
            '1h' => 'Session reminder in 1 hour',
            '10m' => 'Session starts in 10 minutes',
            default => 'Upcoming coaching session reminder',
        };
    }

    private function bodyForWindow(CoachingSession $session, User $recipient, string $windowLabel): string
    {
        $scheduledTime = $session->scheduled_time;
        $scheduled = $scheduledTime
            ? $scheduledTime->copy()->setTimezone($this->resolveAudienceTimezone($session, $recipient))->format('M d, Y h:i A')
            : 'the scheduled time';
        $coachName = $session->coach?->name ?? 'your coach';
        $clientName = $session->client?->name ?? 'your client';

        $isCoach = $session->coach && (int) $session->coach->user_id === (int) $recipient->id;

        return $isCoach
            ? sprintf('%s is coming up in %s with %s at %s.', 'Your coaching session', $windowLabel, $clientName, $scheduled)
            : sprintf('%s is coming up in %s with %s at %s.', 'Your coaching session', $windowLabel, $coachName, $scheduled);
    }

    private function resolveAudienceTimezone(CoachingSession $session, User $recipient): string
    {
        $isCoach = $session->coach && (int) $session->coach->user_id === (int) $recipient->id;

        if ($isCoach) {
            return Timezone::normalize($session->coach?->timezone, 'UTC');
        }

        return Timezone::normalize($this->resolveViewerTimezone($session), 'UTC');
    }

    private function resolveViewerTimezone(CoachingSession $session): ?string
    {
        $scheduledLog = $session->relationLoaded('stateLogs')
            ? $session->stateLogs
                ->sortByDesc(fn ($log) => optional($log->created_at)?->getTimestamp() ?? 0)
                ->first(fn ($log) => $this->normalizeState((string) $log->to_state) === 'scheduled')
            : $session->stateLogs()
                ->where('to_state', 'scheduled')
                ->orderByDesc('created_at')
                ->first();

        $viewerTimezone = data_get($scheduledLog?->metadata ?? [], 'viewer_timezone');

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
}
