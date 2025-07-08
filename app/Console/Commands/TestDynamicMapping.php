<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSeal\DynamicFieldMappingService;
use App\Services\UnifiedFieldMappingService;

class TestDynamicMapping extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docuseal:test-dynamic-mapping
                          {--template-id= : DocuSeal template ID to test}
                          {--manufacturer= : Manufacturer name to test}
                          {--episode-id= : Episode ID to test (optional)}
                          {--test-connection : Test AI service and DocuSeal connectivity}
                          {--test-template= : Test template field discovery}';

    /**
     * The console command description.
     */
    protected $description = 'Test the dynamic DocuSeal field mapping system';

    public function handle()
    {
        $this->info('🚀 Testing Dynamic DocuSeal Field Mapping System');
        $this->newLine();

        // Test connectivity if requested
        if ($this->option('test-connection')) {
            $this->testConnectivity();
            return;
        }

        // Test template field discovery if requested
        if ($templateId = $this->option('test-template')) {
            $this->testTemplateFields($templateId);
            return;
        }

        // Test full mapping workflow
        $templateId = $this->option('template-id');
        $manufacturerName = $this->option('manufacturer');
        $episodeId = $this->option('episode-id');

        if (!$templateId || !$manufacturerName) {
            $this->error('❌ Template ID and manufacturer name are required for mapping test');
            $this->info('Usage examples:');
            $this->line('  php artisan docuseal:test-dynamic-mapping --test-connection');
            $this->line('  php artisan docuseal:test-dynamic-mapping --test-template=1233913');
            $this->line('  php artisan docuseal:test-dynamic-mapping --template-id=1233913 --manufacturer="MEDLIFE SOLUTIONS"');
            return 1;
        }

        $this->testMapping($templateId, $manufacturerName, $episodeId);
    }

    private function testConnectivity()
    {
        $this->info('🔍 Testing System Connectivity...');
        
        $dynamicService = app(DynamicFieldMappingService::class);

        // Test AI Service
        $this->info('Testing AI Service connection...');
        $aiResult = $dynamicService->testAIServiceConnection();
        
        if ($aiResult['success']) {
            $this->info("✅ AI Service: {$aiResult['message']}");
            $this->line("   Status: {$aiResult['service_status']}");
            $this->line("   Azure AI Available: " . ($aiResult['azure_ai_available'] ? 'Yes' : 'No'));
        } else {
            $this->error("❌ AI Service: {$aiResult['message']}");
            $this->line("   Error: {$aiResult['error']}");
        }

        $this->newLine();

        // Test DocuSeal Connection
        $this->info('Testing DocuSeal connection...');
        $docuSealResult = $dynamicService->testDocuSealConnection();
        
        if ($docuSealResult['success']) {
            $this->info("✅ DocuSeal: {$docuSealResult['message']}");
            if (isset($docuSealResult['templates_accessible'])) {
                $this->line("   Templates Accessible: " . ($docuSealResult['templates_accessible'] ? 'Yes' : 'No'));
            }
        } else {
            $this->error("❌ DocuSeal: {$docuSealResult['message']}");
            if (isset($docuSealResult['error'])) {
                $this->line("   Error: {$docuSealResult['error']}");
            }
        }

        $this->newLine();
        $this->info('💡 Connectivity test completed!');
    }

    private function testTemplateFields(string $templateId)
    {
        $this->info("🔍 Testing Template Field Discovery for Template ID: {$templateId}");
        
        try {
            $dynamicService = app(DynamicFieldMappingService::class);
            $templateInfo = $dynamicService->getTemplateFields($templateId);

            $this->info("✅ Template fields retrieved successfully!");
            $this->newLine();
            
            $this->line("Template Name: {$templateInfo['template_name']}");
            $this->line("Template ID: {$templateInfo['template_id']}");
            $this->line("Total Fields: {$templateInfo['total_fields']}");
            
            $this->newLine();
            $this->info('📋 Available Fields:');
            
            foreach ($templateInfo['field_names'] as $index => $fieldName) {
                $fieldDetails = $templateInfo['field_details'][$fieldName] ?? [];
                $type = $fieldDetails['type'] ?? 'text';
                $required = $fieldDetails['required'] ? ' (required)' : ' (optional)';
                
                $this->line("  " . ($index + 1) . ". {$fieldName} [{$type}]{$required}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Template field discovery failed: {$e->getMessage()}");
        }
    }

    private function testMapping(string $templateId, string $manufacturerName, ?string $episodeId = null)
    {
        $this->info("🧠 Testing Dynamic Field Mapping");
        $this->line("Template ID: {$templateId}");
        $this->line("Manufacturer: {$manufacturerName}");
        $this->line("Episode ID: " . ($episodeId ?: 'None (using sample data)'));
        $this->newLine();

        try {
            // Prepare test data
            $testData = $this->getTestData($manufacturerName);
            
            $dynamicService = app(DynamicFieldMappingService::class);
            
            $this->info('🚀 Calling dynamic mapping service...');
            $result = $dynamicService->mapEpisodeToDocuSealForm(
                $episodeId,
                $manufacturerName,
                $templateId,
                $testData
            );

            if ($result['success']) {
                $this->info("✅ Dynamic mapping completed successfully!");
                $this->displayMappingResults($result);
            } else {
                $this->error("❌ Dynamic mapping failed: " . ($result['error'] ?? 'Unknown error'));
                if (isset($result['metadata']['fallback_used']) && $result['metadata']['fallback_used']) {
                    $this->warn("⚠️  Fallback was attempted");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Mapping test failed: {$e->getMessage()}");
            
            // Try static mapping as comparison
            $this->warn("🔄 Attempting static mapping for comparison...");
            try {
                $unifiedService = app(UnifiedFieldMappingService::class);
                $staticResult = $unifiedService->mapEpisodeToTemplate($episodeId, $manufacturerName, $testData, 'IVR');
                
                $this->info("✅ Static mapping completed as fallback");
                $this->line("Completeness: {$staticResult['completeness']['percentage']}%");
                $this->line("Mapped fields: " . count($staticResult['data']));
                
            } catch (\Exception $staticE) {
                $this->error("❌ Static mapping also failed: {$staticE->getMessage()}");
            }
        }
    }

    private function displayMappingResults(array $result)
    {
        $this->newLine();
        $this->info('📊 Mapping Results:');
        
        // Template Info
        if (isset($result['template_info'])) {
            $templateInfo = $result['template_info'];
            $this->line("Template: {$templateInfo['template_name']} (ID: {$templateInfo['template_id']})");
            $this->line("Total template fields: {$templateInfo['total_fields']}");
        }

        // Validation Info
        if (isset($result['validation'])) {
            $validation = $result['validation'];
            $this->line("Quality grade: {$validation['quality_grade']}");
            
            if (isset($validation['confidence_scores'])) {
                $avgConfidence = array_sum($validation['confidence_scores']) / count($validation['confidence_scores']);
                $this->line("Average confidence: " . round($avgConfidence * 100, 1) . "%");
            }
        }

        // Mapped Fields
        $mappedData = $result['data'] ?? [];
        $this->line("Mapped fields: " . count($mappedData));

        if (!empty($mappedData)) {
            $this->newLine();
            $this->info('🗂️  Sample Mapped Fields:');
            $count = 0;
            foreach ($mappedData as $field => $value) {
                if ($count >= 10) { // Limit display
                    $remaining = count($mappedData) - $count;
                    $this->line("   ... and {$remaining} more fields");
                    break;
                }
                $displayValue = is_string($value) ? substr($value, 0, 50) : (string) $value;
                $this->line("   {$field}: {$displayValue}");
                $count++;
            }
        }

        // Submission Info
        if (isset($result['submission_result'])) {
            $submission = $result['submission_result'];
            $this->newLine();
            $this->info('📝 DocuSeal Submission:');
            $this->line("Submission ID: {$submission['submission_id']}");
            $this->line("Status: {$submission['status']}");
            if (isset($submission['form_url'])) {
                $this->line("Form URL: {$submission['form_url']}");
            }
        }

        // Performance
        if (isset($result['metadata']['duration_ms'])) {
            $this->newLine();
            $this->info("⚡ Performance: {$result['metadata']['duration_ms']}ms");
        }

        // Suggestions
        if (!empty($result['validation']['suggestions'])) {
            $this->newLine();
            $this->warn('💡 Suggestions:');
            foreach ($result['validation']['suggestions'] as $suggestion) {
                $this->line("   • {$suggestion}");
            }
        }
    }

    private function getTestData(string $manufacturerName): array
    {
        // Return sample test data based on manufacturer
        return [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1980-01-01',
            'patient_phone' => '(555) 123-4567',
            'patient_address_line1' => '123 Main St',
            'patient_city' => 'Anytown',
            'patient_state' => 'CA',
            'patient_zip' => '90210',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'Test Medical Center',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'BC123456789',
            'wound_location' => 'Left heel',
            'wound_size_length' => '3.5',
            'wound_size_width' => '2.1',
            'primary_diagnosis_code' => 'L97.424',
            'expected_service_date' => now()->addDays(3)->format('Y-m-d')
        ];
    }
} 