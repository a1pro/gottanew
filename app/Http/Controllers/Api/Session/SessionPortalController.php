<?php

namespace App\Http\Controllers\Api\Session;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionStateLog;

class SessionPortalController extends BaseController
{
    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated');

        $session = CoachingSession::with([
            'coach',
            'client',
            'videoDetail',
        ])->findOrFail($id);

        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;

        abort_unless($isClient || $isCoach, 403, 'Unauthorized access to session');

        return $session;
    }

    public function show(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        return $this->success($session);
    }

    public function join(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $joinUrl = optional($session->videoDetail)->video_join_url;

        if (!$joinUrl) {
            return $this->error('Video room link not found', 404);
        }

        return $this->success([
            'session_id' => $session->id,
            'video_join_url' => $joinUrl,
            'daily_room_name' => optional($session->videoDetail)->daily_room_name,
            'status' => $session->status,
        ]);
    }

    public function updateState(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'new_state' => ['required', 'in:scheduled,in_progress,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $fromState = $session->status;
        $toState = $validated['new_state'];

        if ($fromState !== $toState) {
            $session->update([
                'status' => $toState,
            ]);

            SessionStateLog::create([
                'session_id' => $session->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => optional($request->user())->id,
                'change_reason' => $validated['reason'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);
        }

        return $this->success(
            $session->fresh(['coach', 'client', 'videoDetail']),
            'Session state updated'
        );
    }

    public function saveNotes(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $validated = $request->validate([
            'client_notes' => ['nullable', 'string'],
            'coach_notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;

        $updates = [];

        if ($isClient && array_key_exists('client_notes', $validated)) {
            $updates['client_notes'] = $validated['client_notes'];
        }

        if ($isCoach && array_key_exists('coach_notes', $validated)) {
            $updates['coach_notes'] = $validated['coach_notes'];
        }

        if (!empty($updates)) {
            $session->update($updates);
        }

        return $this->success(
            $session->fresh(['coach', 'client', 'videoDetail']),
            'Session notes saved'
        );
    }

    public function end(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $fromState = $session->status;

        $session->update([
            'status' => 'completed',
        ]);

        SessionStateLog::create([
            'session_id' => $session->id,
            'from_state' => $fromState,
            'to_state' => 'completed',
            'changed_by' => optional($request->user())->id,
            'change_reason' => 'Session ended by participant',
            'metadata' => [
                'ended_at' => now()->toISOString(),
            ],
        ]);

        return $this->success(
            $session->fresh(['coach', 'client', 'videoDetail']),
            'Session ended successfully'
        );
    }
}