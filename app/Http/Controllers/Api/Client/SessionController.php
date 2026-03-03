<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use App\Models\Session\CoachingSession;
use App\Http\Controllers\Api\BaseController;

class SessionController extends BaseController
{
    public function index(Request $request)
    {
        return $this->success(
            $request->user()->clientSessions()->with('coach')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'coach_id' => 'required|exists:coaches,id',
            'scheduled_time' => 'required|date'
        ]);

        $session = CoachingSession::create([
            'client_id' => $request->user()->id,
            'coach_id' => $request->coach_id,
            'scheduled_time' => $request->scheduled_time
        ]);

        return $this->success($session, 'Session booked');
    }
}