<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGoal extends Model
{
    //

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'status',
        'progress_percentage'
    ];
}
