<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── Google (Gmail + Calendar adapters) ──────────────────────────────────
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    // ── Zoho (Cliq adapter for notifications / messaging) ───────────────────
    'zoho' => [
        'client_id'     => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
    ],

    // ── Meta / Facebook (Ads adapter) ────────────────────────────────────────
    'meta' => [
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    ],

    // ── WhatsApp (notification channel) ─────────────────────────────────────
    'whatsapp' => [
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
    ],

];
