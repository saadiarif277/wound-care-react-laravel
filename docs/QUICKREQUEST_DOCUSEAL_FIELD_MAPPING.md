# QuickRequest to DocuSeal IVR Field Mapping Guide

## Overview
This document provides comprehensive field mapping between QuickRequest form data and DocuSeal IVR templates to achieve 90%+ pre-fill coverage.

## Field Mapping Structure

### 1. Patient Information
```javascript
{
  // Basic Demographics
  'patient_first_name': formData.patient_first_name,
  'patient_last_name': formData.patient_last_name,
  'patient_full_name': `${formData.patient_first_name} ${formData.patient_last_name}`,
  'patient_dob': formData.patient_dob,
  'patient_gender': formData.patient_gender,
  'patient_display_id': episode.patient_display_id,
  
  // Contact Information
  'patient_phone': formData.patient_phone,
  'patient_email': formData.patient_email,
  'patient_address_line1': formData.patient_address_line1,
  'patient_address_line2': formData.patient_address_line2,
  'patient_city': formData.patient_city,
  'patient_state': formData.patient_state,
  'patient_zip': formData.patient_zip,
  'patient_full_address': `${formData.patient_address_line1} ${formData.patient_address_line2}, ${formData.patient_city}, ${formData.patient_state} ${formData.patient_zip}`,
  
  // Caregiver Information
  'caregiver_name': formData.caregiver_name,
  'caregiver_relationship': formData.caregiver_relationship,
  'caregiver_phone': formData.caregiver_phone,
  'patient_is_subscriber': formData.patient_is_subscriber
}
```

### 2. Insurance Information
```javascript
{
  // Primary Insurance
  'primary_insurance_name': formData.primary_insurance_name,
  'primary_insurance_id': formData.primary_member_id,
  'primary_policy_number': formData.primary_member_id, // Alias
  'primary_plan_type': formData.primary_plan_type,
  'primary_payer_phone': formData.primary_payer_phone,
  'primary_network_status': formData.primary_network_status,
  
  // Secondary Insurance
  'has_secondary_insurance': formData.has_secondary_insurance,
  'secondary_insurance_name': formData.secondary_insurance_name,
  'secondary_insurance_id': formData.secondary_member_id,
  'secondary_plan_type': formData.secondary_plan_type,
  'secondary_payer_phone': formData.secondary_payer_phone,
  
  // Authorization
  'prior_auth_permission': formData.prior_auth_permission,
  'prior_auth_number': formData.prior_auth_number,
  'medicare_advantage': formData.medicare_advantage
}
```

### 3. Provider & Facility Information
```javascript
{
  // Provider Details
  'provider_name': provider.full_name,
  'provider_first_name': provider.first_name,
  'provider_last_name': provider.last_name,
  'provider_npi': provider.npi,
  'provider_credentials': provider.credentials,
  'provider_specialty': provider.specialty,
  'provider_phone': provider.phone,
  'provider_fax': provider.fax,
  'provider_email': provider.email,
  'provider_tax_id': provider.tax_id,
  'provider_ptan': provider.ptan,
  'provider_medicaid_number': provider.medicaid_number,
  
  // Facility Details
  'facility_name': facility.name,
  'facility_address_line1': facility.address_line1,
  'facility_address_line2': facility.address_line2,
  'facility_city': facility.city,
  'facility_state': facility.state,
  'facility_zip': facility.zip,
  'facility_full_address': facility.full_address,
  'facility_npi': facility.npi,
  'facility_tax_id': facility.tax_id,
  'facility_phone': facility.phone,
  'facility_fax': facility.fax,
  
  // Organization
  'organization_name': organization.name,
  'management_company': organization.name
}
```

### 4. Clinical Information
```javascript
{
  // Wound Details
  'wound_type': formData.wound_types.join(', '),
  'wound_types_list': formData.wound_types, // Array format
  'wound_other_specify': formData.wound_other_specify,
  'wound_location': formData.wound_location,
  'wound_location_details': formData.wound_location_details,
  
  // Wound Measurements
  'wound_length': formData.wound_size_length,
  'wound_width': formData.wound_size_width,
  'wound_depth': formData.wound_size_depth,
  'wound_size': `${formData.wound_size_length} x ${formData.wound_size_width} x ${formData.wound_size_depth} cm`,
  'wound_total_area': (formData.wound_size_length * formData.wound_size_width),
  'wound_duration': formData.wound_duration,
  'wound_onset_date': formData.wound_onset_date,
  
  // Treatment History
  'failed_conservative_treatment': formData.failed_conservative_treatment,
  'previous_treatments': formData.previous_treatments,
  'treatment_tried': formData.treatment_tried,
  'current_dressing': formData.current_dressing,
  
  // Diagnosis Codes
  'primary_diagnosis': formData.yellow_diagnosis_code,
  'secondary_diagnosis': formData.orange_diagnosis_code,
  'diagnosis_codes': `${formData.yellow_diagnosis_code}, ${formData.orange_diagnosis_code}`,
  'additional_diagnoses': formData.additional_diagnoses,
  'comorbidities': formData.comorbidities
}
```

### 5. Product Information
```javascript
{
  // Product Details
  'product_name': selectedProduct.name,
  'product_code': selectedProduct.hcpcs_code,
  'product_q_code': selectedProduct.q_code,
  'product_manufacturer': selectedProduct.manufacturer,
  
  // Product Selection with Sizes
  'product_size': formData.selected_products[0].size,
  'product_size_label': formData.selected_products[0].size_label || formData.selected_products[0].size,
  'product_quantity': formData.selected_products[0].quantity,
  'total_products': formData.selected_products.length,
  
  // Arrays for multiple products
  'selected_products': formData.selected_products.map(p => p.product_name),
  'product_sizes': formData.selected_products.map(p => p.size),
  'product_quantities': formData.selected_products.map(p => p.quantity),
  
  // Size in different formats
  'graft_size_requested': formData.selected_products[0].size,
  'product_dimensions': formData.selected_products[0].size
}
```

### 6. Service & Shipping Information
```javascript
{
  // Service Details
  'expected_service_date': formData.expected_service_date,
  'anticipated_treatment_date': formData.expected_service_date,
  'place_of_service': formData.place_of_service,
  'place_of_service_code': formData.place_of_service,
  
  // Shipping Address
  'shipping_same_as_patient': formData.shipping_same_as_patient,
  'shipping_address_line1': formData.shipping_same_as_patient ? formData.patient_address_line1 : formData.shipping_address_line1,
  'shipping_address_line2': formData.shipping_same_as_patient ? formData.patient_address_line2 : formData.shipping_address_line2,
  'shipping_city': formData.shipping_same_as_patient ? formData.patient_city : formData.shipping_city,
  'shipping_state': formData.shipping_same_as_patient ? formData.patient_state : formData.shipping_state,
  'shipping_zip': formData.shipping_same_as_patient ? formData.patient_zip : formData.shipping_zip,
  'shipping_full_address': // Computed full address
  
  // Delivery Options
  'shipping_speed': formData.shipping_speed,
  'delivery_date': formData.delivery_date,
  'delivery_notes': formData.delivery_notes,
  'special_instructions': formData.special_instructions
}
```

### 7. Billing & Medicare Information
```javascript
{
  // Medicare Status
  'medicare_part_b_authorized': formData.medicare_part_b_authorized,
  'snf_status': formData.snf_status,
  'snf_days': formData.snf_days,
  'snf_over_100_days': formData.snf_over_100_days,
  'hospice_status': formData.hospice_status,
  'part_a_status': formData.part_a_status,
  
  // Global Period
  'global_period_status': formData.global_period_status,
  'global_period_cpt': formData.global_period_cpt,
  'global_period_surgery_date': formData.global_period_surgery_date,
  
  // CPT Codes
  'application_cpt_codes': formData.application_cpt_codes.join(', '),
  'cpt_codes_list': formData.application_cpt_codes,
  'prior_applications': formData.prior_applications,
  'anticipated_applications': formData.anticipated_applications
}
```

### 8. Episode & Tracking Information
```javascript
{
  // Episode Details
  'episode_id': episode.id,
  'patient_display_id': episode.patient_display_id,
  'ivr_submission_id': episode.docuseal_submission_id,
  
  // Request Information
  'request_type': formData.request_type,
  'request_date': new Date().toISOString().split('T')[0],
  'request_time': new Date().toTimeString().split(' ')[0],
  'submission_date': new Date().toISOString().split('T')[0],
  
  // Sales Information
  'sales_representative': auth.user.name,
  'sales_rep_email': auth.user.email,
  'sales_rep_phone': auth.user.phone
}
```

### 9. Compliance & Documentation
```javascript
{
  // Attestations
  'information_accurate': formData.information_accurate,
  'medical_necessity_established': formData.medical_necessity_established,
  'maintain_documentation': formData.maintain_documentation,
  'authorize_prior_auth': formData.authorize_prior_auth,
  'hipaa_consent': formData.hipaa_consent,
  
  // Documentation Status
  'insurance_cards_uploaded': formData.insurance_card_front ? 'Yes' : 'No',
  'clinical_notes_uploaded': formData.clinical_notes ? 'Yes' : 'No',
  'wound_photo_uploaded': formData.wound_photo ? 'Yes' : 'No',
  'face_sheet_uploaded': formData.face_sheet ? 'Yes' : 'No'
}
```

### 10. Manufacturer-Specific Fields
```javascript
{
  // Dynamic fields based on manufacturer
  ...formData.manufacturer_fields,
  
  // Common manufacturer fields
  'lot_number': formData.manufacturer_fields?.lot_number,
  'tissue_id': formData.manufacturer_fields?.tissue_id,
  'graft_id': formData.manufacturer_fields?.graft_id,
  'serial_number': formData.manufacturer_fields?.serial_number
}
```

## Implementation Notes

### Field Name Variations
Many DocuSeal templates may use slightly different field names. Common variations:
- `patient_name` vs `patientName` vs `patient_full_name`
- `dob` vs `date_of_birth` vs `patient_dob`
- `member_id` vs `policy_number` vs `insurance_id`

### Data Formatting
- Dates: Use `YYYY-MM-DD` format
- Phone numbers: Include formatting `(XXX) XXX-XXXX`
- Boolean fields: Convert to "Yes"/"No" strings
- Arrays: Join with commas or provide as JSON

### Null Handling
- Always provide empty string `''` instead of null
- Use default values where appropriate
- Check for undefined before accessing nested properties

### Coverage Tracking
To achieve 90%+ prefill:
1. Map all collected QuickRequest fields
2. Provide multiple format variations
3. Include computed/derived fields
4. Add metadata and tracking fields
5. Handle manufacturer-specific requirements

## Future Enhancements

### Order Form Integration
When adding order forms to the same episode:
1. Reuse all patient, insurance, and provider data
2. Add order-specific fields (PO numbers, tracking, etc.)
3. Link to existing episode and IVR submission
4. Maintain data consistency across forms

### Field Mapping API
Consider creating a dynamic field mapping service:
```php
$fieldMapper = new DocuSealFieldMapper($manufacturer);
$mappedData = $fieldMapper->mapQuickRequestData($formData, $episode);
```

This ensures consistent mapping across all manufacturers and form types.