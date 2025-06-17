# DocuSeal Embedded Text Field Tags Guide

This guide explains how to use embedded text field tags in your DocuSeal PDF templates for the Quick Request IVR forms.

## Overview

Instead of manually drawing form fields in DocuSeal, you can embed text field tags directly in your PDF templates using the `{{field_name}}` syntax. When a submission is created, these tags are automatically converted to fillable form fields with pre-populated data.

## Basic Syntax

```
{{field_name}}
{{field_name;type=text;required=true}}
{{field_name;type=signature;role=Provider}}
```

## Quick Request Field Mapping

### Patient Information Fields
```
{{patient_first_name}}        - Patient's first name
{{patient_last_name}}         - Patient's last name  
{{patient_dob}}               - Date of birth (YYYY-MM-DD)
{{patient_member_id}}         - Insurance member ID
{{patient_gender}}            - Patient gender
{{patient_phone}}             - Patient phone number
```

### Patient Address Fields
```
{{patient_address_line1}}     - Street address line 1
{{patient_address_line2}}     - Street address line 2 (optional)
{{patient_city}}              - City
{{patient_state}}             - State (2-letter code)
{{patient_zip}}               - ZIP code
```

### Insurance Information Fields
```
{{payer_name}}                - Insurance company name
{{payer_id}}                  - Payer/Plan ID
{{insurance_type}}            - Type of insurance
```

### Product Information Fields
```
{{product_name}}              - Product name
{{product_code}}              - Product Q-code
{{manufacturer}}              - Manufacturer name
{{size}}                      - Product size
{{quantity}}                  - Quantity ordered
```

### Service Information Fields
```
{{expected_service_date}}     - Expected date of service
{{wound_type}}                - Type of wound
{{place_of_service}}          - Place of service
```

### Provider Information Fields
```
{{provider_name}}             - Provider's full name
{{provider_npi}}              - Provider NPI number
{{signature_date}}            - Date of signature
{{facility_name}}             - Facility name
{{facility_address}}          - Facility address
```

### Clinical Attestations (Checkbox Fields)
```
{{failed_conservative_treatment}}    - Conservative treatment failed (Yes/No)
{{information_accurate}}             - Information is accurate (Yes/No)
{{medical_necessity_established}}    - Medical necessity established (Yes/No)
{{maintain_documentation}}           - Will maintain documentation (Yes/No)
{{authorize_prior_auth}}             - Authorize prior authorization (Yes/No)
```

### Auto-Generated Fields
```
{{todays_date}}               - Current date (MM/DD/YYYY)
{{current_time}}              - Current time (HH:MM:SS AM/PM)
```

### Caregiver Information (Optional)
```
{{caregiver_name}}            - Caregiver name
{{caregiver_relationship}}    - Relationship to patient
{{caregiver_phone}}           - Caregiver phone number
```

### Verbal Order Information (Optional)
```
{{verbal_order_received_from}} - Who verbal order was received from
{{verbal_order_date}}          - Date of verbal order
{{verbal_order_documented_by}} - Who documented the verbal order
```

## Manufacturer-Specific Fields

### ACZ Fields
```
{{physician_attestation}}     - Physician attestation (Yes/No)
{{not_used_previously}}       - Product not used previously (Yes/No)
```

### Advanced Health Fields
```
{{multiple_products}}         - Multiple products in session (Yes/No)
{{additional_products}}       - List of additional products
{{previous_use}}              - Previous use of Advanced Health products (Yes/No)
{{previous_product_info}}     - Previous product info
```

### MedLife Fields
```
{{amnio_amp_size}}            - Amnio AMP size (2x2, 2x4, 4x4, 4x6, 4x8)
```

### Centurion Fields
```
{{previous_amnion_use}}       - Previous amnion/chorion use (Yes/No)
{{previous_product}}          - Previous product name
{{previous_date}}             - Previous product date
{{stat_order}}                - STAT/After-hours order (Yes/No)
```

### BioWerX Fields
```
{{first_application}}         - First application to wound (Yes/No)
{{reapplication}}             - Reapplication (Yes/No)
{{previous_product}}          - Previous product name
```

### BioWound Fields
```
{{california_facility}}       - California facility certification (Yes/No)
{{mesh_configuration}}        - Mesh configuration (DL/TL/SL)
{{previous_biologics_failed}} - Previous biologics failed (Yes/No)
{{failed_biologics_list}}     - List of failed biologics
```

### Extremity Care Fields
```
{{quarter}}                   - Quarter (Q1/Q2/Q3/Q4)
{{order_type}}                - Order type (standing/single)
```

### Skye Biologics Fields
```
{{shipping_speed_required}}   - Shipping speed (standard_ground/next_day_air/etc.)
{{temperature_controlled}}    - Temperature-controlled shipping (Yes/No)
```

### Total Ancillary Forms Fields
```
{{universal_benefits_verified}} - Universal benefits verified (Yes/No)
{{facility_account_number}}     - Facility account number
```

## Advanced Field Attributes

### Text Fields
```
{{field_name;type=text;required=true}}
{{field_name;type=text;readonly=true}}
{{field_name;type=text;placeholder=Enter value here}}
```

### Signature Fields
```
{{provider_signature;type=signature;role=Provider;required=true}}
{{patient_signature;type=signature;role=Patient}}
```

### Date Fields
```
{{service_date;type=date;required=true}}
{{todays_date;type=datenow}}
```

### Checkbox Fields
```
{{attestation_field;type=checkbox;required=true}}
```

### Select/Radio Fields
```
{{wound_type;type=select;options=surgical,traumatic,diabetic_foot}}
{{quarter;type=radio;options=Q1,Q2,Q3,Q4}}
```

### Conditional Fields
```
{{additional_info;condition=previous_use=Yes}}
```

## Implementation Steps

1. **Create PDF Template**: Design your IVR form PDF with embedded `{{field_name}}` tags
2. **Upload to DocuSeal**: Upload the PDF template to DocuSeal
3. **Configure Template**: Set template ID in manufacturer configuration
4. **Test Integration**: Test with Quick Request to ensure fields populate correctly

## Example PDF Template Structure

```
WOUND CARE IVR FORM

Patient Information:
Name: {{patient_first_name}} {{patient_last_name}}
DOB: {{patient_dob}}
Member ID: {{patient_member_id}}

Address:
{{patient_address_line1}}
{{patient_address_line2}}
{{patient_city}}, {{patient_state}} {{patient_zip}}

Product Information:
Product: {{product_name}} ({{product_code}})
Manufacturer: {{manufacturer}}
Size: {{size}}
Quantity: {{quantity}}

Clinical Attestations:
☐ Conservative treatment failed: {{failed_conservative_treatment}}
☐ Information is accurate: {{information_accurate}}
☐ Medical necessity established: {{medical_necessity_established}}

Provider Signature: {{provider_signature;type=signature;role=Provider;required=true}}
Date: {{signature_date}}
```

## Best Practices

1. **Field Names**: Use descriptive, consistent field names that match the code
2. **Required Fields**: Mark critical fields as required
3. **Field Types**: Use appropriate field types (text, signature, checkbox, etc.)
4. **Conditional Logic**: Use conditions to show/hide fields based on other field values
5. **Testing**: Always test templates with real data before production use

## Troubleshooting

- **Field Not Populating**: Check field name spelling matches exactly
- **Wrong Field Type**: Ensure field type matches expected data (checkbox for Yes/No)
- **Missing Data**: Check that QuickRequest form collects all required data
- **Conditional Fields**: Verify condition syntax and dependent field values