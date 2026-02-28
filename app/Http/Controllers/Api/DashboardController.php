<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserGoal;
use App\Models\UserTask;
use App\Models\Session;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $goals = UserGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->get();

        $tasks = UserTask::where('user_id', $user->id)
            ->latest()
            ->get();

        $upcomingSessions = Session::with('coach')
            ->where('client_id', $user->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_time', '>=', now())
            ->orderBy('scheduled_time')
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'activeGoals' => $goals->count(),
                'avgProgress' => $goals->count() > 0 ? round($goals->avg('progress_percentage')) : 0,
                'sessionsThisMonth' => Session::where('client_id', $user->id)
                    ->whereMonth('scheduled_time', Carbon::now()->month)
                    ->count(),
                'completedTasks' => $tasks->where('is_completed', true)->count(),
            ],
            'goals' => $goals,
            'tasks' => $tasks,
            'upcomingSessions' => $upcomingSessions,
        ]);
    }

    public function storeGoal(Request $request)
    {
        $goal = UserGoal::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'category' => $request->category ?? 'personal',
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        return response()->json($goal);
    }

    public function deleteGoal($id)
    {
        UserGoal::where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function storeTask(Request $request)
    {
        $task = UserTask::create([
            'user_id' => $request->user()->id,
            'goal_id' => $request->goal_id,
            'title' => $request->title,
            'is_completed' => false,
        ]);

        return response()->json($task);
    }

    public function updateTask(Request $request, $id)
    {
        $task = UserTask::findOrFail($id);

        $task->update([
            'is_completed' => $request->is_completed,
            'completed_at' => $request->is_completed ? now() : null,
        ]);

        return response()->json($task);
    }

    public function deleteTask($id)
    {
        UserTask::where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}