<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PatientIVRStatus;
use App\Models\Order\ProductRequest;
use App\Models\Order\Manufacturer;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Support\Str;

class CreateTestEpisode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-episode {--status=ready_for_review}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test episode with orders for testing the episode workflow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating test episode data...');

        // Create or get a manufacturer
        $manufacturer = Manufacturer::firstOrCreate([
            'name' => 'Legacy Medical Consultants'
        ], [
            'contact_email' => 'orders@legacymedical.com',
            'contact_phone' => '555-123-4567',
            'api_endpoint' => null,
            'submission_method' => 'email'
        ]);

        // Create or get a provider (user)
        $provider = User::where('email', 'test.provider@example.com')->first();
        if (!$provider) {
            $provider = User::create([
                'first_name' => 'Dr. Test',
                'last_name' => 'Provider',
                'email' => 'test.provider@example.com',
                'password' => bcrypt('password'),
                'npi_number' => '1234567890',
                'account_id' => 1, // Default account
                'owner' => false
            ]);
        }

        // Create or get a facility
        $facility = Facility::firstOrCreate([
            'name' => 'Test Medical Center'
        ], [
            'facility_type' => 'clinic',
            'address' => '123 Medical Drive',
            'city' => 'Healthcare City',
            'state' => 'TX',
            'zip_code' => '12345',
            'phone' => '555-987-6543',
            'organization_id' => 1, // Default organization
            'active' => true
        ]);

        // Create the episode
        $episode = PatientIVRStatus::create([
            'id' => Str::uuid(),
            'patient_id' => 'Provider/' . $provider->id, // Using provider ID as patient ID for testing
            'manufacturer_id' => $manufacturer->id,
            'status' => $this->option('status'),
            'ivr_status' => $this->option('status') === 'ready_for_review' ? 'pending' : 'verified',
            'verification_date' => $this->option('status') !== 'ready_for_review' ? now() : null,
            'expiration_date' => now()->addMonths(3),
        ]);

        // Create test product requests for the episode
        $baseRequestNumber = 'REQ-' . date('Y') . '-' . substr($episode->id, 0, 8);
        for ($i = 1; $i <= 2; $i++) {
            ProductRequest::create([
                'request_number' => $baseRequestNumber . '-' . $i,
                'provider_id' => $provider->id,
                'facility_id' => $facility->id,
                'patient_fhir_id' => $episode->patient_id,
                'patient_display_id' => 'PAT-' . $i,
                'ivr_episode_id' => $episode->id,
                'order_status' => $this->option('status') === 'ready_for_review' ? 'ivr_confirmed' : 'approved',
                'total_order_value' => rand(100, 500),
                'expected_service_date' => now()->addDays(7),
                'submitted_at' => now(),
                'step' => 4,
                'payer_name_submitted' => 'Medicare',
                'wound_type' => 'VLU',
                'place_of_service' => '11',
                'medicare_part_b_authorized' => true,
            ]);
        }

        $this->info("✅ Test episode created successfully!");
        $this->info("📋 Episode ID: {$episode->id}");
        $this->info("🏥 Manufacturer: {$manufacturer->name}");
        $this->info("👨‍⚕️ Provider: {$provider->first_name} {$provider->last_name}");
        $this->info("🏢 Facility: {$facility->name}");
        $this->info("📊 Status: {$episode->status}");
        $this->info("📄 IVR Status: {$episode->ivr_status}");
        $this->info("📦 Product Requests: 2 test product requests created");
        $this->line('');
        $this->info("🌐 View at: /admin/episodes/{$episode->id}");

        return 0;
    }
}
