# Code Cleanup Recommendations

## Service Layer

### 1. Duplicate Eligibility Services
**Files:**  
- `app/Services/Eligibility/UnifiedEligibilityService.php`  
- `app/Services/Insurance/UnifiedEligibilityService.php`  

**Issue:**  
Two different implementations of eligibility checking with overlapping functionality:
- Eligibility version integrates with FHIR auditing and uses provider-specific logic
- Insurance version focuses on product requests/orders with caching
- Both implement core eligibility check functionality

**Recommendation:**  
- Consolidate into single `UnifiedEligibilityService` in `app/Services/Eligibility/`
- Adopt the FHIR audit logging from the Eligibility version
- Incorporate caching mechanism from Insurance version
- Create unified request/response DTOs for all eligibility checks

### 2. Deprecated CMS Coverage Implementation
**Files:**  
- `app/Services/CmsCoverageApiSampleData.php`  

**Issue:**  
Appears to be a legacy implementation for mock data generation. Not referenced in any controllers or other services based on initial search.

**Recommendation:**  
- Confirm if used in testing/development
- Either move to `tests/Support/` or remove entirely

### 3. Duplicate Provider Interfaces
**Files:**  
- `app/Services/Eligibility/Providers/EligibilityProviderInterface.php`  
- `app/Services/Insurance/EligibilityProviderInterface.php`  

**Issue:**  
Duplicate interface definitions in different namespaces causing potential implementation conflicts.

**Recommendation:**  
- Create `app/Contracts/Eligibility/EligibilityProviderInterface.php`
- Update all implementations to reference the unified interface
- Remove duplicate files

## Model Layer

### 1. Unused PatientManufacturerIVREpisode Model
**File:**  
- `app/Models/PatientManufacturerIVREpisode.php`  

**Issue:**  
No references found in migrations, controllers, or services. Appears to be legacy from previous IVR system.

**Recommendation:**  
- Confirm with team if historical data needs preservation
- Remove model and create migration to drop table if unused

## Frontend

### 1. Unused Step Components
**Files:**  
- `resources/js/Pages/QuickRequest/Components/Step3ProductSelection.tsx`  
- `resources/js/Pages/QuickRequest/Components/Step5ClinicalBilling.tsx`  

**Issue:**  
Not imported in any route files or parent components based on initial search.

**Recommendation:**  
- Verify with recent design changes
- Remove unused components if confirmed obsolete
