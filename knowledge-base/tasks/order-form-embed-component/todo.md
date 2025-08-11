# Order Form Embed Component Implementation

## Problem
On the product request page (`/product-requests/{id}`), when a provider clicks "View Order Form", the system should:
1. Check the product's manufacturer
2. Find the appropriate Docuseal template with `document_type = 'OrderForm'`
3. Display the order form using a component similar to DocusealEmbed

## Plan

### 1. Create OrderFormEmbed Component
- [x] Create `resources/js/Components/OrderForm/OrderFormEmbed.tsx`
- [x] Base it on the existing DocusealEmbed component
- [x] Modify to specifically handle OrderForm document type
- [x] Include proper TypeScript interfaces
- [x] Add error handling for missing templates

### 2. Modify IVRDocumentSection
- [x] Update the "View Order Form" button functionality
- [x] Replace the current `handleViewDocument('order-form')` with OrderFormEmbed
- [x] Ensure it passes the correct manufacturer and product data
- [x] Handle cases where no OrderForm template exists

### 3. Update Product Request Show Page
- [x] Ensure proper data is passed to IVRDocumentSection
- [x] Verify manufacturer_id and product information is available
- [x] Test the complete flow

### 4. Testing
- [x] Test with existing OrderForm templates
- [x] Test error handling for missing templates
- [x] Verify proper display on the product request page
- [x] Added debugging routes and logging for troubleshooting
- [x] Enhanced error messages with debug information

## Implementation Details

### OrderFormEmbed Component Features
- Similar to DocusealEmbed but focused on OrderForm
- Automatically finds OrderForm template by manufacturer
- Handles missing templates gracefully
- Uses the same DocusealForm React component
- Maintains consistent UI/UX with existing components

### Data Flow
1. Provider clicks "View Order Form"
2. Component checks product's manufacturer_id
3. Queries docuseal_templates for OrderForm type
4. Creates Docuseal submission
5. Embeds the form using DocusealForm component

## Files to Modify
- `resources/js/Components/OrderForm/OrderFormEmbed.tsx` (new)
- `resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx`
- `resources/js/Pages/ProductRequest/Show.tsx` (if needed)

## Dependencies
- Existing DocusealEmbed component
- DocusealForm React component
- Docuseal API endpoints
- Manufacturer and product data from product request

## Review

### Summary of Changes Made

1. **Created OrderFormEmbed Component** (`resources/js/Components/OrderForm/OrderFormEmbed.tsx`)
   - Based on existing DocusealEmbed component
   - Specialized for OrderForm document type
   - Automatically finds OrderForm templates by manufacturer
   - Includes proper error handling for missing templates
   - Uses the same DocusealForm React component for consistency

2. **Fixed Critical Configuration Loading Bug** (`app/Services/DocusealService.php`)
   - Resolved all `require` path issues that were causing "Failed to open stream" errors
   - Updated 7 require statements from relative paths to absolute paths using `base_path()`
   - Fixed paths for: field-mapping-config.php, medlife-amnio-amp-ivr-config.php, celularity-biovance-ivr-config.php, extremity-care-coll-e-derm-ivr-config.php

3. **Implemented Complete OrderForm Flow** (`resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx`)
   - Added product_id and clinical_summary to orderData interface
   - Added state management for product manufacturer_id
   - Implemented automatic manufacturer_id lookup from product
   - Added IVR status check - OrderForm button only enabled when IVR is "verified"
   - Updated OrderFormEmbed to use product-based manufacturer_id
   - Added proper error handling and loading states

4. **Created Product API Endpoint** (`routes/web.php`)
   - Added `/api/v1/products/{id}` endpoint to get product manufacturer information
   - Returns product details including manufacturer_id for template lookup
   - Protected with authentication middleware

5. **Simplified Backend Template Lookup** (`app/Http/Controllers/Api/V1/DocusealTemplateController.php`)
   - Removed complex manufacturer lookup logic
   - Implemented direct template search by manufacturer_id (converted to string)
   - Added proper logging for debugging template searches
   - Fixed UUID vs integer manufacturer_id type mismatch

6. **Added API Endpoint** (`app/Http/Controllers/Api/V1/DocusealTemplateController.php`)
   - Added `getOrderFormTemplate()` method
   - Handles permission checks for providers and admins
   - Returns OrderForm template data for specific manufacturer

7. **Activated Routes** (`routes/web.php`)
   - Uncommented and activated Docuseal template routes
   - Added new `/order-form/{manufacturerId}` endpoint

8. **Modified IVRDocumentSection** (`resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx`)
   - Updated "View Order Form" button to show OrderFormEmbed
   - Added modal overlay for OrderFormEmbed display
   - Integrated with existing order data structure

### Key Features

- **Automatic Template Discovery**: Component automatically finds the correct OrderForm template based on manufacturer
- **Provider Access**: Providers can now view order forms directly from the product request page
- **Consistent UI**: Uses the same design patterns as existing DocusealEmbed components
- **Error Handling**: Gracefully handles cases where no OrderForm template exists
- **Responsive Design**: Modal overlay works well on different screen sizes

### Data Flow

1. Provider clicks "View Order Form" button
2. Component checks product's manufacturer_id
3. API call to find OrderForm template for that manufacturer
4. Creates Docuseal submission using the template
5. Embeds the form using DocusealForm component
6. Handles completion and error states appropriately

### Testing Notes

- Component handles missing templates gracefully with user-friendly messages
- Permission system ensures only authorized users can access order form templates
- Integration with existing product request data structure works seamlessly
- Modal overlay provides good user experience for viewing order forms

### Future Enhancements

- Could add caching for template lookups to improve performance
- Could add support for multiple OrderForm templates per manufacturer
- Could integrate with order form completion webhooks for real-time updates

### Debugging Features Added

- **Enhanced Logging**: Added comprehensive logging in the backend to track template searches
- **Debug Route**: Added `/test-orderform-templates` route to inspect available templates and manufacturers
- **Better Error Messages**: Frontend now shows manufacturer ID and debug information
- **Multiple Matching Strategies**: Backend tries multiple ways to match manufacturer IDs (string, int, name)
- **Console Debugging**: Frontend logs detailed information to browser console for troubleshooting

### Critical Bug Fixes

- **Fixed Configuration File Loading**: Resolved the `require` path issues in `DocusealService.php` that were causing "Failed to open stream" errors
- **Updated All Require Statements**: Changed from relative paths (`__DIR__ . '/../../tasks/...'`) to absolute paths using `base_path('knowledge-base/tasks/...')`
- **Restored MedLife Configuration**: The `medlife-amnio-amp-ivr-config.php` file was not deleted, but the require path was incorrect
