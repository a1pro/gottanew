<?php

namespace App\Services\Coach;

use App\Models\Coach\Coach;

class CoachMatchingService
{
    public function match($goalId, $responses, $personality = null)
    {
        $coaches = Coach::where('is_active', true)->get();

        $results = [];

        foreach ($coaches as $coach) {

            $score = 0;

            /*
            |--------------------------------------------------
            | Prepare Coach Data
            |--------------------------------------------------
            */

            $specialties = is_array($coach->specialties)
                ? implode(' ', $coach->specialties)
                : ($coach->specialties ?? '');

            $specialties = strtolower($specialties);

            /*
            |--------------------------------------------------
            | Response Keyword Match
            |--------------------------------------------------
            */

            if (!empty($responses) && is_array($responses)) {

                foreach ($responses as $response) {

                    if (!isset($response['answer']) || empty($response['answer'])) {
                        continue;
                    }

                    $answer = $response['answer'];

                    // Convert array answers to string
                    if (is_array($answer)) {
                        $answer = implode(' ', $answer);
                    }

                    $answer = strtolower($answer);

                    if (str_contains($specialties, $answer)) {
                        $score += 20;
                    }
                }
            }

            /*
            |--------------------------------------------------
            | Personality Compatibility
            |--------------------------------------------------
            */

            if ($personality) {

                $personalityString = is_array($personality)
                    ? implode(' ', $personality)
                    : $personality;

                $style = is_array($coach->coaching_style)
                    ? implode(' ', $coach->coaching_style)
                    : ($coach->coaching_style ?? '');

                $style = strtolower($style);
                $personalityString = strtolower($personalityString);

                if (str_contains($style, $personalityString)) {
                    $score += 15;
                }
            }

            /*
            |--------------------------------------------------
            | Rating Boost
            |--------------------------------------------------
            */

            $score += ($coach->rating ?? 0) * 2;

            /*
            |--------------------------------------------------
            | Experience Boost
            |--------------------------------------------------
            */

            $score += ($coach->years_experience ?? 0);

            /*
            |--------------------------------------------------
            | Store Result
            |--------------------------------------------------
            */

            $results[] = [
                'coachId' => $coach->id,
                'coachName' => $coach->name,
                'confidenceScore' => $score,
                'matchReason' => "Strong alignment with your goals and responses.",
                'coach' => $coach
            ];
        }

        /*
        |--------------------------------------------------
        | Sort Best Coaches
        |--------------------------------------------------
        */

        usort($results, function ($a, $b) {
            return $b['confidenceScore'] <=> $a['confidenceScore'];
        });

        return array_slice($results, 0, 8);
    }
}