<?php
// app/Http/Controllers/Api/Auth/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'is_active' => true
            ]);

            // Assign role
            $role = Role::where('slug', $request->role)->first();
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 400);
            }
            
            $user->roles()->attach($role->id);

            // Create profile based on role
            if ($request->role === 'coach') {
                $user->coachProfile()->create([
                    'hourly_rate' => 100,
                    'onboarding_completed' => false
                ]);
            } else {
                $user->clientProfile()->create([
                    'questionnaire_completed' => false
                ]);
            }

            // Load relationships
            $user->load('roles');
            
            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->pluck('slug'),
                    'primary_role' => $user->primary_role,
                    'created_at' => $user->created_at,
                ],
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user = Auth::user();
            
            // Check if user is active
            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.'
                ], 403);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Delete existing tokens
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Load relationships
            $user->load('roles');
            
            if ($user->isCoach()) {
                $user->load('coachProfile');
            } else if ($user->isClient()) {
                $user->load('clientProfile');
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->pluck('slug'),
                    'primary_role' => $user->primary_role,
                    'profile' => $user->isCoach() ? $user->coachProfile : $user->clientProfile,
                    'last_login_at' => $user->last_login_at,
                ],
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $user->load('roles');
            
            if ($user->isCoach()) {
                $user->load('coachProfile');
            } else if ($user->isClient()) {
                $user->load('clientProfile');
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->pluck('slug'),
                    'primary_role' => $user->primary_role,
                    'profile' => $user->isCoach() ? $user->coachProfile : $user->clientProfile,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if ($user) {
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Delete old token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}