<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Coach\PendingCoachApplication;
use App\Models\Core\Profile;
use App\Models\Core\UserRole;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:client',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $request->role,
        ]);

        UserWallet::create([
            'user_id' => $user->id,
        ]);

        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
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
                    'role' => $role,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $roles = UserRole::where('user_id', $user->id)
            ->pluck('role')
            ->values();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roles,
            'primary_role' => $roles->first(),
            'full_name' => $profile->full_name ?: $user->name,
            'bio' => $profile->bio,
            'phone' => $profile->phone ?: $user->phone,
            'notification_method' => $profile->notification_method,
            'email_verified' => (bool) ($profile->email_verified || !empty($user->email_verified_at)),
            'created_at' => optional($user->created_at)?->toISOString(),
            'last_login_at' => optional($user->last_login_at)?->toISOString(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notification_method' => ['nullable', 'in:email,whatsapp,both'],
        ]);

        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $profile->update([
            'full_name' => $validated['full_name'] ?? $profile->full_name,
            'bio' => $validated['bio'] ?? $profile->bio,
            'phone' => $validated['phone'] ?? $profile->phone,
            'notification_method' => $validated['notification_method'] ?? $profile->notification_method,
            'email_verified' => !empty($user->email_verified_at),
        ]);

        if (!empty($validated['full_name'])) {
            $user->update([
                'name' => $validated['full_name'],
                'phone' => $validated['phone'] ?? $user->phone,
            ]);
        } elseif (array_key_exists('phone', $validated)) {
            $user->update([
                'phone' => $validated['phone'],
            ]);
        }

        return $this->success($this->me($request)->getData(true)['data'], 'Profile updated');
    }

    public function deleteTranscripts(Request $request)
    {
        $user = $request->user();

        $sessionIds = CoachingSession::query()
            ->where('client_id', $user->id)
            ->pluck('id');

        SessionRecording::query()
            ->whereIn('session_id', $sessionIds)
            ->update([
                'transcript' => null,
                'ai_summary' => null,
                'sentiment_analysis' => null,
                'key_topics' => null,
                'personality_insights' => null,
                'emotional_journey' => null,
            ]);

        return $this->success([], 'Transcripts deleted successfully');
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
            'message' => 'required',
        ]);

        PendingCoachApplication::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'experience' => $request->experience,
            'specialties' => $request->specialties,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Application submitted successfully',
        ]);
    }

    public function approveCoach($id)
    {
        $application = PendingCoachApplication::findOrFail($id);

        $user = User::create([
            'name' => $application->name,
            'email' => $application->email,
            'password' => bcrypt(Str::random(10)),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => 'coach',
        ]);

        Coach::create([
            'user_id' => $user->id,
            'name' => $application->name,
            'title' => 'Coach',
            'bio' => $application->message,
            'years_experience' => $application->experience,
            'specialties' => $application->specialties,
        ]);

        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $application->update([
            'status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Coach approved',
        ]);
    }

    public function setPassword(Request $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password set successfully',
        ]);
    }
}
