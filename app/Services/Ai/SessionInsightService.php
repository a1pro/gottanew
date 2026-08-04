<?php

namespace App\Services\Ai;

use App\Models\Goal\UserGoal;
use App\Models\Response\UserResponse;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SessionInsightService
{
    public function __construct(
        private DeepSeekClient $ai,
        private AiPromptResolver $prompts,
    ) {
    }

    public function ensureRecording(CoachingSession $session): SessionRecording
    {
        return SessionRecording::firstOrCreate(
            ['session_id' => $session->id],
            [
                'transcription_status' => 'inactive',
                'privacy_settings' => [
                    'recording_enabled' => false,
                    'transcription_consent' => 'none',
                ],
            ]
        );
    }

    public function payload(CoachingSession $session, bool $includeCoachAssistant = false): array
    {
        $session->loadMissing(['recording', 'messages', 'resources']);
        $recording = $this->ensureRecording($session)->fresh();

        return [
            'session_id' => $session->id,
            'pre_session_summary' => $recording->pre_session_summary,
            'post_session_summary' => $recording->post_session_summary,
            'ai_summary' => $recording->ai_summary,
            'next_actions' => $recording->next_actions ?? [],
            'key_topics' => $recording->key_topics ?? [],
            'personality_insights' => $recording->personality_insights ?? [],
            'transcription_status' => $recording->transcription_status,
            'pre_session_generated_at' => optional($recording->pre_session_generated_at)?->toISOString(),
            'post_session_generated_at' => optional($recording->post_session_generated_at)?->toISOString(),
            'coach_assistant' => $includeCoachAssistant ? $this->buildCoachAssistantPayload($session, $recording) : null,
        ];
    }

    // =========================================================================
    // PRE-SESSION SUMMARY
    // =========================================================================

    public function generatePreSessionSummary(CoachingSession $session, bool $force = false): SessionRecording
    {
        $session->loadMissing(['client', 'coach', 'recording']);
        $recording = $this->ensureRecording($session);

        if (!$force && !empty($recording->pre_session_summary)) {
            return $recording->fresh();
        }

        $goals = UserGoal::query()
            ->where('user_id', $session->client_id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest()
            ->take(3)
            ->get();

        $responses = UserResponse::query()
            ->with('question')
            ->where('user_id', $session->client_id)
            ->latest()
            ->take(6)
            ->get();

        $aiResult = $this->generatePreSessionViaAi($session, $goals, $responses);

        if ($aiResult) {
            $recording->update([
                'pre_session_summary' => $aiResult['summary'],
                'personality_insights' => $aiResult['personality_insights'],
                'pre_session_generated_at' => now(),
            ]);

            Log::info('Pre-session summary generated via DeepSeek AI', ['session_id' => $session->id]);

            return $recording->fresh();
        }

        Log::info('Pre-session summary generated via rule-based fallback', ['session_id' => $session->id]);

        return $this->generatePreSessionFallback($session, $recording, $goals, $responses);
    }

    private function generatePreSessionViaAi(CoachingSession $session, Collection $goals, Collection $responses): ?array
    {
        $goalLines = $goals->map(fn ($g) => trim((string) $g->title))->filter()->values()->all();

        $qa = $responses->map(function ($response) {
            $question = trim((string) optional($response->question)->question);
            $answer = $this->cleanText((string) $response->answer);
            return $question !== '' ? "{$question} => {$answer}" : $answer;
        })->filter()->values()->all();

        $coach = $session->coach;

        // System prompt comes from the DB (admin-editable), falling back to a
        // hardcoded default if no row exists or it's deactivated.
        $promptConfig = $this->prompts->resolve('pre_session_summary');

        $user = json_encode([
            'client_goals' => $goalLines ?: ['No goals recorded yet — clarify the primary goal at the start.'],
            'questionnaire_answers' => $qa,
            'coach_name' => optional($coach)->name ?: 'Coach',
            'coach_title' => optional($coach)->title ?: 'Coach',
            'coaching_style' => optional($coach)->coaching_style ?: 'Supportive and goal-focused',
            'session_length_minutes' => $session->duration_minutes ?? 15,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->ai->generateJson(
            $promptConfig['system_prompt'],
            (string) $user,
            $promptConfig['max_tokens'],
            $promptConfig['temperature'],
            $promptConfig['model']
        );

        if (!$result || empty($result['summary']) || !is_string($result['summary'])) {
            return null;
        }

        return [
            'summary' => trim($result['summary']),
            'personality_insights' => collect($result['personality_insights'] ?? [])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->take(4)
                ->values()
                ->all(),
        ];
    }

    /**
     * Original rule-based implementation, kept as a safety net when the AI call
     * fails, times out, or returns malformed JSON.
     */
    private function generatePreSessionFallback(CoachingSession $session, SessionRecording $recording, Collection $goals, Collection $responses): SessionRecording
    {
        $goalLines = $goals->isNotEmpty()
            ? $goals->map(fn ($goal) => '- ' . trim($goal->title))->values()->all()
            : ['- Clarify the client’s primary goal in the first few minutes of the session.'];

        $personalitySignals = $responses->map(function ($response) {
            $question = trim((string) optional($response->question)->question);
            $answer = $this->cleanText((string) $response->answer);

            if ($question !== '') {
                return $this->truncate("{$question}: {$answer}", 110);
            }

            return $this->truncate($answer, 110);
        })->filter()->take(4)->values()->all();

        $coachName = optional($session->coach)->name ?: 'Coach';
        $coachTitle = optional($session->coach)->title ?: 'Coach';
        $coachStyle = optional($session->coach)->coaching_style ?: 'Supportive and goal-focused';
        $primaryGoal = $goals->first()?->title ?: 'clarify the client’s immediate priority';

        $focusPoints = [
            "- Open by aligning on the most important outcome for this 15-minute session.",
            "- Keep the session focused on {$primaryGoal}.",
            "- Finish with one concrete next step the client can take within 7 days.",
        ];

        if (!empty($personalitySignals)) {
            $focusPoints[] = "- Adapt the conversation pace and tone to the client signals below.";
        }

        $summarySections = [
            "Client goals:",
            implode("\n", $goalLines),
            "",
            "Recommended session focus:",
            implode("\n", $focusPoints),
            "",
            "Coach context:",
            "- {$coachName} ({$coachTitle})",
            "- Coaching style: {$coachStyle}",
        ];

        if (!empty($personalitySignals)) {
            $summarySections[] = "";
            $summarySections[] = "Client signals from questionnaire:";
            $summarySections[] = implode("\n", array_map(fn ($item) => "- {$item}", $personalitySignals));
        }

        $summarySections[] = "";
        $summarySections[] = "Suggested preparation:";
        $summarySections[] = "- Confirm the client’s top priority at the beginning.";
        $summarySections[] = "- Keep questions practical and time-bound.";
        $summarySections[] = "- End with a clear commitment or action item.";

        $recording->update([
            'pre_session_summary' => implode("\n", $summarySections),
            'personality_insights' => $personalitySignals,
            'pre_session_generated_at' => now(),
        ]);

        return $recording->fresh();
    }

    // =========================================================================
    // POST-SESSION SUMMARY
    // =========================================================================

    public function generatePostSessionSummary(CoachingSession $session, bool $force = false): SessionRecording
    {
        $session->loadMissing(['client', 'coach', 'recording']);
        $recording = $this->ensureRecording($session);

        if (!$force && !empty($recording->post_session_summary)) {
            return $recording->fresh();
        }

        $goal = UserGoal::query()
            ->where('user_id', $session->client_id)
            ->where('source_session_id', $session->id)
            ->first();

        $goals = $goal ? collect([$goal]) : collect();

        $sourceText = trim(implode("\n", array_filter([
            $this->cleanText((string) $recording->transcript),
            $this->cleanText((string) $session->coach_notes),
            $this->cleanText((string) $session->client_notes),
        ])));

        $aiResult = $this->generatePostSessionViaAi($session, $sourceText, $goals);

        if ($aiResult) {
            $recording->update([
                'post_session_summary' => $aiResult['summary'],
                'ai_summary' => $aiResult['summary'],
                'next_actions' => $aiResult['next_actions'],
                'key_topics' => $aiResult['key_topics'],
                'post_session_generated_at' => now(),
            ]);

            Log::info('Post-session summary generated via DeepSeek AI', ['session_id' => $session->id]);

            return $recording->fresh();
        }

        Log::info('Post-session summary generated via rule-based fallback', ['session_id' => $session->id]);

        return $this->generatePostSessionFallback($session, $recording, $sourceText, $goals);
    }

    private function generatePostSessionViaAi(CoachingSession $session, string $sourceText, Collection $goals): ?array
    {
        if (trim($sourceText) === '' && $goals->isEmpty()) {
            return null; // nothing to summarize — let the fallback produce sensible defaults
        }

        $goalContext = $goals->map(fn ($g) => ['id' => $g->id, 'title' => $g->title])->values()->all();

        $promptConfig = $this->prompts->resolve('post_session_summary');

        $user = json_encode([
            'transcript_and_notes' => $this->truncate($sourceText, 6000),
            'related_goals' => $goalContext,
            'is_intro_session' => (bool) ($session->is_intro_session ?? false),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->ai->generateJson(
            $promptConfig['system_prompt'],
            (string) $user,
            $promptConfig['max_tokens'],
            $promptConfig['temperature'],
            $promptConfig['model']
        );

        if (!$result || empty($result['summary']) || !is_string($result['summary'])) {
            return null;
        }

        $nextActions = collect($result['next_actions'] ?? [])
            ->filter(fn ($item) => is_array($item) && !empty($item['action']))
            ->map(fn ($item) => [
                'goal_id' => $item['goal_id'] ?? null,
                'goal_title' => $item['goal_title'] ?? null,
                'action' => (string) $item['action'],
            ])
            ->take(3)
            ->values()
            ->all();

        $keyTopics = collect($result['key_topics'] ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->take(6)
            ->values()
            ->all();

        return [
            'summary' => trim($result['summary']),
            'next_actions' => $nextActions,
            'key_topics' => $keyTopics,
        ];
    }

    /**
     * Original rule-based implementation, kept as a safety net when the AI call
     * fails, times out, or returns malformed JSON.
     */
    private function generatePostSessionFallback(CoachingSession $session, SessionRecording $recording, string $sourceText, Collection $goals): SessionRecording
    {
        $summarySentence = $this->buildSummaryLead($sourceText, $goals);
        $keyDecisions = $this->extractDecisionSentences($sourceText, $goals);
        $nextActions = $this->extractActionItems($sourceText, $goals);
        $keyTopics = $this->extractKeywords($sourceText ?: $goals->pluck('title')->implode(' '));

        $formattedActions = array_map(function ($item) {

        if (is_array($item)) {
            return "- " . ($item['goal_title'] ? $item['goal_title'] . ": " : "") . $item['action'];
        }

        return "- " . $item;

        }, $nextActions);

        $postSummaryLines = [
            "Session summary:",
            $summarySentence,
            "",
            "Key decisions:",
            ...array_map(fn ($item) => "- {$item}", $keyDecisions),
            "",
            "Next actions:",
            ...$formattedActions,
        ];

        $recording->update([
            'post_session_summary' => implode("\n", $postSummaryLines),
            'ai_summary' => implode("\n", $postSummaryLines),
            'next_actions' => $nextActions,
            'key_topics' => $keyTopics,
            'post_session_generated_at' => now(),
        ]);

        return $recording->fresh();
    }

    // =========================================================================
    // COACH ASSISTANT PANEL (unchanged — still rule-based, not in scope yet)
    // =========================================================================

    private function buildCoachAssistantPayload(CoachingSession $session, SessionRecording $recording): array
    {
        $session->loadMissing(['client', 'coach', 'messages', 'resources']);

        $goals = UserGoal::query()
            ->where('user_id', $session->client_id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest()
            ->take(3)
            ->get();

        $focusGoal = $goals->first()?->title;
        $transcript = $this->cleanText((string) $recording->transcript);
        $coachNotes = $this->cleanText((string) $session->coach_notes);
        $personalityInsights = collect($recording->personality_insights ?? [])->filter()->values()->all();
        $keyTopics = collect($recording->key_topics ?? [])->filter()->values()->all();
        $nextActions = collect($recording->next_actions ?? [])->filter()->values()->all();

        $openingFocus = $focusGoal
            ? "Anchor the session around {$focusGoal} and agree on one concrete outcome before advice-giving."
            : 'Use the first two minutes to define what would make this short session successful for the client.';

        if ($session->is_intro_session) {
            $openingFocus = 'Treat this as an intro call: build trust quickly, confirm the main challenge, and end with a clear follow-up recommendation.';
        }

        $engagementSignals = [
            [
                'label' => 'Transcription',
                'status' => in_array($recording->transcription_status, ['active', 'completed'], true) ? 'good' : 'watch',
                'detail' => in_array($recording->transcription_status, ['active', 'completed'], true)
                    ? 'Transcript context is available for live and post-session insights.'
                    : 'Transcript is limited or off. Capture key commitments in coach notes.',
            ],
        ];

        if ($transcript !== '') {
            $engagementSignals[] = [
                'label' => 'Conversation depth',
                'status' => Str::length($transcript) >= 500 ? 'good' : 'info',
                'detail' => Str::length($transcript) >= 500
                    ? 'Enough conversation context is available for sharper follow-up questions.'
                    : 'Transcript context is still short. Ask one more clarifying question before closing.',
            ];
        }

        $engagementSignals[] = [
            'label' => 'Coach notes',
            'status' => Str::length($coachNotes) >= 60 ? 'good' : 'watch',
            'detail' => Str::length($coachNotes) >= 60
                ? 'Coach notes already capture useful context for the summary.'
                : 'Add a brief summary and one commitment so the handoff stays clear after the call.',
        ];

        if (!empty($nextActions)) {
            $engagementSignals[] = [
                'label' => 'Next steps',
                'status' => 'good',
                'detail' => 'Action items are already present. Confirm ownership and timing before ending.',
            ];
        }

        $messageCount = method_exists($session, 'messages') ? $session->messages->count() : 0;
        $resourceCount = method_exists($session, 'resources') ? $session->resources->count() : 0;
        $engagementSignals[] = [
            'label' => 'Collaboration',
            'status' => ($messageCount + $resourceCount) > 0 ? 'good' : 'info',
            'detail' => ($messageCount + $resourceCount) > 0
                ? 'The session already has messages or resources to support follow-through.'
                : 'Share one short resource or written recap if the client needs structure after the call.',
        ];

        $suggestedQuestions = $this->buildSuggestedQuestions($focusGoal, $personalityInsights, $keyTopics, $session->is_intro_session);

        $livePrompts = [
            'Reflect the client’s last key phrase before you move into advice or planning.',
            'Check confidence: ask what feels realistic in the next 7 days.',
            $session->is_intro_session
                ? 'End by confirming whether the client wants another session and what it should focus on.'
                : 'End by naming the single most important next step and who owns it.',
        ];

        return [
            'opening_focus' => $openingFocus,
            'suggested_questions' => $suggestedQuestions,
            'engagement_signals' => $engagementSignals,
            'live_prompts' => $livePrompts,
            'transcript_preview' => $transcript !== '' ? $this->truncate($transcript, 420) : null,
            'recommended_close' => !empty($nextActions)
                ? 'Recap the agreed actions out loud and confirm which one the client will do first.'
                : 'Before ending, ask the client to choose one concrete action and when they will do it.',
        ];
    }

    private function buildSuggestedQuestions(?string $focusGoal, array $personalityInsights, array $keyTopics, bool $isIntroSession): array
    {
        $questions = [];

        $questions[] = $focusGoal
            ? "What would progress on {$focusGoal} look like by next week?"
            : 'What would make this session feel useful by the time we end today?';

        if (!empty($personalityInsights)) {
            $signal = $this->cleanText((string) $personalityInsights[0]);
            $questions[] = 'I noticed a signal from your questionnaire that may matter here — what feels most true for you right now?';
            if ($signal !== '') {
                $questions[] = 'Which part of this feels hardest in real life, not just in theory?';
            }
        } else {
            $questions[] = 'What is getting in the way most often when you try to act on this?';
        }

        if (!empty($keyTopics)) {
            $topic = $this->cleanText((string) $keyTopics[0]);
            $questions[] = $topic !== ''
                ? "How is {$topic} affecting your decisions or energy right now?"
                : 'What part of this situation is taking the most energy right now?';
        }

        $questions[] = $isIntroSession
            ? 'Would you like the next session to go deeper into strategy, mindset, or accountability?'
            : 'What is the smallest next step you are ready to commit to before the next session?';

        return collect($questions)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->unique()
            ->values()
            ->take(5)
            ->all();
    }

    // =========================================================================
    // SHARED HELPERS (unchanged — used by fallbacks and coach assistant panel)
    // =========================================================================

    private function buildSummaryLead(string $text, Collection $goals): string
    {
        $sentences = $this->splitSentences($text);

        if (!empty($sentences)) {
            return $this->truncate(implode(' ', array_slice($sentences, 0, 2)), 280);
        }

        $goal = $goals->first()?->title;

        if ($goal) {
            return "This session focused on {$goal} and ended with practical next-step planning.";
        }

        return "This coaching session focused on clarifying the client’s priorities and identifying immediate next steps.";
    }

    private function extractDecisionSentences(string $text, Collection $goals): array
    {
        $sentences = $this->splitSentences($text);

        $matches = collect($sentences)
            ->filter(function ($sentence) {
                $s = Str::lower($sentence);
                return Str::contains($s, [
                    'decide', 'decided', 'plan', 'planned', 'focus', 'commit',
                    'next', 'action', 'will', 'schedule', 'start', 'follow up'
                ]);
            })
            ->take(3)
            ->values()
            ->all();

        if (!empty($matches)) {
            return array_map(fn ($item) => $this->truncate($item, 160), $matches);
        }

        if ($goals->isNotEmpty()) {
            return [
                "The session stayed focused on {$goals->first()->title}.",
                "The client and coach aligned on a short-term practical next step.",
            ];
        }

        return [
            'The session clarified the client’s immediate priority.',
            'The conversation ended with an actionable next step.',
        ];
    }

    private function extractActionItems(string $text, Collection $goals): array
    {
        $sentences = $this->splitSentences($text);

        $actions = collect($sentences)
            ->filter(function ($sentence) {
                $s = Str::lower($sentence);
                return Str::contains($s, [
                    'next', 'action', 'follow', 'practice', 'prepare',
                    'schedule', 'commit', 'plan', 'will', 'send', 'review'
                ]);
            })
            ->map(fn ($sentence) => $this->truncate($sentence, 150))
            ->unique()
            ->take(3)
            ->values()
            ->all();

        if (!empty($actions)) {

            return collect($actions)->map(function ($action) use ($goals) {

                $goal = $goals->first();

                return [
                    'goal_id' => $goal?->id,
                    'goal_title' => $goal?->title,
                    'action' => $action,
                ];

            })->values()->all();
        }

        if ($goals->isNotEmpty()) {
            return $goals->take(3)->map(function ($goal) {

                return [
                    'goal_id' => $goal->id,
                    'goal_title' => $goal->title,
                    'action' => "Continue making progress on {$goal->title} before the next session.",
                ];

            })->values()->all();
        }

        return [
            [
                'goal_id' => null,
                'goal_title' => null,
                'action' => 'Review the main insight from this session within 24 hours.',
            ],
            [
                'goal_id' => null,
                'goal_title' => null,
                'action' => 'Choose one concrete action to complete before the next session.',
            ],
        ];
    }

    private function extractKeywords(string $text, int $limit = 6): array
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^a-z0-9\s]+/i', ' ', $text ?? '');
        $words = preg_split('/\s+/', trim((string) $text));

        $stopwords = [
            'the','and','for','with','that','this','from','have','will','your','about','session',
            'client','coach','they','them','their','into','then','than','were','been','being',
            'where','when','what','which','would','could','should','there','here','also','just',
            'goal','goals','next','action','actions','plan','plans','focus','need'
        ];

        $counts = [];

        foreach ($words as $word) {
            if (!$word || strlen($word) < 4 || in_array($word, $stopwords, true)) {
                continue;
            }

            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, $limit);
    }

    private function splitSentences(string $text): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));

        if ($clean === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+/', $clean) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    private function truncate(string $text, int $limit = 180): string
    {
        return Str::limit(trim($text), $limit, '...');
    }
}