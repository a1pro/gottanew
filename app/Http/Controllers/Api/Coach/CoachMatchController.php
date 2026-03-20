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
            'guest_session_id' => 'nullable|string',
        ]);

        $userId = auth()->id();
        $sessionId = $request->guest_session_id;
        $goalId = $request->goal_id;

        /*
        |----------------------------------------------------------
        | Load only the responses for THIS goal
        |----------------------------------------------------------
        */

        $responsesQuery = UserResponse::query()
            ->where('goal_id', $goalId);

        if ($userId) {
            $responsesQuery->where(function ($q) use ($userId, $sessionId) {
                $q->where('user_id', $userId);

                if ($sessionId) {
                    $q->orWhere(function ($nested) use ($sessionId, $userId) {
                        $nested->where('guest_session_id', $sessionId)
                               ->where(function ($inner) use ($userId) {
                                   $inner->whereNull('user_id')
                                         ->orWhere('user_id', $userId);
                               });
                    });
                }
            });
        } elseif ($sessionId) {
            $responsesQuery->where('guest_session_id', $sessionId);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Session context missing',
            ], 422);
        }

        $responses = $responsesQuery->get()->toArray();

        /*
        |----------------------------------------------------------
        | Load personality for current session if available
        |----------------------------------------------------------
        */

        $personality = null;

        if ($sessionId) {
            $session = GuestSession::where('session_id', $sessionId)
                ->when($userId, function ($q) use ($userId) {
                    $q->where(function ($nested) use ($userId) {
                        $nested->whereNull('user_id')
                               ->orWhere('user_id', $userId);
                    });
                })
                ->first();

            $personality = $session?->ai_analysis;
        }

        /*
        |----------------------------------------------------------
        | Run matching service
        |----------------------------------------------------------
        */

        $service = new CoachMatchingService();

        $coaches = $service->match(
            $goalId,
            $responses,
            $personality
        );

        return response()->json([
            'success' => true,
            'analysis' => 'Based on your responses and personality profile we found these coaches.',
            'recommendations' => $coaches,
            'totalRecommendations' => count($coaches),
        ]);
    }
}