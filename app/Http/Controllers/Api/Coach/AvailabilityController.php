<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coach\CoachAvailability;

class AvailabilityController extends Controller
{
    public function index()
    {
        return CoachAvailability::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'coach_id' => 'required|exists:coaches,id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required',
            'end_time' => 'required',
            'timezone' => 'nullable|string'
        ]);

        $availability = CoachAvailability::create($data);

        return response()->json($availability, 201);
    }

    public function show($id)
    {
        return CoachAvailability::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $availability = CoachAvailability::findOrFail($id);

        $availability->update($request->all());

        return response()->json($availability);
    }

    public function destroy($id)
    {
        $availability = CoachAvailability::findOrFail($id);
        $availability->delete();

        return response()->json([
            'message' => 'Availability deleted'
        ]);
    }
}