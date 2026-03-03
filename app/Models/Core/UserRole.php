<?php
namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'assigned_by',
        'assigned_at'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}