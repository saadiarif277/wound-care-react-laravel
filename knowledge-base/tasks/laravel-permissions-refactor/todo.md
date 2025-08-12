# Laravel Permissions Refactor - 2025 Best Practices Implementation

## Overview
Refactor the current permission system to follow 2025 Laravel best practices by implementing a single source of truth using Spatie Laravel Permission package, eliminating hard-coded role restrictions, and implementing proper RBAC patterns.

---

## Current Issues Identified

### 🚨 Critical Problems
1. **Two Sources of Truth**: Database permissions vs hard-coded `roleRestrictions` in `QuickRequestService`
2. **Hard-coded Role Logic**: Role-based restrictions scattered throughout the codebase
3. **Inconsistent Permission Checks**: Mix of database permissions and frontend role restrictions
4. **Maintenance Overhead**: Adding new permissions requires code changes in multiple places

### 📊 Current State Analysis
- **Database Permissions**: Proper RBAC structure in `permissions`, `roles`, `model_has_roles` tables
- **Hard-coded Logic**: `getRoleRestrictions()` method in `QuickRequestService.php`
- **Frontend Issues**: `roleRestrictions` prop passed to components instead of using permissions
- **Middleware**: Routes use permission middleware but components use role restrictions

---

## 🎯 2025 Best Practices Goals

Based on Spatie Laravel Permission documentation and industry standards:

### Core Principles
1. **Single Source of Truth**: Database-driven permissions only
2. **Permission-Based Authorization**: Check specific permissions, not roles
3. **Separation of Concerns**: Business logic separate from authorization logic
4. **Consistent API**: Same permission checking mechanism everywhere
5. **Maintainable**: Easy to add/modify permissions without code changes

### Architecture Pattern
```
Users → have → Roles → have → Permissions
Application checks PERMISSIONS (not roles) everywhere
```

---

## 📋 Implementation Tasks

### Phase 1: Foundation Setup ✅
- [ ] **Audit current permission system**
  - Map all hard-coded permissions in `QuickRequestService`
  - Identify all role-based checks in frontend components
  - Document current permission structure in database
  - List all routes using permission middleware

### Phase 2: Spatie Package Configuration
- [ ] **Install and configure Spatie Laravel Permission**
  - Verify package is properly installed and configured
  - Ensure User model has `HasRoles` trait
  - Configure permission caching for performance
  - Set up proper middleware aliases

### Phase 3: Permission Structure Redesign
- [ ] **Create comprehensive permission seeder**
  - Define all granular permissions based on current `roleRestrictions`
  - Map permissions to appropriate roles
  - Include permissions for:
    - `view-financials`
    - `see-discounts` 
    - `see-msc-pricing`
    - `see-order-totals`
    - `manage-products`
    - `view-facilities`
    - `manage-orders`
    - All other identified permissions

### Phase 4: Backend Refactoring
- [ ] **Update User model**
  - Ensure proper `HasRoles` trait implementation
  - Remove any custom role logic
  - Add helper methods for common permission checks

- [ ] **Create Laravel Policies**
  - `ProductPolicy` for product-related permissions
  - `OrderPolicy` for order-related permissions  
  - `QuickRequestPolicy` for quick request permissions
  - Use `$user->can('permission-name')` pattern

- [ ] **Refactor Services**
  - Remove `getRoleRestrictions()` from `QuickRequestService`
  - Update controllers to use policies instead of role checks
  - Implement permission-based data filtering

### Phase 5: Middleware Updates
- [ ] **Update route middleware**
  - Replace role-based middleware with permission-based
  - Use `permission:view-financials` instead of role checks
  - Ensure consistent middleware usage across all routes

### Phase 6: Frontend Refactoring
- [ ] **Update Inertia middleware**
  - Modify `HandleInertiaRequests.php` to share permissions only
  - Remove `roleRestrictions` object
  - Share flat permissions array: `['view-financials', 'see-discounts', ...]`

- [ ] **Refactor React components**
  - Update `Step5ProductSelection.tsx` to use permissions
  - Replace `roleRestrictions.can_view_financials` with `permissions.includes('view-financials')`
  - Create reusable `usePermissions()` hook
  - Update all components using role restrictions

### Phase 7: Permission Management
- [ ] **Create admin interface**
  - Role management CRUD interface
  - Permission assignment interface
  - User role assignment interface
  - Bulk permission operations

### Phase 8: Testing & Validation
- [ ] **Comprehensive testing**
  - Test all permission scenarios
  - Verify proper authorization for each user role
  - Test frontend permission checks
  - Validate API endpoint security

---

## 🔧 Technical Implementation Details

### Database Permissions Structure
```sql
-- Granular permissions based on current roleRestrictions
INSERT INTO permissions (name) VALUES 
('view-financials'),
('see-discounts'),
('see-msc-pricing'), 
('see-order-totals'),
('manage-products'),
('view-facilities'),
('manage-orders'),
('create-quick-requests'),
('view-quick-requests'),
('edit-quick-requests'),
('delete-quick-requests');
```

### Policy Example
```php
// app/Policies/ProductPolicy.php
class ProductPolicy
{
    public function viewFinancials(User $user): bool
    {
        return $user->can('view-financials');
    }
    
    public function seeDiscounts(User $user): bool  
    {
        return $user->can('see-discounts');
    }
}
```

### Frontend Permission Hook
```typescript
// resources/js/hooks/usePermissions.ts
export function usePermissions() {
    const { permissions } = usePage().props;
    
    return {
        can: (permission: string) => permissions.includes(permission),
        canAny: (perms: string[]) => perms.some(p => permissions.includes(p)),
        canAll: (perms: string[]) => perms.every(p => permissions.includes(p))
    };
}
```

### Component Update Example
```tsx
// Before
{roleRestrictions.can_view_financials && <PriceDisplay />}

// After  
const { can } = usePermissions();
{can('view-financials') && <PriceDisplay />}
```

---

## 🚀 Migration Strategy

### Phase-by-Phase Rollout
1. **Parallel Implementation**: Keep existing system while building new one
2. **Feature Flags**: Use feature flags to toggle between old/new systems
3. **Gradual Migration**: Migrate one component/route at a time
4. **Validation**: Extensive testing at each phase
5. **Cleanup**: Remove old system once new one is fully validated

### Rollback Plan
- Keep existing `roleRestrictions` logic as fallback
- Feature flag to quickly revert if issues arise
- Database backup before permission structure changes

---

## 📈 Success Metrics

### Technical Metrics
- [ ] Single source of truth for all permissions
- [ ] Zero hard-coded role checks in application
- [ ] Consistent permission API across frontend/backend
- [ ] Performance: Permission checks under 10ms
- [ ] Code reduction: Remove ~100 lines of duplicate permission logic

### Business Metrics  
- [ ] Faster permission changes (no code deployments needed)
- [ ] Reduced support tickets for permission issues
- [ ] Easier onboarding of new roles/permissions
- [ ] Better audit trail for permission changes

---

## 🔍 Code Review Checklist

### Backend
- [ ] All controllers use policies instead of role checks
- [ ] No hard-coded role logic in services
- [ ] Proper middleware usage on all routes
- [ ] Permission-based data filtering implemented

### Frontend
- [ ] No `roleRestrictions` props in components
- [ ] Consistent `usePermissions()` hook usage
- [ ] Permission checks at component level
- [ ] Proper loading states for permission-dependent UI

### Database
- [ ] Comprehensive permission seeder
- [ ] Proper role-permission relationships
- [ ] Migration scripts for existing data
- [ ] Performance indexes on permission tables

---

## 📚 References

- [Spatie Laravel Permission Best Practices](https://spatie.be/docs/laravel-permission/v6/best-practices/roles-vs-permissions)
- [Laravel Authorization Documentation](https://laravel.com/docs/authorization)
- [RBAC Implementation Patterns](https://www.permit.io/blog/how-to-implement-role-based-access-control-rbac-in-laravel)

---

## 🎉 Expected Outcomes

After completing this refactor:

1. **Simplified Architecture**: Single source of truth for all permissions
2. **Better Maintainability**: Add permissions via seeder, not code changes  
3. **Consistent API**: Same permission checking everywhere
4. **Improved Security**: Granular, database-driven permissions
5. **Better UX**: Faster permission updates without deployments
6. **Developer Experience**: Clear, consistent patterns for permission checks

This refactor aligns with Laravel 2025 best practices and provides a solid foundation for future permission requirements. 