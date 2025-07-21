<?php

/**
 * ACZ & Associates Configuration v2
 * 
 * This configuration file contains ALL mapping logic for ACZ & Associates forms.
 * The UnifiedFieldMappingService should only read and apply these rules.
 */

return [
    'id' => 1,
    'name' => 'ACZ & ASSOCIATES',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,
    
    // Document templates
    'templates' => [
        'ivr' => [
            'docuseal_template_id' => 852440,
            'folder_id' => 75423,
            'type' => 'insurance_verification_request'
        ],
        'order_form' => [
            'docuseal_template_id' => null, // TO BE FILLED
            'folder_id' => null,
            'type' => 'order_form'
        ]
    ],
    
    // Field mapping configuration
    'field_mappings' => [
        'ivr' => [
            // Header Information
            'name' => [
                'docuseal_field' => 'Name',
                'source' => 'current_user.full_name',
                'fallback' => ['sales_rep_name', 'submitter_name'],
                'required' => true
            ],
            'email' => [
                'docuseal_field' => 'Email',
                'source' => 'current_user.email',
                'fallback' => ['sales_rep_email', 'submitter_email'],
                'required' => true,
                'validation' => 'email'
            ],
            'phone' => [
                'docuseal_field' => 'Phone',
                'source' => 'current_user.phone',
                'fallback' => ['provider.phone', 'facility.phone'],
                'transform' => 'phone_format',
                'required' => true
            ],
            
            // Q-Code checkboxes (product selections)
            'q4205' => [
                'docuseal_field' => 'Q4205',
                'source' => 'products',
                'transform' => 'product_checkbox',
                'transform_params' => ['product_code' => 'Q4205'],
                'type' => 'boolean'
            ],
            'q4290' => [
                'docuseal_field' => 'Q4290',
                'source' => 'products',
                'transform' => 'product_checkbox',
                'transform_params' => ['product_code' => 'Q4290'],
                'type' => 'boolean'
            ],
            
            // Sales Rep Information
            'sales_rep' => [
                'docuseal_field' => 'Sales Rep',
                'source' => 'sales_rep.name',
                'fallback' => ['current_user.full_name'],
                'required' => false
            ],
            
            // Physician Information
            'physician_name' => [
                'docuseal_field' => 'Physician Name',
                'source' => 'provider.name',
                'fallback' => ['provider.full_name', 'physician.name'],
                'required' => true
            ],
            'physician_npi' => [
                'docuseal_field' => 'Physician NPI',
                'source' => 'provider.npi',
                'validation' => 'npi',
                'required' => true
            ],
            'physician_specialty' => [
                'docuseal_field' => 'Physician Specialty',
                'source' => 'provider.specialty',
                'fallback' => ['provider.credentials'],
                'required' => false
            ],
            'physician_tax_id' => [
                'docuseal_field' => 'Physician Tax ID',
                'source' => 'provider.tax_id',
                'transform' => 'tax_id_format',
                'required' => false
            ],
            
            // Facility Information
            'facility_name' => [
                'docuseal_field' => 'Facility Name',
                'source' => 'facility.name',
                'required' => true
            ],
            'facility_npi' => [
                'docuseal_field' => 'Facility NPI',
                'source' => 'facility.npi',
                'validation' => 'npi',
                'required' => false
            ],
            'facility_address' => [
                'docuseal_field' => 'Facility Address',
                'source' => 'facility.address',
                'fallback' => ['facility.address_line1'],
                'required' => true
            ],
            'facility_city_state_zip' => [
                'docuseal_field' => 'Facility City, State, Zip',
                'source' => 'computed',
                'computation' => 'concat_address',
                'computation_fields' => ['facility.city', 'facility.state', 'facility.zip_code'],
                'required' => true
            ],
            
            // Place of Service checkboxes
            'pos_11' => [
                'docuseal_field' => 'POS 11',
                'source' => 'place_of_service',
                'transform' => 'pos_checkbox',
                'transform_params' => ['pos_code' => '11'],
                'type' => 'boolean'
            ],
            'pos_22' => [
                'docuseal_field' => 'POS 22',
                'source' => 'place_of_service',
                'transform' => 'pos_checkbox',
                'transform_params' => ['pos_code' => '22'],
                'type' => 'boolean'
            ],
            
            // Patient Information
            'patient_name' => [
                'docuseal_field' => 'Patient Name',
                'source' => 'computed',
                'computation' => 'concat_name',
                'computation_fields' => ['patient.first_name', 'patient.last_name'],
                'required' => true
            ],
            'patient_dob' => [
                'docuseal_field' => 'Patient DOB',
                'source' => 'patient.dob',
                'transform' => 'date_format',
                'transform_params' => ['format' => 'm/d/Y'],
                'validation' => 'date',
                'required' => true
            ],
            
            // Insurance Information
            'primary_insurance_name' => [
                'docuseal_field' => 'Primary Insurance Name',
                'source' => 'insurance.primary.name',
                'fallback' => ['primary_insurance_name', 'insurance_name'],
                'required' => true
            ],
            'primary_policy_number' => [
                'docuseal_field' => 'Primary Policy Number',
                'source' => 'insurance.primary.member_id',
                'fallback' => ['primary_member_id', 'member_id'],
                'required' => true
            ],
            
            // Network Status (checkbox version for DocuSeal)
            'physician_status_primary_in_network' => [
                'docuseal_field' => 'Physician Status With Primary: In-Network',
                'source' => 'insurance.primary.network_status',
                'transform' => 'network_status_checkbox',
                'transform_params' => ['status' => 'in_network'],
                'type' => 'boolean'
            ],
            'physician_status_primary_out_of_network' => [
                'docuseal_field' => 'Physician Status With Primary: Out-Of-Network',
                'source' => 'insurance.primary.network_status',
                'transform' => 'network_status_checkbox',
                'transform_params' => ['status' => 'out_of_network'],
                'type' => 'boolean'
            ],
            
            // Clinical Status
            'patient_in_hospice_yes' => [
                'docuseal_field' => 'Is The Patient Currently in Hospice? Yes',
                'source' => 'clinical.hospice_status',
                'transform' => 'boolean_to_checkbox',
                'transform_params' => ['check_value' => true],
                'type' => 'boolean',
                'default' => false
            ],
            'patient_in_hospice_no' => [
                'docuseal_field' => 'Is The Patient Currently in Hospice? No',
                'source' => 'clinical.hospice_status',
                'transform' => 'boolean_to_checkbox',
                'transform_params' => ['check_value' => false],
                'type' => 'boolean',
                'default' => true
            ],
            
            // Wound Information
            'wound_location' => [
                'docuseal_field' => 'Location of Wound',
                'source' => 'clinical.wound_location',
                'transform' => 'wound_location_map',
                'required' => true
            ],
            
            // ICD-10 Codes
            'icd_10_code_1' => [
                'docuseal_field' => 'ICD-10 Code 1',
                'source' => 'clinical.primary_diagnosis_code',
                'fallback' => ['diagnosis_codes[0]', 'icd10_codes[0]'],
                'validation' => 'icd10',
                'required' => true
            ]
        ]
    ],
    
    // Transformation functions
    'transformations' => [
        'phone_format' => function($value) {
            // Remove all non-numeric characters
            $cleaned = preg_replace('/\D/', '', $value);
            // Format as (XXX) XXX-XXXX
            if (strlen($cleaned) === 10) {
                return sprintf('(%s) %s-%s', 
                    substr($cleaned, 0, 3),
                    substr($cleaned, 3, 3),
                    substr($cleaned, 6)
                );
            }
            return $value;
        },
        
        'tax_id_format' => function($value) {
            // Remove all non-numeric characters
            $cleaned = preg_replace('/\D/', '', $value);
            // Format as XX-XXXXXXX
            if (strlen($cleaned) === 9) {
                return substr($cleaned, 0, 2) . '-' . substr($cleaned, 2);
            }
            return $value;
        },
        
        'product_checkbox' => function($products, $params) {
            if (!is_array($products)) return false;
            $productCode = $params['product_code'] ?? '';
            foreach ($products as $product) {
                if (isset($product['code']) && $product['code'] === $productCode) {
                    return true;
                }
            }
            return false;
        },
        
        'pos_checkbox' => function($placeOfService, $params) {
            $posCode = $params['pos_code'] ?? '';
            return $placeOfService === $posCode;
        },
        
        'network_status_checkbox' => function($networkStatus, $params) {
            $checkStatus = $params['status'] ?? '';
            return $networkStatus === $checkStatus;
        },
        
        'boolean_to_checkbox' => function($value, $params) {
            $checkValue = $params['check_value'] ?? true;
            return ($value === $checkValue);
        },
        
        'date_format' => function($value, $params) {
            $format = $params['format'] ?? 'Y-m-d';
            try {
                $date = new DateTime($value);
                return $date->format($format);
            } catch (Exception $e) {
                return $value;
            }
        },
        
        'wound_location_map' => function($value) {
            // Map internal wound location codes to DocuSeal field values
            $locationMap = [
                'right_foot' => 'Legs/Arms/Trunk ≤ 100 sq cm',
                'left_foot' => 'Legs/Arms/Trunk ≤ 100 sq cm',
                'trunk_arms_legs_small' => 'Legs/Arms/Trunk ≤ 100 sq cm',
                'trunk_arms_legs_large' => 'Legs/Arms/Trunk ≥ 100 sq cm',
                'feet_hands_head_small' => 'Feet/Hands/Head ≤ 100 sq cm',
                'feet_hands_head_large' => 'Feet/Hands/Head ≥ 100 sq cm'
            ];
            return $locationMap[$value] ?? $value;
        }
    ],
    
    // Computation functions
    'computations' => [
        'concat_name' => function($fields) {
            $firstName = $fields[0] ?? '';
            $lastName = $fields[1] ?? '';
            return trim($firstName . ' ' . $lastName);
        },
        
        'concat_address' => function($fields) {
            $city = $fields[0] ?? '';
            $state = $fields[1] ?? '';
            $zip = $fields[2] ?? '';
            return trim($city . ', ' . $state . ' ' . $zip);
        }
    ],
    
    // Validation rules
    'validation_rules' => [
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'npi' => '/^\d{10}$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$|^\d{1,2}\/\d{1,2}\/\d{4}$/',
        'icd10' => '/^[A-TV-Z]\d{2}(\.\d{1,4})?$/',
        'phone' => '/^\(\d{3}\) \d{3}-\d{4}$|^\d{10}$/',
        'zip' => '/^\d{5}(-\d{4})?$/'
    ],
    
    // Business rules
    'business_rules' => [
        'wound_duration_requirement' => 'greater_than_4_weeks',
        'require_prior_auth_for_medicare' => true,
        'max_applications_per_episode' => 12,
        'require_clinical_documentation' => true
    ]
]; 