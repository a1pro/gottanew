<?php
// app/Http/Controllers/Api/Coach/OnboardingController.php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\CoachProfileRequest;
use App\Http\Requests\Coach\CoachAvailabilityRequest;
use App\Http\Requests\Coach\CoachBoundariesRequest;
use App\Models\CoachAvailability;

class OnboardingController extends Controller
{
    public function saveProfile(CoachProfileRequest $request)
    {
        $coach = $request->user();
        $profile = $coach->coachProfile;

        $profile->update($request->only([
            'bio', 'expertise', 'coaching_styles', 'hourly_rate'
        ]));

        return response()->json([
            'message' => 'Profile saved successfully',
            'profile' => $profile
        ]);
    }

    public function saveAvailability(CoachAvailabilityRequest $request)
    {
        $coach = $request->user();

        // Delete existing availability
        CoachAvailability::where('coach_id', $coach->id)->delete();

        // Save new availability
        foreach ($request->availability as $slot) {
            CoachAvailability::create([
                'coach_id' => $coach->id,
                'day_of_week' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'is_available' => true
            ]);
        }

        // Also save as JSON in profile for quick access
        $coach->coachProfile->update([
            'availability_preferences' => $request->availability
        ]);

        return response()->json(['message' => 'Availability saved successfully']);
    }

    public function saveBoundaries(CoachBoundariesRequest $request)
    {
        $coach = $request->user();
        $profile = $coach->coachProfile;

        $profile->update([
            'boundaries' => $request->boundaries,
            'ethics_acknowledged' => true,
            'ethics_acknowledged_at' => now(),
            'onboarding_completed' => true,
            'is_approved' => true
        ]);

        return response()->json([
            'message' => 'Onboarding completed successfully',
            'onboarding_completed' => true
        ]);
    }

    public function getAvailability()
    {
        $coach = auth()->user();
        
        $availability = CoachAvailability::where('coach_id', $coach->id)
            ->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return response()->json($availability);
    }

    public function getStatus()
    {
        $coach = auth()->user();
        $profile = $coach->coachProfile;

        $steps = [
            'profile_completed' => !empty($profile->bio) && !empty($profile->expertise),
            'availability_completed' => !empty($profile->availability_preferences),
            'boundaries_completed' => $profile->ethics_acknowledged,
            'onboarding_completed' => $profile->onboarding_completed
        ];

        return response()->json([
            'steps' => $steps,
            'profile' => $profile
        ]);
    }
}