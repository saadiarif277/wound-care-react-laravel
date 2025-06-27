# DocuSeal Mapper MCP Server - Usage Examples

This document provides practical examples of using the DocuSeal Mapper MCP server with your wound care application.

## Understanding the Workflow

The DocuSeal integration in your wound care app follows this flow:

1. **Step 7 (IVR)**: Insurance Verification Request form
2. **Step 8 (Order Form)**: Optional manufacturer order form
3. **Mapping**: QuickRequest data → DocuSeal template fields

## Real-World Examples

### Example 1: Complete ACZ Product Order

```javascript
// 1. First, check what manufacturer handles this product
Tool: get_manufacturer_config
Input: { "identifier": "EMP001" }

// Response:
{
  "product_code": "EMP001",
  "manufacturer": "ACZ",
  "config": {
    "id": "1",
    "name": "ACZ",
    "templateId": "852440",
    "signatureRequired": true,
    "hasOrderForm": false,
    "fields": { ... }
  }
}

// 2. Map the form data for DocuSeal
Tool: map_form_data
Input: {
  "manufacturer": "ACZ",
  "formData": {
    "patient_first_name": "John",
    "patient_last_name": "Doe",
    "patient_dob": "1980-05-15",
    "patient_email": "john.doe@email.com",
    "patient_phone": "555-123-4567",
    "patient_address_line1": "123 Main St",
    "patient_city": "Los Angeles",
    "patient_state": "CA",
    "patient_zip": "90001",
    "primary_insurance_name": "Medicare",
    "primary_member_id": "1EG4-TE5-MK73",
    "primary_diagnosis_code": "L97.512",
    "wound_type": "Diabetic Foot Ulcer",
    "wound_location": "Right foot, plantar",
    "wound_size_length": "3.5",
    "wound_size_width": "2.0",
    "provider_name": "Dr. Sarah Johnson",
    "provider_npi": "1234567890",
    "facility_name": "Wound Care Center of LA"
  }
}

// Response shows mapped fields:
{
  "manufacturer": "ACZ",
  "templateId": "852440",
  "mappedFields": {
    "patient_name": "John Doe",
    "patient_dob": "1980-05-15",
    "patient_email": "john.doe@email.com",
    "patient_phone": "555-123-4567",
    "patient_address": "123 Main St ",
    "patient_city": "Los Angeles",
    "patient_state": "CA",
    "patient_zip": "90001",
    "insurance_name": "Medicare",
    "member_id": "1EG4-TE5-MK73",
    "diagnosis_code": "L97.512",
    "wound_type": "Diabetic Foot Ulcer",
    "wound_location": "Right foot, plantar",
    "wound_size": 7,
    "provider_name": "Dr. Sarah Johnson",
    "provider_npi": "1234567890",
    "facility_name": "Wound Care Center of LA"
  },
  "unmappedFormFields": []
}

// 3. Validate before submission
Tool: validate_mapping
Input: {
  "manufacturer": "ACZ",
  "formData": { ... same as above ... }
}

// Response:
{
  "manufacturer": "ACZ",
  "isValid": true,
  "missingFields": [],
  "message": "All required fields are present"
}
```

### Example 2: Handling Missing Fields

```javascript
// Incomplete form data
Tool: validate_mapping
Input: {
  "manufacturer": "Advanced Health",
  "formData": {
    "patient_first_name": "Jane",
    "patient_last_name": "Smith",
    // Missing DOB, insurance info, provider info
    "wound_type": "Venous Leg Ulcer"
  }
}

// Response:
{
  "manufacturer": "Advanced Health",
  "isValid": false,
  "missingFields": [
    "patient_dob",
    "primary_insurance_name",
    "primary_member_id",
    "provider_name",
    "provider_npi"
  ],
  "message": "Missing 5 required field(s)"
}
```

### Example 3: Finding IVR Forms

```javascript
// Get the IVR form location for a manufacturer
Tool: get_ivr_form_path
Input: { "manufacturer": "BioWerX" }

// Response:
{
  "manufacturer": "BioWerX",
  "formPath": "docs/ivr-forms/BioWerX/BioWerX Fillable IVR Apr 2024.pdf",
  "fullPath": "/path/to/project/docs/ivr-forms/BioWerX/BioWerX Fillable IVR Apr 2024.pdf",
  "exists": true
}
```

### Example 4: Listing All Manufacturers

```javascript
Tool: list_manufacturers
Input: {}

// Response:
[
  {
    "name": "ACZ",
    "id": "1",
    "templateId": "852440",
    "signatureRequired": true,
    "hasOrderForm": false,
    "fieldsCount": 16
  },
  {
    "name": "Advanced Health",
    "id": "2",
    "templateId": "TBD",
    "signatureRequired": true,
    "hasOrderForm": true,
    "fieldsCount": 0
  },
  // ... more manufacturers
]
```

## Integration with Your Code

### In Step7DocuSealIVR.tsx

The component uses manufacturer config to determine if IVR is required:

```javascript
// This is what happens in your component
const manufacturerConfig = getManufacturerByProduct(selectedProduct.name);

if (!manufacturerConfig?.signatureRequired) {
  // No IVR required - skip this step
  return <NoIVRRequired />;
}

// Use the mapper to prepare data
const mappedData = mapFormData(manufacturerConfig.name, formData);
```

### In DocuSealEmbed.tsx

The embed component receives:
- `manufacturerId`: From the manufacturer config
- `productCode`: The product being ordered
- `formData`: The mapped data from the MCP server

## Common Scenarios

### Scenario 1: Product with Multiple Sizes
```javascript
// Products like "Q2 CompleteFT" may have multiple sizes
formData: {
  "selected_products": [{
    "product_id": 123,
    "quantity": 2,
    "size": "5x5cm"
  }],
  // ... other fields
}
```

### Scenario 2: Wound Duration Calculation
```javascript
// The mapper automatically formats duration
formData: {
  "wound_duration_years": "1",
  "wound_duration_months": "3",
  "wound_duration_days": "15"
}

// Mapped as:
mappedFields: {
  "wound_duration": "1 years, 3 months, 15 days"
}
```

### Scenario 3: Hospice Patient
```javascript
formData: {
  "hospice_status": true,
  "hospice_family_consent": true,
  "hospice_clinically_necessary": true
}

// Mapped as:
mappedFields: {
  "hospice_status": "Yes",
  "hospice_family_consent": "Yes",
  "hospice_clinically_necessary": "Yes"
}
```

## Debugging Tips

1. **Check Product Mapping**: Use `get_manufacturer_config` with product code
2. **Verify Field Mapping**: Use `map_form_data` to see how fields are transformed
3. **Validate Early**: Use `validate_mapping` before attempting DocuSeal submission
4. **Find Forms**: Use `get_ivr_form_path` to locate PDF templates

## Error Handling

Common errors and solutions:

1. **"Unknown manufacturer"**: Check manufacturer name spelling
2. **"No configuration found"**: Product code may not be mapped
3. **"Missing required fields"**: Use validate_mapping to identify gaps
4. **"File not found"**: IVR form PDF may be in different location