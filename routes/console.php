<?php

use App\Jobs\SyncDailyTranscriptJob;
use App\Models\Session\SessionRecording;
use App\Services\Communication\EmailOutboxService;
use App\Services\Communication\MessageOutboxService;
use App\Services\Communication\SessionReminderService;
use App\Services\Video\DailyRestApiService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:dispatch-session-reminders {--limit=200}', function (SessionReminderService $reminderService) {
    $result = $reminderService->dispatchDueReminders((int) $this->option('limit'));

    $processed = (int) ($result['processed'] ?? 0);
    $emailScheduled = (int) ($result['email_scheduled'] ?? 0);
    $messageScheduled = (int) ($result['message_scheduled'] ?? 0);

    $this->info(sprintf(
        'Processed: %d | Emails scheduled: %d | Messages scheduled: %d',
        $processed,
        $emailScheduled,
        $messageScheduled
    ));
})->purpose('Dispatch due session reminder notifications');

Artisan::command('notifications:send-email-outbox {--limit=200}', function (EmailOutboxService $emailOutboxService) {
    $result = $emailOutboxService->sendDueEmails((int) $this->option('limit'));

    $processed = (int) ($result['processed'] ?? 0);
    $sent = (int) ($result['sent'] ?? 0);
    $failed = (int) ($result['failed'] ?? 0);
    $cancelled = (int) ($result['cancelled'] ?? 0);

    $this->info(sprintf(
        'Processed: %d | Sent: %d | Failed: %d | Cancelled: %d',
        $processed,
        $sent,
        $failed,
        $cancelled
    ));
})->purpose('Send pending email notifications from the EmailOutbox');

Artisan::command('notifications:send-message-outbox {--limit=200}', function (MessageOutboxService $messageOutboxService) {
    $result = $messageOutboxService->sendDueMessages((int) $this->option('limit'));

    $processed = (int) ($result['processed'] ?? 0);
    $sent = (int) ($result['sent'] ?? 0);
    $failed = (int) ($result['failed'] ?? 0);
    $cancelled = (int) ($result['cancelled'] ?? 0);

    $this->info(sprintf(
        'Processed: %d | Sent: %d | Failed: %d | Cancelled: %d',
        $processed,
        $sent,
        $failed,
        $cancelled
    ));
})->purpose('Send pending WhatsApp/SMS messages from the message outbox');

Artisan::command('daily:webhooks:sync {--url=} {--uuid=} {--retryType=} {--hmac=}', function (DailyRestApiService $dailyService) {
    if (!$dailyService->isConfigured()) {
        $this->error('Daily is not configured. Set DAILY_API_KEY first.');
        return 1;
    }

    $defaultUrl = rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/daily';
    $url = trim((string) ($this->option('url') ?: config('services.daily.webhook_url') ?: $defaultUrl));
    $uuid = trim((string) ($this->option('uuid') ?: ''));
    $retryType = trim((string) ($this->option('retryType') ?: config('services.daily.webhook_retry_type', 'circuit-breaker')));
    $hmac = trim((string) ($this->option('hmac') ?: config('services.daily.webhook_hmac', '')));

    $eventTypes = [
        'transcript.started',
        'transcript.ready-to-download',
        'transcript.error',
        'recording.started',
        'recording.ready-to-download',
        'recording.error',
    ];

    if ($url === '') {
        $this->error('Webhook URL is required. Use --url or set DAILY_WEBHOOK_URL.');
        return 1;
    }

    try {
        if ($uuid === '') {
            $existing = collect($dailyService->listWebhooks())->first(function ($hook) use ($url) {
                return is_array($hook) && isset($hook['url']) && (string) $hook['url'] === $url;
            });
            $uuid = is_array($existing) ? (string) ($existing['uuid'] ?? '') : '';
        }

        $result = $uuid !== ''
            ? $dailyService->updateWebhook($uuid, $url, $eventTypes, $hmac !== '' ? $hmac : null, $retryType)
            : $dailyService->createWebhook($url, $eventTypes, $hmac !== '' ? $hmac : null, $retryType);

        $this->info('Daily webhook synced successfully.');
        $this->line('URL: ' . ($result['url'] ?? $url));
        $this->line('UUID: ' . ($result['uuid'] ?? ($uuid !== '' ? $uuid : '(new)')));
        $this->line('Retry type: ' . ($result['retryType'] ?? $retryType));
        $this->line('Event types: ' . implode(', ', $result['eventTypes'] ?? $eventTypes));

        $returnedHmac = (string) ($result['hmac'] ?? '');
        if ($returnedHmac !== '' && $hmac === '') {
            $this->warn('A new webhook hmac was provisioned. Set DAILY_WEBHOOK_HMAC to this value so signature verification works:');
            $this->line($returnedHmac);
        }

        $this->info('Tip: For local development, set DAILY_WEBHOOK_URL to your tunnel URL (e.g., ngrok) + /api/v1/webhooks/daily, then rerun this command.');
        return 0;
    } catch (\Throwable $e) {
        $this->error('Failed to sync Daily webhook: ' . $e->getMessage());
        return 1;
    }
})->purpose('Create or update the Daily webhook so transcription/recording events reach this backend.');

Artisan::command('daily:sync-transcripts {--hours=48} {--limit=100} {--sync}', function (DailyRestApiService $dailyService) {
    if ($dailyService->usingFakeRoom()) {
        $this->error('Daily sync is unavailable while fake Daily rooms are enabled (DAILY_USE_FAKE_ROOM=true).');
        return 1;
    }

    if (!$dailyService->isConfigured()) {
        $this->error('Daily is not configured. Set DAILY_API_KEY first.');
        return 1;
    }

    $hours = max(1, (int) $this->option('hours'));
    $limit = max(1, (int) $this->option('limit'));
    $cutoff = now()->subHours($hours);

    $recordings = SessionRecording::query()
        ->with('session:id,status,actual_ended_at')
        ->whereNull('transcript')
        ->whereNotNull('daily_transcript_id')
        ->whereIn('transcription_status', ['active', 'completed'])
        ->whereHas('session', function ($query) use ($cutoff) {
            $query->whereNotNull('actual_ended_at')
                ->where('actual_ended_at', '>=', $cutoff);
        })
        ->orderByDesc('id')
        ->limit($limit)
        ->get();

    if ($recordings->isEmpty()) {
        $this->info('No transcripts pending sync.');
        return 0;
    }

    $sync = (bool) $this->option('sync');
    $dispatched = 0;

    foreach ($recordings as $recording) {
        $sessionId = (int) $recording->session_id;
        if ($sessionId <= 0) {
            continue;
        }

        if ($sync) {
            SyncDailyTranscriptJob::dispatchSync($sessionId);
        } else {
            SyncDailyTranscriptJob::dispatch($sessionId);
        }

        $dispatched++;
    }

    $this->info(sprintf(
        'Queued transcript sync for %d session(s) (window: last %d hours).',
        $dispatched,
        $hours
    ));

    return 0;
})->purpose('Backfill any Daily transcripts that were not persisted to the database (webhook missed, local dev, etc.).');

Schedule::command('notifications:dispatch-session-reminders --limit=500')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('notifications:dispatch-session-reminders');

Schedule::command('notifications:send-email-outbox --limit=500')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('notifications:send-email-outbox');

Schedule::command('notifications:send-message-outbox --limit=500')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('notifications:send-message-outbox');

Schedule::command('daily:sync-transcripts --hours=48 --limit=50')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->name('daily:sync-transcripts');