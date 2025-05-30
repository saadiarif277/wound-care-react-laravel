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
- [x] **Pre-Authorization Integration (NEW)**
    - [x] Created `AvailityServiceReviewsService.php` to integrate with Availity Service Reviews 2.0 API
    - [x] Added pre-authorization submission and status checking endpoints to `ProductRequestController`
    - [x] Updated `ValidationEligibilityStep.tsx` to handle pre-auth when required from eligibility
    - [x] Added automatic pre-auth submission with clinical data generation
    - [x] Implemented real-time status checking and updates
    - [x] Added database fields to track pre-authorization status
    - [x] Created relationship between ProductRequest and PreAuthorization models

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
    - [x] Added cases for `ssp_checklist_` prefixed UI section keys to `validateClinicalSection` switch statement.
    - [x] Updated `validateSspDiagnosis`, `validateSspLabResults`, `validateSspWoundDescription`, `validateSspCirculation`, `validateSspConservativeMeasures` methods to accept the full checklist data and validate fields based on the PHP DTO (`SkinSubstituteChecklistInput`) structure.
    - [x] Removed `ssp_additional_clinical` validation method and case (fields merged into `validateSspLabResults`).
    - [x] Refined validation logic within SSP methods for required fields, formats (date, numeric, boolean), and enums based on checklist and DTO.
    - [x] Corrected PHP syntax errors from unescaped quotes in error messages.
    - [x] Updated `getRequiredFieldsForSection` for SSP UI section keys.
    - [ ] **Further refinement/testing of validation logic within all `validateSsp...` methods is ongoing (e.g., more complex cross-field dependencies, specific value ranges not yet implemented).**
- [ ] **FHIR Observation Mapping for Clinical Data (Backend PHP Service)**
    - [x] Port logic from TypeScript `SkinSubstituteChecklistMapper.mapToFhirBundle` to a new PHP service (`App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService`).
    - [x] This service will take the PHP `SkinSubstituteChecklistInput` DTO and generate a FHIR Bundle array.
    - [x] Ensure `AzureFhirClient.php` (PHP) is used by this service to submit the bundle.
    - [ ] Define necessary custom FHIR extensions (StructureDefinitions) if not already in place on the FHIR server.
- [ ] **Frontend: `Create.tsx` Form Submission**
    - [ ] Implement the `submitForm` function in `Create.tsx`.
    - [ ] It should gather all `FormData` (including `patient_api_input` and `clinical_data` as `SkinSubstituteChecklistInput`).
    - [ ] Determine `orderId` to submit to (may need to create a draft order first or have it passed in).
    - [ ] POST the `clinical_data` (as `SkinSubstituteChecklistInput`) to the new Laravel backend API endpoint (e.g., `/api/v1/orders/{orderId}/checklist` handled by `ChecklistController.php`).
- [ ] **Backend: `ChecklistController.php` and `SkinSubstituteChecklistRequest.php**
    - [ ] Create `App\Http\Requests\SkinSubstituteChecklistRequest.php` to validate incoming raw checklist data against the `SkinSubstituteChecklistInput` DTO structure (required fields, types, enums).
    - [ ] Implement `App\Http\Controllers\Api\V1\Orders\ChecklistController@store` method:
        - [ ] Authorize request.
        - [ ] Use `SkinSubstituteChecklistRequest` for initial validation & DTO creation.
        - [ ] Call a PHP `ChecklistValidationService` (ported from TS version) to perform business logic/MAC validation on the DTO.
        - [ ] If valid, call the PHP `SkinSubstituteChecklistService` to generate FHIR bundle and submit to Azure.
        - [ ] Update local Supabase `Order` with `azure_order_checklist_fhir_id` and status.
- [ ] **Frontend: Remaining Steps Integration**
    - [x] `ProductSelectionStep.tsx`: Integrate with `Create.tsx` and `formData.selected_products`.
    - [x] `ValidationEligibilityStep.tsx`: Integrate, populate `formData` validation/eligibility fields. Implement backend calls.
    - [x] `ClinicalOpportunitiesStep.tsx`: Integrate, populate `formData.clinical_opportunities`. Implement backend calls.
    - [x] `ReviewSubmitStep.tsx`: Display summary of `FormData` (including `SkinSubstituteChecklistInput`).

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
