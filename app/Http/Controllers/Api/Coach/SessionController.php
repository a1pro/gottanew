<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionStateLog;
use App\Support\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

  private function getAuthorizedSession(Request $request, $id):
CoachingSession
  {
      $coach = $this->getCoach($request);

      return CoachingSession::with([
          'client:id,name,email',
          'coach',
          'videoDetail',
          'recording',
          'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
           'introRequest.preferredCoach:id,name,title,timezone',
           'introRequest.assignedCoach:id,name,title,timezone',
      ])
           ->where('coach_id', $coach->id)
           ->findOrFail($id);
  }

  public function index(Request $request)
  {
      $coach = $this->getCoach($request);

      $sessions = CoachingSession::with([
          'client:id,name,email',
          'videoDetail',
          'recording',
          'introRequest.preferredCoach:id,name,title,timezone',
            'introRequest.assignedCoach:id,name,title,timezone',
      ])
            ->where('coach_id', $coach->id)
            ->orderBy('scheduled_time')
          ->get()
          ->map(fn (CoachingSession $session) => $this->serializeSession($session));

      return $this->success($sessions);
  }

  public function show(Request $request, $id)
  {
      return $this->success($this->serializeSession($this->getAuthorizedSession($request, $id), true));
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

      return $this->success($this->serializeSession($session->fresh([
          'client:id,name,email',
          'coach',
            'videoDetail',
            'recording',
            'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
          'introRequest.preferredCoach:id,name,title,timezone',
          'introRequest.assignedCoach:id,name,title,timezone',
      ]), true), 'Notes saved');
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

      $fresh = $session->fresh([
          'client:id,name,email',
          'coach',
          'videoDetail',
          'recording',
          'stateLogs' => fn ($query) => $query->latest('created_at')->limit(10),
          'introRequest.preferredCoach:id,name,title,timezone',
          'introRequest.assignedCoach:id,name,title,timezone',
      ]);

      return $this->success([
          'session' => $this->serializeSession($fresh, true),
          'video_join_url' => optional($fresh->videoDetail)->video_join_url,
      ], 'Session started');
  }

  private function serializeSession(CoachingSession $session, bool
$includeStateLogs = false): array
  {
      $recording = $session->recording;
      $introRequest = $session->introRequest;
      $coachTimezone = Timezone::normalize($session->coach?->timezone, 'UTC');
      $clientTimezone = Timezone::normalize($session->scheduled_timezone ?:
($introRequest?->viewer_timezone ?: 'UTC'), 'UTC');
      $normalizedStatus = $this->normalizeState((string) $session->status);

      $payload = [
          'id' => (int) $session->id,
          'status' => $normalizedStatus,

          'raw_status' => $session->status,
          'scheduled_time' => optional($session->scheduled_time)?->toISOString(),
          'scheduled_time_local' => optional($session->scheduled_time)?->copy()->setTimezone($clientTimezone)->toIso8601String(),
          'duration_minutes' => (int) ($session->duration_minutes ?? 15),
          'client_name' => optional($session->client)->name,
          'timezone_context' => [
              'display_timezone' => $clientTimezone,
              'viewer_timezone' => $clientTimezone,
              'coach_timezone' => $coachTimezone,
              'client_requested_timezone' => $clientTimezone,
              'scheduled_time_for_viewer' => optional($session->scheduled_time)?->copy()->setTimezone($clientTimezone)->toIso8601String(),
              'scheduled_time_for_coach' => optional($session->scheduled_time)?->copy()->setTimezone($coachTimezone)->toIso8601String(),
          ],
          'client' => $session->client ? [
              'id' => (int) $session->client->id,
              'name' => $session->client->name,
              'email' => $session->client->email,
          ] : null,
          'video_detail' => $session->videoDetail ? [
              'video_join_url' => $session->videoDetail->video_join_url,
              'daily_room_name' => $session->videoDetail->daily_room_name,
              'room_created_at' => optional($session->videoDetail->room_created_at)?->toISOString(),
          ] : null,
          'created_at' => optional($session->created_at)?->toISOString(),
          'is_intro_session' => (bool) $session->is_intro_session,
          'coach_notes' => $session->coach_notes,
          'recording' => $recording ? [
              'transcription_status' => $recording->transcription_status,
              'transcript' => $recording->transcript,
              'transcript_available' => filled($recording->transcript),
              'transcript_preview' => filled($recording->transcript) ?
Str::limit((string) $recording->transcript, 220) : null,
              'transcript_word_count' => str_word_count((string) $recording->transcript),
              'summary_ready' => filled($recording->post_session_summary) ||
filled($recording->ai_summary) || filled($recording->pre_session_summary),
              'ai_summary' => $recording->ai_summary,
              'pre_session_summary' => $recording->pre_session_summary,
              'post_session_summary' => $recording->post_session_summary,
              'next_actions' => is_array($recording->next_actions) ?
$recording->next_actions : [],
              'key_topics' => is_array($recording->key_topics) ? $recording->key_topics : [],

               'privacy_settings' => $recording->privacy_settings,
               'feedback_rating' => $recording->feedback_rating,
           ] : null,
           'source_request' => $introRequest ? [
               'id' => (int) $introRequest->id,
               'status' => $introRequest->status,
               'goal_summary' => $introRequest->goal_summary,
               'request_notes' => $introRequest->request_notes,
               'admin_notes' => $introRequest->admin_notes,
               'viewer_timezone' => $introRequest->viewer_timezone,
               'preferred_coach' => $introRequest->preferredCoach ? [
                   'id' => (int) $introRequest->preferredCoach->id,
                   'name' => $introRequest->preferredCoach->name,
                   'title' => $introRequest->preferredCoach->title,
               ] : null,
               'assigned_coach' => $introRequest->assignedCoach ? [
                   'id' => (int) $introRequest->assignedCoach->id,
                   'name' => $introRequest->assignedCoach->name,
                   'title' => $introRequest->assignedCoach->title,
               ] : null,
           ] : null,
      ];

      if ($includeStateLogs) {
          $payload['state_logs'] = $session->stateLogs->map(fn
(SessionStateLog $log) => [
              'id' => (int) $log->id,
              'from_state' => $this->normalizeState($log->from_state),
              'to_state' => $this->normalizeState($log->to_state),
              'raw_from_state' => $log->from_state,
              'raw_to_state' => $log->to_state,
              'change_reason' => $log->change_reason,
              'metadata' => $log->metadata,
              'created_at' => optional($log->created_at)?->toISOString(),
          ])->values()->all();
      }

      return $payload;
  }

  private function normalizeState(?string $state): string
  {
      return match (trim(strtolower((string) $state))) {
          'in_progress' => 'live',
          'cancelled', 'no_show' => 'failed',
          default => trim(strtolower((string) $state)) ?: 'scheduled',
      };

    }
}
