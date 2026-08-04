<?php

namespace App\Services\Ai;

use App\Models\Ai\AiPrompt;
use Illuminate\Support\Facades\Cache;

class AiPromptResolver
{
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_PREFIX = 'ai_prompt:';

    /**
     * Hardcoded fallback defaults. Used when a DB row is missing, deactivated,
     * or the DB is unreachable. These match what the app used before prompts
     * became admin-editable, so nothing breaks if a row is deleted.
     */
    private const DEFAULTS = [
        'pre_session_summary' => [
            'label' => 'Pre-Session Summary',
            'description' => 'Generates the coach-facing briefing before a session starts, based on client goals and questionnaire answers.',
            'system_prompt' => <<<PROMPT
                You are an assistant preparing a coach for a short 1:1 coaching call. Given the client's goals and their
                questionnaire answers, produce a concise, practical pre-session briefing.

                Respond ONLY with valid JSON matching this exact shape, no markdown, no extra text:
                {
                "summary": "string, plain text, ready to paste as-is, max ~180 words, using this structure:
                    'Client goals:' section, 'Recommended session focus:' section, 'Coach context:' section",
                "personality_insights": ["short string", "short string", ...]   // max 4 items, each under 20 words
                }
                PROMPT,
            'max_tokens' => 1500,
            'temperature' => 0.4,
            'model' => null,
        ],
        'post_session_summary' => [
            'label' => 'Post-Session Summary',
            'description' => 'Generates the session recap after a call ends, based on transcript/notes and related goals.',
            'system_prompt' => <<<PROMPT
                You are an assistant summarizing a completed 1:1 coaching call for both the coach and the client to review later.
                Use only what's in the provided transcript/notes and goals — do not invent details.

                Respond ONLY with valid JSON matching this exact shape, no markdown, no extra text:
                {
                "summary": "string, plain text, structure: 'Session summary:' section (2-3 sentences), 'Key decisions:' bullet list, 'Next actions:' bullet list",
                "next_actions": [ { "goal_id": number|null, "goal_title": string|null, "action": "string, under 25 words" } ],
                "key_topics": ["single word or short phrase", ... up to 6]
                }
                PROMPT,
            'max_tokens' => 1800,
            'temperature' => 0.4,
            'model' => null,
        ],
    ];

    /**
     * Resolve a prompt config by key. Always returns a usable array —
     * never null — so callers don't need extra null-checks.
     *
     * @return array{system_prompt: string, max_tokens: int, temperature: float, model: ?string}
     */
    public function resolve(string $key): array
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        $config = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($key) {
            try {
                $prompt = AiPrompt::query()->where('key', $key)->where('is_active', true)->first();
            } catch (\Throwable $e) {
                // DB unreachable, table missing, etc. — fall through to defaults below.
                $prompt = null;
            }

            if (!$prompt) {
                return $this->defaultFor($key);
            }

            return [
                'system_prompt' => $prompt->system_prompt,
                'max_tokens' => $prompt->max_tokens,
                'temperature' => (float) $prompt->temperature,
                'model' => $prompt->model,
            ];
        });

        return $config;
    }

    public function defaultFor(string $key): array
    {
        $default = self::DEFAULTS[$key] ?? null;

        if (!$default) {
            throw new \InvalidArgumentException("No default prompt registered for key [{$key}]. Add it to AiPromptResolver::DEFAULTS.");
        }

        return [
            'system_prompt' => $default['system_prompt'],
            'max_tokens' => $default['max_tokens'],
            'temperature' => $default['temperature'],
            'model' => $default['model'],
        ];
    }

    /**
     * All known prompt keys with their labels/descriptions/defaults, used to
     * seed the database and to let the admin UI list available prompts even
     * before rows exist.
     */
    public static function registry(): array
    {
        return self::DEFAULTS;
    }

    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function forgetAll(): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            $this->forget($key);
        }
    }
}