<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiFormFillerService;
use Illuminate\Support\Facades\Log;

class TestAiFormFiller extends Command
{
    protected $signature = 'ai:test-form-filler 
                           {--service-health : Test AI service health status}
                           {--validate-terms : Test medical terminology validation}
                           {--fill-form : Test intelligent form filling}
                           {--all : Run all tests}';

    protected $description = 'Test AI Form Filler Service functionality';

    public function __construct(
        private AiFormFillerService $aiFormFillerService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🤖 Testing AI Form Filler Service Integration');
        $this->newLine();

        if ($this->option('all')) {
            $this->testServiceHealth();
            $this->testMedicalTermValidation();
            $this->testFormFilling();
        } else {
            if ($this->option('service-health')) {
                $this->testServiceHealth();
            }
            
            if ($this->option('validate-terms')) {
                $this->testMedicalTermValidation();
            }
            
            if ($this->option('fill-form')) {
                $this->testFormFilling();
            }
            
            if (!$this->option('service-health') && !$this->option('validate-terms') && !$this->option('fill-form')) {
                $this->testServiceHealth();
            }
        }

        $this->newLine();
        $this->info('✅ AI Form Filler Service tests completed');
    }

    private function testServiceHealth()
    {
        $this->info('🔍 Testing AI Service Health...');
        
        try {
            $health = $this->aiFormFillerService->getServiceHealth();
            
            if ($health['accessible']) {
                $this->info("  ✅ Service Status: {$health['status']}");
                
                $stats = $this->aiFormFillerService->getTerminologyStats();
                if (isset($stats['total_terms'])) {
                    $this->info("  📊 Medical Terms Available: {$stats['total_terms']}");
                }
                
                if (isset($stats['domains']) && is_array($stats['domains'])) {
                    $this->info("  🏥 Medical Domains: " . implode(', ', $stats['domains']));
                }
            } else {
                $this->error("  ❌ Service not accessible: {$health['error']}");
                $this->warn("  💡 Make sure the Python AI service is running on the configured URL");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Health check failed: {$e->getMessage()}");
        }
    }

    private function testMedicalTermValidation()
    {
        $this->info('🏥 Testing Medical Terminology Validation...');
        
        // Test terms for wound care
        $testTerms = [
            'pressure ulcer',
            'diabetic foot ulcer',
            'venous stasis ulcer',
            'surgical wound',
            'stage 3 pressure injury',
            'wound dehiscence',
            'invalid_medical_term_xyz'
        ];

                 try {
             $result = $this->aiFormFillerService->validateMedicalTerms($testTerms, 'clinical_note');
            
                         if (isset($result['error'])) {
                $this->error("  ❌ Validation failed: " . $result['error']);
            } else {
                $this->info("  ✅ Validation completed successfully");
                $this->info("  📊 Valid terms: {$result['valid_terms']} / {$result['total_terms']}");
                $this->info("  🎯 Overall confidence: " . number_format(($result['overall_confidence'] ?? 0) * 100, 1) . "%");
                
                if (isset($result['processing_method'])) {
                    $this->info("  🔧 Processing method: {$result['processing_method']}");
                }
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Validation test failed: {$e->getMessage()}");
        }
    }

    private function testFormFilling()
    {
        $this->info('📝 Testing Intelligent Form Filling...');
        
        // Simulate OCR data from an insurance card
        $ocrData = [
            'member_name' => 'John Doe',
            'insurance_id' => '123456789',
            'group_num' => 'GRP001',
            'plan_name' => 'Blue Cross Blue Shield',
            'effective_date' => '01/01/2024',
            'primary_care_copay' => '$25',
            'specialist_copay' => '$50'
        ];

        try {
            $result = $this->aiFormFillerService->fillFormFields($ocrData, 'insurance_card');
            
            if ($result['success']) {
                $this->info("  ✅ Form filling completed successfully");
                $this->info("  🎯 Quality grade: {$result['quality_grade']}");
                $this->info("  🤖 AI enhanced: " . ($result['ai_enhanced'] ? 'Yes' : 'No'));
                $this->info("  📊 Fields filled: " . count($result['filled_fields']));
                
                if (!empty($result['suggestions'])) {
                    $this->info("  💡 AI suggestions:");
                    foreach ($result['suggestions'] as $suggestion) {
                        $this->info("    • {$suggestion}");
                    }
                }
            } else {
                $this->error("  ❌ Form filling failed");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Form filling test failed: {$e->getMessage()}");
        }
    }
} 