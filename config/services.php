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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ferro' => [
        'base_url' => env('FERRO_API_BASE_URL', 'http://server.ferro.uz:8008/api'),
        'token' => env('FERRO_API_TOKEN'),
    ],

    'ferro_site_backend' => [
        'base_url' => env('FERRO_SITE_BACKEND_API_BASE_URL', 'https://ferro.uz/api'),
        'token' => env('FERRO_SITE_BACKEND_API_TOKEN'),
    ],

    'bitrix_webhook' => [
        'domain' => env('BITRIX_WEBHOOK_DOMAIN'),
        'application_token' => env('BITRIX_WEBHOOK_APPLICATION_TOKEN'),
    ],

];
