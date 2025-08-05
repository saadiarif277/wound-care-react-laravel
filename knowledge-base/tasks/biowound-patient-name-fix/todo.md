# Fix BioWound Solutions Patient Name Field Mapping

## Problem
The BioWound Solutions manufacturer configuration has a `patient_name` field that computes using `patient_first_name` and `patient_last_name`, but these source fields are not mapped in the configuration. This causes the error "Unknown field: patient_first_name" when creating IVR submissions.

## Root Cause
In `/config/manufacturers/biowound-solutions.php`:
- Line 409: `patient_name` computation references `patient_first_name + " " + patient_last_name`
- But there are no field mappings for `patient_first_name` or `patient_last_name`
- The system cannot find these fields in the source data, causing the error

## Solution Plan

### TODO:
- [ ] Add field mappings for `patient_first_name` and `patient_last_name` in the BioWound Solutions configuration
- [ ] Ensure these fields map correctly to the source data structure
- [ ] Test the fix with a BioWound Solutions IVR submission
- [ ] Verify other manufacturers don't have similar issues

### Implementation Steps:
1. Edit `/config/manufacturers/biowound-solutions.php`
2. Add field mappings before the `patient_name` field:
   ```php
   'patient_first_name' => [
       'source' => 'patient_first_name || patient.first_name || patient_data.first_name',
       'required' => true,
       'type' => 'string'
   ],
   'patient_last_name' => [
       'source' => 'patient_last_name || patient.last_name || patient_data.last_name',
       'required' => true,
       'type' => 'string'
   ],
   ```
3. Keep the existing `patient_name` computation as is
4. Test the configuration

## Notes
- MedLife Solutions works because it likely has proper field mappings or doesn't use computed patient names
- This is a configuration issue, not a code issue
- The fix is simple and low-risk