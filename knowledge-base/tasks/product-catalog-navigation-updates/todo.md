# Product Catalog and Navigation Updates

## Overview
Update the product catalog to show all products for viewing (not filtered by onboarding) while maintaining proper pricing visibility based on roles. Also simplify the navigation menu structure.

## Agent 1: Product Catalog Updates

### Backend Tasks
- [x] Update ProductController::index() to remove provider onboarding filtering
- [x] Add total products and onboarded products count to the response
- [x] Update pricing data inclusion based on user role:
  - [x] Providers: Include MSC price (price_per_sq_cm * 0.6)
  - [x] Office Managers: Include National ASP (price_per_sq_cm)
  - [x] Admins: Include all pricing data
- [x] Ensure MUE remains hidden from non-admins
- [x] Add onboarded products count for providers

### Frontend Tasks
- [x] Update Products/Index.tsx stats section:
  - [x] Show total products count
  - [x] Show onboarded products count (providers only)
- [x] Update pricing display logic:
  - [x] Show MSC price for providers
  - [x] Show National ASP for office managers
  - [x] Keep existing pricing for admins
- [x] Hide "Add Product" button for non-admin users
- [x] Hide commission rate display for unauthorized users
- [x] Ensure all existing filters and search functionality remain working

## Agent 2: Navigation Menu Updates

### Provider Menu Updates
- [x] Remove "Product Requests" dropdown structure
- [x] Add single "Product Request" button pointing to `/quick-requests/create-new`
- [x] Remove "New Request" option (not ready yet)
- [x] Remove "My Orders" option (already on dashboard)
- [x] Remove "Validations & Authorizations" dropdown
- [x] Add single "MAC Validation" button pointing to `/mac-validation`
- [x] Keep "Resources" dropdown with:
  - [x] Product Catalog
  - [x] My Facilities

### Office Manager Menu Updates
- [x] Apply same simplification as Provider role
- [x] Single "Product Request" button
- [x] Remove "New Request" option
- [x] Remove "My Orders" option
- [x] Single "MAC Validation" button (not dropdown)
- [x] Update Management section to keep existing structure
- [x] Keep Reports section as is

### Final Menu Structure
**Provider:**
- Dashboard
- Product Request (single button → `/quick-requests/create-new`)
- MAC Validation (single button → `/mac-validation`)
- Resources (dropdown)
  - Product Catalog
  - My Facilities

**Office Manager:**
- Dashboard
- Product Request (single button → `/quick-requests/create-new`)
- MAC Validation (single button → `/mac-validation`)
- Management (dropdown - keep existing)
- Reports

## Important Notes
- Product Catalog (`/products`) should show ALL products for reference
- Quick Request product selection should remain filtered to onboarded products only
- Do not modify the ProductController::search() method used by Quick Request

## Review Summary

### Agent 1 Completed Changes:

#### Backend (ProductController.php):
1. **Removed provider onboarding filtering** - The `index()` method now shows ALL products without filtering by provider onboarding status
2. **Added role-based pricing visibility**:
   - Providers see MSC price (calculated as price_per_sq_cm * 0.6) with label "MSC Price"
   - Office Managers see National ASP (price_per_sq_cm) with label "National ASP"
   - Admins see all pricing data including both MSC price and National ASP
3. **Added statistics** - Returns total products count and onboarded products count (for providers only)
4. **Maintained security**:
   - MUE values only visible to admins
   - Commission rates only visible to users with financial permissions
   - Added `is_onboarded` flag for providers to see which products they can order
5. **Added pagination metadata** to support frontend pagination display

#### Frontend (Products/Index.tsx):
1. **Updated TypeScript interfaces** to support new data structure with optional pricing fields
2. **Updated stats cards**:
   - Shows total products count for all users
   - Shows onboarded products count for providers only
   - Shows active products count for non-providers
3. **Conditional "Add Product" button** - Only visible to users with `can_manage_products` permission
4. **Updated pricing display**:
   - Shows appropriate price based on user role with correct label
   - Commission rate only shown when available (based on permissions)
5. **Added onboarding status indicator** for providers in both grid and list views
6. **Maintained all existing functionality** - Search, filters, sorting, and view modes continue to work

### Key Design Decisions:
- Used `display_price` and `price_label` fields to simplify frontend logic
- Kept the search() method unchanged as requested (it remains filtered for Quick Request)
- Added visual indicators (green/yellow) for onboarding status
- Maintained backward compatibility with existing features

### Testing Recommendations:
1. Test as Provider to verify MSC pricing display and onboarding status
2. Test as Office Manager to verify National ASP display
3. Test as Admin to verify all pricing data is visible
4. Verify pagination works correctly with the new data structure
5. Confirm Quick Request product selection still shows only onboarded products

### Agent 2 Completed Changes:

#### Navigation Menu (RoleBasedNavigation.tsx):
1. **Provider Menu Simplified**:
   - Removed "Product Requests" dropdown - now single "Product Request" button → `/quick-requests/create-new`
   - Removed "New Request" option (not ready yet)
   - Removed "My Orders" option (already on dashboard)
   - Removed "Validations & Authorizations" dropdown - now single "MAC Validation" button → `/mac-validation`
   - Kept "Resources" dropdown with Product Catalog and My Facilities

2. **Office Manager Menu Simplified**:
   - Applied same simplification as Provider role
   - Single "Product Request" button → `/quick-requests/create-new`
   - Single "MAC Validation" button → `/mac-validation`
   - Maintained Management dropdown with existing structure
   - Kept Reports section as is

3. **Final Menu Structure**:
   - Provider: Dashboard → Product Request → MAC Validation → Resources (dropdown)
   - Office Manager: Dashboard → Product Request → MAC Validation → Management (dropdown) → Reports

### All Tasks Completed Successfully ✓