<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | This is the single source of truth for all field mapping configurations
    | in the wound care application. It defines how data from various sources
    | (FHIR, forms, database) maps to manufacturer-specific IVR templates.
    |
    */

    'manufacturers' => [
        'ACZ' => [
            'id' => 1,
            'name' => 'ACZ',
            'template_id' => env('DOCUSEAL_ACZ_TEMPLATE_ID', '852440'),
            'signature_required' => true,
            'has_order_form' => false,
            'duration_requirement' => 'greater_than_4_weeks',
            'fields' => [
                // Patient Information
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_first_name' => [
                    'source' => 'patient_first_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_last_name' => [
                    'source' => 'patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_dob' => [
                    'source' => 'patient_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'patient_gender' => [
                    'source' => 'patient_gender',
                    'required' => false,
                    'type' => 'string'
                ],
                'patient_phone' => [
                    'source' => 'patient_phone',
                    'transform' => 'phone:US',
                    'required' => true,
                    'type' => 'phone'
                ],
                'patient_email' => [
                    'source' => 'patient_email',
                    'required' => false,
                    'type' => 'email'
                ],
                'patient_address' => [
                    'source' => 'computed',
                    'computation' => 'patient_address_line1 + patient_address_line2',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_city' => [
                    'source' => 'patient_city',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_state' => [
                    'source' => 'patient_state',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_zip' => [
                    'source' => 'patient_zip',
                    'required' => true,
                    'type' => 'zip'
                ],
                
                // Insurance Information
                'insurance_name' => [
                    'source' => 'primary_insurance_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'member_id' => [
                    'source' => 'primary_member_id',
                    'required' => true,
                    'type' => 'string'
                ],
                'plan_type' => [
                    'source' => 'primary_plan_type',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Clinical Information
                'diagnosis_code' => [
                    'source' => 'primary_diagnosis_code || diagnosis_code',
                    'required' => true,
                    'type' => 'string'
                ],
                'wound_type' => [
                    'source' => 'wound_type',
                    'required' => true,
                    'type' => 'string'
                ],
                'wound_location' => [
                    'source' => 'wound_location',
                    'required' => true,
                    'type' => 'string'
                ],
                'wound_size' => [
                    'source' => 'computed',
                    'computation' => 'wound_size_length * wound_size_width',
                    'transform' => 'number:2',
                    'required' => true,
                    'type' => 'number'
                ],
                'wound_duration' => [
                    'source' => 'computed',
                    'computation' => 'format_duration',
                    'required' => true,
                    'type' => 'string'
                ],
                
                // Provider Information
                'provider_name' => [
                    'source' => 'provider_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'provider_npi' => [
                    'source' => 'provider_npi',
                    'required' => true,
                    'type' => 'npi'
                ],
                'provider_email' => [
                    'source' => 'provider_email',
                    'required' => false,
                    'type' => 'email'
                ],
                'facility_name' => [
                    'source' => 'facility_name',
                    'required' => true,
                    'type' => 'string'
                ],
                
                // Hospice Information
                'hospice_status' => [
                    'source' => 'hospice_status',
                    'transform' => 'boolean:yes_no',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'hospice_family_consent' => [
                    'source' => 'hospice_family_consent',
                    'transform' => 'boolean:yes_no',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'hospice_clinically_necessary' => [
                    'source' => 'hospice_clinically_necessary',
                    'transform' => 'boolean:yes_no',
                    'required' => false,
                    'type' => 'boolean'
                ],
            ]
        ],
        
        'Advanced Health' => [
            'id' => 2,
            'name' => 'Advanced Health',
            'template_id' => env('DOCUSEAL_ADVANCED_HEALTH_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => true,
            'fields' => [
                // Similar structure to ACZ with manufacturer-specific fields
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                // ... additional fields specific to Advanced Health
            ]
        ],
        
        'MedLife' => [
            'id' => 3,
            'name' => 'MedLife',
            'template_id' => env('DOCUSEAL_MEDLIFE_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // MedLife specific field mappings
            ]
        ],
        
        'Centurion' => [
            'id' => 4,
            'name' => 'Centurion Therapeutics',
            'template_id' => env('DOCUSEAL_CENTURION_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // Centurion specific field mappings
            ]
        ],
        
        'BioWerX' => [
            'id' => 5,
            'name' => 'BioWerX',
            'template_id' => env('DOCUSEAL_BIOWERX_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // BioWerX specific field mappings
            ]
        ],
        
        'BioWound' => [
            'id' => 6,
            'name' => 'BioWound',
            'template_id' => env('DOCUSEAL_BIOWOUND_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // BioWound specific field mappings
            ]
        ],
        
        'Extremity Care' => [
            'id' => 7,
            'name' => 'Extremity Care',
            'template_id' => env('DOCUSEAL_EXTREMITY_CARE_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => true,
            'fields' => [
                // Extremity Care specific field mappings
            ]
        ],
        
        'SKYE Biologics' => [
            'id' => 8,
            'name' => 'SKYE Biologics',
            'template_id' => env('DOCUSEAL_SKYE_BIOLOGICS_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // SKYE Biologics specific field mappings
            ]
        ],
        
        'Total Ancillary' => [
            'id' => 9,
            'name' => 'Total Ancillary',
            'template_id' => env('DOCUSEAL_TOTAL_ANCILLARY_TEMPLATE_ID', ''),
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // Total Ancillary specific field mappings
            ]
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Field Transformers
    |--------------------------------------------------------------------------
    |
    | Define how different field types should be transformed.
    |
    */
    'transformers' => [
        'date' => [
            'm/d/Y' => 'convertToMDY',
            'Y-m-d' => 'convertToISO',
            'd/m/Y' => 'convertToDMY',
        ],
        'phone' => [
            'US' => 'formatUSPhone',
            'E164' => 'formatE164Phone',
        ],
        'boolean' => [
            'yes_no' => 'booleanToYesNo',
            '1_0' => 'booleanToNumeric',
            'true_false' => 'booleanToString',
        ],
        'number' => [
            '0' => 'roundToInteger',
            '2' => 'roundToTwoDecimals',
        ],
        'text' => [
            'upper' => 'toUpperCase',
            'lower' => 'toLowerCase',
            'title' => 'toTitleCase',
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Field Aliases
    |--------------------------------------------------------------------------
    |
    | Common field name variations that should map to the same field.
    |
    */
    'field_aliases' => [
        'patient_first_name' => ['first_name', 'fname', 'patient_fname', 'firstName'],
        'patient_last_name' => ['last_name', 'lname', 'patient_lname', 'lastName'],
        'patient_dob' => ['date_of_birth', 'dob', 'birth_date', 'birthDate'],
        'patient_phone' => ['phone', 'phone_number', 'telephone', 'contact_phone'],
        'patient_email' => ['email', 'email_address', 'contact_email'],
        'primary_insurance_name' => ['insurance_name', 'payer_name', 'insurance_company'],
        'primary_member_id' => ['member_id', 'subscriber_id', 'insurance_id'],
        'provider_npi' => ['npi', 'provider_number', 'npi_number'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Regular expressions for validating field formats.
    |
    */
    'validation_rules' => [
        'phone' => '/^\d{10}$/',
        'zip' => '/^\d{5}(-\d{4})?$/',
        'npi' => '/^\d{10}$/',
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Fuzzy Matching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the fuzzy field matching algorithm.
    |
    */
    'fuzzy_matching' => [
        'confidence_threshold' => 0.7,
        'exact_match_boost' => 1.5,
        'semantic_match_boost' => 1.2,
        'fuzzy_match_boost' => 1.0,
        'pattern_match_boost' => 1.1,
        'cache_ttl' => 3600, // 1 hour
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Product to Manufacturer Mapping
    |--------------------------------------------------------------------------
    |
    | Maps product codes to their manufacturers.
    |
    */
    'product_mappings' => [
        'EMP001' => 'ACZ',
        'EMP002' => 'ACZ',
        'SKN001' => 'Advanced Health',
        'SKN002' => 'Advanced Health',
        'MDL001' => 'MedLife',
        'MDL002' => 'MedLife',
        'CTN001' => 'Centurion',
        'BWX001' => 'BioWerX',
        'BWD001' => 'BioWound',
        'EXT001' => 'Extremity Care',
        'EXT002' => 'Extremity Care',
        'SKY001' => 'SKYE Biologics',
        'TAC001' => 'Total Ancillary',
    ],
];