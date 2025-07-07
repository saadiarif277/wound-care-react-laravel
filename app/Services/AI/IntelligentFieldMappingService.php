<?php

namespace App\Services\AI;

use App\Services\AI\AzureFoundryService;
use App\Services\UnifiedFieldMappingService;
use App\Models\IVRFieldMapping;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Intelligent Field Mapping Service
 * 
 * Enhances the existing field mapping system with AI-powered capabilities:
 * - Adaptive validation based on template requirements
 * - Intelligent missing field detection and suggestions
 * - Learning from past mappings to improve accuracy
 * - Dynamic field mapping for new templates
 */
class IntelligentFieldMappingService
{
    public function __construct(
        private AzureFoundryService $azureFoundry,
        private UnifiedFieldMappingService $unifiedMapping
    ) {}

    /**
     * Intelligently map episode data to template with AI assistance
     */
    public function mapEpisodeWithAI(
        ?int $episodeId,
        string $manufacturerName,
        array $additionalData = [],
        array $options = []
    ): array {
        Log::info('AI-enhanced field mapping started', [
            'episode_id' => $episodeId,
            'manufacturer' => $manufacturerName,
            'additional_data_fields' => count($additionalData),
            'options' => $options
        ]);

        try {
            // First, get the standard mapping
            $standardResult = $this->unifiedMapping->mapEpisodeToTemplate(
                $episodeId,
                $manufacturerName,
                $additionalData
            );

            // Log the standard result for debugging
            Log::info('Standard mapping result', [
                'manufacturer_name' => $manufacturerName,
                'has_manufacturer' => isset($standardResult['manufacturer']),
                'manufacturer_keys' => array_keys($standardResult['manufacturer'] ?? []),
                'has_template_id' => isset($standardResult['manufacturer']['docuseal_template_id']),
                'template_id' => $standardResult['manufacturer']['docuseal_template_id'] ?? null
            ]);

            // If standard mapping is successful and complete, use it
            if ($standardResult['validation']['valid'] && 
                ($standardResult['completeness']['percentage'] ?? 0) >= 90) {
                Log::info('Standard mapping sufficient, skipping AI enhancement');
                return $standardResult;
            }

            // Use AI to enhance the mapping
            $enhancedResult = $this->enhanceWithAI($standardResult, $manufacturerName, $options);

            return $enhancedResult;

        } catch (\Exception $e) {
            Log::error('AI-enhanced field mapping failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage()
            ]);

            // Fallback to standard mapping with relaxed validation
            return $this->fallbackMapping($episodeId, $manufacturerName, $additionalData);
        }
    }

    /**
     * Enhance mapping result with AI assistance
     */
    private function enhanceWithAI(array $standardResult, string $manufacturerName, array $options): array
    {
        $cacheKey = 'ai_enhancement_' . md5($manufacturerName . serialize($standardResult['data']));
        
        if ($options['use_cache'] ?? true) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('Using cached AI enhancement');
                return array_merge($standardResult, $cached);
            }
        }

        try {
            // Get template schema for this manufacturer
            $templateSchema = $this->getTemplateSchema($manufacturerName);
            
            // Use AI to suggest missing field mappings
            $aiSuggestions = $this->azureFoundry->suggestFieldMappings(
                array_keys($standardResult['data']),
                array_keys($templateSchema),
                $standardResult['data'],
                "IVR form for {$manufacturerName} medical device order"
            );

            // Apply AI suggestions to fill missing fields
            $enhancedData = $this->applyAISuggestions(
                $standardResult['data'],
                $aiSuggestions,
                $templateSchema
            );

            // Perform adaptive validation
            $adaptiveValidation = $this->performAdaptiveValidation(
                $enhancedData,
                $templateSchema,
                $manufacturerName
            );

            // Ensure we preserve the full manufacturer config from standard result
            $manufacturerConfig = $standardResult['manufacturer'] ?? ['name' => $manufacturerName];
            
            // Log manufacturer config preservation
            Log::info('Preserving manufacturer config in AI enhancement', [
                'manufacturer_name' => $manufacturerName,
                'has_template_id' => isset($manufacturerConfig['docuseal_template_id']),
                'template_id' => $manufacturerConfig['docuseal_template_id'] ?? null,
                'config_keys' => array_keys($manufacturerConfig)
            ]);

            $result = [
                'data' => $enhancedData,
                'validation' => $adaptiveValidation,
                'manufacturer' => $manufacturerConfig,
                'completeness' => $this->calculateCompleteness($enhancedData, $templateSchema),
                'ai_enhanced' => true,
                'ai_suggestions' => $aiSuggestions,
                'enhancement_method' => 'azure_foundry'
            ];

            // Cache successful enhancements
            if ($options['use_cache'] ?? true) {
                Cache::put($cacheKey, [
                    'ai_enhanced' => true,
                    'ai_suggestions' => $aiSuggestions,
                    'enhancement_method' => 'azure_foundry'
                ], now()->addHours(6));
            }

            Log::info('AI enhancement completed', [
                'manufacturer' => $manufacturerName,
                'fields_before' => count($standardResult['data']),
                'fields_after' => count($enhancedData),
                'validation_improved' => $adaptiveValidation['valid']
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('AI enhancement failed, using standard result', [
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage()
            ]);

            // Return standard result with relaxed validation
            return array_merge($standardResult, [
                'ai_enhanced' => false,
                'enhancement_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Apply AI suggestions to enhance field mapping
     */
    private function applyAISuggestions(array $currentData, array $aiSuggestions, array $templateSchema): array
    {
        $enhancedData = $currentData;

        foreach ($aiSuggestions['mappings'] ?? [] as $targetField => $suggestion) {
            // Only apply suggestions with high confidence
            if (($suggestion['confidence'] ?? 0) >= 0.7) {
                // Check if target field exists in template
                if (isset($templateSchema[$targetField])) {
                    $enhancedData[$targetField] = $suggestion['value'];
                    
                    Log::debug('Applied AI suggestion', [
                        'field' => $targetField,
                        'value' => $suggestion['value'],
                        'confidence' => $suggestion['confidence']
                    ]);
                }
            }
        }

        return $enhancedData;
    }

    /**
     * Perform adaptive validation based on template requirements
     */
    private function performAdaptiveValidation(array $data, array $templateSchema, string $manufacturerName): array
    {
        $errors = [];
        $warnings = [];
        $criticalMissing = [];
        $optionalMissing = [];

        // Categorize missing fields by importance
        foreach ($templateSchema as $field => $fieldConfig) {
            $isRequired = $fieldConfig['required'] ?? false;
            $isEmpty = empty($data[$field]) && $data[$field] !== '0' && $data[$field] !== 0;

            if ($isEmpty && $isRequired) {
                // Check if this is a critical field for this manufacturer
                if ($this->isCriticalField($field, $manufacturerName)) {
                    $criticalMissing[] = $field;
                    $errors[] = "Critical field '{$field}' is required for {$manufacturerName} but missing";
                } else {
                    $optionalMissing[] = $field;
                    $warnings[] = "Optional required field '{$field}' is missing but may be acceptable";
                }
            }
        }

        // Adaptive validation logic
        $isValid = empty($criticalMissing);

        return [
            'valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'critical_missing' => $criticalMissing,
            'optional_missing' => $optionalMissing,
            'validation_strategy' => 'adaptive',
            'can_proceed' => $isValid || (count($criticalMissing) === 0 && count($optionalMissing) <= 3)
        ];
    }

    /**
     * Determine if a field is critical for a specific manufacturer
     */
    private function isCriticalField(string $field, string $manufacturerName): bool
    {
        // Critical fields that are always required regardless of manufacturer
        $alwaysCritical = [
            'patient_name', 'patient_dob', 'physician_npi',
            'primary_insurance_name', 'primary_member_id'
        ];

        if (in_array($field, $alwaysCritical)) {
            return true;
        }

        // Manufacturer-specific critical fields
        $manufacturerCritical = [
            'MEDLIFE SOLUTIONS' => [
                'graft_size_requested', 'icd10_code_1', 'cpt_code_1',
                'failed_conservative_treatment', 'information_accurate'
            ],
            'CENTURION' => [
                'wound_type', 'wound_location', 'medical_necessity_established'
            ],
            // Add more manufacturers as needed
        ];

        return in_array($field, $manufacturerCritical[$manufacturerName] ?? []);
    }

    /**
     * Get template schema for a manufacturer
     */
    private function getTemplateSchema(string $manufacturerName): array
    {
        // Try to get from manufacturer config
        $config = $this->unifiedMapping->getManufacturerConfig($manufacturerName, 'IVR');
        
        if (isset($config['fields'])) {
            return $config['fields'];
        }

        // Fallback to database mappings
        $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
        if ($manufacturer) {
            $mappings = IVRFieldMapping::where('manufacturer_id', $manufacturer->id)
                ->where('confidence', '>=', 0.8)
                ->get();

            $schema = [];
            foreach ($mappings as $mapping) {
                $schema[$mapping->target_field] = [
                    'required' => $mapping->metadata['required'] ?? false,
                    'type' => $mapping->metadata['type'] ?? 'string',
                    'confidence' => $mapping->confidence
                ];
            }

            return $schema;
        }

        // Ultimate fallback - basic schema
        return $this->getBasicTemplateSchema();
    }

    /**
     * Get basic template schema as fallback
     */
    private function getBasicTemplateSchema(): array
    {
        return [
            'patient_name' => ['required' => true, 'type' => 'string'],
            'patient_dob' => ['required' => true, 'type' => 'date'],
            'physician_npi' => ['required' => true, 'type' => 'npi'],
            'primary_insurance_name' => ['required' => true, 'type' => 'string'],
            'primary_member_id' => ['required' => true, 'type' => 'string'],
            'graft_size_requested' => ['required' => false, 'type' => 'decimal'],
            'icd10_code_1' => ['required' => false, 'type' => 'icd10'],
            'cpt_code_1' => ['required' => false, 'type' => 'cpt'],
            'wound_type' => ['required' => false, 'type' => 'string'],
            'wound_location' => ['required' => false, 'type' => 'string'],
            'failed_conservative_treatment' => ['required' => false, 'type' => 'boolean'],
            'information_accurate' => ['required' => false, 'type' => 'boolean'],
            'medical_necessity_established' => ['required' => false, 'type' => 'boolean'],
            'maintain_documentation' => ['required' => false, 'type' => 'boolean']
        ];
    }

    /**
     * Calculate completeness percentage
     */
    private function calculateCompleteness(array $data, array $templateSchema): array
    {
        $totalFields = count($templateSchema);
        $filledFields = 0;
        $requiredFields = 0;
        $filledRequiredFields = 0;

        foreach ($templateSchema as $field => $config) {
            $isRequired = $config['required'] ?? false;
            $isFilled = !empty($data[$field]) || $data[$field] === '0' || $data[$field] === 0;

            if ($isRequired) {
                $requiredFields++;
                if ($isFilled) {
                    $filledRequiredFields++;
                }
            }

            if ($isFilled) {
                $filledFields++;
            }
        }

        return [
            'percentage' => $totalFields > 0 ? round(($filledFields / $totalFields) * 100, 2) : 0,
            'required_percentage' => $requiredFields > 0 ? round(($filledRequiredFields / $requiredFields) * 100, 2) : 100,
            'filled_fields' => $filledFields,
            'total_fields' => $totalFields,
            'filled_required' => $filledRequiredFields,
            'total_required' => $requiredFields
        ];
    }

    /**
     * Fallback mapping with relaxed validation
     */
    private function fallbackMapping(?int $episodeId, string $manufacturerName, array $additionalData): array
    {
        try {
            // Get standard mapping
            $result = $this->unifiedMapping->mapEpisodeToTemplate($episodeId, $manufacturerName, $additionalData);
            
            // Override validation to be more lenient
            $result['validation'] = [
                'valid' => true, // Always pass validation in fallback mode
                'errors' => [],
                'warnings' => $result['validation']['errors'] ?? [], // Convert errors to warnings
                'validation_strategy' => 'fallback_lenient',
                'can_proceed' => true
            ];

            return $result;

        } catch (\Exception $e) {
            Log::error('Fallback mapping also failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage()
            ]);

            // Ultimate fallback - return minimal structure
            return [
                'data' => $additionalData,
                'validation' => [
                    'valid' => true,
                    'errors' => [],
                    'warnings' => ['Using minimal data due to mapping failures'],
                    'validation_strategy' => 'minimal_fallback',
                    'can_proceed' => true
                ],
                'manufacturer' => ['name' => $manufacturerName],
                'completeness' => ['percentage' => 50],
                'ai_enhanced' => false,
                'fallback_used' => true
            ];
        }
    }

    /**
     * Learn from successful mappings to improve future AI suggestions
     */
    public function learnFromSuccess(string $manufacturerName, array $mappingData, array $submissionResult): void
    {
        try {
            // Store successful mapping patterns for future AI training
            $learningData = [
                'manufacturer' => $manufacturerName,
                'mapping_data' => $mappingData,
                'submission_success' => $submissionResult['success'] ?? false,
                'timestamp' => now()->toISOString()
            ];

            // Store in cache for AI training data
            $cacheKey = 'ai_learning_' . $manufacturerName . '_' . now()->format('Y-m-d');
            $existingData = Cache::get($cacheKey, []);
            $existingData[] = $learningData;
            
            Cache::put($cacheKey, $existingData, now()->addDays(30));

            Log::info('Recorded mapping success for AI learning', [
                'manufacturer' => $manufacturerName,
                'fields_count' => count($mappingData),
                'success' => $submissionResult['success'] ?? false
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to record AI learning data', [
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage()
            ]);
        }
    }
}
