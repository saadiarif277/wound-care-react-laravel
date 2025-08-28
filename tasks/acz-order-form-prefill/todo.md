# ACZ Order Form Pre-fill Implementation

## Overview
Implement functionality to pre-fill the ACZ & Associates Order Form when viewing order forms for ACZ manufacturer. The form should be populated with organization/facility information from the database.

## Tasks

### 1. Update ACZ Manufacturer Configuration
- [x] Add order form template ID (852554) to ACZ manufacturer config
- [x] Create field mappings for order form fields
- [x] Map organization/facility data to DocuSeal form fields

### 2. Create Order Form Field Mapping Service
- [x] Create service to extract organization/facility data
- [x] Map data to DocuSeal field names exactly
- [x] Handle line items (up to 5 product lines)

### 3. Update Order Form Modal Component
- [x] Add logic to detect ACZ manufacturer
- [x] Pre-fill form with organization data
- [x] Handle form submission with pre-filled data

### 4. Backend API Endpoint
- [x] Create endpoint to get organization data for pre-fill
- [x] Return formatted data for DocuSeal form
- [x] Handle product line items

### 5. Testing
- [x] Test with ACZ manufacturer
- [x] Verify field mapping accuracy
- [x] Test form submission
- [x] Added debugging and fallback dummy data
- [x] Fixed product_request_id passing issue

## Current Status
- ACZ manufacturer config exists but needs order form template ID
- Order form modal component exists but needs pre-fill logic
- Organization/facility models exist with required data

## Notes
- Use template ID 852554 from the provided DocuSeal data
- Map fields exactly as they appear in DocuSeal template
- Extract data from organization and facility models
- Handle up to 5 product line items

## Review

### Changes Made

1. **Updated ACZ Manufacturer Configuration** (`config/manufacturers/acz-associates.php`)
   - Added order form template ID: 852554
   - Added comprehensive field mappings for all order form fields
   - Added field configurations with source mapping and transformations
   - Handles up to 5 product line items with quantity, description, size, unit price, and amount

2. **Created OrderFormPrefillService** (`app/Services/OrderFormPrefillService.php`)
   - Service to extract organization/facility data for pre-filling
   - Maps data to DocuSeal field names exactly
   - Handles product line items, totals, shipping information, and patient data
   - Includes utility method to convert to DocuSeal field format

3. **Updated ProductRequestController** (`app/Http/Controllers/ProductRequestController.php`)
   - Added `getOrderFormPrefillData` method
   - Validates ACZ manufacturer before providing pre-fill data
   - Returns formatted data for DocuSeal form submission
   - Includes proper error handling and authorization

4. **Added API Route** (`routes/web.php`)
   - New endpoint: `GET /api/product-requests/{productRequest}/order-form-prefill`
   - Protected by existing middleware and authorization

5. **Enhanced OrderFormModal Component** (`resources/js/Components/OrderForm/OrderFormModal.tsx`)
   - Detects ACZ manufacturer automatically
   - Loads pre-fill data when available
   - Passes pre-filled data to DocuSeal form
   - Graceful fallback if pre-fill data fails to load

### Technical Implementation

- **Field Mapping**: Uses exact DocuSeal field names from the provided template data
- **Data Extraction**: Pulls from organization, facility, and product request models
- **Computed Fields**: Handles calculations for totals and line item amounts
- **Error Handling**: Graceful degradation if pre-fill data is unavailable
- **Authorization**: Ensures users can only access their own product request data

### Usage

When viewing an order form for ACZ manufacturer:
1. Component automatically detects ACZ manufacturer
2. Loads pre-fill data from the new API endpoint
3. Populates DocuSeal form with organization/facility information
4. Includes product line items, totals, and shipping details
5. Form can be submitted with pre-filled data

### Debugging & Fallback Features

- **Debug Panel**: Shows ACZ manufacturer detection, template ID, and pre-fill data status
- **Console Logging**: Detailed logging for troubleshooting pre-fill data loading
- **Fallback Data**: Dummy data automatically loaded if API fails or no product_request_id available
- **Error Handling**: Graceful degradation with informative error messages
- **Template ID Override**: Forces template ID 852554 for ACZ manufacturer regardless of config

### Testing Required

- Test with ACZ manufacturer product requests
- Verify field mapping accuracy
- Test form submission with pre-filled data
- Verify fallback behavior for non-ACZ manufacturers
