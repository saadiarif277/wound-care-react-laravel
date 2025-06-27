<?php

namespace App\Console\Commands;

use App\Services\EnhancedDocuSealIVRService;
use App\Models\Order\ProductRequest;
use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEnhancedDocuSealCommand extends Command
{
    protected $signature = 'docuseal:test-enhanced {--product-request-id=} {--episode-id=}';
    protected $description = 'Test Enhanced DocuSeal IVR Service with FHIR integration';

    public function __construct(
        protected EnhancedDocuSealIVRService $enhancedDocuSealService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Testing Enhanced DocuSeal IVR Service');

        try {
            // Get test data
            $productRequestId = $this->option('product-request-id');
            $episodeId = $this->option('episode-id');

            if (!$productRequestId) {
                // Find a recent product request
                $productRequest = ProductRequest::latest()->first();
                if (!$productRequest) {
                    $this->error('❌ No ProductRequest found. Please create one first.');
                    return 1;
                }
            } else {
                $productRequest = ProductRequest::find($productRequestId);
                if (!$productRequest) {
                    $this->error("❌ ProductRequest {$productRequestId} not found.");
                    return 1;
                }
            }

            $episode = null;
            if ($episodeId) {
                $episode = Episode::find($episodeId);
                if (!$episode) {
                    $this->warn("⚠️ Episode {$episodeId} not found. Continuing without episode.");
                }
            }

            $this->info("📋 Testing with ProductRequest ID: {$productRequest->id}");
            if ($episode) {
                $this->info("📋 Testing with Episode ID: {$episode->id}");
            }

            // Prepare test form data
            $testFormData = [
                'patient_first_name' => 'John',
                'patient_last_name' => 'Doe',
                'patient_dob' => '1980-01-15',
                'patient_gender' => 'male',
                'patient_phone' => '(555) 123-4567',
                'patient_email' => 'john.doe@example.com',
                'provider_name' => 'Dr. Test Provider',
                'provider_npi' => '1234567890',
                'facility_name' => 'Test Clinic',
                'primary_insurance_name' => 'Test Insurance Co.',
                'primary_member_id' => 'ABC123456789',
                'wound_type' => 'Diabetic Foot Ulcer',
                'wound_location' => 'Left Foot',
                'wound_size_cm2' => '3.5',
                'place_of_service' => '11',
                'expected_service_date' => now()->addDays(7)->format('Y-m-d'),
            ];

            $this->info('📤 Creating Enhanced DocuSeal submission...');

            // Create enhanced submission
            $result = $this->enhancedDocuSealService->createEnhancedIVRSubmission(
                $productRequest,
                $testFormData,
                $episode
            );

            if ($result['success']) {
                $this->info('✅ Enhanced DocuSeal submission created successfully!');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Submission ID', $result['submission_id']],
                        ['Slug', $result['slug'] ?? 'N/A'],
                        ['Template ID', $result['template_id']],
                        ['Fields Mapped', $result['fields_mapped']],
                        ['FHIR Data Used', $result['fhir_data_used']],
                        ['Embed URL', $result['embed_url'] ?? 'N/A'],
                    ]
                );

                // Update the product request with the submission ID for testing
                $productRequest->update([
                    'docuseal_submission_id' => $result['submission_id'],
                    'order_status' => 'ivr_sent',
                ]);

                $this->info('📝 ProductRequest updated with submission ID');

                // Test the order summary URL
                $summaryUrl = route('quick-requests.order-summary', $productRequest->id);
                $this->info("🔗 Order summary URL: {$summaryUrl}");

            } else {
                $this->error('❌ Enhanced DocuSeal submission failed:');
                $this->error($result['error']);
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Test failed with exception:');
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
