<?php
namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'bio',
        'phone',
        'notification_method',
        'email_verified',
        'legal_version',
        'terms_accepted_at',
        'privacy_policy_accepted_at',
        'coaching_disclaimer_accepted_at',
        'coach_independence_acknowledged_at',
        'personality_traits',
        'communication_style',
        'engagement_patterns',
        'learning_preferences',
        'motivation_triggers',
        'success_patterns',
        'preferred_session_times',
        'coaching_history_summary',
        'total_sessions_count',
        'average_session_rating',
        'last_session_at'
    ];

    protected $casts = [
        'personality_traits' => 'array',
        'communication_style' => 'array',
        'engagement_patterns' => 'array',
        'learning_preferences' => 'array',
        'motivation_triggers' => 'array',
        'success_patterns' => 'array',
        'preferred_session_times' => 'array',
        'email_verified' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'privacy_policy_accepted_at' => 'datetime',
        'coaching_disclaimer_accepted_at' => 'datetime',
        'coach_independence_acknowledged_at' => 'datetime',
        'last_session_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}