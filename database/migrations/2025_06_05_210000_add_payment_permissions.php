<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add manage-payments permission if it doesn't exist
        $permission = DB::table('permissions')->where('slug', 'manage-payments')->first();
        
        if (!$permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'slug' => 'manage-payments',
                'name' => 'Manage Payments',
                'description' => 'Record and manage provider payments',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add this permission to msc-admin role
            $mscAdminRole = DB::table('roles')->where('slug', 'msc-admin')->first();
            
            if ($mscAdminRole && $permissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $mscAdminRole->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Also add to office-manager if they should be able to record payments
            $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
            
            if ($officeManagerRole && $permissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $officeManagerRole->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ensure provider management permissions are properly assigned
        $viewProvidersPermission = DB::table('permissions')->where('slug', 'view-providers')->first();
        $manageProvidersPermission = DB::table('permissions')->where('slug', 'manage-providers')->first();
        
        if ($viewProvidersPermission && $manageProvidersPermission) {
            // Ensure msc-admin has these permissions
            $mscAdminRole = DB::table('roles')->where('slug', 'msc-admin')->first();
            
            if ($mscAdminRole) {
                // Check and add view-providers permission if not exists
                $hasViewPermission = DB::table('role_permission')
                    ->where('role_id', $mscAdminRole->id)
                    ->where('permission_id', $viewProvidersPermission->id)
                    ->exists();
                    
                if (!$hasViewPermission) {
                    DB::table('role_permission')->insert([
                        'role_id' => $mscAdminRole->id,
                        'permission_id' => $viewProvidersPermission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                // Check and add manage-providers permission if not exists
                $hasManagePermission = DB::table('role_permission')
                    ->where('role_id', $mscAdminRole->id)
                    ->where('permission_id', $manageProvidersPermission->id)
                    ->exists();
                    
                if (!$hasManagePermission) {
                    DB::table('role_permission')->insert([
                        'role_id' => $mscAdminRole->id,
                        'permission_id' => $manageProvidersPermission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove manage-payments permission
        $permission = DB::table('permissions')->where('slug', 'manage-payments')->first();
        
        if ($permission) {
            // Remove from role_permission pivot table
            DB::table('role_permission')->where('permission_id', $permission->id)->delete();
            
            // Remove the permission
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};