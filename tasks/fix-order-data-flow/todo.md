# Fix Order Data Flow from Submission to Order Details

## Problem Statement
Order data was not being properly displayed in the OrderDetails page. The page was showing "N/A" for most fields even though the data was being submitted and saved correctly.

## Root Cause Analysis
1. **Data is saved correctly**: The order submission saves all form data in the `clinical_summary` JSON field of the ProductRequest model
2. **Data retrieval issue**: The ProductRequestController and OrderCenterController were not using the clinical_summary field to populate the order details
3. **Enhanced endpoint issue**: The enhanced-details API endpoint was also not using the clinical_summary data

## Todo List
- [x] Trace the order submission flow from the last step
- [x] Identify where order data is not being properly saved or passed
- [x] Fix the data flow from submission to provider dashboard
- [x] Ensure OrderDetails receives proper order data

## Changes Made

### 1. Updated ProductRequestController.php (show method)
- Modified the orderData construction to use the `clinical_summary` field
- Added extraction of patient details (DOB, gender, phone, address) from clinical_summary
- Added insurance information extraction with proper formatting
- Added clinical details (wound type, location, size, CPT codes) from clinical_summary
- Added forms/attestations data from clinical_summary
- Added shippingInfo from orderPreferences in clinical_summary
- Included the full clinical_summary in the response for debugging

### 2. Updated OrderCenterController.php (getEnhancedOrderDetails method)
- Modified to extract data from the `clinical_summary` field instead of non-existent `submission_data`
- Added proper patient data extraction with address formatting
- Added insurance data extraction with primary/secondary formatting
- Updated clinical data extraction to match the field names in clinical_summary
- Added forms/attestations extraction from the correct location in clinical_summary

## Data Flow Summary

1. **Order Submission** (QuickRequestController::submitOrder):
   - Form data is converted to QuickRequestData DTO
   - All form data is saved in `clinical_summary` field as JSON
   - Product relationships are created with pricing

2. **Order Display** (ProductRequestController::show):
   - Retrieves ProductRequest with relationships
   - Extracts data from `clinical_summary` field
   - Formats data for OrderDetails component
   - Passes complete orderData to view

3. **Enhanced Details API** (OrderCenterController::getEnhancedOrderDetails):
   - Retrieves additional data via AJAX
   - Uses `clinical_summary` for patient, clinical, and form data
   - Returns comprehensive data structure

## Review

The implementation successfully fixes the order data flow issue. Now when an order is submitted:

1. All form data is properly saved in the `clinical_summary` JSON field
2. The OrderDetails page correctly extracts and displays this data
3. Both the initial page load and the enhanced details API use the same data source
4. All fields that were previously showing "N/A" now display the actual submitted data

The fix maintains backward compatibility and doesn't require any database migrations since it uses the existing `clinical_summary` field that was already being populated correctly.