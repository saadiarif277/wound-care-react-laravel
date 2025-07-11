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
     * Assigns the 4-tier financial permissions to the correct role names:
     * - Healthcare Provider: common-financial-data (58) + my-financial-data (59)
     * - MSC Administrator & Super Admin: view-all-financial-data (60)
     * - Office Manager: no-financial-data (61) [already assigned]
     */
    public function up(): void
    {
        // Define the role-permission assignments with correct role names
        $rolePermissionAssignments = [
            // Healthcare Providers get common-financial-data and my-financial-data
            'Healthcare Provider' => [
                'common-financial-data',
                'my-financial-data'
            ],
            
            // MSC Administrator gets view-all-financial-data
            'MSC Administrator' => [
                'view-all-financial-data'
            ],
            
            // Super Admin also gets view-all-financial-data
            'Super Admin' => [
                'view-all-financial-data'
            ]
            
            // Office Manager already has no-financial-data assigned
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

        Log::info('4-tier financial permissions assigned to correct roles successfully', [
            'role_assignments' => $rolePermissionAssignments,
            'migration_timestamp' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the new assignments made in this migration
        $rolesToClean = ['Healthcare Provider', 'MSC Administrator', 'Super Admin'];
        $permissionsToRemove = ['common-financial-data', 'my-financial-data', 'view-all-financial-data'];

        foreach ($rolesToClean as $roleName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            
            if (!$role) {
                continue;
            }

            foreach ($permissionsToRemove as $permissionSlug) {
                $permission = DB::table('permissions')->where('slug', $permissionSlug)->first();
                
                if ($permission) {
                    DB::table('role_permission')
                        ->where('role_id', $role->id)
                        ->where('permission_id', $permission->id)
                        ->delete();
                        
                    Log::info("Removed permission '{$permissionSlug}' from role '{$roleName}' during rollback");
                }
            }
        }

        Log::info('Financial permission assignments rollback completed', [
            'rollback_timestamp' => now(),
        ]);
    }
};
