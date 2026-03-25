<?php

use App\Services\Communication\EmailOutboxService;
use App\Services\Communication\MessageOutboxService;
use App\Services\Communication\SessionReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:dispatch-session-reminders {--limit=200}', function (SessionReminderService $reminderService) {
    $result = $reminderService->dispatchDueReminders((int) $this->option('limit'));

    $this->info(sprintf(
        'Processed: %d | Sent: %d | Failed: %d | Cancelled: %d',
        $result['processed'],
        $result['sent'],
        $result['failed'],
        $result['cancelled']
    ));
})->purpose('Dispatch due session reminder notifications');

Artisan::command('notifications:send-email-outbox {--limit=100}', function (EmailOutboxService $emailOutboxService) {
    $result = $emailOutboxService->sendDueEmails((int) $this->option('limit'));

    $this->info(sprintf(
        'Processed: %d | Sent: %d | Failed: %d | Cancelled: %d',
        $result['processed'],
        $result['sent'],
        $result['failed'],
        $result['cancelled']
    ));
})->purpose('Send pending emails from the email outbox');

Artisan::command('notifications:send-message-outbox {--limit=100}', function (MessageOutboxService $messageOutboxService) {
    $result = $messageOutboxService->sendDueMessages((int) $this->option('limit'));

    $this->info(sprintf(
        'Processed: %d | Sent: %d | Failed: %d | Cancelled: %d',
        $result['processed'],
        $result['sent'],
        $result['failed'],
        $result['cancelled']
    ));
})->purpose('Send pending WhatsApp/SMS messages from the message outbox');

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
