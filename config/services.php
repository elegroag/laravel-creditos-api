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

    'external_api' => [
        'url' => env('EXTERNAL_API_BASE_URL', 'https://api.example.com'),
        'auth_type' => env('EXTERNAL_API_TYPE', 'Basic'),
        'user' => env('EXTERNAL_API_USER'),
        'password' => env('EXTERNAL_API_PASSWORD'),
        'timeout' => env('EXTERNAL_API_TIMEOUT', 30),
    ],

    'generador_pdf_api' => [
        'url' => env('GENERATE_PDF_API_BASE_URL', 'https://api.example.com'),
        'auth_type' => env('GENERATE_PDF_API_TYPE', 'Basic'),
        'user' => env('GENERATE_PDF_API_USER'),
        'password' => env('GENERATE_PDF_API_PASSWORD'),
        'timeout' => env('GENERATE_PDF_API_TIMEOUT', 30),
    ],

    'firma_plus' => [
        'url' => env('FIRMAPLUS_API_URL', 'https://api.firmaplus.com'),
        'api_key' => env('FIRMAPLUS_API_KEY'),
        'webhook_secret' => env('FIRMAPLUS_WEBHOOK_SECRET'),
        'timeout' => env('FIRMAPLUS_TIMEOUT', 30),
        'verify_ssl' => env('FIRMAPLUS_VERIFY_SSL', true),
    ],

];
