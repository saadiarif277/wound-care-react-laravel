# Role-Based Menu Structure & Financial Restrictions

## Overview
This document outlines the comprehensive role-based menu structure implemented for the wound care platform, with specific emphasis on critical financial restrictions for Office Managers.

## Portal Structure by Role

### 📋 Healthcare Provider Portal
```
📊 Dashboard
📋 Product Requests
    └─ New Request
    └─ My Requests  
    └─ Status
✅ MAC/Eligibility/PA
    └─ MAC Validation 
    └─ Eligibility Check
    └─ Pre-Authorization
📚 Product Catalog
```

**Financial Access:** Full financial visibility including amounts owed and discounts.

### 🏥 Office Manager Portal
```
📊 Dashboard
📋 Product Requests
    └─ New
    └─ Facility Requests
    └─ Provider Requests
✅ MAC/Eligibility/PA
    └─ MAC Validation 
    └─ Eligibility Check
    └─ Pre-Authorization
📚 Product Catalog
👥 Provider Management
```

**CRITICAL RESTRICTIONS:**
- ❌ **NO financial data visible anywhere**
- ❌ **NO order totals**
- ❌ **NO amounts owed**
- ❌ **NO discounts visible**
- ❌ **NO MSC pricing**
- ❌ **NO special rates**
- ✅ **ONLY National ASP pricing**

### 💼 MSC Sales Representative Portal
```
📊 Dashboard
📋 Customer Orders (view only - product & commission, NO PHI)
💰 Commissions
    └─ My Earnings
    └─ History
    └─ Payouts
👥 My Customers
    └─ Customer List
    └─ My Team (invite sub-reps with commission split proposals)
```

**Data Restrictions:** Can see product & commission data but NO PHI (Protected Health Information).

### 🎯 MSC Sub-Representative Portal
```
📊 Dashboard
📋 Customer Orders (view only - product & commission, NO PHI)
💰 My Commissions (limited access)
```

**Access Level:** Limited - view only orders, limited commission access, no financial management.

### ⚙️ MSC Administrator Portal
```
📊 Dashboard
📋 Request Management (approve/reject product requests)
📦 Order Management 
    └─ Create Manual Orders (FULL financial visibility)
    └─ Manage All Orders (FULL financial visibility)
    └─ Product Management (catalog, pricing, Q-codes)
    └─ Engines
        └─ Clinical Opportunity Rules
        └─ Product Recommendation Rules
        └─ Commission Management
👥 User & Org Management 
    └─ Access Requests
    └─ Sub-Rep Approval Queue
    └─ User Management
    └─ Organization Management
⚙️ Settings
```

**Financial Access:** FULL financial visibility and management capabilities.

### 🔧 Super Administrator Portal
```
📊 Dashboard (system health, all metrics)
📋 Request Management
📦 Order Management (FULL financial visibility)
💰 Commission Overview (system-wide view)
👥 User & Org Management 
    └─ RBAC Configuration
    └─ All Users
    └─ System Access Control
    └─ Role Management
⚙️ System Admin
    └─ Platform Configuration
    └─ Integration Settings
    └─ API Management
    └─ Audit Logs
```

**Access Level:** Complete system access with all administrative capabilities.

## Implementation Details

### Backend Components

#### Role Model (`app/Models/UserRole.php`)
- Enhanced with financial restriction methods
- Dashboard configuration per role
- Pricing access levels
- Customer data restrictions

#### Middleware (`app/Http/Middleware/FinancialAccessControl.php`)
- Enforces financial access restrictions
- Blocks restricted routes for Office Managers
- Injects role restrictions into requests

#### Navigation Component (`resources/js/Components/Navigation/RoleBasedNavigation.tsx`)
- Role-specific menu generation
- Hierarchical menu structure
- Dynamic menu filtering

### Frontend Components

#### Pricing Display (`resources/js/Components/Pricing/PricingDisplay.tsx`)
- `PricingDisplay`: Role-aware pricing component
- `OrderTotalDisplay`: Financial totals with restrictions
- `CommissionDisplay`: Commission data with access controls

### Role Permissions Matrix

| Feature | Provider | Office Mgr | MSC Rep | MSC Sub-Rep | MSC Admin | Super Admin |
|---------|----------|------------|---------|-------------|-----------|-------------|
| View Financial Data | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ |
| See Discounts | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ |
| MSC Pricing | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ |
| Order Totals | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ |
| Commission Data | ❌ | ❌ | ✅ | Limited | ✅ | ✅ |
| PHI Access | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ |
| Manage Orders | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |

### Pricing Access Levels

1. **Full Access**: Complete pricing visibility including discounts and MSC pricing
2. **National ASP Only**: Only National Average Sales Price visible (Office Managers)
3. **Limited**: Basic pricing without discounts (MSC Sub-Reps)

### Financial Restrictions Implementation

#### Office Manager Restrictions
```php
// Backend validation
if ($userRole->name === 'office_manager') {
    // Strip all financial data from responses
    // Only show National ASP pricing
    // Hide order totals, discounts, amounts owed
}
```

#### Frontend Component Usage
```tsx
// Example usage of pricing component
<PricingDisplay 
    userRole={userRole}
    product={product}
    showLabel={true}
/>

// Automatically handles Office Manager restrictions
// Shows only National ASP for office_manager role
// Shows full pricing for other authorized roles
```

## Security Considerations

1. **Backend Enforcement**: All restrictions are enforced at the API level
2. **Middleware Protection**: Financial routes are protected by middleware
3. **Component-Level Security**: Frontend components respect role restrictions
4. **Data Sanitization**: Sensitive data is stripped before sending to unauthorized roles

## Testing Scenarios

### Office Manager Access Tests
- ✅ Can access National ASP pricing
- ❌ Cannot see MSC pricing
- ❌ Cannot see discounts
- ❌ Cannot see order totals
- ❌ Cannot see amounts owed
- ✅ Can access clinical workflows

### Sales Rep Access Tests
- ✅ Can see commission data
- ✅ Can view customer orders
- ❌ Cannot see PHI data
- ✅ Can manage customer relationships

### Admin Access Tests
- ✅ Full financial visibility
- ✅ Can create manual orders
- ✅ Can manage system configuration
- ✅ Can approve/reject requests

## Compliance Notes

- **HIPAA Compliance**: PHI restrictions for sales roles
- **Financial Separation**: Office Managers have zero financial visibility
- **Role Segregation**: Clear separation of duties between roles
- **Audit Trail**: All access attempts are logged for compliance

## Configuration Files

- Menu Structure: `resources/js/Components/Navigation/RoleBasedNavigation.tsx`
- Role Definitions: `resources/js/lib/roleUtils.ts`
- Backend Roles: `app/Models/UserRole.php`
- Middleware: `app/Http/Middleware/FinancialAccessControl.php`
- Pricing Components: `resources/js/Components/Pricing/PricingDisplay.tsx` 
