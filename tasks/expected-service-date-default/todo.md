# Expected Service Date Default Value Fix

## Problem
The `expected_service_date` field in the QuickRequest form is not being set to a default value when the form loads. Currently it's initialized as an empty string, which causes issues when the form is submitted.

## Root Cause
The form is initialized with `expected_service_date: ''` but there's no useEffect to set a default value when the component mounts.

## Solution
Add a useEffect hook to set the default `expected_service_date` to tomorrow's date when the component mounts.

## Tasks

- [x] Add useEffect to set default expected_service_date in CreateNew.tsx
- [x] Test that the form loads with tomorrow's date pre-filled
- [x] Test that the date can still be changed by the user
- [x] Verify the date is properly submitted to the backend
- [x] Update todo with results

## Implementation Details

The fix should:
1. Add a useEffect that runs once when component mounts
2. Set `expected_service_date` to tomorrow's date using the existing `getTomorrowDate()` function
3. Only set the default if the field is currently empty

## Testing Steps

1. Load the QuickRequest form
2. Verify that the expected service date field shows tomorrow's date by default
3. Change the date to a different value
4. Submit the form and verify the selected date is saved correctly

## Review

### Changes Made
- Added useEffect to set default expected_service_date in CreateNew.tsx
- The useEffect runs once when component mounts and sets the date to tomorrow if it's empty
- Created test scripts to verify the fix works correctly

### Results
- Form now loads with tomorrow's date pre-filled in the expected service date field
- Users can still change the date as needed
- The selected date is properly submitted to the backend
- No more "empty string" issues with the expected_service_date field
- Both QuickRequestSubmission and Order models accept the expected_service_date field correctly
- The fix prevents the "Invalid datetime format" error that was occurring with empty strings 
