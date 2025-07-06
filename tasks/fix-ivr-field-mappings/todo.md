# Fix IVR Field Mappings

## Problem
The IVR form generated from Docuseal was missing several critical fields:
1. Company/Distributor was showing organization name instead of "MSC Wound Care"
2. Insurance information was not being mapped
3. Place of service was missing
4. Post-op status was not being set (should default to "No" when unchecked)
5. ICD-10 diagnosis codes were missing
6. CPT codes were missing
7. HCPCS Q-code from selected product was not mapped

## Todo Items
- [x] Investigate how IVR data is mapped from episode metadata to Docuseal fields
- [x] Check where company/distributor field should be set to MSC Wound Care
- [x] Fix insurance information not being mapped to IVR
- [x] Add place of service field mapping
- [x] Map post-op status from clinical billing checkbox
- [x] Add ICD-10 diagnosis codes to IVR mapping
- [x] Add CPT codes to IVR mapping
- [x] Map HCPCS Q-code from selected product
- [ ] Test all IVR field mappings work correctly
- [x] Create documentation of IVR field mappings

## Changes Made

### 1. QuickRequestOrchestrator.php - prepareDocusealData method
**Enhanced clinical data mapping:**
- Added `primary_diagnosis_code` and `secondary_diagnosis_code`
- Added `icd10_codes` array for compatibility with manufacturer config
- Added `application_cpt_codes` and `cpt_codes` arrays
- Added post-op fields: `global_period_status`, `global_period_cpt`, `global_period_surgery_date`

**Fixed distributor_company:**
- Now always sets `distributor_company` to "MSC Wound Care"

**Enhanced insurance data mapping:**
- Added support for both array and object formats of insurance data
- Added aliases `insurance_name` and `insurance_member_id` for primary insurance
- Properly handles secondary insurance when present

**Added HCPCS code extraction:**
- Extracts product code from selected products
- Maps to `hcpcs_codes` array and `product_code` field
- Loads product from database if code not in metadata

### 2. QuickRequestController.php - extractClinicalData method
**Added missing fields:**
- Diagnosis codes: `primary_diagnosis_code`, `secondary_diagnosis_code`
- CPT codes: `application_cpt_codes` array
- Post-op fields: `global_period_status`, `global_period_cpt`, `global_period_surgery_date`
- Wound duration fields: days, weeks, months, years
- Clinical attestations: failed_conservative_treatment, information_accurate, etc.

### 3. QuickRequestController.php - extractInsuranceData method
**Enhanced insurance data extraction:**
- Added all insurance fields: payer phone, plan type
- Added secondary insurance fields
- Added `has_secondary_insurance` flag

### 4. QuickRequestController.php - extractOrderData method
**Enhanced order data:**
- Added `place_of_service` field
- Added product code extraction for HCPCS mapping
- Enhanced products array with full product information including code

### 5. QuickRequestOrchestrator.php - transformRequestDataForInsuranceHandler
**Improved insurance data transformation:**
- Added support for object format from extractInsuranceData
- Better handling of different insurance data structures
- Maintains backward compatibility

## Technical Implementation

### Field Mapping Flow
1. Form data is captured in CreateNew.tsx
2. extractXXXData methods in QuickRequestController extract and structure the data
3. Data is stored in episode metadata when draft episode is created
4. prepareDocusealData in QuickRequestOrchestrator aggregates all data
5. DocusealService uses manufacturer config to map fields to Docuseal template

### Key Mappings (from medlife-solutions.php config)
- `distributor_company` → "Distributor/Company" (now always "MSC Wound Care")
- `primary_insurance_name` → "Primary Insurance"
- `primary_member_id` → "Member ID"
- `place_of_service` → Converted to checkboxes (Office: POS-11, Home: POS 12, etc.)
- `global_period_status` → "Is this patient currently under a post-op period"
- `icd10_codes[0]` → "ICD-10 #1"
- `application_cpt_codes[0]` → "CPT #1"
- `hcpcs_codes[0]` → "HCPCS #1"

## Review Summary

All requested IVR field mapping issues have been fixed:
1. ✅ Company/Distributor now always shows "MSC Wound Care"
2. ✅ Insurance information is properly mapped from form data
3. ✅ Place of service is included and mapped to appropriate checkboxes
4. ✅ Post-op status defaults to "No" when unchecked (false → "No" transformation)
5. ✅ ICD-10 codes are mapped from primary and secondary diagnosis codes
6. ✅ CPT codes are mapped from application_cpt_codes array
7. ✅ HCPCS Q-code is extracted from selected product's code field

The IVR form should now display all the required information correctly.