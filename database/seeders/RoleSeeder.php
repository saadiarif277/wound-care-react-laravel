<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            [
                'name' => 'provider',
                'display_name' => 'Healthcare Provider',
                'description' => 'Healthcare providers/clinicians who request wound care products and services',
                'hierarchy_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'office_manager',
                'display_name' => 'Office Manager',
                'description' => 'Facility-attached managers with provider oversight and financial restrictions',
                'hierarchy_level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_rep',
                'display_name' => 'MSC Sales Representative',
                'description' => 'Primary MSC sales representatives with commission tracking',
                'hierarchy_level' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_subrep',
                'display_name' => 'MSC Sub-Representative',
                'description' => 'Sub-representatives with limited access under primary reps',
                'hierarchy_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_admin',
                'display_name' => 'MSC Administrator',
                'description' => 'MSC internal administrators with system management access',
                'hierarchy_level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert roles
        foreach ($roles as $role) {
            DB::table('user_roles')->insert($role);
        }

        // Assign permissions to roles
        $rolePermissions = [
            'provider' => [
                'view_product_requests',
                'create_product_requests',
                'edit_product_requests',
                'view_facilities',
                'view_products',
            ],
            'office_manager' => [
                'view_product_requests',
                'create_product_requests',
                'edit_product_requests',
                'view_facilities',
                'manage_facility_users',
                'view_products',
                'view_commissions',
            ],
            'msc_rep' => [
                'view_product_requests',
                'create_product_requests',
                'edit_product_requests',
                'process_product_requests',
                'ship_product_requests',
                'deliver_product_requests',
                'view_facilities',
                'view_products',
                'view_commissions',
                'view_sales_reps',
            ],
            'msc_subrep' => [
                'view_product_requests',
                'create_product_requests',
                'edit_product_requests',
                'view_facilities',
                'view_products',
                'view_commissions',
            ],
            'msc_admin' => [
                // Admin gets all permissions
                'view_product_requests',
                'create_product_requests',
                'edit_product_requests',
                'delete_product_requests',
                'approve_product_requests',
                'reject_product_requests',
                'process_product_requests',
                'ship_product_requests',
                'deliver_product_requests',
                'cancel_product_requests',
                'view_facilities',
                'create_facilities',
                'edit_facilities',
                'delete_facilities',
                'manage_facility_users',
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'manage_user_roles',
                'view_roles',
                'create_roles',
                'edit_roles',
                'delete_roles',
                'assign_roles',
                'view_commissions',
                'create_commissions',
                'edit_commissions',
                'delete_commissions',
                'approve_commissions',
                'process_commissions',
                'view_sales_reps',
                'create_sales_reps',
                'edit_sales_reps',
                'delete_sales_reps',
                'manage_sales_territories',
                'view_products',
                'create_products',
                'edit_products',
                'delete_products',
                'manage_product_categories',
            ],
        ];

        // Assign permissions to roles
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = DB::table('user_roles')->where('name', $roleName)->first();
            foreach ($permissions as $permission) {
                $permissionId = DB::table('permissions')->where('name', $permission)->first()->id;
                DB::table('role_permission')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
