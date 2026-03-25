<?php

namespace App\Services\Communication;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioMessagingService
{
    public function send(array $payload): array
    {
        $accountSid = (string) config('services.twilio.account_sid', '');
        if ($accountSid === '') {
            throw new RuntimeException('TWILIO_ACCOUNT_SID is not configured.');
        }

        [$username, $password] = $this->credentials();

        $response = Http::asForm()
            ->withBasicAuth($username, $password)
            ->post(sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $accountSid), $this->buildForm($payload));

        if (!$response->successful()) {
            throw new RuntimeException($this->extractError($response->json(), $response->body()));
        }

        $json = $response->json();

        return [
            'sid' => Arr::get($json, 'sid'),
            'status' => Arr::get($json, 'status', 'queued'),
            'from' => Arr::get($json, 'from', $payload['from'] ?? null),
            'to' => Arr::get($json, 'to', $payload['to'] ?? null),
            'raw' => $json,
        ];
    }

    public function supportsChannel(string $channel): bool
    {
        return match (strtolower($channel)) {
            'whatsapp' => filled(config('services.twilio.whatsapp_from')),
            'sms' => filled(config('services.twilio.sms_from')) || filled(config('services.twilio.messaging_service_sid')),
            default => false,
        };
    }

    public function preferredFallbackChannel(): ?string
    {
        return (bool) config('services.twilio.use_sms_fallback_for_whatsapp', true) && $this->supportsChannel('sms')
            ? 'sms'
            : null;
    }

    private function buildForm(array $payload): array
    {
        $channel = strtolower((string) ($payload['channel'] ?? 'sms'));
        $to = $this->formatAddress((string) ($payload['to'] ?? ''), $channel);
        $body = trim((string) ($payload['body'] ?? ''));

        if ($to === '' || $body === '') {
            throw new RuntimeException('Twilio message payload is missing the recipient or body.');
        }

        $form = [
            'To' => $to,
            'Body' => $body,
        ];

        $messagingServiceSid = (string) config('services.twilio.messaging_service_sid', '');
        if ($messagingServiceSid !== '') {
            $form['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $from = (string) ($payload['from'] ?? $this->defaultSenderForChannel($channel));
            $formattedFrom = $this->formatAddress($from, $channel);
            if ($formattedFrom === '') {
                throw new RuntimeException(sprintf('Twilio sender is not configured for %s messages.', $channel));
            }
            $form['From'] = $formattedFrom;
        }

        $statusCallback = (string) ($payload['status_callback'] ?? $this->defaultStatusCallbackUrl());
        if ($statusCallback !== '') {
            $form['StatusCallback'] = $statusCallback;
        }

        return $form;
    }

    private function defaultSenderForChannel(string $channel): string
    {
        return match ($channel) {
            'whatsapp' => (string) config('services.twilio.whatsapp_from', ''),
            default => (string) config('services.twilio.sms_from', ''),
        };
    }

    private function defaultStatusCallbackUrl(): string
    {
        $configured = (string) config('services.twilio.status_callback_url', '');
        if ($configured !== '') {
            return $configured;
        }

        if (function_exists('route')) {
            try {
                return route('webhooks.twilio.messaging.status');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private function formatAddress(string $value, string $channel): string
    {
        $normalized = $this->normalizePhone($value);
        if ($normalized === null) {
            return '';
        }

        return $channel === 'whatsapp' ? 'whatsapp:' . $normalized : $normalized;
    }

    private function normalizePhone(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d+]/', '', $value) ?: '';
        if (str_starts_with($value, '00')) {
            $value = '+' . substr($value, 2);
        }

        if (!str_starts_with($value, '+')) {
            $value = '+' . ltrim($value, '+');
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }

    private function credentials(): array
    {
        $apiKey = (string) config('services.twilio.api_key', '');
        $apiSecret = (string) config('services.twilio.api_secret', '');
        if ($apiKey !== '' && $apiSecret !== '') {
            return [$apiKey, $apiSecret];
        }

        $accountSid = (string) config('services.twilio.account_sid', '');
        $authToken = (string) config('services.twilio.auth_token', '');
        if ($accountSid === '' || $authToken === '') {
            throw new RuntimeException('Twilio credentials are incomplete. Configure API key/secret or Account SID/Auth Token.');
        }

        return [$accountSid, $authToken];
    }

    private function extractError(?array $json, string $fallback): string
    {
        $message = Arr::get($json, 'message');
        $code = Arr::get($json, 'code');

        if ($message) {
            return $code ? sprintf('Twilio error %s: %s', $code, $message) : (string) $message;
        }

        return $fallback !== '' ? $fallback : 'Twilio request failed.';
    }
}
