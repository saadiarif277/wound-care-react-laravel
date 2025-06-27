<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new permissions for episode management
        $newPermissions = [
            'view-episodes' => 'View episode information',
            'manage-episodes' => 'Manage episodes and IVR workflows',
            'view-ivr-management' => 'View IVR management dashboard',
            'manage-ivr' => 'Manage IVR reminders and settings',
            'export-ivr-data' => 'Export IVR data',
            'view-ai-insights' => 'View AI-powered insights and predictions',
            'use-voice-commands' => 'Use voice command interface',
            'view-enhanced-dashboard' => 'Access enhanced dashboard with AI features',
        ];

        $permissionIds = [];
        foreach ($newPermissions as $slug => $description) {
            $exists = DB::table('permissions')->where('slug', $slug)->exists();
            if (!$exists) {
                $permissionIds[$slug] = DB::table('permissions')->insertGetId([
                    'slug' => $slug,
                    'name' => ucwords(str_replace('-', ' ', $slug)),
                    'description' => $description,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Get role IDs
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        $mscAdminRole = DB::table('roles')->where('slug', 'msc-admin')->first();

        // Assign permissions to roles
        if ($providerRole) {
            $providerPermissions = [
                'view-episodes',
                'use-voice-commands',
            ];

            foreach ($providerPermissions as $permission) {
                if (isset($permissionIds[$permission])) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $providerRole->id,
                        'permission_id' => $permissionIds[$permission],
                    ]);
                }
            }
        }

        if ($officeManagerRole) {
            $officeManagerPermissions = [
                'view-episodes',
                'view-ivr-management',
                'use-voice-commands',
            ];

            foreach ($officeManagerPermissions as $permission) {
                if (isset($permissionIds[$permission])) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $officeManagerRole->id,
                        'permission_id' => $permissionIds[$permission],
                    ]);
                }
            }
        }

        if ($mscAdminRole) {
            // Admins get all new permissions
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $mscAdminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        // Update existing manage-orders permission to include episode management for admins
        $manageOrdersPermission = DB::table('permissions')->where('slug', 'manage-orders')->first();
        if ($manageOrdersPermission) {
            DB::table('permissions')
                ->where('id', $manageOrdersPermission->id)
                ->update([
                    'description' => 'Manage orders and episode workflows',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the permissions
        $permissionsToRemove = [
            'view-episodes',
            'manage-episodes',
            'view-ivr-management',
            'manage-ivr',
            'export-ivr-data',
            'view-ai-insights',
            'use-voice-commands',
            'view-enhanced-dashboard',
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

        // Revert manage-orders description
        DB::table('permissions')
            ->where('slug', 'manage-orders')
            ->update([
                'description' => 'Manage orders',
                'updated_at' => now(),
            ]);
    }
};