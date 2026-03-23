<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionRecording extends Model
{
    protected $fillable = [
        'session_id',
        'recording_url',
        'transcript',
        'ai_summary',
        'pre_session_summary',
        'post_session_summary',
        'next_actions',
        'pre_session_generated_at',
        'post_session_generated_at',
        'duration_seconds',
        'file_size_bytes',
        'sentiment_analysis',
        'key_topics',
        'personality_insights',
        'emotional_journey',
        'coaching_effectiveness_score',
        'transcription_status',
        'transcription_paused_segments',
        'privacy_settings',
        'feedback_rating',
        'feedback_notes',
        'feedback_submitted_by_user_id',
    ];

    protected $casts = [
        'next_actions' => 'array',
        'sentiment_analysis' => 'array',
        'key_topics' => 'array',
        'personality_insights' => 'array',
        'emotional_journey' => 'array',
        'transcription_paused_segments' => 'array',
        'privacy_settings' => 'array',
        'coaching_effectiveness_score' => 'decimal:2',
        'pre_session_generated_at' => 'datetime',
        'post_session_generated_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }
}