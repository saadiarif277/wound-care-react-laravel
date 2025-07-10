<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;
use App\Services\Medical\OptimizedMedicalAiService;
use Illuminate\Support\Facades\Log;
use Exception;

class TestDynamicTemplateDiscovery extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:dynamic-template {templateId?} {--manufacturer=} {--detailed} {--no-cache} {--ensemble : Test ML ensemble integration}';

    /**
     * The console command description.
     */
    protected $description = 'Test the dynamic template field discovery system with DocuSeal API and ML ensemble integration';

    protected DocuSealTemplateDiscoveryService $templateDiscovery;
    protected OptimizedMedicalAiService $aiService;

    public function __construct(
        DocuSealTemplateDiscoveryService $templateDiscovery,
        OptimizedMedicalAiService $aiService
    ) {
        parent::__construct();
        $this->templateDiscovery = $templateDiscovery;
        $this->aiService = $aiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing Dynamic Template Discovery System');
        $this->line('');

        // Test 1: API Connectivity
        $this->testApiConnectivity();

        // Test 2: Template Discovery (if template ID provided)
        $templateId = $this->argument('templateId');
        if ($templateId) {
            $this->testTemplateDiscovery($templateId);
            
            // Test 3: AI Integration (with optional ensemble)
            $manufacturer = $this->option('manufacturer') ?? 'Test Manufacturer';
            if ($this->option('ensemble')) {
                $this->testEnsembleIntegration($templateId, $manufacturer);
            } else {
                $this->testAiIntegration($templateId, $manufacturer);
            }
        } else {
            $this->warn('No template ID provided. Skipping template-specific tests.');
            $this->line('Usage: php artisan test:dynamic-template <templateId> --manufacturer="Manufacturer Name" --detailed --ensemble');
        }

        // Test 4: Cache functionality
        if (!$this->option('no-cache')) {
            $this->testCaching($templateId);
        }

        // Test 5: Service Health
        $this->testServiceHealth();

        // Test 6: ML Ensemble Health (if ensemble option)
        if ($this->option('ensemble')) {
            $this->testEnsembleHealth();
        }

        $this->line('');
        $this->info('✅ Dynamic Template Discovery testing completed!');
    }

    /**
     * Test DocuSeal API connectivity
     */
    protected function testApiConnectivity(): void
    {
        $this->info('🌐 Testing DocuSeal API Connectivity...');
        
        try {
            $result = $this->templateDiscovery->testConnection();
            
            if ($result['connected']) {
                $this->info("✅ API Connected: {$result['api_url']}");
                $this->info("📊 Templates available: {$result['templates_count']}");
            } else {
                $this->error("❌ API Connection failed: {$result['error']}");
            }
        } catch (Exception $e) {
            $this->error("❌ API Connection error: {$e->getMessage()}");
        }
        
        $this->line('');
    }

    /**
     * Test template field discovery
     */
    protected function testTemplateDiscovery(string $templateId): void
    {
        $this->info("📋 Testing Template Discovery for ID: {$templateId}");
        
        try {
            // Test direct API call
            $templateFields = $this->templateDiscovery->getTemplateFields($templateId);
            
            $this->info("✅ Template discovered: {$templateFields['name']}");
            $this->info("📝 Total fields: {$templateFields['total_fields']}");
            
                         if ($this->option('detailed')) {
                 $this->line('');
                 $this->info('🔍 Field Details:');
                 $this->table(
                     ['Field Name', 'Type', 'Required'],
                     collect($templateFields['fields'])->map(function ($field) {
                         return [
                             $field['name'],
                             $field['type'],
                             $field['required'] ? 'Yes' : 'No'
                         ];
                     })->toArray()
                 );
             } else {
                $this->info("📋 Field names: " . implode(', ', array_slice($templateFields['field_names'], 0, 10)));
                if (count($templateFields['field_names']) > 10) {
                    $this->info("... and " . (count($templateFields['field_names']) - 10) . " more");
                }
            }

            // Test validation
            $isValid = $this->templateDiscovery->validateTemplateStructure($templateFields);
            if ($isValid) {
                $this->info('✅ Template structure validation passed');
            } else {
                $this->warn('⚠️ Template structure validation failed');
            }

        } catch (Exception $e) {
            $this->error("❌ Template discovery failed: {$e->getMessage()}");
        }
        
        $this->line('');
    }

    /**
     * Test AI integration with dynamic templates
     */
    protected function testAiIntegration(string $templateId, string $manufacturer): void
    {
        $this->info("🤖 Testing AI Integration with Dynamic Templates...");
        
        try {
            // Create comprehensive sample FHIR data that matches typical template fields
            $sampleFhirData = [
                'resourceType' => 'Patient',
                'id' => 'test-patient-123',
                'name' => [
                    [
                        'given' => ['John', 'Michael'],
                        'family' => 'Doe'
                    ]
                ],
                'birthDate' => '1980-01-01',
                'gender' => 'male',
                'telecom' => [
                    [
                        'system' => 'phone',
                        'value' => '(555) 123-4567'
                    ],
                    [
                        'system' => 'email',
                        'value' => 'john.doe@email.com'
                    ]
                ],
                'address' => [
                    [
                        'line' => ['123 Main Street'],
                        'city' => 'Los Angeles',
                        'state' => 'CA',
                        'postalCode' => '90210'
                    ]
                ],
                'identifier' => [
                    [
                        'type' => ['coding' => [['code' => 'MB']]],
                        'system' => 'http://example.com/insurance',
                        'value' => 'INS123456789'
                    ]
                ]
            ];

            $additionalData = [
                // Wound/Clinical Information
                'wound_type' => 'diabetic_ulcer',
                'wound_location' => 'left foot dorsal',
                'wound_size_length' => '3.5',
                'wound_size_width' => '2.1', 
                'wound_total_size' => '7.35',
                'procedure_date' => '2024-01-15',
                'surgery_date' => '2024-01-10',
                
                // Provider Information
                'provider_name' => 'Dr. Sarah Johnson',
                'provider_npi' => '1234567890',
                'provider_ptan' => 'PTAN123456',
                'practice_name' => 'Advanced Wound Care Center',
                'practice_npi' => '9876543210',
                'practice_ptan' => 'PTAN987654',
                'tax_id' => '12-3456789',
                'office_contact_name' => 'Jennifer Smith',
                'office_contact_email' => 'jennifer@advancedwound.com',
                
                // Insurance Information
                'primary_insurance' => 'Medicare',
                'primary_member_id' => 'MEDICARE123456A',
                'secondary_insurance' => 'Blue Cross Blue Shield',
                'secondary_member_id' => 'BCBS987654321',
                
                // Medical Codes
                'icd10_1' => 'L97.429',
                'icd10_2' => 'E11.621', 
                'icd10_3' => 'Z87.891',
                'icd10_4' => 'I70.25',
                'cpt_1' => '97597',
                'cpt_2' => '97598',
                'cpt_3' => '11042',
                'cpt_4' => '15271',
                'hcpcs_1' => 'Q4151',
                'hcpcs_2' => 'Q4186',
                'hcpcs_3' => 'A6196',
                'hcpcs_4' => 'A6197',
                
                // Place of Service & Clinical Details
                'place_of_service' => 'Office: POS-11',
                'nursing_home' => 'No',
                'over_100_days' => 'No',
                'post_op_period' => 'Yes',
                'surgery_cpt_codes' => '11042, 15271',
                'size_graft_requested' => '4cm x 3cm',
                
                // Distributor/Company Info
                'distributor_company' => 'MSC Wound Care'
            ];

            // Test the AI service
            $this->info("🔄 Calling AI service for field mapping...");
            $result = $this->aiService->enhanceWithDynamicTemplate(
                $sampleFhirData,
                $templateId,
                $manufacturer,
                $additionalData
            );

            if (!empty($result)) {
                $this->info("✅ AI mapping successful");
                $this->info("📊 Mapped fields: " . count($result));
                
                if ($this->option('detailed')) {
                    $this->line('');
                    $this->info('🗺️ Mapped Data:');
                    foreach ($result as $field => $value) {
                        $this->line("  {$field}: " . (is_array($value) ? json_encode($value) : $value));
                    }
                }
            } else {
                $this->warn('⚠️ AI mapping returned empty result');
            }

        } catch (Exception $e) {
            $this->error("❌ AI integration test failed: {$e->getMessage()}");
            
                         if ($this->option('detailed')) {
                 $this->line($e->getTraceAsString());
             }
        }
        
        $this->line('');
    }

    /**
     * Test ML ensemble integration with dynamic templates
     */
    protected function testEnsembleIntegration(string $templateId, string $manufacturer): void
    {
        $this->info("🤖🎯 Testing ML Ensemble Integration with Dynamic Templates...");
        
        try {
            // Create comprehensive sample FHIR data that matches typical template fields
            $sampleFhirData = [
                'resourceType' => 'Patient',
                'id' => 'test-patient-123',
                'name' => [
                    [
                        'given' => ['John', 'Michael'],
                        'family' => 'Doe'
                    ]
                ],
                'birthDate' => '1980-01-01',
                'gender' => 'male',
                'telecom' => [
                    [
                        'system' => 'phone',
                        'value' => '(555) 123-4567'
                    ],
                    [
                        'system' => 'email',
                        'value' => 'john.doe@email.com'
                    ]
                ],
                'address' => [
                    [
                        'line' => ['123 Main Street'],
                        'city' => 'Los Angeles',
                        'state' => 'CA',
                        'postalCode' => '90210'
                    ]
                ]
            ];

            $additionalData = [
                // Provider Information
                'provider_name' => 'Dr. Sarah Johnson',
                'provider_npi' => '1234567890',
                'practice_name' => 'Advanced Wound Care Center',
                'practice_npi' => '9876543210',
                'office_contact_name' => 'Jennifer Smith',
                'office_contact_email' => 'jennifer@advancedwound.com',
                
                // Insurance Information
                'primary_insurance' => 'Medicare',
                'primary_member_id' => 'MEDICARE123456A',
                'secondary_insurance' => 'Blue Cross Blue Shield',
                'secondary_member_id' => 'BCBS987654321',
                
                // Clinical Information
                'wound_type' => 'diabetic_ulcer',
                'wound_location' => 'left foot dorsal',
                'wound_size_length' => '3.5',
                'wound_size_width' => '2.1',
                'procedure_date' => '2024-01-15',
                
                // Medical Codes
                'icd10_1' => 'L97.429',
                'cpt_1' => '97597',
                'hcpcs_1' => 'Q4151',
            ];

            // Test ensemble-enhanced mapping
            $this->info("🔄 Calling ensemble-enhanced AI service...");
            $result = $this->aiService->enhanceWithDynamicTemplateAndEnsemble(
                $sampleFhirData,
                $templateId,
                $manufacturer,
                $additionalData,
                1 // Test user ID
            );

            if (!empty($result)) {
                $this->info("✅ Ensemble AI mapping successful");
                $this->info("📊 Mapped fields: " . count($result));
                $this->info("🎯 Base confidence: " . ($result['_ai_confidence'] ?? 'N/A'));
                $this->info("🔬 Ensemble method: " . ($result['_ai_method'] ?? 'N/A'));
                $this->info("⚡ Processing time: " . ($result['_processing_time_ms'] ?? 'N/A') . 'ms');
                
                if ($this->option('detailed')) {
                    $this->line('');
                    $this->info('🗺️ Ensemble-Enhanced Mapping Data:');
                    foreach ($result as $field => $value) {
                        if (!str_starts_with($field, '_')) {
                            $this->line("  {$field}: " . (is_array($value) ? json_encode($value) : $value));
                        }
                    }
                    
                    // Show ensemble metadata
                    $this->line('');
                    $this->info('🎯 Ensemble Metadata:');
                    foreach ($result as $field => $value) {
                        if (str_starts_with($field, '_')) {
                            $this->line("  {$field}: " . (is_array($value) ? json_encode($value) : $value));
                        }
                    }
                }
            } else {
                $this->warn('⚠️ Ensemble AI mapping returned empty result');
            }

            // Test ML recommendations
            $this->line('');
            $this->info("📋 Testing ML-powered recommendations...");
            $recommendations = $this->aiService->getMappingRecommendations($templateId, 1);
            
            $this->info("✅ Recommendations retrieved");
            $this->info("📊 Field suggestions: " . count($recommendations['field_suggestions']));
            $this->info("🔀 Workflow suggestions: " . count($recommendations['workflow_suggestions']));
            $this->info("🎨 Personalization hints: " . count($recommendations['personalization_hints']));
            $this->info("📈 Confidence: " . ($recommendations['confidence'] ?? 'N/A'));
            
            if ($this->option('detailed') && !empty($recommendations['field_suggestions'])) {
                $this->line('');
                $this->info('📝 Field Suggestions:');
                foreach ($recommendations['field_suggestions'] as $suggestion) {
                    $this->line("  - " . json_encode($suggestion));
                }
            }

        } catch (Exception $e) {
            $this->error("❌ Ensemble integration test failed: {$e->getMessage()}");
        }
        
        $this->line('');
    }

    /**
     * Test ML ensemble system health
     */
    protected function testEnsembleHealth(): void
    {
        $this->info('🧠 Testing ML Ensemble System Health...');
        
        try {
            // Test ContinuousLearningService availability
            $learningService = app(\App\Services\Learning\ContinuousLearningService::class);
            $this->info('✅ ContinuousLearningService: Available');
            
            // Test BehavioralTrackingService availability  
            $trackingService = app(\App\Services\Learning\BehavioralTrackingService::class);
            $this->info('✅ BehavioralTrackingService: Available');
            
            // Test MLDataPipelineService availability
            $pipelineService = app(\App\Services\Learning\MLDataPipelineService::class);
            $this->info('✅ MLDataPipelineService: Available');
            
            // Test model performance analysis
            $performanceAnalysis = $learningService->analyzeModelPerformance();
            $this->info('📊 Model Performance Analysis:');
            
            if (!empty($performanceAnalysis)) {
                foreach ($performanceAnalysis as $modelType => $analysis) {
                    $this->line("  🔬 {$modelType}:");
                    $this->line("    - Accuracy: " . ($analysis['accuracy'] ?? 'N/A'));
                    $this->line("    - Predictions: " . ($analysis['total_predictions'] ?? 'N/A'));
                    $this->line("    - Confidence: " . ($analysis['average_confidence'] ?? 'N/A'));
                }
            } else {
                $this->warn('⚠️ No ML models found - system may need training');
            }
            
        } catch (Exception $e) {
            $this->error("❌ ML Ensemble system health check failed: {$e->getMessage()}");
        }
        
        $this->line('');
    }

    /**
     * Test caching functionality
     */
    protected function testCaching(?string $templateId): void
    {
        $this->info('💾 Testing Cache Functionality...');
        
        if (!$templateId) {
            $this->warn('⚠️ No template ID provided, skipping cache test');
            return;
        }

        try {
            // First call (should hit API)
            $start = microtime(true);
            $result1 = $this->templateDiscovery->getCachedTemplateStructure($templateId);
            $duration1 = microtime(true) - $start;

            // Second call (should hit cache)
            $start = microtime(true);
            $result2 = $this->templateDiscovery->getCachedTemplateStructure($templateId);
            $duration2 = microtime(true) - $start;

            $this->info("⏱️ First call (API): " . round($duration1 * 1000, 2) . "ms");
            $this->info("⏱️ Second call (Cache): " . round($duration2 * 1000, 2) . "ms");

            if ($duration2 < $duration1 * 0.5) {
                $this->info('✅ Cache is working (second call significantly faster)');
            } else {
                $this->warn('⚠️ Cache may not be working properly');
            }

            // Test cache clear
            $cleared = $this->templateDiscovery->clearTemplateCache($templateId);
            if ($cleared) {
                $this->info('✅ Cache clear successful');
            } else {
                $this->warn('⚠️ Cache clear failed');
            }

        } catch (Exception $e) {
            $this->error("❌ Cache test failed: {$e->getMessage()}");
        }
        
        $this->line('');
    }

    /**
     * Test service health
     */
    protected function testServiceHealth(): void
    {
        $this->info('🏥 Testing Service Health...');
        
        try {
            // Test AI service health
            $aiHealth = $this->aiService->healthCheck();
            
            if ($aiHealth['healthy']) {
                $this->info('✅ AI Service: Healthy');
                $this->info("🔧 Azure configured: " . ($aiHealth['azure_configured'] ? 'Yes' : 'No'));
                $this->info("📚 Knowledge base: " . ($aiHealth['knowledge_base_loaded'] ? 'Loaded' : 'Not loaded'));
            } else {
                $this->warn("⚠️ AI Service: Unhealthy - {$aiHealth['error']}");
            }

            // Test overall status
            $status = $this->aiService->getStatus();
            $this->info("⚙️ AI Service enabled: " . ($status['enabled'] ? 'Yes' : 'No'));
            $this->info("🌐 Service URL: {$status['service_url']}");

        } catch (Exception $e) {
            $this->error("❌ Service health check failed: {$e->getMessage()}");
        }
        
        $this->line('');
    }
} 