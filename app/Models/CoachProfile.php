<?php
// app/Models/CoachProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachProfile extends Model
{
    protected $fillable = [
        'user_id', 'bio', 'expertise', 'coaching_styles', 'availability_preferences',
        'hourly_rate', 'total_sessions', 'rating', 'is_approved',
        'ethics_acknowledged', 'ethics_acknowledged_at', 'boundaries',
        'onboarding_completed'
    ];

    protected $casts = [
        'expertise' => 'array',
        'coaching_styles' => 'array',
        'availability_preferences' => 'array',
        'boundaries' => 'array',
        'is_approved' => 'boolean',
        'ethics_acknowledged' => 'boolean',
        'onboarding_completed' => 'boolean',
        'ethics_acknowledged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availability()
    {
        return $this->hasMany(CoachAvailability::class, 'coach_id', 'user_id');
    }
}