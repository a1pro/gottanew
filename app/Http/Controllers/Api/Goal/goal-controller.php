<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoalController extends Controller
{
    /**
     * Get all goals
     */
    public function index()
    {
        try {
            $goals = Goal::all();
            
            return response()->json([
                'success' => true,
                'data' => $goals
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching goals: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get goal by ID
     */
    public function show($id)
    {
        try {
            $goal = Goal::find($id);
            
            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $goal
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching goal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new goal
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255|unique:goals',
                'description' => 'required|string',
                'icon' => 'required|string|max:50',
                'color' => 'required|string|max:50'
            ]);

            $goal = Goal::create($request->all());

            Log::info('Goal created: ' . $goal->id);

            return response()->json([
                'success' => true,
                'message' => 'Goal created successfully',
                'data' => $goal
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating goal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update goal
     */
    public function update(Request $request, $id)
    {
        try {
            $goal = Goal::find($id);
            
            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found'
                ], 404);
            }

            $request->validate([
                'title' => 'sometimes|string|max:255|unique:goals,title,' . $id,
                'description' => 'sometimes|string',
                'icon' => 'sometimes|string|max:50',
                'color' => 'sometimes|string|max:50'
            ]);

            $goal->update($request->all());

            Log::info('Goal updated: ' . $goal->id);

            return response()->json([
                'success' => true,
                'message' => 'Goal updated successfully',
                'data' => $goal
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating goal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete goal
     */
    public function destroy($id)
    {
        try {
            $goal = Goal::find($id);
            
            if (!$goal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Goal not found'
                ], 404);
            }

            $goal->delete();

            Log::info('Goal deleted: ' . $id);

            return response()->json([
                'success' => true,
                'message' => 'Goal deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting goal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
