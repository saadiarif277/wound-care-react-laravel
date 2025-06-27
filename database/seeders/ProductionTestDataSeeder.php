<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Account;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Models\Order\Category;

class ProductionTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating production test data...');

        DB::beginTransaction();

        try {
            // 1. Create roles and permissions first
            $this->createRolesAndPermissions();

            // 2. Create account
            $account = $this->createAccount();

            // 3. Create organization
            $organization = $this->createOrganization($account);

            // 4. Create facilities
            $facilities = $this->createFacilities($organization);

            // 5. Create users (admins, office managers, providers)
            $this->createUsers($account, $organization, $facilities);

            // 6. Create manufacturers and categories
            $this->createManufacturersAndCategories();

            // 7. Create products
            $this->createProducts();

            DB::commit();
            $this->command->info('Production test data created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('Production test data seeder failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createRolesAndPermissions(): void
    {
        $this->command->info('Creating roles and permissions...');

        // Create permissions if they don't exist
        $permissions = [
            'manage-users' => 'Manage Users',
            'manage-organizations' => 'Manage Organizations',
            'manage-facilities' => 'Manage Facilities',
            'manage-products' => 'Manage Products',
            'manage-orders' => 'Manage Orders',
            'view-reports' => 'View Reports',
            'manage-all-organizations' => 'Manage All Organizations',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => "Permission to {$name}"
                ]
            );
        }

        // Create roles if they don't exist
        $roles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'permissions' => ['manage-users', 'manage-organizations', 'manage-facilities', 'manage-products', 'manage-orders', 'view-reports', 'manage-all-organizations']
            ],
            'admin' => [
                'name' => 'Admin',
                'permissions' => ['manage-users', 'manage-facilities', 'manage-products', 'manage-orders', 'view-reports']
            ],
            'office-manager' => [
                'name' => 'Office Manager',
                'permissions' => ['manage-orders', 'view-reports']
            ],
            'provider' => [
                'name' => 'Provider',
                'permissions' => ['manage-orders']
            ],
        ];

        foreach ($roles as $roleSlug => $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleSlug],
                [
                    'name' => $roleData['name'],
                    'slug' => $roleSlug,
                    'display_name' => $roleData['name'],
                    'description' => "Role for {$roleData['name']}"
                ]
            );

            // Attach permissions to role
            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }

    private function createAccount(): Account
    {
        $this->command->info('Creating account...');

        return Account::firstOrCreate(
            ['name' => 'MSC Wound Care Test Account'],
            [
                'name' => 'MSC Wound Care Test Account',
            ]
        );
    }

    private function createOrganization(Account $account): Organization
    {
        $this->command->info('Creating organization...');

        return Organization::firstOrCreate(
            ['name' => 'MSC Test Organization'],
            [
                'name' => 'MSC Test Organization',
                'account_id' => $account->id,
                'type' => 'Hospital',
                'status' => 'active',
                'tax_id' => '12-3456789',
                'phone' => '555-TEST-ORG',
                'email' => 'admin@mscwound.com',
                'address' => '123 Healthcare Drive',
                'city' => 'Medical City',
                'region' => 'TX',
                'country' => 'US',
                'postal_code' => '12345',
            ]
        );
    }

    private function createFacilities(Organization $organization): array
    {
        $this->command->info('Creating facilities...');

        $facilities = [];

        for ($i = 1; $i <= 2; $i++) {
            $facility = Facility::firstOrCreate(
                ['name' => "Test Facility {$i}"],
                [
                    'name' => "Test Facility {$i}",
                    'organization_id' => $organization->id,
                    'facility_type' => 'Hospital',
                    'npi' => "123456789{$i}",
                    'phone' => "555-FAC-000{$i}",
                    'email' => "facility{$i}@mscwound.com",
                    'address' => "{$i}00 Facility Street",
                    'city' => 'Medical City',
                    'state' => 'TX',
                    'zip_code' => "1234{$i}",
                    'status' => 'active',
                    'active' => true,
                ]
            );

            $facilities[] = $facility;
            $this->command->info("Created facility: {$facility->name}");
        }

        return $facilities;
    }

    private function createUsers(Account $account, Organization $organization, array $facilities): void
    {
        $this->command->info('Creating users...');

        // Create 2 Admins
        $adminRole = Role::where('slug', 'admin')->first();
        for ($i = 1; $i <= 2; $i++) {
            $admin = User::firstOrCreate(
                ['email' => "admin{$i}@mscwound.com"],
                [
                    'first_name' => "Admin",
                    'last_name' => "User {$i}",
                    'email' => "admin{$i}@mscwound.com",
                    'password' => Hash::make('password'),
                    'account_id' => $account->id,
                    'current_organization_id' => $organization->id,
                    'is_verified' => true,
                ]
            );

            // Attach to organization
            $admin->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role' => 'admin',
                    'is_primary' => true,
                    'is_active' => true,
                ]
            ]);

            // Assign role
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);

            $this->command->info("Created admin: {$admin->email}");
        }

        // Create 2 Office Managers (one for each facility)
        $omRole = Role::where('slug', 'office-manager')->first();
        foreach ($facilities as $index => $facility) {
            $om = User::firstOrCreate(
                ['email' => "om" . ($index + 1) . "@mscwound.com"],
                [
                    'first_name' => "Office Manager",
                    'last_name' => "User " . ($index + 1),
                    'email' => "om" . ($index + 1) . "@mscwound.com",
                    'password' => Hash::make('password'),
                    'account_id' => $account->id,
                    'current_organization_id' => $organization->id,
                    'is_verified' => true,
                ]
            );

            // Attach to organization
            $om->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role' => 'office_manager',
                    'is_primary' => true,
                    'is_active' => true,
                ]
            ]);

            // Attach to facility
            $om->facilities()->syncWithoutDetaching([
                $facility->id => [
                    'relationship_type' => 'employee',
                    'role' => 'office_manager',
                    'is_primary' => true,
                ]
            ]);

            // Assign role
            $om->roles()->syncWithoutDetaching([$omRole->id]);

            $this->command->info("Created office manager: {$om->email} for {$facility->name}");
        }

        // Create 2 Providers
        $providerRole = Role::where('slug', 'provider')->first();

        // Provider 1 - credentialed with facility 1 only
        $provider1 = User::firstOrCreate(
            ['email' => 'provider1@mscwound.com'],
            [
                'first_name' => 'Dr. John',
                'last_name' => 'Provider',
                'email' => 'provider1@mscwound.com',
                'password' => Hash::make('password'),
                'account_id' => $account->id,
                'current_organization_id' => $organization->id,
                'npi_number' => '1234567890',
                'dea_number' => 'BP1234567',
                'license_number' => 'TX12345',
                'license_state' => 'TX',
                'license_expiry' => Carbon::now()->addYear(),
                'credentials' => ['MD', 'CWSP'],
                'is_verified' => true,
            ]
        );

        // Attach to organization
        $provider1->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role' => 'provider',
                'is_primary' => true,
                'is_active' => true,
            ]
        ]);

        // Attach to facility 1 only
        $provider1->facilities()->syncWithoutDetaching([
            $facilities[0]->id => [
                'relationship_type' => 'credentialed',
                'role' => 'provider',
                'is_primary' => true,
            ]
        ]);

        // Assign role
        $provider1->roles()->syncWithoutDetaching([$providerRole->id]);

        $this->command->info("Created provider: {$provider1->email} (credentialed with {$facilities[0]->name})");

        // Provider 2 - associated with both facilities
        $provider2 = User::firstOrCreate(
            ['email' => 'provider2@mscwound.com'],
            [
                'first_name' => 'Dr. Jane',
                'last_name' => 'Provider',
                'email' => 'provider2@mscwound.com',
                'password' => Hash::make('password'),
                'account_id' => $account->id,
                'current_organization_id' => $organization->id,
                'npi_number' => '1234567891',
                'dea_number' => 'BP1234568',
                'license_number' => 'TX12346',
                'license_state' => 'TX',
                'license_expiry' => Carbon::now()->addYear(),
                'credentials' => ['MD', 'CWS'],
                'is_verified' => true,
            ]
        );

        // Attach to organization
        $provider2->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role' => 'provider',
                'is_primary' => true,
                'is_active' => true,
            ]
        ]);

        // Attach to both facilities
        foreach ($facilities as $index => $facility) {
            $provider2->facilities()->syncWithoutDetaching([
                $facility->id => [
                    'relationship_type' => 'associated',
                    'role' => 'provider',
                    'is_primary' => $index === 0, // First facility is primary
                ]
            ]);
        }

        // Assign role
        $provider2->roles()->syncWithoutDetaching([$providerRole->id]);

        $this->command->info("Created provider: {$provider2->email} (associated with both facilities)");
    }

    private function createManufacturersAndCategories(): void
    {
        $this->command->info('Creating manufacturers and categories...');

        // Create categories
        $categories = [
            ['name' => 'Wound Care Matrix', 'slug' => 'wound-care-matrix'],
            ['name' => 'Skin Substitutes', 'slug' => 'skin-substitutes'],
            ['name' => 'Negative Pressure', 'slug' => 'negative-pressure'],
            ['name' => 'Advanced Dressings', 'slug' => 'advanced-dressings'],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData + ['is_active' => true]
            );
        }

        // Create manufacturers
        $manufacturers = [
            [
                'name' => 'Integra LifeSciences',
                'slug' => 'integra-lifesciences',
                'contact_email' => 'orders@integralife.com',
                'contact_phone' => '555-INTEGRA',
                'website' => 'https://integralife.com',
                'is_active' => true,
            ],
            [
                'name' => 'MiMedx',
                'slug' => 'mimedx',
                'contact_email' => 'orders@mimedx.com',
                'contact_phone' => '555-MIMEDX',
                'website' => 'https://mimedx.com',
                'is_active' => true,
            ],
            [
                'name' => 'Organogenesis',
                'slug' => 'organogenesis',
                'contact_email' => 'orders@organogenesis.com',
                'contact_phone' => '555-ORGANO',
                'website' => 'https://organogenesis.com',
                'is_active' => true,
            ],
        ];

        foreach ($manufacturers as $manufacturerData) {
            Manufacturer::firstOrCreate(
                ['slug' => $manufacturerData['slug']],
                $manufacturerData
            );
        }
    }

    private function createProducts(): void
    {
        $this->command->info('Creating products...');

        $integra = Manufacturer::where('slug', 'integra-lifesciences')->first();
        $mimedx = Manufacturer::where('slug', 'mimedx')->first();
        $organogenesis = Manufacturer::where('slug', 'organogenesis')->first();

        $woundCareCategory = Category::where('slug', 'wound-care-matrix')->first();
        $skinSubsCategory = Category::where('slug', 'skin-substitutes')->first();

        $products = [
            // Integra Products
            [
                'sku' => 'INT-DRT-001',
                'name' => 'Dermal Regeneration Template',
                'description' => 'Advanced dermal regeneration template for wound healing',
                'manufacturer' => $integra->name,
                'manufacturer_id' => $integra->id,
                'category' => $woundCareCategory->name,
                'category_id' => $woundCareCategory->id,
                'national_asp' => 450.00,
                'price_per_sq_cm' => 18.50,
                'q_code' => 'Q4104',
                'available_sizes' => json_encode(['2x3cm', '4x5cm', '7x10cm', '10x12cm']),
                'graph_type' => 'dermal_template',
                'commission_rate' => 5.00,
                'is_active' => true,
            ],
            [
                'sku' => 'INT-BWM-002',
                'name' => 'Bilayer Wound Matrix',
                'description' => 'Bilayer matrix for complex wound management',
                'manufacturer' => $integra->name,
                'manufacturer_id' => $integra->id,
                'category' => $woundCareCategory->name,
                'category_id' => $woundCareCategory->id,
                'national_asp' => 625.00,
                'price_per_sq_cm' => 25.00,
                'q_code' => 'Q4104',
                'available_sizes' => json_encode(['3x4cm', '5x7cm', '8x10cm', '10x15cm']),
                'graph_type' => 'bilayer_matrix',
                'commission_rate' => 5.50,
                'is_active' => true,
            ],

            // MiMedx Products
            [
                'sku' => 'MMX-EPF-001',
                'name' => 'EpiFix Dehydrated Human Amnion',
                'description' => 'Dehydrated human amnion/chorion membrane',
                'manufacturer' => $mimedx->name,
                'manufacturer_id' => $mimedx->id,
                'category' => $skinSubsCategory->name,
                'category_id' => $skinSubsCategory->id,
                'national_asp' => 750.00,
                'price_per_sq_cm' => 30.00,
                'q_code' => 'Q4186',
                'available_sizes' => json_encode(['2x2cm', '4x4cm', '6x6cm', '8x8cm']),
                'graph_type' => 'amniotic_membrane',
                'commission_rate' => 6.00,
                'is_active' => true,
            ],
            [
                'sku' => 'MMX-AMN-002',
                'name' => 'AmnioFix Injectable',
                'description' => 'Injectable amniotic membrane matrix',
                'manufacturer' => $mimedx->name,
                'manufacturer_id' => $mimedx->id,
                'category' => $skinSubsCategory->name,
                'category_id' => $skinSubsCategory->id,
                'national_asp' => 525.00,
                'price_per_sq_cm' => 21.00,
                'q_code' => 'Q4188',
                'available_sizes' => json_encode(['1ml', '2ml', '5ml']),
                'graph_type' => 'injectable_matrix',
                'commission_rate' => 5.75,
                'is_active' => true,
            ],

            // Organogenesis Products
            [
                'sku' => 'ORG-APL-001',
                'name' => 'Apligraf Living Skin Substitute',
                'description' => 'Living bilayered skin substitute',
                'manufacturer' => $organogenesis->name,
                'manufacturer_id' => $organogenesis->id,
                'category' => $skinSubsCategory->name,
                'category_id' => $skinSubsCategory->id,
                'national_asp' => 1250.00,
                'price_per_sq_cm' => 50.00,
                'q_code' => 'Q4101',
                'available_sizes' => json_encode(['44cm²', '77cm²']),
                'graph_type' => 'living_skin_substitute',
                'commission_rate' => 7.00,
                'is_active' => true,
            ],
            [
                'sku' => 'ORG-DRM-002',
                'name' => 'Dermagraft Cryopreserved',
                'description' => 'Cryopreserved human fibroblast-derived dermal substitute',
                'manufacturer' => $organogenesis->name,
                'manufacturer_id' => $organogenesis->id,
                'category' => $skinSubsCategory->name,
                'category_id' => $skinSubsCategory->id,
                'national_asp' => 950.00,
                'price_per_sq_cm' => 38.00,
                'q_code' => 'Q4106',
                'available_sizes' => json_encode(['5x5cm', '5x7.5cm', '7.5x7.5cm']),
                'graph_type' => 'dermal_substitute',
                'commission_rate' => 6.50,
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            $this->command->info("Created product: {$productData['name']} ({$productData['sku']})");
        }
    }
}
