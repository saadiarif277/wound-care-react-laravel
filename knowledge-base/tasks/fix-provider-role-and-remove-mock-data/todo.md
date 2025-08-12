# Fix Provider Role Detection and Remove Mock Data

## Problem Statement
1. Providers were being shown as "User" in the sidebar instead of "Healthcare Provider"
2. Providers were still seeing admin controls despite role-based restrictions
3. Order details page contained extensive mock data instead of using real data from the database

## Root Causes
1. ProductRequestController was checking for `hasRole('Provider')` but the database uses lowercase role slugs like `'provider'`
2. This caused providers to fall through to the Admin role, giving them admin privileges
3. OrderDetails component had hardcoded mock data throughout

## Todo List
- [x] Update IVRDocumentSection to hide status dropdowns and upload buttons for non-admin users
- [x] Remove mock documents from AdditionalDocumentsSection
- [x] Pass userRole prop to IVRDocumentSection from OrderDetails
- [x] Fix role checks in ProductRequestController to use lowercase role slugs
- [x] Remove mock patient data from OrderDetails and use real FHIR data
- [x] Remove mock product data from OrderDetails and use real order data
- [x] Remove mock clinical data from OrderDetails and use real submission data
- [x] Remove mock provider data from OrderDetails and use real provider data
- [x] Update getEnhancedOrderDetails to return comprehensive data structure

## Changes Made

### 1. Fixed Role Detection (ProductRequestController.php)
- Changed `hasRole('Provider')` to `hasRole('provider')` on lines 345 and 467
- Changed `hasRole('Office Manager')` to `hasRole('office-manager')` on lines 350 and 469
- This ensures proper role detection matches the database role slugs

### 2. Updated IVRDocumentSection.tsx
- Added `userRole` prop to the interface
- Wrapped admin-only controls (status dropdowns, upload buttons, delete buttons) in conditional rendering
- Only users with `userRole === 'Admin'` can now see and interact with these controls

### 3. Removed Mock Data from AdditionalDocumentsSection.tsx
- Removed entire mockDocuments array (lines 83-134)
- Changed `const displayDocuments = documents.length > 0 ? documents : mockDocuments;` to `const displayDocuments = documents;`
- Now only shows real documents passed via props

### 4. Updated OrderDetails.tsx
- Removed all hardcoded mock data
- Updated orderData structure to use enhancedOrderData from API
- All fields now use real data with 'N/A' fallbacks when data is missing
- Patient data comes from FHIR or submission data
- Product data comes from actual order products
- Clinical data comes from submission data
- Provider data comes from actual provider records

### 5. Enhanced OrderCenterController.php
- Updated `getEnhancedOrderDetails` method to return comprehensive data
- Fetches patient data from FHIR service when available
- Extracts product details from order relationships
- Parses submission_data JSON for clinical information, forms, and submission flags
- Returns properly structured data that matches OrderDetails component expectations

## Review

The implementation successfully addresses all identified issues:

1. **Role Detection Fixed**: Providers are now correctly identified using lowercase role slugs, which will show them as "Healthcare Provider" in the sidebar and apply proper permissions.

2. **Admin Controls Hidden**: IVR status changes and document uploads are now restricted to Admin users only. Providers and Office Managers cannot see or interact with these controls.

3. **Mock Data Removed**: All hardcoded mock data has been removed from OrderDetails. The component now uses real data from the database via the enhanced-details API endpoint.

4. **Data Integration**: The enhanced order details endpoint now properly integrates data from multiple sources:
   - FHIR service for patient demographics
   - Product relationships for item details
   - Submission data for clinical information
   - Provider records for NPI and credentials

The changes maintain backward compatibility while ensuring that only real, authorized data is displayed to users based on their roles.