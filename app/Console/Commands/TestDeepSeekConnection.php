<?php

namespace App\Console\Commands;

use App\Models\Session\CoachingSession;
use App\Services\Ai\DeepSeekClient;
use App\Services\Ai\SessionInsightService;
use Illuminate\Console\Command;

class TestDeepSeekConnection extends Command
{
    /**
     * php artisan deepseek:test              -> just checks the API connection
     * php artisan deepseek:test --session=42  -> also runs pre/post summary generation on a real session
     */
    protected $signature = 'deepseek:test {--session= : Optional session ID to test real pre/post summary generation}';

    protected $description = 'Verify the DeepSeek API key/config works, and optionally test summary generation on a real session';

    public function handle(DeepSeekClient $client, SessionInsightService $insightService): int
    {
        $this->info('Step 1: Checking DeepSeek API connection...');

        $ping = $client->ping();

        if (!$ping['ok']) {
            $this->error('DeepSeek connection FAILED: ' . $ping['error']);
            $this->warn('Check DEEPSEEK_API_KEY / DEEPSEEK_BASE_URL / DEEPSEEK_MODEL in .env, then run php artisan config:clear.');
            return self::FAILURE;
        }

        $this->info('DeepSeek connection OK.');
        $this->line('  Model responded: ' . $ping['model']);
        $this->line('  Content: ' . $ping['content']);
        $this->line('  Usage: ' . json_encode($ping['usage']));

        $sessionId = $this->option('session');

        if (!$sessionId) {
            $this->comment('No --session given, skipping summary generation test. Example: php artisan deepseek:test --session=42');
            return self::SUCCESS;
        }

        $this->info("Step 2: Generating pre/post summaries for session #{$sessionId}...");

        $session = CoachingSession::with(['client', 'coach', 'recording'])->find($sessionId);

        if (!$session) {
            $this->error("Session #{$sessionId} not found.");
            return self::FAILURE;
        }

        $preRecording = $insightService->generatePreSessionSummary($session, true);
        $this->line('');
        $this->info('--- pre_session_summary ---');
        $this->line((string) $preRecording->pre_session_summary);
        $this->line('');
        $this->info('--- personality_insights ---');
        $this->line(json_encode($preRecording->personality_insights, JSON_PRETTY_PRINT));

        $postRecording = $insightService->generatePostSessionSummary($session->fresh(['client', 'coach', 'recording']), true);
        $this->line('');
        $this->info('--- post_session_summary ---');
        $this->line((string) $postRecording->post_session_summary);
        $this->line('');
        $this->info('--- next_actions ---');
        $this->line(json_encode($postRecording->next_actions, JSON_PRETTY_PRINT));
        $this->line('');
        $this->info('--- key_topics ---');
        $this->line(json_encode($postRecording->key_topics, JSON_PRETTY_PRINT));

        $this->line('');
        $this->info('Done. Check storage/logs/laravel.log for any "DeepSeek API" warnings — if you see none, both summaries came from the AI path, not the fallback.');

        return self::SUCCESS;
    }
}