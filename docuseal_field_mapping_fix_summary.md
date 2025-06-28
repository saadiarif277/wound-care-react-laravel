# DocuSeal Field Mapping Fix Summary

## Issues Fixed

### 1. Field Format Error (500 Error)
**Problem**: DocuSeal API was returning "fields must be a Array in submitters[0]"
**Solution**: Changed field format from associative array to array of objects:
```php
// Before
'fields' => ['amnio_amp_size' => '4x4', ...]

// After  
'fields' => [
    ['name' => 'amnio_amp_size', 'value' => '4x4'],
    ...
]
```

### 2. Unknown Field Error
**Problem**: DocuSeal was rejecting fields like "provider_npi" as unknown
**Solution**: 
- Added field validation to filter out fields that don't exist in DocuSeal template
- Created `DocuSealFieldMapper` service to handle proper field mapping
- Enhanced logging to track which fields are being skipped

### 3. AI Integration for Templates Page
**Problem**: AI wasn't integrated into the template mapping interface
**Solution**:
- Added "AI Auto-Map" button to FieldMappingInterface component
- Created `autoMapFields` endpoint in TemplateMappingController
- Button automatically maps unmapped fields using AI suggestions

## New Components Added

### 1. DocuSealFieldMapper Service
Location: `/app/Services/DocuSealFieldMapper.php`
- Converts associative arrays to DocuSeal format
- Maps fields based on JSON mapping files
- Handles manufacturer-specific field mappings

### 2. Debug Tools
- `DebugFieldMappingController` - Web-based debugging
- `DocuSealDebugController` - API debugging endpoints
- `DebugDocuSealFields` command - CLI debugging

### 3. Field Validation Controller
Location: `/app/Http/Controllers/Api/DocuSealFieldValidationController.php`
- Validates fields before submission
- Fetches template fields from DocuSeal
- Provides field compatibility checking

## How to Use

### 1. For Quick Request Forms
The field mapping now automatically:
- Converts fields to correct format
- Uses AI mapping if enabled
- Falls back to JSON-based mapping if AI fails
- Filters out unknown fields

### 2. For Template Management
1. Go to Admin > DocuSeal > Templates
2. Click on a template to open field mapping
3. Click "AI Auto-Map" to automatically map fields
4. Review and save mappings

### 3. For Debugging
```bash
# Debug field mapping for an episode
php artisan docuseal:debug-fields 123 MedLife --show-values

# Clear template field cache
php artisan docuseal:debug-fields clear-cache
```

## Key Files Modified
1. `/app/Http/Controllers/QuickRequestController.php` - Fixed field format
2. `/app/Services/DocuSealService.php` - Enhanced field filtering
3. `/resources/js/Components/Admin/DocuSeal/FieldMappingInterface.tsx` - Added AI button
4. `/app/Http/Controllers/Api/TemplateMappingController.php` - Added auto-map endpoint

## Testing the Fix
1. Try loading a DocuSeal form - it should no longer give 500 error
2. Check if fields are being populated (may need proper field name matching)
3. Use the template mapping interface to set up proper field mappings with AI

## Next Steps
1. Ensure DocuSeal template field names match what we're sending
2. Run the field mapping migration for MedLife
3. Test with actual Azure OpenAI credentials for better AI mapping