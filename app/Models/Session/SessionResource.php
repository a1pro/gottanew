<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;
use App\Models\ManagedResource;

class SessionResource extends Model
{
    protected $fillable = [
        'session_id',
        'managed_resource_id',
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

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function managedResource()
    {
        return $this->belongsTo(ManagedResource::class);
    }
}
