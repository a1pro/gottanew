<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekClient
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.deepseek.key');
        $this->baseUrl = (string) config('services.deepseek.base_url');
        $this->model = (string) config('services.deepseek.model', 'deepseek-v4-flash');
    }

    /**
     * Quick connectivity/config check. Returns true only on a real 200 response.
     */
    public function ping(): array
    {
        if (trim($this->apiKey) === '') {
            return ['ok' => false, 'error' => 'DEEPSEEK_API_KEY is empty. Check .env and run php artisan config:clear.'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with exactly: DeepSeek connection OK'],
                    ],
                    'max_tokens' => 20,
                ]);

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'error' => "HTTP {$response->status()}: {$response->body()}",
                ];
            }

            $content = $response->json('choices.0.message.content');

            return [
                'ok' => true,
                'model' => $response->json('model'),
                'content' => $content,
                'usage' => $response->json('usage'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a system + user prompt and get back parsed JSON.
     * Returns null on any failure so callers can fall back gracefully.
     *
     * @param string|null $model Override the configured default model (e.g. an admin-set per-prompt model).
     */
    public function generateJson(string $systemPrompt, string $userPrompt, int $maxTokens = 800, float $temperature = 0.4, ?string $model = null): ?array
    {
        if (trim($this->apiKey) === '') {
            Log::warning('DeepSeek API key not configured; skipping AI call.');
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(25)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model ?: $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            if ($response->failed()) {
                Log::warning('DeepSeek API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $raw = $response->json('choices.0.message.content');

            if (!is_string($raw) || trim($raw) === '') {
                Log::warning('DeepSeek API returned empty content');
                return null;
            }

            // Strip accidental markdown fences just in case
            $clean = trim(preg_replace('/^```json|```$/m', '', $raw));
            $decoded = json_decode($clean, true);

            if (!is_array($decoded)) {
                Log::warning('DeepSeek API returned non-JSON content', ['raw' => $raw]);
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::error('DeepSeek API exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}