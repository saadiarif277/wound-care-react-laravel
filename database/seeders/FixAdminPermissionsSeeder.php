<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class FixAdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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

        // Create permissions if they don't exist
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Get or create the msc-admin role
        $adminRole = Role::firstOrCreate(['name' => 'msc-admin']);
        
        // Assign all permissions to the admin role
        $adminRole->syncPermissions(Permission::all());

        // Find all users with admin role and ensure they have the role assigned
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'msc-admin');
        })->get();

        foreach ($adminUsers as $user) {
            // Re-assign the role to refresh permissions
            $user->syncRoles(['msc-admin']);
        }

        // Also check for users with admin in their email and give them admin role
        $potentialAdmins = User::where('email', 'like', '%admin%')
            ->orWhere('email', 'like', '%msc%')
            ->get();

        foreach ($potentialAdmins as $user) {
            if (!$user->hasRole('msc-admin')) {
                $user->assignRole('msc-admin');
            }
        }

        $this->command->info('Admin permissions have been fixed and synchronized.');
        $this->command->info('Total permissions: ' . Permission::count());
        $this->command->info('Admin role has ' . $adminRole->permissions()->count() . ' permissions');
        $this->command->info('Admin users updated: ' . ($adminUsers->count() + $potentialAdmins->count()));
    }
}