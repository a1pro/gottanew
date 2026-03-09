<?php

namespace App\Http\Controllers\Api\Responses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Response\UserResponse;
use App\Models\Session\GuestSession;

class ResponseController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'goal_id' => 'required',
            'answers' => 'required|array',
            'guest_session_id' => 'nullable'
        ]);

        $userId = auth()->check() ? auth()->id() : null;
        $sessionId = $request->guest_session_id;

        // create or update guest session
        if ($sessionId) {

            GuestSession::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_id' => $userId,
                    'goal_id' => $request->goal_id
                ]
            );

        }

        foreach ($request->answers as $answer) {

            UserResponse::updateOrCreate(
                [
                    'user_id' => $userId,
                    'guest_session_id' => $sessionId,
                    'question_id' => $answer['question_id']
                ],
                [
                    'goal_id' => $request->goal_id,
                    'answer' => $answer['answer']
                ]
            );

        }

        return response()->json([
            'success' => true,
            'message' => 'Responses saved successfully'
        ]);
    }
}
