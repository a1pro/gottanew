<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ManagedResource extends Model
{
    protected $fillable = [
        'title',
        'description',
        'metadata',
        'url',
        'resource_type',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}