# AI Coding Companion Task List

## Project: Medical IVR & Clinical Validation System Enhancement

### Overview
Enhance the medical intake system with improved IVR functionality, document handling, and clinical validation features. Integration points include DocuSeal API, Azure AI, and ICD-10 code management.

---

## Phase 1: Step 7 IVR Enhancements

### 1.1 Expected Service Date Improvements
**File:** `/resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx`

**Tasks:**
- Remove date pre-selection logic
- Implement MM/DD/YYYY placeholder text
- Add date format validation for DocuSeal compatibility
- Implement 24-hour warning for near-term service dates

**Technical Requirements:**
```javascript
// Date should validate to MM/DD/YYYY format
// Warning threshold: Date.now() + (24 * 60 * 60 * 1000)
```

### 1.2 Document Upload Component Migration
**Files to modify:**
- `/resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx` (remove lines 813-970)
- Clinical Validation step component (add DocumentUploadCard)
- `/resources/js/types/document-upload.ts` (potentially add wound_photo type)

**Document Types Configuration:**
- Face Sheet/Demographics
- Clinical Notes  
- Insurance Card (front/back)
- Wound Photo (if applicable)

### 1.3 Azure AI & DocuSeal Integration
**Integration Points:**
- Azure AI service for automatic field mapping
- DocuSeal API for form field rendering
- Data flow to IVR form generation

**API Reference:** https://www.docuseal.com/docs/api

### 1.4 Remove Attestation Requirement
- Remove attestation checkbox UI elements
- Update validation schema to exclude attestation checks

---

## Phase 2: Step 8 Clinical Validation Enhancements

### 2.1 Wound Type Button Implementation
**File:** `/resources/js/Pages/QuickRequest/Components/ClinicalValidation.tsx`

**New Wound Types:**
```typescript
const WOUND_TYPES = {
  TRAUMATIC: 'Traumatic Wounds',
  SURGICAL: 'Surgical Wounds',
  ARTICULAR: 'Articular Wounds',
  OTHER: 'Other/General Wounds'
}
```

### 2.2 ICD-10 Code Consolidation
**Source Files:**
- `/docs/data-and-reference/Wound Care-Skin Subs Diagnosis Codes-one sheet.csv`
- `/docs/data-and-reference/icd-10-diagnosis-codes-for-traumatic-surgicial-articular-wounds.md`

**Output:** `src/data/icd10.json`

**Data Structure:**
```json
[
  {
    "code": "L89.000",
    "description": "Pressure ulcer of unspecified elbow, unstageable"
  }
]
```

**Processing Requirements:**
- Parse both source files
- Deduplicate by code
- Sort alphabetically
- Export as JSON array

### 2.3 Auto-Complete Implementation
**Component Requirements:**
- Async search functionality
- Search by code OR description
- Display format: "CODE – Description"
- Result limit: 50 items
- Multi-select capability

**Suggested Library:** React Select with async loading

---

## Testing Checklist

### Step 7 Tests:
- [ ] Service date shows MM/DD/YYYY placeholder
- [ ] Date validation catches invalid formats
- [ ] 24-hour warning displays correctly
- [ ] DocumentUploadCard renders on Clinical Validation step
- [ ] All document types upload successfully
- [ ] Azure AI maps fields correctly
- [ ] DocuSeal API displays form fields
- [ ] No attestation requirements remain

### Step 8 Tests:
- [ ] All 4 wound type buttons render
- [ ] Button selection filters diagnosis codes
- [ ] ICD-10 JSON contains complete merged dataset
- [ ] Auto-complete searches both code and description
- [ ] Multiple diagnosis selections persist
- [ ] Wound type filtering works with auto-complete

---

## Key Integration Points

1. **DocuSeal API**
   - Form field fetching
   - Data submission
   - Field mapping validation

2. **Azure AI**
   - Document field extraction
   - Automatic mapping to form fields

3. **React Components**
   - DocumentUploadCard integration
   - Async select for ICD-10 codes
   - Wound type button group

---

## Development Notes

- Ensure all dates use MM/DD/YYYY format for DocuSeal compatibility
- Maintain existing wound type functionality while adding new types
- Preserve form state across step navigation
- Consider performance with large ICD-10 dataset (implement virtualization if needed)
- Test edge cases for document upload and AI mapping failures

---

## Review of Completed Changes

### Changes Made:

1. **Step7DocusealIVR.tsx Modifications**:
   - ✅ Removed automatic date assignment for `service_date` and `provider_signature_date`
   - ✅ Removed entire document upload section (lines 813-970)
   - ✅ Removed all clinical attestation checkboxes (lines 974-1081)
   - Result: Component is now focused solely on DocuSeal IVR form completion

2. **Step4ClinicalBilling.tsx Enhancements**:
   - ✅ Added DocumentUploadCard component import
   - ✅ Added document upload section at the end of the component
   - ✅ Configured handlers for face sheet, clinical notes, and wound photo uploads
   - ✅ Added insurance data extraction handler

3. **Document Type Updates**:
   - ✅ Added 'clinical_notes' and 'wound_photo' to DocumentType in document-upload.ts
   - ✅ Added configurations for both new document types in DOCUMENT_TYPE_CONFIGS

### Summary:
The IVR step has been successfully cleaned up to focus only on DocuSeal form completion. Document upload functionality has been moved to the clinical validation step (Step4ClinicalBilling) using the existing DocumentUploadCard component. The attestation requirements have been removed entirely from the IVR flow as requested.