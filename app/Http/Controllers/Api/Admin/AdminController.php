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
    public function users(Request $request)
    {
        $users = User::query()
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($users);
    }

    public function coaches(Request $request)
    {
        $coaches = Coach::query()
            ->with('coachApplication')
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($coaches);
    }

    public function pendingApplications(Request $request)
    {
        $applications = PendingCoachApplication::query()
            ->whereIn('status', ['pending', 'invited'])
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($applications);
    }

    public function sessions(Request $request)
    {
        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
            ])
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($sessions);
    }

    public function failedSessions(Request $request)
    {
        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
            ])
            ->whereIn('status', ['failed', 'cancelled', 'no_show'])
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($sessions);
    }

    public function transcripts(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $query = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
            ])
            ->whereHas('recording');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder
                    ->whereHas('client', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->orWhereHas('coach', function ($sub) use ($q) {
                        $sub->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('recording', function ($sub) use ($q) {
                        $sub->where('transcript', 'like', "%{$q}%")
                            ->orWhere('ai_summary', 'like', "%{$q}%")
                            ->orWhere('pre_session_summary', 'like', "%{$q}%")
                            ->orWhere('post_session_summary', 'like', "%{$q}%");
                    });
            });
        }

        $sessions = $query
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success($sessions);
    }

    public function transcript($id)
    {
        $session = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name',
                'recording',
            ])
            ->findOrFail($id);

        return $this->success($session);
    }

    public function inviteCoach(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        DB::beginTransaction();

        try {
            $existingApplication = PendingCoachApplication::where('email', $validated['email'])->first();

            $application = PendingCoachApplication::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $existingApplication?->name ?? 'Coach Invite',
                    'phone' => $existingApplication?->phone,
                    'experience' => $existingApplication?->experience ?? 'Pending',
                    'specialties' => $existingApplication?->specialties ?? [],
                    'message' => $existingApplication?->message ?? 'Invited by admin',
                    'status' => 'invited',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]
            );

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $application->name ?: 'Coach',
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->send(new CoachInvitationMail($user->email, $plainToken));

            DB::commit();

            return $this->success([
                'application' => $application,
                'user' => $user,
            ], 'Coach invitation sent successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send coach invitation',
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
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            if (!$user->name && $application->name) {
                $user->update(['name' => $application->name]);
            }

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role' => 'coach',
            ]);

            Coach::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $application->name,
                    'title' => 'Coach',
                    'bio' => $application->message ?: 'Coach profile pending completion.',
                    'years_experience' => is_numeric($application->experience) ? (int) $application->experience : 1,
                    'specialties' => $application->specialties ?? [],
                    'similar_experiences' => [],
                    'notification_email' => $user->email,
                    'timezone' => 'UTC',
                    'is_active' => true,
                    'available_now' => false,
                ]
            );

            $application->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            Mail::to($user->email)->send(new CoachInvitationMail($user->email, $plainToken));

            DB::commit();

            return $this->success([], 'Coach approved successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve coach application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}