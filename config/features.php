<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file contains feature flags that can be used to enable/disable
    | specific functionality in the application. This is particularly useful
    | for safely rolling out new features or rolling back problematic ones.
    |
    */

    'fhir' => [
        /*
        |--------------------------------------------------------------------------
        | FHIR Operations Feature Flags
        |--------------------------------------------------------------------------
        |
        | These flags control whether FHIR operations are enabled for each handler.
        | When disabled, handlers will fall back to generating local IDs.
        |
        */

        'patient_handler_enabled' => env('FHIR_PATIENT_HANDLER_ENABLED', false),
        'provider_handler_enabled' => env('FHIR_PROVIDER_HANDLER_ENABLED', false),
        'insurance_handler_enabled' => env('FHIR_INSURANCE_HANDLER_ENABLED', false),
        'clinical_handler_enabled' => env('FHIR_CLINICAL_HANDLER_ENABLED', false),
        'order_handler_enabled' => env('FHIR_ORDER_HANDLER_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | FHIR Cache Operations
        |--------------------------------------------------------------------------
        |
        | Controls whether episode cache warming is enabled. This was causing
        | transaction rollbacks due to provider_fhir_id issues.
        |
        */

        'episode_cache_warming_enabled' => env('FHIR_EPISODE_CACHE_WARMING_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | FHIR Connection Settings
        |--------------------------------------------------------------------------
        |
        | General FHIR service configuration flags.
        |
        */

        'enabled' => env('FHIR_ENABLED', false),
        'service_enabled' => env('FHIR_SERVICE_ENABLED', false),
        'debug_mode' => env('FHIR_DEBUG_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Other Feature Flags
    |--------------------------------------------------------------------------
    |
    | Additional feature flags for other parts of the application.
    |
    */

    'pdf' => [
        'integration_enabled' => env('PDF_INTEGRATION_ENABLED', true),
        'webhook_processing_enabled' => env('PDF_WEBHOOK_PROCESSING_ENABLED', true),
    ],

    'polling' => [
        'order_polling_enabled' => env('ORDER_POLLING_ENABLED', true),
        'real_time_updates_enabled' => env('REAL_TIME_UPDATES_ENABLED', true),
    ],
];