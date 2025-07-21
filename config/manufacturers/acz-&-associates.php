<?php

/**
 * ACZ & Associates Manufacturer Configuration
 * 
 * This configuration follows DocuSeal API Field Mapping Rules:
 * - Radio button fields use single string values (e.g., "POS 12", "In-Network", "Yes")
 * - Field names must match DocuSeal template exactly (case-sensitive with spaces)
 * - Yes/No questions use "Yes" or "No" strings, not boolean values
 * - Place of Service uses single radio field with values: "POS 11", "POS 12", etc.
 * - Network Status uses "In-Network" or "Out-of-Network" values
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
    'folder_id' => 75423, // TO BE FILLED with actual DocuSeal template ID
    'order_form_template_id' => null, // TO BE FILLED if there's a separate order form
    
    // IVR form field mappings - EXACT field names from DocuSeal template API
    'docuseal_field_names' => [
        // Product Selection - Single radio field with all product codes
        'product_q_code' => 'Product Q Code',
        
        // Basic Information
        'representative_name' => 'Representative Name',
        'iso_if_applicable' => 'ISO if applicable',
        'additional_emails' => 'Additional Emails for Notification (requires BAA)',
        
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
    
    // Order form field mappings (if separate from IVR)
    'order_form_field_names' => [
        // Add order form specific fields here if needed
    ],
    
    // Field configuration with source mapping and transformations
    'fields' => [
        // Product Selection - Simplified to work with current condition evaluation
        'product_q_code' => [
            'source' => 'computed',
            'computation' => 'selected_products ? selected_products[0].product.q_code : ""',
            'required' => false,
            'type' => 'string' // Will be "Q4316", "Q4205", etc.
        ],
        
        // Basic Information - Fixed to work with available data
        'representative_name' => [
            'source' => 'name || sales_rep || sales_rep_name || sales_representative || representative_name',
            'required' => false, // Changed from true to false to avoid blocking
            'type' => 'string'
        ],
        'iso_if_applicable' => [
            'source' => 'iso_number || iso_if_applicable',
            'required' => false,
            'type' => 'string'
        ],
        'additional_emails' => [
            'source' => 'additional_emails || additional_notification_emails',
            'required' => false,
            'type' => 'string'
        ],
        
        // Physician Information
        'physician_name' => [
            'source' => 'physician_name || provider_name || current_user.name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'physician_npi || provider_npi',
            'required' => false,
            'type' => 'string'
        ],
        'physician_specialty' => [
            'source' => 'physician_specialty || provider_specialty',
            'required' => false,
            'type' => 'string'
        ],
        'physician_tax_id' => [
            'source' => 'physician_tax_id || provider_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'physician_ptan' => [
            'source' => 'physician_ptan || provider_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'physician_medicaid' => [
            'source' => 'physician_medicaid || provider_medicaid || physician_medicaid_number',
            'required' => false,
            'type' => 'string'
        ],
        'physician_phone' => [
            'source' => 'physician_phone || provider_phone',
            'required' => false,
            'type' => 'string'
        ],
        'physician_fax' => [
            'source' => 'physician_fax || provider_fax',
            'required' => false,
            'type' => 'string'
        ],
        'physician_organization' => [
            'source' => 'physician_organization || organization_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Facility Information
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => false,
            'type' => 'string'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id || facility_tin',
            'required' => false,
            'type' => 'string'
        ],
        'facility_name' => [
            'source' => 'facility_name',
            'required' => false,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'facility_address || facility_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'facility_medicaid' => [
            'source' => 'facility_medicaid || facility_medicaid_number',
            'required' => false,
            'type' => 'string'
        ],
        'facility_city_state_zip' => [
            'source' => 'facility_city_state_zip',
            'required' => false,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name',
            'required' => false,
            'type' => 'string'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_info' => [
            'source' => 'facility_contact_info',
            'required' => false,
            'type' => 'string'
        ],
        'facility_organization' => [
            'source' => 'facility_organization || organization_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Place of Service - Single radio field
        'place_of_service' => [
            'source' => 'computed',
            'computation' => 'place_of_service ? "POS " + place_of_service : ""',
            'required' => true,
            'type' => 'string' // Will be "POS 11", "POS 12", etc.
        ],
        'pos_other_specify' => [
            'source' => 'pos_other_specify || place_of_service_other',
            'required' => false,
            'type' => 'string'
        ],
        
        // Patient Information
        'patient_name' => [
            'source' => 'patient_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob',
            'required' => false,
            'type' => 'string'
        ],
        'patient_address' => [
            'source' => 'patient_address || patient_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'patient_city_state_zip' => [
            'source' => 'computed',
            'computation' => 'patient_city && patient_state && patient_zip ? patient_city + ", " + patient_state + " " + patient_zip : (patient_city_state_zip || "")',
            'required' => false,
            'type' => 'string'
        ],
        'patient_phone' => [
            'source' => 'patient_phone',
            'required' => false,
            'type' => 'string'
        ],
        'patient_email' => [
            'source' => 'patient_email',
            'required' => false,
            'type' => 'string'
        ],
        'patient_caregiver_info' => [
            'source' => 'patient_caregiver_info',
            'required' => false,
            'type' => 'string'
        ],
        
        // Insurance Information
        'primary_insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_name' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'primary_policy_number' => [
            'source' => 'primary_policy_number || primary_member_id',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_policy_number || secondary_member_id',
            'required' => false,
            'type' => 'string'
        ],
        'primary_payer_phone' => [
            'source' => 'primary_payer_phone',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_payer_phone' => [
            'source' => 'secondary_payer_phone',
            'required' => false,
            'type' => 'string'
        ],
        
        // Network Status - Radio fields with exact values
        'physician_status_primary' => [
            'source' => 'computed',
            'computation' => 'primary_physician_network_status === "in_network" ? "In-Network" : "Out-of-Network"',
            'required' => true,
            'type' => 'string' // Will be "In-Network" or "Out-of-Network"
        ],
        'physician_status_secondary' => [
            'source' => 'computed',
            'computation' => 'secondary_physician_network_status === "in_network" ? "In-Network" : "Out-of-Network"',
            'required' => true,
            'type' => 'string' // Will be "In-Network" or "Out-of-Network"
        ],
        
        // Authorization Questions - Radio fields with Yes/No values
        'permission_prior_auth' => [
            'source' => 'computed',
            'computation' => 'prior_auth_permission === true ? "Yes" : "No"',
            'required' => true,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_in_hospice' => [
            'source' => 'computed',
            'computation' => 'hospice_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_part_a_stay' => [
            'source' => 'computed',
            'computation' => 'part_a_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_global_surgery' => [
            'source' => 'computed',
            'computation' => 'global_period_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        
        // Conditional Surgery Fields
        'surgery_cpts' => [
            'source' => 'surgery_cpts',
            'required' => false,
            'type' => 'string'
        ],
        'surgery_date' => [
            'source' => 'surgery_date',
            'required' => false,
            'type' => 'string'
        ],
        
        // Location and Clinical - Radio field with exact option values
        'location_of_wound' => [
            'source' => 'computed',
            'computation' => 'wound_location_details || "Legs/Arms/Trunk < 100 SQ CM"',
            'required' => true,
            'type' => 'string' // Will be one of the 4 specific options
        ],
        'icd_10_codes' => [
            'source' => 'computed',
            'computation' => 'primary_diagnosis_code && secondary_diagnosis_code ? primary_diagnosis_code + ", " + secondary_diagnosis_code : (primary_diagnosis_code || secondary_diagnosis_code || "")',
            'required' => false,
            'type' => 'string'
        ],
        'total_wound_size' => [
            'source' => 'total_wound_size',
            'required' => false,
            'type' => 'string'
        ],
        'medical_history' => [
            'source' => 'medical_history',
            'required' => false,
            'type' => 'string'
        ],
    ]
];