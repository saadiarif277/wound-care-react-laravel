# FHIR Operations Disabled - Documentation

## Overview
FHIR operations have been temporarily disabled to debug order saving issues. Orders were being created but not saved to the database due to transaction rollbacks potentially caused by FHIR API failures.

## Problem Statement
- Orders return success (e.g., order_id: 21) but are NOT saved to database
- Transaction rollback occurring somewhere in the process
- Need to isolate if FHIR operations are the cause

## Changes Made

### 1. OrderHandler.php (`app/Services/QuickRequest/Handlers/OrderHandler.php`)
- **Disabled**: `createDeviceRequest()` calls in both `createInitialOrder()` and `createFollowUpOrder()`
- **Replacement**: Using `'local-device-request-' . uniqid()`
- **Also Disabled**: FHIR DeviceRequest status updates in `updateOrderStatus()`

### 2. PatientHandler.php (`app/Services/QuickRequest/Handlers/PatientHandler.php`)
- **Disabled**: Entire `createOrUpdatePatient()` FHIR logic
- **Replacement**: Returns `'local-patient-' . uniqid()`

### 3. ProviderHandler.php (`app/Services/QuickRequest/Handlers/ProviderHandler.php`)
- **Disabled**: `createOrUpdateProvider()` FHIR logic
- **Replacement**: Returns `'local-provider-' . ($providerData['npi'] ?? uniqid())`
- **Also Disabled**: `createOrUpdateOrganization()` FHIR logic
- **Replacement**: Returns `'local-org-' . ($facilityData['npi'] ?? uniqid())`

### 4. ClinicalHandler.php (`app/Services/QuickRequest/Handlers/ClinicalHandler.php`)
- **Disabled**: Entire `createClinicalResources()` FHIR logic
- **Replacement**: Returns array with local IDs:
  - `condition_id`: `'local-condition-' . uniqid()`
  - `episode_of_care_id`: `'local-episodeofcare-' . uniqid()`
  - `encounter_id`: `'local-encounter-' . uniqid()` (if needed)
  - `task_id`: `'local-task-' . uniqid()`

### 5. InsuranceHandler.php (`app/Services/QuickRequest/Handlers/InsuranceHandler.php`)
- **Disabled**: `createCoverage()` FHIR logic
- **Replacement**: Returns `'local-coverage-' . uniqid()`

### 6. Other Changes
- **QuickRequestOrchestrator.php**: Added detailed exception logging with trace
- **EpisodeTemplateCacheService.php**: Commented out provider_fhir_id query (was causing SQL errors)
- **UsesEpisodeCache.php**: Disabled automatic cache warming on episode creation

## Re-enabling FHIR Operations

To re-enable FHIR operations, follow these steps:

### Step 1: OrderHandler.php
1. Remove the local device request ID generation and logging
2. Uncomment the original `$deviceRequestId = $this->createDeviceRequest(...)` calls
3. Uncomment the FHIR DeviceRequest update in `updateOrderStatus()`

### Step 2: PatientHandler.php
1. Remove the local patient ID generation code
2. Uncomment the entire original FHIR patient creation logic

### Step 3: ProviderHandler.php
1. In `createOrUpdateProvider()`: Remove local ID generation, uncomment original FHIR logic
2. In `createOrUpdateOrganization()`: Remove local ID generation, uncomment original FHIR logic

### Step 4: ClinicalHandler.php
1. Remove the local ID generation code
2. Uncomment the entire original FHIR clinical resource creation logic

### Step 5: InsuranceHandler.php
1. Remove the local coverage ID generation
2. Uncomment the original FHIR coverage creation logic

### Step 6: Re-enable Cache Warming
1. In `UsesEpisodeCache.php`: Uncomment `$episode->warmCache();` in the `bootUsesEpisodeCache()` method
2. In `EpisodeTemplateCacheService.php`: Fix the provider_fhir_id issue properly

## Testing Checklist
- [ ] Submit a test Quick Request
- [ ] Verify order is saved to database
- [ ] Check Provider Dashboard shows the order
- [ ] Check Admin Order Center shows the request
- [ ] Check Laravel logs for any errors
- [ ] Verify no 500 errors on polling endpoints

## Notes
- All original code has been preserved in comments marked with `/* ORIGINAL CODE - DISABLED`
- Local IDs follow pattern: `local-{resourceType}-{uniqueId}`
- This is a temporary debugging measure - not for production use