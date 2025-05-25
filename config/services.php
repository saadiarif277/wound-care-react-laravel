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

    'azure' => [
        'tenant_id' => env('AZURE_TENANT_ID'),
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'fhir_endpoint' => env('AZURE_FHIR_ENDPOINT'),
        'key_vault' => [
            'vault_url' => env('AZURE_KEY_VAULT_URL'),
            'use_managed_identity' => env('AZURE_USE_MANAGED_IDENTITY', false),
        ],
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'anon_key' => env('SUPABASE_ANON_KEY'),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ecw' => [
        'client_id' => env('ECW_CLIENT_ID'),
        'client_secret' => env('ECW_CLIENT_SECRET'),
        'redirect_uri' => env('ECW_REDIRECT_URI'),
        'scope' => env('ECW_SCOPE', 'patient/read patient/write'),
        'environment' => env('ECW_ENVIRONMENT', 'sandbox'),
        'sandbox_endpoint' => env('ECW_SANDBOX_ENDPOINT'),
        'production_endpoint' => env('ECW_PRODUCTION_ENDPOINT'),
        'jwk_public_key' => env('ECW_JWK_PUBLIC_KEY'),
        'jwk_private_key' => env('ECW_JWK_PRIVATE_KEY'),
    ],

];
