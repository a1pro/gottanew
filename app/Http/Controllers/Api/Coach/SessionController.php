<?php

namespace App\Http\Controllers\Api\Coach;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionStateLog;

class SessionController extends BaseController
{
    private function getCoach(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $coach = $user->coachProfile;
        abort_unless($coach, 403, 'Coach profile not found');

        return $coach;
    }

    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $coach = $this->getCoach($request);

        return CoachingSession::with(['client', 'coach', 'videoDetail'])
            ->where('coach_id', $coach->id)
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $coach = $this->getCoach($request);

        $sessions = CoachingSession::with(['client', 'videoDetail'])
            ->where('coach_id', $coach->id)
            ->orderBy('scheduled_time')
            ->get()
            ->map(function (CoachingSession $session) {
                return [
                    'id' => $session->id,
                    'status' => $session->status,
                    'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
                    'duration_minutes' => $session->duration_minutes,
                    'client_name' => optional($session->client)->name,
                    'client' => $session->client,
                    'video_detail' => $session->videoDetail,
                    'created_at' => optional($session->created_at)?->toISOString(),
                    'is_intro_session' => (bool) $session->is_intro_session,
                    'coach_notes' => $session->coach_notes,
                ];
            });

        return $this->success($sessions);
    }

    public function show(Request $request, $id)
    {
        return $this->success($this->getAuthorizedSession($request, $id));
    }

    public function saveNotes(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $session->update([
            'coach_notes' => $validated['notes'] ?? null,
        ]);

        return $this->success($session->fresh(['client', 'coach', 'videoDetail']), 'Notes saved');
    }

    public function start(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);
        $fromState = $session->status;

        if ($fromState !== 'live') {
            $session->update(['status' => 'live']);

            SessionStateLog::create([
                'session_id' => $session->id,
                'from_state' => $fromState,
                'to_state' => 'live',
                'changed_by' => optional($request->user())->id,
                'change_reason' => 'Session started by coach',
                'metadata' => [
                    'started_at' => now()->toISOString(),
                ],
            ]);
        }

        return $this->success([
            'session' => $session->fresh(['client', 'coach', 'videoDetail']),
            'video_join_url' => optional($session->videoDetail)->video_join_url,
        ], 'Session started');
    }
}