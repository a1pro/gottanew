<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DailyRestApiService
{
    private const BASE_URL = 'https://api.daily.co/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.daily.api_key'));
    }

    public function usingFakeRoom(): bool
    {
        return app()->environment('local')
            && filter_var((string) env('DAILY_USE_FAKE_ROOM', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function createRoom(array $overrides = []): array
    {
        if ($this->usingFakeRoom()) {
            $suffix = now()->format('YmdHis');

            return [
                'id' => 'local-test-room-' . $suffix,
                'name' => 'local_test_room_' . $suffix,
                'url' => 'https://example.daily.co/local-test-room-' . $suffix,
            ];
        }

        $payload = array_replace_recursive([
            'name' => 'session_' . Str::lower((string) Str::uuid()),
            'privacy' => 'private',
            'properties' => [
                'max_participants' => 2,
                'enable_chat' => true,
                'enable_screenshare' => true,
                'start_video_off' => false,
                'start_audio_off' => false,
                'enable_recording' => 'cloud',
                'enable_transcription_storage' => (bool) config('services.daily.enable_transcription_storage', true),
            ],
        ], $overrides);

        $response = $this->request('POST', '/rooms', $payload);

        return [
            'id' => $response['id'] ?? null,
            'name' => $response['name'] ?? null,
            'url' => $response['url'] ?? null,
            'raw' => $response,
        ];
    }

    public function createMeetingToken(string $roomName, array $properties = []): ?string
    {
        if ($this->usingFakeRoom()) {
            return null;
        }

        $response = $this->request('POST', '/meeting-tokens', [
            'properties' => array_merge([
                'room_name' => $roomName,
            ], $properties),
        ]);

        return $response['token'] ?? null;
    }

    public function startRecording(string $roomName, array $payload = []): array
    {
        return $this->request(
            'POST',
            '/rooms/' . rawurlencode($roomName) . '/recordings/start',
            array_merge([
                'type' => 'cloud',
            ], $payload)
        );
    }

    public function stopRecording(string $roomName, array $payload = []): array
    {
        return $this->request(
            'POST',
            '/rooms/' . rawurlencode($roomName) . '/recordings/stop',
            array_merge([
                'type' => 'cloud',
            ], $payload)
        );
    }

    public function startTranscription(string $roomName, array $payload = []): array
    {
        $defaultPayload = array_filter([
            'language' => config('services.daily.transcription_language', 'en'),
            'model' => config('services.daily.transcription_model'),
            'punctuate' => true,
            'includeRawResponse' => true,
        ], static fn ($value) => $value !== null && $value !== '');

        return $this->request(
            'POST',
            '/rooms/' . rawurlencode($roomName) . '/transcription/start',
            array_merge($defaultPayload, $payload)
        );
    }

    public function stopTranscription(string $roomName, array $payload = []): array
    {
        return $this->request(
            'POST',
            '/rooms/' . rawurlencode($roomName) . '/transcription/stop',
            empty($payload) ? new \stdClass() : $payload
        );
    }

    public function getRoom(string $roomName): array
    {
        return $this->request('GET', '/rooms/' . rawurlencode($roomName));
    }

    public function listWebhooks(): array
    {
        $response = $this->request('GET', '/webhooks');

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        if (isset($response['webhooks']) && is_array($response['webhooks'])) {
            return $response['webhooks'];
        }

        return array_values(array_filter($response, static fn ($item) => is_array($item)));
    }

    public function getWebhook(string $uuid): array
    {
        return $this->request('GET', '/webhooks/' . rawurlencode($uuid));
    }

    public function createWebhook(string $url, array $eventTypes, ?string $hmac = null, ?string $retryType = null): array
    {
        $url = trim((string) $url);

        if ($url === '') {
            throw new \InvalidArgumentException('Webhook URL is required.');
        }

        $retryType = trim((string) ($retryType ?: config('services.daily.webhook_retry_type', 'circuit-breaker')));

        $payload = array_filter([
            'url' => $url,
            'eventTypes' => array_values(array_unique(array_filter(
                $eventTypes,
                static fn ($value) => is_string($value) && trim($value) !== ''
            ))),
            'retryType' => $retryType !== '' ? $retryType : null,
            'hmac' => is_string($hmac) && trim($hmac) !== '' ? trim($hmac) : null,
        ], static fn ($value) => $value !== null);

        return $this->request('POST', '/webhooks', $payload);
    }

    public function updateWebhook(string $uuid, string $url, array $eventTypes, ?string $hmac = null, ?string $retryType = null): array
    {
        $uuid = trim((string) $uuid);
        $url = trim((string) $url);

        if ($uuid === '') {
            throw new \InvalidArgumentException('Webhook UUID is required.');
        }

        if ($url === '') {
            throw new \InvalidArgumentException('Webhook URL is required.');
        }

        $retryType = trim((string) ($retryType ?: config('services.daily.webhook_retry_type', 'circuit-breaker')));

        $payload = array_filter([
            'url' => $url,
            'eventTypes' => array_values(array_unique(array_filter(
                $eventTypes,
                static fn ($value) => is_string($value) && trim($value) !== ''
            ))),
            'retryType' => $retryType !== '' ? $retryType : null,
            'hmac' => is_string($hmac) && trim($hmac) !== '' ? trim($hmac) : null,
        ], static fn ($value) => $value !== null);

        return $this->request('POST', '/webhooks/' . rawurlencode($uuid), $payload);
    }

    public function getRecordingAccessLink(string $recordingId, int $validForSeconds = 3600): array
    {
        return $this->request(
            'GET',
            '/recordings/' . rawurlencode($recordingId) . '/access-link',
            [],
            [
                'valid_for_secs' => $validForSeconds,
            ]
        );
    }

    public function getTranscriptAccessLink(string $transcriptId): array
    {
        return $this->request('GET', '/transcript/' . rawurlencode($transcriptId) . '/access-link');
    }

    public function getTranscript(string $transcriptId): array
    {
        return $this->request('GET', '/transcript/' . rawurlencode($transcriptId));
    }

    public function fetchTextFromAccessLink(?string $link): ?string
    {
        if (!$link || !filter_var($link, FILTER_VALIDATE_URL)) {
            return null;
        }

        $response = Http::accept('text/vtt, text/plain, */*')->get($link);

        if (!$response->successful()) {
            return null;
        }

        return trim($this->convertVttToPlainText($response->body()));
    }

    public function convertVttToPlainText(string $raw): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $output = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line === 'WEBVTT') {
                continue;
            }

            if (str_contains($line, '-->')) {
                continue;
            }

            if (preg_match('/^NOTE\b/i', $line)) {
                continue;
            }

            if (preg_match('/^\d+$/', $line)) {
                continue;
            }

            $line = preg_replace('/<\/?[^>]+>/', '', $line) ?? $line;
            $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5);

            if ($line === '') {
                continue;
            }

            if (end($output) !== $line) {
                $output[] = $line;
            }
        }

        return implode("\n", $output);
    }

    public function resolveTranscriptText(?string $transcriptId, ?string $fallbackLink = null): ?string
    {
        $transcriptId = is_string($transcriptId) ? trim($transcriptId) : '';
        $link = is_string($fallbackLink) ? trim($fallbackLink) : '';

        if ($transcriptId !== '') {
            try {
                $accessLinkResponse = $this->getTranscriptAccessLink($transcriptId);
                $link = $accessLinkResponse['link'] ?? $accessLinkResponse['download_link'] ?? $link;
            } catch (\Throwable) {
                // fall back to existing link
            }
        }

        $text = $this->fetchTextFromAccessLink($link !== '' ? $link : null);

        if ($text) {
            return $text;
        }

        if ($transcriptId === '') {
            return null;
        }

        try {
            $transcript = $this->getTranscript($transcriptId);
        } catch (\Throwable) {
            return null;
        }

        foreach ([
            $transcript['text'] ?? null,
            $transcript['transcript'] ?? null,
            data_get($transcript, 'result.text'),
            data_get($transcript, 'data.text'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public function resolveRecordingDownloadLink(?string $recordingId, int $validForSeconds = 3600): ?string
    {
        $recordingId = is_string($recordingId) ? trim($recordingId) : '';

        if ($recordingId === '') {
            return null;
        }

        try {
            $accessLinkResponse = $this->getRecordingAccessLink($recordingId, $validForSeconds);
        } catch (\Throwable) {
            return null;
        }

        $link = $accessLinkResponse['download_link'] ?? $accessLinkResponse['link'] ?? null;

        return is_string($link) && trim($link) !== '' ? trim($link) : null;
    }

    private function request(string $method, string $path, array|\stdClass $body = [], array $query = []): array
    {
        $apiKey = config('services.daily.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('DAILY_API_KEY is missing');
        }

        $http = Http::withToken($apiKey)->acceptJson();
        $url = rtrim(self::BASE_URL, '/') . $path;

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $query),
            'POST' => $http->send('POST', $url . (empty($query) ? '' : '?' . http_build_query($query)), [
                'json' => $body,
            ]),
            default => throw new \InvalidArgumentException('Unsupported Daily API method: ' . $method),
        };

        if (!$response->successful()) {
            throw new \RuntimeException('Daily API request failed: ' . ($response->json('info') ?: $response->body()));
        }

        return $response->json() ?? [];
    }
}