<?php

namespace App\Jobs;

use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Services\Ai\SessionInsightService;
use App\Services\Video\DailyRestApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDailyTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $sessionId,
        public bool $generateAiSummary = true
    ) {
        $this->onQueue('default');
    }

    public function handle(DailyRestApiService $dailyService, SessionInsightService $sessionInsightService): void
    {
        $session = CoachingSession::query()
            ->with(['videoDetail', 'recording', 'client', 'coach'])
            ->find($this->sessionId);

        if (!$session) {
            return;
        }

        /** @var SessionRecording $recording */
        $recording = $session->recording ?: SessionRecording::firstOrCreate(
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

        if (is_string($recording->transcript) && trim($recording->transcript) !== '') {
            return;
        }

        $transcriptId = is_string($recording->daily_transcript_id)
            ? trim($recording->daily_transcript_id)
            : '';

        if ($transcriptId === '') {
            return;
        }

        $lockKey = 'daily:transcript-sync:' . $recording->id;
        $lock = Cache::lock($lockKey, 90);

        if (!$lock->get()) {
            return;
        }

        try {
            $dailyMetadata = is_array(data_get($recording->provider_metadata, 'daily'))
                ? data_get($recording->provider_metadata, 'daily')
                : [];
            $transcriptMetadata = is_array($dailyMetadata['transcript'] ?? null)
                ? $dailyMetadata['transcript']
                : [];

            $fallbackLink = Arr::first([
                $transcriptMetadata['access_link'] ?? null,
                $transcriptMetadata['download_link'] ?? null,
            ], static fn ($value) => is_string($value) && trim($value) !== '');

            $text = $dailyService->resolveTranscriptText(
                $transcriptId,
                is_string($fallbackLink) ? $fallbackLink : null
            );

            if (!is_string($text) || trim($text) === '') {
                $recording->update([
                    'provider_metadata' => array_replace_recursive(
                        is_array($recording->provider_metadata) ? $recording->provider_metadata : [],
                        [
                            'daily' => [
                                'transcript' => [
                                    'last_sync_attempt_at' => now()->toISOString(),
                                    'last_sync_attempt_status' => 'no_text_available',
                                ],
                            ],
                        ]
                    ),
                ]);
                return;
            }

            $text = trim($text);

            $recording->update([
                'provider_name' => 'daily',
                'transcript' => $text,
                'transcription_status' => 'completed',
                'provider_metadata' => array_replace_recursive(
                    is_array($recording->provider_metadata) ? $recording->provider_metadata : [],
                    [
                        'daily' => [
                            'transcript' => [
                                'status' => 'completed',
                                'synced_at' => now()->toISOString(),
                                'last_sync_attempt_at' => now()->toISOString(),
                                'last_sync_attempt_status' => 'ok',
                            ],
                        ],
                    ]
                ),
            ]);

            if ($this->generateAiSummary) {
                try {
                    $sessionInsightService->generatePostSessionSummary(
                        $session->fresh(['client', 'coach', 'recording']),
                        true
                    );
                } catch (\Throwable $e) {
                    Log::info('Post-session AI summary generation failed after transcript sync', [
                        'session_id' => $session->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Daily transcript sync job failed', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
            ]);

            $recording->update([
                'provider_metadata' => array_replace_recursive(
                    is_array($recording->provider_metadata) ? $recording->provider_metadata : [],
                    [
                        'daily' => [
                            'transcript' => [
                                'last_sync_attempt_at' => now()->toISOString(),
                                'last_sync_attempt_status' => 'error',
                                'last_sync_attempt_error' => $e->getMessage(),
                            ],
                        ],
                    ]
                ),
            ]);

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}
