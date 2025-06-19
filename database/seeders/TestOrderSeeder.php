<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use Illuminate\Support\Str;

class TestOrderSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();
        try {
            // Find or create necessary entities
            $account = \App\Models\Account::first() ?? \App\Models\Account::create([
                'name' => 'Test Account',
            ]);
        
        $organization = Organization::first() ?? Organization::create([
            'account_id' => $account->id,
            'name' => 'Test Medical Center',
            'email' => 'info@testmedical.com',
            'phone' => '555-000-1234',
            'address' => '456 Medical Center Way',
            'city' => 'Test City',
            'region' => 'TS',
            'country' => 'US',
            'postal_code' => '12345',
        ]);

        $facility = Facility::first() ?? Facility::create([
            'organization_id' => $organization->id,
            'name' => 'Test Wound Care Clinic',
            'facility_type' => 'outpatient',
            'address' => '123 Medical Plaza',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'phone' => '555-123-4567',
            'email' => 'clinic@test.com',
            'npi' => '1234567890',
            'active' => true,
        ]);

        // Find or create a provider user
        $providerRole = \App\Models\Role::where('slug', 'provider')->first();
        
        $provider = User::whereHas('roles', function($q) {
            $q->where('slug', 'provider');
        })->first();
        
        if (!$provider) {
            // Find or create an account first
            $account = \App\Models\Account::first() ?? \App\Models\Account::create([
                'name' => 'Test Account',
            ]);
            
            $provider = User::create([
                'account_id' => $account->id,
                'first_name' => 'Test',
                'last_name' => 'Provider',
                'email' => 'provider' . rand(1000, 9999) . '@test.com',
                'password' => bcrypt('password'),
                'npi_number' => '9876543210',
            ]);
            
            // Attach provider role if it exists
            if ($providerRole) {
                $provider->roles()->attach($providerRole->id);
            }
        }

        // Generate a test patient FHIR ID (since we don't have a patients table)
        $patientFhirId = 'patient-' . rand(100000, 999999);
        
        // Generate patient display ID using the format: first 2 letters + last 2 letters + 3 random digits
        $patientDisplayId = 'JODO' . rand(100, 999); // John Doe -> JODO###

        // Create a submitted ProductRequest
        $productRequest = ProductRequest::create([
            'request_number' => 'REQ-' . date('Ymd') . '-' . rand(1000, 9999),
            'provider_id' => $provider->id,
            'patient_fhir_id' => $patientFhirId,
            'patient_display_id' => $patientDisplayId,
            'facility_id' => $facility->id,
            'payer_name_submitted' => 'Medicare',
            'payer_id' => 'MED123',
            'expected_service_date' => now()->addDays(7),
            'wound_type' => 'DFU',
            'clinical_summary' => json_encode([
                'patient' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'dateOfBirth' => '1970-01-01',
                    'gender' => 'male',
                    'address' => '123 Test St, Test City, TS 12345',
                    'phone' => '555-123-4567',
                ],
                'woundDetails' => [
                    'type' => 'diabetic_foot_ulcer',
                    'location' => 'left_foot',
                    'duration' => '6_weeks',
                    'size' => 'medium',
                    'previousTreatments' => ['compression', 'debridement'],
                ],
            ]),
            'mac_validation_results' => json_encode([
                'status' => 'valid',
                'checked_at' => now()->toISOString(),
                'jurisdiction' => 'J5',
            ]),
            'mac_validation_status' => 'valid',
            'eligibility_results' => json_encode([
                'status' => 'eligible',
                'coverage_active' => true,
                'checked_at' => now()->toISOString(),
            ]),
            'eligibility_status' => 'eligible',
            'pre_auth_required_determination' => 'not_required',
            'clinical_opportunities' => json_encode([
                ['type' => 'wound_care', 'description' => 'Advanced wound dressing recommended'],
            ]),
            'order_status' => 'pending_ivr',
            'step' => 6,
            'submitted_at' => now(),
            'total_order_value' => 420.00,
            'acquiring_rep_id' => null,
        ]);

        // Find one product to attach (only one product allowed per request)
        $product = Product::inRandomOrder()->first();
        
        if (!$product) {
            // Create test product if none exist
            $product = Product::create([
                'name' => 'Advanced Wound Dressing',
                'sku' => 'AWD-001',
                'description' => 'Advanced moisture-retentive wound dressing',
                'national_asp' => 125.00,
                'manufacturer' => 'MedTech Solutions',
                'category' => 'Wound Dressings',
                'is_active' => true,
            ]);
        }

        // First, onboard the provider with this product (if not already onboarded)
        if (!$provider->products()->where('product_id', $product->id)->exists()) {
            $provider->products()->attach($product->id, [
                'onboarded_at' => now(),
                'onboarding_status' => 'active',
                'expiration_date' => now()->addYear(),
                'notes' => 'Provider onboarded for testing'
            ]);
        }
        
        // Attach product with multiple sizes/quantities (only one product type allowed)
        $sizes = ['5x5cm', '10x10cm', '15x15cm'];
        $totalValue = 0;
        
        foreach ($sizes as $size) {
            $quantity = rand(1, 3);
            $unitPrice = $product->national_asp ?? 100.00;
            $totalPrice = $unitPrice * $quantity;
            $totalValue += $totalPrice;
            
            $productRequest->products()->attach($product->id, [
                'quantity' => $quantity,
                'size' => $size,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ]);
        }
        
        // Update total order value
        $productRequest->update(['total_order_value' => $totalValue]);
        
        // Verify it was saved (without global scopes since we're not authenticated)
        $saved = ProductRequest::withoutGlobalScopes()->find($productRequest->id);
        if (!$saved) {
            throw new \Exception('ProductRequest was not saved to database!');
        }

        $this->command->info('Test ProductRequest created successfully!');
        $this->command->info('Request Number: ' . $productRequest->request_number);
        $this->command->info('Status: ' . $productRequest->order_status);
        $this->command->info('Product: ' . $product->name . ' with ' . count($sizes) . ' sizes');
        $this->command->info('Database ID: ' . $productRequest->id);
        
        DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}