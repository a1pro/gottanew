<?php
// app/Http/Controllers/Api/Coach/CoachController.php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function index()
    {
        $coaches = User::whereHas('roles', function($q) {
            $q->where('slug', 'coach');
        })
        ->with('coachProfile')
        ->get()
        ->map(function ($coach) {
            return [
                'id' => $coach->id,
                'name' => $coach->name,
                'avatar' => $coach->avatar,
                'bio' => $coach->coachProfile?->bio,
                'expertise' => $coach->coachProfile?->expertise,
                'rating' => $coach->coachProfile?->rating,
                'hourly_rate' => $coach->coachProfile?->hourly_rate,
                'total_sessions' => $coach->coachProfile?->total_sessions
            ];
        });

        return response()->json($coaches);
    }

    public function show($id)
    {
        $coach = User::whereHas('roles', function($q) {
            $q->where('slug', 'coach');
        })
        ->with('coachProfile')
        ->findOrFail($id);

        return response()->json([
            'id' => $coach->id,
            'name' => $coach->name,
            'email' => $coach->email,
            'avatar' => $coach->avatar,
            'bio' => $coach->coachProfile?->bio,
            'expertise' => $coach->coachProfile?->expertise,
            'rating' => $coach->coachProfile?->rating,
            'hourly_rate' => $coach->coachProfile?->hourly_rate,
            'total_sessions' => $coach->coachProfile?->total_sessions
        ]);
    }

    public function updateProfile(Request $request)
    {
        $coach = $request->user();
        $profile = $coach->coachProfile;

        $request->validate([
            'bio' => 'sometimes|string',
            'expertise' => 'sometimes|array',
            'hourly_rate' => 'sometimes|numeric',
            'availability' => 'sometimes|array'
        ]);

        $profile->update($request->only(['bio', 'expertise', 'hourly_rate', 'availability']));

        return response()->json($profile);
    }
}