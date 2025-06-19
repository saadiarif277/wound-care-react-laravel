# Example IVR PDF Template with Embedded Text Field Tags

This is an example of how to structure a PDF template for DocuSeal with embedded text field tags for the Quick Request IVR forms.

## Template Structure

```
================================================================================
                            WOUND CARE IVR FORM
                         {{manufacturer}} Product Request
================================================================================

DATE: {{todays_date}}                                    TIME: {{current_time}}

PATIENT INFORMATION
--------------------------------------------------------------------------------
Patient Name: {{patient_first_name}} {{patient_last_name}}
Date of Birth: {{patient_dob}}                         Gender: {{patient_gender}}
Member ID: {{patient_member_id}}
Phone: {{patient_phone}}

Patient Address:
{{patient_address_line1}}
{{patient_address_line2}}
{{patient_city}}, {{patient_state}} {{patient_zip}}

CAREGIVER INFORMATION (if applicable)
--------------------------------------------------------------------------------
Caregiver Name: {{caregiver_name}}
Relationship: {{caregiver_relationship}}
Phone: {{caregiver_phone}}

INSURANCE INFORMATION
--------------------------------------------------------------------------------
Payer Name: {{payer_name}}
Payer ID: {{payer_id}}
Insurance Type: {{insurance_type}}

PROVIDER INFORMATION
--------------------------------------------------------------------------------
Provider Name: {{provider_name}}
NPI Number: {{provider_npi}}
Facility: {{facility_name}}
Facility Address: {{facility_address}}

PRODUCT INFORMATION
--------------------------------------------------------------------------------
Product Name: {{product_name}}
Product Code: {{product_code}}
Manufacturer: {{manufacturer}}
Size: {{size}}
Quantity: {{quantity}}

SERVICE INFORMATION
--------------------------------------------------------------------------------
Expected Service Date: {{expected_service_date}}
Wound Type: {{wound_type}}
Place of Service: {{place_of_service}}

CLINICAL ATTESTATIONS
--------------------------------------------------------------------------------
☐ Failed Conservative Treatment: {{failed_conservative_treatment;type=checkbox}}
☐ Information Accurate: {{information_accurate;type=checkbox}}
☐ Medical Necessity Established: {{medical_necessity_established;type=checkbox}}
☐ Will Maintain Documentation: {{maintain_documentation;type=checkbox}}
☐ Authorize Prior Authorization: {{authorize_prior_auth;type=checkbox}}

MANUFACTURER-SPECIFIC REQUIREMENTS
--------------------------------------------------------------------------------
<!-- ACZ-specific fields -->
☐ Physician Attestation of Medical Necessity: {{physician_attestation;type=checkbox}}
☐ Product Not Used Previously on This Wound: {{not_used_previously;type=checkbox}}

<!-- Advanced Health-specific fields -->
☐ Multiple Products Being Applied: {{multiple_products;type=checkbox}}
Additional Products: {{additional_products}}
☐ Previous Use of Advanced Health Products: {{previous_use;type=checkbox}}
Previous Product Info: {{previous_product_info}}

<!-- MedLife-specific fields -->
Amnio AMP Size Required: {{amnio_amp_size;type=radio;options=2x2,2x4,4x4,4x6,4x8}}

<!-- Centurion-specific fields -->
☐ Previous Amnion/Chorion Product Use: {{previous_amnion_use;type=checkbox}}
Previous Product: {{previous_product}}
Previous Date: {{previous_date;type=date}}
☐ STAT/After-hours Order: {{stat_order;type=checkbox}}

<!-- BioWerX-specific fields -->
☐ First Application to This Wound: {{first_application;type=checkbox}}
☐ Reapplication: {{reapplication;type=checkbox}}
Previous Product: {{previous_product}}

<!-- BioWound-specific fields -->
☐ California Facility Certification: {{california_facility;type=checkbox}}
Mesh Configuration: {{mesh_configuration;type=radio;options=DL,TL,SL}}
☐ Previous Biologics Failed: {{previous_biologics_failed;type=checkbox}}
Failed Biologics List: {{failed_biologics_list}}

<!-- Extremity Care-specific fields -->
Quarter: {{quarter;type=radio;options=Q1,Q2,Q3,Q4}}
Order Type: {{order_type;type=radio;options=standing,single}}

<!-- Skye Biologics-specific fields -->
Shipping Speed: {{shipping_speed_required;type=select;options=standard_ground,next_day_air,next_day_air_early,saturday_delivery}}
☐ Temperature-Controlled Shipping: {{temperature_controlled;type=checkbox}}

<!-- Total Ancillary Forms-specific fields -->
☐ Universal Benefits Verification Completed: {{universal_benefits_verified;type=checkbox}}
Facility Account Number: {{facility_account_number}}

VERBAL ORDER INFORMATION (if applicable)
--------------------------------------------------------------------------------
Verbal Order Received From: {{verbal_order_received_from}}
Date: {{verbal_order_date;type=date}}
Documented By: {{verbal_order_documented_by}}

PROVIDER AUTHORIZATION
--------------------------------------------------------------------------------
I certify that the information provided is accurate and that the prescribed 
product is medically necessary for the treatment of this patient.

Provider Signature: {{provider_signature;type=signature;role=Provider;required=true}}

Print Name: {{provider_name}}                          Date: {{signature_date;type=date}}

NPI: {{provider_npi}}

================================================================================
                           END OF IVR FORM
================================================================================
```

## Advanced Field Examples

### Conditional Fields

```
Previous Product Details: {{previous_product_details;condition=previous_use=Yes}}
```

### Required Signature Fields

```
Provider Signature: {{provider_signature;type=signature;role=Provider;required=true}}
Patient Signature: {{patient_signature;type=signature;role=Patient}}
```

### Date Fields with Auto-Population

```
Today's Date: {{todays_date;type=datenow}}
Service Date: {{expected_service_date;type=date;required=true}}
```

### Multi-Select Options

```
Wound Type: {{wound_type;type=select;options=surgical,traumatic,diabetic_foot,pressure,venous,arterial,burn,other}}
```

### Text Areas for Long Responses

```
Additional Notes: {{additional_notes;type=text;multiline=true}}
```

## Implementation Tips

1. **Test Templates**: Always test with sample data before going live
2. **Field Alignment**: Ensure field tags align properly in your PDF layout
3. **Required Fields**: Mark critical fields as required
4. **Conditional Logic**: Use conditions to show/hide manufacturer-specific sections
5. **Signature Placement**: Position signature fields appropriately for signing
6. **Font Consistency**: Ensure embedded fields match your PDF's font styling

## Manufacturer-Specific Template Variations

Each manufacturer can have their own template with specific fields:

- **ACZ Template**: Focus on physician attestation and previous use
- **Advanced Health Template**: Include multiple product questions
- **MedLife Template**: Emphasize size selection requirements
- **BioWound Template**: Include mesh configuration and biologics history
- **Skye Biologics Template**: Focus on shipping requirements

This approach allows for highly customized forms while maintaining consistent data collection across all manufacturers.
