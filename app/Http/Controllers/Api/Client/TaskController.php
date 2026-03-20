<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use App\Models\Goal\UserGoal;
use App\Models\Goal\UserTask;
use App\Http\Controllers\Api\BaseController;

class TaskController extends BaseController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goal_id' => ['required', 'exists:user_goals,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
        ]);

        $goal = UserGoal::findOrFail($validated['goal_id']);

        if ((int) $goal->user_id !== (int) $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $task = UserTask::create([
            'user_id' => $request->user()->id,
            'goal_id' => $goal->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'due_date' => $validated['due_date'] ?? null,
            'is_completed' => false,
        ]);

        return $this->success($task, 'Task created');
    }

    public function update(Request $request, UserTask $task)
    {
        if ((int) $task->user_id !== (int) $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'is_completed' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('is_completed', $validated)) {
            $validated['completed_at'] = $validated['is_completed'] ? now() : null;
        }

        $task->update($validated);

        return $this->success($task->fresh(), 'Task updated');
    }

    public function destroy(Request $request, UserTask $task)
    {
        if ((int) $task->user_id !== (int) $request->user()->id) {
            return $this->error('Unauthorized', 403);
        }

        $task->delete();

        return $this->success(null, 'Task deleted');
    }
}