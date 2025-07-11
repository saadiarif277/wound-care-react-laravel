<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Implements the 4-tier financial data access control system:
     * 1. "common-financial-data" - National ASP & default MSC pricing (40% off ASP)
     * 2. "my-financial-data" - Provider-specific pricing, what they owe, commissions
     * 3. "view-all-financial-data" - Admin access to all financial information
     * 4. "no-financial-data" - Office managers see NO pricing info (headers/titles hidden)
     */
    public function up(): void
    {
        // Create the 4-tier financial data permissions
        $newPermissions = [
            'common-financial-data' => [
                'name' => 'Common Financial Data',
                'description' => 'Can see national ASP pricing and default MSC price (40% off ASP). This is general market pricing information.',
            ],
            'my-financial-data' => [
                'name' => 'My Financial Data', 
                'description' => 'Can see provider-specific financial data including what they owe, special pricing assigned to them, and commissions for sales reps.',
            ],
            'view-all-financial-data' => [
                'name' => 'View All Financial Data',
                'description' => 'Admin-level access to see all financial information across all providers, commissions, and pricing tiers.',
            ],
            'no-financial-data' => [
                'name' => 'No Financial Data',
                'description' => 'Cannot see any pricing information including national ASP. For users like Office Managers who have no business need to know pricing.',
            ],
        ];

        $permissionIds = [];
        
        // Create permissions if they don't exist
        foreach ($newPermissions as $slug => $permissionData) {
            $exists = DB::table('permissions')->where('slug', $slug)->exists();
            if (!$exists) {
                $permissionIds[$slug] = DB::table('permissions')->insertGetId([
                    'slug' => $slug,
                    'name' => $permissionData['name'],
                    'description' => $permissionData['description'],
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $permissionIds[$slug] = DB::table('permissions')->where('slug', $slug)->value('id');
            }
        }

        // Get role IDs
        $roles = DB::table('roles')->pluck('id', 'slug');

        // Assign permissions to roles based on hierarchy
        $rolePermissionMapping = [
            'super-admin' => ['view-all-financial-data'],
            'admin' => ['view-all-financial-data'], 
            'msc-admin' => ['view-all-financial-data'],
            'sales-rep' => ['my-financial-data'],
            'msc-rep' => ['my-financial-data'],
            'provider' => ['common-financial-data'],
            'office-manager' => ['no-financial-data'],
        ];

        foreach ($rolePermissionMapping as $roleSlug => $permissions) {
            if (!isset($roles[$roleSlug])) continue;

            foreach ($permissions as $permissionSlug) {
                if (!isset($permissionIds[$permissionSlug])) continue;

                $exists = DB::table('role_permission')
                    ->where('role_id', $roles[$roleSlug])
                    ->where('permission_id', $permissionIds[$permissionSlug])
                    ->exists();

                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'role_id' => $roles[$roleSlug],
                        'permission_id' => $permissionIds[$permissionSlug],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Remove old financial permissions from roles to avoid conflicts
        $oldFinancialPermissions = [
            'view-financials',
            'view-msc-pricing',
            'view-discounts',
            'view-order-totals',
        ];

        foreach ($oldFinancialPermissions as $oldPermissionSlug) {
            $oldPermission = DB::table('permissions')->where('slug', $oldPermissionSlug)->first();
            if ($oldPermission) {
                // Remove from all roles except keep manage-financials for admins
                if ($oldPermissionSlug !== 'manage-financials') {
                    DB::table('role_permission')
                        ->where('permission_id', $oldPermission->id)
                        ->delete();
                }
            }
        }

        // Log the changes
        Log::info('4-tier financial data permissions implemented', [
            'permissions_created' => array_keys($newPermissions),
            'role_mappings' => $rolePermissionMapping,
            'migration_timestamp' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 4-tier financial data permissions
        $permissionsToRemove = [
            'common-financial-data',
            'my-financial-data', 
            'view-all-financial-data',
            'no-financial-data',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $permissionsToRemove)
            ->pluck('id');

        // Remove from role_permission table
        DB::table('role_permission')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        // Remove permissions
        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();

        // Restore old financial permissions to their original roles
        $oldFinancialPermissions = [
            'view-financials' => ['admin', 'super-admin', 'msc-admin', 'sales-rep', 'msc-rep', 'provider'],
            'view-msc-pricing' => ['admin', 'super-admin', 'msc-admin', 'sales-rep', 'msc-rep', 'provider'],
            'view-discounts' => ['admin', 'super-admin', 'msc-admin', 'sales-rep', 'msc-rep'],
            'view-order-totals' => ['admin', 'super-admin', 'msc-admin', 'sales-rep', 'msc-rep', 'provider'],
        ];

        $roles = DB::table('roles')->pluck('id', 'slug');

        foreach ($oldFinancialPermissions as $permissionSlug => $rolesList) {
            $permission = DB::table('permissions')->where('slug', $permissionSlug)->first();
            if ($permission) {
                foreach ($rolesList as $roleSlug) {
                    if (isset($roles[$roleSlug])) {
                        $exists = DB::table('role_permission')
                            ->where('role_id', $roles[$roleSlug])
                            ->where('permission_id', $permission->id)
                            ->exists();

                        if (!$exists) {
                            DB::table('role_permission')->insert([
                                'role_id' => $roles[$roleSlug],
                                'permission_id' => $permission->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        Log::info('4-tier financial data permissions rolled back', [
            'permissions_removed' => $permissionsToRemove,
            'rollback_timestamp' => now(),
        ]);
    }
};
