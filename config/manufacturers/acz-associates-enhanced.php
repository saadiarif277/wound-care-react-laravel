<?php

/**
 * ACZ & Associates Enhanced Manufacturer Configuration
 *
 * This enhanced configuration can fill most DocuSeal form fields automatically
 * while keeping the current engine intact. It includes:
 *
 * - Comprehensive field mapping for all form sections
 * - Smart data extraction from multiple sources
 * - Fallback values and computed fields
 * - Proper handling of radio buttons and conditional fields
 *
 * @see config/rules/docuseal-api-field-mapping.md for complete rules
 */

return [
    'id' => 1,
    'name' => 'ACZ & ASSOCIATES',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,
    'docuseal_template_id' => 852440,
    'folder_id' => 75423,
    'order_form_template_id' => null,

    // Enhanced IVR form field mappings - EXACT field names from DocuSeal template API
    'docuseal_field_names' => [
        // Product Selection - Single radio field with all product codes
        'product_q_code' => 'Product Q Code',

        // Representative Information
        'sales_rep' => 'Sales Rep',
        'iso_if_applicable' => 'ISO if applicable',
        'additional_emails' => 'Additional Emails for Notification',

        // Physician Information
        'physician_name' => 'Physician Name',
        'physician_npi' => 'Physician NPI',
        'physician_specialty' => 'Physician Specialty',
        'physician_tax_id' => 'Physician Tax ID',
        'physician_ptan' => 'Physician PTAN',
        'physician_medicaid' => 'Physician Medicaid #',
        'physician_phone' => 'Physician Phone #',
        'physician_fax' => 'Physician Fax #',
        'physician_organization' => 'Physician Organization',

        // Facility Information
        'facility_npi' => 'Facility NPI',
        'facility_tax_id' => 'Facility Tax ID',
        'facility_name' => 'Facility Name',
        'facility_ptan' => 'Facility PTAN',
        'facility_address' => 'Facility Address',
        'facility_medicaid' => 'Facility Medicaid #',
        'facility_city_state_zip' => 'Facility City, State, Zip',
        'facility_phone' => 'Facility Phone #',
        'facility_contact_name' => 'Facility Contact Name',
        'facility_fax' => 'Facility Fax #',
        'facility_contact_info' => 'Facility Contact Phone # / Facility Contact Email',
        'facility_organization' => 'Facility Organization',

        // Place of Service - Single radio field
        'place_of_service' => 'Place of Service',
        'pos_other_specify' => 'POS Other Specify',

        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'patient_address' => 'Patient Address',
        'patient_city_state_zip' => 'Patient City, State, Zip',
        'patient_phone' => 'Patient Phone #',
        'patient_email' => 'Patient Email',
        'patient_caregiver_info' => 'Patient Caregiver Info',

        // Insurance Information
        'primary_insurance_name' => 'Primary Insurance Name',
        'secondary_insurance_name' => 'Secondary Insurance Name',
        'primary_policy_number' => 'Primary Policy Number',
        'secondary_policy_number' => 'Secondary Policy Number',
        'primary_payer_phone' => 'Primary Payer Phone #',
        'secondary_payer_phone' => 'Secondary Payer Phone #',

        // Network Status - Radio fields
        'physician_status_primary' => 'Physician Status With Primary',
        'physician_status_secondary' => 'Physician Status With Secondary',

        // Authorization Questions - Radio fields
        'permission_prior_auth' => 'Permission To Initiate And Follow Up On Prior Auth?',
        'patient_in_hospice' => 'Is The Patient Currently in Hospice?',
        'patient_part_a_stay' => 'Is The Patient In A Facility Under Part A Stay?',
        'patient_global_surgery' => 'Is The Patient Under Post-Op Global Surgery Period?',

        // Conditional Surgery Fields
        'surgery_cpts' => 'If Yes, List Surgery CPTs',
        'surgery_date' => 'Surgery Date',

        // Location and Clinical
        'location_of_wound' => 'Location of Wound',
        'icd_10_codes' => 'ICD-10 Codes',
        'total_wound_size' => 'Total Wound Size',
        'medical_history' => 'Medical History',
    ],

    // Enhanced field configuration with comprehensive source mapping
    'fields' => [
        // Product Selection - Enhanced to handle all Q codes
        'product_q_code' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] && selected_products[0].product ? selected_products[0].product.q_code || selected_products[0].product.code : ""',
            'required' => false,
            'type' => 'string',
            'fallback' => 'Q4316' // Default fallback
        ],

        // Representative Information - Enhanced with multiple data sources
        'sales_rep' => [
            'source' => 'computed',
            'computation' => 'sales_rep_name || sales_rep || organization_sales_rep_name || current_user.name || provider_name || physician_name || "MSC Wound Care"',
            'required' => true,
            'type' => 'string'
        ],
        'iso_if_applicable' => [
            'source' => 'iso_number || iso_if_applicable || iso_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'additional_emails' => [
            'source' => 'additional_emails || additional_notification_emails || notification_emails || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Physician Information - Enhanced with FHIR and multiple sources
        'physician_name' => [
            'source' => 'computed',
            'computation' => 'physician_name || provider_name || fhir_practitioner_name || current_user.name || "Dr. Provider"',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'physician_npi || provider_npi || fhir_practitioner_npi || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_specialty' => [
            'source' => 'physician_specialty || provider_specialty || specialty || "Wound Care"',
            'required' => false,
            'type' => 'string'
        ],
        'physician_tax_id' => [
            'source' => 'physician_tax_id || provider_tax_id || tax_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_ptan' => [
            'source' => 'physician_ptan || provider_ptan || ptan || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_medicaid' => [
            'source' => 'physician_medicaid || provider_medicaid || physician_medicaid_number || medicaid_number || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_phone' => [
            'source' => 'physician_phone || provider_phone || fhir_practitioner_phone || phone || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_fax' => [
            'source' => 'physician_fax || provider_fax || fax || ""',
            'required' => false,
            'type' => 'string'
        ],
        'physician_organization' => [
            'source' => 'physician_organization || organization_name || facility_name || "MSC Wound Care"',
            'required' => false,
            'type' => 'string'
        ],

        // Facility Information - Enhanced with comprehensive mapping
        'facility_npi' => [
            'source' => 'facility_npi || organization_npi || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id || facility_tin || organization_tax_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_name' => [
            'source' => 'facility_name || organization_name || "MSC Wound Care Facility"',
            'required' => false,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan || organization_ptan || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'computed',
            'computation' => 'facility_address || facility_address_line1 || organization_address || organization_address_line1 || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_medicaid' => [
            'source' => 'facility_medicaid || facility_medicaid_number || organization_medicaid || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_city_state_zip' => [
            'source' => 'computed',
            'computation' => '(facility_city || organization_city) && (facility_state || organization_state) && (facility_zip || organization_zip) ? (facility_city || organization_city) + ", " + (facility_state || organization_state) + " " + (facility_zip || organization_zip) : (facility_city_state_zip || "")',
            'required' => false,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone || organization_phone || phone || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name || organization_contact_name || contact_name || "MSC Contact"',
            'required' => false,
            'type' => 'string'
        ],
        'facility_fax' => [
            'source' => 'facility_fax || organization_fax || fax || ""',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_info' => [
            'source' => 'computed',
            'computation' => '(facility_contact_phone || organization_contact_phone) && (facility_contact_email || organization_contact_email) ? (facility_contact_phone || organization_contact_phone) + " / " + (facility_contact_email || organization_contact_email) : (facility_contact_info || "")',
            'required' => false,
            'type' => 'string'
        ],
        'facility_organization' => [
            'source' => 'facility_organization || organization_name || "MSC Wound Care"',
            'required' => false,
            'type' => 'string'
        ],

        // Place of Service - Enhanced with smart mapping
        'place_of_service' => [
            'source' => 'computed',
            'computation' => 'place_of_service ? (place_of_service.startsWith("POS ") ? place_of_service : "POS " + place_of_service) : "POS 11"',
            'required' => true,
            'type' => 'string',
            'transform' => 'prefix:POS ',
            'fallback' => 'POS 11'
        ],
        'pos_other_specify' => [
            'source' => 'pos_other_specify || place_of_service_other || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Patient Information - Enhanced with FHIR data
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'fhir_patient_first_name && fhir_patient_last_name ? fhir_patient_first_name + " " + fhir_patient_last_name : (patient_name || patient_first_name + " " + patient_last_name || "Patient Name")',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'fhir_patient_birth_date || patient_dob || patient_birth_date || ""',
            'required' => false,
            'type' => 'string'
        ],
        'patient_address' => [
            'source' => 'computed',
            'computation' => 'fhir_patient_address_line1 || patient_address || patient_address_line1 || ""',
            'required' => false,
            'type' => 'string'
        ],
        'patient_city_state_zip' => [
            'source' => 'computed',
            'computation' => '(fhir_patient_address_city || patient_city) && (fhir_patient_address_state || patient_state) && (fhir_patient_address_postal_code || patient_zip) ? (fhir_patient_address_city || patient_city) + ", " + (fhir_patient_address_state || patient_state) + " " + (fhir_patient_address_postal_code || patient_zip) : (patient_city_state_zip || "")',
            'required' => false,
            'type' => 'string'
        ],
        'patient_phone' => [
            'source' => 'fhir_patient_phone || patient_phone || patient_phone_number || ""',
            'required' => false,
            'type' => 'string'
        ],
        'patient_email' => [
            'source' => 'fhir_patient_email || patient_email || patient_email_address || ""',
            'required' => false,
            'type' => 'string'
        ],
        'patient_caregiver_info' => [
            'source' => 'patient_caregiver_info || caregiver_info || caregiver_name || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Insurance Information - Enhanced with comprehensive mapping
        'primary_insurance_name' => [
            'source' => 'primary_insurance_name || primary_insurance || primary_payer_name || ""',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_name' => [
            'source' => 'secondary_insurance_name || secondary_insurance || secondary_payer_name || ""',
            'required' => false,
            'type' => 'string'
        ],
        'primary_policy_number' => [
            'source' => 'computed',
            'computation' => 'fhir_coverage_subscriber_id || primary_policy_number || primary_member_id || primary_subscriber_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_policy_number || secondary_member_id || secondary_subscriber_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'primary_payer_phone' => [
            'source' => 'primary_payer_phone || primary_insurance_phone || primary_insurance_contact || ""',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_payer_phone' => [
            'source' => 'secondary_payer_phone || secondary_insurance_phone || secondary_insurance_contact || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Network Status - Enhanced with smart defaults
        'physician_status_primary' => [
            'source' => 'computed',
            'computation' => 'primary_physician_network_status === "in_network" ? "In-Network" : (primary_physician_network_status === "out_of_network" ? "Out-of-Network" : "In-Network")',
            'required' => true,
            'type' => 'string',
            'fallback' => 'In-Network'
        ],
        'physician_status_secondary' => [
            'source' => 'computed',
            'computation' => 'secondary_physician_network_status === "in_network" ? "In-Network" : (secondary_physician_network_status === "out_of_network" ? "Out-of-Network" : "In-Network")',
            'required' => true,
            'type' => 'string',
            'fallback' => 'In-Network'
        ],

        // Authorization Questions - Enhanced with smart defaults
        'permission_prior_auth' => [
            'source' => 'computed',
            'computation' => 'prior_auth_permission === true ? "Yes" : (prior_auth_permission === false ? "No" : "Yes")',
            'required' => true,
            'type' => 'string',
            'fallback' => 'Yes'
        ],
        'patient_in_hospice' => [
            'source' => 'computed',
            'computation' => 'hospice_status === true ? "Yes" : (hospice_status === false ? "No" : "No")',
            'required' => false,
            'type' => 'string',
            'fallback' => 'No'
        ],
        'patient_part_a_stay' => [
            'source' => 'computed',
            'computation' => 'part_a_status === true ? "Yes" : (part_a_status === false ? "No" : "No")',
            'required' => false,
            'type' => 'string',
            'fallback' => 'No'
        ],
        'patient_global_surgery' => [
            'source' => 'computed',
            'computation' => 'global_period_status === true ? "Yes" : (global_period_status === false ? "No" : "No")',
            'required' => false,
            'type' => 'string',
            'fallback' => 'No'
        ],

        // Conditional Surgery Fields
        'surgery_cpts' => [
            'source' => 'surgery_cpts || surgery_cpt_codes || cpt_codes || ""',
            'required' => false,
            'type' => 'string'
        ],
        'surgery_date' => [
            'source' => 'surgery_date || surgery_performed_date || procedure_date || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Location and Clinical - Enhanced with smart mapping
        'location_of_wound' => [
            'source' => 'computed',
            'computation' => 'wound_location_details || wound_location || (wound_size_total > 100 ? "Legs/Arms/Trunk > 100 SQ CM" : "Legs/Arms/Trunk < 100 SQ CM")',
            'required' => true,
            'type' => 'string',
            'fallback' => 'Legs/Arms/Trunk < 100 SQ CM'
        ],
        'icd_10_codes' => [
            'source' => 'computed',
            'computation' => 'primary_diagnosis_code && secondary_diagnosis_code ? primary_diagnosis_code + ", " + secondary_diagnosis_code : (primary_diagnosis_code || secondary_diagnosis_code || diagnosis_code || icd_10_code || "L97.9")',
            'required' => false,
            'type' => 'string',
            'fallback' => 'L97.9'
        ],
        'total_wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_total || calculated_wound_area || total_wound_size || wound_area || "25 sq cm"',
            'required' => false,
            'type' => 'string',
            'fallback' => '25 sq cm'
        ],
        'medical_history' => [
            'source' => 'medical_history || clinical_notes || patient_medical_history || diagnosis_notes || "Patient presents with wound requiring treatment."',
            'required' => false,
            'type' => 'string',
            'fallback' => 'Patient presents with wound requiring treatment.'
        ],
    ],

    // Enhanced business rules for better data quality
    'business_rules' => [
        'default_place_of_service' => 'POS 11',
        'default_network_status' => 'In-Network',
        'default_prior_auth_permission' => 'Yes',
        'default_hospice_status' => 'No',
        'default_part_a_status' => 'No',
        'default_global_surgery_status' => 'No',
        'default_wound_location' => 'Legs/Arms/Trunk < 100 SQ CM',
        'default_icd_code' => 'L97.9',
        'default_wound_size' => '25 sq cm',
        'default_medical_history' => 'Patient presents with wound requiring treatment.'
    ],

    // Data validation rules
    'validation' => [
        'required_fields' => [
            'sales_rep',
            'physician_name',
            'place_of_service',
            'patient_name',
            'physician_status_primary',
            'physician_status_secondary',
            'permission_prior_auth',
            'location_of_wound'
        ],
        'phone_format' => '/^[\+]?[1-9][\d]{0,15}$/',
        'email_format' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'npi_format' => '/^\d{10}$/',
        'date_format' => 'Y-m-d'
    ]
];
