<?php

return [
    'id' => 3,
    'name' => 'BIOWOUND SOLUTIONS',
    'signature_required' => true,
    'has_order_form' => true,
    'docuseal_template_id' => '1254774',
    'order_form_template_id' => '1299495',
    'docuseal_field_names' => [
        // Contact Information
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'territory' => 'Territory',
        'sales_rep' => 'Sales Rep',
        'rep_email' => 'Rep Email',
        
        // Request Type checkboxes
        'new_request' => 'New Request',
        're_verification' => 'Re-Verification',
        'additional_applications' => 'Additional Applications',
        'new_insurance' => 'New Insurance',
        
        // Physician Information
        'physician_name' => 'Physician Name',
        'physician_specialty' => 'Physician Specialty',
        'provider_npi' => 'Provider NPI',
        'provider_tax_id' => 'Provider Tax ID',
        'provider_ptan' => 'Provider PTAN',
        'provider_medicaid' => 'Provider Medicaid #',
        'provider_phone' => 'Provider Phone #',
        'provider_fax' => 'Provider Fax',
        
        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_npi' => 'FacilityÂ NPI', // Note: special character in original
        'facility_tax_id' => 'Facility Tax ID',
        'facility_address' => 'Facility Address',
        'facility_ptan' => 'Facility PTAN',
        'facility_medicaid' => 'Facility Medicaid #',
        'facility_phone' => 'Facility Phone #',
        'facility_fax' => 'Facility Fax',
        'city_state_zip' => 'City State Zip',
        
        // Contact Information
        'contact_name' => 'Contact Name',
        'contact_email' => 'Contact Email',
        
        // Place of Service checkboxes
        'pos_11' => 'POS 11',
        'pos_21' => 'POS 21',
        'pos_24' => 'POS 24',
        'pos_22' => 'POS 22',
        'pos_32' => 'POS 32',
        'pos_13' => 'POS 13',
        'critical_access_hospital' => 'Critical Access Hospital',
        'pos_12' => 'POS 12',
        'other_pos' => 'Other POS',
        
        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient Date of Birth',
        'patient_address' => 'Patient Address',
        'patient_snf_yes' => 'Is the patient currently in a Skilled Nursing Facility: Yes',
        'patient_snf_no' => 'Is the patient currently in a Skilled Nursing Facility: No',
        'snf_days' => 'Number of Days in SNF',
        'patient_global_yes' => 'Is patient in a surgical global period: Yes',
        'patient_global_no' => 'Is patient in a surgical global period: No',
        
        // Insurance Information - Primary
        'primary_name' => 'Primary Name',
        'primary_policy' => 'Primary Policy #',
        'primary_phone' => 'Primary Phone #',
        
        // Insurance Information - Secondary
        'secondary_name' => 'Secondary Name',
        'secondary_policy' => 'Secondary Policy #',
        'secondary_phone' => 'Secondary Phone #',
        
        // Prior Authorization
        'prior_auth_yes' => 'Assistance with prior-auth: Yes',
        'prior_auth_no' => 'Assistance with prior-auth: No',
        
        // Product checkboxes (Q-codes)
        'q4161' => 'Q4161',
        'q4205' => 'Q4205',
        'q4290' => 'Q4290',
        'q4238' => 'Q4238',
        'q4239' => 'Q4239',
        'q4266' => 'Q4266',
        'q4267' => 'Q4267',
        'q4265' => 'Q4265',
        
        // Wound Type checkboxes
        'wound_dfu' => 'DFU',
        'wound_vlu' => 'VLU',
        'wound_chronic_ulcer' => 'Chronic Ulcer',
        'wound_dehisced_surgical' => 'Dehisced Surgical Wound',
        'wound_mohs_surgical' => 'Mohs Surgical Wound',
        'wound_other' => 'Other Wound Type',
        
        // Wound Information
        'primary_icd10' => 'Primary ICD-10',
        'secondary_icd10' => 'Secondary ICD-10',
        'previously_used_therapies' => 'Previously Used Therapies',
        'wound_location' => 'Location of Wound',
        'wound_duration' => 'Wound Duration',
        'co_morbidities' => 'Co-Morbities', // Note: typo in original
        'post_debridement_size' => 'Post Debridement Total Size',
        
        // Date
        'date' => 'Date',
        
        // Signature
        'authorized_signature' => 'Authorized Signature (optional)'
    ],
    'order_form_field_names' => [
        // Order Information
        'order_date' => 'DATE',
        'delivery_date' => 'REQUESTED DELIVERY DATE',
        'po_number' => 'PO#',
        
        // Shipping Information  
        'ship_to_name' => 'SHIP TO',
        'ship_to_address' => 'SHIP TO', // Address section
        'ship_to_city' => 'SHIP TO', 
        'ship_to_state' => 'SHIP TO',
        'ship_to_zip' => 'SHIP TO',
        'ship_to_phone' => 'SHIP TO',
        
        // Billing Information
        'bill_to_name' => 'BILL TO',
        'bill_to_address' => 'BILL TO', // Address section
        'bill_to_city' => 'BILL TO',
        'bill_to_state' => 'BILL TO', 
        'bill_to_zip' => 'BILL TO',
        'payment_terms' => 'NET TERMS',
        
        // Product Information
        'product_description_1' => 'DESCRIPTION',
        'product_quantity_1' => 'QUANTITY',
        'product_unit_price_1' => 'UNIT PRICE',
        'product_total_1' => 'TOTAL',
        
        // Multiple product rows (assuming up to 5 products)
        'product_description_2' => 'DESCRIPTION_2',
        'product_quantity_2' => 'QUANTITY_2', 
        'product_unit_price_2' => 'UNIT PRICE_2',
        'product_total_2' => 'TOTAL_2',
        
        'product_description_3' => 'DESCRIPTION_3',
        'product_quantity_3' => 'QUANTITY_3',
        'product_unit_price_3' => 'UNIT PRICE_3', 
        'product_total_3' => 'TOTAL_3',
        
        'product_description_4' => 'DESCRIPTION_4',
        'product_quantity_4' => 'QUANTITY_4',
        'product_unit_price_4' => 'UNIT PRICE_4',
        'product_total_4' => 'TOTAL_4',
        
        'product_description_5' => 'DESCRIPTION_5',
        'product_quantity_5' => 'QUANTITY_5',
        'product_unit_price_5' => 'UNIT PRICE_5',
        'product_total_5' => 'TOTAL_5',
        
        // Order Details
        'sales_person' => 'SALESPERSON',
        'order_total' => 'ORDER TOTAL',
        'comments' => 'COMMENTS OR SPECIAL INSTRUCTIONS',
        
        // Contact Information
        'contact_name' => 'CONTACT NAME',
        'contact_phone' => 'CONTACT PHONE',
        'contact_email' => 'CONTACT EMAIL',
        
        // Delivery Information
        'delivery_instructions' => 'DELIVERY INSTRUCTIONS',
        'signature_required' => 'SIGNATURE REQUIRED',
    ],
    'fields' => [
        // Contact Information - Enhanced
        'name' => [
            'source' => 'contact_name || sales_contact_name || representative_name',
            'required' => true,
            'type' => 'string'
        ],
        'email' => [
            'source' => 'contact_email || sales_contact_email || representative_email',
            'required' => true,
            'type' => 'email'
        ],
        'phone' => [
            'source' => 'contact_phone || sales_contact_phone || representative_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'territory' => [
            'source' => 'territory || sales_territory || region || district',
            'required' => false,
            'type' => 'string'
        ],
        'sales_rep' => [
            'source' => 'sales_rep_name || sales_rep || distributor_name || representative',
            'required' => true,
            'type' => 'string'
        ],
        'rep_email' => [
            'source' => 'sales_rep_email || rep_email || distributor_email || representative_email',
            'required' => true,
            'type' => 'email'
        ],
        
        // Request Type checkboxes - Enhanced
        'new_request' => [
            'source' => 'computed',
            'computation' => 'request_type == "new" || request_types.includes("new_request") || is_new_request == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        're_verification' => [
            'source' => 'computed',
            'computation' => 'request_type == "reverification" || request_types.includes("reverification") || is_reverification == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'additional_applications' => [
            'source' => 'computed',
            'computation' => 'request_type == "additional" || request_types.includes("additional_applications") || has_additional_applications == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'new_insurance' => [
            'source' => 'computed',
            'computation' => 'request_type == "new_insurance" || request_types.includes("new_insurance") || has_new_insurance == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Physician Information - Enhanced with validation
        'physician_name' => [
            'source' => 'provider_name || physician_name || doctor_name || attending_physician',
            'required' => true,
            'type' => 'string'
        ],
        'physician_specialty' => [
            'source' => 'provider_specialty || physician_specialty || specialty || medical_specialty',
            'required' => true,
            'type' => 'string'
        ],
        'provider_npi' => [
            'source' => 'provider_npi || physician_npi || npi || doctor_npi',
            'required' => true,
            'type' => 'string',
            'validation' => 'npi'
        ],
        'provider_tax_id' => [
            'source' => 'provider_tax_id || physician_tax_id || provider_tin || tax_id',
            'required' => false,
            'type' => 'string',
            // 'transform' => 'tax_id'
        ],
        'provider_ptan' => [
            'source' => 'provider_ptan || physician_ptan || ptan || medicare_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'provider_medicaid' => [
            'source' => 'provider_medicaid_number || physician_medicaid || medicaid_number || medicaid_id',
            'required' => false,
            'type' => 'string'
        ],
        'provider_phone' => [
            'source' => 'provider_phone || physician_phone || doctor_phone || office_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'provider_fax' => [
            'source' => 'provider_fax || physician_fax || doctor_fax || office_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Facility Information - Enhanced
        'facility_name' => [
            'source' => 'facility_name || practice_name || location_name || hospital_name || clinic_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_npi' => [
            'source' => 'facility_npi || practice_npi || location_npi || organizational_npi',
            'required' => false,
            'type' => 'string',
            'validation' => 'npi'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id || facility_tin || practice_tax_id || organization_tax_id',
            'required' => false,
            'type' => 'string',
            // 'transform' => 'tax_id'
        ],
        'facility_address' => [
            'source' => 'facility_address || practice_address || location_address || facility_street',
            'required' => true,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan || practice_ptan || location_ptan || organizational_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'facility_medicaid' => [
            'source' => 'facility_medicaid_number || practice_medicaid || location_medicaid || facility_medicaid_id',
            'required' => false,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone || practice_phone || location_phone || main_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'facility_fax' => [
            'source' => 'facility_fax || practice_fax || location_fax || main_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'city_state_zip' => [
            'source' => 'computed',
            'computation' => 'facility_city + ", " + facility_state + " " + facility_zip || city_state_zip || facility_csz',
            'required' => true,
            'type' => 'string'
        ],
        
        // Contact Information - Enhanced
        'contact_name' => [
            'source' => 'office_contact_name || facility_contact || contact_name || office_manager || contact_person',
            'required' => true,
            'type' => 'string'
        ],
        'contact_email' => [
            'source' => 'office_contact_email || facility_email || contact_email || office_email',
            'required' => true,
            'type' => 'email'
        ],
        
        // Place of Service checkboxes - Enhanced
        'pos_11' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "11" || pos_codes.includes("11") || physician_office == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_21' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "21" || pos_codes.includes("21") || hospital_inpatient == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_24' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "24" || pos_codes.includes("24") || ambulatory_surgical_center == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_22' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "22" || pos_codes.includes("22") || hospital_outpatient == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_32' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "32" || pos_codes.includes("32") || nursing_facility == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_13' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "13" || pos_codes.includes("13") || assisted_living == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'critical_access_hospital' => [
            'source' => 'computed',
            'computation' => 'is_critical_access_hospital == true || facility_type == "CAH" || critical_access == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_12' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "12" || pos_codes.includes("12") || home == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'other_pos' => [
            'source' => 'place_of_service_other || pos_other_description || other_place_of_service',
            'required' => false,
            'type' => 'string'
        ],
        
        // Patient Information - Enhanced
        'patient_first_name' => [
            'source' => 'patient_first_name || patient.first_name || patient_firstName',
            'required' => false,
            'type' => 'string'
        ],
        'patient_last_name' => [
            'source' => 'patient_last_name || patient.last_name || patient_lastName',
            'required' => false,
            'type' => 'string'
        ],
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name || patient_name || patient_full_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob || patient_date_of_birth || patient_birthdate',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_address' => [
            'source' => 'patient_address || patient.address || patient_street || patient_street_address',
            'required' => true,
            'type' => 'string'
        ],
        'patient_snf_yes' => [
            'source' => 'computed',
            'computation' => 'patient_in_snf == true || snf_status == "yes" || in_skilled_nursing == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_snf_no' => [
            'source' => 'computed',
            'computation' => 'patient_in_snf == false || snf_status == "no" || in_skilled_nursing == false ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'snf_days' => [
            'source' => 'snf_days || days_in_snf || snf_duration || skilled_nursing_days',
            'required' => false,
            'type' => 'number'
        ],
        'patient_global_yes' => [
            'source' => 'computed',
            'computation' => 'global_period_status == true || in_global_period == "yes" || surgical_global == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'patient_global_no' => [
            'source' => 'computed',
            'computation' => 'global_period_status == false || in_global_period == "no" || surgical_global == false ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Insurance Information - Primary (Enhanced)
        'primary_name' => [
            'source' => 'primary_insurance_name || primary_payer || insurance_name || primary_carrier',
            'required' => true,
            'type' => 'string'
        ],
        'primary_policy' => [
            'source' => 'primary_member_id || primary_policy_number || insurance_id || primary_id_number',
            'required' => true,
            'type' => 'string'
        ],
        'primary_phone' => [
            'source' => 'primary_insurance_phone || primary_payer_phone || insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Insurance Information - Secondary (Enhanced)
        'secondary_name' => [
            'source' => 'secondary_insurance_name || secondary_payer || secondary_carrier',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_policy' => [
            'source' => 'secondary_member_id || secondary_policy_number || secondary_id_number',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_phone' => [
            'source' => 'secondary_insurance_phone || secondary_payer_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Prior Authorization - Enhanced
        'prior_auth_yes' => [
            'source' => 'computed',
            'computation' => 'needs_prior_auth_assistance == true || prior_auth_help == "yes" || authorization_assistance == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'prior_auth_no' => [
            'source' => 'computed',
            'computation' => 'needs_prior_auth_assistance == false || prior_auth_help == "no" || authorization_assistance == false ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Product checkboxes (Q-codes) - Enhanced with product names
        'q4161' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4161") || product_codes.includes("Q4161") || bio_connekt == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4205' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4205") || product_codes.includes("Q4205") || membrane_wrap == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4290' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4290") || product_codes.includes("Q4290") || membrane_wrap_hydro == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4238' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4238") || product_codes.includes("Q4238") || derm_maxx == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4239' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4239") || product_codes.includes("Q4239") || amnio_maxx == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4266' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4266") || product_codes.includes("Q4266") || neostim_sl == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4267' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4267") || product_codes.includes("Q4267") || neostim_dl == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'q4265' => [
            'source' => 'computed',
            'computation' => 'selected_products.includes("Q4265") || product_codes.includes("Q4265") || neostim_tl == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Wound Type checkboxes - Enhanced
        'wound_dfu' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("DFU") || wound_type == "diabetic_foot_ulcer" || diabetic_foot_ulcer == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_vlu' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("VLU") || wound_type == "venous_leg_ulcer" || venous_leg_ulcer == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_chronic_ulcer' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("chronic_ulcer") || chronic_ulcer == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_dehisced_surgical' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("dehisced_surgical") || dehisced_surgical_wound == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_mohs_surgical' => [
            'source' => 'computed',
            'computation' => 'wound_types.includes("mohs_surgical") || mohs_surgical_wound == true ? true : false',
            'required' => false,
            'type' => 'boolean'
        ],
        'wound_other' => [
            'source' => 'wound_type_other || other_wound_type || wound_other_description',
            'required' => false,
            'type' => 'string'
        ],
        
        // Wound Information - Enhanced Clinical Data
        'primary_icd10' => [
            'source' => 'primary_diagnosis_code || primary_icd10 || icd10_codes[0] || primary_diagnosis',
            'required' => true,
            'type' => 'string',
            'validation' => 'icd10'
        ],
        'secondary_icd10' => [
            'source' => 'secondary_diagnosis_code || secondary_icd10 || icd10_codes[1] || secondary_diagnosis',
            'required' => false,
            'type' => 'string',
            'validation' => 'icd10'
        ],
        'previously_used_therapies' => [
            'source' => 'previous_therapies || prior_treatments || therapies_used || previous_treatment_history',
            'required' => false,
            'type' => 'string'
        ],
        'wound_location' => [
            'source' => 'wound_location || wound_site || anatomical_location || wound_anatomical_site',
            'required' => true,
            'type' => 'string'
        ],
        'wound_duration' => [
            'source' => 'wound_duration || wound_age || time_since_onset || wound_chronicity',
            'required' => true,
            'type' => 'string'
        ],
        'co_morbidities' => [
            'source' => 'comorbidities || co_morbidities || patient_comorbidities || medical_conditions',
            'required' => false,
            'type' => 'string'
        ],
        'post_debridement_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + " x " + wound_size_width + " x " + wound_size_depth + " cm" || post_debridement_size || wound_size || total_wound_size',
            'required' => true,
            'type' => 'string'
        ],
        
        // Date - Enhanced
        'date' => [
            'source' => 'form_date || submission_date || created_at || request_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        
        // Signature
        'authorized_signature' => [
            'source' => 'signature || authorized_signature || electronic_signature',
            'required' => false,
            'type' => 'signature'
        ],
        // Enhanced product selection for order forms
        'selected_products' => [
            'source' => 'computed',
            'computation' => 'getSelectedProductsForOrder',
            'required' => true,
            'type' => 'array'
        ],
        
        // Order form specific fields
        'order_date' => [
            'source' => 'computed',
            'computation' => 'now()',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'delivery_date' => [
            'source' => 'expected_service_date || delivery_date || computed',
            'computation' => 'addDays(now(), 2)', // Default to 2 days from now
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'po_number' => [
            'source' => 'po_number || purchase_order || order_number',
            'required' => false,
            'type' => 'string'
        ],
        
        // Shipping Information (Order Form)
        'ship_to_name' => [
            'source' => 'facility_name || shipping_facility_name',
            'required' => true,
            'type' => 'string'
        ],
        'ship_to_address' => [
            'source' => 'computed',
            'computation' => 'facility_address || shipping_address_line1 + " " + shipping_address_line2',
            'required' => true,
            'type' => 'string'
        ],
        'ship_to_city' => [
            'source' => 'facility_city || shipping_city',
            'required' => true,
            'type' => 'string'
        ],
        'ship_to_state' => [
            'source' => 'facility_state || shipping_state',
            'required' => true,
            'type' => 'string'
        ],
        'ship_to_zip' => [
            'source' => 'facility_zip || shipping_zip',
            'required' => true,
            'type' => 'string'
        ],
        'ship_to_phone' => [
            'source' => 'facility_phone || shipping_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        
        // Billing Information (Order Form)
        'bill_to_name' => [
            'source' => 'billing_facility_name || facility_name',
            'required' => false,
            'type' => 'string'
        ],
        'bill_to_address' => [
            'source' => 'computed',
            'computation' => 'billing_address || facility_address',
            'required' => false,
            'type' => 'string'
        ],
        'payment_terms' => [
            'source' => 'payment_terms || "Net 30"',
            'required' => true,
            'type' => 'string'
        ],
        
        // Product Information (Order Form) - Amnio-maxx specific
        'product_description_1' => [
            'source' => 'computed',
            'computation' => 'getProductDescription(0)', // First product
            'required' => true,
            'type' => 'string'
        ],
        'product_quantity_1' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(0)',
            'required' => true,
            'type' => 'integer'
        ],
        'product_unit_price_1' => [
            'source' => 'computed',
            'computation' => 'getProductUnitPrice(0)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        'product_total_1' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(0) * getProductUnitPrice(0)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        
        // Additional product rows (2-5) for multiple products
        'product_description_2' => [
            'source' => 'computed',
            'computation' => 'getProductDescription(1)',
            'required' => false,
            'type' => 'string'
        ],
        'product_quantity_2' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(1)',
            'required' => false,
            'type' => 'integer'
        ],
        'product_unit_price_2' => [
            'source' => 'computed',
            'computation' => 'getProductUnitPrice(1)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        'product_total_2' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(1) * getProductUnitPrice(1)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        
        'product_description_3' => [
            'source' => 'computed',
            'computation' => 'getProductDescription(2)',
            'required' => false,
            'type' => 'string'
        ],
        'product_quantity_3' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(2)',
            'required' => false,
            'type' => 'integer'
        ],
        'product_unit_price_3' => [
            'source' => 'computed',
            'computation' => 'getProductUnitPrice(2)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        'product_total_3' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(2) * getProductUnitPrice(2)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        
        'product_description_4' => [
            'source' => 'computed',
            'computation' => 'getProductDescription(3)',
            'required' => false,
            'type' => 'string'
        ],
        'product_quantity_4' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(3)',
            'required' => false,
            'type' => 'integer'
        ],
        'product_unit_price_4' => [
            'source' => 'computed',
            'computation' => 'getProductUnitPrice(3)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        'product_total_4' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(3) * getProductUnitPrice(3)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        
        'product_description_5' => [
            'source' => 'computed',
            'computation' => 'getProductDescription(4)',
            'required' => false,
            'type' => 'string'
        ],
        'product_quantity_5' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(4)',
            'required' => false,
            'type' => 'integer'
        ],
        'product_unit_price_5' => [
            'source' => 'computed',
            'computation' => 'getProductUnitPrice(4)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        'product_total_5' => [
            'source' => 'computed',
            'computation' => 'getProductQuantity(4) * getProductUnitPrice(4)',
            // 'transform' => 'currency',
            'required' => false,
            'type' => 'number'
        ],
        
        // Order Details
        'sales_person' => [
            'source' => 'sales_rep_name || rep_name || "MSC Wound Care"',
            'required' => false,
            'type' => 'string'
        ],
        'order_total' => [
            'source' => 'computed',
            'computation' => 'calculateOrderTotal',
            // 'transform' => 'currency',
            'required' => true,
            'type' => 'number'
        ],
        'comments' => [
            'source' => 'order_notes || delivery_notes || special_instructions',
            'required' => false,
            'type' => 'string'
        ],
        
        // Contact Information (Order Form)
        'contact_name' => [
            'source' => 'facility_contact_name || contact_name',
            'required' => true,
            'type' => 'string'
        ],
        'contact_phone' => [
            'source' => 'facility_phone || contact_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'contact_email' => [
            'source' => 'facility_email || contact_email',
            'required' => true,
            'type' => 'email'
        ],
        
        // Delivery Information
        'delivery_instructions' => [
            'source' => 'delivery_notes || delivery_instructions || special_instructions',
            'required' => false,
            'type' => 'string'
        ],
        'signature_required' => [
            'source' => 'computed',
            'computation' => 'signature_required || true',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'boolean'
        ],
    ]
]; 