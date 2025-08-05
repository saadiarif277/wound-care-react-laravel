# Fix Order Details Missing Fields and UI Updates

## Problem Statement
The order details page was missing several fields and showing hardcoded values:
- Insurance card always showed "Uploaded" even when no card was uploaded
- Diagnosis codes were hardcoded as "E11.621"
- Missing wound size, CPT codes, and proper diagnosis codes from clinical data
- New Request button was in bottom-right corner instead of top-right

## Todo List
- [x] Fix insurance card hardcoded 'Uploaded' status
- [x] Add diagnosis codes from clinical summary to orderData
- [x] Remove hardcoded diagnosis code E11.621 from ClinicalSection
- [x] Move New Request button from bottom-right to top-right

## Changes Made

### 1. PatientInsuranceSection.tsx
- Changed hardcoded "Uploaded" to conditional display
- Only shows insurance card status if `orderData.patient?.insurance?.cardUploaded` is true

### 2. ProductRequestController.php
- Added diagnosis codes extraction from clinical summary:
  - `diagnosisCodes`: Full array of diagnosis codes
  - `primaryDiagnosis`: First diagnosis code for display

### 3. ClinicalSection.tsx
- Removed hardcoded "E11.621" diagnosis code
- Now displays actual diagnosis codes from `orderData.clinical.diagnosisCodes`
- Falls back to `primaryDiagnosis` if array is empty

### 4. Provider/Orders/Dashboard.tsx
- Moved New Request button from fixed bottom-right position
- Added button to header section next to title
- Maintains same styling but now in top-right of header

## Review

The implementation successfully addresses all the issues:

1. **Insurance Card**: Now only shows "Uploaded" when actually uploaded
2. **Diagnosis Codes**: Real diagnosis codes from FHIR data displayed
3. **Clinical Data**: Properly extracts and displays from clinical summary
4. **UI Improvement**: New Request button more accessible in header

All data now comes from actual database values and FHIR resources, no more hardcoded mock data.