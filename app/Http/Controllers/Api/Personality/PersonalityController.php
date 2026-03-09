<?php

namespace App\Http\Controllers\Api\Personality;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Session\GuestSession;

class PersonalityController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'guest_session_id' => 'required|string',
            'responses' => 'required|array'
        ]);

        $userId = auth()->check() ? auth()->id() : null;

        $session = GuestSession::where('session_id', $request->guest_session_id)->first();

        if (!$session) {

            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);

        }

        $session->update([
            'user_id' => $userId ?? $session->user_id,
            'ai_analysis' => $request->responses
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Personality responses saved successfully'
        ]);
    }
}