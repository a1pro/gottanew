<?php
// app/Http/Controllers/Api/Admin/AdminController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\CoachApplicationApproved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Role;
use App\Models\CoachingSession;

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

    /**
     * Get all users with coach role
     */
public function getAllCoaches(Request $request)
{
    $perPage = $request->get('per_page', 10); // Default to 10 items per page
    
    $coaches = User::where('role', 'coach')
        ->with(['coachProfile', 'roles', 'coachApplication'])
        ->orderBy('created_at', 'desc') // Order by latest first
        ->paginate($perPage);
    
    // Transform the paginated data
    $transformedCoaches = collect($coaches->items())->map(function ($coach) {
        return [
            'id' => $coach->id,
            'name' => $coach->name,
            'email' => $coach->email,
            'phone' => $coach->phone,
            'avatar' => $coach->avatar,
            'is_active' => $coach->is_active,
            'is_approved' => $coach->is_approved,
            'approved_at' => $coach->approved_at,
            'created_at' => $coach->created_at,
            'coach_profile' => $coach->coachProfile,
            'roles' => $coach->roles,
            'coach_application' => $coach->coachApplication ? [
                'id' => $coach->coachApplication->id,
                'experience' => $coach->coachApplication->experience,
                'specialties' => $coach->coachApplication->specialties,
                'reason' => $coach->coachApplication->reason,
                'certification' => $coach->coachApplication->certification,
                'status' => $coach->coachApplication->status,
                'admin_notes' => $coach->coachApplication->admin_notes,
                'reviewed_at' => $coach->coachApplication->reviewed_at,
                'reviewed_by' => $coach->coachApplication->reviewed_by,
                'created_at' => $coach->coachApplication->created_at,
                'updated_at' => $coach->coachApplication->updated_at,
            ] : null
        ];
    });

    return response()->json([
        'success' => true,
        'coaches' => $transformedCoaches,
        'pagination' => [
            'current_page' => $coaches->currentPage(),
            'per_page' => $coaches->perPage(),
            'total' => $coaches->total(),
            'last_page' => $coaches->lastPage(),
            'next_page_url' => $coaches->nextPageUrl(),
            'prev_page_url' => $coaches->previousPageUrl(),
            'from' => $coaches->firstItem(),
            'to' => $coaches->lastItem(),
        ],
        'total_coaches' => $coaches->total()
    ]);
}

    /**
     * Approve a coach (set is_approved = 1)
     */
    public function approveCoach($id)
    {
        // die('test');
        $user = User::where('role', 'coach')->findOrFail($id);
        
        if ($user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Coach is already approved'
            ], 400);
        }

        $user->update([
            'is_approved' => true,
            'approved_at' => now()
        ]);

        // Log approval action
        \Log::info('Coach approved', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'approved_at' => now()
        ]);

        // Send approval email
        Mail::to($user->email)->send(new CoachApplicationApproved($user));

        // Log email sent
        \Log::info('Coach approval email sent', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'sent_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coach approved successfully',
            'coach' => $user->load(['coachProfile', 'roles'])
        ]);
    }
}