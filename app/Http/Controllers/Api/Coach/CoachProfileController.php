<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class CoachProfileController extends Controller
{
    /**
     * Get all approved coaches - public endpoint
     */
    public function index()
    {
        try {
            Log::info('Fetching all approved coaches from coach_profiles');
            
            // Get coaches from coach_profiles table with user data
            $coaches = CoachProfile::with('user')
                ->whereHas('user', function($q) {
                    $q->where('is_approved', true);
                })
                ->get()
                ->map(function ($profile) {
                    return [
                        'id' => $profile->user->id,
                        'name' => $profile->user->name,
                        'avatar' => $profile->user->avatar,
                        'bio' => $profile->bio,
                        'expertise' => $profile->expertise,
                        'coaching_styles' => $profile->coaching_styles,
                        'rating' => $profile->rating,
                        'hourly_rate' => $profile->hourly_rate,
                        'total_sessions' => $profile->total_sessions,
                        'languages' => $profile->languages,
                        'certifications' => $profile->certifications,
                        'education' => $profile->education,
                        'experience_years' => $profile->experience_years
                    ];
                });

            Log::info('Found ' . $coaches->count() . ' approved coaches');

            return response()->json([
                'success' => true,
                'data' => $coaches
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching coaches: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coaches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single coach by ID - public endpoint
     */
    public function show($id)
    {
        try {
            Log::info('Fetching coach with ID: ' . $id);
            
            $profile = CoachProfile::with('user')
                ->whereHas('user', function($q) {
                    $q->where('is_approved', true);
                })
                ->where('user_id', $id)
                ->first();

            if (!$profile) {
                Log::warning('Coach not found with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Coach not found'
                ], 404);
            }

            $coach = $profile->user;

            Log::info('Coach found: ' . $coach->name);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'email' => $coach->email,
                    'avatar' => $coach->avatar,
                    'bio' => $profile->bio,
                    'expertise' => $profile->expertise,
                    'coaching_styles' => $profile->coaching_styles,
                    'hourly_rate' => $profile->hourly_rate,
                    'languages' => $profile->languages,
                    'certifications' => $profile->certifications,
                    'education' => $profile->education,
                    'experience_years' => $profile->experience_years,
                    'rating' => $profile->rating,
                    'total_sessions' => $profile->total_sessions,
                    'availability' => $coach->coachAvailability ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching coach: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coach details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update coach profile - protected endpoint
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $profile = $user->coachProfile;
            
            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coach profile not found'
                ], 404);
            }

            $request->validate([
                'bio' => 'sometimes|string|max:1000',
                'expertise' => 'sometimes|array',
                'expertise.*' => 'string',
                'coaching_styles' => 'sometimes|array',
                'coaching_styles.*' => 'string',
                'hourly_rate' => 'sometimes|numeric|min:0',
                'languages' => 'sometimes|array',
                'certifications' => 'sometimes|array',
                'education' => 'sometimes|array',
                'experience_years' => 'sometimes|integer|min:0'
            ]);

            $updateData = $request->only([
                'bio', 
                'expertise', 
                'coaching_styles', 
                'hourly_rate', 
                'languages', 
                'certifications', 
                'education', 
                'experience_years'
            ]);

            $profile->update($updateData);

            Log::info('Coach profile updated for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $profile
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating coach profile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coach availability - public endpoint
     */
    public function getAvailability($id)
    {
        try {
            $user = User::where('id', $id)
                ->where('is_approved', true)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coach not found'
                ], 404);
            }

            $availability = $user->coachAvailability()
                ->where('is_available', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availability
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching coach availability: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}