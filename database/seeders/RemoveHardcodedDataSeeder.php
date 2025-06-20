<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class RemoveHardcodedDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed insurance product rules
        $this->seedInsuranceProductRules();
        
        // Seed diagnosis codes
        $this->seedDiagnosisCodes();
        
        // Seed wound types
        $this->seedWoundTypes();
        
        // Update products with MUE limits
        $this->updateProductMueLimits();
        
        // Seed MSC contacts
        $this->seedMscContacts();
    }
    
    private function seedInsuranceProductRules(): void
    {
        // Check if rules already exist
        $existingCount = DB::table('insurance_product_rules')->count();
        if ($existingCount > 0) {
            $this->command->info("Insurance product rules already exist ($existingCount found), skipping...");
            return;
        }

        $rules = [
            // PPO/Commercial rules
            [
                'id' => Str::uuid(),
                'insurance_type' => 'ppo',
                'state_code' => null,
                'wound_size_min' => null,
                'wound_size_max' => null,
                'allowed_product_codes' => json_encode(['Q4154']), // BioVance
                'coverage_message' => 'PPO/Commercial insurance covers BioVance for any wound size',
                'requires_consultation' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'insurance_type' => 'commercial',
                'state_code' => null,
                'wound_size_min' => null,
                'wound_size_max' => null,
                'allowed_product_codes' => json_encode(['Q4154']), // BioVance
                'coverage_message' => 'PPO/Commercial insurance covers BioVance for any wound size',
                'requires_consultation' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Medicare rules
            [
                'id' => Str::uuid(),
                'insurance_type' => 'medicare',
                'state_code' => null,
                'wound_size_min' => 0,
                'wound_size_max' => 250,
                'allowed_product_codes' => json_encode(['Q4250', 'Q4290']), // Amnio AMP, Membrane Wrap Hydro
                'coverage_message' => 'Medicare covers Amnio AMP or Membrane Wrap Hydro for wounds 0-250 sq cm',
                'requires_consultation' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'insurance_type' => 'medicare',
                'state_code' => null,
                'wound_size_min' => 251,
                'wound_size_max' => 450,
                'allowed_product_codes' => json_encode(['Q4290']), // Membrane Wrap Hydro only
                'coverage_message' => 'Medicare covers only Membrane Wrap Hydro for wounds 251-450 sq cm',
                'requires_consultation' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'insurance_type' => 'medicare',
                'state_code' => null,
                'wound_size_min' => 451,
                'wound_size_max' => null,
                'allowed_product_codes' => json_encode([]),
                'coverage_message' => 'Wounds larger than 450 sq cm require consultation with MSC Admin',
                'requires_consultation' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        // Medicaid state-specific rules for Membrane Wrap states
        $membraneWrapStates = ['TX', 'FL', 'GA', 'TN', 'NC', 'AL', 'OH', 'MI', 'IN', 'KY', 'MO', 'OK', 'SC', 'LA', 'MS', 'WA', 'OR', 'MT', 'SD', 'UT', 'AZ', 'CA', 'CO'];
        foreach ($membraneWrapStates as $state) {
            $rules[] = [
                'id' => Str::uuid(),
                'insurance_type' => 'medicaid',
                'state_code' => $state,
                'wound_size_min' => null,
                'wound_size_max' => null,
                'allowed_product_codes' => json_encode(['Q4290', 'Q4205']), // Membrane Wrap products
                'coverage_message' => "Medicaid in {$state} covers Membrane Wrap products",
                'requires_consultation' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Medicaid state-specific rules for Restorigen states
        $restorigenStates = ['TX', 'CA', 'LA', 'MD'];
        foreach ($restorigenStates as $state) {
            // Check if state already exists (TX and CA are in both lists)
            $existingRule = collect($rules)->first(fn($r) => $r['insurance_type'] === 'medicaid' && $r['state_code'] === $state);
            if ($existingRule) {
                // Update existing rule to include both products
                $key = array_search($existingRule, $rules);
                $codes = array_unique(array_merge(json_decode($existingRule['allowed_product_codes'], true), ['Q4191']));
                $rules[$key]['allowed_product_codes'] = json_encode($codes);
                $rules[$key]['coverage_message'] = "Medicaid in {$state} covers Membrane Wrap products and Restorigen";
            } else {
                $rules[] = [
                    'id' => Str::uuid(),
                    'insurance_type' => 'medicaid',
                    'state_code' => $state,
                    'wound_size_min' => null,
                    'wound_size_max' => null,
                    'allowed_product_codes' => json_encode(['Q4191']), // Restorigen
                    'coverage_message' => "Medicaid in {$state} covers Restorigen",
                    'requires_consultation' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Default Medicaid rule for other states
        $rules[] = [
            'id' => Str::uuid(),
            'insurance_type' => 'medicaid',
            'state_code' => null, // Applies to all states not specifically listed
            'wound_size_min' => null,
            'wound_size_max' => null,
            'allowed_product_codes' => json_encode(['Q4271', 'Q4154', 'Q4238']), // Complete FT, BioVance, Derm-maxx
            'coverage_message' => 'Medicaid covers Complete FT, BioVance, or Derm-maxx',
            'requires_consultation' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        DB::table('insurance_product_rules')->insert($rules);
    }
    
    private function seedDiagnosisCodes(): void
    {
        // Check if diagnosis codes already exist
        $existingCount = DB::table('diagnosis_codes')->count();
        if ($existingCount > 0) {
            $this->command->info("Diagnosis codes already exist ($existingCount found), skipping...");
            return;
        }

        $codes = [
            // Yellow codes
            [
                'id' => Str::uuid(),
                'code' => 'E11.621',
                'description' => 'Type 2 diabetes mellitus with foot ulcer',
                'category' => 'yellow',
                'specialty' => 'diabetic',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'E11.622',
                'description' => 'Type 2 diabetes mellitus with other skin ulcer',
                'category' => 'yellow',
                'specialty' => 'diabetic',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'E10.621',
                'description' => 'Type 1 diabetes mellitus with foot ulcer',
                'category' => 'yellow',
                'specialty' => 'diabetic',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Orange codes
            [
                'id' => Str::uuid(),
                'code' => 'L97.411',
                'description' => 'Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin',
                'category' => 'orange',
                'specialty' => 'pressure',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'L97.412',
                'description' => 'Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed',
                'category' => 'orange',
                'specialty' => 'pressure',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'L97.511',
                'description' => 'Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin',
                'category' => 'orange',
                'specialty' => 'pressure',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('diagnosis_codes')->insert($codes);
    }
    
    private function seedWoundTypes(): void
    {
        // Check if wound types already exist
        $existingCount = DB::table('wound_types')->count();
        if ($existingCount > 0) {
            $this->command->info("Wound types already exist ($existingCount found), skipping...");
            return;
        }

        $types = [
            [
                'id' => Str::uuid(),
                'code' => 'diabetic_foot_ulcer',
                'display_name' => 'Diabetic Foot Ulcer',
                'description' => 'Ulceration of the foot in patients with diabetes',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'venous_leg_ulcer',
                'display_name' => 'Venous Leg Ulcer',
                'description' => 'Open wound on the leg caused by poor blood flow in the veins',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'pressure_ulcer',
                'display_name' => 'Pressure Ulcer',
                'description' => 'Injury to skin and underlying tissue from prolonged pressure',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'surgical_wound',
                'display_name' => 'Surgical Wound',
                'description' => 'Incision or wound from surgical procedure',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'traumatic_wound',
                'display_name' => 'Traumatic Wound',
                'description' => 'Wound caused by trauma or injury',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'arterial_ulcer',
                'display_name' => 'Arterial Ulcer',
                'description' => 'Ulcer caused by poor arterial blood flow',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'chronic_ulcer',
                'display_name' => 'Chronic Ulcer',
                'description' => 'Non-specific chronic ulceration',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'code' => 'other',
                'display_name' => 'Other',
                'description' => 'Other wound types not listed above',
                'sort_order' => 99,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('wound_types')->insert($types);
    }
    
    private function updateProductMueLimits(): void
    {
        // Check which table exists
        $tableName = null;
        if (Schema::hasTable('msc_products')) {
            $tableName = 'msc_products';
        } elseif (Schema::hasTable('products')) {
            $tableName = 'products';
        } else {
            $this->command->warn("Neither 'products' nor 'msc_products' table exists, skipping MUE limits update...");
            return;
        }

        $mueLimits = [
            'Q4154' => 4000, // BioVance
            'Q4271' => 4000, // Complete FT
            'Q4238' => 4000, // Derm-maxx
            'Q4250' => 4000, // Amnio AMP
            'Q4290' => 4000, // Membrane Wrap Hydro
            'Q4205' => 4000, // Membrane Wrap
            'Q4191' => 4000, // Restorigen
        ];
        
        foreach ($mueLimits as $qCode => $limit) {
            DB::table($tableName)
                ->where('q_code', $qCode)
                ->update(['mue_limit' => $limit]);
        }
    }
    
    private function seedMscContacts(): void
    {
        // Check if MSC contacts already exist
        $existingCount = DB::table('msc_contacts')->count();
        if ($existingCount > 0) {
            $this->command->info("MSC contacts already exist ($existingCount found), skipping...");
            return;
        }

        $contacts = [
            [
                'id' => Str::uuid(),
                'department' => 'admin',
                'name' => 'MSC Admin Team',
                'email' => 'admin@mscwoundcare.com',
                'phone' => '1-800-MSC-CARE',
                'purpose' => 'consultation',
                'is_primary' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'department' => 'support',
                'name' => 'MSC Support',
                'email' => 'support@mscwoundcare.com',
                'phone' => '1-800-MSC-HELP',
                'purpose' => 'general',
                'is_primary' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('msc_contacts')->insert($contacts);
    }
}