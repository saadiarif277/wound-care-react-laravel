# Order Display Fix Task

## Problem Statement
Orders were not showing up on the Provider Dashboard despite successful Quick Request submissions. Investigation revealed that Order records were not being saved to the database due to multiple issues.

## Root Causes Identified
1. Missing 'notes' field in Order model's fillable array
2. Order model had 'notes' cast as JSON but OrderHandler was using json_encode
3. Episode cache warming was failing due to non-existent 'provider_fhir_id' column
4. The cache warming failure was causing the entire transaction to rollback

## Todo Items
- [x] Investigate why Order records are not being created by OrderHandler
- [x] Add detailed logging to QuickRequestOrchestrator to trace execution flow
- [x] Check if PHI audit exceptions are interrupting Order creation
- [ ] Verify correct model usage (Order vs ProductRequest vs QuickRequest)
- [ ] Fix model mismatch between creation and dashboard queries
- [x] Add 'notes' field to Order model fillable array
- [x] Fix OrderHandler to not use 'order_date' field that doesn't exist
- [x] Fix database transaction rollback issue preventing Order saves
- [x] Temporarily disable episode cache warming to prevent failures
- [ ] Test order creation flow to verify fixes
- [ ] Re-enable cache warming after fixing provider FHIR ID issue

## Changes Made

### 1. Order Model Updates
**File**: `app/Models/Order/Order.php`
- Added 'notes' to fillable array
- Added 'notes' => 'json' to casts array

### 2. OrderHandler Updates
**File**: `app/Services/QuickRequest/Handlers/OrderHandler.php`
- Removed json_encode from notes field (let model handle JSON casting)
- Added 'order_status' => 'Pending' to match database schema

### 3. EpisodeTemplateCacheService Fix
**File**: `app/Services/EpisodeTemplateCacheService.php`
- Commented out problematic provider_fhir_id query that was causing SQL errors

### 4. Temporarily Disabled Cache Warming
**File**: `app/Traits/UsesEpisodeCache.php`
- Commented out automatic cache warming on episode creation to prevent transaction rollbacks

### 5. Added Debugging
**File**: `app/Services/QuickRequest/QuickRequestOrchestrator.php`
- Added logging to trace order creation flow
- Added order count verification after commit

## Verification Steps
1. Submit a new Quick Request
2. Check if Order record is created in database
3. Verify Provider Dashboard shows the order
4. Verify Admin Order Center shows the request

## Future Work
1. Fix the provider_fhir_id issue properly by:
   - Either adding provider_fhir_id column to orders table
   - Or fetching provider FHIR ID from episode metadata
   - Or removing this requirement from cache service
2. Re-enable automatic cache warming
3. Consider unifying Order and ProductRequest models to avoid confusion

## Review Summary
The main issue was that the episode cache warming was trying to access a non-existent column 'provider_fhir_id' in the orders table. This caused an SQL error that rolled back the entire transaction, preventing orders from being saved. By temporarily disabling the cache warming and fixing the model configuration, orders should now be created successfully.

**Status**: Ready for testing