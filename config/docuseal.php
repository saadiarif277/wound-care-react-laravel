<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Docuseal API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Docuseal e-signature integration
    |
    */

    'api_key' => env('DOCUSEAL_API_KEY'),
    'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),
    'account_email' => env('DOCUSEAL_ACCOUNT_EMAIL', 'limitless@mscwoundcare.com'),

    /*
    |--------------------------------------------------------------------------
    | Docuseal Request Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API requests and timeouts
    |
    */
    'timeout' => (int) env('DOCUSEAL_TIMEOUT', 30),
    'max_retries' => (int) env('DOCUSEAL_MAX_RETRIES', 3),
    'retry_delay' => (int) env('DOCUSEAL_RETRY_DELAY', 1000),

    /*
    |--------------------------------------------------------------------------
    | Docuseal Template Configuration
    |--------------------------------------------------------------------------
    |
    | Template IDs are now managed via the database (docuseal_templates table)
    | This provides better flexibility and avoids hardcoded values
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Docuseal Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mapping form fields to Docuseal template fields
    | This enables the 91% field pre-filling mentioned in documentation
    |
    */
    'field_mapping' => [
        // Common mappings that work across most manufacturers
        'patient_name' => ['patient_name', 'patient_full_name', 'full_name'],
        'patient_first_name' => ['first_name', 'patient_first_name', 'fname'],
        'patient_last_name' => ['last_name', 'patient_last_name', 'lname'],
        'patient_dob' => ['date_of_birth', 'dob', 'patient_dob'],
        'patient_address' => ['address', 'patient_address', 'street_address'],
        'patient_city' => ['city', 'patient_city'],
        'patient_state' => ['state', 'patient_state'],
        'patient_zip' => ['zip', 'zip_code', 'postal_code'],
        'patient_phone' => ['phone', 'patient_phone', 'phone_number'],
        'patient_email' => ['email', 'patient_email'],

        // Provider information
        'provider_name' => ['provider_name', 'physician_name', 'doctor_name'],
        'provider_npi' => ['npi', 'provider_npi', 'physician_npi'],
        'provider_phone' => ['provider_phone', 'physician_phone'],
        'provider_address' => ['provider_address', 'physician_address'],

        // Facility information
        'facility_name' => ['facility_name', 'clinic_name'],
        'facility_address' => ['facility_address', 'clinic_address'],
        'facility_phone' => ['facility_phone', 'clinic_phone'],

        // Insurance information
        'insurance_carrier' => ['insurance_carrier', 'insurance_company', 'payer'],
        'insurance_member_id' => ['member_id', 'insurance_id', 'subscriber_id'],
        'insurance_group' => ['group_number', 'insurance_group'],

        // Clinical information
        'diagnosis_codes' => ['diagnosis_codes', 'icd10_codes', 'primary_diagnosis'],
        'wound_type' => ['wound_type', 'wound_classification'],
        'wound_location' => ['wound_location', 'anatomical_location'],
        'wound_size' => ['wound_size', 'wound_dimensions'],
        'onset_date' => ['onset_date', 'date_of_onset', 'injury_date'],

        // Product information
        'product_name' => ['product_name', 'requested_product'],
        'product_code' => ['product_code', 'hcpcs_code'],
        'quantity' => ['quantity', 'units_requested'],

        // Service information
        'service_date' => ['service_date', 'date_of_service'],
        'treatment_frequency' => ['frequency', 'treatment_frequency'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Episode Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings specific to episode-centric workflow integration
    |
    */
    'episode_integration' => [
        'auto_populate_fields' => true,
        'require_manufacturer_match' => true,
        'validate_template_fields' => true,
        'field_coverage_threshold' => 0.8, // 80% field coverage minimum
        'enable_smart_mapping' => true,
    ],
];
