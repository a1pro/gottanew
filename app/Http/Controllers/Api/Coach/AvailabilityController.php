<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Coach\CoachAvailability;
use App\Services\Coach\CoachAvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends BaseController
{
    public function __construct(private CoachAvailabilityService $availabilityService)
    {
    }

    public function publicIndex($coachId)
    {
        $coach = Coach::findOrFail($coachId);

        return $this->success(
            $this->availabilityService->publicAvailabilityPayload($coach)
        );
    }

    public function slots(Request $request, $coachId)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'viewer_timezone' => ['nullable', 'timezone'],
        ]);

        $coach = Coach::findOrFail($coachId);
        $viewerTimezone = $validated['viewer_timezone'] ?? 'UTC';

        return $this->success([
            'date' => $validated['date'],
            'viewer_timezone' => $viewerTimezone,
            'coach_timezone' => $coach->timezone ?: 'UTC',
            'slots' => $this->availabilityService->buildSlotsForDate(
                $coach,
                $validated['date'],
                $viewerTimezone
            ),
        ]);
    }

    public function index(Request $request)
    {
        $coach = $this->getAuthenticatedCoach($request);

        return $this->success(
            $this->availabilityService->publicAvailabilityPayload($coach)
        );
    }

    public function store(Request $request)
    {
        $coach = $this->getAuthenticatedCoach($request);

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['end_time'] <= $validated['start_time']) {
            return $this->error('End time must be after start time.', 422);
        }

        if ($this->availabilityService->hasAvailabilityOverlap(
            $coach->id,
            (int) $validated['day_of_week'],
            $validated['start_time'] . ':00',
            $validated['end_time'] . ':00'
        )) {
            return $this->error('This availability block overlaps an existing block.', 422);
        }

        $availability = CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => (int) $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'timezone' => $validated['timezone'] ?? ($coach->timezone ?: 'UTC'),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($availability, 'Availability saved', 201);
    }

    public function update(Request $request, $id)
    {
        $coach = $this->getAuthenticatedCoach($request);
        $availability = CoachAvailability::where('coach_id', $coach->id)->findOrFail($id);

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['end_time'] <= $validated['start_time']) {
            return $this->error('End time must be after start time.', 422);
        }

        if ($this->availabilityService->hasAvailabilityOverlap(
            $coach->id,
            (int) $validated['day_of_week'],
            $validated['start_time'] . ':00',
            $validated['end_time'] . ':00',
            $availability->id
        )) {
            return $this->error('This availability block overlaps an existing block.', 422);
        }

        $availability->update([
            'day_of_week' => (int) $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'timezone' => $validated['timezone'] ?? ($coach->timezone ?: 'UTC'),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($availability->fresh(), 'Availability updated');
    }

    public function destroy(Request $request, $id)
    {
        $coach = $this->getAuthenticatedCoach($request);
        $availability = CoachAvailability::where('coach_id', $coach->id)->findOrFail($id);
        $availability->delete();

        return $this->success([], 'Availability deleted');
    }

    private function getAuthenticatedCoach(Request $request): Coach
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $coach = $user->coachProfile;
        abort_unless($coach, 403, 'Coach profile not found');

        return $coach;
    }
}