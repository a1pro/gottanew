<?php

namespace App\Services\Video;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DailyWebhookValidator
{
    public function isValid(Request $request): bool
    {
        if ($this->shouldBypassValidation()) {
            Log::info('Daily webhook signature validation bypassed', [
                'app_env' => app()->environment(),
                'url' => $request->fullUrl(),
                'has_signature' => filled($request->header('X-Webhook-Signature')),
                'has_timestamp' => filled($request->header('X-Webhook-Timestamp')),
            ]);

            return true;
        }

        $configuredSecret = trim((string) config('services.daily.webhook_hmac', ''));

        if ($configuredSecret === '') {
            Log::warning('Daily webhook HMAC not configured; accepting request without signature validation.', [
                'app_env' => app()->environment(),
                'url' => $request->fullUrl(),
            ]);

            return true;
        }

        $signature = trim((string) $request->header('X-Webhook-Signature', ''));
        $timestamp = trim((string) $request->header('X-Webhook-Timestamp', ''));

        if ($signature === '' || $timestamp === '') {
            return $this->looksLikeVerificationRequest($request);
        }

        $maxAge = max(0, (int) config('services.daily.webhook_max_age_seconds', 300));
        if ($maxAge > 0) {
            if (!ctype_digit($timestamp)) {
                Log::warning('Daily webhook rejected: timestamp is not numeric.', [
                    'timestamp' => $timestamp,
                ]);

                return false;
            }

            $timestampInt = (int) $timestamp;

            // Daily sends timestamp in milliseconds in live requests.
            if (strlen($timestamp) >= 13) {
                $timestampInt = (int) floor($timestampInt / 1000);
            }

            if (abs(time() - $timestampInt) > $maxAge) {
                Log::warning('Daily webhook rejected due to timestamp age mismatch.', [
                    'received_timestamp_raw' => $timestamp,
                    'normalized_timestamp' => $timestampInt,
                    'server_time' => time(),
                    'max_age' => $maxAge,
                ]);

                return false;
            }
        }

        $rawBody = (string) $request->getContent();
        if ($rawBody === '') {
            return false;
        }

        foreach ($this->candidateSecrets($configuredSecret) as $secret) {
            foreach ($this->candidatePayloads($timestamp, $rawBody) as $payload) {
                foreach ($this->candidateDigests($secret, $payload) as $candidate) {
                    if (hash_equals($candidate, $signature)) {
                        return true;
                    }
                }
            }
        }

        Log::warning('Daily webhook signature mismatch after trying all candidates.', [
            'has_signature' => $signature !== '',
            'timestamp' => $timestamp,
        ]);

        return false;
    }

    private function shouldBypassValidation(): bool
    {
        return app()->environment('local')
            && filter_var((string) env('DAILY_SKIP_WEBHOOK_SIGNATURE_VALIDATION', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function looksLikeVerificationRequest(Request $request): bool
    {
        $payload = $request->json()->all();
        $testValue = $payload['test'] ?? $payload['Test'] ?? null;

        if (is_bool($testValue)) {
            return $testValue;
        }

        if (is_string($testValue)) {
            $normalized = strtolower(trim($testValue));

            return in_array($normalized, ['1', 'true', 'yes', 'ok', 'test'], true);
        }

        return false;
    }

    private function candidatePayloads(string $timestamp, string $rawBody): array
    {
        return array_values(array_unique([
            $rawBody,
            $timestamp . '.' . $rawBody,
            $timestamp . $rawBody,
            $rawBody . '.' . $timestamp,
            $rawBody . $timestamp,
        ]));
    }

    private function candidateSecrets(string $configuredSecret): array
    {
        $secrets = [trim($configuredSecret)];
        $decoded = base64_decode($configuredSecret, true);

        if ($decoded !== false && $decoded !== '') {
            $secrets[] = $decoded;
        }

        return array_values(array_unique(array_filter(
            $secrets,
            static fn ($value) => is_string($value) && $value !== ''
        )));
    }

    private function candidateDigests(string $secret, string $payload): array
    {
        $hex = hash_hmac('sha256', $payload, $secret);
        $base64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return [
            $hex,
            'sha256=' . $hex,
            strtoupper($hex),
            $base64,
            'sha256=' . $base64,
        ];
    }
}