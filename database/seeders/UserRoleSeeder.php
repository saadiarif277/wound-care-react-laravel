<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions first
        $permissions = [
            // View permissions
            'view-users' => 'View users and user information',
            'view-financials' => 'View financial information and reports',
            'view-discounts' => 'View discounted pricing',
            'view-msc-pricing' => 'View MSC pricing information',
            'view-order-totals' => 'View order total amounts',
            'view-phi' => 'View protected health information',
            'view-reports' => 'View system reports',
            'view-customers' => 'View customer information and management',
            'view-analytics' => 'View analytics and dashboard reports',
            'view-products' => 'View product catalog and information',
            'view-providers' => 'View healthcare providers information',
            'view-product-requests' => 'View product requests and status',

            // Management permissions
            'edit-users' => 'Edit user information and settings',
            'delete-users' => 'Delete or deactivate users',
            'manage-products' => 'Manage product catalog',
            'manage-orders' => 'Manage orders and order processing',
            'manage-financials' => 'Manage financial settings and data',
            'manage-access-control' => 'Manage user access and permissions',
            'manage-system' => 'Manage system settings and configuration',
            'manage-pre-authorization' => 'Manage prior authorization requests',
            'manage-mac-validation' => 'Manage MAC validation processes',

            // Order permissions
            'create-orders' => 'Create new orders',
            'approve-orders' => 'Approve pending orders',
            'process-orders' => 'Process and fulfill orders',

            // Commission permissions
            'view-commission' => 'View commission information',
            'manage-commission' => 'Manage commission settings and tracking',
        ];

        foreach ($permissions as $slug => $description) {
            Permission::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $description,
            ]);
        }

        // Create roles and assign permissions
        $rolesWithPermissions = [
            'provider' => [
                'name' => 'Healthcare Provider',
                'description' => 'Healthcare provider with access to patient care tools',
                'permissions' => [
                    'create-orders',
                    'view-reports',
                ]
            ],
            'office-manager' => [
                'name' => 'Office Manager',
                'description' => 'Office manager with administrative access',
                'permissions' => [
                    'view-users',
                    'edit-users',
                    'create-orders',
                    'approve-orders',
                    'view-reports',
                    'view-order-totals',
                    'view-products',
                    'view-providers',
                    'view-product-requests',
                    'manage-pre-authorization',
                    'manage-mac-validation',
                ]
            ],
            'msc-rep' => [
                'name' => 'MSC Sales Rep',
                'description' => 'MSC sales representative with commission tracking',
                'permissions' => [
                    'view-users',
                    'create-orders',
                    'process-orders',
                    'view-commission',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-reports',
                ]
            ],
            'msc-subrep' => [
                'name' => 'MSC Sub Rep',
                'description' => 'MSC sub-representative with limited access',
                'permissions' => [
                    'create-orders',
                    'view-commission',
                    'view-reports',
                ]
            ],
            'msc-admin' => [
                'name' => 'MSC Admin',
                'description' => 'MSC administrator with full system access',
                'permissions' => [
                    'view-users',
                    'edit-users',
                    'delete-users',
                    'manage-products',
                    'manage-orders',
                    'view-financials',
                    'manage-financials',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-order-totals',
                    'view-commission',
                    'manage-commission',
                    'view-reports',
                    'manage-access-control',
                    'view-customers',
                    'view-analytics',
                    'create-orders',
                    'approve-orders',
                    'process-orders',
                ]
            ],
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Super administrator with complete system control',
                'permissions' => array_keys($permissions), // All permissions
            ],
        ];

        foreach ($rolesWithPermissions as $slug => $roleData) {
            $role = Role::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $roleData['name'],
                'description' => $roleData['description'],
            ]);

            // Attach permissions to role
            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        // Assign default provider role to existing users who don't have any roles
        $providerRole = Role::where('slug', 'provider')->first();
        if ($providerRole) {
            $usersWithoutRoles = User::doesntHave('roles')->get();
            foreach ($usersWithoutRoles as $user) {
                $user->roles()->attach($providerRole->id);
            }
        }

        $this->command->info('Robust RBAC roles and permissions seeded successfully!');
    }
}
