<?php
// app/Http/Controllers/Api/Admin/AdminController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\CoachingSession;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_coaches' => User::whereHas('roles', fn($q) => $q->where('slug', 'coach'))->count(),
            'total_clients' => User::whereHas('roles', fn($q) => $q->where('slug', 'client'))->count(),
            'total_sessions' => CoachingSession::count(),
            'active_sessions' => CoachingSession::where('status', 'live')->count()
        ]);
    }

    public function users()
    {
        $users = User::with('roles')
            ->with(['coachProfile', 'clientProfile'])
            ->paginate(15);

        return response()->json($users);
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => 'User status updated',
            'is_active' => $user->is_active
        ]);
    }

    public function assignRole(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($id);
        $user->roles()->syncWithoutDetaching([$request->role_id]);

        return response()->json(['message' => 'Role assigned successfully']);
    }

    public function removeRole($id, $roleId)
    {
        $user = User::findOrFail($id);
        $user->roles()->detach($roleId);

        return response()->json(['message' => 'Role removed successfully']);
    }
}