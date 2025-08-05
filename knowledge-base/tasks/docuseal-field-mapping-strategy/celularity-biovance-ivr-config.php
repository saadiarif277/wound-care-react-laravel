<?php

/**
 * Celularity Biovance IVR Field Mapping Configuration
 *
 * Template ID: 1330769
 * Template Name: Celularity Biovance IVR
 * Manufacturer: Celularity
 *
 * This configuration maps form data to Docuseal fields for 100% completion
 */

return [
    'field_mappings' => [
        // Product Selection Checkboxes
        'INTERFYL' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_interfyl'
        ],

        'BIOVANCE 3L' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_biovance_3l'
        ],

        'BIOVANCE' => [
            'source' => 'selected_products',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_product_biovance'
        ],

        // Account Executive Information
        'Account Executive contact information name and email' => [
            'source' => ['account_executive_name', 'account_executive_email'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_name_email'
        ],

        // Patient Information
        'PATIENT NAME' => [
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

        'PATIENT ADDRESS' => [
            'source' => ['patient_address_line1', 'patient_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_address_lines'
        ],

        'CITY STATE ZIP' => [
            'source' => ['patient_city', 'patient_state', 'patient_zip'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_city_state_zip'
        ],

        // Insurance Information
        'PRIM INS' => [
            'source' => 'primary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'PRIM MEM ID' => [
            'source' => 'primary_member_id',
            'type' => 'text',
            'required' => false
        ],

        'PRIM INS PH' => [
            'source' => 'primary_payer_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'SEC INS' => [
            'source' => 'secondary_insurance_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'SEC MEM ID' => [
            'source' => 'secondary_member_id',
            'type' => 'text',
            'required' => false
        ],

        'SEC INS PH' => [
            'source' => 'secondary_payer_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        // Global Period Questions
        'SURG GLOBAL' => [
            'source' => 'global_period_status',
            'type' => 'radio',
            'required' => false,
            'options' => [
                'yes' => 'yes',
                'no' => 'no'
            ],
            'transform' => 'boolean_to_yes_no'
        ],

        'If yes what is the CPT surgery code' => [
            'source' => 'surgery_cpt_codes',
            'type' => 'text',
            'required' => false,
            'conditional' => [
                'field' => 'SURG GLOBAL',
                'value' => 'yes'
            ],
            'transform' => 'array_to_comma_separated'
        ],

        // SNF Question
        'SNF' => [
            'source' => 'nursing_home_status',
            'type' => 'radio',
            'required' => false,
            'options' => [
                'yes' => 'yes',
                'no' => 'no'
            ],
            'transform' => 'boolean_to_yes_no'
        ],

        // Place of Service Checkboxes
        'PHYS OFC' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_phys_office'
        ],

        'HOPD' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_hopd'
        ],

        'ASC' => [
            'source' => 'place_of_service',
            'type' => 'checkbox',
            'required' => false,
            'transform' => 'check_place_of_service_asc'
        ],

        'Other Please Write In' => [
            'source' => 'place_of_service_other',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        // Physician Information
        'Rendering Physician Name' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'PHYS OFC NPI' => [
            'source' => 'provider_npi',
            'type' => 'text',
            'required' => false
        ],

        'PHYS OFC TIN' => [
            'source' => 'provider_tax_id',
            'type' => 'text',
            'required' => false
        ],

        'PHYS OFC PTAN' => [
            'source' => 'provider_ptan',
            'type' => 'text',
            'required' => false
        ],

        'Rendering Physician Address' => [
            'source' => ['facility_address_line1', 'facility_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_address_lines'
        ],

        'Rendering Physician PRIM CONTACT NAME' => [
            'source' => 'facility_contact_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Rendering Physician CONTACT EMAIL' => [
            'source' => 'facility_contact_email',
            'type' => 'text',
            'required' => false,
            'transform' => 'lowercase'
        ],

        'PHYS OFC PHONE' => [
            'source' => 'facility_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'PHYS OFC FAX' => [
            'source' => 'facility_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'PHYS OFC CONTACT PH' => [
            'source' => 'facility_contact_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'PHYS OFC CONTACT FAX' => [
            'source' => 'facility_contact_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        // Facility Information
        'FAC NAME' => [
            'source' => 'facility_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'FAC PHONE' => [
            'source' => 'facility_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'FAC ADDRESS' => [
            'source' => ['facility_address_line1', 'facility_address_line2'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_address_lines'
        ],

        'FAC FAX' => [
            'source' => 'facility_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'FAC TIN' => [
            'source' => 'facility_tax_id',
            'type' => 'text',
            'required' => false
        ],

        'FAC NPI' => [
            'source' => 'facility_npi',
            'type' => 'text',
            'required' => false
        ],

        'GRP PTAN' => [
            'source' => 'facility_ptan',
            'type' => 'text',
            'required' => false
        ],

        'FAC CONTACT NAME' => [
            'source' => 'facility_contact_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'FAC CONTACT EMAIL' => [
            'source' => 'facility_contact_email',
            'type' => 'text',
            'required' => false,
            'transform' => 'lowercase'
        ],

        'FAC CONTACT PHONE' => [
            'source' => 'facility_contact_phone',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'FAC CONTACT FAX' => [
            'source' => 'facility_contact_fax',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_phone'
        ],

        // Procedure Information
        'Procedure Date' => [
            'source' => 'expected_service_date',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_date_mdy'
        ],

        'CPT AND HCPCS Codes' => [
            'source' => ['application_cpt_codes', 'primary_hcpcs_code'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_cpt_hcpcs_codes'
        ],

        'Diagnosis ICD10 Codes' => [
            'source' => ['primary_diagnosis_code', 'secondary_diagnosis_code'],
            'type' => 'text',
            'required' => false,
            'transform' => 'combine_diagnosis_codes'
        ],

        // Wound Information
        'Wound Sizes' => [
            'source' => ['wound_size_length', 'wound_size_width'],
            'type' => 'text',
            'required' => false,
            'transform' => 'format_wound_sizes'
        ],

        'Wound Location' => [
            'source' => 'wound_location_details',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Additional Patient Notes' => [
            'source' => 'clinical_notes',
            'type' => 'text',
            'required' => false
        ],

        // Product Information
        'No GRAFTS' => [
            'source' => 'selected_products',
            'type' => 'text',
            'required' => false,
            'transform' => 'count_products'
        ],

        'SIZE INITIAL APP' => [
            'source' => ['selected_products'],
            'type' => 'text',
            'required' => false,
            'transform' => 'extract_product_size'
        ],

        // Signature Date
        'SIG DATE' => [
            'source' => 'submission_date',
            'type' => 'text',
            'required' => false,
            'transform' => 'format_date_mdy'
        ]
    ],

    'transformations' => [
        'check_product_interfyl' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['product']['name']) &&
                        stripos($product['product']['name'], 'interfyl') !== false) {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_biovance_3l' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['product']['name']) &&
                        stripos($product['product']['name'], 'biovance 3l') !== false) {
                        return true;
                    }
                }
            }
            return false;
        },

        'check_product_biovance' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                foreach ($selectedProducts as $product) {
                    if (isset($product['product']['name']) &&
                        stripos($product['product']['name'], 'biovance') !== false &&
                        stripos($product['product']['name'], '3l') === false) {
                        return true;
                    }
                }
            }
            return false;
        },

        'combine_name_email' => function($values) {
            if (is_array($values) && count($values) >= 2) {
                $name = $values[0] ?? '';
                $email = $values[1] ?? '';
                if ($name && $email) {
                    return $name . ' - ' . $email;
                } elseif ($name) {
                    return $name;
                } elseif ($email) {
                    return $email;
                }
            }
            return null;
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

        'combine_city_state_zip' => function($values) {
            if (is_array($values) && count($values) >= 3) {
                $city = $values[0] ?? '';
                $state = $values[1] ?? '';
                $zip = $values[2] ?? '';

                $parts = array_filter([$city, $state, $zip]);
                return implode(', ', $parts);
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

        'boolean_to_yes_no' => function($value) {
            if (is_bool($value)) {
                return $value ? 'yes' : 'no';
            }
            if (is_string($value)) {
                $lower = strtolower($value);
                if (in_array($lower, ['true', '1', 'yes'])) return 'yes';
                if (in_array($lower, ['false', '0', 'no'])) return 'no';
            }
            return $value;
        },

        'array_to_comma_separated' => function($value) {
            if (is_array($value)) {
                return implode(', ', array_filter($value));
            }
            return $value;
        },

        'check_place_of_service_phys_office' => function($placeOfService) {
            $physOfficeCodes = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];
            return in_array($placeOfService, $physOfficeCodes);
        },

        'check_place_of_service_hopd' => function($placeOfService) {
            return $placeOfService === '22'; // Hospital Outpatient Department
        },

        'check_place_of_service_asc' => function($placeOfService) {
            return $placeOfService === '24'; // Ambulatory Surgical Center
        },

        'combine_cpt_hcpcs_codes' => function($values) {
            if (is_array($values) && count($values) >= 2) {
                $cptCodes = $values[0] ?? [];
                $hcpcsCode = $values[1] ?? '';

                $allCodes = [];
                if (is_array($cptCodes)) {
                    $allCodes = array_merge($allCodes, array_filter($cptCodes));
                }
                if ($hcpcsCode) {
                    $allCodes[] = $hcpcsCode;
                }

                return implode(', ', $allCodes);
            }
            return $values;
        },

        'combine_diagnosis_codes' => function($values) {
            if (is_array($values) && count($values) >= 2) {
                $primary = $values[0] ?? '';
                $secondary = $values[1] ?? '';

                $codes = array_filter([$primary, $secondary]);
                return implode(', ', $codes);
            }
            return $values;
        },

        'format_wound_sizes' => function($values) {
            if (is_array($values) && count($values) >= 2) {
                $length = $values[0] ?? '';
                $width = $values[1] ?? '';

                if ($length && $width) {
                    return $length . 'cm x ' . $width . 'cm';
                } elseif ($length) {
                    return $length . 'cm';
                } elseif ($width) {
                    return $width . 'cm';
                }
            }
            return null;
        },

        'count_products' => function($selectedProducts) {
            if (is_array($selectedProducts)) {
                return (string) count($selectedProducts);
            }
            return '0';
        },

        'extract_product_size' => function($selectedProducts) {
            if (is_array($selectedProducts) && !empty($selectedProducts)) {
                $firstProduct = $selectedProducts[0];
                if (isset($firstProduct['size'])) {
                    return $firstProduct['size'] . ' cm';
                }
            }
            return null;
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
            'SURG GLOBAL',
            'SNF'
        ],

        'conditional_fields' => [
            'If yes what is the CPT surgery code' => ['SURG GLOBAL' => 'yes']
        ]
    ]
];
