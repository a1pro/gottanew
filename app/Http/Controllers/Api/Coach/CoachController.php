<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Core\Profile;
use App\Models\Core\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CoachController extends BaseController
{
    private const LEGAL_VERSION = '2026-03';

    public function index()
    {
        return Coach::where('is_active', true)->get();
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $coach = $user->coachProfile;
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        return $this->success([
            'id' => $coach?->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'full_name' => $profile->full_name ?: $user->name,
            'name' => $coach?->name ?: $user->name,
            'title' => $coach?->title,
            'bio' => $profile->bio ?: $coach?->bio,
            'phone' => $profile->phone ?: $coach?->notification_phone ?: $user->phone,
            'notification_method' => $profile->notification_method,
            'notification_email' => $coach?->notification_email ?: $user->email,
            'specialties' => $coach?->specialties ?? [],
            'timezone' => $coach?->timezone,
            'email_verified' => (bool) ($profile->email_verified || !empty($user->email_verified_at)),
            'created_at' => optional($user->created_at)?->toISOString(),
            'last_login_at' => optional($user->last_login_at)?->toISOString(),
            'legal' => $this->formatLegalPayload($profile),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $coach = $user->coachProfile;
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
            ]
        );

        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notification_method' => ['nullable', 'in:email,whatsapp,both'],
            'title' => ['nullable', 'string', 'max:255'],
            'specialties' => ['nullable', 'array'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'accept_terms' => ['nullable', 'boolean'],
            'accept_privacy_policy' => ['nullable', 'boolean'],
            'accept_coaching_disclaimer' => ['nullable', 'boolean'],
            'acknowledge_coach_independence' => ['nullable', 'boolean'],
        ]);

        $profile->update([
            'full_name' => $validated['full_name'] ?? $profile->full_name,
            'bio' => $validated['bio'] ?? $profile->bio,
            'phone' => $validated['phone'] ?? $profile->phone,
            'notification_method' => $validated['notification_method'] ?? $profile->notification_method,
            'email_verified' => !empty($user->email_verified_at),
            ...$this->resolveLegalUpdates($profile, $validated),
        ]);

        $user->update([
            'name' => $validated['full_name'] ?? $user->name,
            'phone' => $validated['phone'] ?? $user->phone,
        ]);

        if ($coach) {
            $coach->update(array_filter([
                'name' => $validated['full_name'] ?? $coach->name,
                'title' => $validated['title'] ?? $coach->title,
                'bio' => $validated['bio'] ?? $coach->bio,
                'notification_phone' => $validated['phone'] ?? $coach->notification_phone,
                'notification_email' => $user->email,
                'specialties' => $validated['specialties'] ?? $coach->specialties,
                'timezone' => $validated['timezone'] ?? $coach->timezone,
            ], fn ($value) => $value !== null));
        }

        return $this->profile($request);
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
            'accept_terms' => ['required', 'accepted'],
            'accept_privacy_policy' => ['required', 'accepted'],
            'accept_coaching_disclaimer' => ['required', 'accepted'],
            'acknowledge_coach_independence' => ['required', 'accepted'],
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

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $validated['name'],
                    'bio' => $validated['bio'],
                    'notification_method' => 'email',
                    'email_verified' => !empty($user->email_verified_at),
                    'legal_version' => self::LEGAL_VERSION,
                    'terms_accepted_at' => now(),
                    'privacy_policy_accepted_at' => now(),
                    'coaching_disclaimer_accepted_at' => now(),
                    'coach_independence_acknowledged_at' => now(),
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

    private function resolveLegalUpdates(Profile $profile, array $validated): array
    {
        $updates = [
            'legal_version' => self::LEGAL_VERSION,
        ];

        if (($validated['accept_terms'] ?? false) && !$profile->terms_accepted_at) {
            $updates['terms_accepted_at'] = now();
        }

        if (($validated['accept_privacy_policy'] ?? false) && !$profile->privacy_policy_accepted_at) {
            $updates['privacy_policy_accepted_at'] = now();
        }

        if (($validated['accept_coaching_disclaimer'] ?? false) && !$profile->coaching_disclaimer_accepted_at) {
            $updates['coaching_disclaimer_accepted_at'] = now();
        }

        if (($validated['acknowledge_coach_independence'] ?? false) && !$profile->coach_independence_acknowledged_at) {
            $updates['coach_independence_acknowledged_at'] = now();
        }

        return $updates;
    }

    private function formatLegalPayload(Profile $profile): array
    {
        return [
            'version' => $profile->legal_version ?: self::LEGAL_VERSION,
            'terms_accepted_at' => optional($profile->terms_accepted_at)?->toISOString(),
            'privacy_policy_accepted_at' => optional($profile->privacy_policy_accepted_at)?->toISOString(),
            'coaching_disclaimer_accepted_at' => optional($profile->coaching_disclaimer_accepted_at)?->toISOString(),
            'coach_independence_acknowledged_at' => optional($profile->coach_independence_acknowledged_at)?->toISOString(),
        ];
    }
}
