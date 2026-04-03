<?php

namespace Tests\Feature;

use App\Jobs\SyncDailyTranscriptJob;
use App\Models\Coach\Coach;
use App\Models\Session\CoachingSession;
use App\Models\Session\SessionVideoDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DailyWebhookTranscriptTest extends TestCase
{
    use RefreshDatabase;

     public function test_accepts_verification_requests(): void
     {
         $response = $this->postJson('/api/v1/webhooks/daily', ['test' => true]);

      $response->assertOk();
      $response->assertJson([
          'ok' => true,
          'verification' => true,
      ]);
  }

  public function
test_transcript_ready_event_updates_recording_and_dispatches_sync_job(): void
  {
      Queue::fake();

      $client = User::factory()->create();
      $coachUser = User::factory()->create();
      $coach = Coach::create([
          'user_id' => $coachUser->id,
          'name' => 'Coach',
          'title' => 'Coach',
          'bio' => 'Bio',
          'years_experience' => 1,
          'specialties' => [],
          'similar_experiences' => [],
          'timezone' => 'UTC',
          'hourly_rate_amount' => 100,
          'hourly_rate_currency' => 'USD',
          'hourly_coin_cost' => 100,
          'booking_buffer_minutes' => 0,
          'max_session_duration' => 60,
          'min_session_duration' => 15,
          'immediate_availability' => false,
          'available_now' => false,
          'is_active' => true,
          'response_preference_minutes' => 5,
      ]);

      $session = CoachingSession::create([
            'client_id' => $client->id,
            'coach_id' => $coach->id,
            'scheduled_time' => now()->addHour(),
            'scheduled_timezone' => 'UTC',
            'status' => 'scheduled',
            'duration_minutes' => 15,
            'price_amount' => 0,
            'price_currency' => 'TOKEN',
      ]);

      SessionVideoDetail::create([

                'session_id' => $session->id,
                'daily_room_name' => 'room-test',
                'video_join_url' => 'https://example.invalid/room-test',
          ]);

          $event = [
              'id' => 'evt_test_1',
              'type' => 'transcript.ready-to-download',
              'payload' => [
                     'id' => 'tr_test_1',
                     'room_name' => 'room-test',
                     'instanceId' => 'inst_1',
                     'duration' => 120,
                     'status' => 't_finished',
                ],
          ];

          $response = $this->postJson('/api/v1/webhooks/daily', $event);

          $response->assertOk();
          $response->assertJson(['ok' => true]);

        $session->refresh();
        $this->assertNotNull($session->recording);
        $this->assertSame('tr_test_1', $session->recording->daily_transcript_id);
        $this->assertSame('completed', $session->recording->transcription_status);

        Queue::assertPushed(SyncDailyTranscriptJob::class, function
(SyncDailyTranscriptJob $job) use ($session) {
            return $job->sessionId === (int) $session->id;
        });
    }
}
