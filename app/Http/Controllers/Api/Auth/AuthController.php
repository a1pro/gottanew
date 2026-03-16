<?php

namespace App\Http\Controllers\Api\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Coach\PendingCoachApplication;

use App\Models\Core\UserRole;
use App\Models\Finance\UserWallet;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Api\BaseController;


class AuthController extends BaseController
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:client'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

          UserRole::create([
                'user_id' => $user->id,
                'role' => $request->role
            ]);

              UserWallet::create([
                    'user_id' => $user->id
                ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token
        ], 'User registered');
    }

   public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $role = UserRole::where('user_id', $user->id)->value('role');

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role
                ]
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $roles = UserRole::where('user_id', $user->id)
            ->pluck('role')
            ->values();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roles,
            'primary_role' => $roles->first(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success([], 'Logged out successfully');
    }

    public function coachApply(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:pending_coach_applications,email',
            'experience' => 'required',
            'specialties' => 'required|array',
            'message' => 'required'
        ]);

        $application = PendingCoachApplication::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'experience' => $request->experience,
            'specialties' => $request->specialties,
            'message' => $request->message,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Application submitted successfully'
        ]);
    }

    public function approveCoach($id)
        {
            $application = PendingCoachApplication::findOrFail($id);

            $user = User::create([
                'name' => $application->name,
                'email' => $application->email,
                'password' => bcrypt(Str::random(10))
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => 'coach'
            ]);

            Coach::create([
                'user_id' => $user->id,
                'name' => $application->name,
                'title' => 'Coach',
                'bio' => $application->message,
                'years_experience' => $application->experience,
                'specialties' => $application->specialties
            ]);

            $application->update([
                'status' => 'approved'
            ]);

            return response()->json([
                'message' => 'Coach approved'
            ]);
        }

        public function setPassword(Request $request)
            {
                $user = User::where('email',$request->email)->firstOrFail();

                $user->password = Hash::make($request->password);
                $user->save();

                return response()->json([
                    'message' => 'Password set successfully'
                ]);
            }
}