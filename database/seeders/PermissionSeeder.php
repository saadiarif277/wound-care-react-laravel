<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Product Request Permissions
        $productRequestPermissions = [
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
        ];

        // Facility Permissions
        $facilityPermissions = [
            'view_facilities',
            'create_facilities',
            'edit_facilities',
            'delete_facilities',
            'manage_facility_users',
        ];

        // User Management Permissions
        $userPermissions = [
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_user_roles',
        ];

        // Role Management Permissions
        $rolePermissions = [
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'assign_roles',
        ];

        // Commission Management Permissions
        $commissionPermissions = [
            'view_commissions',
            'create_commissions',
            'edit_commissions',
            'delete_commissions',
            'approve_commissions',
            'process_commissions',
        ];

        // Sales Rep Management Permissions
        $salesRepPermissions = [
            'view_sales_reps',
            'create_sales_reps',
            'edit_sales_reps',
            'delete_sales_reps',
            'manage_sales_territories',
        ];

        // Product Management Permissions
        $productPermissions = [
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'manage_products',
            'manage_product_categories',
        ];

        // Additional System Permissions
        $systemPermissions = [
            'view_providers',
            'manage_pre_authorization',
            'view_requests',
            'manage_orders',
            'view_settings',
            'manage_subrep_approvals',
            'manage_rbac',
            'manage_access_control',
            'view_commission',
            'manage_system_config',
            'view_audit_logs',
            'manage_clinical_rules',
            'manage_recommendation_rules',
            'manage_commission_engine',
            'view_customers',
            'view_team',
            'view_access_requests',
            'approve_access_requests',
        ];

        // Combine all permissions
        $allPermissions = array_merge(
            $productRequestPermissions,
            $facilityPermissions,
            $userPermissions,
            $rolePermissions,
            $commissionPermissions,
            $salesRepPermissions,
            $productPermissions,
            $systemPermissions
        );

        // Insert permissions
        foreach ($allPermissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'slug' => Str::slug($permission),
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
