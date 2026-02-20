<?php
// app/Models/User.php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'last_login_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function coachProfile()
    {
        return $this->hasOne(CoachProfile::class);
    }

    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function sessionsAsClient()
    {
        return $this->hasMany(CoachingSession::class, 'client_id');
    }

    public function sessionsAsCoach()
    {
        return $this->hasMany(CoachingSession::class, 'coach_id');
    }

    public function coachAvailability()
    {
        return $this->hasMany(CoachAvailability::class, 'coach_id');
    }

    public function coachMatches()
    {
        return $this->hasMany(CoachMatch::class, 'client_id');
    }

    public function questionnaire()
    {
        return $this->hasOne(ClientQuestionnaire::class, 'client_id');
    }

    /**
     * Role Helper Methods
     */
    public function hasRole($roleSlug)
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function hasAnyRole(array $roles)
    {
        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isCoach()
    {
        return $this->hasRole('coach');
    }

    public function isClient()
    {
        return $this->hasRole('client');
    }

    /**
     * Get primary role (first role)
     */
    public function getPrimaryRoleAttribute()
    {
        return $this->roles->first()?->slug;
    }

    /**
     * Get all role names
     */
    public function getRoleNamesAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Check if user is active
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for users with specific role
     */
    public function scopeWithRole($query, $roleSlug)
    {
        return $query->whereHas('roles', function($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        });
    }
}