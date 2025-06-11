# RBAC Implementation Guide

This guide explains how to properly implement Role-Based Access Control (RBAC) using permissions throughout the MSC Wound Portal application.

## Core Principles

1. **No Hardcoded Roles**: Never check for specific roles in code. Always use permissions.
2. **Permission-Based Access**: All access control should be based on permissions, not roles.
3. **Backend Enforcement**: Permissions are enforced on the backend via middleware.
4. **Frontend Guidance**: Frontend permission checks are for UX only, not security.

## Backend Implementation

### Controller Protection

All controllers should use permission middleware in their constructor:

```php
public function __construct()
{
    // Protect all methods
    $this->middleware('permission:manage-providers');
    
    // Or protect specific methods
    $this->middleware('permission:view-providers')->only(['index', 'show']);
    $this->middleware('permission:manage-providers')->only(['create', 'store', 'edit', 'update', 'destroy']);
}
```

### Multiple Permission Requirements

```php
// Require ANY of these permissions
$this->middleware('permission:view-financials,manage-financials')->only(['financialReport']);

// For ALL permissions (custom middleware needed)
$this->middleware(['permission:view-orders', 'permission:view-financials'])->only(['orderFinancials']);
```

### In-Method Permission Checks

```php
public function sensitiveAction(Request $request)
{
    // Check single permission
    if (!$request->user()->hasPermission('manage-system-config')) {
        abort(403, 'Unauthorized');
    }
    
    // Check multiple permissions (ANY)
    if (!$request->user()->hasAnyPermission(['view-financials', 'manage-financials'])) {
        abort(403, 'Unauthorized');
    }
    
    // Check multiple permissions (ALL)
    if (!$request->user()->hasAllPermissions(['manage-orders', 'view-financials'])) {
        abort(403, 'Unauthorized');
    }
}
```

## Frontend Implementation

### Available Utilities

The system provides several utilities for permission checking in React components:

```typescript
import { 
    usePermissions,        // Get all user permissions
    useHasPermission,      // Check single permission
    useHasAnyPermission,   // Check if user has ANY of the permissions
    useHasAllPermissions,  // Check if user has ALL permissions
    Can                    // Component for conditional rendering
} from '@/lib/permissions';
```

### Basic Usage

#### Using Hooks

```tsx
function MyComponent() {
    // Get all permissions
    const permissions = usePermissions();
    
    // Check single permission
    const canEdit = useHasPermission('manage-providers');
    
    // Check multiple permissions (ANY)
    const canViewFinancials = useHasAnyPermission(['view-financials', 'manage-financials']);
    
    // Check multiple permissions (ALL)
    const canProcessPayments = useHasAllPermissions(['manage-payments', 'view-financials']);
    
    return (
        <div>
            {canEdit && <button>Edit</button>}
            {canViewFinancials && <FinancialSection />}
        </div>
    );
}
```

#### Using Can Component

```tsx
function ProviderActions({ provider }) {
    return (
        <div className="flex gap-3">
            {/* Single permission */}
            <Can permission="manage-providers">
                <Button onClick={handleEdit}>Edit Provider</Button>
            </Can>
            
            {/* Multiple permissions (ANY) */}
            <Can permissions={['view-financials', 'manage-financials']}>
                <Button onClick={viewFinancials}>View Financials</Button>
            </Can>
            
            {/* Multiple permissions (ALL) */}
            <Can permissions={['manage-orders', 'approve-orders']} requireAll>
                <Button onClick={approveOrder}>Approve Order</Button>
            </Can>
            
            {/* With fallback */}
            <Can 
                permission="delete-users" 
                fallback={<span className="text-gray-500">No delete permission</span>}
            >
                <Button variant="danger" onClick={handleDelete}>Delete</Button>
            </Can>
        </div>
    );
}
```

### Navigation Menu

The `PermissionBasedNavigation` component automatically filters menu items based on permissions:

```tsx
// Menu items are defined with required permissions
const menuItems: MenuItem[] = [
    {
        name: 'Dashboard',
        href: '/',
        icon: FiHome,
        permissions: ['view-dashboard']
    },
    {
        name: 'Provider Management',
        href: '/admin/providers',
        icon: FiUsers,
        permissions: ['manage-providers'],
        description: 'Manage provider profiles and credentials'
    },
    {
        name: 'Orders',
        href: '/orders',
        icon: FiShoppingCart,
        permissions: ['view-orders'],
        children: [
            {
                name: 'All Orders',
                href: '/orders',
                permissions: ['view-orders']
            },
            {
                name: 'Approve Orders',
                href: '/orders/approve',
                permissions: ['approve-orders']
            }
        ]
    }
];
```

### Modal and Form Protection

```tsx
function ProviderEditModal({ provider, isOpen, onClose }) {
    const canEdit = useHasPermission('manage-providers');
    const canEditFinancials = useHasPermission('manage-financials');
    
    if (!canEdit) {
        return (
            <Modal isOpen={isOpen} onClose={onClose}>
                <p className="text-red-600">
                    You don't have permission to edit providers.
                </p>
            </Modal>
        );
    }
    
    return (
        <Modal isOpen={isOpen} onClose={onClose}>
            <form onSubmit={handleSubmit}>
                {/* Basic fields */}
                <input name="name" />
                <input name="email" />
                
                {/* Financial fields only for those with permission */}
                <Can permission="manage-financials">
                    <div className="mt-4 p-4 border rounded">
                        <h3>Financial Information</h3>
                        <input name="credit_limit" />
                        <input name="payment_terms" />
                    </div>
                </Can>
                
                <button type="submit">Save</button>
            </form>
        </Modal>
    );
}
```

## Available Permissions

The system includes the following permissions:

### General Permissions
- `view-dashboard` - View dashboard and analytics
- `view-reports` - View reports and analytics
- `view-analytics` - View analytics dashboard

### Product Management
- `view-products` - View product catalog
- `manage-products` - Manage product catalog
- `create-product-requests` - Create new product requests
- `view-product-requests` - View product requests
- `view-request-status` - View request status

### Provider Management
- `view-providers` - View providers
- `manage-providers` - Manage providers
- `view-facility-requests` - View facility product requests
- `view-provider-requests` - View provider product requests

### Order Management
- `view-orders` - View orders
- `create-orders` - Create orders
- `manage-orders` - Manage orders
- `view-order-totals` - View order totals

### Financial Management
- `view-financials` - View financial information
- `manage-financials` - Manage financial rules
- `manage-payments` - Manage payment processing
- `view-msc-pricing` - View MSC pricing
- `view-discounts` - View discounts
- `view-national-asp` - View national ASP pricing

### Clinical Features
- `view-mac-validation` - View MAC validation
- `manage-mac-validation` - Manage MAC validation
- `view-eligibility` - View eligibility checks
- `manage-eligibility` - Manage eligibility checks
- `view-pre-authorization` - View pre-authorization
- `manage-pre-authorization` - Manage pre-authorization

### Commission Management
- `view-commission` - View commission information
- `manage-commission` - Manage commission rules
- `view-payouts` - View commission payouts

### Organization Management
- `view-organizations` - View organizations
- `manage-organizations` - Manage organizations
- `view-facilities` - View facilities
- `manage-facilities` - Manage facilities
- `complete-organization-onboarding` - Complete organization onboarding

### User Management
- `view-users` - View user accounts
- `manage-users` - Manage user accounts
- `view-team` - View team members
- `manage-team` - Manage team members
- `manage-access-requests` - Manage access requests
- `manage-subrep-approvals` - Manage sub-rep approvals

### System Administration
- `view-settings` - View system settings
- `manage-settings` - Manage system settings
- `view-audit-logs` - View audit logs
- `manage-rbac` - Manage role-based access control
- `manage-system-config` - Manage system configuration
- `manage-integrations` - Manage system integrations
- `manage-api` - Manage API settings

### Document Management
- `view-documents` - View documents
- `manage-documents` - Manage documents and uploads

### Customer Management
- `view-customers` - View customer information
- `manage-customers` - Manage customer information

## Best Practices

1. **Always Check Permissions on Backend**: Frontend checks are for UX only.
2. **Use Specific Permissions**: Create granular permissions for better control.
3. **Group Related Permissions**: Users who can view often can also manage.
4. **Document Permission Requirements**: Always document what permissions are needed.
5. **Test Permission Scenarios**: Test with different role combinations.
6. **Fail Gracefully**: Provide helpful messages when permissions are denied.

## Example: Complete Feature Implementation

Here's a complete example of implementing a new admin feature with proper permissions:

### 1. Add Permission to Seeder

```php
// database/seeders/DatabaseSeeder.php
$permissions = [
    // ... existing permissions
    'view-reports' => 'View system reports',
    'manage-reports' => 'Create and edit reports',
];
```

### 2. Assign to Roles

```php
// database/seeders/DatabaseSeeder.php
'msc-admin' => [
    'permissions' => [
        // ... existing permissions
        'view-reports',
        'manage-reports',
    ],
],
```

### 3. Create Controller with Middleware

```php
// app/Http/Controllers/Admin/ReportController.php
class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-reports')->only(['index', 'show']);
        $this->middleware('permission:manage-reports')->only(['create', 'store', 'edit', 'update']);
    }
}
```

### 4. Add Routes with Middleware

```php
// routes/web.php
Route::middleware(['auth', 'permission:view-reports'])->group(function () {
    Route::get('/admin/reports', [ReportController::class, 'index']);
    Route::get('/admin/reports/{report}', [ReportController::class, 'show']);
});

Route::middleware(['auth', 'permission:manage-reports'])->group(function () {
    Route::get('/admin/reports/create', [ReportController::class, 'create']);
    Route::post('/admin/reports', [ReportController::class, 'store']);
});
```

### 5. Implement Frontend with Permission Checks

```tsx
// resources/js/Pages/Admin/Reports/Index.tsx
import { Can, useHasPermission } from '@/lib/permissions';

export default function ReportsIndex({ reports }) {
    const canManageReports = useHasPermission('manage-reports');
    
    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h1>Reports</h1>
                <Can permission="manage-reports">
                    <Button href="/admin/reports/create">
                        Create Report
                    </Button>
                </Can>
            </div>
            
            <div className="grid gap-4">
                {reports.map(report => (
                    <div key={report.id} className="p-4 border rounded">
                        <h3>{report.name}</h3>
                        <div className="flex gap-2 mt-4">
                            <Button href={`/admin/reports/${report.id}`}>
                                View
                            </Button>
                            <Can permission="manage-reports">
                                <Button href={`/admin/reports/${report.id}/edit`}>
                                    Edit
                                </Button>
                            </Can>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

## Troubleshooting

### Permission Not Working

1. Check if permission exists in database
2. Verify role has the permission assigned
3. Clear cache: `php artisan cache:clear`
4. Check middleware is applied to route
5. Verify user has the correct role

### Frontend Shows But Backend Denies

This is expected behavior. Frontend permission checks are for UX only. The backend is the source of truth for security.

### Need Complex Permission Logic

For complex scenarios, create custom middleware or use gate definitions in your AuthServiceProvider.

## Migration from Role-Based to Permission-Based

If migrating from role-based checks:

1. Replace `$user->hasRole('admin')` with `$user->hasPermission('specific-permission')`
2. Replace `@role('admin')` with `@can('specific-permission')`
3. Update React components from role checks to permission checks
4. Test thoroughly with each role to ensure correct access 