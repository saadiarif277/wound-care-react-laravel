<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Fhir\Facility;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use Illuminate\Support\Facades\DB;

class QuickFixFacilitySeeder extends Seeder
{
    public function run()
    {
        // Ensure we have customer organization for facilities
        $customerOrganization = Organization::where('name', '!=', 'MSC Wound Care')->first();

        if (!$customerOrganization) {
            $account = \App\Models\Account::first() ?? \App\Models\Account::create([
                'name' => 'Default Account',
            ]);

            $customerOrganization = Organization::create([
                'account_id' => $account->id,
                'name' => 'Test Healthcare Network',
                'email' => 'admin@testhealthcare.com',
                'phone' => '(555) 000-0000',
                'address' => '100 Healthcare Plaza',
                'city' => 'Medical City',
                'region' => 'MC',
                'country' => 'US',
                'postal_code' => '12345',
            ]);
        }

        // Ensure we have MSC Wound Care organization for internal staff
        $mscOrganization = Organization::firstOrCreate(
            ['name' => 'MSC Wound Care'],
            [
                'account_id' => $customerOrganization->account_id,
                'name' => 'MSC Wound Care',
                'email' => 'admin@mscwoundcare.com',
                'phone' => '(555) 123-4567',
                'address' => '123 MSC Way',
                'city' => 'MSC City',
                'region' => 'MC',
                'country' => 'US',
                'postal_code' => '12346',
            ]
        );

        // Create facilities if they don't exist (these belong to customer organizations)
        $facilities = [
            [
                'organization_id' => $customerOrganization->id,
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
                'facility_type' => 'hospital',
            ],
            [
                'organization_id' => $customerOrganization->id,
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
            ],
            [
                'organization_id' => $customerOrganization->id,
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
            ],
        ];

        $createdFacilityIds = [];

        foreach ($facilities as $facilityData) {
            $facility = Facility::updateOrCreate(
                ['npi' => $facilityData['npi']],
                $facilityData
            );
            $createdFacilityIds[] = $facility->id;

            $this->command->info("Created/Updated facility: {$facility->name}");
        }

        // Associate all providers with facilities
        $providers = User::whereHas('roles', function($q) {
            $q->whereIn('slug', ['provider', 'office-manager']);
        })->get();

        foreach ($providers as $provider) {
            // Set current organization if not set
            if (!$provider->current_organization_id) {
                $provider->current_organization_id = $customerOrganization->id;
                $provider->save();
                $this->command->info("Set organization for user: {$provider->email}");
            }

            // Create organization_users relationship if it doesn't exist
            $existingOrgUser = DB::table('organization_users')
                ->where('user_id', $provider->id)
                ->where('organization_id', $customerOrganization->id)
                ->first();

            if (!$existingOrgUser) {
                DB::table('organization_users')->insert([
                    'user_id' => $provider->id,
                    'organization_id' => $customerOrganization->id,
                    'role' => $provider->hasRole('office-manager') ? 'office-manager' : 'provider',
                    'is_primary' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created organization_users relationship for: {$provider->email}");
            }

            // Associate with facilities if not already associated
            $existingFacilityIds = $provider->facilities()->pluck('facilities.id')->toArray();
            $facilityIdsToAttach = array_diff($createdFacilityIds, $existingFacilityIds);

            if (!empty($facilityIdsToAttach)) {
                foreach ($facilityIdsToAttach as $facilityId) {
                    $provider->facilities()->attach($facilityId, [
                        'relationship_type' => 'provider',
                        'role' => $provider->hasRole('office-manager') ? 'office_manager' : 'provider'
                    ]);
                }
                $this->command->info("Associated {$provider->email} with " . count($facilityIdsToAttach) . " facilities");
            }
        }

        // Also associate admin users with all facilities
        $admins = User::whereHas('roles', function($q) {
            $q->whereIn('slug', ['msc-admin', 'super-admin']);
        })->get();

        foreach ($admins as $admin) {
            // Set current organization if not set
            if (!$admin->current_organization_id) {
                $admin->current_organization_id = $mscOrganization->id;
                $admin->save();
            }

            // Create organization_users relationship if it doesn't exist
            $existingOrgUser = DB::table('organization_users')
                ->where('user_id', $admin->id)
                ->where('organization_id', $mscOrganization->id)
                ->first();

            if (!$existingOrgUser) {
                DB::table('organization_users')->insert([
                    'user_id' => $admin->id,
                    'organization_id' => $mscOrganization->id,
                    'role' => $admin->hasRole('super-admin') ? 'super-admin' : 'msc-admin',
                    'is_primary' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created organization_users relationship for admin: {$admin->email}");
            }

            // Associate with all facilities
            $existingFacilityIds = $admin->facilities()->pluck('facilities.id')->toArray();
            $facilityIdsToAttach = array_diff($createdFacilityIds, $existingFacilityIds);

            if (!empty($facilityIdsToAttach)) {
                foreach ($facilityIdsToAttach as $facilityId) {
                    $admin->facilities()->attach($facilityId, [
                        'relationship_type' => 'admin',
                        'role' => 'admin'
                    ]);
                }
                $this->command->info("Associated admin {$admin->email} with " . count($facilityIdsToAttach) . " facilities");
            }
        }

        $this->command->info('Facility associations completed!');

        // Show summary
        $totalFacilities = Facility::count();
        $activeFacilities = Facility::where('active', true)->count();
        $this->command->info("Total facilities: {$totalFacilities} (Active: {$activeFacilities})");

        // Show user-facility associations
        $associations = DB::table('facility_user')->count();
        $this->command->info("Total user-facility associations: {$associations}");

        // Ensure provider@example.com has facility associations
        $providerUser = \App\Models\User::where('email', 'provider@example.com')->first();
        if ($providerUser && $associations == 0) {
            $this->command->info("Adding facility associations for provider@example.com...");

            // Get all facilities
            $allFacilities = \App\Models\Fhir\Facility::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->get();

            foreach ($allFacilities as $facility) {
                DB::table('facility_user')->insert([
                    'facility_id' => $facility->id,
                    'user_id' => $providerUser->id,
                    'relationship_type' => 'provider',
                    'role' => 'provider',
                    'is_primary' => $facility->id === $allFacilities->first()->id, // Make first one primary
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("Added {$allFacilities->count()} facility associations for provider@example.com");
        }
    }
}
