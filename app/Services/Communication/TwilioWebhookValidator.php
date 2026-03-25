<?php

namespace App\Services\Communication;

use Illuminate\Http\Request;

class TwilioWebhookValidator
{
    public function isValid(Request $request, ?string $expectedUrl = null): bool
    {
        $authToken = (string) config('services.twilio.auth_token', '');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        if ($authToken === '' || $signature === '') {
            return false;
        }

        foreach ($this->candidateUrls($request, $expectedUrl) as $url) {
            if (hash_equals($this->signatureFor($url, $request->all(), $authToken), $signature)) {
                return true;
            }
        }

        return false;
    }

    public function signatureFor(string $url, array $params, string $authToken): string
    {
        ksort($params);

        $payload = $url;
        foreach ($params as $key => $value) {
            $payload .= $key;

            if (is_array($value)) {
                $values = array_map(static fn ($item) => (string) $item, $value);
                sort($values, SORT_STRING);
                foreach ($values as $item) {
                    $payload .= $item;
                }
            } else {
                $payload .= (string) $value;
            }
        }

        return base64_encode(hash_hmac('sha1', $payload, $authToken, true));
    }

    private function candidateUrls(Request $request, ?string $expectedUrl = null): array
    {
        $candidates = [];

        if ($expectedUrl) {
            $candidates[] = $expectedUrl;
        }

        $fullUrl = $request->fullUrl();
        if ($fullUrl !== '') {
            $candidates[] = $fullUrl;
            if (str_starts_with($fullUrl, 'http://')) {
                $candidates[] = 'https://' . substr($fullUrl, 7);
            }
        }

        $configuredBase = rtrim((string) config('app.url', ''), '/');
        if ($configuredBase !== '') {
            $candidates[] = $configuredBase . '/' . ltrim($request->path(), '/');
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
