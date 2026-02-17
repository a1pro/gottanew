<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar', 'is_active', 'last_login_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Relationships
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

    // Helper methods
    public function hasRole($roleSlug)
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
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

    // Get primary role (for simple checking)
    public function getPrimaryRoleAttribute()
    {
        return $this->roles->first()?->slug;
    }
}