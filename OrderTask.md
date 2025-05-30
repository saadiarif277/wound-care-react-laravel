# E2E MVP Order Flow Task List

This document tracks the tasks for implementing the minimal MVP of the end-to-end ordering flow.

## Phase 1: Frontend Setup & Initial Component Alignment

### Completed
- [x] **Project Setup & Understanding:**
    - [x] Initial planning and conversation regarding E2E order flow MVP.
    - [x] Identified Laravel + Inertia.js stack.
- [x] **Frontend Adapters (`resources/js/services/integrations/`)**
    - [x] Created `FHIRAdapter.ts` placeholder.
    - [x] Created `ECWAdapter.ts` placeholder.
    - [x] Created `DocuSealService.ts` placeholder.
    - [x] Refined `FHIRAdapter.ts` to call Laravel backend APIs for Patient and Observation resources.
    - [x] Defined `PatientFormData` (now `PatientApiInput` in `Create.tsx`) and base FHIR types in `FHIRAdapter.ts`.
    - [x] Refined `ECWAdapter.ts` to call Laravel backend APIs and added `searchPatientsByName`.
    - [x] Refined `DocuSealService.ts` to call Laravel backend APIs and defined placeholder types.
- [x] **Backend API Endpoints (Laravel)**
    - [x] `EcwController.php` & `EcwFhirService.php`: Confirmed suitability of `searchPatients` and `getPatientConditions`.
    - [x] `FhirController.php` & `FhirService.php`: Modified `createPatient` to accept `PatientApiInput`-like structure, transform to FHIR, and call service.
    - [x] `FhirController.php` & `FhirService.php`: Added `searchObservations` methods.
    - [x] `routes/api.php`: Added `GET /api/v1/fhir/Observation` route.
- [x] **Product Request Parent Component (`resources/js/Pages/ProductRequest/Create.tsx`)**
    - [x] Identified `Create.tsx` as the main orchestrator for the multi-step form.
    - [x] Updated `PatientApiInput` interface (added optional `id`, `unknown` gender).
    - [x] Defined detailed `ClinicalAssessmentData` with `SSP_` prefixed interfaces for Skin Substitute Pre-application checklist sections.
    - [x] Removed redundant `updatePatientData` function and prop.
    - [x] Ensured correct import and usage of child step components.
    - [x] Removed unused inline/stubbed step components from the end of the file.
- [x] **Patient Information Step Component (`resources/js/Pages/ProductRequest/Components/PatientInformationStep.tsx`)**
    - [x] Refactored props to align with `Create.tsx` (`formData` and `updateFormData`).
    - [x] Updated data access to use `formData.patient_api_input` and `formData` directly.
    - [x] Aligned data update calls to use parent's `updateFormData` with the correct structure.
    - [x] Simplified/removed internal `PatientStepFormData` and `PatientDemographics` types.
    - [x] Re-integrated general wound characteristic fields (tissue type, exudate, infection signs) into the SSP wound description section.

### Linter Error Resolution (Frontend)
- [x] **`PatientInformationStep.tsx`**
    - [x] Defined and exported `Facility` and `WoundType` interfaces in `resources/js/types/index.d.ts`.
    - [x] Updated `PatientApiInput` (in `Create.tsx` and locally in `PatientInformationStep.tsx`) to have `member_id?: string` and `gender?: 'male' | 'female' | 'other' | 'unknown'` for compatibility with `PatientFormData` from `FHIRAdapter.ts`.
- [x] **`ECWAdapter.ts`**
    - [x] Removed local placeholder `PatientFormData` and imported the correct one from `FHIRAdapter.ts` to resolve type mismatch for `searchPatientsByName` return value.

**Persistent Linter Issues (Frontend):**
- `ClinicalAssessmentStep.tsx` has shown persistent linter errors related to type inference for `getSSPSectionCompletion` (property 'length' on 'never') and assignability to `Partial<SectionSpecificData>` in `renderAssessmentForm`. Multiple attempts made to resolve with type refinements and assertions. These are now deferred for deeper investigation to prioritize backend validation logic. The `any` cast in `getSSPSectionCompletion` and explicit casts in `renderAssessmentForm` are workarounds.

### Pending
- [ ] **Backend Validation for Clinical Assessment (`ValidationBuilderController.php`)**
    - [x] Added cases for `ssp_` sections to `validateClinicalSection` switch statement.
    - [x] Created initial shells for `validateSspDiagnosis`, `validateSspLabResults`, `validateSspWoundDescription`, `validateSspCirculation`, `validateSspConservativeMeasures`.
    - [x] `ssp_additional_clinical` section and validation method removed as fields merged into `labResults`.
    - [x] Refined `validateSspDiagnosis` with more detailed checks.
    - [x] Refined `validateSspLabResults` with more detailed checks.
    - [x] Refined `validateSspWoundDescription` with enum/array checks.
    - [x] Refined `validateSspCirculation` with conditional date checks.
    - [x] Refined `validateSspConservativeMeasures` with boolean and conditional checks.
    - [x] Updated `getRequiredFieldsForSection` for SSP sections.
    - [ ] **Further refine validation logic within all `validateSsp...` methods (e.g., numeric ranges, specific regex patterns, more complex cross-field dependencies based on checklist logic).**
- [ ] **FHIR Observation Mapping for Clinical Data**
    - [ ] Design mapping from `ClinicalAssessmentData` (especially SSP sections) to FHIR Observation resources.
    - [ ] Decide where mapping occurs (frontend/backend) and implement.
- [ ] **Product Selection Step Component (`resources/js/Pages/ProductRequest/Components/ProductSelectionStep.tsx`)**
    - [ ] Review integration with `Create.tsx`.
    - [ ] Ensure `formData.selected_products` is correctly populated.
    - [ ] Integrate with Product Recommendation Engine if applicable for MVP.
- [ ] **Validation & Eligibility Step Component (`resources/js/Pages/ProductRequest/Components/ValidationEligibilityStep.tsx`)**
    - [ ] Review integration with `Create.tsx`.
    - [ ] Ensure `formData.mac_validation_results`, `mac_validation_status`, `eligibility_results`, `eligibility_status` are correctly populated.
    - [ ] Implement calls to backend for MAC validation and eligibility checks.
- [ ] **Clinical Opportunities Step Component (`resources/js/Pages/ProductRequest/Components/ClinicalOpportunitiesStep.tsx`)**
    - [ ] Review integration with `Create.tsx`.
    - [ ] Ensure `formData.clinical_opportunities` is correctly populated.
    - [ ] Implement calls to Clinical Opportunity Engine if applicable for MVP.
- [ ] **Review & Submit Step Component (`resources/js/Pages/ProductRequest/Components/ReviewSubmitStep.tsx`)**
    - [ ] Implement component to display a summary of all collected `formData`.
    - [ ] Allow for `provider_notes` to be added to `formData`.
- [ ] **Form Submission (`resources/js/Pages/ProductRequest/Create.tsx`)**
    - [ ] Ensure `submitForm` function in `Create.tsx` posts the complete `formData` to the correct Laravel backend endpoint (e.g., `/api/v1/product-requests` or `/api/v1/orders`).
    - [ ] Create the backend controller and service method to handle this submission, store the initial order/request, and potentially trigger the next phase (e.g., admin review).

## Phase 2: Backend Processing & DocuSeal Integration

### Pending
- [ ] **Order Approval Workflow (Admin Portal)**
    - [ ] Design and implement UI for admins to review submitted orders.
    - [ ] Implement admin actions (approve, reject, request more info).
- [ ] **DocuSeal Document Generation (Backend)**
    - [ ] Implement logic to fetch necessary data (FHIR patient/clinical, Supabase order/customer) upon order approval.
    - [ ] Integrate with `DocuSealService.ts` (and its backend counterpart) to generate and pre-populate forms (Insurance Verification, Order Form, Onboarding Form) in the correct manufacturer folder.
- [ ] **DocuSeal Admin Review (Admin Portal)**
    - [ ] Implement UI for admins to review generated DocuSeal documents.
- [ ] **DocuSeal Provider Signature (Provider Portal & DocuSeal)**
    - [ ] Implement mechanism for admins to send documents to providers for signature via DocuSeal.
    - [ ] Handle DocuSeal webhooks for signature status updates.
- [ ] **Final Submission to Manufacturer (Admin Portal/Backend)**
    - [ ] Implement logic for final admin review (if any) and submission of signed documents to the manufacturer.

## Phase 3: Testing & Refinement

### Pending
- [ ] **Unit Tests**
    - [ ] Write unit tests for new frontend components and adapters.
    - [ ] Write unit tests for new backend services and controllers.
- [ ] **Integration Tests**
    - [ ] Test frontend-backend API integrations for each step.
    - [ ] Test integration with Azure FHIR.
    - [ ] Test integration with DocuSeal.
- [ ] **E2E Testing**
    - [ ] Perform complete end-to-end tests of the order flow from product request creation to (simulated) manufacturer submission.
- [ ] **UAT Readiness & Bug Fixing**
    - [ ] Address any issues found during testing.
    - [ ] Prepare for User Acceptance Testing. 
