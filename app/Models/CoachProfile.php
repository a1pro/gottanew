<?php
// app/Models/CoachProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachProfile extends Model
{
    protected $fillable = [
        'user_id', 'bio', 'expertise', 'coaching_styles', 'hourly_rate',
        'languages', 'certifications', 'education', 'experience_years',
        'rating', 'total_sessions', 'is_approved', 'onboarding_completed'
    ];

    protected $casts = [
        'expertise' => 'array',
        'coaching_styles' => 'array',
        'languages' => 'array',
        'certifications' => 'array',
        'education' => 'array',
        'is_approved' => 'boolean',
        'onboarding_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}