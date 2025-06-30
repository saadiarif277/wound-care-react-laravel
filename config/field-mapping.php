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
        // ACZ & Associates - moved to config/manufacturers/acz-associates.php
        // Advanced Health - moved to config/manufacturers/advanced-health.php
        // BIOWOUND SOLUTIONS - moved to config/manufacturers/biowound-solutions.php
        // MEDLIFE SOLUTIONS - moved to config/manufacturers/medlife-solutions.php
        // BioWerX - moved to config/manufacturers/biowerx.php
        // Extremity Care LLC - moved to config/manufacturers/extremity-care-llc.php
        // SKYE Biologics - moved to config/manufacturers/skye-biologics.php
        // Total Ancillary - moved to config/manufacturers/total-ancillary.php
        // CENTURION THERAPEUTICS - moved to config/manufacturers/centurion-therapeutics.php
        // ADVANCED SOLUTION - moved to config/manufacturers/advanced-solution.php
        // AdvancedSolutionOrderForm - moved to config/manufacturers/advanced-solution-order-form.php
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
        ],
        'tax_id' => [
            'format' => 'formatTaxId',
        ]
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
        'Q4250' => 'MEDLIFE SOLUTIONS',
        'CTN001' => 'CENTURION THERAPEUTICS',
        'Q4151' => 'CENTURION THERAPEUTICS', // AmnioBand
        'Q4128' => 'CENTURION THERAPEUTICS', // Allopatch
        'BWX001' => 'BioWerX',
        // BioWound Solutions products
        'Q4161' => 'BIOWOUND SOLUTIONS',
        'Q4205' => 'BIOWOUND SOLUTIONS',
        'Q4290' => 'BIOWOUND SOLUTIONS',
        'Q4238' => 'BIOWOUND SOLUTIONS',
        'Q4239' => 'BIOWOUND SOLUTIONS',
        'Q4266' => 'BIOWOUND SOLUTIONS',
        'Q4267' => 'BIOWOUND SOLUTIONS',
        'Q4265' => 'BIOWOUND SOLUTIONS',
        'EXT001' => 'Extremity Care LLC',
        'EXT002' => 'Extremity Care LLC',
        'SKY001' => 'SKYE Biologics',
        'TAC001' => 'Total Ancillary',
        // Advanced Solution Products
        'ASL001' => 'ADVANCED SOLUTION', // CompleteAA
        'ASL002' => 'ADVANCED SOLUTION', // Membrane Wrap Hydro
        'ASL003' => 'ADVANCED SOLUTION', // Membrane Wrap
        'ASL004' => 'ADVANCED SOLUTION', // WoundPlus
        'ASL005' => 'ADVANCED SOLUTION', // CompleteFT
        'CompleteAA' => 'ADVANCED SOLUTION',
        'Membrane Wrap Hydro' => 'ADVANCED SOLUTION',
        'Membrane Wrap' => 'ADVANCED SOLUTION',
        'WoundPlus' => 'ADVANCED SOLUTION',
        'CompleteFT' => 'ADVANCED SOLUTION'
    ],
];