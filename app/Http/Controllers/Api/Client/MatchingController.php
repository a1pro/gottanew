<?php
// app/Http/Controllers/Api/Client/MatchingController.php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CoachMatch;
use App\Models\ClientQuestionnaire;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    /**
     * Generate coach matches for a client
     */
    public function generateMatches($clientId)
    {
        $client = User::with('questionnaire')->findOrFail($clientId);
        
        if (!$client->questionnaire || !$client->questionnaire->completed) {
            return response()->json([
                'message' => 'Please complete questionnaire first'
            ], 400);
        }

        // Get all approved coaches
        $coaches = User::whereHas('roles', function($q) {
            $q->where('slug', 'coach');
        })->whereHas('coachProfile', function($q) {
            $q->where('onboarding_completed', true)
              ->where('is_approved', true);
        })->with('coachProfile')->get();

        $matches = [];
        $clientGoals = $client->questionnaire->goals;
        $clientPersonality = $client->questionnaire->personality_traits;

        foreach ($coaches as $coach) {
            $score = $this->calculateMatchScore($client, $coach);
            $reasons = $this->getMatchReasons($client, $coach);

            // Save match
            $match = CoachMatch::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'coach_id' => $coach->id
                ],
                [
                    'match_score' => $score,
                    'match_reasons' => $reasons
                ]
            );

            $matches[] = $match;
        }

        // Get top 5-6 matches
        $topMatches = CoachMatch::where('client_id', $client->id)
            ->with('coach.coachProfile')
            ->orderBy('match_score', 'desc')
            ->limit(6)
            ->get();

        return $topMatches;
    }

    /**
     * Get shortlist for current client
     */
    public function getShortlist(Request $request)
    {
        $client = $request->user();

        // Check if matches exist, if not generate them
        $existingMatches = CoachMatch::where('client_id', $client->id)->count();
        
        if ($existingMatches === 0) {
            $this->generateMatches($client->id);
        }

        // Get top 6 matches
        $matches = CoachMatch::where('client_id', $client->id)
            ->with(['coach' => function($q) {
                $q->with('coachProfile');
            }])
            ->orderBy('match_score', 'desc')
            ->limit(6)
            ->get();

        // Mark as presented
        foreach ($matches as $match) {
            $match->update(['presented_to_client' => true]);
        }

        // Format response
        $shortlist = $matches->map(function($match) {
            $coach = $match->coach;
            $profile = $coach->coachProfile;
            
            // Get today's availability for preview
            $today = strtolower(now()->format('l'));
            $availabilityToday = $coach->coachAvailability()
                ->where('day_of_week', $today)
                ->where('is_available', true)
                ->get();

            return [
                'match_id' => $match->id,
                'match_score' => $match->match_score,
                'match_reasons' => $match->match_reasons,
                'coach' => [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'avatar' => $coach->avatar,
                    'bio' => $profile->bio,
                    'expertise' => $profile->expertise,
                    'coaching_styles' => $profile->coaching_styles,
                    'rating' => $profile->rating,
                    'hourly_rate' => $profile->hourly_rate,
                    'total_sessions' => $profile->total_sessions,
                    'availability_today' => $availabilityToday->map(function($slot) {
                        return [
                            'start' => $slot->start_time,
                            'end' => $slot->end_time
                        ];
                    })
                ]
            ];
        });

        return response()->json([
            'shortlist' => $shortlist,
            'total_matches' => $matches->count()
        ]);
    }

    /**
     * Select a coach from shortlist
     */
    public function selectCoach(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:coach_matches,id'
        ]);

        $match = CoachMatch::findOrFail($request->match_id);
        
        // Ensure this match belongs to the client
        if ($match->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as selected
        $match->update([
            'selected_by_client' => true,
            'selected_at' => now()
        ]);

        return response()->json([
            'message' => 'Coach selected successfully',
            'coach' => [
                'id' => $match->coach_id,
                'name' => $match->coach->name
            ]
        ]);
    }

    /**
     * Calculate match score between client and coach
     */
    private function calculateMatchScore($client, $coach)
    {
        $score = 0;
        $profile = $coach->coachProfile;
        $clientData = $client->questionnaire;

        // Expertise matching (40 points)
        $clientGoalAreas = array_column($clientData->goals ?? [], 'area');
        $coachExpertise = $profile->expertise ?? [];
        
        $expertiseMatch = count(array_intersect($clientGoalAreas, $coachExpertise));
        $expertiseScore = min($expertiseMatch * 10, 40); // 10 points per match, max 40
        $score += $expertiseScore;

        // Coaching style matching (30 points)
        $clientPreference = $clientData->personality_traits['support_preference'] ?? null;
        $coachStyles = $profile->coaching_styles ?? [];
        
        $styleMap = [
            'guidance' => ['supportive', 'mentor'],
            'accountability' => ['directive', 'structured'],
            'encouragement' => ['supportive', 'motivational'],
            'challenge' => ['directive', 'challenging']
        ];

        $preferredStyles = $styleMap[$clientPreference] ?? [];
        $styleMatch = count(array_intersect($preferredStyles, $coachStyles));
        $score += $styleMatch * 15; // 15 points per style match, max 30

        // Availability check (20 points)
        $availability = $coach->coachAvailability()->count();
        if ($availability > 0) {
            $score += 20;
        }

        // Experience (10 points)
        if ($profile->total_sessions > 100) {
            $score += 10;
        } elseif ($profile->total_sessions > 50) {
            $score += 7;
        } elseif ($profile->total_sessions > 20) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Get reasons for match
     */
    private function getMatchReasons($client, $coach)
    {
        $reasons = [];
        $profile = $coach->coachProfile;
        $clientData = $client->questionnaire;

        // Expertise reasons
        $clientGoalAreas = array_column($clientData->goals ?? [], 'area');
        $coachExpertise = $profile->expertise ?? [];
        $matchingExpertise = array_intersect($clientGoalAreas, $coachExpertise);
        
        if (!empty($matchingExpertise)) {
            $reasons[] = "Expert in: " . implode(', ', $matchingExpertise);
        }

        // Style reasons
        $clientPreference = $clientData->personality_traits['support_preference'] ?? null;
        $coachStyles = $profile->coaching_styles ?? [];
        
        if ($clientPreference === 'guidance' && in_array('supportive', $coachStyles)) {
            $reasons[] = "Provides supportive guidance you're looking for";
        }
        if ($clientPreference === 'accountability' && in_array('structured', $coachStyles)) {
            $reasons[] = "Great for accountability and structure";
        }

        // Experience reason
        if ($profile->total_sessions > 50) {
            $reasons[] = "Highly experienced with " . $profile->total_sessions . "+ sessions";
        }

        // Rating reason
        if ($profile->rating >= 4.5) {
            $reasons[] = "Excellent rating from clients";
        }

        return $reasons;
    }

    /**
     * Get available time slots for a coach
     */
    public function getCoachAvailability(Request $request, $coachId)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $dayOfWeek = strtolower(now()->parse($date)->format('l'));

        $availability = CoachAvailability::where('coach_id', $coachId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->get();

        return response()->json($availability);
    }
}