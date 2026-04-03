<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'daily' => [
        'api_key' => env('DAILY_API_KEY'),
        'transcription_language' => env('DAILY_TRANSCRIPTION_LANGUAGE', 'en'),
        'transcription_model' => env('DAILY_TRANSCRIPTION_MODEL'),
        /**
         * Daily webhooks:
         * - Daily provides X-Webhook-Signature + X-Webhook-Timestamp headers.
         * - The hmac shared secret must be BASE64-encoded.
         * - See Daily docs: https://docs.daily.co/reference/rest-api/webhooks
         */
        'webhook_hmac' => env('DAILY_WEBHOOK_HMAC'),
        'webhook_max_age_seconds' => (int) env('DAILY_WEBHOOK_MAX_AGE_SECONDS', 300),
        'webhook_url' => env('DAILY_WEBHOOK_URL'),
        'webhook_retry_type' => env('DAILY_WEBHOOK_RETRY_TYPE', 'circuit-breaker'),
        /**
         * Transcription storage:
         * Daily defaults enable_transcription_storage=false unless explicitly enabled.
         * When enabled, Daily stores a WebVTT file and makes it downloadable via the REST API.
         */
        'enable_transcription_storage' =>
            filter_var(env('DAILY_ENABLE_TRANSCRIPTION_STORAGE', true), FILTER_VALIDATE_BOOL),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key' => env('TWILIO_API_KEY'),
        'api_secret' => env('TWILIO_API_SECRET'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'sms_from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'status_callback_url' => env('TWILIO_STATUS_CALLBACK_URL'),
        'use_sms_fallback_for_whatsapp' =>
            filter_var(env('TWILIO_USE_SMS_FALLBACK_FOR_WHATSAPP', true), FILTER_VALIDATE_BOOL),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:8080')),
    ],
];
