<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the permission if it doesn't exist
        $permission = Permission::where('slug', 'manage-orders')->first();
        if (!$permission) {
            $permission = Permission::create([
                'name' => 'Manage Orders',
                'slug' => 'manage-orders',
                'description' => 'Ability to manage orders and Docuseal templates'
            ]);
        }

        // Get admin roles
        $adminRole = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        // Assign to admin role
        if ($adminRole && !$adminRole->hasPermission('manage-orders')) {
            $adminRole->permissions()->attach($permission->id);
        }

        // Assign to super-admin role
        if ($superAdminRole && !$superAdminRole->hasPermission('manage-orders')) {
            $superAdminRole->permissions()->attach($permission->id);
        }

        // Also create field mapping related permissions
        $fieldMappingPermissions = [
            ['name' => 'View Field Mappings', 'slug' => 'view-field-mappings', 'description' => 'View template field mappings'],
            ['name' => 'Create Field Mappings', 'slug' => 'create-field-mappings', 'description' => 'Create new field mappings'],
            ['name' => 'Edit Field Mappings', 'slug' => 'edit-field-mappings', 'description' => 'Edit existing field mappings'],
            ['name' => 'Delete Field Mappings', 'slug' => 'delete-field-mappings', 'description' => 'Delete field mappings'],
            ['name' => 'Manage Canonical Fields', 'slug' => 'manage-canonical-fields', 'description' => 'Manage canonical field definitions']
        ];

        foreach ($fieldMappingPermissions as $permData) {
            $perm = Permission::firstOrCreate($permData);
            
            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
            if ($superAdminRole) {
                $superAdminRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'manage-orders',
            'view-field-mappings',
            'create-field-mappings',
            'edit-field-mappings',
            'delete-field-mappings',
            'manage-canonical-fields'
        ];

        foreach ($permissions as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            if ($permission) {
                // Remove from all roles first
                $permission->roles()->detach();
                // Then delete the permission
                $permission->delete();
            }
        }
    }
};