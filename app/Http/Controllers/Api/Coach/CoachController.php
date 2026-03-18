<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Core\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CoachController extends BaseController
{
    public function index()
    {
        return Coach::where('is_active', true)->get();
    }

    public function profile(Request $request)
    {
        return $this->success(
            $request->user()->coachProfile
        );
    }

    public function update(Request $request)
    {
        $coach = $request->user()->coachProfile;
        $coach->update($request->all());

        return $this->success($coach, 'Profile updated');
    }

    public function show($id)
    {
        $coach = Coach::findOrFail($id);

        return $this->success($coach);
    }

    public function invitation(string $token)
    {
        $resetRows = DB::table('password_reset_tokens')->get();
        $match = $resetRows->first(function ($row) use ($token) {
            return Hash::check($token, $row->token);
        });

        if (!$match) {
            return response()->json([
                'is_valid' => false,
                'email' => null,
                'expires_at' => null,
                'used_at' => null,
            ]);
        }

        $createdAt = Carbon::parse($match->created_at);
        $expiresAt = $createdAt->copy()->addDays(7);

        return response()->json([
            'is_valid' => now()->lt($expiresAt),
            'email' => $match->email,
            'expires_at' => $expiresAt->toISOString(),
            'used_at' => null,
        ]);
    }

    public function completeOnboarding(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'bio' => ['required', 'string'],
            'years_experience' => ['required', 'integer', 'min:1'],
            'specialties' => ['nullable', 'array'],
            'hourly_rate_amount' => ['required', 'numeric', 'min:0'],
            'timezone' => ['required', 'string', 'max:100'],
        ]);

        $resetRows = DB::table('password_reset_tokens')->get();
        $match = $resetRows->first(function ($row) use ($validated) {
            return Hash::check($validated['token'], $row->token);
        });

        if (!$match) {
            return $this->error('Invalid invitation token', 422);
        }

        $createdAt = Carbon::parse($match->created_at);
        if (now()->gte($createdAt->copy()->addDays(7))) {
            return $this->error('Invitation token expired', 422);
        }

        $user = User::where('email', $match->email)->firstOrFail();

        DB::beginTransaction();

        try {
            $user->update([
                'name' => $validated['name'],
                'password' => Hash::make($validated['password']),
            ]);

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            $coach = Coach::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $validated['name'],
                    'title' => $validated['title'],
                    'bio' => $validated['bio'],
                    'years_experience' => $validated['years_experience'],
                    'specialties' => $validated['specialties'] ?? [],
                    'similar_experiences' => [],
                    'timezone' => $validated['timezone'],
                    'notification_email' => $user->email,
                    'hourly_rate_amount' => $validated['hourly_rate_amount'],
                    'hourly_rate_currency' => 'USD',
                    'hourly_coin_cost' => 4,
                    'booking_buffer_minutes' => 0,
                    'max_session_duration' => 15,
                    'min_session_duration' => 15,
                    'immediate_availability' => false,
                    'available_now' => false,
                    'is_active' => true,
                    'response_preference_minutes' => 5,
                ]
            );

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $authToken = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $this->success([
                'token' => $authToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'coach',
                ],
                'coach' => $coach,
            ], 'Coach onboarding completed');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete onboarding',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}