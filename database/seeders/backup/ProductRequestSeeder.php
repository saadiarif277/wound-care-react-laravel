<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\User;
use App\Models\Fhir\Facility;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProductRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get a provider user (or create one if needed) and ensure it has a valid UUID (length 36)
        $provider = User::where('email', 'provider@example.com')->first();
        if (!$provider) {
            $provider = User::first(); // Get any user for testing
        }
        if (!$provider || strlen($provider->id) !== 36) {
            $this->command->error('No valid provider (with UUID) found. Please run UserSeeder first.');
            return;
        }

        // Get facilities and filter out any non-UUIDs (i.e. ensure facility->id is a valid UUID)
        $facilities = Facility::where('active', true)->get()->filter(function ($facility) {
            return (strlen($facility->id) === 36);
        });
        if ($facilities->isEmpty()) {
            $this->command->error('No valid (active) facilities (with UUID) found. Please run FacilitySeeder first.');
            return;
        }

        // Get products
        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->error('No products found. Please run ProductSeeder first.');
            return;
        }

        // Sample product requests with different statuses (using valid UUIDs for provider_id and facility_id)
        $requests = [
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $provider->id, // (valid UUID)
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'JoSm001',
                'facility_id' => $facilities->random()->id, // (valid UUID)
                'payer_name_submitted' => 'Medicare Part B',
                'payer_id' => 'MEDICARE',
                'expected_service_date' => Carbon::now()->addDays(7),
                'wound_type' => 'DFU',
                'order_status' => 'draft',
                'step' => 3,
                'total_order_value' => 450.00,
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $provider->id, // (valid UUID)
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'MaJo002',
                'facility_id' => $facilities->random()->id, // (valid UUID)
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
            ],
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $provider->id, // (valid UUID)
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'RoWi003',
                'facility_id' => $facilities->random()->id, // (valid UUID)
                'payer_name_submitted' => 'Aetna',
                'payer_id' => 'AETNA',
                'expected_service_date' => Carbon::now()->addDays(10),
                'wound_type' => 'PU',
                'order_status' => 'processing',
                'step' => 6,
                'mac_validation_status' => 'passed',
                'eligibility_status' => 'eligible',
                'pre_auth_required_determination' => 'required',
                'total_order_value' => 920.00,
                'submitted_at' => Carbon::now()->subDays(2),
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $provider->id, // (valid UUID)
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'SaAn004',
                'facility_id' => $facilities->random()->id, // (valid UUID)
                'payer_name_submitted' => 'United Healthcare',
                'payer_id' => 'UHC',
                'expected_service_date' => Carbon::now()->addDays(3),
                'wound_type' => 'TW',
                'order_status' => 'approved',
                'step' => 6,
                'mac_validation_status' => 'passed',
                'eligibility_status' => 'eligible',
                'pre_auth_required_determination' => 'not_required',
                'total_order_value' => 550.00,
                'submitted_at' => Carbon::now()->subDays(4),
                'approved_at' => Carbon::now()->subDays(3),
                'created_at' => Carbon::now()->subDays(7),
            ],
            [
                'request_number' => 'PR-' . strtoupper(uniqid()),
                'provider_id' => $provider->id, // (valid UUID)
                'patient_fhir_id' => 'Patient/' . uniqid(),
                'patient_display_id' => 'DaGr005',
                'facility_id' => $facilities->random()->id, // (valid UUID)
                'payer_name_submitted' => 'Humana',
                'payer_id' => 'HUMANA',
                'expected_service_date' => Carbon::now()->addDays(14),
                'wound_type' => 'AU',
                'order_status' => 'rejected',
                'step' => 6,
                'mac_validation_status' => 'failed',
                'eligibility_status' => 'not_eligible',
                'total_order_value' => 380.00,
                'submitted_at' => Carbon::now()->subDays(6),
                'created_at' => Carbon::now()->subDays(8),
            ],
        ];

        foreach ($requests as $requestData) {
            $requestData['id'] = (string) Str::uuid();
            $request = ProductRequest::create($requestData);

            // Attach random products
            $selectedProducts = $products->random(rand(2, 4));
            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 3);
                $unitPrice = $product->price ?? rand(50, 200);
                $request->products()->attach($product->id, [
                    'quantity' => $quantity,
                    'size' => $product->sizes ? collect($product->sizes)->random() : null,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ]);
            }

            // Update total if not set
            if (!$request->total_order_value) {
                $request->update(['total_order_value' => $request->calculateTotalAmount()]);
            }
        }

        $this->command->info('Product requests seeded successfully!');
    }
}
