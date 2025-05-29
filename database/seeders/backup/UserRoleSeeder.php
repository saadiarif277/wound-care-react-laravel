<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        Schema::disableForeignKeyConstraints();

        // Clean up all related tables
        DB::table('role_permission')->truncate();
        DB::table('user_role')->truncate();
        DB::table('permissions')->truncate();
        DB::table('roles')->truncate();

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

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

        // Create permissions with explicit UUIDs
        $permissionIds = [];
        foreach ($permissions as $slug => $description) {
            $id = (string) Str::uuid();
            $permissionIds[$slug] = $id;

            DB::table('permissions')->insert([
                'id' => $id,
                'slug' => $slug,
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $description,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
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

        // Create roles with explicit UUIDs and their permissions
        $roleIds = [];
        foreach ($rolesWithPermissions as $slug => $roleData) {
            $roleId = (string) Str::uuid();
            $roleIds[$slug] = $roleId;

            // Insert role
            DB::table('roles')->insert([
                'id' => $roleId,
                'slug' => $slug,
                'name' => $roleData['name'],
                'display_name' => $roleData['name'],
                'description' => $roleData['description'],
                'is_active' => true,
                'hierarchy_level' => match($slug) {
                    'super-admin' => 100,
                    'msc-admin' => 80,
                    'msc-rep' => 60,
                    'msc-subrep' => 50,
                    'office-manager' => 40,
                    'provider' => 20,
                    default => 0
                },
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert role-permission relationships
            foreach ($roleData['permissions'] as $permissionSlug) {
                if (isset($permissionIds[$permissionSlug])) {
                    DB::table('role_permission')->insert([
                        'id' => (string) Str::uuid(),
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$permissionSlug],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Assign default provider role to existing users who don't have any roles
        if (isset($roleIds['provider'])) {
            $usersWithoutRoles = User::doesntHave('roles')->get();
            foreach ($usersWithoutRoles as $user) {
                DB::table('user_role')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'role_id' => $roleIds['provider'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Robust RBAC roles and permissions seeded successfully!');
    }
}
