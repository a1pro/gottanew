<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Goal extends Model
{
    protected $fillable = [
        'goal_id', // Changed from 'id' to 'goal_id'
        'title',
        'description', 
        'icon',
        'color'
    ];
 
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
 
    /**
     * Get the route key for the model (goal_id instead of id)
     */
    public function getRouteKeyName()
    {
        return 'goal_id';
    }
 
    public function coachMatches()
    {
        return $this->hasMany(CoachMatch::class);
    }
 
    public function userResponses()
    {
        return $this->hasMany(UserResponse::class);
    }
}