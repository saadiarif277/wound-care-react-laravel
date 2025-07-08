<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for the new agnostic platform schema.
     */
    public function run(): void
    {
        // Disable foreign key checks for seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Clear all tables first
            $this->truncateTables();

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Seed the data
            $this->seedData();
            
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }

        $this->command->info('Database seeded successfully!');
    }

    private function truncateTables(): void
    {
        $tables = [
            // Clear in reverse dependency order
            'audit_logs',
            'integration_events',
            'commission_records',
            'commission_rules',
            'documents',
            'order_compliance_checks',
            'compliance_rules',
            'verifications',
            'order_items',
            'orders',
            'product_requests',
            'products',
            'episode_care_team',
            'episodes',
            'patient_references',
            'user_facility_assignments',
            'organizations',
            'role_permissions',
            'user_roles',
            'permissions',
            'roles',
            'users',
            'tenants',
        ];

        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
                $this->command->info("Truncated table: {$table}");
            } catch (\Exception $e) {
                $this->command->warn("Could not truncate table '{$table}': " . $e->getMessage());
            }
        }
    }

    private function seedData(): void
    {
        // Create default tenant
        $tenantId = $this->createTenant();

        // Create permissions
        $permissions = $this->createPermissions();

        // Create roles
        $roles = $this->createRoles();

        // Assign permissions to roles
        $this->assignPermissionsToRoles($roles, $permissions);

        // Create organizations
        $organizations = $this->createOrganizations($tenantId);

        // Create users
        $users = $this->createUsers();

        // Assign roles to users with proper scoping
        $this->assignRolesToUsers($users, $roles, $organizations);

        // Create user facility assignments
        $this->createUserFacilityAssignments($users, $organizations);

        // Create products with sizes
        $products = $this->createProducts($tenantId, $organizations['manufacturers']);

        // Create patient references
        $patients = $this->createPatientReferences($tenantId);

        // Create episodes
        $episodes = $this->createEpisodes($tenantId, $patients, $organizations['facilities'], $users);

        // Create product requests
        $this->createProductRequests($episodes, $users);

        // Create sales reps and assignments
        $salesReps = $this->createSalesReps($users);
        $this->createSalesAssignments($salesReps, $organizations);

        // Create commission rules
        $commissionRules = $this->createCommissionRules($tenantId, $products);

        // Create initial orders
        $this->createOrders($episodes, $users, $organizations, $products, $commissionRules, $salesReps);

        // Create compliance rules
        $this->createComplianceRules($tenantId);

        // Call additional seeders
        $this->callOtherSeeders();

        $this->command->info('All data seeded successfully!');
    }

    private function createTenant(): string
    {
        $tenantId = Str::uuid()->toString();
        
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'MSC Medical Distribution',
            'type' => 'distributor',
            'settings' => json_encode([
                'features' => ['wound_care', 'dme', 'surgical'],
                'timezone' => 'America/New_York',
                'billing' => [
                    'tax_rate' => 0.0875,
                    'payment_terms' => 'net_30'
                ]
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created default tenant');
        return $tenantId;
    }

    private function createPermissions(): array
    {
        $permissions = [
            // Dashboard & Analytics
            'view_dashboard' => 'View dashboard and analytics',
            'view_analytics' => 'View detailed analytics',
            'export_reports' => 'Export reports and data',
            
            // Episodes
            'create_episodes' => 'Create new episodes',
            'view_episodes' => 'View episodes',
            'edit_episodes' => 'Edit episodes',
            'delete_episodes' => 'Delete episodes',
            'manage_care_team' => 'Manage episode care team',
            
            // Product Requests
            'create_product_requests' => 'Create product requests',
            'view_product_requests' => 'View product requests',
            'approve_product_requests' => 'Approve product requests',
            'convert_requests_to_orders' => 'Convert requests to orders',
            
            // Orders
            'create_orders' => 'Create orders',
            'view_orders' => 'View orders',
            'edit_orders' => 'Edit orders',
            'cancel_orders' => 'Cancel orders',
            'view_order_financials' => 'View order financial information',
            'manage_order_status' => 'Manage order status',
            
            // Verifications
            'manage_verifications' => 'Manage insurance verifications',
            'view_verifications' => 'View verification status',
            'submit_verifications' => 'Submit verification forms',
            'approve_verifications' => 'Approve verifications',
            
            // Products
            'view_products' => 'View product catalog',
            'manage_products' => 'Manage products',
            'view_pricing' => 'View product pricing',
            'manage_pricing' => 'Manage product pricing',
            
            // Organizations
            'view_organizations' => 'View organizations',
            'manage_organizations' => 'Manage organizations',
            'view_facilities' => 'View facilities',
            'manage_facilities' => 'Manage facilities',
            
            // Users
            'view_users' => 'View users',
            'manage_users' => 'Manage users',
            'invite_users' => 'Invite new users',
            'manage_user_roles' => 'Manage user roles',
            
            // Financial
            'view_financials' => 'View financial data',
            'manage_commissions' => 'Manage commission rules',
            'view_commissions' => 'View commission data',
            'approve_payouts' => 'Approve commission payouts',
            'view_invoices' => 'View invoices',
            'manage_payments' => 'Manage payments',
            
            // Compliance
            'view_compliance' => 'View compliance status',
            'manage_compliance_rules' => 'Manage compliance rules',
            'override_compliance' => 'Override compliance checks',
            
            // Documents
            'view_documents' => 'View documents',
            'upload_documents' => 'Upload documents',
            'manage_documents' => 'Manage all documents',
            'sign_documents' => 'Sign documents',
            
            // System
            'manage_system' => 'Manage system settings',
            'view_audit_logs' => 'View audit logs',
            'manage_integrations' => 'Manage integrations',
            'view_system_health' => 'View system health metrics',
        ];

        $permissionIds = [];
        foreach ($permissions as $name => $description) {
            $id = Str::uuid()->toString();
            DB::table('permissions')->insert([
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'created_at' => now(),
            ]);
            $permissionIds[$name] = $id;
        }

        $this->command->info('Created ' . count($permissions) . ' permissions');
        return $permissionIds;
    }

    private function createRoles(): array
    {
        $roles = [
            'super_admin' => [
                'name' => 'Super Administrator',
                'description' => 'Full system access',
                'is_system' => true,
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Administrative access',
                'is_system' => true,
            ],
            'provider' => [
                'name' => 'Healthcare Provider',
                'description' => 'Healthcare provider with patient care access',
                'is_system' => true,
            ],
            'office_manager' => [
                'name' => 'Office Manager',
                'description' => 'Office administrative access',
                'is_system' => true,
            ],
            'sales_rep' => [
                'name' => 'Sales Representative',
                'description' => 'Sales and commission tracking',
                'is_system' => true,
            ],
            'manufacturer_rep' => [
                'name' => 'Manufacturer Representative',
                'description' => 'Manufacturer order management',
                'is_system' => true,
            ],
        ];

        $roleIds = [];
        foreach ($roles as $key => $role) {
            $id = Str::uuid()->toString();
            DB::table('roles')->insert([
                'id' => $id,
                'name' => $key,
                'description' => $role['description'],
                'is_system' => $role['is_system'],
                'created_at' => now(),
            ]);
            $roleIds[$key] = $id;
        }

        $this->command->info('Created ' . count($roles) . ' roles');
        return $roleIds;
    }

    private function assignPermissionsToRoles($roles, $permissions): void
    {
        $rolePermissions = [
            'super_admin' => array_keys($permissions), // All permissions
            'admin' => [
                'view_dashboard', 'view_analytics', 'export_reports',
                'view_episodes', 'edit_episodes', 'manage_care_team',
                'view_product_requests', 'approve_product_requests', 'convert_requests_to_orders',
                'view_orders', 'edit_orders', 'view_order_financials', 'manage_order_status',
                'manage_verifications', 'view_verifications', 'approve_verifications',
                'view_products', 'manage_products', 'view_pricing', 'manage_pricing',
                'view_organizations', 'manage_organizations', 'view_facilities', 'manage_facilities',
                'view_users', 'manage_users', 'invite_users', 'manage_user_roles',
                'view_financials', 'manage_commissions', 'view_commissions',
                'view_compliance', 'manage_compliance_rules',
                'view_documents', 'manage_documents',
                'view_audit_logs', 'manage_integrations'
            ],
            'provider' => [
                'view_dashboard', 'view_analytics',
                'create_episodes', 'view_episodes', 'edit_episodes', 'manage_care_team',
                'create_product_requests', 'view_product_requests',
                'create_orders', 'view_orders', 'view_order_financials',
                'view_verifications', 'submit_verifications',
                'view_products', 'view_pricing',
                'view_documents', 'upload_documents', 'sign_documents'
            ],
            'office_manager' => [
                'view_dashboard', 'view_analytics',
                'view_episodes', 'create_product_requests', 'view_product_requests',
                'create_orders', 'view_orders', 'manage_order_status',
                'manage_verifications', 'view_verifications', 'submit_verifications',
                'view_products', 'view_facilities',
                'view_documents', 'upload_documents'
            ],
            'sales_rep' => [
                'view_dashboard', 'view_analytics', 'export_reports',
                'view_orders', 'view_order_financials',
                'view_commissions', 'view_products', 'view_pricing',
                'view_organizations', 'view_facilities',
                'view_financials'
            ],
            'manufacturer_rep' => [
                'view_dashboard', 'view_orders', 'manage_order_status',
                'view_products', 'view_verifications',
                'view_documents'
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $roleId = $roles[$roleName];
            foreach ($permissionNames as $permissionName) {
                if (isset($permissions[$permissionName])) {
                    DB::table('role_permissions')->insert([
                        'id' => Str::uuid()->toString(),
                        'role_id' => $roleId,
                        'permission_id' => $permissions[$permissionName],
                        'created_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Assigned permissions to roles');
    }

    private function createOrganizations($tenantId): array
    {
        $organizations = [
            'facilities' => [],
            'manufacturers' => [],
            'payers' => [],
        ];

        // Create manufacturer organizations with realistic data
        $manufacturers = [
            [
                'name' => 'ACZ Associates',
                'npi' => '1234567890',
                'tax_id' => '45-1234567',
                'phone' => '(800) 422-2952',
                'email' => 'orders@aczmedical.com',
                'settings' => [
                    'docuseal_template_id' => 'templ_ACZ123',
                    'requires_prior_auth' => true,
                    'payment_terms' => 'net_30'
                ]
            ],
            [
                'name' => 'Advanced Solution',
                'npi' => '2345678901',
                'tax_id' => '54-2345678',
                'phone' => '(888) 237-8679',
                'email' => 'support@advancedsolution.com',
                'settings' => [
                    'docuseal_template_id' => 'templ_ADV456',
                    'requires_prior_auth' => false,
                    'payment_terms' => 'net_45'
                ]
            ],
            [
                'name' => 'Centurion Medical',
                'npi' => '3456789012',
                'tax_id' => '63-3456789',
                'phone' => '(800) 248-4058',
                'email' => 'customerservice@centurionmp.com',
                'settings' => [
                    'docuseal_template_id' => 'templ_CEN789',
                    'requires_prior_auth' => true,
                    'payment_terms' => 'net_30'
                ]
            ],
            [
                'name' => 'MedLife Solutions',
                'npi' => '4567890123',
                'tax_id' => '72-4567890',
                'phone' => '(877) 633-5433',
                'email' => 'orders@medlifesolutions.com',
                'settings' => [
                    'docuseal_template_id' => 'templ_MED012',
                    'requires_prior_auth' => false,
                    'payment_terms' => 'net_60'
                ]
            ],
        ];

        foreach ($manufacturers as $mfg) {
            $id = Str::uuid()->toString();
            DB::table('organizations')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'type' => 'manufacturer',
                'name' => $mfg['name'],
                'npi' => $mfg['npi'],
                'tax_id' => $mfg['tax_id'],
                'business_email' => $mfg['email'],
                'business_phone' => $mfg['phone'],
                'settings' => json_encode($mfg['settings']),
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $organizations['manufacturers'][] = $id;
        }

        // Create facility organizations with realistic data
        $facilities = [
            [
                'name' => 'Main Medical Center',
                'npi' => '5678901234',
                'tax_id' => '81-5678901',
                'phone' => '(305) 555-0100',
                'email' => 'admin@mainmedicalcenter.com',
                'settings' => [
                    'facility_type' => 'hospital',
                    'bed_count' => 450,
                    'specialties' => ['wound_care', 'orthopedics', 'cardiology']
                ]
            ],
            [
                'name' => 'Downtown Clinic',
                'npi' => '6789012345',
                'tax_id' => '82-6789012',
                'phone' => '(305) 555-0200',
                'email' => 'info@downtownclinic.com',
                'settings' => [
                    'facility_type' => 'outpatient_clinic',
                    'specialties' => ['wound_care', 'primary_care']
                ]
            ],
            [
                'name' => 'Suburban Health Center',
                'npi' => '7890123456',
                'tax_id' => '83-7890123',
                'phone' => '(305) 555-0300',
                'email' => 'contact@suburbanhc.com',
                'settings' => [
                    'facility_type' => 'ambulatory_care',
                    'specialties' => ['wound_care', 'diabetes_care', 'podiatry']
                ]
            ],
            [
                'name' => 'Specialty Wound Care Center',
                'npi' => '8901234567',
                'tax_id' => '84-8901234',
                'phone' => '(305) 555-0400',
                'email' => 'referrals@specialtywoundcare.com',
                'settings' => [
                    'facility_type' => 'specialty_clinic',
                    'specialties' => ['wound_care', 'hyperbaric_medicine']
                ]
            ],
        ];

        foreach ($facilities as $facility) {
            $id = Str::uuid()->toString();
            DB::table('organizations')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'type' => 'facility',
                'name' => $facility['name'],
                'npi' => $facility['npi'],
                'tax_id' => $facility['tax_id'],
                'business_email' => $facility['email'],
                'business_phone' => $facility['phone'],
                'settings' => json_encode($facility['settings']),
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $organizations['facilities'][] = $id;
        }

        // Create payer organizations
        $payers = [
            [
                'name' => 'Medicare',
                'tax_id' => '00-0000001',
                'phone' => '(800) 633-4227',
                'email' => 'provider@medicare.gov',
                'settings' => [
                    'payer_type' => 'government',
                    'requires_prior_auth' => true,
                    'electronic_submission' => true
                ]
            ],
            [
                'name' => 'Blue Cross Blue Shield',
                'tax_id' => '00-0000002',
                'phone' => '(800) 262-2583',
                'email' => 'provider@bcbs.com',
                'settings' => [
                    'payer_type' => 'commercial',
                    'requires_prior_auth' => true,
                    'electronic_submission' => true
                ]
            ],
            [
                'name' => 'United Healthcare',
                'tax_id' => '00-0000003',
                'phone' => '(877) 842-3210',
                'email' => 'provider@uhc.com',
                'settings' => [
                    'payer_type' => 'commercial',
                    'requires_prior_auth' => false,
                    'electronic_submission' => true
                ]
            ],
            [
                'name' => 'Medicaid',
                'tax_id' => '00-0000004',
                'phone' => '(800) 541-5555',
                'email' => 'provider@medicaid.gov',
                'settings' => [
                    'payer_type' => 'government',
                    'requires_prior_auth' => true,
                    'electronic_submission' => false
                ]
            ],
        ];

        foreach ($payers as $payer) {
            $id = Str::uuid()->toString();
            DB::table('organizations')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'type' => 'payer',
                'name' => $payer['name'],
                'tax_id' => $payer['tax_id'],
                'business_email' => $payer['email'],
                'business_phone' => $payer['phone'],
                'settings' => json_encode($payer['settings']),
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $organizations['payers'][] = $id;
        }

        $this->command->info('Created organizations: ' . count($manufacturers) . ' manufacturers, ' . 
            count($facilities) . ' facilities, ' . count($payers) . ' payers');

        return $organizations;
    }

    private function createUsers(): array
    {
        $users = [
            // Admin users
            [
                'email' => 'richard@mscwoundcare.com',
                'first_name' => 'RV',
                'last_name' => 'CTO',
                'user_type' => 'admin',
                'role' => 'super_admin',
            ],
            [
                'email' => 'admin@msc.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'user_type' => 'admin',
                'role' => 'admin',
            ],
            // Healthcare providers
            [
                'email' => 'provider@example.com',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'user_type' => 'provider',
                'provider_fhir_id' => 'Practitioner/' . Str::uuid(),
                'role' => 'provider',
                'phone' => '(305) 555-1001',
            ],
            [
                'email' => 'dr.jones@example.com',
                'first_name' => 'Sarah',
                'last_name' => 'Jones',
                'user_type' => 'provider',
                'provider_fhir_id' => 'Practitioner/' . Str::uuid(),
                'role' => 'provider',
                'phone' => '(305) 555-1002',
            ],
            [
                'email' => 'dr.wilson@example.com',
                'first_name' => 'Michael',
                'last_name' => 'Wilson',
                'user_type' => 'provider',
                'provider_fhir_id' => 'Practitioner/' . Str::uuid(),
                'role' => 'provider',
                'phone' => '(305) 555-1003',
            ],
            // Office managers
            [
                'email' => 'manager@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Manager',
                'user_type' => 'office_manager',
                'role' => 'office_manager',
                'phone' => '(305) 555-2001',
            ],
            [
                'email' => 'office.lead@example.com',
                'first_name' => 'Patricia',
                'last_name' => 'Davis',
                'user_type' => 'office_manager',
                'role' => 'office_manager',
                'phone' => '(305) 555-2002',
            ],
            // Sales reps
            [
                'email' => 'rep@msc.com',
                'first_name' => 'Bob',
                'last_name' => 'Sales',
                'user_type' => 'sales_rep',
                'role' => 'sales_rep',
                'phone' => '(305) 555-3001',
            ],
            [
                'email' => 'regional.rep@msc.com',
                'first_name' => 'Lisa',
                'last_name' => 'Regional',
                'user_type' => 'sales_rep',
                'role' => 'sales_rep',
                'phone' => '(305) 555-3002',
            ],
            // Manufacturer reps
            [
                'email' => 'mfgrep@aczmedical.com',
                'first_name' => 'Alice',
                'last_name' => 'MfgRep',
                'user_type' => 'manufacturer_rep',
                'role' => 'manufacturer_rep',
                'phone' => '(800) 422-2952',
            ],
            [
                'email' => 'rep@centurionmp.com',
                'first_name' => 'Robert',
                'last_name' => 'Centurion',
                'user_type' => 'manufacturer_rep',
                'role' => 'manufacturer_rep',
                'phone' => '(800) 248-4058',
            ],
        ];

        $userIds = [];
        foreach ($users as $userData) {
            $id = Str::uuid()->toString();
            DB::table('users')->insert([
                'id' => $id,
                'email' => $userData['email'],
                'password_hash' => Hash::make('secret'),
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'phone' => $userData['phone'] ?? null,
                'user_type' => $userData['user_type'],
                'provider_fhir_id' => $userData['provider_fhir_id'] ?? null,
                'status' => 'active',
                'settings' => json_encode([
                    'notifications' => [
                        'email' => true,
                        'sms' => false
                    ],
                    'preferences' => [
                        'theme' => 'light',
                        'language' => 'en'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userIds[$userData['email']] = ['id' => $id, 'role' => $userData['role']];
        }

        $this->command->info('Created ' . count($users) . ' users');
        return $userIds;
    }

    private function assignRolesToUsers($users, $roles, $organizations): void
    {
        foreach ($users as $email => $userData) {
            $roleId = $roles[$userData['role']];
            $userId = $userData['id'];

            // Determine scope based on role
            $scopeType = 'global';
            $scopeId = null;

            if (in_array($userData['role'], ['provider', 'office_manager'])) {
                $scopeType = 'facility';
                $scopeId = $organizations['facilities'][0]; // Assign to first facility
            } elseif ($userData['role'] === 'manufacturer_rep') {
                $scopeType = 'manufacturer';
                // Assign to appropriate manufacturer based on email
                if (strpos($email, 'aczmedical') !== false) {
                    $scopeId = $organizations['manufacturers'][0]; // ACZ
                } elseif (strpos($email, 'centurionmp') !== false) {
                    $scopeId = $organizations['manufacturers'][2]; // Centurion
                } else {
                    $scopeId = $organizations['manufacturers'][0]; // Default to first
                }
            }

            DB::table('user_roles')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'role_id' => $roleId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'created_at' => now(),
            ]);
        }

        $this->command->info('Assigned roles to users with proper scoping');
    }

    private function createUserFacilityAssignments($users, $organizations): void
    {
        // Assign providers and office managers to facilities
        $assignments = [
            'provider@example.com' => [
                'facilities' => [$organizations['facilities'][0], $organizations['facilities'][1]],
                'role' => 'provider',
                'can_order' => true,
                'can_view_financial' => true,
            ],
            'dr.jones@example.com' => [
                'facilities' => [$organizations['facilities'][1], $organizations['facilities'][2]],
                'role' => 'provider',
                'can_order' => true,
                'can_view_financial' => true,
            ],
            'dr.wilson@example.com' => [
                'facilities' => [$organizations['facilities'][2], $organizations['facilities'][3]],
                'role' => 'provider',
                'can_order' => true,
                'can_view_financial' => true,
            ],
            'manager@example.com' => [
                'facilities' => [$organizations['facilities'][0], $organizations['facilities'][1], $organizations['facilities'][2]],
                'role' => 'office_manager',
                'can_order' => true,
                'can_manage_verifications' => true,
            ],
            'office.lead@example.com' => [
                'facilities' => [$organizations['facilities'][3]],
                'role' => 'office_manager',
                'can_order' => true,
                'can_manage_verifications' => true,
            ],
        ];

        foreach ($assignments as $email => $config) {
            $userId = $users[$email]['id'];
            $isPrimary = true;

            foreach ($config['facilities'] as $facilityId) {
                DB::table('user_facility_assignments')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'facility_id' => $facilityId,
                    'role' => $config['role'],
                    'can_order' => $config['can_order'] ?? false,
                    'can_view_orders' => true,
                    'can_view_financial' => $config['can_view_financial'] ?? false,
                    'can_manage_verifications' => $config['can_manage_verifications'] ?? false,
                    'can_order_for_providers' => null,
                    'is_primary_facility' => $isPrimary,
                    'assigned_at' => now(),
                ]);
                $isPrimary = false;
            }
        }

        $this->command->info('Created user facility assignments');
    }

    private function createProducts($tenantId, $manufacturerIds): array
    {
        $products = [
            // ACZ Associates products (Wound Dressings)
            [
                'manufacturer_index' => 0,
                'sku' => 'ACZ-WD-001',
                'name' => 'Advanced Foam Dressing 4x4',
                'category' => 'wound_dressing',
                'sub_category' => 'foam_dressing',
                'hcpcs_code' => 'A6209',
                'description' => 'High-absorbency foam dressing for moderate to heavy exudate wounds',
                'specifications' => [
                    'sizes' => ['2x2', '4x4', '6x6'],
                    'absorbency' => 'high',
                    'adhesive' => 'border',
                    'sterile' => true
                ],
            ],
            [
                'manufacturer_index' => 0,
                'sku' => 'ACZ-WD-002',
                'name' => 'Hydrogel Wound Dressing',
                'category' => 'wound_dressing',
                'sub_category' => 'hydrogel',
                'hcpcs_code' => 'A6248',
                'description' => 'Moisture-donating gel dressing for dry wounds',
                'specifications' => [
                    'sizes' => ['2x2', '4x4'],
                    'type' => 'amorphous_gel',
                    'sterile' => true
                ],
            ],
            [
                'manufacturer_index' => 0,
                'sku' => 'ACZ-WD-003',
                'name' => 'Calcium Alginate Dressing',
                'category' => 'wound_dressing',
                'sub_category' => 'alginate',
                'hcpcs_code' => 'A6196',
                'description' => 'Highly absorbent alginate dressing for heavily exuding wounds',
                'specifications' => [
                    'sizes' => ['2x2', '4x4', '4x8'],
                    'absorbency' => 'very_high',
                    'hemostatic' => true,
                    'sterile' => true
                ],
            ],
            // Advanced Solution products (DME)
            [
                'manufacturer_index' => 1,
                'sku' => 'ADV-DME-001',
                'name' => 'Portable Oxygen Concentrator',
                'category' => 'dme_equipment',
                'sub_category' => 'oxygen_therapy',
                'hcpcs_code' => 'E1390',
                'description' => 'Lightweight portable oxygen concentrator for ambulatory patients',
                'requires_prescription' => true,
                'specifications' => [
                    'flow_rate' => '1-5 LPM',
                    'battery_life' => '8 hours',
                    'weight' => '5 lbs',
                    'faa_approved' => true
                ],
            ],
            [
                'manufacturer_index' => 1,
                'sku' => 'ADV-DME-002',
                'name' => 'CPAP Machine',
                'category' => 'dme_equipment',
                'sub_category' => 'sleep_therapy',
                'hcpcs_code' => 'E0601',
                'description' => 'Continuous positive airway pressure device for sleep apnea',
                'requires_prescription' => true,
                'specifications' => [
                    'pressure_range' => '4-20 cmH2O',
                    'humidifier' => 'integrated',
                    'data_tracking' => true
                ],
            ],
            // Centurion Medical products (Surgical)
            [
                'manufacturer_index' => 2,
                'sku' => 'CEN-SI-001',
                'name' => 'Titanium Hip Implant System',
                'category' => 'surgical_implant',
                'sub_category' => 'orthopedic_implant',
                'description' => 'Complete hip replacement system with titanium components',
                'requires_prescription' => true,
                'requires_sizing' => true,
                'specifications' => [
                    'material' => 'titanium_alloy',
                    'sizes' => ['small', 'medium', 'large', 'x-large'],
                    'coating' => 'hydroxyapatite',
                    'fda_approved' => true
                ],
            ],
            [
                'manufacturer_index' => 2,
                'sku' => 'CEN-SI-002',
                'name' => 'Knee Replacement System',
                'category' => 'surgical_implant',
                'sub_category' => 'orthopedic_implant',
                'description' => 'Total knee replacement prosthesis system',
                'requires_prescription' => true,
                'requires_sizing' => true,
                'specifications' => [
                    'material' => 'cobalt_chrome',
                    'sizes' => ['1', '2', '3', '4', '5'],
                    'type' => 'posterior_stabilized',
                    'fda_approved' => true
                ],
            ],
            // MedLife Solutions products (Wound Care Supplies)
            [
                'manufacturer_index' => 3,
                'sku' => 'MED-WC-001',
                'name' => 'Negative Pressure Wound Therapy System',
                'category' => 'wound_dressing',
                'sub_category' => 'npwt',
                'hcpcs_code' => 'E2402',
                'description' => 'Portable negative pressure wound therapy device',
                'requires_prescription' => true,
                'specifications' => [
                    'pressure_settings' => '-50 to -200 mmHg',
                    'therapy_modes' => ['continuous', 'intermittent'],
                    'canister_size' => '300ml',
                    'portable' => true
                ],
            ],
            [
                'manufacturer_index' => 3,
                'sku' => 'MED-WC-002',
                'name' => 'Compression Bandage System',
                'category' => 'wound_dressing',
                'sub_category' => 'compression',
                'hcpcs_code' => 'A6545',
                'description' => 'Multi-layer compression bandage system for venous ulcers',
                'specifications' => [
                    'layers' => 4,
                    'compression_level' => '40 mmHg',
                    'sizes' => ['small', 'medium', 'large'],
                    'latex_free' => true
                ],
            ],
        ];

        $productIds = [];
        foreach ($products as $product) {
            $id = Str::uuid()->toString();
            DB::table('products')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'manufacturer_id' => $manufacturerIds[$product['manufacturer_index']],
                'sku' => $product['sku'],
                'manufacturer_part_number' => $product['sku'], // Using SKU as part number for simplicity
                'name' => $product['name'],
                'category' => $product['category'],
                'sub_category' => $product['sub_category'] ?? null,
                'description' => $product['description'] ?? null,
                'hcpcs_code' => $product['hcpcs_code'] ?? null,
                'cpt_codes' => isset($product['cpt_codes']) ? json_encode($product['cpt_codes']) : null,
                'requires_prescription' => $product['requires_prescription'] ?? false,
                'requires_verification' => true,
                'requires_sizing' => $product['requires_sizing'] ?? false,
                'specifications' => json_encode($product['specifications'] ?? []),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productIds[] = $id;
        }

        $this->command->info('Created ' . count($products) . ' products');
        return $productIds;
    }

    private function createPatientReferences($tenantId): array
    {
        $patients = [];
        
        // Create realistic patient references
        $patientData = [
            ['first' => 'JO', 'last' => 'SM'],
            ['first' => 'MA', 'last' => 'JO'],
            ['first' => 'RO', 'last' => 'DA'],
            ['first' => 'LI', 'last' => 'WI'],
            ['first' => 'JA', 'last' => 'BR'],
            ['first' => 'EM', 'last' => 'TH'],
            ['first' => 'MI', 'last' => 'GA'],
            ['first' => 'SA', 'last' => 'MA'],
            ['first' => 'DA', 'last' => 'JO'],
            ['first' => 'CH', 'last' => 'LE'],
        ];

        foreach ($patientData as $i => $data) {
            $id = Str::uuid()->toString();
            $fhirId = 'Patient/' . Str::uuid();
            $randomCode = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            DB::table('patient_references')->insert([
                'id' => $id,
                'patient_fhir_id' => $fhirId,
                'patient_display_id' => $data['first'] . $data['last'] . $randomCode,
                'display_metadata' => json_encode([
                    'first_init' => $data['first'],
                    'last_init' => $data['last'],
                    'random' => $randomCode,
                ]),
                'tenant_id' => $tenantId,
                'created_at' => now(),
            ]);
            
            $patients[] = ['id' => $id, 'fhir_id' => $fhirId, 'display_id' => $data['first'] . $data['last'] . $randomCode];
        }

        $this->command->info('Created ' . count($patients) . ' patient references');
        return $patients;
    }

    private function createEpisodes($tenantId, $patients, $facilityIds, $users): array
    {
        $episodes = [];
        $providerFhirIds = [];

        // Collect all provider FHIR IDs
        foreach ($users as $userData) {
            if ($userData['role'] === 'provider') {
                $user = DB::table('users')->where('id', $userData['id'])->first();
                if ($user && $user->provider_fhir_id) {
                    $providerFhirIds[] = $user->provider_fhir_id;
                }
            }
        }

        // Create diverse episodes
        $episodeTemplates = [
            ['type' => 'wound_care', 'sub_type' => 'diabetic_foot_ulcer', 'priority' => 'routine', 'duration' => 90],
            ['type' => 'wound_care', 'sub_type' => 'pressure_ulcer', 'priority' => 'urgent', 'duration' => 60],
            ['type' => 'wound_care', 'sub_type' => 'venous_ulcer', 'priority' => 'routine', 'duration' => 120],
            ['type' => 'dme_need', 'sub_type' => 'oxygen_therapy', 'priority' => 'urgent', 'duration' => 180],
            ['type' => 'dme_need', 'sub_type' => 'cpap_therapy', 'priority' => 'routine', 'duration' => 365],
            ['type' => 'surgical_case', 'sub_type' => 'hip_replacement', 'priority' => 'routine', 'duration' => 90],
            ['type' => 'surgical_case', 'sub_type' => 'knee_replacement', 'priority' => 'emergent', 'duration' => 90],
            ['type' => 'ongoing_supply', 'sub_type' => 'wound_care_supplies', 'priority' => 'routine', 'duration' => 180],
        ];

        foreach ($patients as $index => $patient) {
            if ($index >= count($episodeTemplates)) break;

            $template = $episodeTemplates[$index];
            $id = Str::uuid()->toString();
            $episodeNumber = 'EP-' . date('Y') . '-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
            
            $facilityId = $facilityIds[$index % count($facilityIds)];
            $providerFhirId = $providerFhirIds[$index % count($providerFhirIds)];
            $creatorEmail = $index % 2 === 0 ? 'provider@example.com' : 'dr.jones@example.com';
            
            DB::table('episodes')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'episode_number' => $episodeNumber,
                'patient_fhir_id' => $patient['fhir_id'],
                'primary_provider_fhir_id' => $providerFhirId,
                'primary_facility_id' => $facilityId,
                'type' => $template['type'],
                'sub_type' => $template['sub_type'],
                'status' => $index < 3 ? 'active' : 'planned',
                'diagnosis_fhir_refs' => json_encode($this->generateDiagnosisRefs($template['type'])),
                'procedure_fhir_refs' => json_encode($this->generateProcedureRefs($template['type'])),
                'estimated_duration_days' => $template['duration'],
                'priority' => $template['priority'],
                'start_date' => $index < 3 ? now()->subDays(rand(1, 30)) : now()->addDays(rand(1, 30)),
                'target_date' => now()->addDays($template['duration']),
                'tags' => json_encode($this->generateTags($template['type'])),
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => $users[$creatorEmail]['id'],
            ]);

            // Create care team for active episodes
            if ($index < 3) {
                $this->createCareTeam($id, $users, $providerFhirId);
            }

            $episodes[] = [
                'id' => $id,
                'episode_number' => $episodeNumber,
                'type' => $template['type'],
                'sub_type' => $template['sub_type'],
                'facility_id' => $facilityId,
                'patient_id' => $patient['id'],
                'patient_display_id' => $patient['display_id'],
            ];
        }

        $this->command->info('Created ' . count($episodes) . ' episodes');
        return $episodes;
    }

    private function generateDiagnosisRefs($episodeType): array
    {
        $diagnosisMap = [
            'wound_care' => [
                'Condition/diag-' . Str::uuid(), // Diabetic foot ulcer
                'Condition/diag-' . Str::uuid(), // Type 2 diabetes
            ],
            'dme_need' => [
                'Condition/diag-' . Str::uuid(), // COPD
                'Condition/diag-' . Str::uuid(), // Sleep apnea
            ],
            'surgical_case' => [
                'Condition/diag-' . Str::uuid(), // Osteoarthritis
                'Condition/diag-' . Str::uuid(), // Joint degeneration
            ],
            'ongoing_supply' => [
                'Condition/diag-' . Str::uuid(), // Chronic wound
            ],
        ];

        return $diagnosisMap[$episodeType] ?? [];
    }

    private function generateProcedureRefs($episodeType): array
    {
        $procedureMap = [
            'wound_care' => [
                'Procedure/proc-' . Str::uuid(), // Debridement
            ],
            'surgical_case' => [
                'Procedure/proc-' . Str::uuid(), // Joint replacement
            ],
        ];

        return $procedureMap[$episodeType] ?? [];
    }

    private function generateTags($episodeType): array
    {
        $tagMap = [
            'wound_care' => ['chronic', 'diabetic', 'high-risk'],
            'dme_need' => ['respiratory', 'long-term'],
            'surgical_case' => ['elective', 'orthopedic'],
            'ongoing_supply' => ['maintenance', 'recurring'],
        ];

        return $tagMap[$episodeType] ?? [];
    }

    private function createCareTeam($episodeId, $users, $primaryProviderFhirId): void
    {
        // Add primary provider
        DB::table('episode_care_team')->insert([
            'id' => Str::uuid()->toString(),
            'episode_id' => $episodeId,
            'provider_fhir_id' => $primaryProviderFhirId,
            'role' => 'attending_physician',
            'can_order' => true,
            'can_modify' => true,
            'can_view_financial' => true,
            'assigned_date' => now(),
        ]);

        // Add care coordinator (office manager)
        DB::table('episode_care_team')->insert([
            'id' => Str::uuid()->toString(),
            'episode_id' => $episodeId,
            'user_id' => $users['manager@example.com']['id'],
            'role' => 'care_coordinator',
            'can_order' => true,
            'can_modify' => false,
            'can_view_financial' => false,
            'assigned_date' => now(),
        ]);
    }

    private function createProductRequests($episodes, $users): void
    {
        foreach ($episodes as $index => $episode) {
            $id = Str::uuid()->toString();
            $requestNumber = 'REQ-' . date('Y') . '-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
            
            // Determine requester based on episode type
            $requesterEmail = in_array($episode['type'], ['wound_care', 'surgical_case']) 
                ? 'provider@example.com' 
                : 'manager@example.com';
            
            $status = $index < 3 ? 'approved' : 'submitted';
            
            DB::table('product_requests')->insert([
                'id' => $id,
                'episode_id' => $episode['id'],
                'request_number' => $requestNumber,
                'requested_by' => $users[$requesterEmail]['id'],
                'request_type' => $index === 0 ? 'initial_assessment' : 'replenishment',
                'status' => $status,
                'clinical_need' => $this->generateClinicalNeed($episode),
                'urgency' => $index === 1 ? 'urgent' : 'routine',
                'product_categories' => json_encode([$episode['type']]),
                'specific_products' => json_encode($this->getSpecificProductsForEpisode($episode)),
                'needed_by_date' => now()->addDays($index === 1 ? 2 : 7),
                'submitted_at' => now()->subHours($index + 1),
                'reviewed_at' => $status === 'approved' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Created product requests for episodes');
    }

    private function generateClinicalNeed($episode): string
    {
        $needs = [
            'wound_care' => 'Patient presents with ' . str_replace('_', ' ', $episode['sub_type']) . ' requiring advanced wound care supplies and regular dressing changes.',
            'dme_need' => 'Patient requires durable medical equipment for ' . str_replace('_', ' ', $episode['sub_type']) . ' management as prescribed by physician.',
            'surgical_case' => 'Scheduled ' . str_replace('_', ' ', $episode['sub_type']) . ' procedure requiring specialized implants and surgical supplies.',
            'ongoing_supply' => 'Ongoing supply needs for chronic condition management.',
        ];

        return $needs[$episode['type']] ?? 'Patient requires medical supplies for treatment.';
    }

    private function getSpecificProductsForEpisode($episode): array
    {
        $productMap = [
            'wound_care' => ['foam_dressing', 'hydrogel', 'alginate'],
            'dme_need' => ['oxygen_concentrator', 'cpap_machine'],
            'surgical_case' => ['implant_system', 'surgical_kit'],
            'ongoing_supply' => ['compression_bandages', 'wound_dressings'],
        ];

        return $productMap[$episode['type']] ?? [];
    }

    private function createOrders($episodes, $users, $organizations, $productIds, $commissionRules, $salesReps): void
    {
        // Create orders for first 5 episodes
        foreach ($episodes as $index => $episode) {
            if ($index >= 5) break;

            $id = Str::uuid()->toString();
            $orderNumber = 'ORD-' . date('Y') . '-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
            
            // Select appropriate manufacturer based on episode type
            $manufacturerIndex = $this->getManufacturerIndexForEpisode($episode);
            $manufacturerId = $organizations['manufacturers'][$manufacturerIndex];
            
            // Determine order status
            $statuses = ['delivered', 'shipped', 'in_fulfillment', 'pending_verification', 'draft'];
            $status = $statuses[$index] ?? 'draft';
            
            // Assign sales rep based on facility
            $salesRepId = null;
            if (isset($salesReps['parent']) && $index < 2) {
                $salesRepId = $salesReps['parent'];
            } elseif (isset($salesReps['sub'])) {
                $salesRepId = $salesReps['sub'];
            }

            $orderData = [
                'id' => $id,
                'episode_id' => $episode['id'],
                'order_number' => $orderNumber,
                'order_type' => 'standard',
                'status' => $status,
                'ordered_by_user_id' => $users['provider@example.com']['id'],
                'sales_rep_id' => $salesRepId,
                'facility_id' => $episode['facility_id'],
                'manufacturer_id' => $manufacturerId,
                'service_date' => now()->addDays(7),
                'ship_to_type' => 'facility',
                'requires_insurance_verification' => true,
                'verification_status' => $index < 2 ? 'completed' : 'pending',
                'estimated_total' => rand(500, 5000),
                'submitted_at' => $index < 3 ? now()->subDays($index + 1) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add status-specific timestamps
            if ($index === 0) {
                $orderData['delivered_at'] = now()->subHours(2);
                $orderData['shipped_at'] = now()->subDays(1);
                $orderData['approved_at'] = now()->subDays(2);
                $orderData['final_total'] = $orderData['estimated_total'];
                $orderData['patient_responsibility'] = $orderData['estimated_total'] * 0.2;
                $orderData['insurance_coverage'] = $orderData['estimated_total'] * 0.8;
            } elseif ($index === 1) {
                $orderData['shipped_at'] = now()->subHours(6);
                $orderData['approved_at'] = now()->subDays(1);
            }

            DB::table('orders')->insert($orderData);

            // Create order items
            $this->createOrderItems($id, $episode, $productIds);

            // Create verification record
            if ($orderData['requires_insurance_verification']) {
                $this->createVerification($episode['id'], $id, $manufacturerId, $organizations['payers'], $index);
            }

            // Create commission record for delivered/shipped orders
            if (in_array($status, ['delivered', 'shipped'])) {
                $this->createCommissionRecord($id, $users, $orderData['estimated_total'], $commissionRules);
            }
        }

        $this->command->info('Created sample orders with verifications');
    }

    private function getManufacturerIndexForEpisode($episode): int
    {
        $typeToManufacturer = [
            'wound_care' => 0, // ACZ Associates
            'dme_need' => 1, // Advanced Solution
            'surgical_case' => 2, // Centurion Medical
            'ongoing_supply' => 3, // MedLife Solutions
        ];

        return $typeToManufacturer[$episode['type']] ?? 0;
    }

    private function createOrderItems($orderId, $episode, $productIds): void
    {
        // Select products based on episode type
        $productIndexes = $this->getProductIndexesForEpisode($episode['type']);
        
        foreach ($productIndexes as $i => $productIndex) {
            if ($productIndex < count($productIds)) {
                DB::table('order_items')->insert([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $orderId,
                    'product_id' => $productIds[$productIndex],
                    'quantity' => rand(5, 20),
                    'unit_price' => rand(25, 250),
                    'specific_indication' => $i === 0 ? 'Primary treatment' : 'Secondary/backup',
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function getProductIndexesForEpisode($episodeType): array
    {
        $productMap = [
            'wound_care' => [0, 1, 2], // ACZ wound dressings
            'dme_need' => [3, 4], // Advanced Solution DME
            'surgical_case' => [5, 6], // Centurion implants
            'ongoing_supply' => [7, 8], // MedLife supplies
        ];

        return $productMap[$episodeType] ?? [0];
    }

    private function createVerification($episodeId, $orderId, $manufacturerId, $payerIds, $index): void
    {
        $verificationType = $index % 2 === 0 ? 'insurance_eligibility' : 'prior_authorization';
        $status = $index < 2 ? 'completed' : ($index === 2 ? 'in_progress' : 'pending');
        
        DB::table('verifications')->insert([
            'id' => Str::uuid()->toString(),
            'episode_id' => $episodeId,
            'order_id' => $orderId,
            'verification_type' => $verificationType,
            'verification_subtype' => $verificationType === 'prior_authorization' ? 'medical_necessity' : null,
            'required_by_organization_id' => $manufacturerId,
            'payer_organization_id' => $payerIds[$index % count($payerIds)],
            'form_template_id' => 'templ_' . strtoupper(substr($verificationType, 0, 3)) . rand(100, 999),
            'form_provider' => 'docuseal',
            'status' => $status,
            'required_fields' => json_encode($this->getRequiredFieldsForVerification($verificationType)),
            'completed_fields' => $status === 'completed' ? json_encode($this->getRequiredFieldsForVerification($verificationType)) : json_encode([]),
            'completeness_percentage' => $status === 'completed' ? 100 : ($status === 'in_progress' ? 65 : 0),
            'determination' => $status === 'completed' ? 'approved' : null,
            'coverage_details' => $status === 'completed' ? json_encode(['covered_percentage' => 80, 'deductible_met' => true]) : null,
            'verified_date' => $status === 'completed' ? now()->subDays($index) : null,
            'expires_date' => $status === 'completed' ? now()->addMonths(6) : null,
            'created_at' => now(),
            'updated_at' => now(),
            'completed_at' => $status === 'completed' ? now()->subDays($index) : null,
        ]);
    }

    private function getRequiredFieldsForVerification($type): array
    {
        $fields = [
            'insurance_eligibility' => [
                'member_id',
                'group_number',
                'patient_dob',
                'service_date',
                'diagnosis_codes',
                'procedure_codes'
            ],
            'prior_authorization' => [
                'patient_info',
                'diagnosis',
                'clinical_documentation',
                'medical_necessity',
                'treatment_plan',
                'provider_signature'
            ],
        ];

        return $fields[$type] ?? [];
    }

    private function createCommissionRecord($orderId, $users, $orderTotal, $commissionRules): void
    {
        // Use the Standard Product Commission rule
        $ruleId = $commissionRules['Standard Product Commission'];
        
        // Get the order to find the sales rep
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order || !$order->sales_rep_id) {
            return;
        }

        // Get the sales rep
        $salesRep = DB::table('sales_reps')->where('id', $order->sales_rep_id)->first();
        if (!$salesRep) {
            return;
        }
        
        // Create commission for sales rep
        $commissionAmount = $orderTotal * ($salesRep->commission_rate_direct / 100);
        
        DB::table('commission_records')->insert([
            'id' => Str::uuid()->toString(),
            'order_id' => $orderId,
            'user_id' => $salesRep->user_id,
            'rule_id' => $ruleId,
            'sales_rep_id' => $salesRep->id,
            'base_amount' => $orderTotal,
            'commission_amount' => $commissionAmount,
            'status' => 'approved',
            'split_type' => 'direct',
            'approved_by' => $users['admin@msc.com']['id'],
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        // If sub-rep, create parent rep commission
        if ($salesRep->parent_rep_id && $salesRep->sub_rep_parent_share_percentage > 0) {
            $parentCommission = $commissionAmount * ($salesRep->sub_rep_parent_share_percentage / 100);
            $parentRep = DB::table('sales_reps')->where('id', $salesRep->parent_rep_id)->first();
            
            if ($parentRep) {
                DB::table('commission_records')->insert([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $orderId,
                    'user_id' => $parentRep->user_id,
                    'rule_id' => $ruleId,
                    'sales_rep_id' => $parentRep->id,
                    'parent_rep_id' => $salesRep->id,
                    'base_amount' => $orderTotal,
                    'commission_amount' => $parentCommission,
                    'status' => 'approved',
                    'split_type' => 'parent_share',
                    'approved_by' => $users['admin@msc.com']['id'],
                    'approved_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function createCommissionRules($tenantId, $productIds): array
    {
        $rules = [
            [
                'name' => 'Standard Product Commission',
                'products' => array_slice($productIds, 0, 4),
                'type' => 'percentage',
                'base_rate' => 5.0,
            ],
            [
                'name' => 'DME Equipment Commission',
                'categories' => ['dme_equipment'],
                'type' => 'percentage',
                'base_rate' => 7.5,
            ],
            [
                'name' => 'Surgical Implant Commission',
                'categories' => ['surgical_implant'],
                'type' => 'tiered',
                'tiers' => [
                    ['min' => 0, 'max' => 10000, 'rate' => 3.0],
                    ['min' => 10001, 'max' => 50000, 'rate' => 5.0],
                    ['min' => 50001, 'max' => null, 'rate' => 7.0],
                ],
            ],
        ];

        $ruleIds = [];
        foreach ($rules as $rule) {
            $ruleId = Str::uuid()->toString();
            $ruleIds[$rule['name']] = $ruleId;
            
            DB::table('commission_rules')->insert([
                'id' => $ruleId,
                'tenant_id' => $tenantId,
                'rule_name' => $rule['name'],
                'applies_to_products' => isset($rule['products']) ? json_encode($rule['products']) : null,
                'applies_to_categories' => isset($rule['categories']) ? json_encode($rule['categories']) : null,
                'applies_to_facilities' => null,
                'commission_type' => $rule['type'],
                'base_rate' => $rule['base_rate'] ?? null,
                'tier_definitions' => isset($rule['tiers']) ? json_encode($rule['tiers']) : null,
                'split_rules' => null,
                'effective_date' => now()->subMonths(6),
                'created_at' => now(),
            ]);
        }

        $this->command->info('Created commission rules');
        return $ruleIds;
    }

    private function createComplianceRules($tenantId): void
    {
        $rules = [
            [
                'name' => 'Medicare LCD - Wound Care Frequency',
                'type' => 'medicare_lcd',
                'categories' => ['wound_dressing'],
                'definition' => [
                    'max_quantity_per_month' => 30,
                    'requires_documentation' => ['wound_assessment', 'treatment_plan'],
                    'min_days_between_orders' => 7,
                ],
                'severity' => 'error',
            ],
            [
                'name' => 'Prior Authorization Required - DME',
                'type' => 'payer_policy',
                'categories' => ['dme_equipment'],
                'payers' => ['Medicare', 'Medicaid'],
                'definition' => [
                    'requires_prior_auth' => true,
                    'auth_valid_days' => 180,
                ],
                'severity' => 'error',
            ],
            [
                'name' => 'Prescription Validation',
                'type' => 'internal_policy',
                'definition' => [
                    'prescription_required' => true,
                    'prescription_valid_days' => 365,
                ],
                'severity' => 'warning',
                'can_override' => true,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('compliance_rules')->insert([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'rule_name' => $rule['name'],
                'rule_type' => $rule['type'],
                'applies_to_categories' => isset($rule['categories']) ? json_encode($rule['categories']) : null,
                'applies_to_products' => null,
                'applies_to_states' => null,
                'applies_to_payers' => isset($rule['payers']) ? json_encode($rule['payers']) : null,
                'rule_engine' => 'json_logic',
                'rule_definition' => json_encode($rule['definition']),
                'required_documentation' => isset($rule['definition']['requires_documentation']) 
                    ? json_encode($rule['definition']['requires_documentation']) 
                    : null,
                'required_fields' => null,
                'severity' => $rule['severity'],
                'can_override' => $rule['can_override'] ?? false,
                'effective_date' => now()->subMonths(3),
                'is_active' => true,
                'created_at' => now(),
            ]);
        }

        $this->command->info('Created compliance rules');
    }

    private function createSalesReps($users): array
    {
        $salesReps = [];

        // Create parent sales rep
        $parentRepId = Str::uuid()->toString();
        DB::table('sales_reps')->insert([
            'id' => $parentRepId,
            'user_id' => $users['rep@msc.com']['id'],
            'parent_rep_id' => null,
            'territory' => 'Northeast',
            'region' => 'East Coast',
            'commission_rate_direct' => 5.0,
            'sub_rep_parent_share_percentage' => 20.0,
            'rep_type' => 'direct',
            'commission_tier' => 'gold',
            'can_have_sub_reps' => true,
            'performance_metrics' => json_encode([
                'ytd_revenue' => 1250000,
                'ytd_commission' => 62500,
                'active_providers' => 45,
                'conversion_rate' => 0.78
            ]),
            'is_active' => true,
            'hired_date' => now()->subYears(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $salesReps['parent'] = $parentRepId;

        // Create sub sales rep using regional rep
        if (isset($users['regional.rep@msc.com'])) {
            $subRepId = Str::uuid()->toString();
            DB::table('sales_reps')->insert([
                'id' => $subRepId,
                'user_id' => $users['regional.rep@msc.com']['id'],
                'parent_rep_id' => $parentRepId,
                'territory' => 'New York Metro',
                'region' => 'East Coast',
                'commission_rate_direct' => 4.0,
                'sub_rep_parent_share_percentage' => 0.0,
                'rep_type' => 'direct',
                'commission_tier' => 'silver',
                'can_have_sub_reps' => false,
                'performance_metrics' => json_encode([
                    'ytd_revenue' => 450000,
                    'ytd_commission' => 18000,
                    'active_providers' => 22,
                    'conversion_rate' => 0.65
                ]),
                'is_active' => true,
                'hired_date' => now()->subYears(1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $salesReps['sub'] = $subRepId;
        }

        // Create independent sales rep
        if (isset($users['independent-rep@external.com'])) {
            $independentRepId = Str::uuid()->toString();
            DB::table('sales_reps')->insert([
                'id' => $independentRepId,
                'user_id' => $users['independent-rep@external.com']['id'],
                'parent_rep_id' => null,
                'territory' => 'Southeast',
                'region' => 'South',
                'commission_rate_direct' => 7.5,
                'sub_rep_parent_share_percentage' => 0.0,
                'rep_type' => 'independent',
                'commission_tier' => 'gold',
                'can_have_sub_reps' => false,
                'is_active' => true,
                'hired_date' => now()->subMonths(6),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $salesReps['independent'] = $independentRepId;
        }

        $this->command->info('Created sales representatives');
        return $salesReps;
    }

    private function createSalesAssignments($salesReps, $organizations): void
    {
        // Assign parent rep to facilities
        foreach ($organizations['facilities'] as $index => $facilityId) {
            if ($index < 2) { // First two facilities to parent rep
                DB::table('facility_sales_assignments')->insert([
                    'id' => Str::uuid()->toString(),
                    'facility_id' => $facilityId,
                    'sales_rep_id' => $salesReps['parent'],
                    'relationship_type' => 'coordinator',
                    'commission_split_percentage' => 0.0,
                    'can_create_orders' => true,
                    'can_view_all_providers' => true,
                    'assigned_from' => now()->subMonths(6),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Assign sub rep to specific facility
        if (count($organizations['facilities']) > 2) {
            DB::table('facility_sales_assignments')->insert([
                'id' => Str::uuid()->toString(),
                'facility_id' => $organizations['facilities'][2],
                'sales_rep_id' => $salesReps['sub'],
                'relationship_type' => 'coordinator',
                'commission_split_percentage' => 0.0,
                'can_create_orders' => true,
                'can_view_all_providers' => true,
                'assigned_from' => now()->subMonths(3),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create provider assignments (would use actual provider FHIR IDs in production)
        $providerFhirIds = [
            'provider-fhir-001',
            'provider-fhir-002',
            'provider-fhir-003',
            'provider-fhir-004',
        ];

        foreach ($providerFhirIds as $index => $providerFhirId) {
            $repId = $index < 2 ? $salesReps['parent'] : $salesReps['sub'];
            $facilityId = $organizations['facilities'][$index % count($organizations['facilities'])];

            DB::table('provider_sales_assignments')->insert([
                'id' => Str::uuid()->toString(),
                'provider_fhir_id' => $providerFhirId,
                'sales_rep_id' => $repId,
                'facility_id' => $facilityId,
                'relationship_type' => 'primary',
                'commission_split_percentage' => 100.0,
                'override_commission_rate' => null,
                'can_create_orders' => true,
                'assigned_from' => now()->subMonths(rand(1, 12)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create commission targets for current year
        foreach ($salesReps as $type => $repId) {
            // Quarterly targets
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                DB::table('commission_targets')->insert([
                    'id' => Str::uuid()->toString(),
                    'sales_rep_id' => $repId,
                    'target_year' => date('Y'),
                    'target_quarter' => $quarter,
                    'revenue_target' => $type === 'parent' ? 400000 : 150000,
                    'commission_target' => $type === 'parent' ? 20000 : 6000,
                    'order_count_target' => $type === 'parent' ? 100 : 40,
                    'new_provider_target' => 5,
                    'category_targets' => json_encode([
                        'wound_dressing' => $type === 'parent' ? 150000 : 50000,
                        'dme_equipment' => $type === 'parent' ? 100000 : 40000,
                        'surgical_implant' => $type === 'parent' ? 150000 : 60000,
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Created sales assignments and targets');
    }

    private function callOtherSeeders(): void
    {
        // These seeders will need to be adapted for the new schema
        // For now, we'll skip them since we've incorporated their functionality above
        
        $this->command->info('Additional seeders would be called here if adapted for new schema');
    }
}