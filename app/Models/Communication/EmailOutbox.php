<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOutbox extends Model
{
    use HasFactory;

    protected $table = 'email_outbox';

    protected $fillable = [
        'dedup_key',
        'template_name',
        'recipient_email',
        'recipient_name',
        'subject',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'last_attempt_at',
        'sent_at',
        'scheduled_for',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
