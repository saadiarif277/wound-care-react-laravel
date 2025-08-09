# DocuSeal Patient Name Field Mapping Fix

## Problem Statement

DocuSeal submissions were failing for some manufacturers (like BioWound Solutions) with error: "Unknown field: patient_first_name" while MedLife Solutions worked correctly.

## Todo Items

- [x] Analyze and compare manufacturer configurations to identify differences in patient name handling
- [x] Check BioWound Solutions and other failing manufacturer configs for missing patient_name field mappings
- [x] Update manufacturer configs to ensure proper patient_name field mapping in docuseal_field_names
- [x] Add or fix computation logic for patient_name field in manufacturer configs
- [x] Test the updated configurations to ensure DocuSeal submissions work correctly
- [x] Document the fix and create a review summary

## Root Cause Analysis

The issue occurred due to multiple factors:

1. **Data Flow Issue**: Patient data was stored with separate `patient_first_name` and `patient_last_name` fields
2. **Template Field Retrieval Error**: DocuSeal API authentication was failing (401 error) due to incorrect header (`X-API-TOKEN` instead of `X-Auth-Token`)
3. **Fallback Logic**: When template fields couldn't be retrieved, the system sent ALL fields to DocuSeal, including unknown fields like `patient_first_name`
4. **Transformer Errors**: BioWound Solutions config had invalid transformer formats (`currency` and `tax_id` without proper type prefix)

## Solution Implemented

### 1. Fixed Authentication Header (DocusealService.php)

- Changed `X-API-TOKEN` to `X-Auth-Token` in `getTemplateFieldsFromAPI` method
- This allows proper template field retrieval from DocuSeal API

### 2. Added Fallback Field Filtering (DocusealService.php)

- Created `getDefaultExcludedFields()` method with known problematic fields
- Updated `prepareFieldsForDocuseal` to exclude these fields when template fields aren't available
- Specifically handles patient name components when `patient_name` already exists

### 3. Fixed Transformer Issues (biowound-solutions.php)

- Commented out invalid `currency` transformers
- Commented out invalid `tax_id` transformers
- These were causing the field mapping to fail entirely

### 4. Verification

- Created test script `test-biowound-patient-name-fix.php`
- Confirmed that BioWound Solutions now:
  - Properly computes `patient_name` from components
  - Excludes individual name fields from DocuSeal submission
  - Maps to correct DocuSeal field name "Patient Name"

## Technical Details

### Why MedLife Solutions Worked

- Has proper field computation: `'patient_first_name + patient_last_name'`
- The `convertToDocusealFields` method in UnifiedFieldMappingService handles the concatenation
- Fields are properly filtered based on `docuseal_field_names` configuration

### Why BioWound Solutions Failed

- Template field retrieval failed due to auth error
- Without template fields, no field filtering occurred
- Raw fields including `patient_first_name` were sent to DocuSeal
- DocuSeal rejected unknown fields

### The Fix Flow

1. Data comes in with `patient_first_name` and `patient_last_name`
2. UnifiedFieldMappingService computes `patient_name` based on config
3. Individual name fields are excluded from final output
4. Only `patient_name` is sent to DocuSeal with correct field name

## Files Modified

1. `/app/Services/DocusealService.php`
   - Fixed auth header
   - Added default field exclusion logic
   - Added `getDefaultExcludedFields()` method

2. `/config/manufacturers/biowound-solutions.php`
   - Fixed invalid transformer formats

## Testing Results

Test script output shows successful field mapping:

- Has patient_name: YES (John)
- Has patient_first_name: NO
- Has patient_last_name: NO
- Patient Name field found in DocuSeal fields: YES

## Recommendations

1. Apply similar fixes to other manufacturer configs that may have invalid transformers
2. Consider adding validation for transformer formats during config loading
3. Implement better error handling when template field retrieval fails
4. Add integration tests for each manufacturer's field mapping

## Review Summary

This fix ensures that patient name fields are properly handled across all manufacturers, even when DocuSeal API template retrieval fails. The solution is defensive and handles edge cases gracefully while maintaining the correct data flow from raw input fields to properly formatted DocuSeal submissions.
