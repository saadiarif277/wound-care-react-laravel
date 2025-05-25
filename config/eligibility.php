<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Optum Enhanced Eligibility API Configuration
    |--------------------------------------------------------------------------
    */

    'api_base_url' => env('OPTUM_API_BASE_URL', 'https://sandbox-apigw.optum.com'),
    'client_id' => env('OPTUM_CLIENT_ID'),
    'client_secret' => env('OPTUM_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */

    'endpoints' => [
        'token' => '/apip/auth/v2/token',
        'eligibility' => '/rcm/eligibility/v1',
        'coverage_discovery' => '/rcm/eligibility/v1/coverage-discovery',
        'transactions' => '/rcm/eligibility/v1/transactions',
        'healthcheck' => '/rcm/eligibility/v1/healthcheck',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Scopes
    |--------------------------------------------------------------------------
    */

    'scopes' => [
        'create_txn',
        'read_txn',
        'create_coveragediscovery',
        'read_coveragediscovery',
        'read_healthcheck',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */

    'timeout' => env('ELIGIBILITY_API_TIMEOUT', 30),
    'dry_run' => env('ELIGIBILITY_DRY_RUN', true),
    'max_retries' => env('ELIGIBILITY_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Provider Information (MSC)
    |--------------------------------------------------------------------------
    */

    'provider' => [
        'organization_name' => env('ELIGIBILITY_PROVIDER_NAME', 'MSC Wound Care'),
        'npi' => env('ELIGIBILITY_PROVIDER_NPI'),
        'service_provider_number' => env('ELIGIBILITY_PROVIDER_SERVICE_NUMBER'),
        'provider_code' => env('ELIGIBILITY_PROVIDER_CODE', 'AT'), // Attending
        'tax_id' => env('ELIGIBILITY_PROVIDER_TAX_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trading Partner Configuration
    |--------------------------------------------------------------------------
    */

    'trading_partner' => [
        'service_id' => env('ELIGIBILITY_TRADING_PARTNER_SERVICE_ID'),
        'name' => env('ELIGIBILITY_TRADING_PARTNER_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coverage Discovery Settings
    |--------------------------------------------------------------------------
    */

    'coverage_discovery' => [
        'enabled' => env('ELIGIBILITY_COVERAGE_DISCOVERY_ENABLED', false),
        'callback_url' => env('ELIGIBILITY_CALLBACK_URL'),
        'polling_interval' => env('ELIGIBILITY_POLLING_INTERVAL', 30), // seconds
        'max_polling_attempts' => env('ELIGIBILITY_MAX_POLLING_ATTEMPTS', 120), // 1 hour max
        'dry_run' => env('ELIGIBILITY_COVERAGE_DISCOVERY_DRY_RUN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'tenant_id' => env('OPTUM_TENANT_ID'),
        'correlation_id_prefix' => env('ELIGIBILITY_CORRELATION_ID_PREFIX', 'MSC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('ELIGIBILITY_LOGGING_ENABLED', true),
        'log_requests' => env('ELIGIBILITY_LOG_REQUESTS', true),
        'log_responses' => env('ELIGIBILITY_LOG_RESPONSES', true),
        'log_channel' => env('ELIGIBILITY_LOG_CHANNEL', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Type Code Mappings
    |--------------------------------------------------------------------------
    */

    'service_type_mappings' => [
        'wound_care' => '30', // Health benefit plan coverage
        'dfu' => '30', // Diabetic Foot Ulcer
        'vlu' => '30', // Venous Leg Ulcer
        'pu' => '30',  // Pressure Ulcer
        'tw' => '30',  // Traumatic Wound
        'au' => '30',  // Arterial Ulcer
        'dme' => '12', // Durable Medical Equipment Purchase
        'dme_rental' => '18', // Durable Medical Equipment Rental
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Mappings
    |--------------------------------------------------------------------------
    */

    'status_mappings' => [
        'eligible' => 'eligible',
        'ineligible' => 'ineligible',
        'payer_unavailable' => 'requires_review',
        'payer_not_in_system' => 'requires_review',
        'processing_error' => 'error',
        'patient_unknown' => 'ineligible',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'control_number_length' => 9,
        'submitter_transaction_identifier' => 'MSC',
        'purpose_code' => '11', // Request
        'individual_relationship_code' => '18', // Self
    ],
];
