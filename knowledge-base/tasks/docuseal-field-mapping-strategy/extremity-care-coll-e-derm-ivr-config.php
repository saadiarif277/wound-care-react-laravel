<?php

/**
 * Extremity Care Coll-e-Derm IVR Field Mapping Configuration
 *
 * Template ID: 1234285
 * Template Name: Extremity Care Coll-e-Derm IVR
 * Manufacturer: Extremity Care LLC
 *
 * This configuration maps form data to Docuseal fields for 100% completion
 */

return [
    'field_mappings' => [
        // Application Type Checkboxes
        'New Application' => [
            'source' => 'request_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_new_application'
        ],

        'Additional Application' => [
            'source' => 'request_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_additional_application'
        ],

        'Re-Verification' => [
            'source' => 'request_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_re_verification'
        ],

        'New Insurance' => [
            'source' => 'has_secondary_insurance',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'boolean_to_checkbox'
        ],

        // Place of Service Checkboxes
        'Physicians Office' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_phys_office'
        ],

        'Patient Home' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_patient_home'
        ],

        'Assisted Living' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_assisted_living'
        ],

        'Nursing Facility' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_nursing_facility'
        ],

        'Skilled Nursing' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_skilled_nursing'
        ],

        'Other' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_other'
        ],

        // Product Size Checkboxes
        '2x2' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_2x2'
        ],

        '2x3' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_2x3'
        ],

        '2x4' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_2x4'
        ],

        '4x4' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_4x4'
        ],

        '4x6' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_4x6'
        ],

        '4x8' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_size_4x8'
        ],

        // Patient Information
        'Patient Name' => [
            'source' => 'patient_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'DOB' => [
            'source' => 'patient_dob',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_date_mdy'
        ],

        'Address' => [
            'source' => ['patient_address_line1', 'patient_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_address_lines'
        ],

        'City' => [
            'source' => 'patient_city',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'State' => [
            'source' => 'patient_state',
            'type' => 'text',
            'required' => false
        ],

        'Zip' => [
            'source' => 'patient_zip',
            'type' => 'text',
            'required' => false
        ],

        // Nursing Home Days
        'If YES how many days has the patient been admitted to the skilled nursing facility or nursing home' => [
            'source' => 'nursing_home_days',
            'type' => 'text',
            'required' => false,
            'conditional' => [
                'field' => 'Nursing Facility',
                'value' => true
            ]
        ],

        // Insurance Information
        'Primary Insurance' => [
            'source' => 'primary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Payer Phone' => [
            'source' => 'primary_payer_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Policy Number' => [
            'source' => 'primary_member_id',
            'type' => 'text',
            'required' => false
        ],

        // Provider Information
        'Provider Name' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Provider ID s' => [
            'source' => 'provider_npi',
            'type' => 'text',
            'required' => false
        ],

        // Facility Information
        'Facility Name' => [
            'source' => 'facility_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Facility ID s' => [
            'source' => 'facility_npi',
            'type' => 'text',
            'required' => false
        ],

        'Facility Contact' => [
            'source' => 'facility_contact_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Facility Contact Email' => [
            'source' => 'facility_contact_email',
            'type' => 'text',
            'required' => false,
            'transform' => 'lowercase'
        ],

        // Wound Information Checkboxes
        'Check BoxA' => [
            'source' => 'wound_size_total',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_size_category'
        ],

        // Wound Size Text Fields
        'Feet/Hands/Head ≤ 100 sq cm' => [
            'source' => 'wound_size_small',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_wound_size'
        ],

        'Feet/Hands/Head ≥ 100 sq cm' => [
            'source' => 'wound_size_large',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_wound_size'
        ],

        // Diagnosis Codes
        'Wound Information  Diagnosis Codes Provide the ICD10CM Codes for the treatment condition below' => [
            'source' => ['primary_diagnosis_code', 'secondary_diagnosis_code'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_diagnosis_codes'
        ],

        // Wound Type Checkboxes
        'q Diabetic Ulcer Code Diabetes and Ulcer Locations Separately 2 codes must be present on claim' => [
            'source' => 'wound_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_type_diabetic'
        ],

        'q Venous Ulcer Code Venous and Ulcer Locations Separately 2 codes must be present on claim' => [
            'source' => 'wound_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_type_venous'
        ],

        'q Trauma Wounds' => [
            'source' => 'wound_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_type_trauma'
        ],

        'q Surgical Dehiscence' => [
            'source' => 'wound_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_type_surgical'
        ],

        'q Other' => [
            'source' => 'wound_type',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_wound_type_other'
        ]
    ],

    'transformations' => [
        'check_new_application' => function($requestType) {
            return $requestType === 'new_request';
        },

        'check_additional_application' => function($requestType) {
            return $requestType === 'additional_request';
        },

        'check_re_verification' => function($requestType) {
            return $requestType === 're_verification';
        },

        'boolean_to_checkbox' => function($value) {
            return (bool) $value;
        },

        'check_place_of_service_phys_office' => function($placeOfService) {
            $physOfficeCodes = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];
            return in_array($placeOfService, $physOfficeCodes);
        },

        'check_place_of_service_patient_home' => function($placeOfService) {
            return $placeOfService === '12'; // Patient's Home
        },

        'check_place_of_service_assisted_living' => function($placeOfService) {
            return $placeOfService === '13'; // Assisted Living Facility
        },

        'check_place_of_service_nursing_facility' => function($placeOfService) {
            return $placeOfService === '32'; // Nursing Facility
        },

        'check_place_of_service_skilled_nursing' => function($placeOfService) {
            return $placeOfService === '31'; // Skilled Nursing Facility
        },

        'check_place_of_service_other' => function($placeOfService) {
            $otherCodes = ['14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];
            return in_array($placeOfService, $otherCodes);
        },

        'check_product_size_2x2' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '4.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_size_2x3' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '6.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_size_2x4' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '8.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_size_4x4' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '16.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_size_4x6' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '24.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_size_4x8' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['size']) && $product['size'] === '32.00') {
                        return true;
                    }
                }
            }
            return false;
        },

        'title_case' => function($value) {
            if (is_string($value)) {
                return ucwords(strtolower($value));
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

        'combine_address_lines' => function($values) {
            if (is_array($values)) {
                $lines = array_filter($values);
                return implode(', ', $lines);
            }
            return $values;
        },

        'format_phone' => function($value) {
            if (!$value) return null;

            // Remove all non-numeric characters
            $phone = preg_replace('/[^0-9]/', '', $value);

            if (strlen($phone) === 10) {
                return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
            } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
                return '(' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7);
            }

            return $value;
        },

        'check_wound_size_category' => function($woundSizeTotal) {
            if (!$woundSizeTotal) return false;

            $size = floatval($woundSizeTotal);
            return $size <= 100; // Check if wound size is ≤ 100 sq cm
        },

        'format_wound_size' => function($value) {
            if (!$value) return null;

            $size = floatval($value);
            return number_format($size, 1) . ' sq cm';
        },

        'combine_diagnosis_codes' => function($values) {
            if (is_array($values)) {
                $codes = array_filter($values);
                return implode(', ', $codes);
            }
            return $values;
        },

        'check_wound_type_diabetic' => function($woundType) {
            if (is_array($woundType)) {
                return in_array('diabetic_foot_ulcer', $woundType);
            }
            return $woundType === 'diabetic_foot_ulcer';
        },

        'check_wound_type_venous' => function($woundType) {
            if (is_array($woundType)) {
                return in_array('venous_ulcer', $woundType);
            }
            return $woundType === 'venous_ulcer';
        },

        'check_wound_type_trauma' => function($woundType) {
            if (is_array($woundType)) {
                return in_array('trauma_wound', $woundType);
            }
            return $woundType === 'trauma_wound';
        },

        'check_wound_type_surgical' => function($woundType) {
            if (is_array($woundType)) {
                return in_array('surgical_dehiscence', $woundType);
            }
            return $woundType === 'surgical_dehiscence';
        },

        'check_wound_type_other' => function($woundType) {
            if (is_array($woundType)) {
                $otherTypes = ['pressure_ulcer', 'arterial_ulcer', 'burns', 'other'];
                foreach ($otherTypes as $type) {
                    if (in_array($type, $woundType)) {
                        return true;
                    }
                }
            } else {
                $otherTypes = ['pressure_ulcer', 'arterial_ulcer', 'burns', 'other'];
                return in_array($woundType, $otherTypes);
            }
            return false;
        },

        'lowercase' => function($value) {
            if (is_string($value)) {
                return strtolower($value);
            }
            return $value;
        }
    ],

    'validation' => [
        'required_fields' => [
            // No required fields in this template
        ],

        'radio_fields' => [
            // No radio fields in this template
        ],

        'conditional_fields' => [
            'If YES how many days has the patient been admitted to the skilled nursing facility or nursing home' => ['Nursing Facility' => true]
        ]
    ]
];
