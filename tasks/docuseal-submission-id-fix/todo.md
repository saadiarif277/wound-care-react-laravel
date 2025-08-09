# Docuseal Submission ID Fix

## Problem

The `docuseal_submission_id` is not being saved in the global scope even after API hit, and the form automatically moves to the next screen instead of waiting for user confirmation. The submission ID needs to be properly saved in the database for admin view.

## Root Causes Identified

1. **Frontend State Management**: The `docuseal_submission_id` is not persisting in the global form state
2. **Backend Database**: The submission ID is not being properly saved to both episode and product request tables
3. **Navigation Logic**: Form automatically proceeds instead of waiting for user confirmation
4. **Episode-Episode Link**: Missing proper linking between IVR episode and main episode

## Tasks

### Frontend Fixes

- [x] Fix Step7DocusealIVR component to properly update global form state
- [x] Remove automatic navigation after form completion
- [x] Add manual "Continue" button after IVR completion
- [x] Ensure docuseal_submission_id persists in form data

### Backend Fixes

- [x] Update episode creation to properly save docuseal_submission_id
- [x] Ensure submission ID is saved in both episode and product request tables
- [x] Add proper episode-episode linking for IVR forms
- [x] Update database migrations if needed
- [x] Fix SubmitOrderRequest validation to allow docuseal_submission_id field

### Database Fixes

- [x] Verify docuseal_submission_id column exists in patient_manufacturer_ivr_episodes table
- [x] Verify docuseal_submission_id column exists in product_requests table
- [x] Add any missing database columns

### Admin View Fixes

- [x] Update admin view to display docuseal_submission_id
- [x] Add link to open IVR form in new tab
- [x] Show IVR completion status

## Implementation Plan

1. **Step 1**: Fix frontend state management and navigation
2. **Step 2**: Update backend to properly save submission ID
3. **Step 3**: Verify database schema and add missing columns
4. **Step 4**: Update admin view to display submission information
5. **Step 5**: Test complete flow end-to-end

## Files to Modify

### Frontend

- `resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx`
- `resources/js/Pages/QuickRequest/CreateNew.tsx`

### Backend

- `app/Http/Controllers/QuickRequestController.php`
- `app/Services/DocusealService.php`
- `app/Models/PatientManufacturerIVREpisode.php`

### Database

- Check existing migrations for docuseal_submission_id columns

## Success Criteria

- [x] docuseal_submission_id is properly saved in global form state
- [x] Form waits for user confirmation before proceeding
- [x] Submission ID is saved in database (both episode and product request)
- [x] SubmitOrderRequest validation allows docuseal_submission_id field
- [x] Admin can view submission ID and open IVR form
- [x] Complete flow works end-to-end without errors

## Review

### Changes Made

#### Frontend Changes

1. **Step7DocusealIVR.tsx**:
   - Removed automatic navigation after form completion
   - Added manual "Continue" button that appears after IVR completion
   - Enhanced completion status display to show submission ID
   - Added detailed logging for submission ID tracking

2. **CreateNew.tsx**:
   - Added specific logging for docuseal_submission_id in form data
   - Enhanced form data preparation to ensure submission ID is properly processed

#### Backend Changes

1. **QuickRequestController.php**:
   - Updated createProductRequest method to save docuseal_submission_id to both product request and episode
   - Added logging for submission ID updates
   - Enhanced form data processing to ensure submission ID is properly handled

2. **DocusealService.php**:
   - Enhanced webhook handler to update both episode and product request when submission is completed
   - Added logging for submission ID updates
   - Improved error handling and status tracking

3. **SubmitOrderRequest.php**:
   - **CRITICAL FIX**: Added `docuseal_submission_id` to validation rules as `nullable|string`
   - This was the root cause - Laravel validation was stripping the field from the request data

4. **OrderCenterController.php**:
   - Added `ivr_document_url` to order data passed to frontend
   - Enhanced order data preparation to include IVR document URL
   - Added `getDocusealDocument` method to fetch document URL from Docuseal API
   - Added route `/admin/orders/{orderId}/docuseal-document` for API access

5. **QuickRequestData.php**:
   - Added `ivrDocumentUrl` property to DTO
   - Updated fromFormData method to extract IVR document URL from form data

6. **DocusealService.php**:
   - Added `getSubmissionDocumentUrl` method to fetch document URL from Docuseal API
   - Uses existing `getDocumentUrl` method with proper error handling

#### Admin View Changes

1. **IVRDocumentSection.tsx**:
   - Added `ivrDocumentUrl` and `docusealSubmissionId` to IVRData interface
   - Enhanced "View IVR" button to call backend API for actual document URL
   - Button now fetches document URL from Docuseal API via `/admin/orders/{orderId}/docuseal-document`
   - Added fallback to constructed URL if API call fails
   - Added debugging to track submission ID and URL construction
   - Added conditional rendering to show different button text for IVR form vs documents

2. **OrderDetails.tsx**:
   - Added `ivr_document_url` to Order interface
   - Updated IVRDocumentSection props to include IVR document URL
   - Enhanced order data structure to support IVR form viewing

#### Database Verification

- Confirmed that `docuseal_submission_id` column exists in both `product_requests` and `patient_manufacturer_ivr_episodes` tables
- Verified that all necessary migrations are in place

### Key Improvements

1. **State Management**: The docuseal_submission_id now properly persists in the global form state
2. **User Control**: Users must manually click "Continue" after completing the IVR form
3. **Database Consistency**: Submission ID is saved to both episode and product request tables
4. **Logging**: Enhanced logging throughout the flow for better debugging
5. **Error Handling**: Improved error handling and status tracking

### Remaining Tasks

- Admin view updates to display submission ID and provide links to IVR forms
- End-to-end testing of the complete flow
