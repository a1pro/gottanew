<?php
 
namespace App\Http\Controllers\Api\Coach;
 
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CoachMatch;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
 
class CoachController extends Controller
{
    /**
     * Get all approved coaches - public endpoint
     */
    public function index()
    {
        try {
            Log::info('Fetching all approved coaches');
 
            $coaches = User::whereHas('roles', function($q) {
                $q->where('slug', 'coach');
            })
            ->where('is_approved', true)
            ->with('coachProfile')
            ->get()
            ->map(function ($coach) {
                return [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'avatar' => $coach->avatar,
                    'bio' => $coach->coachProfile?->bio,
                    'expertise' => $coach->coachProfile?->expertise,
                    'coaching_styles' => $coach->coachProfile?->coaching_styles,
                    'rating' => $coach->coachProfile?->rating,
                    'hourly_rate' => $coach->coachProfile?->hourly_rate,
                    'total_sessions' => $coach->coachProfile?->total_sessions
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
 
            $coach = User::whereHas('roles', function($q) {
                $q->where('slug', 'coach');
            })
            ->where('is_approved', true)
            ->with('coachProfile')
            ->find($id);
 
            if (!$coach) {
                Log::warning('Coach not found with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Coach not found'
                ], 404);
            }
 
            Log::info('Coach found: ' . $coach->name);
 
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'email' => $coach->email,
                    'avatar' => $coach->avatar,
                    'bio' => $coach->coachProfile?->bio,
                    'expertise' => $coach->coachProfile?->expertise,
                    'coaching_styles' => $coach->coachProfile?->coaching_styles,
                    'hourly_rate' => $coach->coachProfile?->hourly_rate,
                    'languages' => $coach->coachProfile?->languages,
                    'certifications' => $coach->coachProfile?->certifications,
                    'education' => $coach->coachProfile?->education,
                    'experience_years' => $coach->coachProfile?->experience_years,
                    'rating' => $coach->coachProfile?->rating,
                    'total_sessions' => $coach->coachProfile?->total_sessions,
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
     * Match coaches based on client goals
     */
    public function matchCoaches(Request $request)
    {
        try {
            $request->validate([
                'goal_id' => 'required|string'
            ]);
 
            $client = $request->user();
            $goalId = $request->goal_id;
 
            // Verify goal exists
            $goal = Goal::find($goalId);
            if (!$goal) {
                return response()->json([
                    'analysis' => 'Goal not found. Please select a valid goal.',
                    'recommendations' => [],
                    'totalRecommendations' => 0
                ]);
            }
 
            Log::info('Matching coaches for client: ' . $client->id . ' with goal: ' . $goalId . ' (' . $goal->title . ')');
 
            // Get all approved coaches with their profiles
            $coaches = User::whereHas('roles', function($q) {
                    $q->where('slug', 'coach');
                })
                ->where('is_approved', true)
                ->with('coachProfile')
                ->get();
 
            if ($coaches->isEmpty()) {
                return response()->json([
                    'analysis' => 'No coaches available at the moment. Please check back later.',
                    'recommendations' => [],
                    'totalRecommendations' => 0
                ]);
            }
 
            // Generate AI analysis and recommendations
            $analysis = $this->generateAIAnalysis($goal, $client);
            $recommendations = $this->generateRecommendations($coaches, $goal, $client);
 
            // Store matches in database
            $this->storeCoachMatches($client->id, $goalId, $recommendations);
 
            Log::info('Generated ' . count($recommendations) . ' coach recommendations');
 
            return response()->json([
                'analysis' => $analysis,
                'recommendations' => $recommendations,
                'totalRecommendations' => count($recommendations)
            ]);
 
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error matching coaches: ' . $e->getMessage());
 
            return response()->json([
                'success' => false,
                'message' => 'Failed to match coaches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Store coach matches in database
     */
    private function storeCoachMatches($clientId, $goalId, $recommendations)
    {
        foreach ($recommendations as $recommendation) {
            CoachMatch::updateOrCreate(
                [
                    'client_id' => $clientId,
                    'coach_id' => $recommendation['coachId'],
                    'goal_id' => $goalId
                ],
                [
                    'match_score' => $recommendation['confidenceScore'] * 10, // Convert to 0-100 scale
                    'match_reasons' => $recommendation['keyAlignments'],
                    'key_alignments' => $recommendation['keyAlignments'],
                    'match_reason' => $recommendation['matchReason'],
                    'confidence_score' => $recommendation['confidenceScore'],
                    'presented_to_client' => true,
                    'selected_by_client' => false
                ]
            );
        }
    }
 
    /**
     * Generate AI analysis based on goal
     */
    private function generateAIAnalysis($goal, $client)
    {
        $goalAnalyses = [
            'health-fitness' => "Based on your health and fitness goals, I've identified coaches who specialize in creating personalized workout and nutrition plans. These coaches have experience with clients at various fitness levels and can help you achieve sustainable results through proven methodologies.",
            'career-development' => "For your career development objectives, I've matched you with coaches who have extensive experience in professional growth, leadership development, and strategic career planning. They can provide valuable insights and guidance to accelerate your career progression.",
            'personal-growth' => "Your personal growth journey will be well-supported by these coaches who specialize in mindset development, habit formation, and emotional intelligence. They use evidence-based approaches to help you unlock your full potential.",
            'relationships' => "I've selected coaches who excel in communication skills, emotional intelligence, and interpersonal dynamics. They can help you build stronger, more fulfilling relationships through proven coaching methodologies.",
            'financial-stability' => "These coaches have strong backgrounds in financial planning, wealth management, and financial psychology. They can help you develop sustainable financial habits and achieve your monetary goals.",
            'creativity-hobbies' => "For your creative pursuits, I've matched you with coaches who understand the unique challenges and opportunities in creative development. They can help you overcome creative blocks and develop your artistic skills."
        ];
 
        return $goalAnalyses[$goal->id] ?? "I've analyzed your goals and matched you with coaches who have the expertise and experience to help you succeed. Each coach brings unique strengths and approaches to support your journey.";
    }
 
    /**
     * Generate coach recommendations
     */
    private function generateRecommendations($coaches, $goal, $client)
    {
        $recommendations = [];
        $goalSpecialties = $this->getGoalSpecialties($goal->id);
 
        foreach ($coaches as $index => $coach) {
            $profile = $coach->coachProfile;
            if (!$profile) continue;
 
            $confidenceScore = $this->calculateConfidenceScore($coach, $goalSpecialties);
            $matchReason = $this->generateMatchReason($coach, $goal, $confidenceScore);
            $keyAlignments = $this->getKeyAlignments($coach, $goalSpecialties);
 
            $recommendations[] = [
                'coachId' => $coach->id,
                'coachName' => $coach->name,
                'matchReason' => $matchReason,
                'keyAlignments' => $keyAlignments,
                'confidenceScore' => $confidenceScore,
                'coach' => [
                    'id' => $coach->id,
                    'name' => $coach->name,
                    'title' => $this->getCoachTitle($coach, $profile),
                    'bio' => $profile->bio ?? 'Experienced coach dedicated to helping clients achieve their goals.',
                    'years_experience' => $profile->experience_years ?? 3,
                    'specialties' => $profile->expertise ?? ['General Coaching'],
                    'similar_experiences' => $this->getSimilarExperiences($profile, $goal->id),
                    'rating' => $profile->rating ?? 4.5,
                    'total_reviews' => $profile->total_sessions ?? 20,
                    'avatar_url' => $coach->avatar ?? null,
                    'timezone' => 'UTC',
                    'availability_hours' => '9AM-6PM',
                    'availability_status' => $this->getAvailabilityStatus($coach),
                    'pricing' => [
                        'min_price' => $profile->hourly_rate ?? 75,
                        'max_price' => ($profile->hourly_rate ?? 75) + 50,
                        'currency' => 'USD'
                    ]
                ]
            ];
        }
 
        // Sort by confidence score and return top recommendations
        usort($recommendations, function($a, $b) {
            return $b['confidenceScore'] <=> $a['confidenceScore'];
        });
 
        return array_slice($recommendations, 0, 8); // Return top 8
    }
 
    /**
     * Get goal specialties for matching
     */
    private function getGoalSpecialties($goalId)
    {
        $specialties = [
            'health-fitness' => ['fitness', 'nutrition', 'weight loss', 'muscle gain', 'wellness'],
            'career-development' => ['leadership', 'career planning', 'professional development', 'business'],
            'personal-growth' => ['mindset', 'confidence', 'habits', 'emotional intelligence'],
            'relationships' => ['communication', 'relationships', 'interpersonal skills'],
            'financial-stability' => ['finance', 'investing', 'financial planning', 'wealth building'],
            'creativity-hobbies' => ['creativity', 'artistic development', 'hobbies', 'skills']
        ];
 
        return $specialties[$goalId] ?? ['general coaching'];
    }
 
    /**
     * Calculate confidence score for coach matching
     */
    private function calculateConfidenceScore($coach, $goalSpecialties)
    {
        $profile = $coach->coachProfile;
        if (!$profile) return 5.0;
 
        $score = 5.0; // Base score
 
        // Add points for relevant expertise
        if ($profile->expertise) {
            foreach ($profile->expertise as $expertise) {
                if (in_array(strtolower($expertise), $goalSpecialties)) {
                    $score += 1.5;
                }
            }
        }
 
        // Add points for experience
        $score += min(($profile->experience_years ?? 0) * 0.2, 2.0);
 
        // Add points for rating
        $score += min(($profile->rating ?? 4.0 - 4.0) * 2, 1.5);
 
        return min($score, 10.0);
    }
 
    /**
     * Generate match reason for coach
     */
    private function generateMatchReason($coach, $goal, $confidenceScore)
    {
        $profile = $coach->coachProfile;
        $reasons = [
            'health-fitness' => "specializes in fitness and wellness with a proven track record",
            'career-development' => "has extensive experience in career advancement and professional development",
            'personal-growth' => "excels in personal development and mindset coaching",
            'relationships' => "has strong expertise in communication and relationship building",
            'financial-stability' => "brings deep knowledge of financial planning and wealth management",
            'creativity-hobbies' => "specializes in creative development and skill building"
        ];
 
        $baseReason = $reasons[$goal->id] ?? "has relevant coaching experience";
        $experience = $profile->experience_years ?? 3;
 
        return "{$coach->name} {$baseReason} with {$experience} years of experience and excellent client results.";
    }
 
    /**
     * Get key alignments between coach and goal
     */
    private function getKeyAlignments($coach, $goalSpecialties)
    {
        $profile = $coach->coachProfile;
        $alignments = [];
 
        if ($profile->expertise) {
            foreach ($profile->expertise as $expertise) {
                if (in_array(strtolower($expertise), $goalSpecialties)) {
                    $alignments[] = $expertise;
                }
            }
        }
 
        if (empty($alignments)) {
            $alignments = ['General Coaching', 'Client Success'];
        }
 
        return array_slice($alignments, 0, 3);
    }
 
    /**
     * Get similar experiences for coach
     */
    private function getSimilarExperiences($profile, $goalId)
    {
        $experiences = [
            'health-fitness' => ['weight loss transformation', 'muscle building', 'marathon training'],
            'career-development' => ['career transitions', 'leadership development', 'skill advancement'],
            'personal-growth' => ['confidence building', 'habit formation', 'mindset shifts'],
            'relationships' => ['communication improvement', 'conflict resolution', 'relationship building'],
            'financial-stability' => ['debt reduction', 'investment planning', 'wealth building'],
            'creativity-hobbies' => ['skill development', 'creative blocks', 'project completion']
        ];
 
        return $experiences[$goalId] ?? ['goal achievement', 'personal development', 'success planning'];
    }
 
    /**
     * Get coach title based on experience
     */
    private function getCoachTitle($coach, $profile)
    {
        $expertise = $profile->expertise[0] ?? 'Coach';
        $experience = $profile->experience_years ?? 0;
 
        if ($experience >= 10) {
            return "Senior {$expertise} Coach";
        } elseif ($experience >= 5) {
            return "Experienced {$expertise} Coach";
        } else {
            return "{$expertise} Coach";
        }
    }
 
    /**
     * Get availability status for coach
     */
    private function getAvailabilityStatus($coach)
    {
        // You can implement real availability checking here
        // For now, return random status
        $statuses = ['available', 'busy', 'away'];
        return $statuses[array_rand($statuses)];
    }
 
    /**
     * Update coach profile - protected endpoint (only for authenticated coaches)
     */
    public function updateProfile(Request $request)
    {
        try {
            $coach = $request->user();
 
            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
 
            // Check if user is a coach
            if (!$coach->isCoach()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Coach role required.'
                ], 403);
            }
 
            $profile = $coach->coachProfile;
 
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
 
            Log::info('Coach profile updated for user: ' . $coach->id);
 
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
            $coach = User::whereHas('roles', function($q) {
                $q->where('slug', 'coach');
            })
            ->where('is_approved', true)
            ->find($id);
 
            if (!$coach) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coach not found'
                ], 404);
            }
 
            $availability = $coach->coachAvailability()
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