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

    /*
    |--------------------------------------------------------------------------
    | Canonical Field Names
    |--------------------------------------------------------------------------
    |
    | These are the standardized field names that all manufacturer forms map to.
    | This allows us to have a single data structure regardless of how each
    | manufacturer names their fields.
    |
    */
    'canonical_fields' => [
        'patient_name', 'patient_first_name', 'patient_last_name', 'patient_dob',
        'patient_gender', 'patient_phone', 'patient_email', 'patient_address',
        'patient_city', 'patient_state', 'patient_zip', 'physician_name',
        'physician_npi', 'physician_ptan', 'facility_name', 'facility_npi',
        'facility_ptan', 'facility_address', 'insurance_name', 'policy_number',
        'member_id', 'plan_type', 'place_of_service', 'wound_location',
        'wound_size', 'wound_type', 'diagnosis_code', 'service_date'
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Aliases
    |--------------------------------------------------------------------------
    |
    | Maps various field name variations to canonical names
    |
    */
    'field_aliases' => [
        'provider_name' => 'physician_name',
        'provider_npi' => 'physician_npi',
        'practice_name' => 'facility_name',
        'clinic_name' => 'facility_name',
        'insurance_company' => 'insurance_name',
        'policy_id' => 'policy_number',
        'member_number' => 'member_id',
        'dos' => 'service_date',
        'date_of_service' => 'service_date',
    ],

    'manufacturers' => [
        'ACZ' => [
            'id' => 1,
            'name' => 'ACZ & Associates',
            'signature_required' => true,
            'has_order_form' => false,
            'duration_requirement' => 'greater_than_4_weeks',
            'docuseal_field_names' => [
                // Map canonical names to ACZ's specific field names
                'patient_name' => 'PATIENT NAME',
                'patient_dob' => 'PATIENT DOB',
                'physician_name' => 'PHYSICIAN NAME',
                'physician_npi' => 'NPI',
                'facility_name' => 'FACILITY NAME',
                'facility_ptan' => 'PTAN',
                'insurance_name' => 'INSURANCE NAME',
                'policy_number' => 'POLICY NUMBER',
                'place_of_service' => 'PLACE OF SERVICE WHERE PATIENT IS BEING SEEN',
            ],
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
            'id' => 5,
            'name' => 'MEDLIFE SOLUTIONS',
            'signature_required' => true,
            'has_order_form' => false,
            'docuseal_field_names' => [
                // Map canonical names to MedLife's specific field names (form2_IVR)
                'physician_name' => 'Physician Name',
                'physician_npi' => 'Physician NPI',
                'physician_ptan' => 'Physician PTAN',
                'facility_name' => 'Practice Name',
                'facility_npi' => 'Practice NPI',
                'facility_ptan' => 'Practice PTAN',
                'patient_name' => 'Patient Name',
                'patient_dob' => 'Patient DOB',
                'insurance_name' => 'Primary Insurance',
                'member_id' => 'Member ID',
                'place_of_service' => 'Place of Service',
                'tax_id' => 'TAX ID#',
            ],
            'fields' => [
                // Patient Information
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_dob' => [
                    'source' => 'patient_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                // Physician Information
                'physician_name' => [
                    'source' => 'provider_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'physician_npi' => [
                    'source' => 'provider_npi',
                    'required' => true,
                    'type' => 'string'
                ],
                'physician_ptan' => [
                    'source' => 'provider_ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                // Facility Information
                'practice_name' => [
                    'source' => 'facility_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'practice_npi' => [
                    'source' => 'facility_npi',
                    'required' => false,
                    'type' => 'string'
                ],
                'practice_ptan' => [
                    'source' => 'facility_ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                // Insurance Information
                'primary_insurance' => [
                    'source' => 'primary_insurance_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'member_id' => [
                    'source' => 'primary_member_id',
                    'required' => true,
                    'type' => 'string'
                ],
                // Other fields
                'place_of_service' => [
                    'source' => 'place_of_service',
                    'required' => true,
                    'type' => 'string'
                ],
                'tax_id' => [
                    'source' => 'tax_id',
                    'required' => true,
                    'type' => 'string'
                ],
            ]
        ],
        
        'Centurion' => [
            'id' => 11,
            'name' => 'CENTURION THERAPEUTICS',
            'signature_required' => true,
            'has_order_form' => false,
            'docuseal_field_names' => [
                // Map canonical names to Centurion's specific field names (form3_Centurion)
                'patient_name' => '*Patient Name',
                'patient_dob' => '*DOB',
                'patient_gender' => 'Male/Female', // Special handling needed
                'provider_name' => '*Provider Name',
                'provider_npi' => '*Provider ID #s: NPI',
                'provider_ptan' => 'PTAN #',
                'facility_name' => '*Facility Name',
                'facility_address' => 'Address',
                'facility_city' => 'City',
                'facility_state' => 'State',
                'facility_zip' => 'Zip',
                'insurance_name' => 'Primary Insurance',
                'policy_number' => 'Policy Number',
                'treatment_setting' => '*Treatment Setting',
                'medicaid_provider' => 'Medicaid Provider #',
                'tax_id' => 'Tax ID',
            ],
            'fields' => [
                // Patient Information
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
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
                    'transform' => 'gender_checkboxes', // Special transform for Male/Female checkboxes
                    'required' => false,
                    'type' => 'checkbox_group'
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
                    'type' => 'string'
                ],
                'provider_ptan' => [
                    'source' => 'provider_ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                // Facility Information
                'facility_name' => [
                    'source' => 'facility_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_address' => [
                    'source' => 'facility_address',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_city' => [
                    'source' => 'facility_city',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_state' => [
                    'source' => 'facility_state',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_zip' => [
                    'source' => 'facility_zip',
                    'required' => true,
                    'type' => 'string'
                ],
                // Insurance Information
                'primary_insurance' => [
                    'source' => 'primary_insurance_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'policy_number' => [
                    'source' => 'primary_member_id',
                    'required' => true,
                    'type' => 'string'
                ],
                // Other fields
                'treatment_setting' => [
                    'source' => 'place_of_service',
                    'required' => true,
                    'type' => 'string'
                ],
                'medicaid_provider' => [
                    'source' => 'medicaid_provider_number',
                    'required' => false,
                    'type' => 'string'
                ],
                'tax_id' => [
                    'source' => 'tax_id',
                    'required' => true,
                    'type' => 'string'
                ],
            ]
        ],
        
        'BioWerX' => [
            'id' => 5,
            'name' => 'BioWerX',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // BioWerX specific field mappings
            ]
        ],
        
        'BioWound' => [
            'id' => 6,
            'name' => 'BioWound',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // BioWound specific field mappings
            ]
        ],
        
        'Extremity Care' => [
            'id' => 7,
            'name' => 'Extremity Care',
            'signature_required' => true,
            'has_order_form' => true,
            'fields' => [
                // Extremity Care specific field mappings
            ]
        ],
        
        'SKYE Biologics' => [
            'id' => 8,
            'name' => 'SKYE Biologics',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // SKYE Biologics specific field mappings
            ]
        ],
        
        'Total Ancillary' => [
            'id' => 9,
            'name' => 'Total Ancillary',
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