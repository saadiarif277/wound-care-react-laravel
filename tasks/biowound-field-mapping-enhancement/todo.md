# BioWound Solutions Field Mapping Enhancement

## Problem Summary
The user reported that the BioWound Solutions DocuSeal form was loading with many empty fields despite some fields being populated.

## Todo List
- [x] Enhance QuickRequestOrchestrator::prepareDocusealData to aggregate missing fields
- [x] Add current user/sales rep information extraction
- [x] Map place_of_service to individual POS checkboxes
- [x] Map wound_type to individual wound type checkboxes
- [x] Map product codes to Q-code checkboxes
- [x] Ensure request_type is being captured in episode metadata
- [ ] Test BioWound Solutions form with enhanced field mapping

## Changes Made

### 1. Enhanced QuickRequestOrchestrator::prepareDocusealData Method

#### Added Sales Rep/Contact Information
- Extract current user as sales rep with name, email
- Fallback phone number extraction from multiple sources (user, organization, provider, facility)
- Extract territory from user's organization (territory, region, or state fields)
- Map to BioWound fields: name, email, phone, contact_name, contact_email, sales_rep, rep_email, territory

#### Added Request Type Mapping
- Map request_type from form data to individual checkboxes
- Default to new_request if not specified
- Map to fields: new_request, re_verification, additional_applications, new_insurance

#### Enhanced Patient Data
- Added patient address formatting
- Added SNF (Skilled Nursing Facility) status mapping with default to "No"
- Map to fields: patient_address, patient_snf_yes, patient_snf_no, snf_days

#### Added Place of Service Mapping
- Convert single place_of_service code to individual checkboxes
- Added city_state_zip formatting for facility
- Map to fields: pos_11, pos_21, pos_24, pos_22, pos_32, pos_13, pos_12, critical_access_hospital, other_pos

#### Enhanced Provider Data
- Added physician specialty field
- Added provider Medicaid number
- Map to fields: physician_specialty, provider_medicaid

#### Enhanced Facility Data
- Added facility Medicaid number
- Map to field: facility_medicaid

#### Enhanced Clinical Data Mapping
- Map wound types to individual checkboxes (wound_dfu, wound_vlu, wound_chronic_ulcer, etc.)
- Added global period status mapping
- Added additional clinical field aliases for BioWound compatibility
- Map to fields: location_of_wound, previously_used_therapies, co_morbidities, post_debridement_size, primary_icd10, secondary_icd10

#### Enhanced Insurance Data
- Added BioWound-specific field aliases (primary_name, primary_policy, primary_phone, etc.)
- Added prior authorization checkbox mapping
- Map to fields: prior_auth_yes, prior_auth_no

#### Added Product Q-Code Mapping
- Extract all selected product codes
- Map to individual Q-code checkboxes
- Map to fields: q4161, q4205, q4290, q4238, q4239, q4266, q4267, q4265

### 2. Updated QuickRequestController

#### Added request_type to Episode Data
- Modified createDraftEpisode to include request_type in episodeData
- Defaults to 'new_request' if not provided in form data

### 3. BioWound Solutions Config
- Verified that patient_first_name and patient_last_name fields are already defined
- Config already has comprehensive field mappings for all DocuSeal form fields

## Review

The enhancements ensure that all BioWound Solutions DocuSeal form fields receive proper data by:

1. **Aggregating data from multiple sources** - The orchestrator now pulls data from user profile, organization, provider, facility, and form data
2. **Mapping single values to multiple checkboxes** - Place of service, wound types, and product codes are now properly mapped to individual checkbox fields
3. **Adding missing data fields** - Sales rep info, contact details, request type, and various clinical fields that were previously missing
4. **Providing sensible defaults** - SNF status defaults to "No", request type defaults to "new_request", etc.

The changes follow the simplicity principle by:
- Only modifying two files (QuickRequestOrchestrator and QuickRequestController)
- Using existing data structures without creating new models or tables
- Leveraging the existing field mapping configuration
- Making minimal changes to achieve maximum field population

## Next Steps
1. Test the BioWound Solutions DocuSeal form to verify all fields are now populated
2. Monitor for any remaining empty fields
3. Apply similar enhancements to other manufacturers if needed