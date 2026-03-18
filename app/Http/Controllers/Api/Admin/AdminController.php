<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Mail\CoachInvitationMail;
use App\Models\Coach\Coach;
use App\Models\Coach\PendingCoachApplication;
use App\Models\Core\UserRole;
use App\Models\Session\CoachingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends BaseController
{
    public function users()
    {
        return $this->success(User::latest()->paginate(20));
    }

    public function coaches()
    {
        return $this->success(
            Coach::with('coachApplication')->latest()->paginate(20)
        );
    }

    public function pendingApplications()
    {
        return $this->success(
            PendingCoachApplication::whereIn('status', ['pending', 'invited'])
                ->latest()
                ->paginate(20)
        );
    }

    public function inviteCoach(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));

        DB::beginTransaction();

        try {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::headline(Str::before($email, '@')),
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            PendingCoachApplication::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $user->name,
                    'phone' => null,
                    'experience' => 'Pending onboarding',
                    'specialties' => [],
                    'message' => 'Coach invited by admin',
                    'status' => 'invited',
                ]
            );

            $token = Str::random(60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => bcrypt($token),
                    'created_at' => now(),
                ]
            );

            Mail::to($email)->send(new CoachInvitationMail($email, $token));

            DB::commit();

            return $this->success([
                'email' => $email,
            ], 'Invitation sent successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approveApplication($id)
    {
        $application = PendingCoachApplication::findOrFail($id);

        DB::beginTransaction();

        try {
            $user = User::firstOrCreate(
                ['email' => $application->email],
                [
                    'name' => $application->name,
                    'password' => Hash::make(Str::random(16)),
                ]
            );

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            $coach = Coach::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $application->name,
                    'title' => 'Coach',
                    'bio' => $application->message ?: 'Coach profile will be updated during onboarding.',
                    'years_experience' => is_numeric($application->experience) ? (int) $application->experience : 1,
                    'specialties' => $application->specialties ?: [],
                    'similar_experiences' => [],
                    'rating' => 0,
                    'total_reviews' => 0,
                    'availability_hours' => null,
                    'timezone' => 'UTC',
                    'social_links' => [],
                    'is_active' => true,
                    'available_now' => false,
                    'notification_email' => $application->email,
                    'notification_phone' => null,
                    'coaching_expertise' => null,
                    'coaching_style' => null,
                    'client_challenge_example' => null,
                    'personal_experiences' => null,
                    'hourly_rate_amount' => 100,
                    'hourly_rate_currency' => 'USD',
                    'hourly_coin_cost' => 4,
                    'booking_buffer_minutes' => 0,
                    'max_session_duration' => 15,
                    'min_session_duration' => 15,
                    'immediate_availability' => false,
                    'response_preference_minutes' => 60,
                ]
            );

            $application->update([
                'status' => 'approved',
            ]);

            $token = Str::random(60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => bcrypt($token),
                    'created_at' => now(),
                ]
            );

            Mail::to($user->email)->send(
                new CoachInvitationMail($user->email, $token)
            );

            DB::commit();

            return $this->success([
                'coach' => $coach,
            ], 'Coach approved and invitation email sent.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to approve coach',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sessions()
    {
        return $this->success(
            CoachingSession::with(['client', 'coach', 'videoDetail', 'recording'])
                ->latest()
                ->paginate(20)
        );
    }

    public function failedSessions()
    {
        return $this->success(
            CoachingSession::with(['client', 'coach', 'videoDetail', 'recording'])
                ->whereIn('status', ['failed', 'interrupted', 'cancelled', 'no_show'])
                ->latest()
                ->paginate(20)
        );
    }

    public function transcripts(Request $request)
    {
        $query = CoachingSession::with(['client', 'coach', 'recording'])
            ->whereHas('recording');

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));

            $query->where(function ($builder) use ($q) {
                $builder
                    ->whereHas('client', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->orWhereHas('coach', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('title', 'like', "%{$q}%");
                    })
                    ->orWhereHas('recording', function ($sub) use ($q) {
                        $sub->where('transcript', 'like', "%{$q}%")
                            ->orWhere('ai_summary', 'like', "%{$q}%")
                            ->orWhere('pre_session_summary', 'like', "%{$q}%")
                            ->orWhere('post_session_summary', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return $this->success(
            $query->latest()->paginate(15)
        );
    }

    public function transcript($id)
    {
        $session = CoachingSession::with(['client', 'coach', 'videoDetail', 'recording'])
            ->findOrFail($id);

        return $this->success($session);
    }
}