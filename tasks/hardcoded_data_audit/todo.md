# Hardcoded Data Audit

## Plan

1.  [x] Analyze frontend for hard-coded data.
2.  [x] Analyze backend for hard-coded data.
3.  [x] Analyze configuration files for hard-coded data.
4.  [x] Compile a report of findings.

## Findings

Here is a list of hard-coded data found in the codebase that should ideally be moved to the backend database for better manageability and scalability.

### 1. Orphaned Diagnosis Codes JSON

*   **File:** `resources/js/data/diagnosis-codes.json`
*   **Description:** This is a large JSON file containing medical diagnosis codes. It does not appear to be used anywhere in the frontend code. The application correctly fetches these codes from a backend API.
*   **Recommendation:** This file is unused and should be deleted to reduce code clutter and potential confusion.

### 2. Hardcoded Frontend Select Options

*   **Files:**
    *   `resources/js/Pages/Auth/ProviderInvitation.tsx`
    *   `resources/js/Pages/QuickRequest/Components/Step2PatientInsurance.tsx`
    *   `resources/js/Pages/Providers/CredentialManagement.tsx`
    *   `resources/js/Pages/Auth/OnboardingSteps/OrganizationStep.tsx`
    *   `resources/js/Pages/QuickRequest/Components/Step4ClinicalBilling.tsx`
    *   `resources/js/Components/ui/AddFacilityModal.tsx`
    *   `resources/js/Components/ui/AddProviderModal.tsx`
    *   And more...
*   **Description:** Numerous components contain hardcoded arrays for dropdown menus (select options). Examples include:
    *   `facilityTypes`
    *   `specialties`
    *   `placeOfServiceCodes`
    *   `planTypes`
    *   `credentialTypes`
    *   `organizationTypes`
    *   `woundLocations`
    *   `cptOptions`
*   **Recommendation:** These lists represent business data (e.g., medical specialties, types of facilities). They should be stored in database tables and fetched via an API. This allows administrators to manage the options without requiring code changes. The `ProviderInvitation.tsx` component already does this for `states`, providing a good pattern to follow.

### 3. Hardcoded Shipping Carriers

*   **File:** `resources/js/Components/Admin/TrackingManager.tsx`
*   **Description:** The `carriers` array contains a hardcoded list of shipping carriers and their tracking URLs.
*   **Recommendation:** This data should be moved to a `carriers` table in the database. This would allow an administrator to add, remove, or update carriers and their tracking URLs through an admin interface.

### 4. Hardcoded Manufacturer Configurations

*   **Files:** All files within the `config/manufacturers/` directory.
*   **Description:** Each manufacturer has a large, dedicated PHP configuration file that defines everything from their name to complex field mappings and business rules.
*   **Recommendation:** This is a major architectural concern. This entire system should be modeled in the database with tables for `manufacturers`, `manufacturer_field_mappings`, and `manufacturer_rules`. Moving this to the database would significantly improve scalability, maintainability (allowing non-devs to manage it), and data integrity.

## Review

The codebase contains several instances of hard-coded data that should be migrated to the database and served via a backend API. The most critical of these is the manufacturer configuration system, which is currently managed in PHP files. Addressing these findings would lead to a more flexible, scalable, and maintainable application. 