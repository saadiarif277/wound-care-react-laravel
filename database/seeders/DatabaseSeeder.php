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
        // 0. Disable foreign key checks
        Schema::disableForeignKeyConstraints();

                // 1. Truncate all tables (in reverse dependency order)
        $tables = [
            // Junction tables first
            'role_permission',
            'user_role',
            'facility_user',
            'product_request_products',
            'provider_products',

            // Dependent tables
            'docuseal_submissions',
            'docuseal_templates',
            'docuseal_folders',
            'commission_payouts',
            'commission_records',
            'commission_rules',
            'order_items',
            'orders',
            'product_requests',
            'provider_profiles',
            'patient_manufacturer_ivr_episodes',  // Episode data

            // Core entity tables
            'msc_products',
            'categories',
            'manufacturers',
            'msc_sales_reps',
            'facilities',
            'organizations',
            'permissions',
            'roles',
            'users',
            'accounts',
        ];

        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            } catch (\Exception $e) {
                // Table might not exist yet, skip it
                $this->command->warn("Could not truncate table '{$table}': " . $e->getMessage());
            }
        }

        // 2. Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

        // 3. Create base account
        $accountId = DB::table('accounts')->insertGetId([
            'name'       => 'Default Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Call OrganizationSeeder to create multiple organizations
        $this->call(OrganizationSeeder::class);

        // 5. Create permissions
        $permissions = [
            'view-dashboard'                        => 'View dashboard and analytics',
            'view-products'                         => 'View product catalog',
            'create-product-requests'               => 'Create new product requests',
            'view-product-requests'                 => 'View product requests',
            'view-facility-requests'                => 'View facility product requests',
            'view-provider-requests'                => 'View provider product requests',
            'view-request-status'                   => 'View request status',
            'view-mac-validation'                   => 'View MAC validation',
            'manage-mac-validation'                 => 'Manage MAC validation',
            'view-eligibility'                      => 'View eligibility checks',
            'manage-eligibility'                    => 'Manage eligibility checks',
            'view-pre-authorization'                => 'View pre-authorization',
            'manage-pre-authorization'              => 'Manage pre-authorization',
            'manage-products'                       => 'Manage product catalog',
            'view-msc-pricing'                      => 'View MSC pricing',
            'view-discounts'                        => 'View discounts',
            'view-national-asp'                     => 'View national ASP pricing',
            'view-providers'                        => 'View providers',
            'manage-providers'                      => 'Manage providers',
            'view-facilities'                       => 'View facilities',
            'manage-facilities'                     => 'Manage facilities',
            'view-documents'                        => 'View documents',
            'manage-documents'                      => 'Manage documents and uploads',
            'view-orders'                           => 'View orders',
            'create-orders'                         => 'Create orders',
            'manage-orders'                         => 'Manage orders',
            'view-order-totals'                     => 'View order totals',
            'view-commission'                       => 'View commission information',
            'manage-commission'                     => 'Manage commission rules',
            'view-payouts'                          => 'View commission payouts',
            'view-customers'                        => 'View customer information',
            'manage-customers'                      => 'Manage customer information',
            'view-team'                             => 'View team members',
            'manage-team'                           => 'Manage team members',
            'complete-organization-onboarding'      => 'Complete organization onboarding process',
            'view-users'                            => 'View user accounts',
            'manage-users'                          => 'Manage user accounts',
            'view-organizations'                    => 'View organizations',
            'manage-organizations'                  => 'Manage organizations',
            'manage-access-requests'                => 'Manage access requests',
            'manage-subrep-approvals'               => 'Manage sub-rep approvals',
            'view-settings'                         => 'View system settings',
            'manage-settings'                       => 'Manage system settings',
            'view-audit-logs'                       => 'View audit logs',
            'manage-rbac'                           => 'Manage role-based access control',
            'manage-system-config'                  => 'Manage system configuration',
            'manage-integrations'                   => 'Manage system integrations',
            'manage-api'                            => 'Manage API settings',
            'view-financials'                       => 'View financial information',
            'manage-financials'                     => 'Manage financial rules',
            'view-reports'                          => 'View reports and analytics',
            'manage-payments'                       => 'Manage payment processing',
            'view-analytics'                        => 'View analytics dashboard',
            'manage-menus'                          => 'Manage navigation menus',
            'manage-roles'                          => 'Manage user roles',
            'manage-permissions'                    => 'Manage permissions',
            'manage-all-organizations'              => 'Manage all organizations (super admin)',
        ];

        $permissionIds = [];
        foreach ($permissions as $slug => $description) {
            $permissionIds[$slug] = DB::table('permissions')->insertGetId([
                'slug'        => $slug,
                'name'        => ucwords(str_replace('-', ' ', $slug)),
                'description' => $description,
                'guard_name'  => 'web',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // 6. Create roles and assign permissions
        $rolesWithPermissions = [
            'provider' => [
                'name'        => 'Healthcare Provider',
                'description' => 'Healthcare provider with access to patient care tools',
                'permissions' => [
                    'view-dashboard',
                    'create-product-requests',
                    'view-product-requests',
                    'view-request-status',
                    'view-mac-validation',
                    'manage-mac-validation',
                    'view-eligibility',
                    'manage-eligibility',
                    'view-pre-authorization',
                    'manage-pre-authorization',
                    'view-products',
                    'view-national-asp',
                    'view-orders',
                    'create-orders',
                    'view-facilities',  // Added to support QuickRequest
                ],
            ],
            'office-manager' => [
                'name'        => 'Office Manager',
                'description' => 'Office manager with administrative access',
                'permissions' => [
                    'view-dashboard',
                    'create-product-requests',
                    'view-product-requests',
                    'view-facility-requests',
                    'view-provider-requests',
                    'view-request-status',
                    'view-mac-validation',
                    'manage-mac-validation',
                    'view-eligibility',
                    'manage-eligibility',
                    'view-pre-authorization',
                    'manage-pre-authorization',
                    'view-products',
                    'view-national-asp',
                    'view-providers',
                    'manage-providers',
                    'view-facilities',
                    'view-orders',
                    'create-orders',
                    'view-reports',
                ],
            ],
            'msc-rep' => [
                'name'        => 'MSC Sales Rep',
                'description' => 'MSC sales representative with commission tracking',
                'permissions' => [
                    'view-dashboard',
                    'view-orders',
                    'create-orders',
                    'view-order-totals',
                    'view-commission',
                    'view-payouts',
                    'view-customers',
                    'manage-customers',
                    'view-team',
                    'manage-team',
                    'view-products',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-reports',
                    'view-financials',
                ],
            ],
            'msc-subrep' => [
                'name'        => 'MSC Sub Rep',
                'description' => 'MSC sub-representative with limited access',
                'permissions' => [
                    'view-dashboard',
                    'view-orders',
                    'create-orders',
                    'view-commission',
                    'view-products',
                    'view-msc-pricing',
                    'view-discounts',
                    'view-reports',
                ],
            ],
            'msc-admin' => [
                'name'        => 'MSC Administrator',
                'description' => 'MSC administrator with system management access',
                'permissions' => [
                    'view-dashboard',
                    'view-product-requests',
                    'manage-products',
                    'manage-orders',
                    'view-order-totals',
                    'view-financials',
                    'manage-financials',
                    'manage-commission',
                    'view-payouts',
                    'view-customers',
                    'manage-customers',
                    'view-facilities',
                    'manage-facilities',
                    'view-providers',
                    'manage-providers',
                    'manage-documents',
                    'manage-team',
                    'view-users',
                    'manage-users',
                    'view-organizations',
                    'manage-organizations',
                    'manage-access-requests',
                    'manage-subrep-approvals',
                    'view-settings',
                    'manage-settings',
                    'view-audit-logs',
                    'manage-rbac',
                    'manage-system-config',
                    'manage-integrations',
                    'manage-api',
                    'view-reports',
                    'manage-payments',
                    'view-analytics',
                    'manage-menus',
                ],
            ],
            'super-admin' => [
                'name'        => 'Super Admin',
                'description' => 'Super administrator with complete system control',
                'permissions' => array_keys($permissions),
            ],
        ];

        $roleIds = [];
        foreach ($rolesWithPermissions as $slug => $roleData) {
            $roleId = DB::table('roles')->insertGetId([
                'slug'            => $slug,
                'name'            => $roleData['name'],
                'display_name'    => $roleData['name'],
                'description'     => $roleData['description'],
                'is_active'       => true,
                'hierarchy_level' => match ($slug) {
                    'super-admin'   => 100,
                    'msc-admin'     => 80,
                    'msc-rep'       => 60,
                    'msc-subrep'    => 50,
                    'office-manager'=> 40,
                    'provider'      => 20,
                    default         => 0,
                },
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $roleIds[$slug] = $roleId;

            foreach ($roleData['permissions'] as $permissionSlug) {
                if (isset($permissionIds[$permissionSlug])) {
                    DB::table('role_permission')->insert([
                        'role_id'       => $roleId,
                        'permission_id' => $permissionIds[$permissionSlug],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }

        // 7. Create users
        $users = [
            [
                'account_id' => $accountId,
                'first_name' => 'Admin',
                'last_name'  => 'User',
                'email'      => 'admin@msc.com',
                'password'   => Hash::make('secret'),
                'owner'      => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'John',
                'last_name'  => 'Smith',
                'email'      => 'provider@example.com',
                'password'   => Hash::make('secret'),
                'owner'      => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Jane',
                'last_name'  => 'Manager',
                'email'      => 'manager@example.com',
                'password'   => Hash::make('secret'),
                'owner'      => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Bob',
                'last_name'  => 'Sales',
                'email'      => 'rep@msc.com',
                'password'   => Hash::make('secret'),
                'owner'      => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountId,
                'first_name' => 'Alice',
                'last_name'  => 'SubRep',
                'email'      => 'subrep@msc.com',
                'password'   => Hash::make('secret'),
                'owner'      => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $userIds = [];
        foreach ($users as $user) {
            $userIds[$user['email']] = DB::table('users')->insertGetId($user);
        }

        // 8. Assign roles to users
        $roleAssignments = [
            'msc-admin'     => 'admin@msc.com',
            'provider'      => 'provider@example.com',
            'office-manager'=> 'manager@example.com',
            'msc-rep'       => 'rep@msc.com',
            'msc-subrep'    => 'subrep@msc.com',
        ];

        foreach ($roleAssignments as $roleSlug => $email) {
            if (isset($roleIds[$roleSlug], $userIds[$email])) {
                DB::table('user_role')->insert([
                    'user_id'    => $userIds[$email],
                    'role_id'    => $roleIds[$roleSlug],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 9. Create organization (legacy - kept for facilities association)
        $organizationId = DB::table('organizations')->insertGetId([
            'account_id'  => $accountId,
            'name'        => 'Test Healthcare Network',
            'email'       => 'admin@testhealthcare.com',
            'phone'       => '(555) 000-0000',
            'address'     => '100 Healthcare Plaza',
            'city'        => 'Medical City',
            'region'      => 'MC',
            'country'     => 'US',
            'postal_code' => '12345',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 10. Create facilities
        $facilities = [
            [
                'organization_id' => $organizationId,
                'name'            => 'Main Medical Center',
                'facility_type'   => 'hospital',
                'address'         => '123 Healthcare Blvd',
                'city'            => 'Medical City',
                'state'           => 'MC',
                'zip_code'        => '12345',
                'phone'           => '(555) 123-4567',
                'email'           => 'info@mainmedical.com',
                'npi'             => '1234567890',
                'active'          => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'organization_id' => $organizationId,
                'name'            => 'Downtown Clinic',
                'facility_type'   => 'clinic',
                'address'         => '456 Downtown Ave',
                'city'            => 'Metro City',
                'state'           => 'MC',
                'zip_code'        => '67890',
                'phone'           => '(555) 987-6543',
                'email'           => 'contact@downtownclinic.com',
                'npi'             => '0987654321',
                'active'          => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'organization_id' => $organizationId,
                'name'            => 'Suburban Health Center',
                'facility_type'   => 'clinic',
                'address'         => '789 Suburban Dr',
                'city'            => 'Suburbia',
                'state'           => 'MC',
                'zip_code'        => '11111',
                'phone'           => '(555) 456-7890',
                'email'           => 'info@suburbanhc.com',
                'npi'             => '1122334455',
                'active'          => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ];

        $facilityIds = [];
        foreach ($facilities as $facility) {
            $facilityIds[] = DB::table('facilities')->insertGetId($facility);
        }

        // Associate provider with facilities
        DB::table('facility_user')->insert([
            ['user_id' => $userIds['provider@example.com'], 'facility_id' => $facilityIds[0], 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userIds['provider@example.com'], 'facility_id' => $facilityIds[1], 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Associate office manager with facilities
        DB::table('facility_user')->insert([
            ['user_id' => $userIds['manager@example.com'], 'facility_id' => $facilityIds[0], 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userIds['manager@example.com'], 'facility_id' => $facilityIds[1], 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userIds['manager@example.com'], 'facility_id' => $facilityIds[2], 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 10. Create sales reps
        $salesReps = [
            [
                'name'                            => 'Bob Sales',
                'email'                           => 'rep@msc.com',
                'phone'                           => '(555) 111-2222',
                'territory'                       => 'North',
                'commission_rate_direct'          => 5.00,
                'sub_rep_parent_share_percentage' => 50.00,
                'is_active'                       => true,
                'created_at'                      => now(),
                'updated_at'                      => now(),
            ],
            [
                'name'                            => 'Alice SubRep',
                'email'                           => 'subrep@msc.com',
                'phone'                           => '(555) 333-4444',
                'territory'                       => 'North-East',
                'commission_rate_direct'          => 4.00,
                'sub_rep_parent_share_percentage' => 50.00,
                'is_active'                       => true,
                'created_at'                      => now(),
                'updated_at'                      => now(),
            ],
        ];

        $salesRepIds = [];
        foreach ($salesReps as $rep) {
            $salesRepIds[] = DB::table('msc_sales_reps')->insertGetId($rep);
        }

        // Link sub-rep to parent rep
        DB::table('msc_sales_reps')
            ->where('id', $salesRepIds[1])
            ->update(['parent_rep_id' => $salesRepIds[0]]);

        // 11. Create products
        $products = [
            [
                'sku'                => 'BIO-Q4154',
                'name'               => 'Biovance',
                'description'        => 'Decellularized, dehydrated human amniotic membrane that preserves the natural extracellular matrix components.',
                'manufacturer'       => 'CELULARITY',
                'category'           => 'SkinSubstitute',
                'q_code'             => '4154',
                'price_per_sq_cm'    => 550.64,
                'available_sizes'    => json_encode([2, 4, 6, 8, 10.5, 16, 25, 36]),
                'graph_type'         => 'Amniotic Membrane',
                'commission_rate'    => 5.0,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'sku'                => 'IMP-Q4262',
                'name'               => 'Impax Dual Layer Membrane',
                'description'        => 'Advanced dual-layer membrane designed for complex wound management.',
                'manufacturer'       => 'LEGACY MEDICAL CONSULTANTS',
                'category'           => 'SkinSubstitute',
                'q_code'             => '4262',
                'price_per_sq_cm'    => 169.86,

                'available_sizes'    => json_encode([4, 6, 16, 24, 32]),
                'graph_type'         => 'Dual Layer',
                'commission_rate'    => 6.0,
                'is_active'          => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ];

        foreach ($products as $product) {
            DB::table('msc_products')->insert($product);
        }

        // 12. Create product requests
        $productRequests = [
            [
                'request_number'                  => 'PR-' . strtoupper(uniqid()),
                'provider_id'                     => $userIds['provider@example.com'],
                'patient_fhir_id'                 => 'Patient/' . uniqid(),
                'patient_display_id'              => 'JoSm001',
                'facility_id'                     => $facilityIds[0],
                'payer_name_submitted'            => 'Medicare Part B',
                'payer_id'                        => 'MEDICARE',
                'expected_service_date'           => Carbon::now()->addDays(7),
                'wound_type'                      => 'DFU',
                'order_status'                    => 'draft',
                'step'                            => 3,
                'total_order_value'               => 450.00,
                'created_at'                      => Carbon::now()->subDays(2),
                'updated_at'                      => Carbon::now()->subDays(2),
            ],
            [
                'request_number'                  => 'PR-' . strtoupper(uniqid()),
                'provider_id'                     => $userIds['provider@example.com'],
                'patient_fhir_id'                 => 'Patient/' . uniqid(),
                'patient_display_id'              => 'MaJo002',
                'facility_id'                     => $facilityIds[1],
                'payer_name_submitted'            => 'Blue Cross Blue Shield',
                'payer_id'                        => 'BCBS',
                'expected_service_date'           => Carbon::now()->addDays(5),
                'wound_type'                      => 'VLU',
                'order_status'                    => 'submitted',
                'step'                            => 6,
                'mac_validation_status'           => 'passed',
                'eligibility_status'              => 'eligible',
                'pre_auth_required_determination' => 'not_required',
                'total_order_value'               => 680.00,
                'submitted_at'                    => Carbon::now()->subDays(1),
                'created_at'                      => Carbon::now()->subDays(3),
                'updated_at'                      => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($productRequests as $request) {
            DB::table('product_requests')->insert($request);
        }

        // Add test orders for current month profit calculation
        \App\Models\Order\ProductRequest::create([
            'request_number' => 'TEST-' . uniqid(),
            'patient_fhir_id' => 'test-patient-1',
            'patient_display_id' => 'TP001',
            'wound_type' => 'DFU',
            'payer_name_submitted' => 'Medicare',
            'expected_service_date' => now()->addDays(7)->toDateString(),
            'facility_id' => 1,
            'provider_id' => 1,
            'order_status' => 'approved',
            'total_order_value' => 2500.00,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\Order\ProductRequest::create([
            'request_number' => 'TEST-' . uniqid(),
            'patient_fhir_id' => 'test-patient-2',
            'patient_display_id' => 'TP002',
            'wound_type' => 'VLU',
            'payer_name_submitted' => 'Blue Cross',
            'expected_service_date' => now()->addDays(5)->toDateString(),
            'facility_id' => 1,
            'provider_id' => 1,
            'order_status' => 'approved',
            'total_order_value' => 3500.00,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\Order\ProductRequest::create([
            'request_number' => 'TEST-' . uniqid(),
            'patient_fhir_id' => 'test-patient-3',
            'patient_display_id' => 'TP003',
            'wound_type' => 'PU',
            'payer_name_submitted' => 'Aetna',
            'expected_service_date' => now()->addDays(3)->toDateString(),
            'facility_id' => 1,
            'provider_id' => 1,
            'order_status' => 'delivered',
            'total_order_value' => 1800.00,
            'approved_at' => now()->subDays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call other seeders in correct order
        $this->call([
            CategoriesAndManufacturersSeeder::class,  // Creates categories and manufacturers first
            ProductSeeder::class,                     // Creates comprehensive product catalog with CMS data
            DocusealFolderSeeder::class,               // Creates folders before templates
            DocusealTemplateSeeder::class,             // Creates templates that reference folders
            EpisodeSeeder::class,                      // Creates episode test data
        ]);

        $this->command->info('Database seeded successfully!');
    }
}
