<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use App\Models\Goal\UserGoal;
use App\Http\Controllers\Api\BaseController;

class GoalController extends BaseController
{
    public function index(Request $request)
    {
       $goals = $request->user()
            ->goals()
            ->with('tasks')
            ->latest()
            ->get();

        return $this->success($goals);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
        ]);

        $goal = $request->user()->goals()->create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
            'progress_percentage' => 0,
            'status' => 'active',
        ]);

        return $this->success($goal, 'Goal created');
    }

    public function destroy(Request $request, UserGoal $goal)
    {
        if ((int) $goal->user_id !== (int) $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $goal->delete();

        return $this->success(null, 'Goal deleted');
    }
}