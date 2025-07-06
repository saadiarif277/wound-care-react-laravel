# Provider and Office Manager Order Details Access

## Problem
- Providers needed access to the same order details page as admins
- Navigation was returning to "my product requests" which no longer exists
- Office managers should see everything except financial data
- Providers should see all financial data except commission

## Todo Items
- [x] Create provider order details route and controller
- [x] Update navigation to return to dashboard instead of 'my product requests'
- [x] Add role-based financial data visibility
- [x] Update ProviderDashboard to link orders to details page
- [x] Add 'Create new product request' button to dashboards
- [x] Update OrderDetails component to handle role-based permissions
- [x] Update OfficeManagerDashboard with same order details access
- [x] Update ProductRequestController's show method to render OrderDetails component

## Changes Made

### 1. Created Provider Order Routes
**File: routes/web.php**
- Added `/provider/orders/{id}` route for providers and office managers
- Uses `view-product-requests` permission check

### 2. Created Provider OrderController
**File: app/Http/Controllers/Provider/OrderController.php**
- Handles order details view for providers and office managers
- Checks permissions based on user role:
  - Providers can view their own orders
  - Office managers can view orders from their facilities
  - Users with `manage-orders` permission can view all orders
- Passes role restrictions and navigation route to OrderDetails component

### 3. Updated OrderDetails Component
**File: resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx**
- Added props for `userRole`, `roleRestrictions`, and `navigationRoute`
- Updated back navigation to:
  - Use provided `navigationRoute` if available
  - Return to dashboard for providers/office managers
  - Return to admin order center for admins
- Pass role restrictions to ProductSection component

### 4. Updated ProductSection Component
**File: resources/js/Pages/Admin/OrderCenter/ProductSection.tsx**
- Added `roleRestrictions` prop
- Uses `can_view_financials` to determine if financial data should be shown
- Office managers see no financial data

### 5. Updated Provider Dashboard
**File: resources/js/Pages/Dashboard/Provider/ProviderDashboard.tsx**
- Added "Create New Product Request" button in top-right corner
- Updated order links to use `provider.orders.show` route
- Updated action item links to use same route

### 6. Updated Office Manager Dashboard
**File: resources/js/Pages/Dashboard/OfficeManager/OfficeManagerDashboard.tsx**
- Added "Create New Product Request" button in top-right corner
- Updated order links to use `provider.orders.show` route
- Updated action item links to use same route

## Role-Based Financial Visibility

### Admin Role
- Can see all financial data including commission
- Can update order status
- Can view IVR documents

### Provider Role
- Can see all financial data EXCEPT commission
- Cannot update order status
- May have limited IVR access based on permissions

### Office Manager Role
- Cannot see ANY financial data
- Cannot update order status
- May have limited IVR access based on permissions

## Navigation Flow
1. Users click on order in their dashboard
2. Navigates to `/provider/orders/{id}`
3. Shows OrderDetails component with appropriate permissions
4. Back button returns to dashboard (not "my product requests")

### 7. Updated ProductRequestController Show Method
**File: app/Http/Controllers/ProductRequestController.php**
- Updated show method to render OrderDetails component instead of ProductRequest/Show
- Maintains the same permission checks (providers see own requests, office managers see facility requests)
- Finds associated Order or creates order data structure from ProductRequest
- Passes role-based restrictions and navigation route to OrderDetails
- Reverted dashboard links to use original product-requests.show route

### 8. Reverted Dashboard Links
**Files: ProviderDashboard.tsx, OfficeManagerDashboard.tsx**
- Changed links back from provider.orders.show to product-requests.show
- Ensures correct parameter name (productRequest) for route

### 9. Cleanup - Removed Unnecessary Provider Routes
**Files: routes/web.php, app/Http/Controllers/Provider/OrderController.php**
- Removed the provider order routes that were initially created
- Deleted the Provider OrderController
- These were not needed since providers use the existing product-requests routes

### 10. Restricted IVR Controls for Non-Admin Users
**File: resources/js/Pages/Admin/OrderCenter/IVRDocumentSection.tsx**
- Added userRole prop to component interface
- Wrapped IVR status dropdown in conditional rendering for Admin only (lines 289-304)
- Wrapped Order Form status dropdown in conditional rendering for Admin only (lines 428-443)
- Restricted upload buttons to Admin users only (lines 311-324, 465-478)
- Restricted delete buttons to Admin users only (lines 353-360, 507-514)
- Restricted "Submit to Manufacturer" button to Admin users only (lines 446-458)

### 11. Removed Mock Data
**File: resources/js/Pages/Admin/OrderCenter/AdditionalDocumentsSection.tsx**
- Removed entire mockDocuments array with hardcoded test data
- Updated displayDocuments to only use real documents passed via props

### 12. Updated OrderDetails Component
**File: resources/js/Pages/Admin/OrderCenter/OrderDetails.tsx**
- Added userRole prop to IVRDocumentSection component

## Review Summary
Successfully implemented role-based order details access for providers and office managers using the existing `/product-requests/{id}` route. The solution:
- Updated ProductRequestController's show method to render OrderDetails component
- Reuses the OrderDetails component with role-based permissions
- Financial data is appropriately filtered (providers see all except commission, office managers see none)
- Navigation returns to dashboard instead of non-existent "my product requests"
- Both dashboards have "Create New Product Request" button in top-right
- Cleaned up unnecessary provider-specific routes and controller
- **Providers can now only VIEW IVR documents and status, cannot modify or upload**
- **All mock data removed, only real data from database is displayed**