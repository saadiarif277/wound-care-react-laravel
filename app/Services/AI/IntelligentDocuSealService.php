<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\DocuSealService;
use App\Services\AI\AzureFoundryService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Episode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Intelligent DocuSeal Service that uses Azure AI for dynamic field mapping
 * 
 * This service replaces static field mappings with AI-powered translation
 * between different form formats, handling variations automatically.
 */
final class IntelligentDocuSealService
{
    private DocuSealService $docuSealService;
    private AzureFoundryService $azureAI;

    public function __construct(
        DocuSealService $docuSealService,
        AzureFoundryService $azureAI
    ) {
        $this->docuSealService = $docuSealService;
        $this->azureAI = $azureAI;
    }

    /**
     * Create a DocuSeal submission using AI-powered field mapping
     */
    public function createIntelligentSubmission(
        array $formData,
        string $manufacturerName,
        array $templateFields,
        array $options = []
    ): array {
        Log::info('🤖 Creating intelligent DocuSeal submission', [
            'manufacturer' => $manufacturerName,
            'form_fields' => count($formData),
            'template_fields' => count($templateFields)
        ]);

        try {
            // Step 1: Use Azure AI to map form data to template fields
            $mappingResult = $this->azureAI->translateFormData(
                $formData,
                $templateFields,
                'MSC Wound Care Quick Request Form',
                "DocuSeal template for {$manufacturerName}",
                $options
            );

            if (!$mappingResult['success']) {
                throw new Exception('AI field mapping failed');
            }

            Log::info('✅ AI field mapping completed', [
                'mapped_fields' => count($mappingResult['mappings']),
                'confidence' => $mappingResult['overall_confidence'],
                'warnings' => count($mappingResult['warnings'])
            ]);

            // Step 2: Format mapped data for DocuSeal
            $docuSealData = $this->formatMappedDataForDocuSeal($mappingResult['mappings']);

            // Step 3: Create DocuSeal submission with mapped data
            $submissionResult = $this->docuSealService->createSubmission($docuSealData);

            // Step 4: Store mapping results for future improvements
            $this->storeMappingResults($mappingResult, $manufacturerName);

            return [
                'success' => true,
                'submission' => $submissionResult,
                'ai_mapping' => [
                    'mapped_fields' => count($mappingResult['mappings']),
                    'confidence' => $mappingResult['overall_confidence'],
                    'unmapped_fields' => $mappingResult['unmapped_target_fields'],
                    'warnings' => $mappingResult['warnings']
                ],
                'tokens_used' => $mappingResult['tokens_used']
            ];

        } catch (Exception $e) {
            Log::error('❌ Intelligent DocuSeal submission failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturerName
            ]);

            // Fallback to basic DocuSeal service
            return $this->fallbackToBasicSubmission($formData, $options);
        }
    }

    /**
     * Create FHIR-enhanced submission using AI mapping
     */
    public function createFhirEnhancedSubmission(
        Episode $episode,
        array $additionalData = [],
        string $manufacturerName = '',
        array $options = []
    ): array {
        Log::info('🔬 Creating FHIR-enhanced DocuSeal submission', [
            'episode_id' => $episode->id,
            'manufacturer' => $manufacturerName,
            'additional_data_fields' => count($additionalData)
        ]);

        try {
            // Step 1: Extract FHIR data from episode
            $fhirData = $this->extractFhirDataFromEpisode($episode);

            // Step 2: Get DocuSeal template fields
            $templateFields = $this->getTemplateFieldsForManufacturer($manufacturerName);

            // Step 3: Use Azure AI to map FHIR data to DocuSeal fields
            $mappingResult = $this->azureAI->mapFhirToDocuSeal(
                $fhirData,
                $templateFields,
                $manufacturerName,
                array_merge($additionalData, [
                    'episode_id' => $episode->id,
                    'episode_status' => $episode->status
                ])
            );

            if (!$mappingResult['success']) {
                throw new Exception('FHIR to DocuSeal AI mapping failed');
            }

            Log::info('✅ FHIR AI mapping completed', [
                'mapped_fields' => count($mappingResult['mapped_data']),
                'fhir_resources_used' => count($fhirData),
                'confidence_average' => $mappingResult['statistics']['confidence_average'] ?? 0
            ]);

            // Step 4: Create DocuSeal submission
            $submissionResult = $this->docuSealService->createSubmission($mappingResult['mapped_data']);

            // Step 5: Update episode with submission info
            $episode->update([
                'metadata' => array_merge($episode->metadata ?? [], [
                    'ai_mapping_used' => true,
                    'fhir_fields_mapped' => count($mappingResult['mapped_data']),
                    'mapping_confidence' => $mappingResult['statistics']['confidence_average'] ?? 0
                ])
            ]);

            return [
                'success' => true,
                'submission' => $submissionResult,
                'fhir_mapping' => [
                    'mapped_fields' => count($mappingResult['mapped_data']),
                    'unmapped_fields' => $mappingResult['unmapped_fields'],
                    'confidence_average' => $mappingResult['statistics']['confidence_average'] ?? 0,
                    'warnings' => $mappingResult['warnings']
                ],
                'tokens_used' => $mappingResult['tokens_used']
            ];

        } catch (Exception $e) {
            Log::error('❌ FHIR-enhanced submission failed', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id
            ]);

            // Fallback to basic episode data
            return $this->fallbackToBasicEpisodeSubmission($episode, $additionalData);
        }
    }

    /**
     * Intelligently suggest field mappings for new templates
     */
    public function suggestTemplateMappings(
        array $sourceFields,
        array $targetTemplateFields,
        string $manufacturerName = '',
        array $sampleData = []
    ): array {
        try {
            $suggestions = $this->azureAI->suggestFieldMappings(
                $sourceFields,
                $targetTemplateFields,
                $sampleData,
                "Field mapping suggestions for {$manufacturerName} DocuSeal template"
            );

            Log::info('🎯 AI field mapping suggestions generated', [
                'manufacturer' => $manufacturerName,
                'suggestions_count' => count($suggestions['suggestions']),
                'strategy' => $suggestions['mapping_strategy']
            ]);

            return $suggestions;

        } catch (Exception $e) {
            Log::error('Field mapping suggestions failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturerName
            ]);
            throw $e;
        }
    }

    /**
     * Validate form data using AI
     */
    public function validateFormDataWithAI(
        array $formData,
        array $validationRules = [],
        string $context = ''
    ): array {
        try {
            return $this->azureAI->validateAndSuggest(
                $formData,
                $validationRules,
                $context ?: 'MSC Wound Care form validation'
            );

        } catch (Exception $e) {
            Log::error('AI form validation failed', [
                'error' => $e->getMessage(),
                'form_fields' => array_keys($formData)
            ]);
            throw $e;
        }
    }

    /**
     * Extract structured data from unstructured text using AI
     */
    public function extractDataFromText(
        string $text,
        array $targetSchema,
        string $context = ''
    ): array {
        try {
            return $this->azureAI->extractStructuredData(
                $text,
                $targetSchema,
                $context ?: 'Medical form data extraction'
            );

        } catch (Exception $e) {
            Log::error('AI text extraction failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);
            throw $e;
        }
    }

    /**
     * Get AI-powered field mapping analytics
     */
    public function getMappingAnalytics(
        string $manufacturerName = '',
        int $days = 30
    ): array {
        $cacheKey = "mapping_analytics_{$manufacturerName}_{$days}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($manufacturerName, $days) {
            // This would analyze stored mapping results to provide insights
            // For now, return basic structure
            return [
                'total_mappings' => 0,
                'average_confidence' => 0.0,
                'most_common_unmapped_fields' => [],
                'mapping_accuracy_trend' => [],
                'token_usage' => 0,
                'cost_estimate' => 0.0
            ];
        });
    }

    /**
     * Extract FHIR data from episode
     */
    private function extractFhirDataFromEpisode(Episode $episode): array
    {
        $fhirData = [];
        $metadata = $episode->metadata ?? [];
        $fhirIds = $metadata['fhir_ids'] ?? [];

        // Extract available FHIR data
        if (!empty($fhirIds['patient_id'])) {
            $fhirData['Patient'] = [
                'id' => $fhirIds['patient_id'],
                'name' => [
                    [
                        'given' => [$metadata['patient_first_name'] ?? ''],
                        'family' => $metadata['patient_last_name'] ?? ''
                    ]
                ],
                'birthDate' => $metadata['patient_dob'] ?? '',
                'gender' => $metadata['patient_gender'] ?? '',
                'telecom' => [
                    [
                        'system' => 'phone',
                        'value' => $metadata['patient_phone'] ?? ''
                    ],
                    [
                        'system' => 'email',
                        'value' => $metadata['patient_email'] ?? ''
                    ]
                ]
            ];
        }

        if (!empty($fhirIds['practitioner_id'])) {
            $fhirData['Practitioner'] = [
                'id' => $fhirIds['practitioner_id'],
                'name' => [
                    [
                        'text' => $metadata['provider_name'] ?? ''
                    ]
                ],
                'identifier' => [
                    [
                        'system' => 'http://hl7.org/fhir/sid/us-npi',
                        'value' => $metadata['provider_npi'] ?? ''
                    ]
                ]
            ];
        }

        if (!empty($fhirIds['coverage_id'])) {
            $fhirData['Coverage'] = [
                'id' => $fhirIds['coverage_id'],
                'payor' => [
                    [
                        'display' => $metadata['insurance_name'] ?? ''
                    ]
                ],
                'identifier' => [
                    [
                        'value' => $metadata['member_id'] ?? ''
                    ]
                ]
            ];
        }

        return $fhirData;
    }

    /**
     * Get template fields for a manufacturer
     */
    private function getTemplateFieldsForManufacturer(string $manufacturerName): array
    {
        // This would fetch actual template fields from database
        // For now, return a basic structure
        return [
            'Patient Name' => 'text',
            'DOB' => 'date',
            'Phone' => 'phone',
            'Email' => 'email',
            'Provider Name' => 'text',
            'Provider NPI' => 'text',
            'Insurance Name' => 'text',
            'Member ID' => 'text',
            'Diagnosis Code' => 'text',
            'Wound Type' => 'text',
            'Product Name' => 'text'
        ];
    }

    /**
     * Format mapped data for DocuSeal submission
     */
    private function formatMappedDataForDocuSeal(array $mappings): array
    {
        $formatted = [];
        
        foreach ($mappings as $fieldName => $mapping) {
            $formatted[$fieldName] = $mapping['value'] ?? '';
        }

        return $formatted;
    }

    /**
     * Store mapping results for future analysis
     */
    private function storeMappingResults(array $mappingResult, string $manufacturerName): void
    {
        // Store in cache or database for analytics
        $key = "mapping_result_" . md5($manufacturerName . time());
        
        Cache::put($key, [
            'manufacturer' => $manufacturerName,
            'timestamp' => now(),
            'confidence' => $mappingResult['overall_confidence'],
            'mapped_count' => count($mappingResult['mappings']),
            'unmapped_count' => count($mappingResult['unmapped_target_fields']),
            'warnings_count' => count($mappingResult['warnings']),
            'tokens_used' => $mappingResult['tokens_used']
        ], now()->addDays(30));
    }

    /**
     * Fallback to basic DocuSeal service
     */
    private function fallbackToBasicSubmission(array $formData, array $options): array
    {
        Log::warning('🔄 Falling back to basic DocuSeal submission');
        
        try {
            $result = $this->docuSealService->createSubmission($formData);
            
            return [
                'success' => true,
                'submission' => $result,
                'fallback_used' => true,
                'ai_mapping' => [
                    'mapped_fields' => 0,
                    'confidence' => 0.0,
                    'warnings' => ['AI mapping failed, used fallback']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_used' => true
            ];
        }
    }

    /**
     * Fallback for episode-based submissions
     */
    private function fallbackToBasicEpisodeSubmission(Episode $episode, array $additionalData): array
    {
        Log::warning('🔄 Falling back to basic episode submission');
        
        try {
            // Use basic episode metadata
            $basicData = array_merge($episode->metadata ?? [], $additionalData);
            $result = $this->docuSealService->createSubmission($basicData);
            
            return [
                'success' => true,
                'submission' => $result,
                'fallback_used' => true,
                'fhir_mapping' => [
                    'mapped_fields' => 0,
                    'confidence_average' => 0.0,
                    'warnings' => ['FHIR AI mapping failed, used basic episode data']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_used' => true
            ];
        }
    }

    /**
     * Test the AI integration
     */
    public function testAIIntegration(): array
    {
        try {
            $connectionTest = $this->azureAI->testConnection();
            
            if (!$connectionTest['success']) {
                return [
                    'success' => false,
                    'error' => 'Azure AI connection failed: ' . $connectionTest['error'],
                    'docuseal_available' => true
                ];
            }

            // Test a simple mapping
            $testResult = $this->azureAI->translateFormData(
                ['patient_name' => 'Test Patient', 'dob' => '1990-01-01'],
                ['Patient Name' => 'text', 'Date of Birth' => 'date'],
                'Test source',
                'Test target',
                ['use_cache' => false]
            );

            return [
                'success' => true,
                'azure_ai_status' => $connectionTest['status'],
                'test_mapping_confidence' => $testResult['overall_confidence'] ?? 0.0,
                'tokens_used' => $testResult['tokens_used'] ?? 0,
                'docuseal_available' => true
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'docuseal_available' => true
            ];
        }
    }
}
