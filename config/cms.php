<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Centers for Medicare & Medicaid Services (CMS) API
    | integration, including sample data mode for development.
    |
    */

    'api' => [
        'use_sample_data' => env('CMS_API_USE_SAMPLE_DATA', false),
    ],
];