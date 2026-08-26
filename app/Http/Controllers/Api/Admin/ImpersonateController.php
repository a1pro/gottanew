<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Core\UserRole;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    /**
     * Get all users for admin dashboard
     */
    public function getUsers(Request $request)
    {
        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $users = User::with(['roles', 'coachProfile'])
            ->withCount(['clientSessions'])
            ->get()
            ->map(function ($user) {
                $roles = $user->roles->pluck('role')->values();
                $primaryRole = $roles->first();
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'primary_role' => $primaryRole,
                    'is_active' => (bool) $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'sessions_count' => $user->clientSessions->count(),
                    'created_at' => $user->created_at,
                    'is_impersonating' => !is_null($user->impersonated_by),
                    'has_coach_profile' => $user->coachProfile ? true : false,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Start impersonating a user
     */
    public function impersonate(Request $request, $userId)
    {
        $admin = $request->user();

        // Verify admin
        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Prevent self-impersonation
        if ($admin->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate yourself.'
            ], 400);
        }

        // Prevent impersonating other admins
        if ($targetUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate other administrators.'
            ], 403);
        }

        // Clear any existing impersonation session for this admin
        User::where('impersonated_by', $admin->id)->update(['impersonated_by' => null]);

        // Set impersonation
        $targetUser->impersonated_by = $admin->id;
        $targetUser->save();

        // Get user roles
        $roles = UserRole::where('user_id', $targetUser->id)
            ->pluck('role')
            ->values();

        // Delete old tokens and create new one for impersonated user
        $targetUser->tokens()->delete();
        $token = $targetUser->createToken('impersonation_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Now impersonating ' . $targetUser->name,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'roles' => $roles,
                    'primary_role' => $roles->first(),
                    'is_impersonating' => true,
                    'impersonated_by' => [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                    ],
                ],
                'impersonation' => [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'user_id' => $targetUser->id,
                    'user_name' => $targetUser->name,
                ]
            ]
        ]);
    }

    /**
     * Stop impersonating and return to admin
     */
    public function stopImpersonating(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->impersonated_by) {
            return response()->json([
                'success' => false,
                'message' => 'Not currently impersonating anyone.'
            ], 400);
        }

        $admin = User::find($user->impersonated_by);

        if (!$admin) {
            // Admin user no longer exists, just clear the impersonation
            $user->impersonated_by = null;
            $user->save();
            
            return response()->json([
                'success' => false,
                'message' => 'Impersonating admin not found. Session cleared.'
            ], 400);
        }

        // Clear impersonation
        $user->impersonated_by = null;
        $user->save();

        // Delete old tokens and create new one for admin
        $admin->tokens()->delete();
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Get admin roles
        $roles = UserRole::where('user_id', $admin->id)
            ->pluck('role')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Stopped impersonating successfully.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'roles' => $roles,
                    'primary_role' => $roles->first(),
                    'is_impersonating' => false,
                    'impersonated_by' => null,
                ]
            ]
        ]);
    }

    /**
     * Get current impersonation status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->impersonated_by) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_impersonating' => false,
                ]
            ]);
        }

        $admin = User::find($user->impersonated_by);

        return response()->json([
            'success' => true,
            'data' => [
                'is_impersonating' => true,
                'admin_id' => $user->impersonated_by,
                'admin_name' => $admin ? $admin->name : 'Unknown Admin',
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]
        ]);
    }
}
