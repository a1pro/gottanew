<?php

namespace App\Models\Webhook;

use Illuminate\Database\Eloquent\Model;

class WebhookEventReceipt extends Model
{
     protected $table = 'webhook_event_receipts';

     public $timestamps = false;

     protected $fillable = [
         'provider_name',
         'provider_event_id',
         'event_type',
         'room_name',
         'session_id',
         'payload',
         'processing_error',
         'received_at',
         'processed_at',
     ];

     protected $casts = [
         'payload' => 'array',
         'received_at' => 'datetime',
         'processed_at' => 'datetime',
     ];
}
