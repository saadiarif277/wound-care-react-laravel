<?php

/**
 * MedLife AMNIO AMP IVR Field Mapping Configuration
 *
 * Template ID: 1233913
 * Template Name: MedLife AMNIO AMP IVR
 * Manufacturer: MedLife Solutions
 *
 * This configuration maps form data to Docuseal fields for 100% completion
 */

return [
    'field_mappings' => [
        // Distributor/Company - Pre-filled
        'Distributor/Company' => [
            'source' => 'distributor_name',
            'type' => 'text',
            'required' => false,
            'default_value' => 'MSC Wound Care',
            'transform' => 'default_distributor'
        ],

        // Physician Information
        'Physician Name' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Practice Name' => [
            'source' => 'facility_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Physician PTAN' => [
            'source' => 'provider_ptan',
            'type' => 'text',
            'required' => false
        ],

        'Practice PTAN' => [
            'source' => 'facility_ptan',
            'type' => 'text',
            'required' => false
        ],

        'Physician NPI' => [
            'source' => 'provider_npi',
            'type' => 'text',
            'required' => false
        ],

        'Practice NPI' => [
            'source' => 'facility_npi',
            'type' => 'text',
            'required' => false
        ],

        'TAX ID' => [
            'source' => 'facility_tax_id',
            'type' => 'text',
            'required' => false
        ],

        // Contact Information
        'Office Contact Name' => [
            'source' => 'facility_contact_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Office Contact Email' => [
            'source' => 'facility_contact_email',
            'type' => 'email',
            'required' => false,
            'transform' => 'lowercase'
        ],

        // Patient Information
        'Patient DOB' => [
            'source' => 'patient_dob',
            'type' => 'date',
            'required' => false,
            'transform' => 'format_date_mdy'
        ],

        'Patient Name' => [
            'source' => 'patient_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        // Insurance Information
        'Primary Insurance' => [
            'source' => 'primary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Primary Member ID' => [
            'source' => 'primary_member_id',
            'type' => 'text',
            'required' => false
        ],

        'Secondary Insurance' => [
            'source' => 'secondary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Secondary Member ID' => [
            'source' => 'secondary_member_id',
            'type' => 'text',
            'required' => false
        ],

        // Place of Service
        'Place of Service' => [
            'source' => 'place_of_service',
            'type' => 'radio',
            'required' => false,
            'options' => [
                'POS 12' => 'POS 12',
                'POS 11' => 'POS 11',
                'POS 13' => 'POS 13',
                'Other' => 'Other'
            ],
            'transform' => 'map_place_of_service'
        ],

        'Other' => [
            'source' => 'place_of_service_other',
            'type' => 'text',
            'required' => false,
            'conditional' => [
                'field' => 'Place of Service',
                'value' => 'Other'
            ]
        ],

        // Nursing Home Questions
        'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility' => [
            'source' => 'nursing_home_status',
            'type' => 'select',
            'required' => true,
            'options' => [
                'Yes' => 'Yes',
                'No' => 'No'
            ],
            'transform' => 'boolean_to_yes_no'
        ],

        'If yes, has it been over 100 days' => [
            'source' => 'nursing_home_over_100_days',
            'type' => 'select',
            'required' => false,
            'options' => [
                'Yes' => 'Yes',
                'No' => 'No'
            ],
            'conditional' => [
                'field' => 'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility',
                'value' => 'Yes'
            ],
            'transform' => 'boolean_to_yes_no'
        ],

        // Post-Op Period
        'Is this patient currently under a post-op period' => [
            'source' => 'global_period_status',
            'type' => 'select',
            'required' => true,
            'options' => [
                'Yes' => 'Yes',
                'No' => 'No'
            ],
            'transform' => 'boolean_to_yes_no'
        ],

        'If yes please list CPT codes of previous surgery' => [
            'source' => 'surgery_cpt_codes',
            'type' => 'text',
            'required' => false,
            'conditional' => [
                'field' => 'Is this patient currently under a post-op period',
                'value' => 'Yes'
            ],
            'transform' => 'array_to_comma_separated'
        ],

        'Surgery Date' => [
            'source' => 'surgery_date',
            'type' => 'date',
            'required' => false,
            'conditional' => [
                'field' => 'Is this patient currently under a post-op period',
                'value' => 'Yes'
            ],
            'transform' => 'format_date_mdy'
        ],

        // Procedure Information
        'Procedure Date' => [
            'source' => 'expected_service_date',
            'type' => 'date',
            'required' => false,
            'transform' => 'format_date_mdy'
        ],

        // Wound Information
        'L' => [
            'source' => 'wound_size_length',
            'type' => 'text',
            'required' => false,
            'transform' => 'add_cm_unit'
        ],

        'W' => [
            'source' => 'wound_size_width',
            'type' => 'text',
            'required' => false,
            'transform' => 'add_cm_unit'
        ],

        'Wound Size Total' => [
            'source' => ['wound_size_length', 'wound_size_width'],
            'type' => 'text',
            'required' => false,
            'transform' => 'calculate_wound_area'
        ],

        'Wound location' => [
            'source' => 'wound_location_details',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Size of Graft Requested' => [
            'source' => ['selected_products'],
            'type' => 'text',
            'required' => false,
            'transform' => 'extract_product_size'
        ],

        // ICD-10 Codes
        'ICD-10 #1' => [
            'source' => 'primary_diagnosis_code',
            'type' => 'text',
            'required' => false
        ],

        'ICD-10 #2' => [
            'source' => 'secondary_diagnosis_code',
            'type' => 'text',
            'required' => false
        ],

        'ICD-10 #3' => [
            'source' => 'tertiary_diagnosis_code',
            'type' => 'text',
            'required' => false
        ],

        'ICD-10 #4' => [
            'source' => 'quaternary_diagnosis_code',
            'type' => 'text',
            'required' => false
        ],

        // CPT Codes
        'CPT #1' => [
            'source' => 'application_cpt_codes',
            'type' => 'text',
            'required' => false,
            'transform' => 'array_to_comma_separated'
        ],

        'CPT #2' => [
            'source' => 'secondary_cpt_codes',
            'type' => 'text',
            'required' => false,
            'transform' => 'array_to_comma_separated'
        ],

        'CPT #3' => [
            'source' => 'tertiary_cpt_codes',
            'type' => 'text',
            'required' => false,
            'transform' => 'array_to_comma_separated'
        ],

        'CPT #4' => [
            'source' => 'quaternary_cpt_codes',
            'type' => 'text',
            'required' => false,
            'transform' => 'array_to_comma_separated'
        ],

        // HCPCS Codes
        'HCPCS #1' => [
            'source' => 'primary_hcpcs_code',
            'type' => 'text',
            'required' => false
        ],

        'HCPCS #2' => [
            'source' => 'secondary_hcpcs_code',
            'type' => 'text',
            'required' => false
        ],

        'HCPCS #3' => [
            'source' => 'tertiary_hcpcs_code',
            'type' => 'text',
            'required' => false
        ],

        'HCPCS #4' => [
            'source' => 'quaternary_hcpcs_code',
            'type' => 'text',
            'required' => false
        ]
    ],

    'transformations' => [
        'default_distributor' => function($value) {
            return $value ?: 'MSC Wound Care';
        },

        'title_case' => function($value) {
            if (is_string($value)) {
                return ucwords(strtolower($value));
            }
            return $value;
        },

        'lowercase' => function($value) {
            if (is_string($value)) {
                return strtolower($value);
            }
            return $value;
        },

        'format_date_mdy' => function($value) {
            if (!$value) return null;

            try {
                $date = new DateTime($value);
                return $date->format('m/d/Y');
            } catch (Exception $e) {
                return $value;
            }
        },

        'map_place_of_service' => function($value) {
            $mapping = [
                '11' => 'POS 11',
                '12' => 'POS 12',
                '13' => 'POS 13',
                'other' => 'Other'
            ];

            return $mapping[$value] ?? $value;
        },

        'boolean_to_yes_no' => function($value) {
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }
            if (is_string($value)) {
                $lower = strtolower($value);
                if (in_array($lower, ['true', '1', 'yes'])) return 'Yes';
                if (in_array($lower, ['false', '0', 'no'])) return 'No';
            }
            return $value;
        },

        'array_to_comma_separated' => function($value) {
            if (is_array($value)) {
                return implode(', ', array_filter($value));
            }
            return $value;
        },

        'add_cm_unit' => function($value) {
            if ($value && is_numeric($value)) {
                return $value . ' cm';
            }
            return $value;
        },

        'calculate_wound_area' => function($values) {
            if (is_array($values) && count($values) >= 2) {
                $length = floatval($values[0] ?? 0);
                $width = floatval($values[1] ?? 0);
                if ($length > 0 && $width > 0) {
                    return number_format($length * $width, 1) . ' sq cm';
                }
            }
            return null;
        },

        'extract_product_size' => function($selectedProducts) {
            if (is_array($selectedProducts) && !empty($selectedProducts)) {
                $firstProduct = $selectedProducts[0];
                if (isset($firstProduct['size'])) {
                    return $firstProduct['size'] . ' cm';
                }
            }
            return null;
        }
    ],

    'validation' => [
        'required_fields' => [
            'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility',
            'Is this patient currently under a post-op period'
        ],

        'radio_fields' => [
            'Place of Service',
            'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility',
            'If yes, has it been over 100 days',
            'Is this patient currently under a post-op period'
        ],

        'conditional_fields' => [
            'Other' => ['Place of Service' => 'Other'],
            'If yes, has it been over 100 days' => ['Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility' => 'Yes'],
            'If yes please list CPT codes of previous surgery' => ['Is this patient currently under a post-op period' => 'Yes'],
            'Surgery Date' => ['Is this patient currently under a post-op period' => 'Yes']
        ]
    ]
];
