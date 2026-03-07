<?php

namespace App\Services\Coach;

use App\Models\Coach\Coach;
use App\Models\Response\UserResponse;

class CoachMatchingService
{

    public function match($goalId, $responses)
    {

        $coaches = Coach::where('is_active', true)->get();

        $results = [];

        foreach ($coaches as $coach) {

            $score = 0;

            /*
            |--------------------------------------------------------------------------
            | Goal Match
            |--------------------------------------------------------------------------
            */

            if (str_contains($coach->coaching_expertise, $goalId)) {
                $score += 50;
            }

            /*
            |--------------------------------------------------------------------------
            | Response Keyword Match
            |--------------------------------------------------------------------------
            */

            foreach ($responses as $response) {

                if (str_contains(
                    strtolower($coach->specialties),
                    strtolower($response['answer'])
                )) {
                    $score += 20;
                }

            }

            /*
            |--------------------------------------------------------------------------
            | Rating Boost
            |--------------------------------------------------------------------------
            */

            $score += $coach->rating * 2;

            $results[] = [
                'coach' => $coach,
                'score' => $score
            ];

        }

        /*
        |--------------------------------------------------------------------------
        | Sort Best Coaches
        |--------------------------------------------------------------------------
        */

        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($results, 0, 5);

    }

}