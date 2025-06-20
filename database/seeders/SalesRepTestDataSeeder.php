<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PatientIVRStatus;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\MscSalesRep;
use App\Models\Commissions\CommissionRecord;
use App\Models\Commissions\CommissionRule;
use App\Models\Users\Organization\Organization;
use Carbon\Carbon;

class SalesRepTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating comprehensive sales rep test data...');

        DB::beginTransaction();

        try {
            // 1. Create Sales Reps
            $salesReps = $this->createSalesReps();

            // 2. Create Provider Attribution
            $providers = $this->createProviderAttribution($salesReps);

            // 3. Create Commission Rules
            $this->createCommissionRules();

            // 4. Create Test Episodes with Orders
            $orders = $this->createEpisodesWithOrders($providers, $salesReps);

            // 5. Create Commission Records
            $this->createCommissionRecords($orders, $salesReps);

            DB::commit();
            $this->command->info('Successfully created sales rep test data!');

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('Sales rep test data seeder failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createSalesReps(): array
    {
        $this->command->info('Creating sales reps...');

        // Create main sales reps
        $mainReps = [
            [
                'name' => 'Michael Thompson',
                'email' => 'michael.thompson@medtech.com',
                'territory' => 'Northeast Region',
                'commission_rate_direct' => 5.00,
                'phone' => '555-001-0001',
                'is_active' => true,
                'parent_rep_id' => null,
            ],
            [
                'name' => 'Sarah Rodriguez',
                'email' => 'sarah.rodriguez@medtech.com',
                'territory' => 'Southeast Region',
                'commission_rate_direct' => 4.50,
                'phone' => '555-001-0002',
                'is_active' => true,
                'parent_rep_id' => null,
            ],
            [
                'name' => 'David Chen',
                'email' => 'david.chen@medtech.com',
                'territory' => 'West Coast Region',
                'commission_rate_direct' => 5.25,
                'phone' => '555-001-0003',
                'is_active' => true,
                'parent_rep_id' => null,
            ]
        ];

        $createdMainReps = [];
        foreach ($mainReps as $repData) {
            $rep = MscSalesRep::create($repData);
            $createdMainReps[] = $rep;
            $this->command->info("Created main rep: {$rep->name} ({$rep->territory})");
        }

        // Create sub-reps under main reps
        $subReps = [
            [
                'name' => 'Jennifer Walsh',
                'email' => 'jennifer.walsh@medtech.com',
                'territory' => 'New York Metro',
                'commission_rate_direct' => 3.00,
                'sub_rep_parent_share_percentage' => 60.00,
                'phone' => '555-002-0001',
                'is_active' => true,
                'parent_rep_id' => $createdMainReps[0]->id, // Under Michael Thompson
            ],
            [
                'name' => 'Robert Kim',
                'email' => 'robert.kim@medtech.com',
                'territory' => 'Boston Area',
                'commission_rate_direct' => 2.75,
                'sub_rep_parent_share_percentage' => 65.00,
                'phone' => '555-002-0002',
                'is_active' => true,
                'parent_rep_id' => $createdMainReps[0]->id, // Under Michael Thompson
            ],
            [
                'name' => 'Lisa Martinez',
                'email' => 'lisa.martinez@medtech.com',
                'territory' => 'Florida Central',
                'commission_rate_direct' => 3.25,
                'sub_rep_parent_share_percentage' => 55.00,
                'phone' => '555-002-0003',
                'is_active' => true,
                'parent_rep_id' => $createdMainReps[1]->id, // Under Sarah Rodriguez
            ]
        ];

        $createdSubReps = [];
        foreach ($subReps as $subRepData) {
            $subRep = MscSalesRep::create($subRepData);
            $createdSubReps[] = $subRep;
            $parentRep = collect($createdMainReps)->firstWhere('id', $subRep->parent_rep_id);
            $this->command->info("Created sub-rep: {$subRep->name} under {$parentRep->name}");
        }

        $allReps = array_merge($createdMainReps, $createdSubReps);
        return $allReps;
    }

    private function createProviderAttribution($salesReps): array
    {
        $this->command->info('Creating provider attribution...');

        // Get or create provider users
        $providers = [];
        $providerData = [
            ['first_name' => 'Dr. Amanda', 'last_name' => 'Williams', 'email' => 'amanda.williams@hospital.com', 'npi_number' => '1234567890'],
            ['first_name' => 'Dr. James', 'last_name' => 'Brown', 'email' => 'james.brown@clinic.com', 'npi_number' => '1234567891'],
            ['first_name' => 'Dr. Patricia', 'last_name' => 'Davis', 'email' => 'patricia.davis@medical.com', 'npi_number' => '1234567892'],
            ['first_name' => 'Dr. Christopher', 'last_name' => 'Miller', 'email' => 'christopher.miller@health.com', 'npi_number' => '1234567893'],
            ['first_name' => 'Dr. Elizabeth', 'last_name' => 'Wilson', 'email' => 'elizabeth.wilson@center.com', 'npi_number' => '1234567894'],
        ];

        foreach ($providerData as $index => $provData) {
            // Assign to sales reps (mix of main reps and sub-reps)
            $assignedRep = $salesReps[$index % count($salesReps)];
            $isSubRep = !is_null($assignedRep->parent_rep_id);

            $provider = User::firstOrCreate(
                ['email' => $provData['email']],
                array_merge($provData, [
                    'password' => bcrypt('password'),
                    'account_id' => 1, // Use the existing account
                    'acquired_by_rep_id' => $isSubRep ? $assignedRep->parent_rep_id : $assignedRep->id,
                    'acquired_by_subrep_id' => $isSubRep ? $assignedRep->id : null,
                    'acquisition_date' => Carbon::now()->subDays(rand(30, 365))
                ])
            );

            $providers[] = $provider;
            $repName = $isSubRep ? "{$assignedRep->name} (Sub-rep)" : $assignedRep->name;
            $this->command->info("Attributed provider {$provider->first_name} {$provider->last_name} to {$repName}");
        }

        return $providers;
    }

    private function createCommissionRules(): void
    {
        $this->command->info('Creating commission rules...');

        // Get actual manufacturer and product IDs for rules
        $manufacturer = \App\Models\Order\Manufacturer::first();
        $product = Product::first();

        $rules = [];

        if ($manufacturer) {
            $rules[] = [
                'target_type' => 'manufacturer',
                'target_id' => $manufacturer->id,
                'percentage_rate' => 5.0,
                'valid_from' => Carbon::now()->subYear(),
                'valid_to' => Carbon::now()->addYear(),
                'is_active' => true,
                'description' => "Commission rate for {$manufacturer->name}"
            ];
        }

        if ($product) {
            $rules[] = [
                'target_type' => 'product',
                'target_id' => $product->id,
                'percentage_rate' => 4.5,
                'valid_from' => Carbon::now()->subYear(),
                'valid_to' => Carbon::now()->addYear(),
                'is_active' => true,
                'description' => "Commission rate for {$product->name}"
            ];
        }

        foreach ($rules as $ruleData) {
            CommissionRule::create($ruleData);
        }
    }

    private function createEpisodesWithOrders($providers, $salesReps): array
    {
        $this->command->info('Creating episodes with orders...');

        $manufacturers = \App\Models\Order\Manufacturer::take(3)->get();
        if ($manufacturers->isEmpty()) {
            $this->command->warn('No manufacturers found. Creating test manufacturer...');
            $manufacturer = \App\Models\Order\Manufacturer::create([
                'name' => 'MedTech Solutions',
                'slug' => 'medtech-solutions',
                'contact_email' => 'orders@medtech.com',
                'contact_phone' => '555-MEDTECH',
                'is_active' => true
            ]);
            $manufacturers = collect([$manufacturer]);
        }

        $products = Product::take(5)->get();
        if ($products->isEmpty()) {
            $this->command->warn('No products found. Creating test products...');
            $products = collect();
            for ($i = 1; $i <= 3; $i++) {
                $products->push(Product::create([
                    'name' => "Advanced Wound Matrix {$i}",
                    'sku' => "AWM-00{$i}",
                    'description' => "Advanced bioengineered wound care product {$i}",
                    'national_asp' => 150.00 + ($i * 25),
                    'manufacturer' => $manufacturers->first()->name,
                    'category' => 'Wound Care Matrix',
                    'q_code' => "Q423{$i}",
                    'is_active' => true,
                ]));
            }
        }

        $orders = [];
        $episodeCount = 0;

        // Create 20 episodes/orders across the last 6 months
        for ($i = 0; $i < 20; $i++) {
            $episodeCount++;
            $provider = $providers[array_rand($providers)];
            $manufacturer = $manufacturers->random();
            $product = $products->random();

            // Generate patient data
            $patientNames = [
                ['John', 'Doe'], ['Jane', 'Smith'], ['Robert', 'Johnson'],
                ['Maria', 'Garcia'], ['William', 'Brown'], ['Linda', 'Davis'],
                ['Richard', 'Miller'], ['Susan', 'Wilson'], ['Joseph', 'Moore'], ['Karen', 'Taylor']
            ];
            $patientName = $patientNames[array_rand($patientNames)];
            $patientDisplayId = strtoupper(substr($patientName[0], 0, 2) . substr($patientName[1], 0, 2)) . rand(100, 999);

            // Create episode
            $episode = PatientIVRStatus::create([
                'patient_id' => \Illuminate\Support\Str::uuid(),
                'manufacturer_id' => $manufacturer->id,
                'status' => collect(['completed', 'tracking_added', 'sent_to_manufacturer'])->random(),
                'ivr_status' => 'verified',
                'verification_date' => Carbon::now()->subDays(rand(1, 180)),
                'expiration_date' => Carbon::now()->addDays(90),
                'frequency_days' => 90,
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
                'completed_at' => Carbon::now()->subDays(rand(1, 90)),
            ]);

            // Create order
            $orderValue = rand(300, 1500);
            $order = ProductRequest::create([
                'request_number' => 'REQ-' . date('Ymd') . '-' . str_pad($episodeCount, 4, '0', STR_PAD_LEFT),
                'provider_id' => $provider->id,
                'patient_fhir_id' => 'patient-' . $patientDisplayId,
                'patient_display_id' => $patientDisplayId,
                'facility_id' => Facility::first()?->id ?? 1,
                'payer_name_submitted' => collect(['Medicare Part B', 'Blue Cross Blue Shield', 'Aetna', 'UnitedHealth'])->random(),
                'payer_id' => 'INS' . rand(100, 999),
                'expected_service_date' => Carbon::now()->addDays(rand(1, 30)),
                'wound_type' => collect(['DFU', 'VLU', 'PU', 'SSI'])->random(),
                'clinical_summary' => json_encode([
                    'patient' => [
                        'firstName' => $patientName[0],
                        'lastName' => $patientName[1],
                        'dateOfBirth' => Carbon::now()->subYears(rand(35, 85))->format('Y-m-d'),
                        'gender' => collect(['male', 'female'])->random(),
                    ],
                    'woundDetails' => [
                        'type' => collect(['diabetic_foot_ulcer', 'venous_leg_ulcer', 'pressure_ulcer'])->random(),
                        'location' => collect(['left_foot', 'right_foot', 'lower_leg', 'heel'])->random(),
                        'duration' => collect(['2_weeks', '4_weeks', '6_weeks', '8_weeks'])->random(),
                        'size' => collect(['small', 'medium', 'large'])->random(),
                    ],
                ]),
                'mac_validation_status' => 'valid',
                'eligibility_status' => 'eligible',
                'pre_auth_required_determination' => collect(['not_required', 'required', 'pending'])->random(),
                'order_status' => collect(['approved', 'delivered', 'completed'])->random(),
                'step' => 8,
                'submitted_at' => Carbon::now()->subDays(rand(1, 180)),
                'total_order_value' => $orderValue,
                'friendly_patient_id' => $patientDisplayId,
                'ivr_episode_id' => $episode->id,
                'payment_status' => collect(['paid', 'pending', 'processing'])->random(1, ['paid' => 70, 'pending' => 20, 'processing' => 10])[0],
                'payment_date' => Carbon::now()->subDays(rand(1, 60)),
            ]);

            // Attach product to order
            $sizes = ['5x5cm', '10x10cm', '15x15cm'];
            $selectedSizes = collect($sizes)->random(rand(1, 2));

            foreach ($selectedSizes as $size) {
                $quantity = rand(1, 3);
                $unitPrice = $product->national_asp ?? 150.00;
                $totalPrice = $unitPrice * $quantity;

                $order->products()->attach($product->id, [
                    'quantity' => $quantity,
                    'size' => $size,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
            }

            $orders[] = $order;
            $this->command->info("Created episode {$episodeCount}: {$order->request_number} - {$patientDisplayId}");
        }

        return $orders;
    }

    private function createCommissionRecords($orders, $salesReps): void
    {
        $this->command->info('Creating commission records...');

        foreach ($orders as $order) {
            // Get the provider's assigned rep
            $provider = $order->provider;
            $repId = $provider->acquired_by_rep_id;
            $subRepId = $provider->acquired_by_subrep_id;

            if (!$repId) continue;

            $baseCommissionAmount = $order->total_order_value * 0.05; // 5% base rate

            // Create commission for main rep
            $mainCommission = CommissionRecord::create([
                'order_id' => $order->id,
                'rep_id' => $repId,
                'parent_rep_id' => null,
                'amount' => $subRepId ? $baseCommissionAmount * 0.6 : $baseCommissionAmount, // 60% if split with sub-rep
                'percentage_rate' => $subRepId ? 3.0 : 5.0,
                'status' => $order->payment_status === 'paid' ? collect(['paid', 'approved'])->random() : 'pending',
                'calculation_date' => $order->submitted_at,
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'first_application_date' => $order->expected_service_date,
                'tissue_ids' => json_encode(['TISSUE-' . rand(1000, 9999), 'TISSUE-' . rand(1000, 9999)]),
                'payment_delay_days' => $order->payment_status === 'paid' ? rand(15, 45) : Carbon::now()->diffInDays($order->submitted_at),
                'payment_date' => $order->payment_status === 'paid' ? $order->payment_date : null,
                'friendly_patient_id' => $order->friendly_patient_id,
                'created_at' => $order->submitted_at,
                'updated_at' => Carbon::now(),
            ]);

            // Create commission for sub-rep if applicable
            if ($subRepId) {
                CommissionRecord::create([
                    'order_id' => $order->id,
                    'rep_id' => $subRepId,
                    'parent_rep_id' => $repId,
                    'amount' => $baseCommissionAmount * 0.4, // 40% for sub-rep
                    'percentage_rate' => 2.0,
                    'status' => $order->payment_status === 'paid' ? collect(['paid', 'approved'])->random() : 'pending',
                    'calculation_date' => $order->submitted_at,
                    'invoice_number' => 'INV-' . date('Y') . '-' . str_pad($order->id, 6, '0', STR_PAD_LEFT) . '-SUB',
                    'first_application_date' => $order->expected_service_date,
                    'tissue_ids' => json_encode(['TISSUE-' . rand(1000, 9999)]),
                    'payment_delay_days' => $order->payment_status === 'paid' ? rand(15, 45) : Carbon::now()->diffInDays($order->submitted_at),
                    'payment_date' => $order->payment_status === 'paid' ? $order->payment_date : null,
                    'friendly_patient_id' => $order->friendly_patient_id,
                    'created_at' => $order->submitted_at,
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        $this->command->info('Created commission records for all orders');
    }
}
