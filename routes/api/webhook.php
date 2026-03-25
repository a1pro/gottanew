<?php

use App\Http\Controllers\Api\Webhook\DailyWebhookController;
use App\Http\Controllers\Api\Webhook\TwilioMessagingWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/daily', [DailyWebhookController::class, 'handle']);
Route::post('/webhooks/twilio/messaging-status', [TwilioMessagingWebhookController::class, 'status'])
    ->name('webhooks.twilio.messaging.status');
