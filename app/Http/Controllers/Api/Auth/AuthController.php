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

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
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
                'roles' => $user->role_slugs,
                'primary_role' => $user->primary_role
            ],
            'token' => $token
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid password'], 401);
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
                'roles' => $user->role_slugs,
                'primary_role' => $user->primary_role
            ],
            'token' => $token
        ]);
    }

    public function me()
    {
        $user = auth()->user();
        $user->load('roles');
        
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
            'roles' => $user->role_slugs,
            'primary_role' => $user->primary_role,
            'profile' => $user->isCoach() ? $user->coachProfile : $user->clientProfile
        ]);
    }

    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}