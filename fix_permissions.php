<?php

// Script to fix permissions for admin@msc.com

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    // Get the admin@msc.com user
    $user = User::where('email', 'admin@msc.com')->first();
    if (!$user) {
        echo "User admin@msc.com not found\n";
        exit;
    }

    echo "Found user: " . $user->name . " (ID: " . $user->id . ", Email: " . $user->email . ")\n\n";

    // Create or get manage-orders permission
    $permission = Permission::where('slug', 'manage-orders')->first();
    if (!$permission) {
        $permission = Permission::create([
            'name' => 'Manage Orders',
            'slug' => 'manage-orders',
            'description' => 'Ability to manage orders and DocuSeal templates'
        ]);
        echo "Created manage-orders permission\n";
    } else {
        echo "Found existing manage-orders permission\n";
    }

    // Check user's current roles
    $userRoles = $user->roles;
    echo "\nCurrent roles: " . ($userRoles->isEmpty() ? 'None' : $userRoles->pluck('name')->implode(', ')) . "\n";

    // Get or create admin role
    $adminRole = Role::where('slug', 'admin')->first();
    if (!$adminRole) {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Administrator role with full access'
        ]);
        echo "Created admin role\n";
    }

    // Ensure user has admin role
    if (!$user->roles()->where('roles.id', $adminRole->id)->exists()) {
        $user->roles()->attach($adminRole->id);
        echo "Assigned admin role to user\n";
    }

    // Ensure admin role has manage-orders permission
    if (!$adminRole->permissions()->where('permissions.id', $permission->id)->exists()) {
        $adminRole->permissions()->attach($permission->id);
        echo "Added manage-orders permission to admin role\n";
    }

    // Also add other field mapping permissions
    $additionalPermissions = [
        ['name' => 'View Field Mappings', 'slug' => 'view-field-mappings', 'description' => 'View template field mappings'],
        ['name' => 'Create Field Mappings', 'slug' => 'create-field-mappings', 'description' => 'Create new field mappings'],
        ['name' => 'Edit Field Mappings', 'slug' => 'edit-field-mappings', 'description' => 'Edit existing field mappings'],
        ['name' => 'Delete Field Mappings', 'slug' => 'delete-field-mappings', 'description' => 'Delete field mappings'],
        ['name' => 'Manage Canonical Fields', 'slug' => 'manage-canonical-fields', 'description' => 'Manage canonical field definitions']
    ];

    foreach ($additionalPermissions as $permData) {
        $perm = Permission::where('slug', $permData['slug'])->first();
        if (!$perm) {
            $perm = Permission::create($permData);
            echo "Created permission: {$permData['name']}\n";
        }
        
        if (!$adminRole->permissions()->where('permissions.id', $perm->id)->exists()) {
            $adminRole->permissions()->attach($perm->id);
            echo "Added {$permData['slug']} permission to admin role\n";
        }
    }

    // Clear any cached permissions
    if (method_exists($user, 'flushCache')) {
        $user->flushCache();
    }

    DB::commit();

    echo "\n✅ Permission setup complete!\n";
    echo "\nThe admin@msc.com user now has:\n";
    echo "- Admin role\n";
    echo "- manage-orders permission\n";
    echo "- All field mapping permissions\n";
    echo "\n⚠️  IMPORTANT: Please log out and log back in as admin@msc.com for changes to take effect.\n";

} catch (\Exception $e) {
    DB::rollback();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}