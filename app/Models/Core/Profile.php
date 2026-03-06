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
        'last_session_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}