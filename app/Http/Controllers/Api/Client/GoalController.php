<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use App\Models\Goal\UserGoal;
use App\Http\Controllers\Api\BaseController;

/**
 * Client's own goals (user_goals table).
 * Used for BOTH:
 *  - selecting a predefined canonical goal (category = one of the 6 goal_id slugs)
 *  - creating a fully custom goal (category still must be one of the 6 slugs,
 *    title/description are the client's own words)
 *
 * "category" is always validated against the canonical `goals` table so
 * downstream AI matching / coach matching always has a known category to work with.
 */
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
            'title'       => ['nullable', 'string', 'max:255'],
            // must match an existing canonical goal_id slug (health-fitness, career-development, etc.)
            'category'    => ['required', 'string', 'exists:goals,goal_id'],
            'description' => ['required', 'string', 'max:300'],
            'target_date' => ['nullable', 'date'],
        ]);

        $goal = $request->user()->goals()->create([
            'title'                => $validated['title'] ?? 'Custom Goal',
            'category'             => $validated['category'],
            'description'          => $validated['description'],
            'target_date'          => $validated['target_date'] ?? null,
            'progress_percentage'  => 0,
            'status'               => 'active',
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
