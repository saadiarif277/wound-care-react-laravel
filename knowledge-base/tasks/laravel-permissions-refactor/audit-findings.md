# Permission System Audit Findings

## 🔍 Current State Analysis

### Database Structure ✅ GOOD
- **Proper RBAC Tables**: `roles`, `permissions`, `user_role`, `role_permission` tables exist
- **Custom Implementation**: Uses custom `HasPermissions` trait instead of Spatie package
- **Working Relationships**: Proper many-to-many relationships between users, roles, and permissions

### Backend Permission Checks 🟡 MIXED

#### ✅ Good Patterns Found
1. **Route Middleware**: Extensive use of `permission:` middleware on routes
   - `routes/web.php`: 50+ routes with proper permission middleware
   - `routes/api.php`: 30+ API routes with permission checks
   - Examples: `permission:manage-orders`, `permission:view-financials`

2. **Database-Driven Checks**: Some controllers use proper permission checks
   - `OrderController::buildRoleRestrictions()` uses `$user->hasPermission()`
   - Test routes show proper permission checking patterns

#### 🚨 Critical Issues Found

1. **Hard-coded Role Logic in Services**
   - `QuickRequestService::getRoleRestrictions()` - **315 lines of hard-coded role-based logic**
   - `QuickRequestController.php.backup` - Duplicate hard-coded logic (backup file)
   - Switch statements mapping roles to permissions instead of checking database

2. **Two Sources of Truth**
   - Database permissions: `view-financials`, `see-discounts`, etc.
   - Hard-coded restrictions: `can_view_financials`, `can_see_discounts`, etc.
   - **These are NOT synchronized and can diverge**

### Frontend Permission Usage 🚨 MAJOR ISSUES

#### Current Pattern (WRONG)
```tsx
// Hard-coded role restrictions passed as props
roleRestrictions={roleRestrictions}

// Usage in components
{roleRestrictions.can_view_financials && <PriceDisplay />}
```

#### Files Using Hard-coded Role Restrictions
1. `Step5ProductSelection.tsx` - Product selection component
2. `ProductSelectorQuickRequest.tsx` - Product catalog
3. `ProductSelector.tsx` - Main product selector
4. `PricingDisplay.tsx` - Pricing components
5. `CreateNew.tsx` - Quick request form
6. `ProductRequest/Show.tsx` - Request details
7. `Dashboard/Index.tsx` - Dashboard

#### TypeScript Interfaces Found
```typescript
interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_msc_pricing: boolean;
  can_see_discounts: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
  commission_access_level: string;
}
```

### Inertia Middleware 🟡 PARTIALLY CORRECT

`HandleInertiaRequests.php` shares both:
- `permissions` array (✅ GOOD - direct from database)
- `roleRestrictions` object (🚨 BAD - hard-coded logic)

## 📊 Hard-coded Permission Mappings Found

### In QuickRequestService::getRoleRestrictions()

#### Provider Role
```php
'can_view_financials' => true,
'can_see_discounts' => false,
'can_see_msc_pricing' => false,
'can_see_order_totals' => false,
'pricing_access_level' => 'national_asp_only',
'commission_access_level' => 'none'
```

#### Office Manager Role
```php
'can_view_financials' => true,
'can_see_discounts' => false,
'can_see_msc_pricing' => false,
'can_see_order_totals' => false,
'pricing_access_level' => 'national_asp_only',
'commission_access_level' => 'none'
```

#### MSC Sub-Rep Role
```php
'can_view_financials' => true,
'can_see_discounts' => true,
'can_see_msc_pricing' => true,
'can_see_order_totals' => true,
'pricing_access_level' => 'full',
'commission_access_level' => 'limited'
```

#### MSC Rep/Admin/Super-Admin Roles
```php
'can_view_financials' => true,
'can_see_discounts' => true,
'can_see_msc_pricing' => true,
'can_see_order_totals' => true,
'pricing_access_level' => 'full',
'commission_access_level' => 'full'
```

## 🎯 Permissions to Create in Database

Based on hard-coded logic analysis:

### Financial Permissions
- `view-financials` ✅ (already exists)
- `view-discounts` ✅ (already exists) 
- `view-msc-pricing` ✅ (already exists)
- `view-order-totals` ✅ (already exists)

### Product Permissions
- `manage-products` ✅ (already exists)
- `view-products` ✅ (already exists)

### Commission Permissions
- `view-commission` ✅ (already exists)
- `view-commission-limited` (NEW - for sub-reps)
- `view-commission-full` (NEW - for reps/admins)

### Quick Request Permissions  
- `create-product-requests` ✅ (already exists)
- `view-product-requests` ✅ (already exists)
- `manage-product-requests` ✅ (already exists)

## 🔧 Migration Path

### Phase 1: Backend Cleanup ✅
1. Remove `getRoleRestrictions()` from `QuickRequestService`
2. Update controllers to use database permissions only
3. Create policies for complex permission logic

### Phase 2: Frontend Refactor 🎯
1. Remove all `roleRestrictions` props from components
2. Create `usePermissions()` hook
3. Update components to use `permissions.includes('permission-name')`

### Phase 3: Middleware Update 🎯
1. Remove `roleRestrictions` from `HandleInertiaRequests.php`
2. Keep only `permissions` array sharing

## 🚨 Risk Assessment

### High Risk Areas
1. **QuickRequest Flow**: Heavily dependent on role restrictions
2. **Product Pricing**: Financial data visibility logic
3. **Commission Display**: Complex access level logic

### Medium Risk Areas
1. **Dashboard Components**: Some role-based display logic
2. **Order Management**: Mixed permission patterns

### Low Risk Areas
1. **Route Protection**: Already using proper middleware
2. **API Endpoints**: Most use permission middleware correctly

## 📈 Expected Benefits

### Technical
- **Single Source of Truth**: All permissions from database
- **Consistency**: Same permission API everywhere
- **Maintainability**: No code changes for permission updates
- **Performance**: Cached permission lookups

### Business
- **Faster Updates**: Change permissions without deployments
- **Better Security**: Granular, auditable permissions
- **Easier Management**: Admin interface for permission changes

## 🎯 Success Criteria

### Must Have
- [ ] Zero hard-coded role logic in application
- [ ] All components use database permissions
- [ ] Consistent permission checking API
- [ ] Admin interface for permission management

### Nice to Have
- [ ] Permission caching for performance
- [ ] Audit trail for permission changes
- [ ] Bulk permission operations
- [ ] Role templates for common setups

## 📋 Files Requiring Changes

### Backend (High Priority)
- `app/Services/QuickRequestService.php` - Remove getRoleRestrictions()
- `app/Http/Controllers/QuickRequestController.php` - Update to use policies
- `app/Http/Controllers/OrderController.php` - Standardize permission checks
- `app/Http/Middleware/HandleInertiaRequests.php` - Remove roleRestrictions

### Frontend (High Priority)  
- `resources/js/Pages/QuickRequest/Components/Step5ProductSelection.tsx`
- `resources/js/Components/ProductCatalog/ProductSelectorQuickRequest.tsx`
- `resources/js/Components/ProductCatalog/ProductSelector.tsx`
- `resources/js/Components/Pricing/PricingDisplay.tsx`
- `resources/js/Pages/QuickRequest/CreateNew.tsx`

### New Files to Create
- `resources/js/hooks/usePermissions.ts` - Permission checking hook
- `app/Policies/ProductPolicy.php` - Product-related permissions
- `app/Policies/QuickRequestPolicy.php` - Quick request permissions
- `database/seeders/UpdatedPermissionSeeder.php` - Comprehensive permissions

This audit confirms that a refactor is **critically needed** to eliminate the dual permission system and establish a single source of truth. 