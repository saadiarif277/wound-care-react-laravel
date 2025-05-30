<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Availity API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Availity Coverages API integration.
    | Used for real-time eligibility verification.
    |
    */

    'api_base_url' => env('AVAILITY_API_BASE_URL', 'https://api.availity.com/availity/development-partner/v1'),

    'client_id' => env('AVAILITY_CLIENT_ID'),
    'client_secret' => env('AVAILITY_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Payer Mappings
    |--------------------------------------------------------------------------
    |
    | Maps common payer names to Availity payer IDs
    |
    */
    'payer_mappings' => [
        'Medicare' => 'MEDICARE',
        'Medicaid' => 'MEDICAID',
        'Aetna' => 'AETNA',
        'Blue Cross Blue Shield' => 'BCBS',
        'BCBS' => 'BCBS',
        'Humana' => 'HUMANA',
        'UnitedHealthcare' => 'UHC',
        'United Healthcare' => 'UHC',
        'Cigna' => 'CIGNA',
        'Anthem' => 'ANTHEM',
        'WellCare' => 'WELLCARE',
        'Molina Healthcare' => 'MOLINA',
        'Centene' => 'CENTENE',
        'Kaiser Permanente' => 'KAISER',
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Type Mappings
    |--------------------------------------------------------------------------
    |
    | Maps wound types to Availity service type codes
    |
    */
    'service_type_mappings' => [
        'DFU' => '30', // Durable Medical Equipment
        'VLU' => '30',
        'PU' => '30',
        'TW' => '30',
        'AU' => '30',
        'OTHER' => '30',
        'skin_substitute' => '30',
        'wound_care' => '1', // Medical Care
        'surgical_dressing' => '30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Type Mappings
    |--------------------------------------------------------------------------
    |
    | Maps provider specialties to Availity provider type codes
    |
    */
    'provider_type_mappings' => [
        'physician' => '1', // Person
        'podiatrist' => '1',
        'wound_specialist' => '1',
        'nurse_practitioner' => '1',
        'physician_assistant' => '1',
        'facility' => '2', // Non-Person Entity
        'hospital' => '2',
        'clinic' => '2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gender Mappings
    |--------------------------------------------------------------------------
    |
    | Maps internal gender values to Availity expected values
    |
    */
    'gender_mappings' => [
        'male' => 'M',
        'female' => 'F',
        'other' => 'U',
        'unknown' => 'U',
        'M' => 'M',
        'F' => 'F',
        'U' => 'U',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Timeouts and Retries
    |--------------------------------------------------------------------------
    */
    'timeout' => 30, // seconds
    'max_retries' => 3,
    'retry_delay' => 1000, // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configuration for caching eligibility results
    |
    */
    'cache' => [
        'enabled' => env('AVAILITY_CACHE_ENABLED', true),
        'ttl' => env('AVAILITY_CACHE_TTL', 3600), // 1 hour in seconds
        'prefix' => 'availity_eligibility_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('AVAILITY_LOGGING_ENABLED', true),
        'level' => env('AVAILITY_LOG_LEVEL', 'info'),
        'include_request_body' => env('AVAILITY_LOG_REQUEST_BODY', false),
        'include_response_body' => env('AVAILITY_LOG_RESPONSE_BODY', false),
    ],
];
