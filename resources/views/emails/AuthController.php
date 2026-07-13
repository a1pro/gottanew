<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Mail\ResetPasswordMail;
use App\Mail\CoachApplicationReceived;
use App\Mail\ClientApplicationCreated;
use App\Models\Coach\Coach;
use App\Models\Coach\PendingCoachApplication;
use App\Models\Core\Profile;
use App\Models\Core\UserRole;
use App\Models\Finance\UserWallet;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionRecording;
use App\Models\Session\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    private const LEGAL_VERSION = '2026-03';

   public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:client,coach',
            'accept_terms' => 'required|accepted',
            'accept_privacy_policy' => 'required|accepted',
            'accept_coaching_disclaimer' => 'required|accepted',
            'acknowledge_coach_independence' =>
            'required_if:role,coach|accepted',
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

        if ($request->role == 'client') {

            UserWallet::create([
                'user_id' => $user->id,
            ]);

        }

        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'notification_method' => 'email',
                'email_verified' => !empty($user->email_verified_at),
                ...$this->legalAcceptanceAttributes(),
            ]
        );

        if ($request->role == 'coach') {

            Coach::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'title' => '',
                'bio' => '',
                'years_experience' => 1,
                'specialties' => [],
                'similar_experiences' => [],
                'notification_email' => $user->email,

                'timezone' => 'UTC',

                // Client requirement
                'is_active' => false,
                'available_now' => false,
                'status' => 'pending_review',
                'is_verified' => false,
            ]);

        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'User registered');
    }

    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required',
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         return response()->json(['message' => 'Invalid credentials'], 401);
    //     }

    //     $token = $user->createToken('auth_token')->plainTextToken;
    //     $role = UserRole::where('user_id', $user->id)->value('role');

    //     return response()->json([
    //         'data' => [
    //             'token' => $token,
    //             'user' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'role' => $role,
    //             ],
    //         ],
    //     ]);
    // }
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 1. Check user
        $user = User::where('email', $request->email)->first();

        // 2. If user exists → normal login
        if ($user) {

            if (!Hash::check($request->password, $user->password)) {
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
                        'status' => 'active'
                    ],
                ],
            ]);
        }

        // 3. If NOT user → check pending coach
        $application = PendingCoachApplication::where('email', $request->email)->first();

        if ($application) {

            if ($application->status === 'pending') {
                return response()->json([
                    'message' => 'Your coach application is still under review. Please wait for admin approval.'
                ], 403);
            }

            if ($application->status === 'rejected') {
                return response()->json([
                    'message' => 'Your coach application was rejected.'
                ], 403);
            }
        }

        // 4. If nothing found
        return response()->json(['message' => 'Invalid credentials'], 401);
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
            'legal' => $this->formatLegalPayload($profile),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notification_method' => ['nullable', 'in:email'],
            'accept_terms' => ['nullable', 'boolean'],
            'accept_privacy_policy' => ['nullable', 'boolean'],
            'accept_coaching_disclaimer' => ['nullable', 'boolean'],
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
            ...$this->resolveLegalUpdates($profile, $validated),
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
public function forgotPassword(Request $request)
    {
        
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        PasswordResetToken::where('email', $validated['email'])->delete();

        $plainToken = hash('sha256', Str::random(64));

        PasswordResetToken::create([
            'email' => $validated['email'],
            'token' => $plainToken,
            'created_at' => now(),
        ]);
       
        Mail::to($validated['email'])
            ->send(new ResetPasswordMail(
                $validated['email'],
                $plainToken
            ));

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent successfully.'
        ]);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $record = PasswordResetToken::where('email', $request->email)->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }
      
        if (trim($request->token) !== trim($record->token)) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        PasswordResetToken::where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password reset successful'
        ]);
    }
    public function coachApply(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:pending_coach_applications,email|unique:users,email',
            // 'password' => 'required|min:6',
            'phone' => 'nullable|string',
            'experience' => 'required|string',
            'specialties' => 'required|array',
            'message' => 'required|string',
            'accept_terms' => 'required|accepted',
            'accept_privacy_policy' => 'required|accepted',
            'accept_coaching_disclaimer' => 'required|accepted',
            'acknowledge_coach_independence' => 'required|accepted',
        ]);

        $application = PendingCoachApplication::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            // 'password' => Hash::make($request->password),
            'experience' => $request->experience,
            'specialties' => $request->specialties,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        Mail::to($request->email)->send(new CoachApplicationReceived($request->name));
        
         return response()->json([
            'message' => 'Your coach application has been sent to admin for approval.',
        ]);
    }

    public function approveCoach($id)
    {
        try {
            DB::beginTransaction();
            
            $application = PendingCoachApplication::findOrFail($id);
            
            // Check if user already exists
            $existingUser = User::where('email', $application->email)->first();
            
            if ($existingUser) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'User with this email already exists',
                ], 400);
            }
            
            // IMPORTANT: Use the EXACT password from application (already hashed)
            $user = User::create([
                'name' => $application->name,
                'email' => $application->email,
                'password' => $application->password, // Use existing hash
                'phone' => $application->phone,
                'is_active' => true,
            ]);
            
            // Assign coach role
            UserRole::create([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);
            
            // Create coach profile
            Coach::create([
                'user_id' => $user->id,
                'name' => $application->name,
                'title' => 'Coach',
                'bio' => $application->message ?: 'Coach profile',
                'years_experience' => (int) filter_var($application->experience, FILTER_SANITIZE_NUMBER_INT) ?: 1,
                'specialties' => $application->specialties ?? [],
                'similar_experiences' => [],
                'notification_email' => $user->email,
                'timezone' => 'UTC',
                'is_active' => true,
                'available_now' => false,
                'hourly_rate_amount' => 100.00,
                'hourly_rate_currency' => 'USD',
                'hourly_coin_cost' => 100,
                'booking_buffer_minutes' => 15,
                'max_session_duration' => 60,
                'min_session_duration' => 30,
                'immediate_availability' => true,
                'response_preference_minutes' => 5,
            ]);
            
            // Create profile
            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $user->name,
                    'notification_method' => 'email',
                    'email_verified' => false,
                ]
            );
            
            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Coach approved successfully. They can now login with their password.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approval failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve coach: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password set successfully',
        ]);
    }

    private function legalAcceptanceAttributes(): array
    {
        $now = now();

        return [
            'legal_version' => self::LEGAL_VERSION,
            'terms_accepted_at' => $now,
            'privacy_policy_accepted_at' => $now,
            'coaching_disclaimer_accepted_at' => $now,
        ];
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