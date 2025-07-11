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
     * Assigns the 4-tier financial permissions to appropriate roles:
     * - Providers: common-financial-data (58) + my-financial-data (59)
     * - Admins: view-all-financial-data (60)
     * - Office Managers: no-financial-data (61)
     */
    public function up(): void
    {
        // Define the role-permission assignments
        $rolePermissionAssignments = [
            // Providers get common-financial-data and my-financial-data
            'Provider' => [
                'common-financial-data',
                'my-financial-data'
            ],
            
            // Admins get view-all-financial-data
            'Admin' => [
                'view-all-financial-data'
            ],
            
            // Office Managers get no-financial-data
            'Office Manager' => [
                'no-financial-data'
            ]
        ];

        foreach ($rolePermissionAssignments as $roleName => $permissionSlugs) {
            // Find the role
            $role = DB::table('roles')->where('name', $roleName)->first();
            
            if (!$role) {
                Log::warning("Role '{$roleName}' not found, skipping permission assignment");
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                // Find the permission
                $permission = DB::table('permissions')->where('slug', $permissionSlug)->first();
                
                if (!$permission) {
                    Log::warning("Permission '{$permissionSlug}' not found, skipping");
                    continue;
                }

                // Check if the role-permission relationship already exists
                $existingAssignment = DB::table('role_permission')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permission->id)
                    ->first();

                if (!$existingAssignment) {
                    // Assign the permission to the role
                    DB::table('role_permission')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info("Assigned permission '{$permissionSlug}' (ID: {$permission->id}) to role '{$roleName}' (ID: {$role->id})");
                } else {
                    Log::info("Permission '{$permissionSlug}' already assigned to role '{$roleName}', skipping");
                }
            }
        }

        // Remove old financial permissions from all roles to ensure clean state
        $oldFinancialPermissions = ['view-financials', 'manage-financials'];
        
        foreach ($oldFinancialPermissions as $oldPermissionSlug) {
            $oldPermission = DB::table('permissions')->where('slug', $oldPermissionSlug)->first();
            
            if ($oldPermission) {
                DB::table('role_permission')
                    ->where('permission_id', $oldPermission->id)
                    ->delete();
                    
                Log::info("Removed old financial permission '{$oldPermissionSlug}' from all roles");
            }
        }

        Log::info('4-tier financial permissions assigned to roles successfully', [
            'role_assignments' => $rolePermissionAssignments,
            'migration_timestamp' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 4-tier financial permission assignments
        $permissionsToRemove = [
            'common-financial-data',
            'my-financial-data', 
            'view-all-financial-data',
            'no-financial-data'
        ];

        foreach ($permissionsToRemove as $permissionSlug) {
            $permission = DB::table('permissions')->where('slug', $permissionSlug)->first();
            
            if ($permission) {
                DB::table('role_permission')
                    ->where('permission_id', $permission->id)
                    ->delete();
                    
                Log::info("Removed permission '{$permissionSlug}' from all roles during rollback");
            }
        }

        // Restore old financial permissions to Admin role
        $adminRole = DB::table('roles')->where('name', 'Admin')->first();
        $oldPermissions = ['view-financials', 'manage-financials'];
        
        if ($adminRole) {
            foreach ($oldPermissions as $oldPermissionSlug) {
                $oldPermission = DB::table('permissions')->where('slug', $oldPermissionSlug)->first();
                
                if ($oldPermission) {
                    $existingAssignment = DB::table('role_permission')
                        ->where('role_id', $adminRole->id)
                        ->where('permission_id', $oldPermission->id)
                        ->first();

                    if (!$existingAssignment) {
                        DB::table('role_permission')->insert([
                            'role_id' => $adminRole->id,
                            'permission_id' => $oldPermission->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        Log::info('4-tier financial permission assignments rolled back successfully', [
            'rollback_timestamp' => now(),
        ]);
    }
};
