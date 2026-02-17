<?php
// app/Http/Controllers/Api/Auth/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:client,coach' // Frontend sends which role they want
        ]);

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign role
        $role = Role::where('slug', $request->role)->first();
        $user->roles()->attach($role);

        // Create profile based on role
        if ($request->role === 'coach') {
            $user->coachProfile()->create([]);
        } else {
            $user->clientProfile()->create([]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->primary_role
            ],
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        
        if (!$user->is_active) {
            return response()->json(['message' => 'Account deactivated'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $user->tokens()->delete();
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->primary_role
            ],
            'token' => $token
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles');
        
        // Load profile based on role
        if ($user->isCoach()) {
            $user->load('coachProfile');
        } else if ($user->isClient()) {
            $user->load('clientProfile');
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->primary_role,
            'roles' => $user->roles->pluck('slug'),
            'profile' => $user->isCoach() ? $user->coachProfile : $user->clientProfile
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}