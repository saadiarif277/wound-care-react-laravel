<?php

return [
    'id' => 11,
    'name' => 'ADVANCED SOLUTION',
    'signature_required' => true,
    'has_order_form' => true,
    'duration_requirement' => 'greater_than_4_weeks',

    // IVR Template Configuration (Template ID: 1199885)
    'ivr_template_id' => 1199885,
    'ivr_template_name' => 'Advanced Solution IVR',

    // Complete field mapping for IVR template based on actual DocuSeal template
    'docuseal_field_names' => [
        // Basic Information
        'sales_rep' => 'Sales Rep',
        'office' => 'Office',
        'outpatient_hospital' => 'Outpatient Hospital',
        'ambulatory_surgical_center' => 'Ambulatory Surgical Center',
        'other_place_of_service' => 'Other',
        'pos_other' => 'POS Other',
        'mac' => 'MAC',

        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_address' => 'Facility Address',
        'facility_npi' => 'Facility NPI',
        'facility_contact_name' => 'Factility Contact Name', // Note: Typo in original template
        'facility_tin' => 'Facility TIN',
        'facility_phone' => 'Facility Phone Number',
        'facility_ptan' => 'Facility PTAN',
        'facility_fax' => 'Facility Fax Number',

        // Physician Information
        'physician_name' => 'Physician Name',
        'physician_fax' => 'Physician Fax',
        'physician_address' => 'Physician Address',
        'physician_npi' => 'Physician NPI',
        'physician_phone' => 'Physician Phone',
        'physician_tin' => 'Physician TIN',

        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_phone' => 'Patient Phone',
        'patient_address' => 'Patient Address',
        'ok_to_contact_patient_yes' => 'Ok to Contact Patient Yes',
        'ok_to_contact_patient_no' => 'OK to Contact Patient No',
        'patient_dob' => 'Patient DOB',

        // Primary Insurance Information
        'primary_insurance_name' => 'Primary Insurance Name',
        'primary_subscriber_name' => 'Primary Subscriber Name',
        'primary_policy_number' => 'Primary Policy Number',
        'primary_subscriber_dob' => 'Primary Subscriber DOB',
        'primary_type_plan_hmo' => 'Primary Type of Plan HMO',
        'primary_type_plan_ppo' => 'Primary Type of Plan PPO',
        'primary_type_plan_other' => 'Primary Type of Plan Other',
        'primary_type_plan_other_string' => 'Primary Type of Plan Other (String)',
        'primary_insurance_phone' => 'Primary Insurance Phone Number',
        'physician_status_primary_in_network' => 'Physician Status With Primary: In-Network',
        'physician_status_primary_out_network' => 'Physician Status With Primary: Out-of-Network',
        'primary_in_network_not_sure' => 'Primary In-Network Not Sure',

        // Secondary Insurance Information
        'secondary_insurance' => 'Secondary Insurance',
        'secondary_subscriber_name' => 'Secondary Subscriber Name',
        'secondary_policy_number' => 'Secondary Policy Number',
        'secondary_subscriber_dob' => 'Secondary Subscriber DOB',
        'secondary_type_plan_hmo' => 'Secondary Type of Plan HMO',
        'secondary_type_plan_ppo' => 'Secondary Type of Plan PPO',
        'secondary_type_plan_other' => 'Secondary Type of Plan Other',
        'secondary_type_plan_other_string' => 'Secondary Type of Plan Other (String)',
        'secondary_insurance_phone' => 'Secondary Insurance Phone Number',
        'physician_status_secondary_in_network' => 'Physician Status With Secondary: In-Network',
        'physician_status_secondary_out_network' => 'Physician Status With Secondary: Out-of-Network',
        'secondary_in_network_not_sure' => 'Secondary In-Network Not Sure',

        // Wound Information
        'diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
        'venous_leg_ulcer' => 'Venous Leg Ulcer',
        'pressure_ulcer' => 'Pressure Ulcer',
        'traumatic_burns' => 'Traumatic Burns',
        'radiation_burns' => 'Radiation Burns',
        'necrotizing_facilitis' => 'Necrotizing Facilitis', // Note: Typo in original template
        'dehisced_surgical_wound' => 'Dehisced Surgical Wound',
        'other_wound' => 'Other Wound',
        'type_of_wound_other' => 'Type of Wound Other',
        'wound_size' => 'Wound Size',
        'cpt_codes' => 'CPT Codes',
        'date_of_service' => 'Date of Service',
        'icd10_diagnosis_codes' => 'ICD-10 Diagnosis Codes',

        // Product Information
        'complete_aa' => 'Complete AA',
        'membrane_wrap_hydro' => 'Membrane Wrap Hydro',
        'membrane_wrap' => 'Membrane Wrap',
        'wound_plus' => 'WoundPlus',
        'complete_ft' => 'CompleteFT',
        'other_product' => 'Other Product',
        'product_other' => 'Product Other',
        'is_patient_curer' => 'Is Patient Curer',

        // Additional Clinical Information
        'patient_in_snf_no' => 'Patient in SNF No',
        'patient_under_global_yes' => 'Patient Under Global Yes',
        'patient_under_global_no' => 'Patient Under Global No',
        'prior_auth' => 'Prior Auth',
        'cpt_codes_2' => 'CPT Codes', // Second CPT Codes field
        'specialty_site_name' => 'Specialty Site Name',

        // Signature and Documentation
        'physician_signature' => 'Physician or Authorized Signature',
        'date_signed' => 'Date Signed',
        'insurance_card' => 'Insurance Card',
    ],

    // Field configurations with source mappings and transformations
    'fields' => [
        // Basic Information
        'sales_rep' => [
            'source' => 'sales_rep_name',
            'required' => false,
            'type' => 'text',
            'fallback' => 'MSC Wound Care Representative'
        ],
        'office' => [
            'source' => 'place_of_service',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:office'
        ],
        'outpatient_hospital' => [
            'source' => 'place_of_service',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:outpatient_hospital'
        ],
        'ambulatory_surgical_center' => [
            'source' => 'place_of_service',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:ambulatory_surgical_center'
        ],
        'other_place_of_service' => [
            'source' => 'place_of_service',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:other'
        ],
        'pos_other' => [
            'source' => 'place_of_service_other',
            'required' => false,
            'type' => 'text',
            'conditional' => 'other_place_of_service',
            'conditional_value' => true
        ],
        'mac' => [
            'source' => 'medicare_mac',
            'required' => false,
            'type' => 'text'
        ],

        // Facility Information
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'text'
        ],
        'facility_address' => [
            'source' => 'facility_address_line1',
            'required' => false,
            'type' => 'text'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => false,
            'type' => 'text'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name',
            'required' => false,
            'type' => 'text'
        ],
        'facility_tin' => [
            'source' => 'facility_tin',
            'required' => false,
            'type' => 'text'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan',
            'required' => false,
            'type' => 'text'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],

        // Physician Information
        'physician_name' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'text'
        ],
        'physician_fax' => [
            'source' => 'provider_fax',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'physician_address' => [
            'source' => 'provider_address_line1',
            'required' => false,
            'type' => 'text'
        ],
        'physician_npi' => [
            'source' => 'provider_npi',
            'required' => true,
            'type' => 'text'
        ],
        'physician_phone' => [
            'source' => 'provider_phone',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'physician_tin' => [
            'source' => 'provider_tin',
            'required' => false,
            'type' => 'text'
        ],

        // Patient Information
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
            'required' => true,
            'type' => 'text'
        ],
        'patient_phone' => [
            'source' => 'patient_phone',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'patient_address' => [
            'source' => 'patient_address_line1',
            'required' => false,
            'type' => 'text'
        ],
        'ok_to_contact_patient_yes' => [
            'source' => 'ok_to_contact_patient',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:true'
        ],
        'ok_to_contact_patient_no' => [
            'source' => 'ok_to_contact_patient',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:false'
        ],
        'patient_dob' => [
            'source' => 'patient_dob',
            'required' => true,
            'type' => 'date',
            'transform' => 'date:m/d/Y'
        ],

        // Primary Insurance Information
        'primary_insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => true,
            'type' => 'text'
        ],
        'primary_subscriber_name' => [
            'source' => 'primary_subscriber_name',
            'required' => true,
            'type' => 'text'
        ],
        'primary_policy_number' => [
            'source' => 'primary_member_id', // Map from real data field
            'required' => false,
            'type' => 'text'
        ],
        'primary_subscriber_dob' => [
            'source' => 'primary_subscriber_dob',
            'required' => false,
            'type' => 'date',
            'transform' => 'date:m/d/Y'
        ],
        'primary_type_plan_hmo' => [
            'source' => 'primary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:hmo'
        ],
        'primary_type_plan_ppo' => [
            'source' => 'primary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:ppo'
        ],
        'primary_type_plan_other' => [
            'source' => 'primary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:other'
        ],
        'primary_type_plan_other_string' => [
            'source' => 'primary_plan_type_other',
            'required' => false,
            'type' => 'text',
            'conditional' => 'primary_type_plan_other',
            'conditional_value' => true
        ],
        'primary_insurance_phone' => [
            'source' => 'primary_insurance_phone',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'physician_status_primary_in_network' => [
            'source' => 'physician_status_primary',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:in_network'
        ],
        'physician_status_primary_out_network' => [
            'source' => 'physician_status_primary',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:out_of_network'
        ],
        'primary_in_network_not_sure' => [
            'source' => 'primary_in_network_not_sure',
            'required' => false,
            'type' => 'text',
            'fallback' => ''
        ],

        // Secondary Insurance Information
        'secondary_insurance' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'text'
        ],
        'secondary_subscriber_name' => [
            'source' => 'secondary_subscriber_name',
            'required' => false,
            'type' => 'text'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_policy_number',
            'required' => false,
            'type' => 'text'
        ],
        'secondary_subscriber_dob' => [
            'source' => 'secondary_subscriber_dob',
            'required' => false,
            'type' => 'date',
            'transform' => 'date:m/d/Y'
        ],
        'secondary_type_plan_hmo' => [
            'source' => 'secondary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:hmo'
        ],
        'secondary_type_plan_ppo' => [
            'source' => 'secondary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:ppo'
        ],
        'secondary_type_plan_other' => [
            'source' => 'secondary_plan_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:other'
        ],
        'secondary_type_plan_other_string' => [
            'source' => 'secondary_plan_type_other',
            'required' => false,
            'type' => 'text',
            'conditional' => 'secondary_type_plan_other',
            'conditional_value' => true
        ],
        'secondary_insurance_phone' => [
            'source' => 'secondary_insurance_phone',
            'required' => false,
            'type' => 'phone',
            'transform' => 'phone:US'
        ],
        'physician_status_secondary_in_network' => [
            'source' => 'physician_status_secondary',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:in_network'
        ],
        'physician_status_secondary_out_network' => [
            'source' => 'physician_status_secondary',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:out_of_network'
        ],
        'secondary_in_network_not_sure' => [
            'source' => 'secondary_in_network_not_sure',
            'required' => false,
            'type' => 'text',
            'fallback' => ''
        ],

        // Wound Information
        'diabetic_foot_ulcer' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:diabetic_foot_ulcer'
        ],
        'venous_leg_ulcer' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:venous_leg_ulcer'
        ],
        'pressure_ulcer' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:pressure_ulcer'
        ],
        'traumatic_burns' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:traumatic_burns'
        ],
        'radiation_burns' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:radiation_burns'
        ],
        'necrotizing_facilitis' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:necrotizing_facilitis'
        ],
        'dehisced_surgical_wound' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:dehisced_surgical_wound'
        ],
        'other_wound' => [
            'source' => 'wound_type',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:other'
        ],
        'type_of_wound_other' => [
            'source' => 'wound_type_other',
            'required' => false,
            'type' => 'text',
            'conditional' => 'other_wound',
            'conditional_value' => true
        ],
        'wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + " x " + wound_size_width + " cm"',
            'required' => false,
            'type' => 'text'
        ],
        'cpt_codes' => [
            'source' => 'application_cpt_codes',
            'required' => false,
            'type' => 'text',
            'transform' => 'array_to_string'
        ],
        'date_of_service' => [
            'source' => 'expected_service_date', // Map from real data field
            'required' => true,
            'type' => 'date',
            'transform' => 'date:m/d/Y'
        ],
        'icd10_diagnosis_codes' => [
            'source' => 'computed',
            'computation' => 'primary_diagnosis_code + ", " + secondary_diagnosis_code',
            'required' => false,
            'type' => 'text'
        ],

        // Product Information
        'complete_aa' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:complete_aa'
        ],
        'membrane_wrap_hydro' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:membrane_wrap_hydro'
        ],
        'membrane_wrap' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:membrane_wrap'
        ],
        'wound_plus' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:wound_plus'
        ],
        'complete_ft' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:complete_ft'
        ],
        'other_product' => [
            'source' => 'selected_products',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'contains:other'
        ],
        'product_other' => [
            'source' => 'product_other',
            'required' => false,
            'type' => 'text',
            'conditional' => 'other_product',
            'conditional_value' => true
        ],
        'is_patient_curer' => [
            'source' => 'is_patient_curer',
            'required' => false,
            'type' => 'checkbox'
        ],

        // Additional Clinical Information
        'patient_in_snf_no' => [
            'source' => 'patient_in_snf',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:false'
        ],
        'patient_under_global_yes' => [
            'source' => 'patient_under_global',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:true'
        ],
        'patient_under_global_no' => [
            'source' => 'patient_under_global',
            'required' => false,
            'type' => 'checkbox',
            'transform' => 'equals:false'
        ],
        'prior_auth' => [
            'source' => 'prior_auth_required',
            'required' => false,
            'type' => 'checkbox'
        ],
        'cpt_codes_2' => [
            'source' => 'cpt_codes',
            'required' => false,
            'type' => 'text'
        ],
        'specialty_site_name' => [
            'source' => 'specialty_site_name',
            'required' => false,
            'type' => 'text'
        ],

        // Signature and Documentation
        'physician_signature' => [
            'source' => 'physician_signature',
            'required' => false,
            'type' => 'signature'
        ],
        'date_signed' => [
            'source' => 'computed',
            'computation' => 'current_date',
            'required' => false,
            'type' => 'date',
            'transform' => 'date:m/d/Y'
        ],
        'insurance_card' => [
            'source' => 'insurance_card_file',
            'required' => true,
            'type' => 'file'
        ],
    ],

    // Order Form field mappings (if needed)
    'order_form_field_names' => [
        // Add order form specific fields here when needed
    ],
];
