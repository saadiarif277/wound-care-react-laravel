# Product Selection and Form Improvements Task List

## Overview
Implement improvements to the product selection page including ASP sorting, size selection functionality, document type refinements, and comprehensive diagnosis code searchability from the provided data sources.

---

## Tasks

### 1. Product Selection Page - ASP Sorting ✅
**Files:** 
- `/app/Http/Controllers/ProductController.php`
- `/app/Http/Controllers/QuickRequestController.php`

**Tasks:**
- [x] Sort products by highest ASP (Average Sales Price) first
- [x] Verify ASP data is available in product data structure
- [x] Implement sorting logic in product display (`orderBy('price_per_sq_cm', 'desc')`)
- [x] Test sorting functionality with different ASP values

### 2. Size Selection Improvements ✅
**Files:**
- `/resources/js/Pages/QuickRequest/Components/ProductSelectorQuickRequest.tsx`

**Tasks:**
- [x] Add size selection dropdown when adding additional quantities
- [x] Implement size selection state management with SizeManager component
- [x] Update product quantity/size tracking in form data
- [x] Ensure size selection works for multiple products with grid layout
- [x] Test size selection workflow with individual quantity controls

### 3. Document Type Refinements ✅
**Files:**
- `/resources/js/types/document-upload.ts`

**Tasks:**
- [x] Update document types to only include: clinical notes, insurance card, demographics, other
- [x] Remove unnecessary document type options
- [x] Update document type validation and configurations
- [x] Test document upload with new types

### 4. Diagnosis Code Data Integration ✅
**Source Files:**
- `/docs/data-and-reference/Wound Care-Skin Subs Diagnosis Codes-one sheet.csv`
- `/docs/data-and-reference/icd-10-diagnosis-codes-for-traumatic-surgicial-articular-wounds.md`
- `/docs/data-and-reference/neat-list-of-csv-icd-10-codes.md`

**Tasks:**
- [x] Parse CSV data from wound care diagnosis codes
- [x] Parse markdown data from traumatic/surgical/articular wound codes
- [x] Create consolidated diagnosis codes database integration
- [x] Implement searchable diagnosis code field via API
- [x] Add database seeder for 921 unique diagnosis codes
- [x] Test search functionality with various code patterns

### 5. Diagnosis Code Component Implementation ✅
**Files:**
- `/resources/js/Components/DiagnosisCode/DiagnosisCodeSelector.tsx`
- `/resources/js/Pages/QuickRequest/Components/Step2PatientInsurance.tsx`
- `/resources/js/Pages/QuickRequest/Components/Step4ClinicalBilling.tsx`

**Tasks:**
- [x] Create searchable diagnosis code input component
- [x] Implement fuzzy search for codes and descriptions
- [x] Add dual-coding support for wound types that require two codes
- [x] Show wound type selection buttons with visual indicators
- [x] Implement validation for both primary and secondary codes
- [x] Update form data structure for dual diagnosis codes
- [x] Test diagnosis code selection workflow for both single and dual coding

### 6. Additional Improvements Completed ✅

#### CSRF Token Security Fix
- [x] Fixed CSRF token expiration causing redirects to Step 1
- [x] Added retry logic with up to 2 attempts for CSRF failures
- [x] Implemented automatic token refresh every 10 minutes
- [x] Added token refresh before step transitions and FHIR operations

#### Component Placement Optimization
- [x] Removed DiagnosisCodeSelector from Step 2 (Patient Insurance)
- [x] Kept DiagnosisCodeSelector only on Step 4 (Clinical Billing)
- [x] Added clear wound type selection buttons

#### Clinical Validation Enhancement
- [x] Fixed expected service date auto-populating with tomorrow's date
- [x] Changed to start empty, forcing manual date entry

#### Database Integration
- [x] Enhanced DiagnosisCodesFromCsvSeeder to parse markdown files
- [x] Successfully imported 921 unique diagnosis codes
- [x] Implemented proper dual-coding categorization:
  - Yellow codes: E codes (diabetes), I codes (venous conditions)
  - Orange codes: L97/L98 codes (wound locations)
  - Pressure ulcer codes: L89 codes (single coding)

---

## Implementation Details

### Product ASP Sorting
```php
// ProductController.php and QuickRequestController.php
$query->orderBy('price_per_sq_cm', 'desc'); // Highest ASP first
```

### Size Selection Data
```typescript
// SizeManager component with grid layout
<div className="grid grid-cols-4 gap-2">
  {sizes.map(size => (
    <SizeQuantityControl key={size} size={size} />
  ))}
</div>
```

### Document Types
```typescript
export type DocumentType = 'demographics' | 'insurance_card' | 'clinical_notes' | 'other';
```

### Diagnosis Codes Database Structure
```sql
-- 921 unique diagnosis codes imported
-- Categories: yellow, orange, pressure_ulcer, chronic_ulcer_generic
-- Proper dual-coding support for venous and diabetic ulcers
```

### Wound Type Selection
```typescript
// Four clear wound type buttons with visual indicators
const WOUND_CARE_GROUPS = {
  venous_leg_ulcer: { requiresDualCoding: true },
  diabetic_foot_ulcer: { requiresDualCoding: true },
  pressure_ulcer: { requiresDualCoding: false },
  chronic_skin_subs: { requiresDualCoding: false }
}
```

---

## Review

### Changes Made:
- [x] Products sorted by highest ASP first
- [x] Size selection added to product selection with grid layout
- [x] Document types simplified to 4 options
- [x] Comprehensive diagnosis code search implemented
- [x] All data from provided files integrated (921 codes)
- [x] CSRF token security issues resolved
- [x] Component placement optimized
- [x] Clinical validation enhanced
- [x] Database integration completed
- [x] Testing completed
- [x] No regressions identified

### Summary:
This comprehensive task successfully improved the user experience across multiple areas:

**🎯 Product Selection Enhancements:**
- Products now display in order of highest ASP first, helping users identify most valuable options
- Enhanced size selection with intuitive grid layout and individual quantity controls
- Streamlined document upload with just 4 clear categories

**🔍 Diagnosis Code System:**
- Complete database-driven system with 921 unique codes from all provided sources
- Intuitive wound type selection buttons with clear visual indicators
- Proper dual-coding support for venous leg ulcers and diabetic foot ulcers
- Single-coding support for pressure ulcers and chronic ulcers
- Searchable interface with API integration

**🔒 Security & Reliability:**
- Resolved critical CSRF token expiration issues
- Automatic token refresh and retry mechanisms
- Better error handling with user-friendly messages

**🎨 User Experience:**
- Removed diagnosis code selection from Step 2 to reduce complexity
- Concentrated all clinical information in Step 4
- Clear wound type buttons with dual/single coding indicators
- Fixed auto-populated dates to force manual entry

**📊 Technical Achievements:**
- Database integration replacing static JSON files
- Enhanced seeder processing complex markdown files
- Proper categorization with yellow (underlying conditions) and orange (wound locations) codes
- Comprehensive error handling and session management

The implementation maintains HIPAA compliance while significantly improving usability and data accuracy across the wound care application workflow. 🚀 