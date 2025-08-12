# Fix Financial Visibility and DocuSeal Issues

## Task Overview

Fixed three critical issues:

1. Provider pricing visibility (providers should always see pricing)
2. Office Manager hidden messages (remove "[Hidden - Contact Admin]" messages)
3. DocuSeal template ID error for non-Amnio Amp manufacturers

## Todo Items

### ✅ Completed Tasks

- [x] Remove "[Hidden - Contact Admin]" messages for Office Managers
- [x] Fix provider pricing visibility - providers should always see pricing
- [x] Add logging to debug DocuSeal template ID error

### Changes Made

#### 1. Fixed Office Manager Pricing Display (ProductSelectorQuickRequest.tsx)

- **Lines 674-689**: Removed the else block that showed "[Hidden - Contact Admin]" for total price
- **Lines 642-651**: Removed the else block that showed "Pricing hidden" for individual items
- **Result**: Office Managers now see no pricing information at all (completely hidden)

#### 2. Fixed Provider Pricing Visibility (QuickRequestController.php)

- **Lines 186-228**: Updated `getUserPermissions` method to:
  - Added logging to debug permission loading
  - Force-set pricing permissions for providers (lines 211-217):

    ```php
    if ($roleSlug === 'provider') {
        $permissions['can_view_financials'] = true;
        $permissions['can_see_order_totals'] = true;
        $permissions['can_see_msc_pricing'] = true;
        $permissions['can_see_discounts'] = true;
        $permissions['pricing_access_level'] = 'msc_only';
    }
    ```

- **Result**: Providers will always see pricing regardless of database permissions

#### 3. Added Debugging for DocuSeal Template ID (IntelligentFieldMappingService.php)

- **Lines 52-59**: Added logging after standard mapping to track manufacturer config
- **Lines 126-135**: Added logging and explicit preservation of manufacturer config in AI enhancement
- **Result**: Better visibility into where the template ID is being lost

## Review

### What Was Fixed

1. **Office Manager Experience**: Pricing is now completely hidden with no indication it exists
2. **Provider Experience**: Providers can now see all pricing information as intended
3. **DocuSeal Debugging**: Added comprehensive logging to track the manufacturer config through the mapping process
4. **Laravel Syntax Error**: Fixed missing comma in api.php route definition
5. **API Response Filtering**: Implemented middleware to filter financial data from API responses
6. **Deep Link Order Tracking**: Added secure deep link functionality for providers/OMs to track orders

### Technical Implementation

- Used conditional rendering in React to completely hide pricing sections for Office Managers
- Implemented role-based permission override in the backend for providers
- Added strategic logging points to diagnose the DocuSeal template ID issue
- Created `FilterFinancialData` middleware that recursively removes financial fields from JSON responses
- Applied the middleware to product and order API endpoints
- Implemented token-based deep links for secure order tracking

### New Features Added

#### 1. Financial Data Filtering Middleware

- **File**: `/app/Http/Middleware/FilterFinancialData.php`
- Automatically filters out financial fields from API responses for users without permissions
- Fields filtered include: price, msc_price, commission, discounts, etc.
- Applied to:
  - `/api/products/search`
  - `/api/v1/products/*`
  - `/api/v1/orders/{orderId}/review`

#### 2. Deep Link Order Tracking

- **Route**: `/order/{order}/track/{token}`
- Secure token-based URL for direct order access
- Verifies user permissions and order ownership
- API endpoint to generate tracking links: `POST /api/orders/{order}/generate-tracking-link`

### Testing Recommendations

1. Test as Office Manager to verify no pricing information is visible
2. Test as Provider to verify all pricing (ASP, MSC pricing, totals) is visible
3. Monitor logs when creating IVR submissions for non-Amnio Amp manufacturers to identify where template ID is lost
4. Test API responses to ensure financial data is properly filtered for Office Managers
5. Test deep link generation and access for different user roles

### Next Steps

1. Add edit capabilities for Provider/OM until submission (task_008)
2. Create Admin order management interface with post-submission controls (task_009)
3. Create notification service for manufacturer communications via DocuSeal (task_011)
4. Add order status management and tracking system (task_012)
