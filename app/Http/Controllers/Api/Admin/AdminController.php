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
        $q = trim((string) $request->get('q', ''));
        $role = trim((string) $request->get('role', ''));

        $users = User::query()
            ->with([
                'roles:id,user_id,role',
                'wallet:user_id,coin_balance,total_coins_purchased,total_coins_spent',
                'coachProfile:id,user_id,title,is_active,timezone',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->whereHas('roles', function ($sub) use ($role) {
                    $sub->where('role', $role);
                });
            })
            ->latest()
            ->paginate((int) $request->get('per_page', 10));

        $users->setCollection(
            $users->getCollection()->map(fn (User $user) => $this->serializeUser($user))
        );

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
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,user_id,notification_email',
                'coach.user:id,email',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings,recording_url,feedback_rating',
                'transactions:id,session_id,status,transaction_type,coin_amount,amount_fiat,amount_currency,created_at',
            ])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('id', $q)
                        ->orWhere('client_notes', 'like', "%{$q}%")
                        ->orWhere('coach_notes', 'like', "%{$q}%")
                        ->orWhereHas('client', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        })
                        ->orWhereHas('coach', function ($sub) use ($q) {
                            $sub->where('name', 'like', "%{$q}%")
                                ->orWhere('notification_email', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        $sessions->setCollection(
            $sessions->getCollection()->map(fn (CoachingSession $session) => $this->serializeSession($session))
        );

        return $this->success($sessions);
    }

    public function failedSessions(Request $request)
    {
        $sessions = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,user_id',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(5),
            ])
            ->whereIn('status', ['interrupted', 'failed', 'cancelled', 'no_show'])
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
                'coach:id,name,title',
                'recording:id,session_id,transcription_status,transcript,ai_summary,pre_session_summary,post_session_summary,next_actions,key_topics,privacy_settings',
                'introRequest:id,approved_session_id,status,goal_summary,request_notes,admin_notes,viewer_timezone,approved_at',
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
                        $sub->where('name', 'like', "%{$q}%")
                            ->orWhere('title', 'like', "%{$q}%");
                    })
                    ->orWhereHas('recording', function ($sub) use ($q) {
                        $sub->where('transcript', 'like', "%{$q}%")
                            ->orWhere('ai_summary', 'like', "%{$q}%")
                            ->orWhere('pre_session_summary', 'like', "%{$q}%")
                            ->orWhere('post_session_summary', 'like', "%{$q}%");
                    })
                    ->orWhereHas('introRequest', function ($sub) use ($q) {
                        $sub->where('goal_summary', 'like', "%{$q}%")
                            ->orWhere('request_notes', 'like', "%{$q}%")
                            ->orWhere('admin_notes', 'like', "%{$q}%");
                    });
            });
        }

        $summaryBase = CoachingSession::query()->whereHas('recording');

        $sessions = $query
            ->latest('scheduled_time')
            ->paginate((int) $request->get('per_page', 10));

        return $this->success([
            'data' => $sessions,
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'intro_sessions' => (clone $summaryBase)->where('is_intro_session', true)->count(),
                'with_transcript' => (clone $summaryBase)->whereHas('recording', fn ($sub) => $sub->whereNotNull('transcript')->where('transcript', '!=', ''))->count(),
                'with_ai_summary' => (clone $summaryBase)->whereHas('recording', fn ($sub) => $sub->whereNotNull('ai_summary')->where('ai_summary', '!=', ''))->count(),
            ],
        ]);
    }

    public function transcript($id)
    {
        $session = CoachingSession::query()
            ->with([
                'client:id,name,email',
                'coach:id,name,title',
                'recording',
                'videoDetail:id,session_id,video_join_url,daily_room_name,room_created_at',
                'introRequest:id,approved_session_id,status,goal_summary,request_notes,admin_notes,viewer_timezone,approved_at',
                'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
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

    private function serializeUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) ($user->is_active ?? true),
            'last_login_at' => optional($user->last_login_at)?->toISOString(),
            'created_at' => optional($user->created_at)?->toISOString(),
            'roles' => $user->roles->pluck('role')->values()->all(),
            'wallet' => $user->wallet ? [
                'coin_balance' => (int) ($user->wallet->coin_balance ?? 0),
                'total_coins_purchased' => (int) ($user->wallet->total_coins_purchased ?? 0),
                'total_coins_spent' => (int) ($user->wallet->total_coins_spent ?? 0),
            ] : null,
            'coach_profile' => $user->coachProfile ? [
                'title' => $user->coachProfile->title,
                'is_active' => (bool) $user->coachProfile->is_active,
                'timezone' => $user->coachProfile->timezone,
            ] : null,
        ];
    }

    private function serializeSession(CoachingSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'status' => $session->status,
            'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
            'duration_minutes' => (int) ($session->duration_minutes ?? 15),
            'price_amount' => (float) ($session->price_amount ?? 0),
            'price_currency' => $session->price_currency ?? 'USD',
            'is_intro_session' => (bool) ($session->is_intro_session ?? false),
            'client_notes' => $session->client_notes,
            'coach_notes' => $session->coach_notes,
            'failure_reason' => $session->failure_reason,
            'recovery_attempts' => (int) ($session->recovery_attempts ?? 0),
            'recovery_deadline_at' => optional($session->recovery_deadline_at)?->toISOString(),
            'actual_started_at' => optional($session->actual_started_at)?->toISOString(),
            'actual_ended_at' => optional($session->actual_ended_at)?->toISOString(),
            'client' => $session->client ? [
                'id' => (int) $session->client->id,
                'name' => $session->client->name,
                'email' => $session->client->email,
            ] : null,
            'coach' => $session->coach ? [
                'id' => (int) $session->coach->id,
                'name' => $session->coach->name,
                'email' => $session->coach->notification_email ?: $session->coach->user?->email,
                'user_id' => $session->coach->user_id ? (int) $session->coach->user_id : null,
            ] : null,
            'video_detail' => $session->videoDetail ? [
                'video_join_url' => $session->videoDetail->video_join_url,
                'daily_room_name' => $session->videoDetail->daily_room_name,
                'room_created_at' => optional($session->videoDetail->room_created_at)?->toISOString(),
            ] : null,
            'recording' => $session->recording ? [
                'transcription_status' => $session->recording->transcription_status,
                'transcript' => $session->recording->transcript,
                'ai_summary' => $session->recording->ai_summary,
                'pre_session_summary' => $session->recording->pre_session_summary,
                'post_session_summary' => $session->recording->post_session_summary,
                'next_actions' => $session->recording->next_actions ?? [],
                'key_topics' => $session->recording->key_topics ?? [],
                'privacy_settings' => $session->recording->privacy_settings ?? [],
                'recording_url' => $session->recording->recording_url,
                'feedback_rating' => $session->recording->feedback_rating,
                'has_ai_outputs' => (bool) ($session->recording->pre_session_summary || $session->recording->post_session_summary || $session->recording->ai_summary),
            ] : null,
            'transactions' => $session->transactions->map(fn ($transaction) => [
                'id' => (int) $transaction->id,
                'status' => $transaction->status,
                'type' => $transaction->transaction_type,
                'coin_amount' => (int) ($transaction->coin_amount ?? 0),
                'amount' => $transaction->amount_fiat,
                'currency' => $transaction->amount_currency,
                'created_at' => optional($transaction->created_at)?->toISOString(),
            ])->values()->all(),
        ];
    }
}
