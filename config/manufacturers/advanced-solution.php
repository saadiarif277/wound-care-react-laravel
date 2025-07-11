<?php

return [
    'id' => 11,
    'name' => 'ADVANCED SOLUTION',
    'signature_required' => true,
    'has_order_form' => true,
    'duration_requirement' => 'greater_than_4_weeks',
    'docuseal_field_names' => [
        // Patient Information - Using EXACT field names from template
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'patient_phone' => 'Patient Phone',
        'patient_address' => 'Patient Address',
        
        // Insurance Information
        'insurance_name' => 'Primary Insurance',
        'secondary_insurance_name' => 'Secondary Insurance',
        'insurance_policy_number' => 'Policy Number',
        'secondary_policy_number' => 'Policy Number 2nd',
        'subscriber_name' => 'Primary Subscriber Name',
        'subscriber_name_secondary' => 'Subscriber Name 2nd',
        'subscriber_dob' => 'Subscriber DOB',
        'subscriber_dob_secondary' => 'Subscriber DOB 2nd',
        'insurance_phone' => 'Primary Insurance Phone Number',
        'secondary_insurance_phone' => 'Secondary Insurance Phone Number',
        
        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_npi' => 'Facility NPI',
        'facility_address' => 'Facility Address',
        'facility_phone' => 'Facility Phone Number',
        'facility_fax' => 'Facility Fax Number',
        'facility_tin' => 'Facility TIN',
        'facility_ptan' => 'Facility PTAN',
        'facility_contact' => 'Factility Contact Name', // Note: typo in template
        'facility_mac' => 'MAC',
        
        // Provider Information
        'physician_name' => 'Physician Name',
        'physician_npi' => 'Physician NPI',
        'physician_address' => 'Physician Address',
        'physician_phone' => 'Physician Phone',
        'physician_fax' => 'Physician Fax',
        'physician_tin' => 'Physician TIN',
        
        // Clinical Information
        'wound_size' => 'Wound Size',
        'diagnosis_code' => 'ICD-1o Diagnosis Code(s)', // Note: typo in template
        'procedure_date' => 'Date of Procedure',
        'application_cpt' => 'Application CPT9S)', // Note: typo in template
        'cpt_code' => 'CPT Code',
        
        // Place of Service (checkboxes)
        'pos_office' => 'Office',
        'pos_outpatient' => 'Outpatient Hospital',
        'pos_asc' => 'Ambulatory Surgical Center',
        'pos_other' => 'Other',
        'pos_other_text' => 'POS Other',
        
        // Wound Type (checkboxes)
        'wound_diabetic' => 'Diabetic Foot Ulcer',
        'wound_venous' => 'Venous Leg Ulcer',
        'wound_pressure' => 'Pressure Ulcer',
        'wound_traumatic_burns' => 'Traumatic Burns',
        'wound_radiation_burns' => 'Radiation Burns',
        'wound_necrotizing' => 'Necrotizing Facilitis', // Note: typo in template
        'wound_dehisced' => 'Dehisced Surgical Wound',
        'wound_other' => 'Other Wound',
        'wound_other_type' => 'Type of Wound Other',
        
        // Products (checkboxes)
        'product_completeaa' => 'CompleteAA',
        'product_membrane_wrap_hydro' => 'Membrane Wrap Hydro',
        'product_membrane_wrap' => 'Membrane Wrap',
        'product_woundplus' => 'WoundPlus',
        'product_completeft' => 'CompleteFT',
        'product_other' => 'Other Product',
        'product_other_name' => 'Product Other',
        
        // Yes/No Questions
        'contact_patient_yes' => 'Ok to Contact Patient Yes',
        'contact_patient_no' => 'OK to Contact Patient No',
        'provider_network_yes' => 'Does Provider Participate with Network Yes',
        'provider_network_no' => 'Does Provider Participate with Network No',
        'provider_network_yes_2nd' => 'Does Provider Participate with Network Yes 2nd',
        'provider_network_no_2nd' => 'Does Provider Participate with Network No 2nd',
        'network_not_sure' => 'In network Not Sure',
        'network_not_sure_2nd' => 'In network Not Sure 2nd',
        'patient_snf_yes' => 'Patient in SNF Yes',
        'patient_snf_no' => 'Patient in SNF No',
        'patient_global_yes' => 'Patient Under Global Yes',
        'patient_global_no' => 'Patient Under Global No',
        
        // Insurance Type (checkboxes)
        'primary_hmo' => 'Primary Insurance HMO',
        'primary_ppo' => 'Primary Insurance PPO',
        'primary_other' => 'Primary Insurance Other',
        'primary_other_type' => 'Type of Plan Other',
        'secondary_hmo' => 'Secondary Insurance HMO',
        'secondary_ppo' => 'Secondary Insurance PPO',
        'secondary_other' => 'Secondary Insurance Other',
        'secondary_other_type' => 'Type of Plan Other 2nd',
        
        // Other Fields
        'sales_rep' => 'Sales Rep',
        'prior_auth' => 'Prior Auth',
        'specialty_site' => 'Specialty Site Name',
        'insurance_card' => 'Insurance Card Upload',
        'physician_signature' => 'Physician or Authorized Signature',
        'date_signed' => 'Date Signed'
    ],
    'fields' => [
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_phone' => [
            'source' => 'patient_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'patient_address' => [
            'source' => 'computed',
            'computation' => 'patient_address_line1 + ", " + patient_city + ", " + patient_state + " " + patient_zip',
            'required' => true,
            'type' => 'string'
        ],
        'insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_insurance_name' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'insurance_policy_number' => [
            'source' => 'primary_member_id',
            'required' => true,
            'type' => 'string'
        ],
        'subscriber_name' => [
            'source' => 'primary_subscriber_name',
            'required' => false,
            'type' => 'string',
            'default' => 'computed:patient_name'
        ],
        'subscriber_dob' => [
            'source' => 'primary_subscriber_dob',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date',
            'default' => 'computed:patient_dob'
        ],
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'facility_address' => [
            'source' => 'computed',
            'computation' => 'facility_address_line1 + ", " + facility_city + ", " + facility_state + " " + facility_zip',
            'required' => true,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'physician_name' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'provider_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'physician_phone' => [
            'source' => 'provider_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + "x" + wound_size_width + "x" + wound_size_depth + " cm"',
            'required' => true,
            'type' => 'string'
        ],
        'diagnosis_code' => [
            'source' => 'primary_diagnosis_code',
            'required' => true,
            'type' => 'string'
        ],
        'procedure_date' => [
            'source' => 'procedure_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        // Handle checkboxes for wound type
        'wound_diabetic' => [
            'source' => 'computed',
            'computation' => 'wound_type == "Diabetic Foot Ulcer"',
            'type' => 'boolean'
        ],
        'wound_venous' => [
            'source' => 'computed',
            'computation' => 'wound_type == "Venous Leg Ulcer"',
            'type' => 'boolean'
        ],
        'wound_pressure' => [
            'source' => 'computed',
            'computation' => 'wound_type == "Pressure Ulcer"',
            'type' => 'boolean'
        ],
        // Handle checkboxes for place of service
        'pos_office' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "11"',
            'type' => 'boolean'
        ],
        'pos_outpatient' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "22"',
            'type' => 'boolean'
        ],
        'pos_asc' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "24"',
            'type' => 'boolean'
        ]
    ]
];