<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Fhir\Facility;
use App\Models\Users\Organization\Organization;
use App\Models\Account;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First create an account since organizations require one
        $accountId = (string) Str::uuid();
        $account = Account::create([
            'id' => $accountId,
            'name' => 'Test Healthcare Account',
        ]);

        // Then create an organization since facilities require one
        $organizationId = (string) Str::uuid();
        $organization = Organization::create([
            'id' => $organizationId,
            'name' => 'Test Healthcare Network',
            'account_id' => $accountId,
            'email' => 'admin@testhealthcare.com',
            'phone' => '(555) 000-0000',
            'address' => '100 Healthcare Plaza',
            'city' => 'Medical City',
            'region' => 'MC',
            'country' => 'US',
            'postal_code' => '12345',
        ]);

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
            ],
        ];

        foreach ($facilities as $facilityData) {
            DB::table('facilities')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $facilityData['organization_id'],
                'name' => $facilityData['name'],
                'facility_type' => $facilityData['facility_type'],
                'address' => $facilityData['address'],
                'city' => $facilityData['city'],
                'state' => $facilityData['state'],
                'zip_code' => $facilityData['zip_code'],
                'phone' => $facilityData['phone'],
                'email' => $facilityData['email'],
                'npi' => $facilityData['npi'],
                'active' => $facilityData['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
