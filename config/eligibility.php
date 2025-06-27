<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unified Eligibility Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file consolidates all eligibility-related settings
    | for the various payer APIs (Availity, Optum, Office Ally, etc.)
    |
    */

    'default' => env('ELIGIBILITY_DEFAULT_PROVIDER', 'availity'),
    
    'providers' => [
        'availity' => [
            'client_id' => env('AVAILITY_CLIENT_ID'),
            'client_secret' => env('AVAILITY_CLIENT_SECRET'),
            'environment' => env('AVAILITY_ENVIRONMENT', 'production'),
            'base_url' => env('AVAILITY_BASE_URL', 'https://api.availity.com'),
            'timeout' => env('AVAILITY_TIMEOUT', 30),
            'retry_attempts' => env('AVAILITY_RETRY_ATTEMPTS', 3),
            'cache_ttl' => env('AVAILITY_CACHE_TTL', 3600), // 1 hour
            'endpoints' => [
                'eligibility' => '/v1/eligibility',
                'auth' => '/v1/token',
                'payer_list' => '/v1/payers',
            ],
        ],
        
        'optum' => [
            'client_id' => env('OPTUM_CLIENT_ID'),
            'client_secret' => env('OPTUM_CLIENT_SECRET'),
            'base_url' => env('OPTUM_BASE_URL', 'https://api.optum.com'),
            'timeout' => env('OPTUM_TIMEOUT', 30),
            'retry_attempts' => env('OPTUM_RETRY_ATTEMPTS', 3),
            'endpoints' => [
                'eligibility' => '/api/eligibility/v3',
                'auth' => '/oauth2/token',
            ],
        ],
        
        'officeally' => [
            'username' => env('OFFICEALLY_USERNAME'),
            'password' => env('OFFICEALLY_PASSWORD'),
            'base_url' => env('OFFICEALLY_BASE_URL', 'https://api.officeally.com'),
            'timeout' => env('OFFICEALLY_TIMEOUT', 30),
            'endpoints' => [
                'eligibility' => '/api/v2/eligibility',
            ],
        ],
    ],
    
    'payer_routing' => [
        // Map payer IDs to preferred providers
        'medicare' => 'optum',
        'medicaid' => 'availity',
        'bcbs' => 'availity',
        'aetna' => 'availity',
        'uhc' => 'optum',
        'default' => 'availity',
    ],
    
    'field_mappings' => [
        // Map internal fields to provider-specific fields
        'member_id' => [
            'availity' => 'memberId',
            'optum' => 'subscriberID',
            'officeally' => 'insurance_id',
        ],
        'payer_id' => [
            'availity' => 'payerId',
            'optum' => 'payerCode',
            'officeally' => 'payer_code',
        ],
    ],
    
    'cache' => [
        'enabled' => env('ELIGIBILITY_CACHE_ENABLED', true),
        'prefix' => 'eligibility:',
        'ttl' => env('ELIGIBILITY_CACHE_TTL', 3600), // 1 hour default
    ],
    
    'logging' => [
        'enabled' => env('ELIGIBILITY_LOGGING_ENABLED', true),
        'channel' => env('ELIGIBILITY_LOG_CHANNEL', 'eligibility'),
        'include_request_data' => env('ELIGIBILITY_LOG_REQUESTS', false),
        'include_response_data' => env('ELIGIBILITY_LOG_RESPONSES', false),
    ],
];
