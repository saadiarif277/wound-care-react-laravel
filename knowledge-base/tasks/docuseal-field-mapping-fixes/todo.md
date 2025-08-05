# DocuSeal Field Mapping Fixes

## Problem Summary
1. DocuSeal was throwing "Unknown field: patient_first_name" error for non-MedLife manufacturers
2. Excessive console logging and re-rendering in React components

## Todo List
- [x] Check all manufacturer configs for patient_name field mapping
- [x] Update manufacturer configs missing patient_name in docuseal_field_names
- [x] Ensure all manufacturer configs have proper patient_name computation in fields section
- [x] Fix excessive re-rendering in Step7DocusealIVR component
- [ ] Test all manufacturers for DocuSeal submission

## Changes Made

### 1. Fixed Manufacturer Configurations

#### BioWound Solutions (`config/manufacturers/biowound-solutions.php`)
- Added missing field definitions for `patient_first_name` and `patient_last_name`
- These fields were referenced in the computation but not defined

#### Centurion Therapeutics (`config/manufacturers/centurion-therapeutics.php`)
- Added missing field definitions for `patient_first_name` and `patient_last_name`
- Fixed computation to include space between names: `patient_first_name + " " + patient_last_name`

#### MedLife Solutions (`config/manufacturers/medlife-solutions.php`)
- Added missing field definitions for `patient_first_name` and `patient_last_name`
- Fixed computation to include space between names

#### ACZ Associates (`config/manufacturers/acz-associates.php`)
- Fixed computation to include space between names (fields were already defined)

#### Advanced Solution (`config/manufacturers/advanced-solution.php`)
- Added missing field definitions for `patient_first_name` and `patient_last_name`

#### Advanced Health (`config/manufacturers/advanced-health.php`)
- Added basic docuseal_field_names mappings (was empty)
- Added complete field definitions including patient fields
- Note: Still needs template ID to be functional

### 2. Enhanced Field Mapping Service (`app/Services/UnifiedFieldMappingService.php`)
- Added fallback logic in `convertToDocusealFields` to compute `patient_name` from:
  - `patient_first_name` + `patient_last_name` (snake_case)
  - `patientFirstName` + `patientLastName` (camelCase)

### 3. Enhanced DocuSeal Service (`app/Services/DocusealService.php`)
- Added logging to track field mapping process
- Added fallback logic in `prepareFieldsForDocuseal` to compute patient_name
- Enhanced error logging to help debug field mapping issues

### 4. Fixed React Component Performance

#### Step7DocusealIVR.tsx
- Memoized `selectedProduct` calculation using `useMemo`
- Memoized `templateId` calculation using `useMemo`
- Memoized `manufacturerConfig` calculation using `useMemo`
- Removed excessive console.log statements from component body
- Fixed useEffect dependency array to include all dependencies

#### ProductSelectorQuickRequest.tsx
- Removed console.log from QuickRequestProductCard component

#### CreateNew.tsx
- Removed debug console.log statements from validation function

## Review

The root cause of the "Unknown field: patient_first_name" error was that several manufacturer configurations were using `patient_first_name` and `patient_last_name` in their computations but these fields were not defined in the fields array. The field mapping service was trying to access these undefined fields, causing the error.

The fixes ensure that:
1. All manufacturer configs that compute `patient_name` have the source fields defined
2. The field mapping service has fallback logic to handle different field naming conventions
3. React components are optimized to prevent excessive re-rendering and console spam

The changes are minimal and focused on configuration fixes rather than complex code changes, following the simplicity principle.

## Next Steps
- Test all manufacturers to ensure DocuSeal submissions work correctly
- Monitor for any remaining field mapping errors
- Add template IDs for manufacturers that are missing them (Advanced Health, BioWerX, etc.)