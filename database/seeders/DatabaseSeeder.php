<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        Schema::disableForeignKeyConstraints();

        // Clean up all tables
        $tables = [
            'role_permission',
            'user_role',
            'permissions',
            'roles',
            'product_requests',
            'commission_payouts',
            'commission_records',
            'commission_rules',
            'order_items',
            'orders',
            'msc_products',
            'msc_sales_reps',
            'facilities',
            'organizations',
            'users',
            'accounts',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

        // 1. Create base account
        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Default Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create permissions
        $permissions = [
            'view-users' => 'View user accounts',
            'edit-users' => 'Edit user accounts',
            'delete-users' => 'Delete user accounts',
            'manage-products' => 'Manage product catalog',
            'manage-orders' => 'Manage orders',
            'view-financials' => 'View financial information',
            'manage-financials' => 'Manage financial information',
            'view-msc-pricing' => 'View MSC pricing',
            'view-discounts' => 'View discounts',
            'view-order-totals' => 'View order totals',
            'view-commission' => 'View commission information',
            'manage-commission' => 'Manage commission rules',
            'view-reports' => 'View reports',
            'manage-access-control' => 'Manage access control',
            'view-customers' => 'View customer information',
            'view-analytics' => 'View analytics',
            'create-orders' => 'Create orders',
            'approve-orders' => 'Approve orders',
            'process-orders' => 'Process orders',
            'view-products' => 'View products',
            'view-providers' => 'View providers',
            'view-product-requests' => 'View product requests',
            'manage-pre-authorization' => 'Manage pre-authorization',
            'manage-mac-validation' => 'Manage MAC validation',
        ];

        $permissionIds = [];
        foreach ($permissions as $slug => $description) {
            $permissionIds[$slug] = DB::table('permissions')->insertGetId([
                'slug' => $slug,
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $description,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Create roles and assign permissions
        $rolesWithPermissions = [
            'provider' => [
                'name' => 'Healthcare Provider',
                'description' => 'Healthcare provider with access to patient care tools',
                'permissions' => [
                    'create-orders',
                    'view-reports',
                ]
            ],
            'office-manager' => [
                'name' => 'Office Manager',
                'description' => 'Office manager with administrative access',
                'permissions' => [
                    'view-users',
                    'edit-users',
                    'create-orders',
                    'approve-orders',
                    'view-reports',
                    'view-order-totals',
                    'view-products',
                    'view-providers',
                    'view-product-requests',
                    'manage-pre-authorization',
                    'manage-mac-validation',
                ]
            ],
            'msc-rep' => [
                'name' => 'MSC Sales Rep',
                'description' => 'MSC sales representative with commission tracking',
                'permissions' => [
                    'view-users',
                    'create-orders',
                    'process-orders',
                    'view-commission',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-reports',
                ]
            ],
            'msc-subrep' => [
                'name' => 'MSC Sub Rep',
                'description' => 'MSC sub-representative with limited access',
                'permissions' => [
                    'create-orders',
                    'view-commission',
                    'view-reports',
                ]
            ],
            'msc-admin' => [
                'name' => 'MSC Admin',
                'description' => 'MSC administrator with full system access',
                'permissions' => [
                    'view-users',
                    'edit-users',
                    'delete-users',
                    'manage-products',
                    'manage-orders',
                    'view-financials',
                    'manage-financials',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-order-totals',
                    'view-commission',
                    'manage-commission',
                    'view-reports',
                    'manage-access-control',
                    'view-customers',
                    'view-analytics',
                    'create-orders',
                    'approve-orders',
                    'process-orders',
                ]
            ],
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Super administrator with complete system control',
                'permissions' => array_keys($permissions), // All permissions
            ],
        ];

        $roleIds = [];
        foreach ($rolesWithPermissions as $slug => $roleData) {
            $roleId = DB::table('roles')->insertGetId([
                'slug' => $slug,
                'name' => $roleData['name'],
                'display_name' => $roleData['name'],
                'description' => $roleData['description'],
                'is_active' => true,
                'hierarchy_level' => match($slug) {
                    'super-admin' => 100,
                    'msc-admin' => 80,
                    'msc-rep' => 60,
                    'msc-subrep' => 50,
                    'office-manager' => 40,
                    'provider' => 20,
                    default => 0
                },
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleIds[$slug] = $roleId;

            // Insert role-permission relationships
            foreach ($roleData['permissions'] as $permissionSlug) {
                if (isset($permissionIds[$permissionSlug])) {
                    DB::table('role_permission')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$permissionSlug],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 4. Create users
        $users = [
            [
                'account_id' => $accountId,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@msc.com',
                'password' => Hash::make('secret'),
                'owner' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'provider@example.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Jane',
                'last_name' => 'Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Bob',
                'last_name' => 'Sales',
                'email' => 'rep@msc.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Alice',
                'last_name' => 'SubRep',
                'email' => 'subrep@msc.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $userIds = [];
        foreach ($users as $user) {
            $userId = DB::table('users')->insertGetId($user);
            $userIds[$user['email']] = $userId;
        }

        // 5. Assign roles to users
        $roleAssignments = [
            'msc-admin' => 'admin@msc.com',
            'provider' => 'provider@example.com',
            'office-manager' => 'manager@example.com',
            'msc-rep' => 'rep@msc.com',
            'msc-subrep' => 'subrep@msc.com',
        ];

        foreach ($roleAssignments as $roleSlug => $userEmail) {
            if (isset($roleIds[$roleSlug]) && isset($userIds[$userEmail])) {
                DB::table('user_role')->insert([
                    'user_id' => $userIds[$userEmail],
                    'role_id' => $roleIds[$roleSlug],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Create organization
        $organizationId = DB::table('organizations')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Test Healthcare Network',
            'email' => 'admin@testhealthcare.com',
            'phone' => '(555) 000-0000',
            'address' => '100 Healthcare Plaza',
            'city' => 'Medical City',
            'region' => 'MC',
            'country' => 'US',
            'postal_code' => '12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. Create facilities
        $facilities = [
            [
                'organization_id' => $organizationId,
                'name' => 'Main Medical Center',
                'facility_type' => 'hospital',
                'address' => '123 Healthcare Blvd',
                'city' => 'Medical City',
                'state' => 'MC',
                'zip_code' => '12345',
                'phone' => '(555) 123-4567',
                'email' => 'info@mainmedical.com',
                'npi' => '1234567890',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organizationId,
                'name' => 'Downtown Clinic',
                'facility_type' => 'clinic',
                'address' => '456 Downtown Ave',
                'city' => 'Metro City',
                'state' => 'MC',
                'zip_code' => '67890',
                'phone' => '(555) 987-6543',
                'email' => 'contact@downtownclinic.com',
                'npi' => '0987654321',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organizationId,
                'name' => 'Suburban Health Center',
                'facility_type' => 'clinic',
                'address' => '789 Suburban Dr',
                'city' => 'Suburbia',
                'state' => 'MC',
                'zip_code' => '11111',
                'phone' => '(555) 456-7890',
                'email' => 'info@suburbanhc.com',
                'npi' => '1122334455',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $facilityIds = [];
        foreach ($facilities as $facility) {
            $facilityId = DB::table('facilities')->insertGetId($facility);
            $facilityIds[] = $facilityId;
        }

        // 8. Create sales reps
        $salesReps = [
            [
                'name' => 'Bob Sales',
                'email' => 'rep@msc.com',
                'phone' => '(555) 111-2222',
                'territory' => 'North',
                'commission_rate_direct' => 5.00,
                'sub_rep_parent_share_percentage' => 50.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Alice SubRep',
                'email' => 'subrep@msc.com',
                'phone' => '(555) 333-4444',
                'territory' => 'North-East',
                'commission_rate_direct' => 4.00,
                'sub_rep_parent_share_percentage' => 50.00,
                'parent_rep_id' => 1, // Will be updated after insert
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $salesRepIds = [];
        foreach ($salesReps as $rep) {
            $repId = DB::table('msc_sales_reps')->insertGetId($rep);
            $salesRepIds[] = $repId;
        }

        // Update parent_rep_id for sub-rep
        DB::table('msc_sales_reps')
            ->where('id', $salesRepIds[1])
            ->update(['parent_rep_id' => $salesRepIds[0]]);

        // 9. Create products
        $products = [
            [
                'sku' => 'BIO-Q4154',
                'name' => 'Biovance',
                'description' => 'Decellularized, dehydrated human amniotic membrane that preserves the natural extracellular matrix components.',
                'manufacturer' => 'CELULARITY',
                'category' => 'SkinSubstitute',
                'q_code' => '4154',
                'price_per_sq_cm' => 550.64,
                'available_sizes' => json_encode([2, 4, 6, 8, 10.5, 16, 25, 36]),
                'graph_type' => 'Amniotic Membrane',
                'commission_rate' => 5.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'IMP-Q4262',
                'name' => 'Impax Dual Layer Membrane',
                'description' => 'Advanced dual-layer membrane designed for complex wound management.',
                'manufacturer' => 'LEGACY MEDICAL CONSULTANTS',
                'category' => 'SkinSubstitute',
                'q_code' => '4262',
                'price_per_sq_cm' => 169.86,
                'available_sizes' => json_encode([4, 6, 16, 24, 32]),
                'graph_type' => 'Dual Layer',
                'commission_rate' => 6.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Add more products as needed
        ];

        $productIds = [];
        foreach ($products as $product) {
            $productId = DB::table('msc_products')->insertGetId($product);
            $productIds[] = $productId;
        }

        // 10. Create product requests
        $productRequests = [
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $userIds['provider@example.com'],
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'JoSm001',
                'facility_id' => $facilityIds[0],
                'payer_name_submitted' => 'Medicare Part B',
                'payer_id' => 'MEDICARE',
                'expected_service_date' => Carbon::now()->addDays(7),
                'wound_type' => 'DFU',
                'order_status' => 'draft',
                'step' => 3,
                'total_order_value' => 450.00,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $userIds['provider@example.com'],
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'MaJo002',
                'facility_id' => $facilityIds[1],
                'payer_name_submitted' => 'Blue Cross Blue Shield',
                'payer_id' => 'BCBS',
                'expected_service_date' => Carbon::now()->addDays(5),
                'wound_type' => 'VLU',
                'order_status' => 'submitted',
                'step' => 6,
                'mac_validation_status' => 'passed',
                'eligibility_status' => 'eligible',
                'pre_auth_required_determination' => 'not_required',
                'total_order_value' => 680.00,
                'submitted_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($productRequests as $request) {
            DB::table('product_requests')->insert($request);
        }

        $this->command->info('Database seeded successfully!');
    }
}
