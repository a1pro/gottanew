<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Services\Ai\SessionInsightService;
use App\Services\Video\DailyRestApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DailyWebhookController extends Controller
{
    public function __construct(
        private DailyRestApiService $dailyService,
        private SessionInsightService $sessionInsightService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $event = $request->all();
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

        $recording = $this->ensureRecording($session);

        try {
            match ($eventType) {
                'transcript.started' => $this->handleTranscriptStarted($recording, $payload),
                'transcript.ready-to-download' => $this->handleTranscriptReady($session, $recording, $payload),
                'transcript.error' => $this->handleTranscriptError($recording, $payload),
                'recording.started' => $this->handleRecordingStarted($recording, $payload),
                'recording.ready-to-download' => $this->handleRecordingReady($recording, $payload),
                'recording.error' => $this->handleRecordingError($recording, $payload),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Daily webhook processing failed', [
                'type' => $eventType,
                'session_id' => $session->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['ok' => true]);
    }

    private function handleTranscriptStarted(SessionRecording $recording, array $payload): void
    {
        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $payload['id'] ?? $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $payload['instanceId'] ?? $recording->daily_transcript_instance_id,
            'transcription_status' => 'active',
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'transcript' => [
                        'status' => 'active',
                        'started_at' => now()->toISOString(),
                        'event_payload' => Arr::only($payload, [
                            'id',
                            'instanceId',
                            'room_id',
                            'room_name',
                            'mtg_session_id',
                            'duration',
                        ]),
                    ],
                ],
            ]),
        ]);
    }

    private function handleTranscriptReady(CoachingSession $session, SessionRecording $recording, array $payload): void
    {
        $transcriptId = (string) ($payload['id'] ?? $recording->daily_transcript_id ?? '');
        $accessLinkResponse = $transcriptId !== '' ? $this->dailyService->getTranscriptAccessLink($transcriptId) : [];
        $accessLink = $accessLinkResponse['link'] ?? $accessLinkResponse['download_link'] ?? null;
        $transcriptText = $this->dailyService->fetchTextFromAccessLink(is_string($accessLink) ? $accessLink : null);

        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $transcriptId !== '' ? $transcriptId : $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $payload['instanceId'] ?? $recording->daily_transcript_instance_id,
            'transcript' => $transcriptText ?: $recording->transcript,
            'transcription_status' => 'completed',
            'duration_seconds' => $payload['duration'] ?? $recording->duration_seconds,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'transcript' => [
                        'status' => 'completed',
                        'completed_at' => now()->toISOString(),
                        'access_link' => $accessLink,
                        'out_params' => $payload['out_params'] ?? null,
                        'event_payload' => Arr::only($payload, [
                            'id',
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

        if ($transcriptText) {
            $this->sessionInsightService->generatePostSummary($session->fresh(['client', 'coach', 'recording']), true);
        }
    }

    private function handleTranscriptError(SessionRecording $recording, array $payload): void
    {
        $recording->update([
            'provider_name' => 'daily',
            'daily_transcript_id' => $payload['id'] ?? $recording->daily_transcript_id,
            'daily_transcript_instance_id' => $payload['instanceId'] ?? $recording->daily_transcript_instance_id,
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
        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $payload['recording_id'] ?? $recording->daily_recording_id,
            'daily_recording_instance_id' => $payload['instanceId'] ?? $recording->daily_recording_instance_id,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'recording' => [
                        'status' => 'active',
                        'started_at' => now()->toISOString(),
                        'event_payload' => $payload,
                    ],
                ],
            ]),
        ]);
    }

    private function handleRecordingReady(SessionRecording $recording, array $payload): void
    {
        $recordingId = (string) ($payload['recording_id'] ?? $recording->daily_recording_id ?? '');
        $accessLinkResponse = $recordingId !== '' ? $this->dailyService->getRecordingAccessLink($recordingId) : [];
        $downloadLink = $accessLinkResponse['download_link'] ?? $accessLinkResponse['link'] ?? null;

        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $recording->daily_recording_id,
            'daily_recording_instance_id' => $payload['instanceId'] ?? $recording->daily_recording_instance_id,
            'recording_url' => is_string($downloadLink) ? $downloadLink : $recording->recording_url,
            'duration_seconds' => $payload['duration'] ?? $recording->duration_seconds,
            'provider_metadata' => $this->mergeProviderMetadata($recording, [
                'daily' => [
                    'recording' => [
                        'status' => 'completed',
                        'completed_at' => now()->toISOString(),
                        'access_link' => $downloadLink,
                        'access_link_expires' => $accessLinkResponse['expires'] ?? null,
                        'event_payload' => $payload,
                    ],
                ],
            ]),
        ]);
    }

    private function handleRecordingError(SessionRecording $recording, array $payload): void
    {
        $recording->update([
            'provider_name' => 'daily',
            'daily_recording_id' => $payload['recording_id'] ?? $recording->daily_recording_id,
            'daily_recording_instance_id' => $payload['instanceId'] ?? $recording->daily_recording_instance_id,
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

    private function ensureRecording(CoachingSession $session): SessionRecording
    {
        return SessionRecording::firstOrCreate(
            ['session_id' => $session->id],
            [
                'provider_name' => 'daily',
                'transcription_status' => 'inactive',
                'privacy_settings' => [
                    'recording_enabled' => false,
                    'transcription_consent' => 'none',
                ],
            ]
        );
    }

    private function extractPayload(array $event): array
    {
        $payload = $event['payload'] ?? [];

        if (is_array($payload) && isset($payload['payload']) && is_array($payload['payload'])) {
            return $payload['payload'];
        }

        return is_array($payload) ? $payload : [];
    }

    private function mergeProviderMetadata(SessionRecording $recording, array $extra): array
    {
        $current = $recording->provider_metadata ?? [];

        return array_replace_recursive(is_array($current) ? $current : [], $extra);
    }
}
