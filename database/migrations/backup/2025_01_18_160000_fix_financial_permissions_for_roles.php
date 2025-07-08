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
        // Get role IDs
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        $mscAdminRole = DB::table('roles')->where('slug', 'msc-admin')->first();
        
        if (!$officeManagerRole || !$providerRole) {
            return; // Roles not found, skip migration
        }
        
        // Get permission IDs
        $financialPermissions = DB::table('permissions')
            ->whereIn('slug', [
                'view-financials',
                'view-msc-pricing', 
                'view-discounts',
                'view-order-totals',
                'view-commission',
                'manage-financials',
                'manage-commission'
            ])
            ->pluck('id', 'slug');
            
        // Remove ALL financial permissions from office managers
        DB::table('role_permission')
            ->where('role_id', $officeManagerRole->id)
            ->whereIn('permission_id', $financialPermissions->values())
            ->delete();
            
        // Ensure providers have proper financial permissions
        // Providers should be able to see MSC pricing and their own financial data
        $providerFinancialPermissions = [
            'view-msc-pricing',
            'view-discounts',
            'view-order-totals',
            'view-financials', // To see their own financial data
        ];
        
        foreach ($providerFinancialPermissions as $permSlug) {
            if (isset($financialPermissions[$permSlug])) {
                // Check if provider already has this permission
                $exists = DB::table('role_permission')
                    ->where('role_id', $providerRole->id)
                    ->where('permission_id', $financialPermissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $financialPermissions[$permSlug],
                        'role_id' => $providerRole->id,
                    ]);
                }
            }
        }
        
        // Ensure MSC Admin has ALL financial permissions
        if ($mscAdminRole) {
            foreach ($financialPermissions as $permSlug => $permId) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $mscAdminRole->id)
                    ->where('permission_id', $permId)
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permId,
                        'role_id' => $mscAdminRole->id,
                    ]);
                }
            }
        }
        
        // Log the changes to Laravel log instead of activity_logs table
        \Illuminate\Support\Facades\Log::info('Fixed financial permissions', [
            'description' => 'Removed from office managers, ensured providers and admins have correct access',
            'removed_from_office_manager' => array_keys($financialPermissions->toArray()),
            'ensured_for_provider' => $providerFinancialPermissions,
            'ensured_for_admin' => array_keys($financialPermissions->toArray()),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a security fix, we don't want to reverse it
        // But for completeness, here's what would restore the old (incorrect) state
        
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        
        if (!$officeManagerRole) {
            return;
        }
        
        // Re-add the financial permissions that were removed (NOT RECOMMENDED)
        $financialPermissions = DB::table('permissions')
            ->whereIn('slug', [
                'view-financials',
                'view-msc-pricing',
                'view-discounts', 
                'view-order-totals'
            ])
            ->pluck('id');
            
        foreach ($financialPermissions as $permId) {
            DB::table('role_permission')->insert([
                'permission_id' => $permId,
                'role_id' => $officeManagerRole->id,
            ]);
        }
    }
};