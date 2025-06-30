<?php

return [
    'id' => 11,
    'name' => 'ADVANCED SOLUTION',
    'signature_required' => true,
    'has_order_form' => true,
    'docuseal_template_id' => '1199885',
    'docuseal_field_names' => [
        // Sales Information
        'sales_rep' => 'Sales Rep',
        'date_signed' => 'Date Signed',
        
        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_address' => 'Facility Address',
        'facility_contact_name' => 'Factility Contact Name', // Note: typo in original
        'facility_phone' => 'Facility Phone Number',
        'facility_fax' => 'Facility Fax Number',
        'facility_npi' => 'Facility NPI',
        'facility_tin' => 'Facility TIN',
        'facility_ptan' => 'Facility PTAN',
        'mac' => 'MAC',
        
        // Place of Service checkboxes
        'pos_office' => 'Office',
        'pos_outpatient_hospital' => 'Outpatient Hospital',
        'pos_ambulatory_surgical_center' => 'Ambulatory Surgical Center',
        'pos_other' => 'Other',
        'pos_other_text' => 'POS Other',
        
        // Physician Information
        'physician_name' => 'Physician Name',
        'physician_address' => 'Physician Address',
        'physician_phone' => 'Physician Phone',
        'physician_fax' => 'Physician Fax',
        'physician_npi' => 'Physician NPI',
        'physician_tin' => 'Physician TIN',
        
        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_address' => 'Patient Address',
        'patient_dob' => 'Patient DOB',
        'patient_phone' => 'Patient Phone',
        'contact_patient_yes' => 'Ok to Contact Patient Yes',
        'contact_patient_no' => 'OK to Contact Patient No',
        
        // Primary Insurance
        'primary_insurance' => 'Primary Insurance',
        'primary_subscriber_name' => 'Primary Subscriber Name',
        'primary_policy_number' => 'Policy Number',
        'primary_subscriber_dob' => 'Subscriber DOB',
        'primary_insurance_phone' => 'Primary Insurance Phone Number',
        'primary_hmo' => 'Primary Insurance HMO',
        'primary_ppo' => 'Primary Insurance PPO',
        'primary_other' => 'Primary Insurance Other',
        'primary_type_other' => 'Type of Plan Other',
        'primary_in_network_yes' => 'Does Provider Participate with Network Yes',
        'primary_in_network_no' => 'Does Provider Participate with Network No',
        'primary_in_network_not_sure' => 'In network Not Sure',
        
        // Secondary Insurance
        'secondary_insurance' => 'Secondary Insurance',
        'secondary_subscriber_name' => 'Subscriber Name 2nd',
        'secondary_policy_number' => 'Policy Number 2nd',
        'secondary_subscriber_dob' => 'Subscriber DOB 2nd',
        'secondary_insurance_phone' => 'Secondary Insurance Phone Number',
        'secondary_hmo' => 'Secondary Insurance HMO',
        'secondary_ppo' => 'Secondary Insurance PPO',
        'secondary_other' => 'Secondary Insurance Other',
        'secondary_type_other' => 'Type of Plan Other 2nd',
        'secondary_in_network_yes' => 'Does Provider Participate with Network Yes 2nd',
        'secondary_in_network_no' => 'Does Provider Participate with Network No 2nd',
        'secondary_in_network_not_sure' => 'In network Not Sure 2nd',
        
        // Wound Information
        'wound_diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
        'wound_venous_leg_ulcer' => 'Venous Leg Ulcer',
        'wound_pressure_ulcer' => 'Pressure Ulcer',
        'wound_traumatic_burns' => 'Traumatic Burns',
        'wound_radiation_burns' => 'Radiation Burns',
        'wound_necrotizing_fasciitis' => 'Necrotizing Facilitis', // Note: typo in original
        'wound_dehisced_surgical' => 'Dehisced Surigcal Wound', // Note: typo in original
        'wound_other' => 'Other Wound',
        'wound_type_other' => 'Type of Wound Other',
        'wound_size' => 'Wound Size',
        
        // Product Information
        'product_completeaa' => 'CompleteAA',
        'product_membrane_wrap_hydro' => 'Membrane Wrap Hydro',
        'product_membrane_wrap' => 'Membrane Wrap',
        'product_woundplus' => 'WoundPlus',
        'product_completeft' => 'CompleteFT',
        'product_other' => 'Other Product',
        'product_other_text' => 'Product Other',
        
        // Clinical Information
        'application_cpt' => 'Application CPT9S)', // Note: typo in original
        'procedure_date' => 'Date of Procedure',
        'icd10_codes' => 'ICD-1o Diagnosis Code(s)', // Note: typo in original
        'cpt_code' => 'CPT Code',
        'prior_auth' => 'Prior Auth',
        
        // SNF/Global Period Status
        'patient_snf_yes' => 'Patient in SNF Yes',
        'patient_snf_no' => 'Patient in SNF No',
        'patient_global_yes' => 'Patient Under Global Yes',
        'patient_global_no' => 'Patient Under Global No',
        
        // Specialty Site
        'specialty_site_name' => 'Specialty Site Name',
        
        // Contact Info
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone'
    ],
    'fields' => [
        // Contact Information
        'name' => [
            'source' => 'contact_name || office_contact_name || sales_rep_name',
            'required' => false,
            'type' => 'string'
        ],
        'email' => [
            'source' => 'contact_email || office_contact_email || sales_rep_email',
            'required' => false,
            'type' => 'email'
        ],
        'phone' => [
            'source' => 'contact_phone || office_contact_phone || sales_rep_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Sales Information
        'sales_rep' => [
            'source' => 'sales_rep_name || sales_rep || distributor_name',
            'required' => false,
            'type' => 'string'
        ],
        'date_signed' => [
            'source' => 'signature_date || date_signed || created_at',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date'
        ],
        
        // Facility Information
        'facility_name' => [
            'source' => 'facility_name || practice_name || location_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'facility_address || facility.address || practice_address',
            'required' => true,
            'type' => 'string'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name || facility.contact_name || office_manager',
            'required' => false,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone || facility.phone || practice_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'facility_fax' => [
            'source' => 'facility_fax || facility.fax || practice_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_npi' => [
            'source' => 'facility_npi || practice_npi || location_npi',
            'required' => true,
            'type' => 'string'
        ],
        'facility_tin' => [
            'source' => 'facility_tax_id || facility_tin || practice_tax_id || tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan || practice_ptan || ptan',
            'required' => false,
            'type' => 'string'
        ],
        'mac' => [
            'source' => 'medicare_admin_contractor || mac || medicare_contractor',
            'required' => false,
            'type' => 'string'
        ],
        
        // Place of Service checkboxes
        'pos_office' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "11" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_outpatient_hospital' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "19" || place_of_service == "22" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_ambulatory_surgical_center' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "24" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_other' => [
            'source' => 'computed',
            'computation' => 'place_of_service != "11" && place_of_service != "19" && place_of_service != "22" && place_of_service != "24" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_other_text' => [
            'source' => 'place_of_service_other || pos_other_description',
            'required' => false,
            'type' => 'string'
        ],
        
        // Physician Information
        'physician_name' => [
            'source' => 'provider_name || physician_name || doctor_name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_address' => [
            'source' => 'provider_address || physician_address || doctor_address',
            'required' => false,
            'type' => 'string'
        ],
        'physician_phone' => [
            'source' => 'provider_phone || physician_phone || doctor_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'physician_fax' => [
            'source' => 'provider_fax || physician_fax || doctor_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'physician_npi' => [
            'source' => 'provider_npi || physician_npi || doctor_npi',
            'required' => true,
            'type' => 'string'
        ],
        'physician_tin' => [
            'source' => 'provider_tin || physician_tin || doctor_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        
        // Patient Information
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name || patient_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_address' => [
            'source' => 'patient_address || patient.address',
            'required' => false,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob || patient_date_of_birth',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_phone' => [
            'source' => 'patient_phone || patient.phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'contact_patient_yes' => [
            'source' => 'computed',
            'computation' => 'ok_to_contact_patient == true || ok_to_contact == "yes" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'contact_patient_no' => [
            'source' => 'computed',
            'computation' => 'ok_to_contact_patient == false || ok_to_contact == "no" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Primary Insurance
        'primary_insurance' => [
            'source' => 'primary_insurance_name || insurance_name || primary_insurance',
            'required' => true,
            'type' => 'string'
        ],
        'primary_subscriber_name' => [
            'source' => 'primary_subscriber_name || primary_insurance_subscriber || patient_name',
            'required' => false,
            'type' => 'string'
        ],
        'primary_policy_number' => [
            'source' => 'primary_member_id || primary_policy_number || insurance_member_id',
            'required' => true,
            'type' => 'string'
        ],
        'primary_subscriber_dob' => [
            'source' => 'primary_subscriber_dob || patient_dob',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date'
        ],
        'primary_insurance_phone' => [
            'source' => 'primary_insurance_phone || insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'primary_hmo' => [
            'source' => 'computed',
            'computation' => 'primary_insurance_type == "HMO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'primary_ppo' => [
            'source' => 'computed',
            'computation' => 'primary_insurance_type == "PPO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'primary_other' => [
            'source' => 'computed',
            'computation' => 'primary_insurance_type != "HMO" && primary_insurance_type != "PPO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'primary_type_other' => [
            'source' => 'primary_insurance_type_other || primary_plan_type_other',
            'required' => false,
            'type' => 'string'
        ],
        'primary_in_network_yes' => [
            'source' => 'computed',
            'computation' => 'primary_in_network == "yes" || primary_in_network == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'primary_in_network_no' => [
            'source' => 'computed',
            'computation' => 'primary_in_network == "no" || primary_in_network == false ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'primary_in_network_not_sure' => [
            'source' => 'computed',
            'computation' => 'primary_in_network == "not_sure" || primary_in_network == null ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Secondary Insurance
        'secondary_insurance' => [
            'source' => 'secondary_insurance_name || secondary_insurance',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_subscriber_name' => [
            'source' => 'secondary_subscriber_name || secondary_insurance_subscriber',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_member_id || secondary_policy_number',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_subscriber_dob' => [
            'source' => 'secondary_subscriber_dob',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date'
        ],
        'secondary_insurance_phone' => [
            'source' => 'secondary_insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'secondary_hmo' => [
            'source' => 'computed',
            'computation' => 'secondary_insurance_type == "HMO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'secondary_ppo' => [
            'source' => 'computed',
            'computation' => 'secondary_insurance_type == "PPO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'secondary_other' => [
            'source' => 'computed',
            'computation' => 'secondary_insurance_type != "HMO" && secondary_insurance_type != "PPO" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'secondary_type_other' => [
            'source' => 'secondary_insurance_type_other || secondary_plan_type_other',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_in_network_yes' => [
            'source' => 'computed',
            'computation' => 'secondary_in_network == "yes" || secondary_in_network == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'secondary_in_network_no' => [
            'source' => 'computed',
            'computation' => 'secondary_in_network == "no" || secondary_in_network == false ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'secondary_in_network_not_sure' => [
            'source' => 'computed',
            'computation' => 'secondary_in_network == "not_sure" || secondary_in_network == null ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Wound Information checkboxes
        'wound_diabetic_foot_ulcer' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("diabetic_foot_ulcer") || wound_type == "DFU" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_venous_leg_ulcer' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("venous_leg_ulcer") || wound_type == "VLU" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_pressure_ulcer' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("pressure_ulcer") || wound_type == "PU" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_traumatic_burns' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("traumatic_burns") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_radiation_burns' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("radiation_burns") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_necrotizing_fasciitis' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("necrotizing_fasciitis") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_dehisced_surgical' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("dehisced_surgical") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_other' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("other") || wound_type_other ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_type_other' => [
            'source' => 'wound_type_other || wound_other_description',
            'required' => false,
            'type' => 'string'
        ],
        'wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + "x" + wound_size_width + " cm" || wound_size',
            'required' => true,
            'type' => 'string'
        ],
        
        // Product Information checkboxes
        'product_completeaa' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("CompleteAA") || product_name == "CompleteAA" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_membrane_wrap_hydro' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Membrane Wrap Hydro") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_membrane_wrap' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Membrane Wrap") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_woundplus' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("WoundPlus") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_completeft' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("CompleteFT") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_other' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("other") || product_other ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'product_other_text' => [
            'source' => 'product_other || product_other_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Clinical Information
        'application_cpt' => [
            'source' => 'application_cpt_codes || cpt_codes || primary_cpt',
            'required' => false,
            'type' => 'string'
        ],
        'procedure_date' => [
            'source' => 'expected_service_date || procedure_date || service_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'icd10_codes' => [
            'source' => 'computed',
            'computation' => 'icd10_codes.join(", ") || diagnosis_codes',
            'required' => true,
            'type' => 'string'
        ],
        'cpt_code' => [
            'source' => 'primary_cpt_code || cpt_code || "15271"',
            'required' => true,
            'type' => 'string'
        ],
        'prior_auth' => [
            'source' => 'prior_authorization_number || auth_number || prior_auth',
            'required' => false,
            'type' => 'string'
        ],
        
        // SNF/Global Period Status
        'patient_snf_yes' => [
            'source' => 'computed',
            'computation' => 'patient_in_snf == true || place_of_service == "31" || place_of_service == "32" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_snf_no' => [
            'source' => 'computed',
            'computation' => 'patient_in_snf == false || (place_of_service != "31" && place_of_service != "32") ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_global_yes' => [
            'source' => 'computed',
            'computation' => 'global_period_status == true || in_global_period == "yes" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_global_no' => [
            'source' => 'computed',
            'computation' => 'global_period_status == false || in_global_period == "no" ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Specialty Site
        'specialty_site_name' => [
            'source' => 'specialty_site || treatment_site || specialty_location',
            'required' => false,
            'type' => 'string'
        ]
    ]
];