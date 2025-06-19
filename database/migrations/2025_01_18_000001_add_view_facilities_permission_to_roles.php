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
        // Ensure 'view-facilities' permission exists
        $viewFacilitiesPermission = DB::table('permissions')
            ->where('slug', 'view-facilities')
            ->first();
            
        if (!$viewFacilitiesPermission) {
            $viewFacilitiesPermissionId = DB::table('permissions')->insertGetId([
                'slug' => 'view-facilities',
                'name' => 'View Facilities',
                'description' => 'View facilities',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $viewFacilitiesPermissionId = $viewFacilitiesPermission->id;
        }

        // Get all roles that have 'create-product-requests' permission
        $createRequestsPermission = DB::table('permissions')
            ->where('slug', 'create-product-requests')
            ->first();
            
        if ($createRequestsPermission) {
            $rolesWithCreateRequests = DB::table('role_permission')
                ->where('permission_id', $createRequestsPermission->id)
                ->pluck('role_id');

            // Add 'view-facilities' permission to these roles if they don't have it
            foreach ($rolesWithCreateRequests as $roleId) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $viewFacilitiesPermissionId)
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $viewFacilitiesPermissionId,
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
        // This is a permission addition, we don't remove it on rollback
        // to avoid breaking existing functionality
    }
};