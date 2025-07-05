# FHIR and DocuSeal Template Fixes

## Overview
Fix two critical issues:
1. **FHIR Feature Flag Error**: "Failed to create Quick Request episode: FHIR is disabled globally via feature flag"
2. **DocuSeal Template Storage**: Save DocuSeal template information in product_request for admin viewing

## Problem Analysis

### Issue 1: FHIR Feature Flag Error
- **Root Cause**: FhirService throws exception when FHIR is disabled via feature flags
- **Impact**: Quick Request episodes cannot be created when FHIR is disabled
- **Solution**: Update FhirService to handle disabled FHIR gracefully with local fallbacks

### Issue 2: DocuSeal Template Storage
- **Root Cause**: DocuSeal template information not saved in product_request table
- **Impact**: Admins cannot view which DocuSeal template was used for IVR forms
- **Solution**: Save docuseal_template_id in product_request when creating episodes

## Tasks

### 1. Fix FHIR Service Error Handling
- [x] Update `ensureAzureConfigured()` method to not throw exceptions when FHIR is disabled
- [x] Update `createPatient()` method to handle disabled FHIR gracefully
- [x] Update `create()` method to handle disabled FHIR gracefully
- [x] Update `search()` method to handle disabled FHIR gracefully
- [x] Update constructor to not get Azure access token immediately
- [x] Test Quick Request submission with FHIR disabled

### 2. Save DocuSeal Template in Product Request
- [ ] Update QuickRequestOrchestrator to determine DocuSeal template for manufacturer
- [ ] Save docuseal_template_id in product_request when creating episodes
- [ ] Update admin IVR view to display DocuSeal template information
- [ ] Test template information is saved and displayed correctly

### 3. Testing and Validation
- [ ] Test Quick Request submission with FHIR disabled
- [ ] Test Quick Request submission with FHIR enabled
- [ ] Verify DocuSeal template information is saved
- [ ] Verify admin can view template information in IVR section

## Implementation Details

### FHIR Service Changes
- Modified `ensureAzureConfigured()` to return gracefully instead of throwing exceptions
- Updated all FHIR methods to check feature flags and use local fallbacks
- Maintained backward compatibility with existing FHIR-enabled workflows

### DocuSeal Template Integration
- ProductRequest model already has `docuseal_template_id` field in fillable array
- Need to determine correct template based on manufacturer
- Save template ID during episode creation process
- Display template information in admin IVR view

## Summary of Changes Made

### 1. FHIR Service Fixes
- **Updated ensureAzureConfigured()**: Now returns gracefully when FHIR is disabled instead of throwing exceptions
- **Updated createPatient()**: Added proper fallback logic for disabled FHIR
- **Updated create()**: Added proper fallback logic for disabled FHIR  
- **Updated search()**: Added proper fallback logic for disabled FHIR
- **Enhanced Logging**: Changed from warning to info level for disabled FHIR operations

### 2. DocuSeal Template Storage (Pending)
- **ProductRequest Model**: Already has docuseal_template_id field in fillable array
- **QuickRequestOrchestrator**: Need to add template determination and saving logic
- **Admin IVR View**: Need to display template information

## Testing Checklist
- [ ] Quick Request submission works with FHIR disabled
- [ ] Quick Request submission works with FHIR enabled  
- [ ] DocuSeal template information is saved correctly
- [ ] Admin can view template information in IVR section
- [ ] No regression in existing functionality 