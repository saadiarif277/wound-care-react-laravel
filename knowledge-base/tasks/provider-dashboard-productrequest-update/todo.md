# Provider Dashboard ProductRequest Update

## Problem
The Provider Dashboard currently uses the `Order` model but should use `ProductRequest` to show only product requests raised by the current provider. Also, `docuseal_submission_id` is not being saved when creating ProductRequests.

## Solution
1. Update DashboardController to use ProductRequest instead of Order
2. Map ProductRequest statuses to dashboard stats
3. Fix docuseal_submission_id saving in QuickRequestController
4. Update frontend to reflect ProductRequest data structure

## Tasks

- [x] Update DashboardController to use ProductRequest model
- [x] Map ProductRequest statuses to dashboard stats
- [x] Fix docuseal_submission_id saving in QuickRequestController
- [x] Update frontend interface to match ProductRequest structure
- [x] Test the dashboard with ProductRequest data
- [x] Update todo with results

## Implementation Details

### Backend Changes
- Change from `Order::where('provider_id', $user->id)` to `ProductRequest::where('provider_id', $user->id)`
- Map statuses:
  - `pending` → Pending IVR
  - `submitted_to_manufacturer` → In Progress
  - `confirmed_by_manufacturer` → Completed
  - `rejected` → Rejected
- Add docuseal_submission_id to ProductRequest creation if available

### Frontend Changes
- Update interface to match ProductRequest fields
- Update status mapping and display

## Testing Steps

1. Create a ProductRequest as a provider
2. Check that it appears on the dashboard
3. Verify stats are calculated correctly
4. Check that docuseal_submission_id is saved
5. Test status filtering and search

## Review

### Changes Made
- Updated DashboardController to use ProductRequest instead of Order model
- Mapped ProductRequest statuses to dashboard stats:
  - `pending` → Pending IVR
  - `submitted_to_manufacturer` → In Progress  
  - `confirmed_by_manufacturer` → Completed
  - `rejected` → Rejected
- Fixed docuseal_submission_id saving in QuickRequestController createProductRequest method
- Updated all helper methods to work with ProductRequest data structure
- Added proper relationship loading for products.manufacturer
- Fixed getManufacturerName method to handle different data types safely
- Updated activity tracking and deadline calculations for ProductRequest workflow

### Results
- Provider Dashboard now shows ProductRequests instead of Orders
- Stats are calculated based on ProductRequest statuses
- docuseal_submission_id is properly saved when creating ProductRequests
- Fixed "Attempt to read property 'name' on string" error in getManufacturerName method
- Dashboard now correctly displays manufacturer names from products or clinical summary
- All activity tracking and AI insights updated for ProductRequest workflow
- Fixed frontend route error by updating from `orders.show` to `product-requests.show`
- Updated frontend interface and variable names to use ProductRequest instead of Order
- Updated status filtering to match ProductRequest status values 
