<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTask extends Model
{
    //
    protected $fillable = [
        'user_id',
        'goal_id',
        'title',
        'is_completed',
        'completed_at'
    ];
}
