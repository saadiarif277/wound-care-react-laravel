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
        // Add view-providers permission if it doesn't exist
        $viewProvidersPermission = DB::table('permissions')->where('slug', 'view-providers')->first();
        
        if (!$viewProvidersPermission) {
            $viewProvidersPermissionId = DB::table('permissions')->insertGetId([
                'slug' => 'view-providers',
                'name' => 'View Providers',
                'description' => 'View providers',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $viewProvidersPermissionId = $viewProvidersPermission->id;
        }

        // Add manage-providers permission if it doesn't exist
        $manageProvidersPermission = DB::table('permissions')->where('slug', 'manage-providers')->first();
        
        if (!$manageProvidersPermission) {
            $manageProvidersPermissionId = DB::table('permissions')->insertGetId([
                'slug' => 'manage-providers',
                'name' => 'Manage Providers',
                'description' => 'Manage providers',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $manageProvidersPermissionId = $manageProvidersPermission->id;
        }

        // Add these permissions to msc-admin role
        $mscAdminRole = DB::table('roles')->where('slug', 'msc-admin')->first();
        
        if ($mscAdminRole) {
            // Check and add view-providers permission if not exists
            $hasViewPermission = DB::table('role_permission')
                ->where('role_id', $mscAdminRole->id)
                ->where('permission_id', $viewProvidersPermissionId)
                ->exists();
                
            if (!$hasViewPermission) {
                DB::table('role_permission')->insert([
                    'role_id' => $mscAdminRole->id,
                    'permission_id' => $viewProvidersPermissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Check and add manage-providers permission if not exists
            $hasManagePermission = DB::table('role_permission')
                ->where('role_id', $mscAdminRole->id)
                ->where('permission_id', $manageProvidersPermissionId)
                ->exists();
                
            if (!$hasManagePermission) {
                DB::table('role_permission')->insert([
                    'role_id' => $mscAdminRole->id,
                    'permission_id' => $manageProvidersPermissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Also add to office-manager role (view only)
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        
        if ($officeManagerRole) {
            $hasViewPermission = DB::table('role_permission')
                ->where('role_id', $officeManagerRole->id)
                ->where('permission_id', $viewProvidersPermissionId)
                ->exists();
                
            if (!$hasViewPermission) {
                DB::table('role_permission')->insert([
                    'role_id' => $officeManagerRole->id,
                    'permission_id' => $viewProvidersPermissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions from roles
        $viewProvidersPermission = DB::table('permissions')->where('slug', 'view-providers')->first();
        $manageProvidersPermission = DB::table('permissions')->where('slug', 'manage-providers')->first();
        
        if ($viewProvidersPermission) {
            DB::table('role_permission')->where('permission_id', $viewProvidersPermission->id)->delete();
        }
        
        if ($manageProvidersPermission) {
            DB::table('role_permission')->where('permission_id', $manageProvidersPermission->id)->delete();
        }
    }
};