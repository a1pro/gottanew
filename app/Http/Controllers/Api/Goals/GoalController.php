<?php

namespace App\Http\Controllers\Api\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * List all active goals
     */
    public function index()
    {
        $goals = Goal::where('is_active', true)
            ->select('id', 'goal_id', 'title', 'description', 'icon', 'color')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $goals
        ]);
    }

    /**
     * Show single goal
     */
    public function show($id)
    {
        $goal = Goal::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $goal
        ]);
    }

    /**
     * Store new goal (Admin use)
     */
    public function store(Request $request)
    {
        $request->validate([
            'goal_id' => 'required|unique:goals',
            'title' => 'required',
            'description' => 'nullable',
            'icon' => 'nullable',
            'color' => 'nullable'
        ]);

        $goal = Goal::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Goal created',
            'data' => $goal
        ]);
    }

    /**
     * Update goal
     */
    public function update(Request $request, $id)
    {
        $goal = Goal::findOrFail($id);

        $goal->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Goal updated',
            'data' => $goal
        ]);
    }

    /**
     * Delete goal
     */
    public function destroy($id)
    {
        $goal = Goal::findOrFail($id);

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted'
        ]);
    }
}