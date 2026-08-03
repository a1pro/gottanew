<?php
namespace App\Models\Coach;
use Illuminate\Database\Eloquent\Model;
class Coach extends Model
{
    protected $fillable = [
        'user_id','name','title','bio','avatar_url',
        'years_experience','specialties',
        'qualifications',
        'expertise_areas',
        'coaching_philosophy',
        'interests_and_personality',
        'preferred_client_types',
        'industries',
        'preferred_challenges',
        'similar_experiences',
        'rating','total_reviews','availability_hours',
        'timezone','website',
        'social_links','is_active','available_now',
        'calendar_link','notification_email','notification_phone',
        'coaching_expertise','coaching_style',
        'client_challenge_example','personal_experiences',
        'hourly_rate_amount','hourly_rate_currency',
        'hourly_coin_cost','booking_buffer_minutes',
        'max_session_duration','min_session_duration',
        'immediate_availability','response_preference_minutes',
        'languages',
        'community_involvement',
    ];

    protected $casts = [
        'specialties' => 'array',
        'expertise_areas' => 'array',
        'preferred_client_types' => 'array',
        'industries' => 'array',
        'languages' => 'array',
        'similar_experiences' => 'array',
        'social_links' => 'array',
        'is_active' => 'boolean',
        'available_now' => 'boolean'
    ];

    public function sessions()
    {
        return $this->hasMany(\App\Models\Session\CoachingSession::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function packages()
    {
        return $this->hasMany(CoachingPackage::class);
    }

    public function availabilities()
    {
        return $this->hasMany(CoachAvailability::class, 'coach_id');
    }

    public function coachApplication()
    {
        return $this->hasOne(\App\Models\Coach\PendingCoachApplication::class, 'email', 'email');
    }
}