<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;
use App\Models\Core\Profile;
use App\Models\Core\UserRole;
use App\Models\User;
use App\Rules\TimezoneIdentifier;
use App\Support\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\CoachInformationRequest;

class CoachController extends BaseController
{
    private const LEGAL_VERSION = '2026-03';

    public function index()
    {
        $coaches = Coach::with('user.profile')
            ->where('is_active', true)
            ->get()
            ->map(function ($coach) {
    
                $coach->profile_image = $coach->user?->profile?->profile_image
                    ? asset(Storage::url($coach->user->profile->profile_image))
                    : null;
    
                return $coach;
            });
    
        return $this->success($coaches);
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
            'profile_image' => $profile->profile_image
                               ? asset(Storage::url($profile->profile_image))
                               : null,
            'notification_method' => $profile->notification_method,
            'notification_email' => $coach?->notification_email ?: $user->email,
            'specialties' => $coach?->specialties ?? [],
            'years_experience' => $coach?->years_experience,
            'hourly_rate_amount' => $coach?->hourly_rate_amount,
            
            'qualifications' => $coach?->qualifications,
            'expertise_areas' => $coach?->expertise_areas ?? [],
            'coaching_philosophy' => $coach?->coaching_philosophy,
            'interests_and_personality' => $coach?->interests_and_personality,
            'preferred_client_types' => $coach?->preferred_client_types ?? [],
            'industries' => $coach?->industries ?? [],
            'preferred_challenges' => $coach?->preferred_challenges,
            
            'coaching_style' => $coach?->coaching_style,
            
            'website' => $coach?->website,
            'social_links' => $coach?->social_links ?? [],
            'languages' => $coach?->languages ?? [],
            
            'community_involvement' => $coach?->community_involvement,
            'similar_experiences' => $coach?->similar_experiences ?? [],
            'timezone' => $coach?->timezone,
            'is_active' => (bool) ($coach?->is_active ?? false),
            'available_now' => (bool) ($coach?->available_now ?? false),
            'immediate_availability' => (bool) ($coach?->immediate_availability ?? false),
            'response_preference_minutes' => (int) ($coach?->response_preference_minutes ?? 5),
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
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'notification_method' => ['nullable', 'in:email'],
            'title' => ['nullable', 'string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0'],
            'hourly_rate_amount' => ['nullable', 'numeric', 'min:0'],
            
            'qualifications' => ['nullable', 'string'],
            'expertise_areas' => ['nullable', 'array'],
            'coaching_philosophy' => ['nullable', 'string'],
            'interests_and_personality' => ['nullable', 'string'],
            
            'preferred_client_types' => ['nullable', 'array'],
            'industries' => ['nullable', 'array'],
            'preferred_challenges' => ['nullable', 'string'],
            
            'coaching_style' => ['nullable', 'string'],
            
            'website' => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url'],
            'social_links.twitter' => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.facebook' => ['nullable', 'url'],
            'languages' => ['nullable', 'array'],
            
            'community_involvement' => ['nullable', 'string'],
            'similar_experiences' => ['nullable', 'array'],
            'specialties' => ['nullable', 'array'],
            'timezone' => ['nullable', 'string', 'max:100', new TimezoneIdentifier()],
            'available_now' => ['nullable', 'boolean'],
            'immediate_availability' => ['nullable', 'boolean'],
            'response_preference_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'accept_terms' => ['nullable', 'boolean'],
            'accept_privacy_policy' => ['nullable', 'boolean'],
            'accept_coaching_disclaimer' => ['nullable', 'boolean'],
            'acknowledge_coach_independence' => ['nullable', 'boolean'],
        ]);



        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile-images', 'public');
        
            $validated['profile_image'] = $path;
        }

        $profile->update([
            'full_name' => $validated['full_name'] ?? $profile->full_name,
            'bio' => $validated['bio'] ?? $profile->bio,
            'phone' => $validated['phone'] ?? $profile->phone,
            'profile_image' => $validated['profile_image'] ?? $profile->profile_image,
            'notification_method' => $validated['notification_method'] ?? $profile->notification_method,
            'email_verified' => !empty($user->email_verified_at),
            ...$this->resolveLegalUpdates($profile, $validated),
        ]);

        $user->update([
            'name' => $validated['full_name'] ?? $user->name,
            'phone' => $validated['phone'] ?? $user->phone,
        ]);

        $keepOldIfEmpty = function ($field) use ($validated, $coach) {
            if (!array_key_exists($field, $validated)) {
                return $coach->{$field};
            }
        
            $value = $validated[$field];
        
            if (is_array($value) && empty($value)) {
                return $coach->{$field};
            }
        
            if (is_string($value) && trim($value) === '') {
                return $coach->{$field};
            }
        
            return $value;
        };

        // Keep old social links when frontend sends empty strings
        $keepOldSocialLinks = function () use ($validated, $coach) {
        
            $oldLinks = $coach->social_links ?? [];
        
            $newLinks = $validated['social_links'] ?? [];
        
            foreach ($newLinks as $key => $value) {
        
                if ($value !== null && trim($value) !== '') {
                    $oldLinks[$key] = $value;
                }
        
            }
        
            return $oldLinks;
        };


        if ($coach) {
            $immediateAvailability = array_key_exists('immediate_availability', $validated)
                ? (bool) $validated['immediate_availability']
                : (bool) $coach->immediate_availability;

            $availableNow = array_key_exists('available_now', $validated)
                ? (bool) $validated['available_now']
                : (bool) $coach->available_now;

            if (!$immediateAvailability) {
                $availableNow = false;
            }

            $coach->update(array_filter([
                'name' => $validated['full_name'] ?? $coach->name,
                'title' => $validated['title'] ?? $coach->title,
                'bio' => $validated['bio'] ?? $coach->bio,
                'years_experience' => $validated['years_experience'] ?? $coach->years_experience,
                'hourly_rate_amount' => $validated['hourly_rate_amount'] ?? $coach->hourly_rate_amount,
                
                'qualifications' => $validated['qualifications'] ?? $coach->qualifications,
                'expertise_areas' => $keepOldIfEmpty('expertise_areas'),
                'coaching_philosophy' => $validated['coaching_philosophy'] ?? $coach->coaching_philosophy,
                'interests_and_personality' => $validated['interests_and_personality'] ?? $coach->interests_and_personality,
                
                'preferred_client_types' => $keepOldIfEmpty('preferred_client_types'),
                'industries' => $keepOldIfEmpty('industries'),
                'preferred_challenges' => $validated['preferred_challenges'] ?? $coach->preferred_challenges,
                
                'coaching_style' => $validated['coaching_style'] ?? $coach->coaching_style,
                
                'website' => $validated['website'] ?? $coach->website,
                'social_links' => $keepOldSocialLinks(),
                'languages' => $keepOldIfEmpty('languages'),
                
                'community_involvement' => $validated['community_involvement'] ?? $coach->community_involvement,
                'similar_experiences' => $keepOldIfEmpty('similar_experiences'),
                'notification_phone' => $validated['phone'] ?? $coach->notification_phone,
                'notification_email' => $user->email,
                'specialties' => $keepOldIfEmpty('specialties'),
                'timezone' => array_key_exists('timezone', $validated)
                    ? Timezone::normalize($validated['timezone'], $coach->timezone ?: 'UTC')
                    : $coach->timezone,
                'available_now' => $availableNow,
                'immediate_availability' => $immediateAvailability,
                'response_preference_minutes' => $validated['response_preference_minutes'] ?? $coach->response_preference_minutes,
            ], fn ($value) => $value !== null));
        }

        return $this->profile($request);
    }

    public function removeProfilePhoto(Request $request)
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
    
        if ($profile->profile_image) {
    
            // Delete image from storage
            Storage::disk('public')->delete($profile->profile_image);
    
            // Remove image from database
            $profile->update([
                'profile_image' => null,
            ]);
        }
    
        return $this->success([
            'profile_image' => null,
        ], 'Profile photo removed successfully.');
    }
    
    public function show($id)
    {
        $coach = Coach::with('user.profile')->findOrFail($id);
    
        $data = $coach->toArray();
    
        $data['profile_image'] = optional($coach->user->profile)->profile_image
            ? asset(Storage::url($coach->user->profile->profile_image))
            : null;
    
        return $this->success($data);
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
            'qualifications' => ['nullable', 'string'],
            'expertise_areas' => ['nullable', 'array'],
            'coaching_philosophy' => ['nullable', 'string'],
            'interests_and_personality' => ['nullable', 'string'],
            'preferred_client_types' => ['nullable', 'array'],
            'industries' => ['nullable', 'array'],
            'preferred_challenges' => ['nullable', 'string'],
            'coaching_style' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'social_links' => ['nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url'],
            'social_links.twitter' => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.facebook' => ['nullable', 'url'],
            'languages' => ['nullable', 'array'],
            'community_involvement' => ['nullable', 'string'],
            'similar_experiences' => ['nullable', 'array'],
            'hourly_rate_amount' => ['required', 'numeric', 'min:0'],
            'timezone' => ['required', 'string', 'max:100', new TimezoneIdentifier()],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

            $profileImage = null;
            
            if ($request->hasFile('profile_image')) {
                $profileImage = $request
                    ->file('profile_image')
                    ->store('profile-images', 'public');
            }

            $coach = Coach::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $validated['name'],
                    'title' => $validated['title'],
                    'bio' => $validated['bio'],
                    'years_experience' => $validated['years_experience'],
                    'specialties' => $validated['specialties'] ?? [],
                    'qualifications' => $validated['qualifications'] ?? null,
                    'expertise_areas' => $validated['expertise_areas'] ?? [],
                    'coaching_philosophy' => $validated['coaching_philosophy'] ?? null,
                    'interests_and_personality' => $validated['interests_and_personality'] ?? null,
                    'preferred_client_types' => $validated['preferred_client_types'] ?? [],
                    'industries' => $validated['industries'] ?? [],
                    'preferred_challenges' => $validated['preferred_challenges'] ?? null,
                    'coaching_style' => $validated['coaching_style'] ?? null,
                    'website' => $validated['website'] ?? null,
                    'social_links' => $validated['social_links'] ?? [],
                    'languages' => $validated['languages'] ?? [],
                    'community_involvement' => $validated['community_involvement'] ?? null,
                    'similar_experiences' => $validated['similar_experiences'] ?? [],
                    'timezone' => Timezone::normalize($validated['timezone'], 'UTC'),
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
                    'approval_status' => 'pending',
                    'admin_notes' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'response_preference_minutes' => 5,
                ]
            );

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $validated['name'],
                    'bio' => $validated['bio'],
                    'profile_image' => $profileImage,
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

            return $this->error($e->getMessage(), 500);
        }
    }

    private function formatLegalPayload(Profile $profile): array
    {
        return [
            'legal_version' => $profile->legal_version,
            'terms_accepted_at' => optional($profile->terms_accepted_at)?->toISOString(),
            'privacy_policy_accepted_at' => optional($profile->privacy_policy_accepted_at)?->toISOString(),
            'coaching_disclaimer_accepted_at' => optional($profile->coaching_disclaimer_accepted_at)?->toISOString(),
            'coach_independence_acknowledged_at' => optional($profile->coach_independence_acknowledged_at)?->toISOString(),
            'is_current' => $profile->legal_version === self::LEGAL_VERSION,
        ];
    }

    private function resolveLegalUpdates(Profile $profile, array $payload): array
    {
        $updates = [];

        if (($payload['accept_terms'] ?? false) && !$profile->terms_accepted_at) {
            $updates['terms_accepted_at'] = now();
        }

        if (($payload['accept_privacy_policy'] ?? false) && !$profile->privacy_policy_accepted_at) {
            $updates['privacy_policy_accepted_at'] = now();
        }

        if (($payload['accept_coaching_disclaimer'] ?? false) && !$profile->coaching_disclaimer_accepted_at) {
            $updates['coaching_disclaimer_accepted_at'] = now();
        }

        if (($payload['acknowledge_coach_independence'] ?? false) && !$profile->coach_independence_acknowledged_at) {
            $updates['coach_independence_acknowledged_at'] = now();
        }

        if (!empty($updates) && !$profile->legal_version) {
            $updates['legal_version'] = self::LEGAL_VERSION;
        }

        return $updates;
    }

    public function respondToInformationRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'coach_response' => ['required', 'string', 'max:2000'],
        ]);
    
        $coach = $request->user()->coachProfile;
    
        if (!$coach) {
            return $this->error('Coach profile not found.', 404);
        }
    
        $requestRecord = CoachInformationRequest::where('id', $id)
            ->where('coach_id', $coach->id)
            ->firstOrFail();
    
        $requestRecord->update([
            'coach_response' => $validated['coach_response'],
            'status' => 'responded',
        ]);
    
        return $this->success(
            $requestRecord->fresh(),
            'Response updated successfully.'
        );
    }
}
