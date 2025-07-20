<?php

return [
    'id' => 1,
    'name' => 'ACZ & ASSOCIATES',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,
    'docuseal_template_id' => 852440,
    'folder_id' => 75423, // TO BE FILLED with actual DocuSeal template ID
    'order_form_template_id' => null, // TO BE FILLED if there's a separate order form
    
    // IVR form field mappings - exact field names from DocuSeal template
    'docuseal_field_names' => [
        // Header Information
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'q4205' => 'Q4205',
        'q4290' => 'Q4290',
        'q4344' => 'Q4344',
        'q4289' => 'Q4289',
        'q4275' => 'Q4275',
        'q4341' => 'Q4341',
        'q4313' => 'Q4313',
        'q4316' => 'Q4316',
        'q4164' => 'Q4164',
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
        
        // Place of Service checkboxes
        'pos_11' => 'POS 11',
        'pos_22' => 'POS 22',
        'pos_24' => 'POS 24',
        'pos_12' => 'POS 12',
        'pos_32' => 'POS 32',
        'pos_other' => 'POS Other',
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
        
        // Provider Network Status - New dropdown fields from Step2PatientInsurance
        'primary_physician_network_status' => 'Primary Physician Network Status',
        'secondary_physician_network_status' => 'Secondary Physician Network Status',
        
        // Provider Network Status - Checkbox fields (for DocuSeal IVR)
        'physician_status_primary_in_network' => 'Physician Status With Primary: In-Network',
        'physician_status_primary_out_of_network' => 'Physician Status With Primary: Out-Of-Network',
        'physician_status_secondary_in_network' => 'Physician Status With Secondary: In-Network',
        'physician_status_secondary_out_of_network' => 'Physician Status With Secondary: Out-Of-Network',
        
        // Authorization Permission
        'permission_prior_auth_yes' => 'Permission To Initiate And Follow Up On Prior Auth? Yes',
        'permission_prior_auth_no' => 'Permission To Initiate And Follow Up On Prior Auth? No',
        
        // Clinical Status
        'patient_in_hospice_yes' => 'Is The Patient Currently in Hospice? Yes',
        'patient_in_hospice_no' => 'Is The Patient Currently in Hospice? No',
        'patient_facility_part_a_yes' => 'Is The Patient In A Facility Under Part A Stay? Yes',
        'patient_facility_part_a_no' => 'Is The Patient In A Facility Under Part A Stay? No',
        'patient_post_op_global_yes' => 'Is The Patient Under Post-Op Global Surgery Period? Yes',
        'patient_post_op_global_no' => 'Is The Patient Under Post-Op Global Surgery Period? No',
        'surgery_cpts' => 'If Yes, List Surgery CPTs',
        'surgery_date' => 'Surgery Date',
        
        // Wound Information
        'wound_location_legs_arms_trunk_less_100' => 'Wound Location: Legs/Arms/Trunk < 100 SQ CM',
        'wound_location_legs_arms_trunk_greater_100' => 'Wound Location: Legs/Arms/Trunk > 100 SQ CM',
        'wound_location_feet_hands_head_less_100' => 'Wound Location: Feet/Hands/Head < 100 SQ CM',
        'wound_location_feet_hands_head_greater_100' => 'Check Wound Location: Feet/Hands/Head > 100 SQ CM',
        'icd_10_codes' => 'ICD-10 Codes',
        'total_wound_size' => 'Total Wound Size',
        'medical_history' => 'Medical History'
    ],
    
    // Order form field mappings (if separate from IVR)
    'order_form_field_names' => [
        // Add order form specific fields here if needed
    ],
    
    // Field configuration with source mapping and transformations
    'fields' => [
        // Basic Contact Information
        'name' => [
            'source' => 'contact_name || submitter_name || sales_rep_name',
            'required' => true,
            'type' => 'string'
        ],
        'email' => [
            'source' => 'contact_email || submitter_email || sales_rep_email',
            'required' => true,
            'type' => 'email'
        ],
        'phone' => [
            'source' => 'contact_phone || submitter_phone || sales_rep_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'sales_rep' => [
            'source' => 'sales_rep_name || sales_representative || representative_name',
            'required' => false,
            'type' => 'string'
        ],
        'iso_if_applicable' => [
            'source' => 'iso_number || iso_id || iso',
            'required' => false,
            'type' => 'string'
        ],
        'additional_emails' => [
            'source' => 'additional_notification_emails || cc_emails',
            'required' => false,
            'type' => 'string'
        ],
        
        // Product Selection (Q-codes) - checkboxes - FIXED to use correct data structure
        'q4205' => [
            'source' => 'q4205', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4290' => [
            'source' => 'q4290', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4344' => [
            'source' => 'q4344', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4289' => [
            'source' => 'q4289', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4275' => [
            'source' => 'q4275', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4341' => [
            'source' => 'q4341', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4313' => [
            'source' => 'q4313', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4316' => [
            'source' => 'q4316', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        'q4164' => [
            'source' => 'q4164', // Pre-computed boolean from QuickRequestOrchestrator
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Physician Information
        'physician_name' => [
            'source' => 'provider_name || physician_name || prescriber_name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'provider_npi || physician_npi || prescriber_npi',
            'required' => true,
            'type' => 'string'
        ],
        'physician_specialty' => [
            'source' => 'provider_specialty || physician_specialty || specialty',
            'required' => false,
            'type' => 'string'
        ],
        'physician_tax_id' => [
            'source' => 'provider_tax_id || physician_tax_id || tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'physician_ptan' => [
            'source' => 'provider_ptan || physician_ptan || ptan',
            'required' => false,
            'type' => 'string'
        ],
        'physician_medicaid' => [
            'source' => 'provider_medicaid_number || physician_medicaid_id || medicaid_number',
            'required' => false,
            'type' => 'string'
        ],
        'physician_phone' => [
            'source' => 'provider_phone || physician_phone || prescriber_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'physician_fax' => [
            'source' => 'provider_fax || physician_fax || prescriber_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'physician_organization' => [
            'source' => 'provider_organization || physician_organization || practice_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Facility Information - FIXED with proper field mappings
        'facility_name' => [
            'source' => 'facility_name || location_name || practice_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_npi' => [
            'source' => 'facility_npi || facility_group_npi || location_npi || practice_npi',
            'required' => true,
            'type' => 'string'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id || facility_tin || organization_tax_id || ein',
            'required' => false,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan || location_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'facility_address || facility_address_line1 || facility_street || location_address',
            'required' => true,
            'type' => 'string'
        ],
        'facility_medicaid' => [
            'source' => 'facility_medicaid_number || facility_medicaid || facility_medicaid_id',
            'required' => false,
            'type' => 'string'
        ],
        'facility_city_state_zip' => [
            'source' => 'computed',
            'computation' => 'facility_city + ", " + facility_state + " " + facility_zip',
            'required' => true,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone || location_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name || office_manager_name || contact_person',
            'required' => false,
            'type' => 'string'
        ],
        'facility_fax' => [
            'source' => 'facility_fax || location_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_contact_info' => [
            'source' => 'computed',
            'computation' => 'facility_contact_phone || facility_contact_email',
            'required' => false,
            'type' => 'string'
        ],
        'facility_organization' => [
            'source' => 'facility_organization_name || organization_name || parent_organization || health_system',
            'required' => false,
            'type' => 'string'
        ],
        
        // Place of Service - FIXED to use proper field mapping
        'pos_11' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "11" || facility_default_place_of_service == "11"',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_22' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "22" || facility_default_place_of_service == "22"',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_24' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "24" || facility_default_place_of_service == "24"',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_12' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "12" || facility_default_place_of_service == "12"',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_32' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "32" || facility_default_place_of_service == "32"',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_other' => [
            'source' => 'computed',
            'computation' => 'place_of_service != "11" && place_of_service != "12" && place_of_service != "22" && place_of_service != "24" && place_of_service != "32" && place_of_service != null',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_other_specify' => [
            'source' => 'place_of_service_other || pos_other_description',
            'required' => false,
            'type' => 'string'
        ],
        
        // Patient Information
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_date_of_birth || patient_dob',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_address' => [
            'source' => 'patient_street_address || patient_address_line1',
            'required' => true,
            'type' => 'string'
        ],
        'patient_city_state_zip' => [
            'source' => 'computed',
            'computation' => 'patient_city + ", " + patient_state + " " + patient_zip',
            'required' => true,
            'type' => 'string'
        ],
        'patient_phone' => [
            'source' => 'patient_phone_number || patient_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'patient_email' => [
            'source' => 'patient_email_address || patient_email',
            'required' => false,
            'type' => 'email'
        ],
        'patient_caregiver_info' => [
            'source' => 'patient_caregiver_name || caregiver_contact || responsible_party',
            'required' => false,
            'type' => 'string'
        ],
        
        // Insurance Information
        'primary_insurance_name' => [
            'source' => 'primary_insurance_name || insurance_company || payer_name',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_insurance_name' => [
            'source' => 'secondary_insurance_name || secondary_payer_name',
            'required' => false,
            'type' => 'string'
        ],
        'primary_policy_number' => [
            'source' => 'primary_member_id || primary_policy_number || insurance_id',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_member_id || secondary_policy_number',
            'required' => false,
            'type' => 'string'
        ],
        'primary_payer_phone' => [
            'source' => 'primary_insurance_phone || payer_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'secondary_payer_phone' => [
            'source' => 'secondary_insurance_phone || secondary_payer_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Provider Network Status - New dropdown fields from Step2PatientInsurance
        'primary_physician_network_status' => [
            'source' => 'primary_physician_network_status',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_physician_network_status' => [
            'source' => 'secondary_physician_network_status',
            'required' => false,
            'type' => 'string'
        ],
        
        // Provider Network Status - Checkbox fields (for DocuSeal IVR)
        'physician_status_primary_in_network' => [
            'source' => 'computed',
            'computation' => 'primary_network_status == "in_network"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'physician_status_primary_out_of_network' => [
            'source' => 'computed',
            'computation' => 'primary_network_status == "out_of_network"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'physician_status_secondary_in_network' => [
            'source' => 'computed',
            'computation' => 'secondary_network_status == "in_network"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'physician_status_secondary_out_of_network' => [
            'source' => 'computed',
            'computation' => 'secondary_network_status == "out_of_network"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Authorization Permission - radio buttons as checkboxes
        'permission_prior_auth_yes' => [
            'source' => 'computed',
            'computation' => 'prior_auth_permission == true || prior_auth_permission == "yes"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'permission_prior_auth_no' => [
            'source' => 'computed',
            'computation' => 'prior_auth_permission == false || prior_auth_permission == "no"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Clinical Status Questions - Yes/No as checkboxes (from Step4ClinicalBilling.tsx)
        'patient_in_hospice_yes' => [
            'source' => 'computed',
            'computation' => 'hospice_status == true',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_in_hospice_no' => [
            'source' => 'computed',
            'computation' => 'hospice_status == false || hospice_status == null',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_facility_part_a_yes' => [
            'source' => 'computed',
            'computation' => 'part_a_status == true',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_facility_part_a_no' => [
            'source' => 'computed',
            'computation' => 'part_a_status == false || part_a_status == null',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_post_op_global_yes' => [
            'source' => 'computed',
            'computation' => 'global_period_status == true',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_post_op_global_no' => [
            'source' => 'computed',
            'computation' => 'global_period_status == false || global_period_status == null',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'surgery_cpts' => [
            'source' => 'global_period_cpt || surgery_cpts || prior_surgery_cpts',
            'required' => false,
            'type' => 'string'
        ],
        'surgery_date' => [
            'source' => 'global_period_surgery_date || surgery_date',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date'
        ],
        
        // Wound Location - checkboxes (mapped from Step4ClinicalBilling.tsx wound_location field)
        'wound_location_legs_arms_trunk_less_100' => [
            'source' => 'computed',
            'computation' => 'wound_location == "trunk_arms_legs_small"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_location_legs_arms_trunk_greater_100' => [
            'source' => 'computed',
            'computation' => 'wound_location == "trunk_arms_legs_large"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_location_feet_hands_head_less_100' => [
            'source' => 'computed',
            'computation' => 'wound_location == "hands_feet_head_small"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_location_feet_hands_head_greater_100' => [
            'source' => 'computed',
            'computation' => 'wound_location == "hands_feet_head_large"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Clinical Information - Updated to match Step4ClinicalBilling.tsx fields
        'icd_10_codes' => [
            'source' => 'computed',
            'computation' => 'primary_diagnosis_code && secondary_diagnosis_code ? primary_diagnosis_code + ", " + secondary_diagnosis_code : (diagnosis_code || primary_diagnosis_code || secondary_diagnosis_code)',
            'required' => true,
            'type' => 'string'
        ],
        'total_wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length && wound_size_width ? wound_size_length * wound_size_width : (wound_area_calculated || wound_size_total)',
            'transform' => 'number:2',
            'required' => true,
            'type' => 'string'
        ],
        'medical_history' => [
            'source' => 'previous_treatments || medical_history_notes || patient_medical_history || clinical_notes',
            'required' => false,
            'type' => 'string'
        ]
    ]
];