<?php

/**
 * ACZ & Associates IVR Template Field Mapping Configuration
 *
 * This configuration maps form data to Docuseal template fields (ID: 852440)
 * Template: "ACZ & Associates IVR"
 *
 * Field mapping strategy for 100% form completion
 */

return [
    'template_id' => 852440,
    'template_name' => 'ACZ & Associates IVR',
    'manufacturer' => 'ACZ & ASSOCIATES',

    // Field mappings organized by section
    'field_mappings' => [

        // ========================================
        // PRODUCT SELECTION SECTION
        // ========================================
        'Product Q Code' => [
            'source' => 'selected_products',
            'type' => 'radio',
            'required' => true,
            'transform' => 'extract_product_code',
            'options' => [
                'Q4205', 'Q4290', 'Q4344', 'Q4275', 'Q4341',
                'Q4313', 'Q4316', 'Q4164', 'Q4289'
            ],
            'description' => 'Map selected product code to radio button'
        ],

        // ========================================
        // REPRESENTATIVE INFORMATION
        // ========================================
        'Sales Rep' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case',
            'fallback' => 'MSC Wound Care Representative'
        ],

        'ISO if applicable' => [
            'source' => 'iso_number',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Additional Emails for Notification' => [
            'source' => 'additional_notification_emails',
            'type' => 'text',
            'required' => false,
            'transform' => 'comma_separated_emails'
        ],

        // ========================================
        // PHYSICIAN INFORMATION
        // ========================================
        'Physician Name' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case'
        ],

        'Physician NPI' => [
            'source' => 'provider_npi',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Physician Specialty' => [
            'source' => 'provider_specialty',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Physician Tax ID' => [
            'source' => 'provider_tax_id',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Physician PTAN' => [
            'source' => 'provider_ptan',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Physician Medicaid #' => [
            'source' => 'provider_medicaid',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Physician Phone #' => [
            'source' => 'provider_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Physician Fax #' => [
            'source' => 'provider_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Physician Organization' => [
            'source' => 'provider_organization',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        // ========================================
        // FACILITY INFORMATION
        // ========================================
        'Facility NPI' => [
            'source' => 'facility_npi',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Facility Tax ID' => [
            'source' => 'facility_tax_id',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Facility Name' => [
            'source' => 'facility_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case',
            'fallback' => 'organization_name'
        ],

        'Facility PTAN' => [
            'source' => 'facility_ptan',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Facility Address' => [
            'source' => ['facility_address_line1', 'facility_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'concatenate_address'
        ],

        'Facility Medicaid #' => [
            'source' => 'facility_medicaid',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Facility City, State, Zip' => [
            'source' => ['facility_city', 'facility_state', 'facility_zip'],
            'type' => 'text',
            'required' => false,
            'transform' => 'format_city_state_zip'
        ],

        'Facility Phone #' => [
            'source' => 'facility_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Facility Contact Name' => [
            'source' => 'facility_contact_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Facility Fax #' => [
            'source' => 'facility_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Facility Contact Phone # / Facility Contact Email' => [
            'source' => ['facility_contact_phone', 'facility_contact_email'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_phone_email'
        ],

        'Facility Organization' => [
            'source' => 'facility_organization',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        // ========================================
        // PLACE OF SERVICE
        // ========================================
        'Place of Service' => [
            'source' => 'place_of_service',
            'type' => 'radio',
            'required' => true,
            'transform' => 'map_pos_code',
            'options' => [
                'POS 11' => '11',
                'POS 22' => '22',
                'POS 24' => '24',
                'POS 12' => '12',
                'POS 32' => '32',
                'Other' => 'other'
            ]
        ],

        'POS Other Specify' => [
            'source' => 'place_of_service_other',
            'type' => 'text',
            'required' => false,
            'conditional' => [
                'field' => 'Place of Service',
                'value' => 'Other'
            ]
        ],

        // ========================================
        // PATIENT INFORMATION
        // ========================================
        'Patient Name' => [
            'source' => 'patient_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case'
        ],

        'Patient DOB' => [
            'source' => 'patient_dob',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_date_mm_dd_yyyy'
        ],

        'Patient Address' => [
            'source' => ['patient_address_line1', 'patient_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'concatenate_address'
        ],

        'Patient City, State, Zip' => [
            'source' => ['patient_city', 'patient_state', 'patient_zip'],
            'type' => 'text',
            'required' => false,
            'transform' => 'format_city_state_zip'
        ],

        'Patient Phone #' => [
            'source' => 'patient_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Patient Email' => [
            'source' => 'patient_email',
            'type' => 'text',
            'required' => false,
            'transform' => 'lowercase'
        ],

        'Patient Caregiver Info' => [
            'source' => 'patient_caregiver_info',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        // ========================================
        // INSURANCE INFORMATION
        // ========================================
        'Primary Insurance Name' => [
            'source' => 'primary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Secondary Insurance Name' => [
            'source' => 'secondary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Primary Policy Number' => [
            'source' => 'primary_member_id',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Secondary Policy Number' => [
            'source' => 'secondary_member_id',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Primary Payer Phone #' => [
            'source' => 'primary_payer_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Secondary Payer Phone #' => [
            'source' => 'secondary_payer_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        // ========================================
        // NETWORK STATUS (RADIO FIELDS)
        // ========================================
        'Physician Status With Primary' => [
            'source' => 'primary_physician_network_status',
            'type' => 'radio',
            'required' => true,
            'transform' => 'map_network_status',
            'options' => [
                'In-Network' => 'in_network',
                'Out-of-Network' => 'out_of_network'
            ]
        ],

        'Physician Status With Secondary' => [
            'source' => 'secondary_physician_network_status',
            'type' => 'radio',
            'required' => true,
            'transform' => 'map_network_status',
            'options' => [
                'In-Network' => 'in_network',
                'Out-of-Network' => 'out_of_network'
            ]
        ],

        // ========================================
        // AUTHORIZATION QUESTIONS (RADIO FIELDS)
        // ========================================
        'Permission To Initiate And Follow Up On Prior Auth?' => [
            'source' => 'prior_auth_permission',
            'type' => 'radio',
            'required' => true,
            'transform' => 'boolean_to_yes_no',
            'options' => [
                'Yes' => true,
                'No' => false
            ]
        ],

        'Is The Patient Currently in Hospice?' => [
            'source' => 'hospice_status',
            'type' => 'radio',
            'required' => false,
            'transform' => 'boolean_to_yes_no',
            'options' => [
                'Yes' => true,
                'No' => false
            ]
        ],

        'Is The Patient In A Facility Under Part A Stay?' => [
            'source' => 'part_a_status',
            'type' => 'radio',
            'required' => false,
            'transform' => 'boolean_to_yes_no',
            'options' => [
                'Yes' => true,
                'No' => false
            ]
        ],

        'Is The Patient Under Post-Op Global Surgery Period?' => [
            'source' => 'global_period_status',
            'type' => 'radio',
            'required' => false,
            'transform' => 'boolean_to_yes_no',
            'options' => [
                'Yes' => true,
                'No' => false
            ]
        ],

        // ========================================
        // CONDITIONAL SURGERY FIELDS
        // ========================================
        'If Yes, List Surgery CPTs' => [
            'source' => 'surgery_cpt_codes',
            'type' => 'text',
            'required' => false,
            'transform' => 'comma_separated',
            'conditional' => [
                'field' => 'Is The Patient Under Post-Op Global Surgery Period?',
                'value' => 'Yes'
            ]
        ],

        'Surgery Date' => [
            'source' => 'surgery_date',
            'type' => 'date',
            'required' => false,
            'transform' => 'format_date_mm_dd_yyyy',
            'conditional' => [
                'field' => 'Is The Patient Under Post-Op Global Surgery Period?',
                'value' => 'Yes'
            ]
        ],

        // ========================================
        // CLINICAL INFORMATION
        // ========================================
        'Location of Wound' => [
            'source' => 'wound_location',
            'type' => 'radio',
            'required' => true,
            'transform' => 'map_wound_location',
            'options' => [
                'Legs/Arms/Trunk < 100 SQ CM' => 'legs_arms_trunk_small',
                'Feet/Hands/Head < 100 SQ CM' => 'feet_hands_head_small',
                'Legs/Arms/Trunk > 100 SQ CM' => 'legs_arms_trunk_large',
                'Feet/Hands/Head > 100 SQ CM' => 'feet_hands_head_large'
            ]
        ],

        'ICD-10 Codes' => [
            'source' => ['primary_diagnosis_code', 'secondary_diagnosis_code'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_diagnosis_codes'
        ],

        'Total Wound Size' => [
            'source' => ['wound_size_length', 'wound_size_width', 'wound_size_depth'],
            'type' => 'text',
            'required' => false,
            'transform' => 'calculate_wound_size'
        ],

        'Medical History' => [
            'source' => 'medical_history',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ]
    ],

    // Data transformation functions
    'transformations' => [
        'extract_product_code' => function($selectedProducts) {
            if (empty($selectedProducts)) return null;
            $firstProduct = $selectedProducts[0];
            return $firstProduct['product']['code'] ?? null;
        },

        'title_case' => function($value) {
            return ucwords(strtolower(trim($value)));
        },

        'format_date_mm_dd_yyyy' => function($date) {
            if (empty($date)) return null;
            return date('m/d/Y', strtotime($date));
        },

        'format_phone' => function($phone) {
            if (empty($phone)) return null;
            // Remove all non-digits
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($digits) === 10) {
                return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
            }
            return $phone;
        },

        'concatenate_address' => function($addressParts) {
            $parts = array_filter($addressParts);
            return implode(', ', $parts);
        },

        'format_city_state_zip' => function($locationParts) {
            $parts = array_filter($locationParts);
            return implode(', ', $parts);
        },

        'combine_phone_email' => function($contactInfo) {
            $parts = array_filter($contactInfo);
            return implode(' / ', $parts);
        },

        'map_pos_code' => function($posCode) {
            $mapping = [
                '11' => 'POS 11',
                '22' => 'POS 22',
                '24' => 'POS 24',
                '12' => 'POS 12',
                '32' => 'POS 32'
            ];
            return $mapping[$posCode] ?? 'Other';
        },

        'map_network_status' => function($status) {
            return $status === 'in_network' ? 'In-Network' : 'Out-of-Network';
        },

        'boolean_to_yes_no' => function($value) {
            return $value ? 'Yes' : 'No';
        },

        'comma_separated' => function($values) {
            if (is_array($values)) {
                return implode(', ', array_filter($values));
            }
            return $values;
        },

        'combine_diagnosis_codes' => function($codes) {
            $codes = array_filter($codes);
            return implode(', ', $codes);
        },

        'calculate_wound_size' => function($dimensions) {
            if (empty($dimensions)) return null;
            $length = $dimensions[0] ?? 0;
            $width = $dimensions[1] ?? 0;
            $depth = $dimensions[2] ?? 0;

            if ($length > 0 && $width > 0) {
                $area = $length * $width;
                return "{$length}cm x {$width}cm = {$area} sq cm";
            }
            return null;
        },

        'map_wound_location' => function($location) {
            $mapping = [
                'right_foot' => 'Feet/Hands/Head < 100 SQ CM',
                'left_foot' => 'Feet/Hands/Head < 100 SQ CM',
                'right_leg' => 'Legs/Arms/Trunk < 100 SQ CM',
                'left_leg' => 'Legs/Arms/Trunk < 100 SQ CM',
                'trunk' => 'Legs/Arms/Trunk < 100 SQ CM'
            ];
            return $mapping[$location] ?? 'Legs/Arms/Trunk < 100 SQ CM';
        }
    ],

    // Validation rules
    'validation' => [
        'required_fields' => [
            'Product Q Code',
            'Sales Rep',
            'Physician Name',
            'Place of Service',
            'Patient Name',
            'Physician Status With Primary',
            'Physician Status With Secondary',
            'Permission To Initiate And Follow Up On Prior Auth?',
            'Location of Wound'
        ],

        'radio_fields' => [
            'Product Q Code',
            'Place of Service',
            'Physician Status With Primary',
            'Physician Status With Secondary',
            'Permission To Initiate And Follow Up On Prior Auth?',
            'Is The Patient Currently in Hospice?',
            'Is The Patient In A Facility Under Part A Stay?',
            'Is The Patient Under Post-Op Global Surgery Period?',
            'Location of Wound'
        ],

        'conditional_fields' => [
            'POS Other Specify' => ['Place of Service' => 'Other'],
            'If Yes, List Surgery CPTs' => ['Is The Patient Under Post-Op Global Surgery Period?' => 'Yes'],
            'Surgery Date' => ['Is The Patient Under Post-Op Global Surgery Period?' => 'Yes']
        ]
    ]
];
