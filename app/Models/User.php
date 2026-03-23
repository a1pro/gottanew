<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Profile (One-to-One)
    public function profile()
    {
        return $this->hasOne(\App\Models\Core\Profile::class);
    }

    // Roles (One-to-Many)
    public function roles()
    {
        return $this->hasMany(\App\Models\Core\UserRole::class);
    }

    // Wallet (One-to-One)
    public function wallet()
    {
        return $this->hasOne(\App\Models\Finance\UserWallet::class);
    }

    // Transactions
    public function transactions()
    {
        return $this->hasMany(\App\Models\Finance\Transaction::class);
    }

    // Goals
    public function goals()
    {
        return $this->hasMany(\App\Models\Goal\UserGoal::class);
    }

    // Tasks
    public function tasks()
    {
        return $this->hasMany(\App\Models\Goal\UserTask::class);
    }

    // Coaching Sessions as Client
    public function clientSessions()
    {
        return $this->hasMany(
            \App\Models\Session\CoachingSession::class,
            'client_id'
        );
    }

    // If user is a Coach
   public function coachProfile()
    {
        return $this->hasOne(\App\Models\Coach\Coach::class, 'user_id');
    }
        // Session Participants
    public function sessionParticipations()
    {
        return $this->hasMany(
            \App\Models\Session\SessionParticipant::class
        );
    }

    // Behavioral Patterns
    public function behavioralPatterns()
    {
        return $this->hasMany(
            \App\Models\Analytics\UserBehavioralPattern::class
        );
    }

    // Conversation Themes
    public function conversationThemes()
    {
        return $this->hasMany(
            \App\Models\Analytics\ConversationTheme::class
        );
    }

    // Activity Logs
    public function activityLogs()
    {
        return $this->hasMany(
            \App\Models\Analytics\UserActivityLog::class
        );
    }

    public function notifications()
    {
        return $this->hasMany(\App\Models\Communication\UserNotification::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE HELPERS
    |--------------------------------------------------------------------------
    */

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isCoach(): bool
    {
        return $this->hasRole('coach');
    }

    public function isClient(): bool
    {
        return $this->hasRole('client');
    }
}