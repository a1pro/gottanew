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
            && filter_var((string) env('DAILY_USE_FAKE_ROOM', true), FILTER_VALIDATE_BOOLEAN);
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
            'privacy' => 'public',
            'properties' => [
                'max_participants' => 2,
                'enable_chat' => true,
                'enable_screenshare' => true,
                'start_video_off' => false,
                'start_audio_off' => false,
                'enable_recording' => 'cloud',
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
        return $this->request('POST', '/rooms/' . rawurlencode($roomName) . '/recordings/start', array_merge([
            'type' => 'cloud',
        ], $payload));
    }

    public function stopRecording(string $roomName, array $payload = []): array
    {
        return $this->request('POST', '/rooms/' . rawurlencode($roomName) . '/recordings/stop', array_merge([
            'type' => 'cloud',
        ], $payload));
    }

    public function startTranscription(string $roomName, array $payload = []): array
    {
        $defaultPayload = array_filter([
            'language' => config('services.daily.transcription_language', 'en'),
            'model' => config('services.daily.transcription_model'),
            'punctuate' => true,
            'includeRawResponse' => true,
        ], static fn ($value) => $value !== null && $value !== '');

        return $this->request('POST', '/rooms/' . rawurlencode($roomName) . '/transcription/start', array_merge($defaultPayload, $payload));
    }

    public function stopTranscription(string $roomName, array $payload = []): array
    {
        return $this->request('POST', '/rooms/' . rawurlencode($roomName) . '/transcription/stop', $payload);
    }

    public function getRecordingAccessLink(string $recordingId, int $validForSeconds = 3600): array
    {
        return $this->request('GET', '/recordings/' . rawurlencode($recordingId) . '/access-link', [], [
            'valid_for_secs' => $validForSeconds,
        ]);
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

    private function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $apiKey = config('services.daily.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('DAILY_API_KEY is missing');
        }

        $http = Http::withToken($apiKey)->acceptJson();
        $url = rtrim(self::BASE_URL, '/') . $path;

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $query),
            'POST' => $http->post($url . (empty($query) ? '' : '?' . http_build_query($query)), $body),
            default => throw new \InvalidArgumentException('Unsupported Daily API method: ' . $method),
        };

        if (!$response->successful()) {
            throw new \RuntimeException('Daily API request failed: ' . ($response->json('info') ?: $response->body()));
        }

        return $response->json() ?? [];
    }
}
