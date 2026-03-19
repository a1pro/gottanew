<?php

namespace App\Services\Ai;

use App\Models\Goal\UserGoal;
use App\Models\Response\UserResponse;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SessionInsightService
{
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

    public function payload(CoachingSession $session): array
    {
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
        ];
    }

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

    public function generatePostSessionSummary(CoachingSession $session, bool $force = false): SessionRecording
    {
        $session->loadMissing(['client', 'coach', 'recording']);
        $recording = $this->ensureRecording($session);

        if (!$force && !empty($recording->post_session_summary)) {
            return $recording->fresh();
        }

        $goals = UserGoal::query()
            ->where('user_id', $session->client_id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest()
            ->take(3)
            ->get();

        $sourceText = trim(implode("\n", array_filter([
            $this->cleanText((string) $recording->transcript),
            $this->cleanText((string) $session->coach_notes),
            $this->cleanText((string) $session->client_notes),
        ])));

        $summarySentence = $this->buildSummaryLead($sourceText, $goals);
        $keyDecisions = $this->extractDecisionSentences($sourceText, $goals);
        $nextActions = $this->extractActionItems($sourceText, $goals);
        $keyTopics = $this->extractKeywords($sourceText ?: $goals->pluck('title')->implode(' '));

        $postSummaryLines = [
            "Session summary:",
            $summarySentence,
            "",
            "Key decisions:",
            ...array_map(fn ($item) => "- {$item}", $keyDecisions),
            "",
            "Next actions:",
            ...array_map(fn ($item) => "- {$item}", $nextActions),
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
            return $actions;
        }

        if ($goals->isNotEmpty()) {
            return $goals->take(3)->map(function ($goal) {
                return "Continue making progress on {$goal->title} before the next session.";
            })->values()->all();
        }

        return [
            'Review the main insight from this session within 24 hours.',
            'Choose one concrete action to complete before the next session.',
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