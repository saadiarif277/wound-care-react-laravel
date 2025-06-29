# DocuSeal Field Mapping Implementation

## Overview

This document describes the implementation of the DocuSeal pre-filling feature that automatically populates IVR and Order forms with data collected during the Quick Request process.

## Problem Statement

DocuSeal forms were not being pre-filled with patient, provider, and order data despite the data being collected and sent to the API. The issue was caused by complex, overlapping field mapping logic that failed to properly convert our application's field names to the exact field names required by DocuSeal templates.

## Solution Architecture

### 1. Backend Refactoring

#### Simplified Field Mapping Flow

The `generateSubmissionSlug` method in `QuickRequestController` was refactored to use a single, unified approach:

```php
// OLD: Complex branching logic
if ($aiEnabled && $aiProvider !== 'mock') {
    // AI mapping path
} else {
    // Static mapping path
}

// NEW: Single unified path
$manufacturerConfig = $this->fieldMappingService->getManufacturerConfig($manufacturer->name);
$mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(...);
$docuSealFields = $this->fieldMappingService->convertToDocuSealFields(...);
```

#### Key Changes

1. **Removed AI branching logic** - All field mapping now goes through UnifiedFieldMappingService
2. **Enhanced logging** - Comprehensive logging at each step for debugging
3. **Better error handling** - Specific error messages for different failure scenarios
4. **Support for multiple response formats** - Handles various DocuSeal API response structures

### 2. Frontend Enhancements

#### Debug Mode

Added comprehensive debugging to track data flow:

```typescript
// DocuSealEmbed component now includes debug mode
<DocuSealEmbed
    templateId={templateId}
    formData={prepareFormData()}
    debug={true} // Enable debug output
/>
```

#### Data Preparation

Step7DocuSealIVR now prepares comprehensive form data:

```typescript
const prepareFormData = () => {
    return {
        // Patient Information
        patient_name: quickRequest?.patient_name || '',
        patient_dob: quickRequest?.patient_dob || '',
        
        // Provider Information
        physician_name: quickRequest?.physician_name || '',
        physician_npi: quickRequest?.physician_npi || '',
        
        // Product Information
        product_name: quickRequest?.product?.name || '',
        manufacturer_name: quickRequest?.product?.manufacturer?.name || '',
        
        // ... all other fields
    };
};
```

### 3. Field Mapping Configuration

The system uses `config/field-mapping.php` to define manufacturer-specific field mappings:

```php
'manufacturers' => [
    'MedLife Solutions' => [
        'docuseal_template_id' => '123456',
        'docuseal_field_names' => [
            'patient_name' => 'Patient Name',
            'physician_npi' => 'Physician NPI',
            // ... other mappings
        ]
    ]
]
```

## Data Flow

1. **Quick Request Form** → Collects patient, provider, product data
2. **Step7DocuSealIVR** → Prepares comprehensive data object
3. **DocuSealEmbed** → Sends data to backend endpoint
4. **QuickRequestController** → Processes request
5. **UnifiedFieldMappingService** → Maps to canonical fields
6. **convertToDocuSealFields** → Converts to DocuSeal format
7. **DocuSeal API** → Creates submission with pre-filled data

## Testing

### Manual Testing

Run the test script to verify field mapping:

```bash
php tests/Manual/test-docuseal-field-mapping.php
```

### Debug Output

When debug mode is enabled, you'll see:

1. **Frontend Console**:
   - Form data being sent
   - API response details
   - Field mapping results

2. **Laravel Logs**:
   - Request validation
   - Manufacturer resolution
   - Field mapping process
   - DocuSeal API communication

## Troubleshooting

### Fields Not Pre-filling

1. **Check Field Names**: Ensure exact match between config and DocuSeal template
2. **Verify Data Flow**: Enable debug mode and check console/logs
3. **Test Mapping**: Run the manual test script
4. **API Response**: Check for 422 errors indicating field name mismatches

### Common Issues

1. **Empty Fields**: Data not being passed from frontend
2. **Wrong Field Names**: Mismatch between config and template
3. **Missing Configuration**: Manufacturer not configured in field-mapping.php
4. **Template Issues**: DocuSeal template fields not properly set up

## Configuration Requirements

### Environment Variables

```env
DOCUSEAL_API_KEY=your_api_key
DOCUSEAL_API_URL=https://api.docuseal.com
```

### Field Mapping Config

Each manufacturer needs:
- `docuseal_template_id`: The template ID in DocuSeal
- `docuseal_field_names`: Mapping of canonical to DocuSeal field names

## Best Practices

1. **Always use exact field names** from DocuSeal templates
2. **Test new manufacturers** with the manual test script
3. **Enable debug mode** during development
4. **Check logs** for detailed error information
5. **Validate field mappings** before deployment

## Future Enhancements

1. **Field Validation**: Pre-validate fields against template before submission
2. **Caching**: Cache template field definitions
3. **Bulk Testing**: Automated tests for all manufacturers
4. **Field Preview**: Show which fields will be pre-filled before submission 