<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDocuSealIntegration extends Command
{
    protected $signature = 'docuseal:test-integration 
                            {product_code : Product Q-code to test}
                            {--dry-run : Only simulate the request without making actual API calls}';

    protected $description = 'Test end-to-end DocuSeal integration for a specific product';

    public function handle()
    {
        $productCode = $this->argument('product_code');
        $isDryRun = $this->option('dry-run');

        $this->info("🧪 Testing DocuSeal Integration");
        $this->info("==============================");
        $this->info("Product Code: {$productCode}");
        $this->info("Mode: " . ($isDryRun ? "DRY RUN" : "LIVE TEST"));

        // Step 1: First verify the product configuration
        $this->info("\n📋 Step 1: Verifying Product Configuration");
        $this->call('docuseal:debug', ['--product' => $productCode]);

        if ($isDryRun) {
            $this->info("\n🔄 Step 2: Simulating DocuSeal Submission Request (DRY RUN)");
            $this->simulateDocuSealRequest($productCode);
        } else {
            $this->info("\n🔄 Step 2: Testing Live DocuSeal Submission Generation");
            $this->testLiveDocuSealSubmission($productCode);
        }

        return 0;
    }

    private function simulateDocuSealRequest($productCode)
    {
        // Get product and manufacturer info
        $product = \App\Models\Order\Product::where('q_code', $productCode)->first();
        if (!$product || !$product->manufacturer_id) {
            $this->error("❌ Product not found or no manufacturer assigned");
            return;
        }

        $manufacturer = \App\Models\Order\Manufacturer::find($product->manufacturer_id);
        $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($product->manufacturer_id, 'IVR');

        $this->info("✅ Product: {$product->name}");
        $this->info("✅ Manufacturer: {$manufacturer->name}");
        $this->info("✅ Template: " . ($template ? $template->template_name : 'NONE FOUND'));

        if (!$template) {
            $this->error("❌ No default IVR template found for manufacturer");
            return;
        }

        // Simulate the request payload
        $requestPayload = [
            'user_email' => 'test@mscwoundcare.com',
            'integration_email' => 'provider@example.com',
            'prefill_data' => $this->generateSampleFormData(),
            'manufacturerId' => $product->manufacturer_id,
            'productCode' => $productCode,
            'documentType' => 'IVR'
        ];

        $this->info("\n📤 Simulated Request Payload:");
        $this->table(['Field', 'Value'], [
            ['user_email', $requestPayload['user_email']],
            ['integration_email', $requestPayload['integration_email']],
            ['manufacturerId', $requestPayload['manufacturerId']],
            ['productCode', $requestPayload['productCode']],
            ['documentType', $requestPayload['documentType']],
            ['prefill_data_fields', count($requestPayload['prefill_data'])],
        ]);

        $this->info("\n📝 Sample Prefill Data:");
        foreach (array_slice($requestPayload['prefill_data'], 0, 5) as $key => $value) {
            $this->line("  {$key} = {$value}");
        }

        $this->info("\n✅ DRY RUN: All components are properly configured for DocuSeal integration");
    }

    private function testLiveDocuSealSubmission($productCode)
    {
        $this->warn("⚠️ This will make a real API call to DocuSeal");
        if (!$this->confirm('Do you want to proceed with live testing?')) {
            $this->info("Live test cancelled");
            return;
        }

        try {
            // Get product and manufacturer info
            $product = \App\Models\Order\Product::where('q_code', $productCode)->first();
            if (!$product || !$product->manufacturer_id) {
                $this->error("❌ Product not found or no manufacturer assigned");
                return;
            }

            // Prepare test data
            $requestData = [
                'user_email' => 'test@mscwoundcare.com',
                'integration_email' => 'provider@example.com',
                'prefill_data' => $this->generateSampleFormData(),
                'manufacturerId' => $product->manufacturer_id,
                'productCode' => $productCode,
                'documentType' => 'IVR'
            ];

            $this->info("📤 Testing DocuSeal service directly...");

            // Use the DocuSeal service directly instead of HTTP call
            $docuSealService = app(\App\Services\DocusealService::class);
            
            // Test API connectivity first
            $this->info("🔗 Testing DocuSeal API connectivity...");
            $connectionTest = $docuSealService->testConnection();
            
            if (!$connectionTest['success']) {
                $this->error("❌ DocuSeal API connection failed:");
                $this->error($connectionTest['error'] ?? 'Unknown error');
                if (isset($connectionTest['recommendation'])) {
                    $this->warn("💡 Recommendation: " . $connectionTest['recommendation']);
                }
                return;
            }
            
            $this->info("✅ DocuSeal API connection successful");
            $this->info("   Templates found: " . ($connectionTest['templates_count'] ?? 0));

            // Get template for manufacturer
            $manufacturer = \App\Models\Order\Manufacturer::find($product->manufacturer_id);
            $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($product->manufacturer_id, 'IVR');
            
            if (!$template) {
                $this->error("❌ No DocuSeal template found for manufacturer: {$manufacturer->name}");
                $this->info("💡 Available templates:");
                $allTemplates = \App\Models\Docuseal\DocusealTemplate::with('manufacturer')
                    ->get()
                    ->map(function($t) {
                        return [
                            'ID' => $t->id,
                            'Name' => $t->template_name,
                            'Manufacturer' => $t->manufacturer->name ?? 'Unknown',
                            'DocuSeal ID' => $t->docuseal_template_id
                        ];
                    })
                    ->toArray();
                $this->table(['ID', 'Name', 'Manufacturer', 'DocuSeal ID'], $allTemplates);
                return;
            }

            $this->info("✅ Template found: {$template->template_name}");
            
            // Test field mapping
            $this->info("🗺️ Testing field mapping...");
            $mappedFields = $docuSealService->mapFieldsUsingTemplate($requestData['prefill_data'], $template);
            
            $this->info("📊 Field mapping results:");
            $this->table(['Field', 'Value'], [
                ['Input fields', count($requestData['prefill_data'])],
                ['Mapped fields', count($mappedFields)],
                ['Mapping success rate', count($requestData['prefill_data']) > 0 ? 
                    round((count($mappedFields) / count($requestData['prefill_data'])) * 100, 2) . '%' : '0%']
            ]);

            if (count($mappedFields) === 0) {
                $this->error("❌ No fields were mapped! Check template field mappings.");
                $this->info("💡 Template field mappings available: " . (is_array($template->field_mappings) ? count($template->field_mappings) : 0));
                if ($template->field_mappings) {
                    $this->info("First 5 template mappings:");
                    $mappingPreview = array_slice($template->field_mappings, 0, 5, true);
                    foreach ($mappingPreview as $docusealField => $mapping) {
                        $this->line("  {$docusealField} => " . (is_array($mapping) ? json_encode($mapping) : $mapping));
                    }
                }
                return;
            }

            // Create the actual submission
            $this->info("🚀 Creating DocuSeal submission...");
            
            try {
                $templateRole = 'First Party'; // Default fallback
                
                // Prepare submission data
                $submissionData = [
                    'template_id' => (int) $template->docuseal_template_id,
                    'send_email' => false,
                    'submitters' => [
                        [
                            'email' => $requestData['integration_email'],
                            'role' => $templateRole,
                            'fields' => $mappedFields
                        ]
                    ]
                ];

                $result = $docuSealService->createSubmission($submissionData);

                if ($result && isset($result['submission_id'])) {
                    $this->info("✅ DocuSeal submission created successfully!");
                    
                    $this->table(['Field', 'Value'], [
                        ['Success', 'Yes'],
                        ['Submission ID', $result['submission_id'] ?? 'N/A'],
                        ['Submitter ID', $result['submitter_id'] ?? 'N/A'],
                        ['Status', $result['status'] ?? 'N/A'],
                        ['Template ID', $template->docuseal_template_id],
                        ['Template Name', $template->template_name],
                        ['Manufacturer', $manufacturer->name],
                        ['Fields Mapped', count($mappedFields)],
                        ['Integration Type', 'direct_service'],
                    ]);

                    // Try to get signing URL if available
                    if (isset($result['submitters'][0]['embed_url'])) {
                        $this->info("🔗 Form URL: {$result['submitters'][0]['embed_url']}");
                    } elseif (isset($result['submitters'][0]['sign_url'])) {
                        $this->info("🔗 Form URL: {$result['submitters'][0]['sign_url']}");
                    }

                } else {
                    $this->error("❌ DocuSeal submission creation failed");
                    $this->error("Response: " . json_encode($result));
                }

            } catch (\Exception $e) {
                $this->error("❌ DocuSeal submission creation failed: " . $e->getMessage());
                $this->info("💡 This might be due to API authentication, template configuration, or field mapping issues.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception during live test: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . " Line: " . $e->getLine());
        }
    }

    private function generateSampleFormData(): array
    {
        return [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_email' => 'john.doe@example.com',
            'patient_phone' => '555-0123',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'provider_email' => 'dr.smith@clinic.com',
            'provider_phone' => '555-0456',
            'facility_name' => 'Test Medical Center',
            'facility_address' => '123 Medical Dr, City, ST 12345',
            'wound_type' => 'Diabetic Ulcer',
            'wound_location' => 'Lower Left Leg',
            'wound_size' => '5.2 cm²',
            'service_date' => now()->format('Y-m-d'),
            'diagnosis_code' => 'E11.621',
            'procedure_code' => 'Q4250',
            'units_requested' => '1',
            'medical_necessity' => 'Patient has chronic diabetic ulcer requiring advanced wound care treatment',
            'previous_treatments' => 'Standard wound dressings, topical antimicrobials',
            'treatment_duration' => '6 weeks',
            'expected_outcome' => 'Complete wound closure'
        ];
    }
}
