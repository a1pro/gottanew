<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Session;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function book(Request $request)
    {
        $request->validate([
            'coachId' => 'required',
            'scheduledTime' => 'required|date',
            'sessionDuration' => 'required|integer|min:15',
        ]);

        $user = $request->user();

        $session = Session::create([
            'id' => Str::uuid(),
            'client_id' => $user->id,
            'coach_id' => $request->coachId,
            'scheduled_time' => $request->scheduledTime,
            'duration_minutes' => $request->sessionDuration,
            'status' => 'scheduled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session booked successfully',
            'session' => $session
        ]);
    }

    public function upcoming(Request $request)
        {
            $user = $request->user();

            $sessions = Session::where('client_id', $user->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('scheduled_time', '>=', now())
                ->orderBy('scheduled_time')
                ->with('coach') // if relation exists
                ->get();

            return response()->json($sessions);
        }
}