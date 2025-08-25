# Facility Provider Filtering Fix

## Problem
The facility selection in Step2PatientInsurance shows all facilities that the current user has access to, instead of only showing facilities that the selected provider has access to.

## Root Cause
The `getFacilitiesForUser` method in `QuickRequestService` returns all user-accessible facilities without considering the provider-facility relationship. The frontend component doesn't filter facilities based on the selected provider.

## Solution
Modify the `Step2PatientInsurance` component to filter facilities based on the selected provider using the existing `facility_user` pivot table relationship.

## Tasks

- [x] Create task folder structure
- [x] Analyze current facility data structure
- [x] Modify Step2PatientInsurance component to filter facilities by provider
- [x] Add state management for filtered facilities
- [x] Reset facility selection when provider changes
- [x] Add error handling for no available facilities
- [ ] Test the filtering functionality
- [x] Update documentation

## Implementation Details

### Current Data Flow
1. `QuickRequestController::create()` calls `QuickRequestService::getFormData()`
2. `getFormData()` calls `getFacilitiesForUser()` which returns all user-accessible facilities
3. Facilities are passed to `Step2PatientInsurance` component as props
4. Component shows all facilities without filtering by selected provider

### Required Changes
1. **Frontend Filtering**: Filter facilities in the component based on selected provider
2. **State Management**: Track filtered facilities separately from all facilities
3. **Provider Change Handling**: Update filtered facilities when provider selection changes
4. **Facility Reset**: Clear facility selection when provider changes

### Database Relationship
- `facility_user` pivot table links users (providers) to facilities
- `User::facilities()` relationship provides provider's accessible facilities
- Need to use this relationship to filter facilities per provider

## Files to Modify
- `resources/js/Pages/QuickRequest/Components/Step2PatientInsurance.tsx`

## Testing
- [ ] Test with user who has access to multiple facilities
- [ ] Test with user who has access to only one facility
- [ ] Test provider change functionality
- [ ] Test facility reset when provider changes
- [ ] Test error handling for no available facilities

## Review
- [x] Code review completed
- [ ] Testing completed
- [x] Documentation updated

## Summary of Changes Made

### 1. Backend API Implementation
- **Created `ProviderFacilityController`**: New API endpoint `/api/v1/providers/{providerId}/facilities`
- **Database integration**: Uses the existing `facility_user` pivot table to get provider-specific facilities
- **Proper error handling**: Returns appropriate HTTP status codes and error messages
- **Logging**: Comprehensive logging for debugging and monitoring

### 2. Frontend Component Enhancement
- **Added `filteredFacilities` state**: Tracks facilities available for the selected provider
- **Real-time API calls**: Fetches provider facilities when provider selection changes
- **Loading states**: Shows loading indicators while fetching facilities
- **Error handling**: Graceful fallback to all facilities if API fails

### 3. Enhanced Provider-Facility Relationship
- **Dynamic filtering**: Facilities are filtered in real-time based on selected provider
- **Automatic facility reset**: Facility selection clears when provider changes
- **Provider-specific data**: Only shows facilities that the selected provider actually has access to

### 4. Current Implementation Status
- **✅ API endpoint created**: New `ProviderFacilityController` with `/api/v1/providers/{providerId}/facilities` endpoint
- **✅ Frontend integration**: Component now fetches provider-specific facilities from the API
- **✅ Real-time filtering**: Facilities are dynamically loaded when provider selection changes
- **✅ Loading states**: Proper loading indicators and error handling
- **✅ Facility reset**: Facility selection automatically resets when provider changes
- **✅ Fallback handling**: Falls back to showing all facilities if API fails

### 5. Future Enhancement Opportunities
- **API-based filtering**: Could implement backend API to get provider-specific facilities from `facility_user` pivot table
- **Real-time validation**: Could add real-time validation of provider-facility relationships
- **Caching**: Could implement caching for provider-facility relationships to improve performance

## Files Modified
- `resources/js/Pages/QuickRequest/Components/Step2PatientInsurance.tsx`
- `app/Http/Controllers/Api/V1/ProviderFacilityController.php` (new file)
- `routes/api.php` (added new route)

## Testing Notes
- Test with different user roles (provider, office-manager, admin)
- Test provider change functionality
- Test facility reset when provider changes
- Test error handling for no available facilities
- Verify that facilities are properly filtered based on selected provider
