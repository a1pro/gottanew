<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\SyncDailyTranscriptJob;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Models\Webhook\WebhookEventReceipt;
use App\Services\Video\DailyRestApiService;
use App\Services\Video\DailyWebhookValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DailyWebhookController extends Controller
{
    public function __construct(
        private DailyRestApiService $dailyService,
        private DailyWebhookValidator $webhookValidator,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $event = $request->all();

        Log::info('RAW DAILY REQUEST', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
            'parsed_body' => $event,
        ]);

        Log::info('Daily webhook entered controller', [
                'headers' => [
                    'signature' => $request->header('X-Webhook-Signature'),
                    'timestamp' => $request->header('X-Webhook-Timestamp'),
                ],
                'body' => $event,
            ]);

        if ($this->isVerificationRequest($request, $event)) {
            Log::info('Accepted Daily webhook verification request.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_type' => $request->header('Content-Type'),
                'raw_body' => $request->getContent(),
                'has_signature' => filled($request->header('X-Webhook-Signature')),
                'has_timestamp' => filled($request->header('X-Webhook-Timestamp')),
            ]);

            return response()->json(['ok' => true, 'verification' => true]);
        }

        if (!$this->webhookValidator->isValid($request)) {
            Log::warning('Rejected Daily webhook because signature validation failed.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_type' => $request->header('Content-Type'),
                'raw_body' => $request->getContent(),
                'has_signature' => filled($request->header('X-Webhook-Signature')),
                'has_timestamp' => filled($request->header('X-Webhook-Timestamp')),
            ]);

            return response()->json(['message' => 'Invalid Daily signature.'], 403);
        }

        $eventType = (string) ($event['type'] ?? data_get($event, 'payload.type') ?? 'unknown');
        $payload = $this->extractPayload($event);
        $roomName = (string) ($payload['room_name'] ?? data_get($event, 'room_name') ?? '');

        if ($roomName === '') {
            Log::warning('Daily webhook ignored: missing room name', [
                'type' => $eventType,
                'event' => $event,
            ]);

            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'missing_room_name']);
        }

        $providerEventId = trim((string) ($event['id'] ?? ''));
        if ($providerEventId === '') {
            $providerEventId = sha1((string) $request->getContent());
        }

        try {
            $receipt = WebhookEventReceipt::firstOrCreate(
                [
                    'provider_name' => 'daily',
                    'provider_event_id' => $providerEventId,
                ],
                [
                    'event_type' => $eventType,
                    'room_name' => $roomName,
                    'payload' => $event,
                    'received_at' => now(),
                ]
            );
        } catch (\Throwable) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if (!$receipt->wasRecentlyCreated) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $session = CoachingSession::query()
            ->with(['videoDetail', 'recording', 'client', 'coach'])
            ->whereHas('videoDetail', fn ($query) => $query->where('daily_room_name', $roomName))
            ->latest('id')
            ->first();

        if (!$session) {
            Log::warning('Daily webhook ignored: no session found for room', [
                'type' => $eventType,
                'room_name' => $roomName,
            ]);

            return response()->json(['ok' => true, 'ignored' => true, 'reason' => 'session_not_found']);
        }

        $receipt->update(['session_id' => $session->id]);

        $recording = $this->ensureRecording($session);

        try {

            Log::info('DAILY EVENT TYPE RECEIVED', [
                'event_type' => $eventType,
                'payload' => $payload,
            ]);

            match ($eventType) {
                'transcript.started' => $this->handleTranscriptStarted($recording, $payload),
                'transcript.ready-to-download' => $this->handleTranscriptReady($session, $recording, $payload),
                'transcript.error' => $this->handleTranscriptError($recording, $payload),
                'recording.started' => $this->handleRecordingStarted($recording, $payload),
                'recording.ready-to-download' => $this->handleRecordingReady($recording, $payload),
                'recording.error' => $this->handleRecordingError($recording, $payload),
                default => null,
            };

            $receipt->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Daily webhook processing failed', [
                'type' => $eventType,
                'session_id' => $session->id,
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            $receipt->update([
                'processing_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'processed' => false,
            ], 200);
        }

        return response()->json(['ok' => true]);
    }

    private function isVerificationRequest(Request $request, array $event): bool
    {
        $testValue = $event['test'] ?? $event['Test'] ?? null;

        if (is_bool($testValue)) {
            return $testValue;
        }

        if (is_string($testValue)) {
            $normalized = strtolower(trim($testValue));

            if (in_array($normalized, ['1', 'true', 'yes', 'ok', 'test'], true)) {
                return true;
            }
        }

        $eventType = (string) ($event['type'] ?? data_get($event, 'payload.type') ?? '');
        $roomName = (string) ($event['room_name'] ?? data_get($event, 'payload.room_name') ?? '');

        if ($eventType === '' && $roomName === '') {
            return true;
        }

        $rawBody = trim((string) $request->getContent());

        if ($rawBody === '' || strtolower($rawBody) === 'test') {
            return true;
        }

        return false;
    }

    private function handleTranscriptStarted(SessionRecording $recording, array $payload): void
    {
        $transcriptId = $this->extractTranscriptId($payload);
        $transcriptInstanceId = $this->extractInstanceId($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $transcriptId ?: $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $transcriptInstanceId ?: $recording->daily_transcript_instance_id,
            'transcription_status' => 'active',
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'transcript' => [
                        'status' => 'active',
                        'started_at' => now()->toISOString(),
                        'event_payload' => Arr::only($payload, [
                            'id',
                            'instance_id',
                            'instanceId',
                            'room_id',
                            'room_name',
                            'mtg_session_id',
                            'duration',
                            'status',
                        ]),
                    ],
                ],
            ]),
        ]);
    }

    private function handleTranscriptReady(CoachingSession $session, SessionRecording $recording, array $payload): void
    {
        Log::info('TRANSCRIPT READY RAW PAYLOAD', [
            'payload' => $payload,
        ]);
    
        Log::info('DAILY REAL TRANSCRIPT READY PAYLOAD', [
            'payload' => $payload,
        ]);
        
        $transcriptId = $this->extractTranscriptId($payload);
        $transcriptInstanceId = $this->extractInstanceId($payload);

        $transcriptLink = $this->extractTranscriptAccessLink($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $transcriptId ?: $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $transcriptInstanceId ?: $recording->daily_transcript_instance_id,
            'transcription_status' => 'completed',
            'duration_seconds' => $payload['duration'] ?? $recording->duration_seconds,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'transcript' => [
                        'status' => 'ready_to_download',
                        'ready_at' => now()->toISOString(),
                        'access_link' => $transcriptLink,
                        'download_link' => $transcriptLink,
                        'out_params' => $payload['out_params'] ?? null,
                        'event_payload' => Arr::only($payload, [
                            'id',
                            'instance_id',
                            'instanceId',
                            'room_id',
                            'room_name',
                            'mtg_session_id',
                            'duration',
                            'participant_minutes',
                            'status',
                        ]),
                    ],
                ],
            ]),
        ]);

        if ((string) config('queue.default') === 'sync') {
            SyncDailyTranscriptJob::dispatchSync($session->id);
        } else {
            SyncDailyTranscriptJob::dispatch($session->id);
        }
    }

    private function handleTranscriptError(SessionRecording $recording, array $payload): void
    {
        $transcriptId = $this->extractTranscriptId($payload);
        $transcriptInstanceId = $this->extractInstanceId($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $transcriptId ?: $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $transcriptInstanceId ?: $recording->daily_transcript_instance_id,
            'transcription_status' => 'inactive',
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'transcript' => [
                        'status' => 'error',
                        'error_at' => now()->toISOString(),
                        'event_payload' => $payload,
                    ],
                ],
            ]),
        ]);
    }

    private function handleRecordingStarted(SessionRecording $recording, array $payload): void
    {
        $recordingId = (string) ($payload['recording_id'] ?? $payload['recordingId'] ?? '');
        $recordingInstanceId = $this->extractInstanceId($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $recording->daily_recording_id,
            'daily_recording_instance_id' => $recordingInstanceId ?: $recording->daily_recording_instance_id,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'recording' => [
                        'status' => 'active',
                        'started_at' => now()->toISOString(),
                        'event_payload' => Arr::only($payload, [
                            'recording_id',
                            'recordingId',
                            'instance_id',
                            'instanceId',
                            'room_name',
                            'duration',
                            'status',
                            'action',
                        ]),
                    ],
                ],
            ]),
        ]);
    }

    private function handleRecordingReady(SessionRecording $recording, array $payload): void
    {
        $recordingId = (string) ($payload['recording_id'] ?? $payload['recordingId'] ?? $recording->daily_recording_id ?? '');
        $recordingInstanceId = $this->extractInstanceId($payload);

        try {
            $accessLinkResponse = $recordingId !== '' ? $this->dailyService->getRecordingAccessLink($recordingId) : [];
        } catch (\Throwable) {
            $accessLinkResponse = [];
        }

        $downloadLink = $accessLinkResponse['download_link']
            ?? $accessLinkResponse['link']
            ?? $this->extractRecordingAccessLink($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $recording->daily_recording_id,
            'daily_recording_instance_id' => $recordingInstanceId ?: $recording->daily_recording_instance_id,
            'recording_url' => is_string($downloadLink) ? $downloadLink : $recording->recording_url,
            'duration_seconds' => $payload['duration'] ?? $recording->duration_seconds,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'recording' => [
                        'status' => 'completed',
                        'completed_at' => now()->toISOString(),
                        'access_link' => $downloadLink,
                        'access_link_expires' => $accessLinkResponse['expires'] ?? null,
                        'event_payload' => Arr::only($payload, [
                            'recording_id',
                            'recordingId',
                            'instance_id',
                            'instanceId',
                            'room_name',
                            'duration',
                            'status',
                            'type',
                            's3_key',
                        ]),
                    ],
                ],
            ]),
        ]);
    }

    private function handleRecordingError(SessionRecording $recording, array $payload): void
    {
        $recordingId = (string) ($payload['recording_id'] ?? $payload['recordingId'] ?? '');
        $recordingInstanceId = $this->extractInstanceId($payload);

        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $recording->daily_recording_id,
            'daily_recording_instance_id' => $recordingInstanceId ?: $recording->daily_recording_instance_id,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'recording' => [
                        'status' => 'error',
                        'error_at' => now()->toISOString(),
                        'event_payload' => $payload,
                    ],
                ],
            ]),
        ]);
    }

    private function extractPayload(array $event): array
{
    $payload = $event['payload'] ?? null;

    if (!is_array($payload)) {
        return $event;
    }

    foreach ([
        'id',
        'type',
        'room_name',
        'room_id',
        'instance_id',
        'instanceId',
        'mtg_session_id',
        'download_url',
        'download_link',
        'access_link',
        'link',
        'duration',
        'out_params'
    ] as $key) {
        if (!array_key_exists($key, $payload) && array_key_exists($key, $event)) {
            $payload[$key] = $event[$key];
        }
    }

    return $payload;
}

    private function ensureRecording(CoachingSession $session): SessionRecording
    {
        if ($session->recording) {
            return $session->recording;
        }

        return $session->recording()->create([
            'provider_name' => 'daily',
            'transcription_status' => 'inactive',
        ]);
    }

    private function mergeProviderMetadata(SessionRecording $recording, array $patch): array
    {
        $existing = $recording->provider_metadata;

        if (!is_array($existing)) {
            $existing = [];
        }

        return array_replace_recursive($existing, $patch);
    }

    private function extractInstanceId(array $payload): ?string
{
    foreach ([
        $payload['instance_id'] ?? null,
        $payload['instanceId'] ?? null,
        data_get($payload, 'transcript.instance_id'),
        data_get($payload, 'transcript.instanceId'),
        data_get($payload, 'recording.instance_id'),
        data_get($payload, 'recording.instanceId'),
        data_get($payload, 'info.instance_id'),
        data_get($payload, 'info.instanceId'),
    ] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

private function extractTranscriptId(array $payload): ?string
{
    foreach ([
        $payload['id'] ?? null,
        $payload['transcript_id'] ?? null,
        $payload['transcriptId'] ?? null,
        data_get($payload, 'transcript.id'),
        data_get($payload, 'transcript.transcript_id'),
        data_get($payload, 'transcript.transcriptId'),
        data_get($payload, 'data.id'),
        data_get($payload, 'info.id'),
    ] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

private function extractTranscriptAccessLink(array $payload): ?string
{
    foreach ([
        $payload['access_link'] ?? null,
        $payload['download_link'] ?? null,
        $payload['download_url'] ?? null,
        $payload['link'] ?? null,
        $payload['url'] ?? null,
        data_get($payload, 'out_params.access_link'),
        data_get($payload, 'out_params.download_link'),
        data_get($payload, 'out_params.download_url'),
        data_get($payload, 'out_params.link'),
        data_get($payload, 'transcript.access_link'),
        data_get($payload, 'transcript.download_link'),
        data_get($payload, 'transcript.download_url'),
        data_get($payload, 'transcript.link'),
        data_get($payload, 'download.link'),
    ] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

private function extractRecordingAccessLink(array $payload): ?string
{
    foreach ([
        $payload['access_link'] ?? null,
        $payload['download_link'] ?? null,
        $payload['link'] ?? null,
        $payload['url'] ?? null,
        data_get($payload, 'recording.access_link'),
        data_get($payload, 'recording.download_link'),
        data_get($payload, 'recording.link'),
        data_get($payload, 'out_params.access_link'),
        data_get($payload, 'out_params.download_link'),
        data_get($payload, 'out_params.link'),
    ] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}
}