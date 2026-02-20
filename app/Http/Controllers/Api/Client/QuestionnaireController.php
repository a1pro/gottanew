<?php
// app/Http/Controllers/Api/Client/QuestionnaireController.php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientGoalsRequest;
use App\Http\Requests\Client\ClientPersonalityRequest;
use App\Models\ClientQuestionnaire;

class QuestionnaireController extends Controller
{
    public function saveGoals(ClientGoalsRequest $request)
    {
        $client = $request->user();

        $questionnaire = ClientQuestionnaire::firstOrNew([
            'client_id' => $client->id
        ]);

        $questionnaire->goals = $request->goals;
        $questionnaire->save();

        return response()->json([
            'message' => 'Goals saved successfully',
            'goals' => $questionnaire->goals
        ]);
    }

    public function savePersonality(ClientPersonalityRequest $request)
    {
        $client = $request->user();

        $questionnaire = ClientQuestionnaire::firstOrNew([
            'client_id' => $client->id
        ]);

        $questionnaire->personality_traits = $request->personality_traits;
        $questionnaire->completed = true;
        $questionnaire->save();

        // Trigger matching
        app(MatchingController::class)->generateMatches($client->id);

        return response()->json([
            'message' => 'Personality traits saved successfully',
            'personality_traits' => $questionnaire->personality_traits
        ]);
    }

    public function getStatus()
    {
        $client = auth()->user();
        $questionnaire = ClientQuestionnaire::where('client_id', $client->id)->first();

        $status = [
            'goals_completed' => !empty($questionnaire?->goals),
            'personality_completed' => !empty($questionnaire?->personality_traits),
            'questionnaire_completed' => $questionnaire?->completed ?? false
        ];

        return response()->json([
            'status' => $status,
            'questionnaire' => $questionnaire
        ]);
    }
}