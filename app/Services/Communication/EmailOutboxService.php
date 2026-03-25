<?php

namespace App\Services\Communication;

use App\Models\Communication\EmailOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailOutboxService
{
    public function sendDueEmails(int $limit = 100): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $cancelled = 0;

        EmailOutbox::query()
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
            ->each(function (EmailOutbox $email) use (&$processed, &$sent, &$failed, &$cancelled) {
                $processed++;

                DB::transaction(function () use ($email, &$sent, &$failed, &$cancelled) {
                    $locked = EmailOutbox::query()->lockForUpdate()->find($email->id);

                    if (!$locked || !in_array($locked->status, ['pending', 'failed'], true)) {
                        return;
                    }

                    if ($locked->expires_at && now()->greaterThanOrEqualTo($locked->expires_at)) {
                        $locked->update([
                            'status' => 'cancelled',
                            'last_error' => 'Email expired before it could be sent.',
                        ]);
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
                        Mail::html($this->renderHtml($locked), function ($message) use ($locked) {
                            $message->to($locked->recipient_email, $locked->recipient_name ?: null)
                                ->subject($locked->subject);
                        });

                        $locked->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                            'last_error' => null,
                        ]);
                        $sent++;
                    } catch (\Throwable $e) {
                        $locked->update([
                            'status' => ((int) $locked->attempts >= (int) $locked->max_attempts) ? 'cancelled' : 'failed',
                            'last_error' => $e->getMessage(),
                        ]);
                        $failed++;
                    }
                });
            });

        return compact('processed', 'sent', 'failed', 'cancelled');
    }

    private function renderHtml(EmailOutbox $email): string
    {
        $payload = is_array($email->payload) ? $email->payload : [];
        $title = e((string) ($payload['title'] ?? $email->subject));
        $body = nl2br(e((string) ($payload['body'] ?? 'You have a new update from your coaching platform.')));
        $actionUrl = $payload['action_url'] ?? null;
        $ctaLabel = e((string) ($payload['cta_label'] ?? 'Open session'));
        $footer = e((string) ($payload['footer'] ?? 'This is an automated message from the coaching platform.'));

        $button = '';
        if (is_string($actionUrl) && $actionUrl !== '') {
            $button = sprintf(
                '<p style="margin:24px 0;"><a href="%s" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;">%s</a></p><p style="font-size:12px;color:#6b7280;word-break:break-all;">%s</p>',
                e($actionUrl),
                $ctaLabel,
                e($actionUrl)
            );
        }

        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:24px;color:#111827;">
  <h2 style="margin:0 0 16px;">{$title}</h2>
  <div style="font-size:15px;line-height:1.6;">{$body}</div>
  {$button}
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
  <p style="font-size:12px;color:#6b7280;">{$footer}</p>
</div>
HTML;
    }
}
