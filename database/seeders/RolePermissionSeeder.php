<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions for all major features
        $permissions = [
            // User Management
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Organization Management
            'view-organizations',
            'create-organizations',
            'edit-organizations',
            'delete-organizations',

            // Facility Management
            'view-facilities',
            'create-facilities',
            'edit-facilities',
            'delete-facilities',

            // Contact Management
            'view-contacts',
            'create-contacts',
            'edit-contacts',
            'delete-contacts',

            // Product Management
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',

            // Order Management
            'view-orders',
            'create-orders',
            'edit-orders',
            'delete-orders',

            // Financial Management
            'view-financials',
            'create-financials',
            'edit-financials',
            'delete-financials',

            // Commission Management
            'view-commissions',
            'create-commissions',
            'edit-commissions',
            'delete-commissions',
            'approve-commissions',
            'process-commissions',
            'view-commission-rules',
            'create-commission-rules',
            'edit-commission-rules',
            'delete-commission-rules',

            // Reports
            'view-reports',
            'generate-reports',
            'export-reports',

            // Settings
            'view-settings',
            'edit-settings',

            // Role & Permission Management
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'assign-roles',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => ucwords(str_replace('-', ' ', $permission)),
                'slug' => $permission,
                'description' => 'Permission to ' . str_replace('-', ' ', $permission),
            ]);
        }

        // Create roles with specific permissions
        $roles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Super Administrator with all permissions',
                'permissions' => $permissions,
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Administrator with most permissions',
                'permissions' => array_diff($permissions, ['delete-roles', 'assign-roles']),
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Manager with limited administrative permissions',
                'permissions' => [
                    'view-users', 'view-organizations', 'view-facilities',
                    'view-contacts', 'view-products', 'view-orders',
                    'view-financials', 'view-reports', 'view-settings',
                    'create-orders', 'edit-orders',
                    'generate-reports', 'export-reports',
                    'view-commissions', 'approve-commissions',
                    'view-commission-rules',
                ],
            ],
            'user' => [
                'name' => 'Regular User',
                'description' => 'Regular user with basic permissions',
                'permissions' => [
                    'view-contacts', 'view-products', 'view-orders',
                    'create-orders', 'view-reports',
                ],
            ],
        ];

        foreach ($roles as $slug => $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'slug' => $slug,
                'description' => $roleData['description'],
            ]);

            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->attach($permissionIds);
        }

        // Create a super-admin user if it doesn't exist
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
            ]
        );

        // Assign super-admin role to the user
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $user->roles()->sync([$superAdminRole->id]);
    }
}
