<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Users\Organization\Organization;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏥 Seeding Healthcare Organizations...');

        // Get the default account ID
        $accountId = DB::table('accounts')->first()->id ?? 1;

        $organizations = [
            // Major Hospital Systems
            [
                'name' => 'Memorial Healthcare System',
                'email' => 'admin@memorialhealthcare.com',
                'phone' => '(555) 100-0001',
                'address' => '500 Medical Center Drive',
                'city' => 'Miami',
                'region' => 'FL',
                'country' => 'US',
                'postal_code' => '33136',
            ],
            [
                'name' => 'Northwestern Medical Group',
                'email' => 'info@northwesternmed.com',
                'phone' => '(312) 555-0100',
                'address' => '1200 N Lake Shore Dr',
                'city' => 'Chicago',
                'region' => 'IL',
                'country' => 'US',
                'postal_code' => '60611',
            ],
            [
                'name' => 'Cedars Medical Center',
                'email' => 'contact@cedarsmedical.com',
                'phone' => '(310) 555-0200',
                'address' => '8700 Beverly Blvd',
                'city' => 'Los Angeles',
                'region' => 'CA',
                'country' => 'US',
                'postal_code' => '90048',
            ],
            [
                'name' => 'Mount Sinai Health System',
                'email' => 'info@mountsinai.org',
                'phone' => '(212) 555-0300',
                'address' => '1 Gustave L. Levy Place',
                'city' => 'New York',
                'region' => 'NY',
                'country' => 'US',
                'postal_code' => '10029',
            ],
            [
                'name' => 'Texas Medical Center',
                'email' => 'admin@texasmedcenter.com',
                'phone' => '(713) 555-0400',
                'address' => '2450 Holcombe Blvd',
                'city' => 'Houston',
                'region' => 'TX',
                'country' => 'US',
                'postal_code' => '77021',
            ],
            
            // Regional Medical Networks
            [
                'name' => 'Pacific Coast Medical Network',
                'email' => 'info@pacificcoastmed.com',
                'phone' => '(206) 555-0500',
                'address' => '1959 NE Pacific St',
                'city' => 'Seattle',
                'region' => 'WA',
                'country' => 'US',
                'postal_code' => '98195',
            ],
            [
                'name' => 'Rocky Mountain Healthcare Alliance',
                'email' => 'contact@rmhalliance.com',
                'phone' => '(303) 555-0600',
                'address' => '1719 E 19th Ave',
                'city' => 'Denver',
                'region' => 'CO',
                'country' => 'US',
                'postal_code' => '80218',
            ],
            [
                'name' => 'Southeast Regional Medical Group',
                'email' => 'admin@southeastmedical.com',
                'phone' => '(404) 555-0700',
                'address' => '1364 Clifton Rd NE',
                'city' => 'Atlanta',
                'region' => 'GA',
                'country' => 'US',
                'postal_code' => '30322',
            ],
            
            // Specialty Care Centers
            [
                'name' => 'Advanced Wound Care Associates',
                'email' => 'info@advancedwoundcare.com',
                'phone' => '(602) 555-0800',
                'address' => '2910 N 3rd Ave',
                'city' => 'Phoenix',
                'region' => 'AZ',
                'country' => 'US',
                'postal_code' => '85013',
            ],
            [
                'name' => 'Comprehensive Diabetic Care Center',
                'email' => 'contact@diabeticcarecenter.com',
                'phone' => '(617) 555-0900',
                'address' => '75 Francis St',
                'city' => 'Boston',
                'region' => 'MA',
                'country' => 'US',
                'postal_code' => '02115',
            ],
            [
                'name' => 'Vascular & Wound Specialists',
                'email' => 'admin@vascularwound.com',
                'phone' => '(215) 555-1000',
                'address' => '3400 Civic Center Blvd',
                'city' => 'Philadelphia',
                'region' => 'PA',
                'country' => 'US',
                'postal_code' => '19104',
            ],
            
            // Community Health Networks
            [
                'name' => 'Community Care Partners',
                'email' => 'info@communitycarepartners.org',
                'phone' => '(513) 555-1100',
                'address' => '234 Goodman St',
                'city' => 'Cincinnati',
                'region' => 'OH',
                'country' => 'US',
                'postal_code' => '45219',
            ],
            [
                'name' => 'Rural Health Alliance',
                'email' => 'contact@ruralhealthalliance.org',
                'phone' => '(406) 555-1200',
                'address' => '310 Wendell Ave',
                'city' => 'Billings',
                'region' => 'MT',
                'country' => 'US',
                'postal_code' => '59101',
            ],
            [
                'name' => 'Unity Health Network',
                'email' => 'admin@unityhealthnet.com',
                'phone' => '(502) 555-1300',
                'address' => '550 S Jackson St',
                'city' => 'Louisville',
                'region' => 'KY',
                'country' => 'US',
                'postal_code' => '40202',
            ],
            
            // Urgent Care Chains
            [
                'name' => 'QuickCare Medical Centers',
                'email' => 'info@quickcaremedical.com',
                'phone' => '(407) 555-1400',
                'address' => '1500 N Orange Ave',
                'city' => 'Orlando',
                'region' => 'FL',
                'country' => 'US',
                'postal_code' => '32804',
            ],
            [
                'name' => 'Express Wound Care Clinics',
                'email' => 'contact@expresswoundcare.com',
                'phone' => '(702) 555-1500',
                'address' => '1800 W Charleston Blvd',
                'city' => 'Las Vegas',
                'region' => 'NV',
                'country' => 'US',
                'postal_code' => '89102',
            ],
            
            // Teaching Hospitals
            [
                'name' => 'University Medical Center',
                'email' => 'admin@universitymed.edu',
                'phone' => '(919) 555-1600',
                'address' => '101 Manning Dr',
                'city' => 'Chapel Hill',
                'region' => 'NC',
                'country' => 'US',
                'postal_code' => '27514',
            ],
            [
                'name' => 'Academic Health Sciences Center',
                'email' => 'info@academichsc.edu',
                'phone' => '(615) 555-1700',
                'address' => '1211 Medical Center Dr',
                'city' => 'Nashville',
                'region' => 'TN',
                'country' => 'US',
                'postal_code' => '37232',
            ],
            
            // Rehabilitation Centers
            [
                'name' => 'Advanced Rehabilitation Institute',
                'email' => 'contact@advancedrehab.com',
                'phone' => '(612) 555-1800',
                'address' => '909 Fulton St SE',
                'city' => 'Minneapolis',
                'region' => 'MN',
                'country' => 'US',
                'postal_code' => '55455',
            ],
            [
                'name' => 'Healing Hands Therapy Network',
                'email' => 'info@healinghandsnetwork.com',
                'phone' => '(503) 555-1900',
                'address' => '3181 SW Sam Jackson Park Rd',
                'city' => 'Portland',
                'region' => 'OR',
                'country' => 'US',
                'postal_code' => '97239',
            ],
        ];

        foreach ($organizations as $organizationData) {
            Organization::firstOrCreate(
                ['email' => $organizationData['email']],
                array_merge($organizationData, [
                    'account_id' => $accountId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ Healthcare Organizations created successfully!');
        $this->command->info('📊 Total Organizations: ' . Organization::count());
    }
} 