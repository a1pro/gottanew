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
            Log::warning('SyncDailyTranscriptJob: session not found', ['session_id' => $this->sessionId]);
            return;
        }
        
        Log::info('SyncDailyTranscriptJob: starting transcript sync', ['session_id' => $this->sessionId]);

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
            Log::info('SyncDailyTranscriptJob: transcript already stored', ['session_id' => $this->sessionId]);
            return;
        }

        $transcriptId = is_string($recording->daily_transcript_id)
            ? trim($recording->daily_transcript_id)
            : '';

        Log::debug('SyncDailyTranscriptJob: transcript details', [
            'session_id' => $this->sessionId,
            'recording_id' => $recording->id,
            'transcript_id' => $transcriptId ?: '(empty)',
        ]);

        $lockKey = 'daily:transcript-sync:' . $recording->id;
        $lock = Cache::lock($lockKey, 90);

        if (!$lock->get()) {
            Log::warning('SyncDailyTranscriptJob: could not acquire lock', ['recording_id' => $recording->id]);
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
                $transcriptMetadata['link'] ?? null,
                data_get($transcriptMetadata, 'out_params.access_link'),
                data_get($transcriptMetadata, 'out_params.download_link'),
                data_get($transcriptMetadata, 'out_params.link'),
            ], static fn ($value) => is_string($value) && trim($value) !== '');

            if ($transcriptId === '' && !is_string($fallbackLink)) {
                Log::error('SyncDailyTranscriptJob: missing transcript identifier and access link', [
                    'session_id' => $this->sessionId,
                    'recording_id' => $recording->id,
                ]);
                
                $recording->update([
                    'provider_metadata' => array_replace_recursive(
                        is_array($recording->provider_metadata) ? $recording->provider_metadata : [],
                        [
                            'daily' => [
                                'transcript' => [
                                    'last_sync_attempt_at' => now()->toISOString(),
                                    'last_sync_attempt_status' => 'missing_identifier_and_link',
                                ],
                            ],
                        ]
                    ),
                ]);
                return;
            }
            
            Log::info('SyncDailyTranscriptJob: fetching transcript text', [
                'session_id' => $this->sessionId,
                'transcript_id' => $transcriptId ?: '(using fallback link)',
                'has_fallback_link' => !empty($fallbackLink),
            ]);

            $text = $dailyService->resolveTranscriptText(
                $transcriptId !== '' ? $transcriptId : null,
                is_string($fallbackLink) ? $fallbackLink : null
            );

            if (!is_string($text) || trim($text) === '') {
                Log::error('SyncDailyTranscriptJob: failed to fetch transcript text', [
                    'session_id' => $this->sessionId,
                    'recording_id' => $recording->id,
                    'transcript_id' => $transcriptId,
                ]);
                
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
            $textLength = strlen($text);
            
            Log::info('SyncDailyTranscriptJob: transcript text fetched successfully', [
                'session_id' => $this->sessionId,
                'text_length' => $textLength,
                'word_count' => str_word_count($text),
            ]);

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
                                'text_length' => $textLength,
                                'word_count' => str_word_count($text),
                            ],
                        ],
                    ]
                ),
            ]);
            
            Log::info('SyncDailyTranscriptJob: transcript persisted to database', [
                'session_id' => $this->sessionId,
                'recording_id' => $recording->id,
                'transcription_status' => 'completed',
            ]);

            if ($this->generateAiSummary) {
                try {
                    Log::debug('SyncDailyTranscriptJob: generating AI summary');
                    $sessionInsightService->generatePostSessionSummary(
                        $session->fresh(['client', 'coach', 'recording']),
                        true
                    );
                    Log::info('SyncDailyTranscriptJob: AI summary generated successfully');
                } catch (\Throwable $e) {
                    Log::warning('SyncDailyTranscriptJob: post-session AI summary generation failed', [
                        'session_id' => $session->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SyncDailyTranscriptJob: exception occurred', [
                'session_id' => $this->sessionId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
