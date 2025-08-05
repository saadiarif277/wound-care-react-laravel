# Clinical Validation Enhancements Task List

## Overview
Complete the remaining Phase 2 enhancements from the Step 7 IVR improvements, focusing on clinical validation enhancements including new wound type buttons, expected service date improvements, and ICD-10 code consolidation.

---

## Tasks

### 1. Add New Wound Type Buttons
**File:** `/resources/js/Components/DiagnosisCode/DiagnosisCodeSelector.tsx`

**New Wound Types to Add:**
- [x] Traumatic Wounds
- [x] Surgical Wounds
- [x] Articular Wounds
- [x] Other/General Wounds

**Implementation:**
- Add to the `WOUND_TYPES` array
- Set `requiresDualCoding: false` for all new types
- Ensure proper category mapping for diagnosis code filtering

### 2. Expected Service Date Improvements
**File:** `/resources/js/Pages/QuickRequest/Components/Step2PatientInsurance.tsx`

**Tasks:**
- [x] Add placeholder="MM/DD/YYYY" to the date input
- [x] Implement date format validation - Fixed by removing auto-populated tomorrow's date
- [x] Add 24-hour warning for service dates within next 24 hours
- [x] Ensure no automatic date pre-population

### 3. ICD-10 Code Consolidation (Optional)
**Source Files:**
- `/docs/data-and-reference/Wound Care-Skin Subs Diagnosis Codes-one sheet.csv`
- `/docs/data-and-reference/icd-10-diagnosis-codes-for-traumatic-surgicial-articular-wounds.md`

**Tasks:**
- [ ] Check if consolidation is needed based on current implementation
- [ ] If needed, parse both source files
- [ ] Create consolidated JSON at `src/data/icd10.json`
- [ ] Implement auto-complete functionality

---

## Implementation Steps

1. **Update DiagnosisCodeSelector Component**
   - Add the 4 new wound types to WOUND_TYPES array
   - Test wound type selection and filtering

2. **Update Expected Service Date Field**
   - Add placeholder text
   - Add validation logic
   - Implement 24-hour warning UI

3. **Test All Changes**
   - Verify new wound types appear and function correctly
   - Test date validation and warnings
   - Ensure no regressions in existing functionality

---

## Review

### Changes Made:
- [x] DiagnosisCodeSelector updated with new wound types
- [x] Expected service date field enhanced with placeholder
- [x] 24-hour warning implemented
- [x] Date format validation - Resolved by removing auto-populated date
- [ ] All tests passing
- [ ] No regressions identified

### Summary:
Successfully added 4 new wound types to the DiagnosisCodeSelector component:
- Traumatic Wounds
- Surgical Wounds
- Articular Wounds
- Other/General Wounds

Enhanced the Expected Service Date field with:
- MM/DD/YYYY placeholder text
- 24-hour warning that displays "Service date is within 24 hours, contact Administration before placing."
- Maintained existing 2 PM CST warning for next-day orders

The date format validation issue has been resolved by removing the auto-populated tomorrow's date - the expected_service_date field now starts empty, forcing users to manually enter the date. The ICD-10 code consolidation was marked as optional and not implemented in this phase.

### Additional Changes Made:
- Fixed Select dropdown background to be opaque (bg-white dark:bg-gray-800) instead of transparent
- Updated DocumentUploadCard to show "Please select a document type" by default instead of auto-selecting "demographics"
- Updated wound location dropdown to show placeholder text
- Updated place of service dropdown to not default to "11 - Office" and show placeholder instead
- Only show upload areas in DocumentUploadCard after a document type is selected