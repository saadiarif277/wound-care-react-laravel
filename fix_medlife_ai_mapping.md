# MedLife AI Field Mapping Fix

## Problem Summary
The AI is not filling out any fields for MedLife forms, specifically the `amnio_amp_size` field remains empty.

## Root Causes Identified

1. **Field Mapping Configuration**: The field mappings need to be properly set in the database
2. **AI Configuration**: AI services need to be properly enabled
3. **DocuSeal Field Names**: The actual field names in DocuSeal templates might differ from what we expect

## Solution Steps

### 1. Update Field Mappings in Database

The migration file exists but needs to be run:
```bash
php artisan migrate --path=database/migrations/2025_01_28_100000_add_medlife_field_mappings.php
```

### 2. Verify AI Configuration

Check these environment variables are set:
```env
AI_ENABLED=true
AI_PROVIDER=azure
AZURE_AI_FOUNDRY_ENABLED=true
AZURE_OPENAI_API_KEY=your_key_here
AZURE_OPENAI_ENDPOINT=your_endpoint_here
```

### 3. Debug the Actual Issue

Access these debug endpoints (after authentication):
- `/docuseal-debug/field-mapping` - Shows field mapping configuration
- `/docuseal-debug/test-actual-mapping` - Tests the actual mapping process
- `/api/docuseal-debug/medlife` - Comprehensive MedLife debugging

### 4. Key Field Mapping for MedLife

The critical field mapping for `amnio_amp_size`:
```json
{
  "amnio_amp_size": {
    "source": "amnio_amp_size",
    "type": "radio"
  }
}
```

### 5. What to Check

1. **DocuSeal Template**: Verify the template actually has a field named `amnio_amp_size`
2. **Field Type**: Ensure the field type in DocuSeal matches (radio button)
3. **Data Format**: The value "4x4" should be one of the available options in the radio field

## Quick Test

1. Login to the application
2. Navigate to `/docuseal-debug/test-actual-mapping`
3. Check if `amnio_field_mapped` is true
4. Verify the `amnio_field_value` shows "4x4"

## If Still Not Working

The issue might be:
1. DocuSeal template doesn't have the expected field names
2. The field IDs in DocuSeal are UUIDs, not the field names
3. AI service credentials are not properly configured

## Next Steps

1. Get the actual DocuSeal template field structure using their API
2. Map the UUID field IDs to our expected field names
3. Update the field mapping configuration accordingly