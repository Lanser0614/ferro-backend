<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base REST endpoint
    |--------------------------------------------------------------------------
    |
    | Provide the base URI for your Bitrix24 instance. For webhooks this should
    | look like: https://your-domain.bitrix24.com/rest/
    |
    */
    'base_uri' => env('BITRIX24_BASE_URI', ''),

    /*
    |--------------------------------------------------------------------------
    | OAuth token
    |--------------------------------------------------------------------------
    |
    | When using OAuth authentication supply the access token returned by
    | Bitrix24. If you use a webhook you can leave this empty.
    |
    */
    'auth_token' => env('BITRIX24_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook credentials
    |--------------------------------------------------------------------------
    |
    | Enter the Bitrix24 user ID and webhook code when calling the API via
    | incoming webhooks. The final endpoint will be composed as:
    | {base_uri}/{webhook_user}/{webhook_code}/{method}.json
    |
    */
    'webhook_user' => env('BITRIX24_WEBHOOK_USER'),
    'webhook_code' => env('BITRIX24_WEBHOOK_CODE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client options
    |--------------------------------------------------------------------------
    */
    'timeout' => env('BITRIX24_TIMEOUT', 10.0),

    'query' => [],

    'headers' => [
        'Accept' => 'application/json',
    ],
];
