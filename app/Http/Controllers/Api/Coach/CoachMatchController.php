<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Coach\CoachMatchingService;
use App\Models\Response\UserResponse;
use App\Models\Session\GuestSession;


class CoachMatchController extends Controller
{
    
    public function match(Request $request)
    {

        $request->validate([
            'goal_id' => 'required',
            'guest_session_id' => 'nullable|string'
        ]);

        $userId = auth()->id();
        $sessionId = $request->guest_session_id;

        /*
        |----------------------------------------------------------
        | Load Responses
        |----------------------------------------------------------
        */

     $responses = UserResponse::query()
            ->when($userId, function ($q) use ($userId, $sessionId) {

                $q->where('user_id', $userId);

                if ($sessionId) {
                    $q->orWhere('guest_session_id', $sessionId);
                }

            })
            ->when(!$userId && $sessionId, function ($q) use ($sessionId) {
                $q->where('guest_session_id', $sessionId);
            })
            ->get()
            ->toArray();

        /*
        |----------------------------------------------------------
        | Load Personality
        |----------------------------------------------------------
        */

        $personality = null;

        if ($sessionId) {
            $session = GuestSession::where('session_id', $sessionId)->first();
            $personality = $session?->ai_analysis;
        }

        /*
        |----------------------------------------------------------
        | Run Matching Service
        |----------------------------------------------------------
        */

        $service = new CoachMatchingService();

        $coaches = $service->match(
            $request->goal_id,
            $responses,
            $personality
        );

        return response()->json([
            'success' => true,
            'analysis' => "Based on your responses and personality profile we found these coaches.",
            'recommendations' => $coaches,
            'totalRecommendations' => count($coaches)
        ]);

    }
}