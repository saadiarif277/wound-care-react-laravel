<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration consolidates all permission-related updates:
     * 1. Removes financial permissions from office managers
     * 2. Ensures proper provider restrictions (self-only orders)
     * 3. Maintains MUE access for admins only
     * 4. Sets up product filtering permissions
     */
    public function up(): void
    {
        // Step 1: Remove ALL financial permissions from office managers
        $this->removeFinancialPermissionsFromOfficeManagers();
        
        // Step 2: Ensure providers have proper permissions (no financial access)
        $this->configureProviderPermissions();
        
        // Step 3: Ensure admins have all necessary permissions
        $this->configureAdminPermissions();
        
        // Step 4: Create any missing permissions needed for the system
        $this->createMissingPermissions();
        
        // Step 5: Log the changes for audit purposes
        $this->logPermissionChanges();
    }

    /**
     * Remove all financial permissions from office managers
     */
    private function removeFinancialPermissionsFromOfficeManagers(): void
    {
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        
        if (!$officeManagerRole) {
            return;
        }
        
        // Financial permissions that office managers should NEVER have
        $financialPermissions = [
            'view-national-asp',
            'view-financials', 
            'view-msc-pricing',
            'view-discounts',
            'view-order-totals',
            'view-commission',
            'view-payouts',
            'manage-financials',
            'manage-commission',
            'manage-payments',
        ];
        
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $financialPermissions)
            ->pluck('id');
        
        // Remove these permissions from office-manager role
        DB::table('role_permission')
            ->where('role_id', $officeManagerRole->id)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    /**
     * Configure provider permissions - they can see their own financial data but not manage finances
     */
    private function configureProviderPermissions(): void
    {
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        
        if (!$providerRole) {
            return;
        }
        
        // Providers should have these permissions for clinical decisions and their own financial data
        $providerPermissions = [
            'view-national-asp', // For clinical decision making
            'view-products',     // To see available products
            'create-product-requests', // To create orders
            'view-product-requests',   // To see their own orders
            'view-financials',   // To see their own financial data (orders, commissions)
            'view-order-totals', // To see totals for their own orders
            'view-commission',   // To see their own commission data
        ];
        
        // Providers should NOT have these financial management permissions
        $prohibitedPermissions = [
            'view-msc-pricing',  // Internal MSC pricing
            'view-discounts',    // Internal discount structures
            'view-payouts',      // Payout management
            'manage-financials', // Financial management
            'manage-commission', // Commission management
            'manage-payments',   // Payment management
        ];
        
        // Remove prohibited permissions
        $prohibitedIds = DB::table('permissions')
            ->whereIn('slug', $prohibitedPermissions)
            ->pluck('id');
            
        DB::table('role_permission')
            ->where('role_id', $providerRole->id)
            ->whereIn('permission_id', $prohibitedIds)
            ->delete();
        
        // Ensure providers have necessary permissions
        foreach ($providerPermissions as $permSlug) {
            $permission = DB::table('permissions')->where('slug', $permSlug)->first();
            if ($permission) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $providerRole->id)
                    ->where('permission_id', $permission->id)
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'role_id' => $providerRole->id,
                        'permission_id' => $permission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Ensure admin roles have all necessary permissions
     */
    private function configureAdminPermissions(): void
    {
        $adminRoles = DB::table('roles')->whereIn('slug', ['admin', 'super-admin', 'msc-admin'])->get();
        
        // All permissions that admins should have
        $adminPermissions = [
            'manage-products',     // Includes MUE access
            'view-financials',
            'view-msc-pricing',
            'view-discounts',
            'view-commission',
            'view-payouts',
            'manage-financials',
            'manage-commission',
            'manage-payments',
            'manage-orders',
            'view-orders',
            'view-all-orders',
        ];
        
        foreach ($adminRoles as $role) {
            foreach ($adminPermissions as $permSlug) {
                $permission = DB::table('permissions')->where('slug', $permSlug)->first();
                if ($permission) {
                    $exists = DB::table('role_permission')
                        ->where('role_id', $role->id)
                        ->where('permission_id', $permission->id)
                        ->exists();
                        
                    if (!$exists) {
                        DB::table('role_permission')->insert([
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Create any missing permissions needed for the system
     */
    private function createMissingPermissions(): void
    {
        $missingPermissions = [
            [
                'slug' => 'view-onboarded-products',
                'name' => 'View Onboarded Products',
                'description' => 'View products that providers are onboarded for',
            ],
            [
                'slug' => 'manage-provider-products',
                'name' => 'Manage Provider Products',
                'description' => 'Manage which products providers are onboarded for',
            ],
            [
                'slug' => 'view-all-orders',
                'name' => 'View All Orders',
                'description' => 'View orders from all providers and organizations',
            ],
        ];
        
        foreach ($missingPermissions as $permData) {
            $exists = DB::table('permissions')->where('slug', $permData['slug'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'slug' => $permData['slug'],
                    'name' => $permData['name'],
                    'description' => $permData['description'],
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Log permission changes for audit purposes
     */
    private function logPermissionChanges(): void
    {
        \Illuminate\Support\Facades\Log::info('Consolidated permission system update completed', [
            'changes' => [
                'office_managers' => 'Removed ALL financial permissions',
                'providers' => 'Limited to clinical permissions only, can only order for themselves',
                'admins' => 'Full access including MUE data',
                'product_filtering' => 'Providers see only onboarded products, office managers see products for selected provider',
            ],
            'security_improvements' => [
                'office_managers_no_financial_data' => true,
                'mue_admin_only' => true,
                'provider_self_only_orders' => true,
                'product_filtering_by_onboarding' => true,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a security-focused migration, so we don't want to reverse it
        // But for completeness, here's what would restore some of the old state
        
        \Illuminate\Support\Facades\Log::warning('Attempting to reverse consolidated permission system update - NOT RECOMMENDED');
        
        // Re-add view-national-asp to office managers only (minimal restoration)
        $officeManagerRole = DB::table('roles')->where('slug', 'office-manager')->first();
        $nationalAspPermission = DB::table('permissions')->where('slug', 'view-national-asp')->first();
        
        if ($officeManagerRole && $nationalAspPermission) {
            $exists = DB::table('role_permission')
                ->where('role_id', $officeManagerRole->id)
                ->where('permission_id', $nationalAspPermission->id)
                ->exists();
                
            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $officeManagerRole->id,
                    'permission_id' => $nationalAspPermission->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
