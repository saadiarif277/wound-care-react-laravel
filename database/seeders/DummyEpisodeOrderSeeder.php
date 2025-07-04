<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Models\Order\Manufacturer;
use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\Order\Product;
use Illuminate\Support\Str;

class DummyEpisodeOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DISABLED: No longer creating mock episodes and orders - using live data only
        $this->command->info('DummyEpisodeOrderSeeder disabled - using live data only');
        return;
        // Create some manufacturers if they don't exist
        $manufacturers = [
            ['name' => 'ACZ & Associates', 'contact_email' => 'orders@acz.com'],
            ['name' => 'Advanced Solutions', 'contact_email' => 'orders@advanced.com'],
            ['name' => 'BioWound Technologies', 'contact_email' => 'orders@biowound.com'],
            ['name' => 'Extremity Care', 'contact_email' => 'orders@extremity.com'],
        ];

        foreach ($manufacturers as $manufacturerData) {
            Manufacturer::firstOrCreate(
                ['name' => $manufacturerData['name']],
                [
                    'name' => $manufacturerData['name'],
                    'contact_email' => $manufacturerData['contact_email'],
                    'contact_phone' => '555-0123',
                    'address' => '123 Medical Drive',
                    'city' => 'Healthcare City',
                    'state' => 'CA',
                    'zip' => '90210',
                ]
            );
        }

        // Get or create some facilities
        $facilities = Facility::firstOrCreate(
            ['name' => 'Sample Medical Center'],
            [
                'name' => 'Sample Medical Center',
                'address' => '456 Health Street',
                'city' => 'Medical City',
                'state' => 'CA',
                'zip' => '90211',
                'phone' => '555-0456',
                'active' => true,
            ]
        );

        // Get or create some providers
        $providers = [
            User::firstOrCreate(
                ['email' => 'dr.smith@sample.com'],
                [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'dr.smith@sample.com',
                    'npi_number' => '1234567890',
                    'phone' => '555-0789',
                ]
            ),
            User::firstOrCreate(
                ['email' => 'dr.jones@sample.com'],
                [
                    'first_name' => 'Sarah',
                    'last_name' => 'Jones',
                    'email' => 'dr.jones@sample.com',
                    'npi_number' => '0987654321',
                    'phone' => '555-0321',
                ]
            ),
        ];

        // Get or create some products
        $products = [
            Product::firstOrCreate(
                ['name' => 'Wound Care Dressing'],
                [
                    'name' => 'Wound Care Dressing',
                    'sku' => 'WC-001',
                    'q_code' => 'A6021',
                    'manufacturer' => 'ACZ & Associates',
                    'category' => 'Dressings',
                    'unit_price' => 25.00,
                ]
            ),
            Product::firstOrCreate(
                ['name' => 'Advanced Wound Gel'],
                [
                    'name' => 'Advanced Wound Gel',
                    'sku' => 'AWG-002',
                    'q_code' => 'A6022',
                    'manufacturer' => 'Advanced Solutions',
                    'category' => 'Gels',
                    'unit_price' => 45.00,
                ]
            ),
            Product::firstOrCreate(
                ['name' => 'BioWound Matrix'],
                [
                    'name' => 'BioWound Matrix',
                    'sku' => 'BWM-003',
                    'q_code' => 'A6023',
                    'manufacturer' => 'BioWound Technologies',
                    'category' => 'Matrices',
                    'unit_price' => 150.00,
                ]
            ),
        ];

        // Create episodes with different statuses
        $episodeStatuses = ['ready_for_review', 'ivr_verified', 'sent_to_manufacturer', 'tracking_added', 'completed'];
        $ivrStatuses = ['pending', 'verified', 'expired'];

        for ($i = 1; $i <= 15; $i++) {
            $manufacturer = Manufacturer::inRandomOrder()->first();
            $provider = $providers[array_rand($providers)];
            $status = $episodeStatuses[array_rand($episodeStatuses)];
            $ivrStatus = $ivrStatuses[array_rand($ivrStatuses)];

            $episode = PatientManufacturerIVREpisode::create([
                'id' => Str::uuid(),
                'patient_id' => 'Patient/' . Str::random(10),
                'patient_name' => 'Patient ' . $i,
                'patient_display_id' => 'P' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'manufacturer_id' => $manufacturer->id,
                'status' => $status,
                'ivr_status' => $ivrStatus,
                'verification_date' => $ivrStatus === 'verified' ? now()->subDays(rand(1, 30)) : null,
                'expiration_date' => $ivrStatus === 'verified' ? now()->addDays(rand(1, 90)) : null,
                'created_at' => now()->subDays(rand(1, 60)),
                'updated_at' => now()->subDays(rand(0, 30)),
            ]);

            // Create 1-3 orders for each episode
            $numOrders = rand(1, 3);
            for ($j = 1; $j <= $numOrders; $j++) {
                $orderStatuses = ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'];
                $orderStatus = $orderStatuses[array_rand($orderStatuses)];

                $productRequest = ProductRequest::create([
                    'request_number' => 'REQ-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . str_pad($j, 2, '0', STR_PAD_LEFT),
                    'order_number' => 'ORD-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . str_pad($j, 2, '0', STR_PAD_LEFT),
                    'provider_id' => $provider->id,
                    'facility_id' => $facilities->id,
                    'patient_display_id' => 'P' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'patient_fhir_id' => 'Patient/' . Str::random(10),
                    'ivr_episode_id' => $episode->id,
                    'order_status' => $orderStatus,
                    'wound_type' => ['diabetic_ulcer', 'pressure_ulcer', 'venous_ulcer'][array_rand(['diabetic_ulcer', 'pressure_ulcer', 'venous_ulcer'])],
                    'wound_location' => ['foot', 'leg', 'back'][array_rand(['foot', 'leg', 'back'])],
                    'expected_service_date' => now()->addDays(rand(1, 30)),
                    'total_order_value' => rand(50, 500),
                    'submitted_at' => now()->subDays(rand(1, 30)),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ]);

                // Attach 1-2 products to each order
                $numProducts = rand(1, 2);
                $selectedProducts = $products->random($numProducts);

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 3);
                    $unitPrice = $product->unit_price;
                    $totalPrice = $unitPrice * $quantity;

                    $productRequest->products()->attach($product->id, [
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                    ]);
                }
            }
        }

        $this->command->info('Dummy episodes and orders created successfully!');
    }
}
