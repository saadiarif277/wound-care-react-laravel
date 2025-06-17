<?php

// Quick script to fix admin permissions
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

// Ensure we have all required permissions
$permissions = [
    // Order Management
    'manage-orders',
    'view-orders',
    'create-orders',
    'edit-orders',
    'delete-orders',
    'generate-ivr',
    
    // Role & Permission Management
    'manage-roles',
    'view-roles',
    'create-roles',
    'edit-roles',
    'delete-roles',
    'manage-permissions',
    'view-permissions',
    'assign-permissions',
    
    // Access Control
    'manage-access-control',
    'view-access-control',
    
    // User Management
    'manage-users',
    'view-users',
    'create-users',
    'edit-users',
    'delete-users',
    
    // Customer Management
    'manage-customers',
    'view-customers',
    
    // Facility Management
    'manage-facilities',
    'view-facilities',
    'create-facilities',
    'edit-facilities',
    'delete-facilities',
    
    // Provider Management
    'manage-providers',
    'view-providers',
    'create-providers',
    'edit-providers',
    'delete-providers',
    
    // Product Management
    'manage-products',
    'view-products',
    
    // Commission Management
    'view-commissions',
    'create-commissions',
    'edit-commissions',
    'delete-commissions',
    'approve-commissions',
    'process-commissions',
    
    // Document Management
    'manage-documents',
    
    // Access Requests
    'view-access-requests',
    'approve-access-requests',
];

echo "Creating permissions...\n";
// Create permissions if they don't exist
foreach ($permissions as $permissionName) {
    Permission::firstOrCreate(['name' => $permissionName]);
    echo "Created/verified permission: $permissionName\n";
}

echo "\nTotal permissions: " . Permission::count() . "\n";

// Get or create the msc-admin role
$adminRole = Role::firstOrCreate(['name' => 'msc-admin']);
echo "\nAdmin role found/created: " . $adminRole->name . "\n";

// Assign all permissions to the admin role
$adminRole->syncPermissions(Permission::all());
echo "Synced all " . Permission::count() . " permissions to admin role\n";

// Find all users with admin role and ensure they have the role assigned
$adminUsers = User::whereHas('roles', function ($query) {
    $query->where('name', 'msc-admin');
})->get();

echo "\nFound " . $adminUsers->count() . " users with admin role\n";

foreach ($adminUsers as $user) {
    // Re-assign the role to refresh permissions
    $user->syncRoles(['msc-admin']);
    echo "Refreshed permissions for: " . $user->email . "\n";
}

// Also check for users with admin in their email and give them admin role
$potentialAdmins = User::where('email', 'like', '%admin%')
    ->orWhere('email', 'like', '%msc%')
    ->get();

echo "\nFound " . $potentialAdmins->count() . " potential admin users\n";

foreach ($potentialAdmins as $user) {
    if (!$user->hasRole('msc-admin')) {
        $user->assignRole('msc-admin');
        echo "Assigned admin role to: " . $user->email . "\n";
    }
}

echo "\nAdmin permissions have been fixed and synchronized!\n";
echo "Admin role has " . $adminRole->permissions()->count() . " permissions\n";