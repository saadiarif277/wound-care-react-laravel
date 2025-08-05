# Role-Based Access Control (RBAC) Implementation Guide

## 🎯 Overview

The MSC Wound Care Portal implements a comprehensive Role-Based Access Control (RBAC) system to ensure secure access to sensitive healthcare data and system functionality.

## 🏗️ RBAC Architecture

### Core Components
- **Users**: Individual system users
- **Roles**: Collections of permissions
- **Permissions**: Specific system capabilities
- **Organizations**: Data isolation boundaries
- **Facilities**: Location-based access controls

### Permission Model
```php
// Permission structure
'permissions' => [
    'resource' => [
        'view-{resource}',      // Read access
        'create-{resource}',    // Create access
        'edit-{resource}',      // Update access
        'delete-{resource}',    // Delete access
        'manage-{resource}',    // Full management
    ]
]
```

## 👥 User Roles

### MSC Administrator
**Purpose**: Complete system administration and oversight
```php
'msc-admin' => [
    'permissions' => [
        'view-users', 'create-users', 'edit-users', 'delete-users',
        'view-organizations', 'create-organizations', 'edit-organizations',
        'view-providers', 'create-providers', 'edit-providers',
        'view-orders', 'create-orders', 'edit-orders', 'manage-orders',
        'view-financials', 'manage-financials',
        'view-analytics', 'manage-analytics',
        'view-commission-rules', 'create-commission-rules', 'edit-commission-rules',
        'view-commissions', 'approve-commissions', 'process-commissions',
        'view-system-logs', 'manage-system-settings',
        'manage-rbac', 'view-audit-logs',
    ],
    'description' => 'Full system administration capabilities'
]
```

### MSC Sales Representative
**Purpose**: Manage sales territories and customer relationships
```php
'msc-rep' => [
    'permissions' => [
        'view-organizations', 'create-organizations', 'edit-organizations',
        'view-providers', 'create-providers',
        'view-orders', 'create-orders',
        'view-own-commissions',
        'view-analytics',
        'manage-customer-onboarding',
    ],
    'description' => 'Sales representative capabilities'
]
```

### MSC Sub-Representative
**Purpose**: Support sales activities under main representative
```php
'msc-subrep' => [
    'permissions' => [
        'view-assigned-organizations',
        'view-assigned-providers',
        'view-assigned-orders',
        'view-own-commissions',
        'assist-customer-onboarding',
    ],
    'description' => 'Sub-representative support capabilities'
]
```

### Healthcare Provider
**Purpose**: Clinical order management and patient care
```php
'provider' => [
    'permissions' => [
        'view-own-patients',
        'create-orders', 'view-own-orders', 'edit-own-orders',
        'view-products',
        'access-clinical-tools',
        'view-own-profile', 'edit-own-profile',
        'submit-insurance-verification',
    ],
    'description' => 'Healthcare provider capabilities'
]
```

### Office Manager
**Purpose**: Facility and staff management
```php
'office-manager' => [
    'permissions' => [
        'view-facility-staff',
        'manage-facility-providers',
        'view-facility-orders',
        'manage-facility-settings',
        'view-facility-analytics',
        'process-facility-paperwork',
    ],
    'description' => 'Office and facility management capabilities'
]
```

## 🔒 Permission Implementation

### Controller Protection
```php
class ProductRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        
        // Method-specific permissions
        $this->middleware('permission:view-orders')->only(['index', 'show']);
        $this->middleware('permission:create-orders')->only(['create', 'store']);
        $this->middleware('permission:edit-orders')->only(['edit', 'update']);
        $this->middleware('permission:delete-orders')->only(['destroy']);
        $this->middleware('permission:manage-orders')->only(['approve', 'reject']);
    }
}
```

### Blade Template Protection
```php
@can('create-orders')
    <a href="{{ route('orders.create') }}" class="btn btn-primary">
        Create New Order
    </a>
@endcan

@can('view-financials')
    <div class="financial-summary">
        <!-- Financial data -->
    </div>
@endcan
```

### API Route Protection
```php
// API routes with permission middleware
Route::middleware(['auth:sanctum', 'permission:view-orders'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:manage-orders'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
});
```

## 🏢 Organization-Based Access Control

### Data Isolation
```php
// Automatic organization scoping
class User extends Model
{
    use BelongsToOrganization;
    
    public function scopeForCurrentOrganization($query)
    {
        if (auth()->user()->hasRole('msc-admin')) {
            return $query; // Admin sees all
        }
        
        return $query->where('organization_id', auth()->user()->organization_id);
    }
}
```

### Cross-Organization Access
```php
trait CrossOrganizationAccess
{
    public function canAccessOrganization($organizationId): bool
    {
        // MSC Admins can access all organizations
        if ($this->hasRole('msc-admin')) {
            return true;
        }
        
        // MSC Reps can access assigned organizations
        if ($this->hasRole('msc-rep')) {
            return $this->assignedOrganizations->contains($organizationId);
        }
        
        // Others limited to their own organization
        return $this->organization_id === $organizationId;
    }
}
```

## 🏥 Facility-Based Access Control

### Provider-Facility Relationships
```php
class User extends Model
{
    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'facility_user')
                    ->withPivot(['can_order_for_facility', 'is_primary_location']);
    }
    
    public function canOrderForFacility($facilityId): bool
    {
        return $this->facilities()
                    ->wherePivot('facility_id', $facilityId)
                    ->wherePivot('can_order_for_facility', true)
                    ->exists();
    }
}
```

### Facility Scoping
```php
class Order extends Model
{
    public function scopeForUserFacilities($query, User $user)
    {
        if ($user->hasRole(['msc-admin', 'msc-rep'])) {
            return $query; // Full access
        }
        
        return $query->whereIn('facility_id', $user->facilities->pluck('id'));
    }
}
```

## 🔐 Advanced Permission Features

### Dynamic Permissions
```php
class PermissionService
{
    public function hasPermissionForResource(User $user, string $permission, $resource): bool
    {
        // Check basic permission
        if (!$user->hasPermission($permission)) {
            return false;
        }
        
        // Apply resource-specific logic
        return match(true) {
            $resource instanceof Order => $this->canAccessOrder($user, $resource),
            $resource instanceof Organization => $this->canAccessOrganization($user, $resource),
            $resource instanceof Facility => $this->canAccessFacility($user, $resource),
            default => true
        };
    }
    
    private function canAccessOrder(User $user, Order $order): bool
    {
        // MSC roles can access all orders
        if ($user->hasRole(['msc-admin', 'msc-rep'])) {
            return true;
        }
        
        // Providers can only access their own orders
        if ($user->hasRole('provider')) {
            return $order->provider_id === $user->id;
        }
        
        // Office managers can access facility orders
        if ($user->hasRole('office-manager')) {
            return $user->facilities->contains($order->facility_id);
        }
        
        return false;
    }
}
```

### Conditional Permissions
```php
class ConditionalPermissions
{
    public function canViewFinancials(User $user, $context = null): bool
    {
        // MSC Admins always can
        if ($user->hasRole('msc-admin')) {
            return true;
        }
        
        // MSC Reps can view their territory financials
        if ($user->hasRole('msc-rep') && $context instanceof Organization) {
            return $user->assignedOrganizations->contains($context->id);
        }
        
        // Providers cannot view financials
        return false;
    }
    
    public function canApproveOrders(User $user, Order $order): bool
    {
        // Only certain roles can approve
        if (!$user->hasPermission('approve-orders')) {
            return false;
        }
        
        // Additional business logic
        return $order->total_amount <= $user->approval_limit;
    }
}
```

## 📊 Permission Auditing

### Permission Changes Tracking
```php
class PermissionAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'changed_by_user_id',
        'permission_name',
        'action', // 'granted', 'revoked'
        'context',
        'timestamp',
        'ip_address',
    ];
    
    public static function logPermissionChange(
        User $user, 
        User $changedBy, 
        string $permission, 
        string $action
    ): void {
        static::create([
            'user_id' => $user->id,
            'changed_by_user_id' => $changedBy->id,
            'permission_name' => $permission,
            'action' => $action,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
        ]);
    }
}
```

### Access Monitoring
```php
class AccessMonitor
{
    public function logAccess(User $user, string $resource, string $action): void
    {
        AccessLog::create([
            'user_id' => $user->id,
            'resource' => $resource,
            'action' => $action,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }
    
    public function detectSuspiciousActivity(User $user): array
    {
        $recentAccess = AccessLog::where('user_id', $user->id)
                                 ->where('timestamp', '>=', now()->subHour())
                                 ->get();
                                 
        return [
            'unusual_resources' => $this->findUnusualResources($recentAccess),
            'high_frequency' => $this->detectHighFrequency($recentAccess),
            'off_hours_access' => $this->detectOffHoursAccess($recentAccess),
        ];
    }
}
```

## 🛠️ Implementation Guidelines

### Best Practices
1. **Principle of Least Privilege**: Grant minimum necessary permissions
2. **Regular Review**: Quarterly permission audits
3. **Role Segregation**: Separate conflicting duties
4. **Temporary Access**: Time-limited elevated permissions
5. **Documentation**: Maintain permission documentation

### Common Patterns
```php
// Check multiple permissions (ANY)
if ($user->hasAnyPermission(['view-orders', 'manage-orders'])) {
    // User has at least one permission
}

// Check multiple permissions (ALL)
if ($user->hasAllPermissions(['view-orders', 'view-financials'])) {
    // User has all required permissions
}

// Role-based checks
if ($user->hasRole(['msc-admin', 'msc-rep'])) {
    // User has MSC role
}

// Combined permission and context check
if ($user->hasPermission('edit-orders') && $user->canAccessOrganization($order->organization_id)) {
    // User can edit this specific order
}
```

## 🔧 Maintenance & Troubleshooting

### Permission Cache Management
```php
// Clear permission cache after changes
php artisan permission:cache-reset

// Rebuild permission cache
php artisan permission:cache-rebuild
```

### Common Issues
1. **Permission Not Working**: Check middleware order and cache
2. **Performance Issues**: Review permission queries and caching
3. **Role Conflicts**: Audit overlapping permissions
4. **Access Denied**: Verify user roles and organization membership

## 📚 Related Documentation

- [Security Overview](./SECURITY_OVERVIEW.md)
- [User Management Guide](../user-guides/ADMIN_USER_GUIDE.md)
- [API Security](../development/API_SECURITY.md)
