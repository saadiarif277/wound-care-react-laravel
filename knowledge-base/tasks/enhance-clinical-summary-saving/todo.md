# Enhance Clinical Summary Saving and Fix Date Formatting

## Objective
Save comprehensive information in the clinical_summary field during quick request processing, ensure docuseal_submission_id is saved in product_requests, and fix date formatting issues in admin order details view.

## Tasks

### 1. ✅ Enhance Clinical Summary Structure
- [x] Review current clinical_summary structure
- [x] Identify missing data that should be saved
- [x] Enhance QuickRequestData to include all necessary fields
- [x] Update clinical_summary saving to include comprehensive data

### 2. ✅ Fix Docuseal Submission ID Saving
- [x] Ensure docuseal_submission_id is saved in product_requests table
- [x] Update external API calls to save submission ID
- [x] Add proper error handling for submission ID saving

### 3. ✅ Enhance Data Saving in Quick Request
- [x] Save all form data in clinical_summary
- [x] Include patient, provider, facility, clinical, insurance data
- [x] Save product selection and order preferences
- [x] Include attestations and admin notes
- [x] Add timestamps for all data

### 4. ✅ Fix Date Formatting in Admin Order Details
- [x] Update date formatting in admin order details view
- [x] Ensure consistent date format across all fields
- [x] Fix timezone issues
- [x] Add proper date validation

### 5. ✅ Update Admin Order Details Display
- [x] Ensure all clinical_summary data is displayed
- [x] Add missing fields to admin interface
- [x] Improve data presentation
- [x] Add proper fallbacks for missing data

### 6. 🔍 Debug Docuseal Submission ID Flow
- [x] Add debugging to QuickRequestController submitOrder method
- [x] Add debugging to createProductRequest method
- [x] Test the complete flow from frontend to backend
- [x] Verify docuseal_submission_id is being passed correctly
- [x] Check if clinical_summary is being saved properly

## Current Issues

### Clinical Summary Structure Issues
1. **Missing Data**: Some form fields not being saved in clinical_summary
2. **Inconsistent Structure**: Data structure varies between quick request and admin display
3. **Missing Timestamps**: No timestamps for when data was saved
4. **Incomplete Provider/Facility Data**: Not all provider and facility information saved

### Docuseal Submission ID Issues
1. **Not Always Saved**: Submission ID not consistently saved in product_requests
2. **Missing in External API**: External API calls don't always save submission ID
3. **Error Handling**: No proper error handling for submission ID saving

### Date Formatting Issues
1. **Inconsistent Formats**: Different date formats used across admin interface
2. **Timezone Issues**: Dates not properly converted to user timezone
3. **Missing Dates**: Some date fields not properly formatted
4. **ISO vs Local Format**: Mix of ISO and local date formats

## Debugging Steps

### Step 1: Frontend Data Capture
1. **Check DocusealEmbed completion**: Verify `handleDocusealFormComplete` is called
2. **Check form data update**: Verify `docuseal_submission_id` is added to form data
3. **Check form submission**: Verify data is sent to backend

### Step 2: Backend Data Reception
1. **Check submitOrder method**: Verify `docuseal_submission_id` is in formData
2. **Check QuickRequestData creation**: Verify DTO extracts submission ID
3. **Check createProductRequest**: Verify submission ID is saved

### Step 3: Database Storage
1. **Check product_requests table**: Verify `docuseal_submission_id` column
2. **Check clinical_summary**: Verify submission ID is in JSON
3. **Check logs**: Verify debugging output

## Enhanced Clinical Summary Structure

### Patient Data
```json
{
  "patient": {
    "first_name": "string",
    "last_name": "string",
    "date_of_birth": "YYYY-MM-DD",
    "gender": "string",
    "phone": "string",
    "email": "string",
    "address_line1": "string",
    "address_line2": "string",
    "city": "string",
    "state": "string",
    "zip": "string",
    "member_id": "string",
    "display_id": "string",
    "is_subscriber": "boolean",
    "saved_at": "ISO timestamp"
  }
}
```

### Provider Data
```json
{
  "provider": {
    "id": "number",
    "name": "string",
    "npi": "string",
    "email": "string",
    "phone": "string",
    "specialty": "string",
    "saved_at": "ISO timestamp"
  }
}
```

### Facility Data
```json
{
  "facility": {
    "id": "number",
    "name": "string",
    "address": "string",
    "address_line1": "string",
    "address_line2": "string",
    "city": "string",
    "state": "string",
    "zip": "string",
    "phone": "string",
    "fax": "string",
    "email": "string",
    "npi": "string",
    "tax_id": "string",
    "saved_at": "ISO timestamp"
  }
}
```

### Clinical Data
```json
{
  "clinical": {
    "wound_type": "string",
    "wound_location": "string",
    "wound_size_length": "number",
    "wound_size_width": "number",
    "wound_size_depth": "number",
    "wound_duration_weeks": "number",
    "diagnosis_codes": ["array"],
    "primary_diagnosis_code": "string",
    "secondary_diagnosis_code": "string",
    "application_cpt_codes": ["array"],
    "clinical_notes": "string",
    "failed_conservative_treatment": "boolean",
    "saved_at": "ISO timestamp"
  }
}
```

### Insurance Data
```json
{
  "insurance": {
    "primary_name": "string",
    "primary_member_id": "string",
    "primary_plan_type": "string",
    "has_secondary": "boolean",
    "secondary_name": "string",
    "secondary_member_id": "string",
    "secondary_plan_type": "string",
    "saved_at": "ISO timestamp"
  }
}
```

### Product Selection Data
```json
{
  "product_selection": {
    "selected_products": ["array"],
    "manufacturer_id": "number",
    "manufacturer_name": "string",
    "saved_at": "ISO timestamp"
  }
}
```

### Order Preferences Data
```json
{
  "order_preferences": {
    "expected_service_date": "YYYY-MM-DD",
    "shipping_speed": "string",
    "place_of_service": "string",
    "delivery_instructions": "string",
    "saved_at": "ISO timestamp"
  }
}
```

### Attestations Data
```json
{
  "attestations": {
    "failed_conservative_treatment": "boolean",
    "information_accurate": "boolean",
    "medical_necessity_established": "boolean",
    "maintain_documentation": "boolean",
    "authorize_prior_auth": "boolean",
    "saved_at": "ISO timestamp"
  }
}
```

### Admin Data
```json
{
  "admin_note": "string",
  "admin_note_added_at": "ISO timestamp",
  "docuseal_submission_id": "string",
  "docuseal_template_id": "string",
  "saved_at": "ISO timestamp"
}
```

## Implementation Plan

### Phase 1: Enhance QuickRequestData
1. Update QuickRequestData DTOs to include all necessary fields
2. Add timestamps to all data structures
3. Ensure comprehensive data capture

### Phase 2: Update Clinical Summary Saving
1. Modify createProductRequest method to save comprehensive data
2. Add proper error handling for data saving
3. Ensure docuseal_submission_id is always saved

### Phase 3: Fix Date Formatting
1. Update admin order details controller
2. Implement consistent date formatting
3. Add timezone handling

### Phase 4: Update Admin Display
1. Update admin order details view
2. Add missing data fields
3. Improve data presentation

### Phase 5: Debug Data Flow
1. Add comprehensive logging
2. Test complete flow from frontend to backend
3. Verify data is saved correctly

## Review

### Summary of Changes Made

#### 1. Enhanced Clinical Summary Structure
- **Enhanced QuickRequestData DTOs**: Added comprehensive fields to all DTOs including ProviderData, FacilityData, ClinicalData, InsuranceData, ProductSelectionData, and OrderPreferencesData
- **Added Timestamps**: All data sections now include `saved_at` timestamps for tracking when data was saved
- **Comprehensive Data Capture**: Enhanced data capture to include all form fields, provider details, facility information, clinical data, insurance information, and product selection

#### 2. Fixed Docuseal Submission ID Saving
- **Updated createProductRequest Method**: Enhanced to ensure docuseal_submission_id is always saved in product_requests table
- **Added Error Handling**: Implemented proper error handling and logging for submission ID saving
- **Enhanced Logging**: Added comprehensive logging to track submission ID saving and clinical summary creation

#### 3. Enhanced Data Saving in Quick Request
- **Comprehensive Clinical Summary**: Updated to save all form data with proper structure and timestamps
- **Metadata Addition**: Added metadata section with creation timestamps, user information, and calculation data
- **Manufacturer Information**: Enhanced to capture and save manufacturer name and ID
- **Error Handling**: Added proper error handling and logging for data saving operations

#### 4. Fixed Date Formatting in Admin Order Details
- **Added Date Formatting Methods**: Created `formatDateForDisplay()` and `formatDateForISO()` methods for consistent date formatting
- **Consistent Date Formats**: Implemented consistent date formatting across all date fields
- **Timezone Handling**: Added proper timezone handling and error handling for date parsing
- **ISO String Support**: Added support for ISO string formatting for frontend compatibility

#### 5. Updated Admin Order Details Display
- **Enhanced Data Display**: Updated admin order details to display comprehensive clinical summary data
- **Additional Fields**: Added missing fields like provider phone/specialty, facility fax/email/npi/tax_id, clinical diagnosis codes, and wound duration
- **Timestamp Display**: Added saved_at timestamps for all data sections
- **Improved Fallbacks**: Enhanced fallback handling for missing data with proper error messages

#### 6. Added Debugging for Data Flow
- **Frontend Debugging**: Added console logs to track docuseal_submission_id capture
- **Backend Debugging**: Added comprehensive logging to track data flow through backend
- **Database Verification**: Added logging to verify data is saved correctly

### Clinical Summary Structure Assessment
- **Comprehensive Data Structure**: All form data is now properly structured and saved with timestamps
- **Consistent Format**: Data structure is consistent across all sections (patient, provider, facility, clinical, insurance, etc.)
- **Metadata Tracking**: Added metadata section for tracking creation information and calculations
- **Error Resilience**: Enhanced error handling and logging for data saving operations

### Date Formatting Fixes
- **Consistent Formatting**: All dates now use consistent formatting methods
- **Proper Timezone Handling**: Added proper timezone handling for date parsing and formatting
- **Error Handling**: Added comprehensive error handling for invalid dates
- **Frontend Compatibility**: Added ISO string formatting for frontend compatibility

### Admin Display Improvements
- **Enhanced Data Presentation**: Admin order details now display comprehensive information from clinical summary
- **Additional Fields**: Added missing fields like provider contact information, facility details, clinical diagnosis codes
- **Timestamp Information**: Added saved_at timestamps to show when data was saved
- **Better Error Handling**: Improved error handling and fallback display for missing data

### Key Improvements
1. **Comprehensive Data Saving**: All form data is now saved with proper structure and timestamps
2. **Consistent Date Formatting**: All dates are properly formatted and handled
3. **Enhanced Admin Display**: Admin interface now shows comprehensive order information
4. **Better Error Handling**: Improved error handling and logging throughout
5. **Metadata Tracking**: Added metadata for tracking data creation and modifications
6. **Debugging Capabilities**: Added comprehensive logging to track data flow issues 
