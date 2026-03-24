<?php

namespace App\Services\Session;

use App\Models\Session\CoachingSession;

class SessionPricingService
{
    public const STANDARD_TOKEN_COST = 1;

    /**
     * One free intro session per client-coach pair.
     *
     * A pair remains eligible when prior sessions only ended in failed/cancelled/no_show.
     * Once there is any scheduled/live/interrupted/completed session, the intro is considered used.
     */
    public function isFreeIntroEligible(?int $clientId, int $coachId): bool
    {
        if (!$clientId) {
            return false;
        }

        return !CoachingSession::query()
            ->where('client_id', $clientId)
            ->where('coach_id', $coachId)
            ->whereIn('status', ['scheduled', 'live', 'interrupted', 'completed', 'in_progress'])
            ->exists();
    }

    public function tokenCost(?int $clientId, int $coachId): int
    {
        return $this->isFreeIntroEligible($clientId, $coachId)
            ? 0
            : self::STANDARD_TOKEN_COST;
    }

    public function preview(?int $clientId, int $coachId): array
    {
        $isIntroEligible = $this->isFreeIntroEligible($clientId, $coachId);
        $tokenCost = $isIntroEligible ? 0 : self::STANDARD_TOKEN_COST;

        return [
            'token_cost' => $tokenCost,
            'is_intro_eligible' => $isIntroEligible,
            'session_kind' => $isIntroEligible ? 'free_intro' : 'paid_follow_up',
            'display_price' => $tokenCost === 0 ? 'Free' : $tokenCost . ' token',
            'note' => $isIntroEligible
                ? 'Your first 15-minute intro session with this coach is free.'
                : 'This session will use 1 token.',
        ];
    }
}
