<?php
// app/Http/Controllers/Api/Shared/SessionController.php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\CoachingSession;
use App\Models\User;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $sessions = CoachingSession::where('client_id', $user->id)
            ->orWhere('coach_id', $user->id)
            ->with(['client', 'coach'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $sessions->items(),
            'next_page' => $sessions->nextPageUrl(),
            'total' => $sessions->total()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'coach_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now'
        ]);

        // Verify coach exists and has coach role
        $coach = User::whereHas('roles', function($q) {
            $q->where('slug', 'coach');
        })->findOrFail($request->coach_id);

        $session = CoachingSession::create([
            'client_id' => $request->user()->id,
            'coach_id' => $coach->id,
            'scheduled_at' => $request->scheduled_at,
            'status' => 'scheduled'
        ]);

        return response()->json($session->load(['client', 'coach']), 201);
    }

    public function show($id)
    {
        $session = CoachingSession::with(['client', 'coach'])->findOrFail($id);
        
        // Check if user is part of this session
        if (!in_array(auth()->id(), [$session->client_id, $session->coach_id])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($session);
    }

    public function update(Request $request, $id)
    {
        $session = CoachingSession::findOrFail($id);
        
        // Only allow updates for scheduled sessions
        if ($session->status !== 'scheduled') {
            return response()->json(['message' => 'Cannot update this session'], 400);
        }

        $session->update($request->only(['scheduled_at']));

        return response()->json($session);
    }

    public function cancel($id)
    {
        $session = CoachingSession::findOrFail($id);
        $session->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Session cancelled']);
    }
}