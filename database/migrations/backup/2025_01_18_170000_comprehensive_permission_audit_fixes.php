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
        $roles = DB::table('roles')->pluck('id', 'slug');
        
        // Get permission IDs
        $permissions = DB::table('permissions')->pluck('id', 'slug');
        
        // 1. FIX PROVIDER PERMISSIONS
        // Providers should be able to see their own requests and documents
        $providerAdditions = [
            'view-documents',           // Need to view clinical documents
            'view-provider-requests',   // Should see their own requests
            'view-msc-pricing',        // Already added in previous migration but ensuring
            'view-discounts',          // Already added in previous migration but ensuring
            'view-order-totals',       // Already added in previous migration but ensuring
            'view-financials',         // Already added in previous migration but ensuring
        ];
        
        foreach ($providerAdditions as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['provider'])) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roles['provider'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permissions[$permSlug],
                        'role_id' => $roles['provider'],
                    ]);
                }
            }
        }
        
        // 2. FIX OFFICE MANAGER PERMISSIONS
        // Office managers should have document access but NO financial visibility
        $officeManagerAdditions = [
            'view-documents',           // Need to view clinical documents
            'manage-documents',         // Can upload documents
            'view-team',               // Should see team members in their facility
            'view-users',              // Limited view of users in their facility
        ];
        
        // Remove any financial permissions that might have been added
        $officeManagerRemovals = [
            'view-financials',
            'view-msc-pricing',
            'view-discounts',
            'view-order-totals',
            'view-commission',
            'manage-financials',
        ];
        
        foreach ($officeManagerAdditions as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['office-manager'])) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roles['office-manager'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permissions[$permSlug],
                        'role_id' => $roles['office-manager'],
                    ]);
                }
            }
        }
        
        // Remove financial permissions
        foreach ($officeManagerRemovals as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['office-manager'])) {
                DB::table('role_permission')
                    ->where('role_id', $roles['office-manager'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->delete();
            }
        }
        
        // 3. FIX MSC REP PERMISSIONS
        // MSC Reps need some additional permissions for their workflow
        $mscRepAdditions = [
            'view-product-requests',    // Should see requests from their customers
            'view-facility-requests',   // Should see facility requests
            'view-provider-requests',   // Should see provider requests
            'view-facilities',          // Need to see facilities
            'view-providers',           // Need to see providers
            'view-documents',           // Need to see documents
            'create-product-requests',  // Can create requests on behalf of providers
            'view-organizations',       // Should see their assigned organizations
        ];
        
        foreach ($mscRepAdditions as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['msc-rep'])) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roles['msc-rep'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permissions[$permSlug],
                        'role_id' => $roles['msc-rep'],
                    ]);
                }
            }
        }
        
        // 4. FIX MSC SUBREP PERMISSIONS
        // Sub-reps need basic viewing permissions
        $mscSubrepAdditions = [
            'view-customers',           // Should see their customers
            'view-facilities',          // Need to see facilities
            'view-product-requests',    // Should see requests
            'view-organizations',       // Should see their assigned organizations
        ];
        
        foreach ($mscSubrepAdditions as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['msc-subrep'])) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roles['msc-subrep'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permissions[$permSlug],
                        'role_id' => $roles['msc-subrep'],
                    ]);
                }
            }
        }
        
        // 5. FIX MSC ADMIN PERMISSIONS
        // MSC Admin is missing some critical permissions
        $mscAdminAdditions = [
            'view-msc-pricing',         // Should definitely see MSC pricing
            'view-discounts',           // Should see all discounts
            'view-national-asp',        // Should see national ASP
            'create-product-requests',  // Should be able to create requests
            'manage-roles',             // Should manage roles
            'manage-permissions',       // Should manage permissions
            'view-commission',          // Should see commission details
            'create-orders',            // Should be able to create orders
            'view-products',            // Missing basic product viewing
            'view-pre-authorization',   // Should see pre-auth status
            'view-eligibility',         // Should see eligibility
            'view-mac-validation',      // Should see MAC validation
        ];
        
        foreach ($mscAdminAdditions as $permSlug) {
            if (isset($permissions[$permSlug]) && isset($roles['msc-admin'])) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roles['msc-admin'])
                    ->where('permission_id', $permissions[$permSlug])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('role_permission')->insert([
                        'permission_id' => $permissions[$permSlug],
                        'role_id' => $roles['msc-admin'],
                    ]);
                }
            }
        }
        
        // 6. CREATE MISSING PERMISSIONS
        // Some permissions that should exist but don't
        $newPermissions = [
            'view-phi' => 'View Protected Health Information',
            'manage-phi' => 'Manage Protected Health Information',
            'export-data' => 'Export data and reports',
            'view-commission-details' => 'View detailed commission breakdowns',
            'bypass-ivr' => 'Bypass IVR requirements',
            'approve-orders' => 'Approve submitted orders',
            'manage-manufacturers' => 'Manage manufacturer settings',
            'view-manufacturers' => 'View manufacturer information',
        ];
        
        foreach ($newPermissions as $slug => $description) {
            if (!isset($permissions[$slug])) {
                $permId = DB::table('permissions')->insertGetId([
                    'slug' => $slug,
                    'name' => ucwords(str_replace('-', ' ', $slug)),
                    'description' => $description,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Assign new permissions to appropriate roles
                if ($slug === 'view-phi' || $slug === 'manage-phi') {
                    // PHI permissions for providers and above
                    foreach (['provider', 'msc-rep', 'msc-admin', 'super-admin'] as $roleSlug) {
                        if (isset($roles[$roleSlug])) {
                            DB::table('role_permission')->insert([
                                'permission_id' => $permId,
                                'role_id' => $roles[$roleSlug],
                            ]);
                        }
                    }
                }
                
                if ($slug === 'export-data') {
                    // Export permissions for office managers and above
                    foreach (['office-manager', 'msc-rep', 'msc-admin', 'super-admin'] as $roleSlug) {
                        if (isset($roles[$roleSlug])) {
                            DB::table('role_permission')->insert([
                                'permission_id' => $permId,
                                'role_id' => $roles[$roleSlug],
                            ]);
                        }
                    }
                }
                
                if ($slug === 'view-commission-details') {
                    // Detailed commission for reps and admins
                    foreach (['msc-rep', 'msc-admin', 'super-admin'] as $roleSlug) {
                        if (isset($roles[$roleSlug])) {
                            DB::table('role_permission')->insert([
                                'permission_id' => $permId,
                                'role_id' => $roles[$roleSlug],
                            ]);
                        }
                    }
                }
                
                if ($slug === 'bypass-ivr' || $slug === 'approve-orders') {
                    // Admin only permissions
                    foreach (['msc-admin', 'super-admin'] as $roleSlug) {
                        if (isset($roles[$roleSlug])) {
                            DB::table('role_permission')->insert([
                                'permission_id' => $permId,
                                'role_id' => $roles[$roleSlug],
                            ]);
                        }
                    }
                }
                
                if ($slug === 'manage-manufacturers' || $slug === 'view-manufacturers') {
                    // Manufacturer permissions
                    if ($slug === 'view-manufacturers') {
                        // All roles can view manufacturers
                        foreach (['provider', 'office-manager', 'msc-rep', 'msc-subrep', 'msc-admin', 'super-admin'] as $roleSlug) {
                            if (isset($roles[$roleSlug])) {
                                DB::table('role_permission')->insert([
                                    'permission_id' => $permId,
                                    'role_id' => $roles[$roleSlug],
                                ]);
                            }
                        }
                    } else {
                        // Only admins can manage
                        foreach (['msc-admin', 'super-admin'] as $roleSlug) {
                            if (isset($roles[$roleSlug])) {
                                DB::table('role_permission')->insert([
                                    'permission_id' => $permId,
                                    'role_id' => $roles[$roleSlug],
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        // 7. REMOVE UNUSED/DANGEROUS PERMISSIONS
        // The 'complete-organization-onboarding' permission is not assigned to any role
        // 'manage-all-organizations' should only be for super-admin (already is)
        
        // 8. LOG ALL CHANGES
        \Illuminate\Support\Facades\Log::info('Comprehensive permission audit completed', [
            'provider_additions' => $providerAdditions,
            'office_manager_additions' => $officeManagerAdditions,
            'office_manager_removals' => $officeManagerRemovals,
            'msc_rep_additions' => $mscRepAdditions,
            'msc_subrep_additions' => $mscSubrepAdditions,
            'msc_admin_additions' => $mscAdminAdditions,
            'new_permissions' => array_keys($newPermissions),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a comprehensive security fix
        // Reverting would restore insecure permission states
        // Therefore, we'll leave this empty to prevent accidental rollback
    }
};