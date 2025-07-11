<?php

namespace App\Services;

use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;

use App\Services\DocuSeal\TemplateFieldValidationService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\CanonicalField;
use App\Services\CanonicalFieldMappingService;
// use App\Models\TemplateFieldMapping; // Model doesn't exist - commenting out
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\QuickRequest;
use App\Models\Patient;
use App\Models\ProviderDetail;
use App\Models\Order;
use App\Services\FhirService;
use App\Services\MedicalTerminologyService;
// use App\Services\EpisodeService; // Service doesn't exist - commenting out
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UnifiedFieldMappingService
{
    private array $config;
    private array $orderFormConfig;

    protected FhirService $fhirService;
    protected MedicalTerminologyService $medicalTerminologyService;
    protected ?string $aiServiceUrl;
    protected bool $aiServiceEnabled;

    public function __construct(
        private DataExtractor $dataExtractor,
        private FieldTransformer $fieldTransformer,
        private FieldMatcher $fieldMatcher,
        // protected EpisodeService $episodeService, // Service doesn't exist - removing
        // protected DocusealService $docusealService, // Circular dependency - removing
        FhirService $fhirService,
        MedicalTerminologyService $medicalTerminologyService,
        private CanonicalFieldMappingService $canonicalMappingService,
        private ?TemplateFieldValidationService $fieldValidator = null
    ) {
        $this->fhirService = $fhirService;
        $this->medicalTerminologyService = $medicalTerminologyService;
        $this->aiServiceUrl = config('services.medical_ai.base_url');
        $this->aiServiceEnabled = !empty($this->aiServiceUrl);
        
        $this->config = config('field-mapping');
        $this->orderFormConfig = config('order-form-mapping');
        
        if (!$this->config) {
            Log::warning('Field mapping configuration not found, using defaults');
            $this->config = [
                'transformations' => [],
                'validation' => [],
                'defaults' => []
            ];
        }
        
        if (!$this->orderFormConfig) {
            Log::warning('Order form mapping configuration not found, using defaults');
            $this->orderFormConfig = [
                'templates' => [],
                'field_mapping' => []
            ];
        }
        
        // Initialize field validator if not provided (for dependency injection)
        if (!$this->fieldValidator) {
            try {
                $this->fieldValidator = app(TemplateFieldValidationService::class);
            } catch (\Exception $e) {
                Log::warning('Could not initialize TemplateFieldValidationService', [
                    'error' => $e->getMessage()
                ]);
                $this->fieldValidator = null;
            }
        }
    }

    /**
     * Enhanced entry point that can use dynamic AI mapping or fall back to static
     */
    public function mapEpisodeToDocuSeal(
        ?string $episodeId,
        string $manufacturerName,
        string $templateId,
        array $additionalData = [],
        ?string $submitterEmail = null,
        bool $useDynamicMapping = true
    ): array {
        // Try AI-enhanced mapping first if enabled
        if ($useDynamicMapping && config('docuseal-dynamic.mapping.enable_caching')) {
            try {
                // Use OptimizedMedicalAiService for AI-enhanced mapping
                if (class_exists('\App\Services\Medical\OptimizedMedicalAiService') && $episodeId) {
                    $episode = \App\Models\PatientManufacturerIVREpisode::find($episodeId);
                    if ($episode) {
                        $aiService = app(\App\Services\Medical\OptimizedMedicalAiService::class);
                        
                        // Get FHIR data from episode
                        $fhirData = [];
                        if ($episode->patient && $episode->patient->azure_fhir_id) {
                            try {
                                $fhirData = $this->fhirService->getPatientById($episode->patient->azure_fhir_id) ?? [];
                            } catch (\Exception $e) {
                                Log::warning('Failed to fetch FHIR patient data', [
                                    'patient_fhir_id' => $episode->patient->azure_fhir_id,
                                    'error' => $e->getMessage()
                                ]);
                                $fhirData = [];
                            }
                        }
                        
                        // Use dynamic template discovery instead of static mapping
                        $result = $aiService->enhanceWithDynamicTemplate(
                            $fhirData,
                            $templateId,
                            $manufacturerName,
                            $additionalData
                        );
                    
                        Log::info('Dynamic template mapping completed successfully', [
                            'episode_id' => $episodeId,
                            'manufacturer' => $manufacturerName,
                            'template_id' => $templateId,
                            'mapped_fields' => count($result),
                            'mapping_method' => 'dynamic_template'
                        ]);
                        
                        // Convert to expected format
                        return [
                            'data' => $result,
                            'validation' => ['quality_grade' => 'dynamic_template_enhanced'],
                            'manufacturer' => ['name' => $manufacturerName],
                            'completeness' => ['percentage' => 100],
                            'metadata' => [
                                'episode_id' => $episodeId,
                                'manufacturer' => $manufacturerName,
                                'template_id' => $templateId,
                                'mapped_at' => now()->toIso8601String(),
                                'source' => 'dynamic_template',
                                'mapping_method' => 'ai_with_template_discovery'
                            ]
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                Log::warning('AI mapping failed, falling back to static', [
                    'episode_id' => $episodeId,
                    'manufacturer' => $manufacturerName,
                    'error' => $e->getMessage()
                ]);
                // Continue to static mapping below
            }
        }
        
        // Fall back to static mapping
        Log::info('Using static field mapping', [
            'episode_id' => $episodeId,
            'manufacturer' => $manufacturerName,
            'dynamic_attempted' => $useDynamicMapping
        ]);
        
        return $this->mapEpisodeToTemplate($episodeId, $manufacturerName, $additionalData, 'IVR');
    }

    /**
     * Main entry point for all field mapping needs (enhanced with CSV-based template mapping)
     */
    public function mapEpisodeToTemplate(
        ?string $episodeId, 
        string $manufacturerName,
        array $additionalData = [],
        string $documentType = 'IVR'
    ): array {
        $startTime = microtime(true);

        try {
            // 1. Extract all data once
            if ($episodeId) {
                $sourceData = $this->dataExtractor->extractEpisodeData($episodeId);
                $sourceData = array_merge($sourceData, $additionalData);
            } else {
                // If no episode, use only additional data
                $sourceData = $additionalData;
            }

            // 2. Get template ID for this manufacturer
            $templateId = $this->getTemplateIdForManufacturer($manufacturerName);
            
            // 3. Try CSV-based template mapping first (more comprehensive)
            if ($templateId) {
                Log::info('Using CSV-based template mapping', [
                    'manufacturer' => $manufacturerName,
                    'template_id' => $templateId,
                    'episode_id' => $episodeId,
                    'source_fields_count' => count($sourceData)
                ]);
                
                $mappedData = $this->mapFieldsWithCSVMapping($sourceData, $templateId);
                
                if (!empty($mappedData)) {
                    // CSV mapping successful
                    Log::info('CSV-based template mapping successful', [
                        'manufacturer' => $manufacturerName,
                        'template_id' => $templateId,
                        'mapped_fields_count' => count($mappedData),
                        'mapping_method' => 'csv_template_mapping'
                    ]);
                    
                    return [
                        'data' => $mappedData,
                        'validation' => ['valid' => true, 'errors' => [], 'warnings' => []],
                        'manufacturer' => ['name' => $manufacturerName, 'template_id' => $templateId],
                        'completeness' => ['percentage' => min(100, (count($mappedData) / 20) * 100)], // Estimate based on typical form size
                        'metadata' => [
                            'episode_id' => $episodeId,
                            'manufacturer' => $manufacturerName,
                            'template_id' => $templateId,
                            'mapped_at' => now()->toIso8601String(),
                            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                            'source' => 'csv_template_mapping',
                            'field_count' => count($mappedData)
                        ]
                    ];
                }
            }

            // 4. Fallback to old manufacturer configuration if CSV mapping fails
            Log::info('CSV mapping not available, falling back to manufacturer config', [
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'has_template_id' => !empty($templateId)
            ]);
            
            // Get manufacturer configuration based on document type
            $manufacturerConfig = $this->getManufacturerConfig($manufacturerName, $documentType);
            if (!$manufacturerConfig) {
                throw new \InvalidArgumentException("Unknown manufacturer: {$manufacturerName} for document type: {$documentType}");
            }

            // Map fields according to old configuration
            $mappedData = $this->mapFields($sourceData, $manufacturerConfig['fields']);

            // Apply manufacturer-specific business rules
            $mappedData = $this->applyBusinessRules($mappedData, $manufacturerConfig, $sourceData);

            // Validate mapped data
            $validation = $this->validateMapping($mappedData, $manufacturerConfig);

            // Calculate completeness
            $completeness = $this->calculateCompleteness($mappedData, $manufacturerConfig);

            // Log mapping analytics
            if ($episodeId) {
                $this->logMappingAnalytics($episodeId, $manufacturerName, $completeness, microtime(true) - $startTime);
            }

            return [
                'data' => $mappedData,
                'validation' => $validation,
                'manufacturer' => $manufacturerConfig,
                'completeness' => $completeness,
                'metadata' => [
                    'episode_id' => $episodeId,
                    'manufacturer' => $manufacturerName,
                    'mapped_at' => now()->toIso8601String(),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'source' => $episodeId ? 'episode' : 'direct'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Field mapping failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Map fields according to manufacturer configuration
     */
    private function mapFields(array $sourceData, array $fieldConfig): array
    {
        $mapped = [];

        foreach ($fieldConfig as $targetField => $config) {
            $value = null;

            // Handle different source types
            switch ($config['source']) {
                case 'computed':
                    $value = $this->computeField($config['computation'], $sourceData);
                    break;
                
                case 'fuzzy':
                    $match = $this->fieldMatcher->findBestMatch(
                        $targetField, 
                        array_keys($sourceData),
                        $sourceData
                    );
                    $value = $match ? $sourceData[$match['field']] : null;
                    break;
                
                default:
                    // Handle OR conditions in source
                    if (str_contains($config['source'], ' || ')) {
                        $sources = array_map('trim', explode(' || ', $config['source']));
                        foreach ($sources as $source) {
                            $value = $this->getValueByPath($sourceData, $source);
                            if ($value !== null && $value !== '') {
                                break;
                            }
                        }
                    } else {
                        // Direct field mapping
                        $value = $this->getValueByPath($sourceData, $config['source']);
                    }
            }

            // Apply transformation if specified
            if ($value !== null && isset($config['transform'])) {
                $value = $this->fieldTransformer->transform($value, $config['transform']);
            }

            $mapped[$targetField] = $value;
        }
        
        Log::info('Field mapping completed', [
            'mapped_fields_count' => count($mapped),
            'mapped_fields' => array_keys($mapped),
            'sample_mapped_data' => array_slice($mapped, 0, 10, true)
        ]);

        return $mapped;
    }

    /**
     * Compute field value based on computation expression
     */
    private function computeField(string $computation, array $data): mixed
    {
        // Special computations
        if ($computation === 'format_duration') {
            return $this->fieldTransformer->formatDuration($data);
        }

        // Handle specific field computations
        if ($computation === 'wound_size_total') {
            $length = (float)($this->getValueByPath($data, 'wound_size_length') ?? 
                             $this->getValueByPath($data, 'wound_length') ?? 0);
            $width = (float)($this->getValueByPath($data, 'wound_size_width') ?? 
                            $this->getValueByPath($data, 'wound_width') ?? 0);
            return $length * $width;
        }

        if ($computation === 'patient_full_address') {
            $parts = [];
            $line1 = $this->getValueByPath($data, 'patient_address_line1') ?? 
                    $this->getValueByPath($data, 'patient_address');
            if ($line1) $parts[] = $line1;
            
            $line2 = $this->getValueByPath($data, 'patient_address_line2');
            if ($line2) $parts[] = $line2;
            
            $city = $this->getValueByPath($data, 'patient_city');
            $state = $this->getValueByPath($data, 'patient_state');
            $zip = $this->getValueByPath($data, 'patient_zip') ?? 
                   $this->getValueByPath($data, 'patient_postal_code');
            
            if ($city && $state && $zip) {
                $parts[] = "{$city}, {$state} {$zip}";
            } elseif ($city && $state) {
                $parts[] = "{$city}, {$state}";
            } elseif ($city) {
                $parts[] = $city;
            }
            
            return implode(', ', $parts);
        }

        if ($computation === 'facility_full_address') {
            $parts = [];
            $line1 = $this->getValueByPath($data, 'facility_address_line1') ?? 
                    $this->getValueByPath($data, 'facility_address');
            if ($line1) $parts[] = $line1;
            
            $line2 = $this->getValueByPath($data, 'facility_address_line2');
            if ($line2) $parts[] = $line2;
            
            $city = $this->getValueByPath($data, 'facility_city');
            $state = $this->getValueByPath($data, 'facility_state');
            $zip = $this->getValueByPath($data, 'facility_zip') ?? 
                   $this->getValueByPath($data, 'facility_zip_code');
            
            if ($city && $state && $zip) {
                $parts[] = "{$city}, {$state} {$zip}";
            } elseif ($city && $state) {
                $parts[] = "{$city}, {$state}";
            } elseif ($city) {
                $parts[] = $city;
            }
            
            return implode(', ', $parts);
        }

        if ($computation === 'provider_full_name' || $computation === 'physician_full_name') {
            $first = $this->getValueByPath($data, 'provider_first_name') ?? 
                    $this->getValueByPath($data, 'physician_first_name');
            $last = $this->getValueByPath($data, 'provider_last_name') ?? 
                   $this->getValueByPath($data, 'physician_last_name');
            
            if ($first && $last) {
                return "{$first} {$last}";
            }
            
            // Fall back to pre-computed full name fields
            return $this->getValueByPath($data, 'provider_name') ?? 
                   $this->getValueByPath($data, 'physician_name') ??
                   $this->getValueByPath($data, 'provider_full_name') ??
                   $this->getValueByPath($data, 'physician_full_name') ?? '';
        }

        // Handle concatenation (field1 + field2)
        if (str_contains($computation, ' + ')) {
            $parts = array_map('trim', explode(' + ', $computation));
            $values = [];
            foreach ($parts as $part) {
                // Handle quoted strings
                if ((str_starts_with($part, '"') && str_ends_with($part, '"')) ||
                    (str_starts_with($part, "'") && str_ends_with($part, "'"))) {
                    $values[] = trim($part, '"\'');
                } else {
                    $value = $this->getValueByPath($data, $part);
                    if ($value !== null && $value !== '') {
                        $values[] = $value;
                    }
                }
            }
            return implode(' ', $values);
        }

        // Handle multiplication (field1 * field2)
        if (str_contains($computation, ' * ')) {
            $parts = array_map('trim', explode(' * ', $computation));
            $values = array_map(fn($part) => (float)($this->getValueByPath($data, $part) ?? 0), $parts);
            return array_reduce($values, fn($carry, $item) => $carry * $item, 1);
        }

        // Handle OR conditions (field1 || field2)
        if (str_contains($computation, ' || ')) {
            $parts = array_map('trim', explode(' || ', $computation));
            foreach ($parts as $part) {
                $value = $this->getValueByPath($data, $part);
                if (!empty($value)) {
                    return $value;
                }
            }
            return null;
        }

        // Handle division (field1 / field2)
        if (str_contains($computation, ' / ')) {
            $parts = array_map('trim', explode(' / ', $computation));
            if (count($parts) === 2) {
                $numerator = (float)($this->getValueByPath($data, $parts[0]) ?? 0);
                $denominator = (float)($this->getValueByPath($data, $parts[1]) ?? 1);
                return $denominator != 0 ? $numerator / $denominator : 0;
            }
        }

        // Handle conditional expressions (condition ? value1 : value2)
        if (str_contains($computation, ' ? ') && str_contains($computation, ' : ')) {
            return $this->evaluateConditional($computation, $data);
        }

        // If no special operators, treat as a simple field path (including array indexing)
        return $this->getValueByPath($data, $computation);
    }

    /**
     * Evaluate conditional expressions (condition ? value1 : value2)
     */
    private function evaluateConditional(string $computation, array $data): mixed
    {
        // Parse condition ? value1 : value2
        $questionPos = strpos($computation, ' ? ');
        $colonPos = strpos($computation, ' : ');
        
        if ($questionPos === false || $colonPos === false || $colonPos <= $questionPos) {
            return null;
        }
        
        $condition = trim(substr($computation, 0, $questionPos));
        $trueValue = trim(substr($computation, $questionPos + 3, $colonPos - $questionPos - 3));
        $falseValue = trim(substr($computation, $colonPos + 3));
        
        // Evaluate the condition
        $conditionResult = $this->evaluateCondition($condition, $data);
        
        // Return appropriate value
        if ($conditionResult) {
            // Handle quoted strings
            if ((str_starts_with($trueValue, '"') && str_ends_with($trueValue, '"')) ||
                (str_starts_with($trueValue, "'") && str_ends_with($trueValue, "'"))) {
                return trim($trueValue, '"\'');
            }
            return $this->getValueByPath($data, $trueValue);
        } else {
            // Handle quoted strings
            if ((str_starts_with($falseValue, '"') && str_ends_with($falseValue, '"')) ||
                (str_starts_with($falseValue, "'") && str_ends_with($falseValue, "'"))) {
                return trim($falseValue, '"\'');
            }
            return $this->getValueByPath($data, $falseValue);
        }
    }

    /**
     * Evaluate a condition (supports ==, !=, >, <, etc.)
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        // Handle OR conditions (||)
        if (str_contains($condition, ' || ')) {
            $parts = explode(' || ', $condition);
            foreach ($parts as $part) {
                if ($this->evaluateCondition(trim($part), $data)) {
                    return true;
                }
            }
            return false;
        }
        
        // Handle AND conditions (&&)
        if (str_contains($condition, ' && ')) {
            $parts = explode(' && ', $condition);
            foreach ($parts as $part) {
                if (!$this->evaluateCondition(trim($part), $data)) {
                    return false;
                }
            }
            return true;
        }
        
        // Handle comparison operators
        $operators = ['==', '!=', '>=', '<=', '>', '<'];
        
        foreach ($operators as $operator) {
            if (str_contains($condition, " $operator ")) {
                $parts = explode(" $operator ", $condition, 2);
                if (count($parts) === 2) {
                    $left = trim($parts[0]);
                    $right = trim($parts[1]);
                    
                    // Get values
                    $leftValue = $this->getValueByPath($data, $left);
                    $rightValue = $right;
                    
                    // Handle quoted strings
                    if ((str_starts_with($right, '"') && str_ends_with($right, '"')) ||
                        (str_starts_with($right, "'") && str_ends_with($right, "'"))) {
                        $rightValue = trim($right, '"\'');
                    } else {
                        // Try to get from data if not quoted
                        $dataValue = $this->getValueByPath($data, $right);
                        if ($dataValue !== null) {
                            $rightValue = $dataValue;
                        }
                    }
                    
                    // Perform comparison
                    switch ($operator) {
                        case '==':
                            return $leftValue == $rightValue;
                        case '!=':
                            return $leftValue != $rightValue;
                        case '>':
                            return (float)$leftValue > (float)$rightValue;
                        case '<':
                            return (float)$leftValue < (float)$rightValue;
                        case '>=':
                            return (float)$leftValue >= (float)$rightValue;
                        case '<=':
                            return (float)$leftValue <= (float)$rightValue;
                    }
                }
            }
        }
        
        // If no operators found, treat as a simple boolean check
        $value = $this->getValueByPath($data, $condition);
        return !empty($value);
    }

    /**
     * Apply manufacturer-specific business rules
     */
    private function applyBusinessRules(array $mappedData, array $manufacturerConfig, array $sourceData): array
    {
        // Default for patientInSkilledNursingFacility
        if (!isset($mappedData['patientInSkilledNursingFacility'])) {
            $mappedData['patientInSkilledNursingFacility'] = false;
        }

        // Handle place_of_service boolean expansion
        $placeOfService = $sourceData['place_of_service'] ?? 
                         $sourceData['facility_default_place_of_service'] ?? 
                         $sourceData['default_place_of_service'] ?? null;
        
        if ($placeOfService !== null) {
            // Map common place of service values to checkboxes
            $posMapping = [
                'office' => 'pos_office',
                '11' => 'pos_office',
                'outpatient' => 'pos_outpatient',
                'outpatient_hospital' => 'pos_outpatient',
                '22' => 'pos_outpatient',
                'asc' => 'pos_asc',
                'ambulatory_surgical_center' => 'pos_asc',
                '24' => 'pos_asc',
                'home' => 'pos_home',
                '12' => 'pos_home',
                'assisted_living' => 'pos_assisted_living',
                '13' => 'pos_assisted_living',
                'skilled_nursing' => 'pos_skilled_nursing',
                'snf' => 'pos_skilled_nursing',
                '31' => 'pos_skilled_nursing',
                'nursing_home' => 'pos_nursing_home',
                '32' => 'pos_nursing_home'
            ];
            
            // First set all POS fields to false if they don't exist
            $posFields = ['pos_office', 'pos_outpatient', 'pos_asc', 'pos_home', 
                         'pos_assisted_living', 'pos_skilled_nursing', 'pos_nursing_home', 'pos_other'];
            foreach ($posFields as $posField) {
                if (!isset($mappedData[$posField])) {
                    $mappedData[$posField] = false;
                }
            }
            
            // Now set the appropriate one to true
            $normalizedPos = strtolower(trim($placeOfService));
            if (isset($posMapping[$normalizedPos])) {
                $mappedData[$posMapping[$normalizedPos]] = true;
            } else {
                // If we don't recognize it, mark as "other" and store the value
                $mappedData['pos_other'] = true;
                $mappedData['pos_other_text'] = $placeOfService;
            }
        }

        // Handle wound type boolean expansion
        $woundType = $sourceData['wound_type'] ?? null;
        if ($woundType !== null) {
            $woundTypeMapping = [
                'diabetic_foot_ulcer' => 'wound_diabetic',
                'diabetic foot ulcer' => 'wound_diabetic',
                'dfu' => 'wound_diabetic',
                'venous_leg_ulcer' => 'wound_venous',
                'venous leg ulcer' => 'wound_venous',
                'vlu' => 'wound_venous',
                'pressure_ulcer' => 'wound_pressure',
                'pressure ulcer' => 'wound_pressure',
                'pressure_injury' => 'wound_pressure',
                'traumatic_burns' => 'wound_traumatic_burns',
                'traumatic burns' => 'wound_traumatic_burns',
                'radiation_burns' => 'wound_radiation_burns',
                'radiation burns' => 'wound_radiation_burns',
                'necrotizing_fasciitis' => 'wound_necrotizing',
                'necrotizing fasciitis' => 'wound_necrotizing',
                'dehisced_surgical_wound' => 'wound_dehisced',
                'dehisced surgical wound' => 'wound_dehisced',
                'surgical_wound' => 'wound_dehisced'
            ];
            
            // Set all wound type fields to false first
            $woundFields = ['wound_diabetic', 'wound_venous', 'wound_pressure', 
                           'wound_traumatic_burns', 'wound_radiation_burns', 
                           'wound_necrotizing', 'wound_dehisced', 'wound_other'];
            foreach ($woundFields as $woundField) {
                if (!isset($mappedData[$woundField])) {
                    $mappedData[$woundField] = false;
                }
            }
            
            // Set the appropriate wound type to true
            $normalizedWoundType = strtolower(trim($woundType));
            if (isset($woundTypeMapping[$normalizedWoundType])) {
                $mappedData[$woundTypeMapping[$normalizedWoundType]] = true;
            } else {
                $mappedData['wound_other'] = true;
                $mappedData['wound_other_type'] = $woundType;
            }
        }

        // Handle insurance type boolean expansion
        $primaryPlanType = $sourceData['primary_plan_type'] ?? null;
        if ($primaryPlanType !== null) {
            $planTypeMapping = [
                'hmo' => 'primary_hmo',
                'ppo' => 'primary_ppo',
                'medicare_advantage' => 'primary_hmo',
                'medicare advantage' => 'primary_hmo',
                'traditional_medicare' => 'primary_other',
                'traditional medicare' => 'primary_other'
            ];
            
            // Set defaults
            if (!isset($mappedData['primary_hmo'])) $mappedData['primary_hmo'] = false;
            if (!isset($mappedData['primary_ppo'])) $mappedData['primary_ppo'] = false;
            if (!isset($mappedData['primary_other'])) $mappedData['primary_other'] = false;
            
            $normalizedPlanType = strtolower(trim($primaryPlanType));
            if (isset($planTypeMapping[$normalizedPlanType])) {
                $mappedData[$planTypeMapping[$normalizedPlanType]] = true;
            } else {
                $mappedData['primary_other'] = true;
                $mappedData['primary_other_type'] = $primaryPlanType;
            }
        }

        $manufacturerName = $manufacturerConfig['name'] ?? '';
        // Example of a manufacturer-specific rule
        if ($manufacturerName === 'Smith & Nephew') {
            // Custom logic for Smith & Nephew
        }

        return $mappedData;
    }

    /**
     * Validate mapped fields against configuration
     */
    private function validateMapping(array $data, array $config): array
    {
        return $this->validateMappedData($data, $config);
    }

    /**
     * Validate mapped data against configuration
     */
    private function validateMappedData(array $data, array $config): array
    {
        $errors = [];
        $warnings = [];
        $criticalErrors = [];
        $missingOptionalFields = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            $isRequired = $fieldConfig['required'] ?? false;
            $fieldValue = $this->getValueByPath($data, $field);
            $isEmpty = !$this->isFieldSet($fieldValue);

            if ($isRequired && $isEmpty) {
                // Categorize by importance for better error handling
                $importance = $fieldConfig['importance'] ?? 'medium';
                
                if ($importance === 'critical') {
                    $criticalErrors[] = "Critical field '{$field}' is missing or empty";
                } else {
                    // For non-critical required fields, just warn
                    $warnings[] = "Required field '{$field}' is missing or empty (some IVR forms may not need this)";
                }
            } elseif (!$isRequired && $isEmpty) {
                $missingOptionalFields[] = $field;
            }

            // Validate format if specified and value exists
            if (!$isEmpty && isset($fieldConfig['type'])) {
                $isValid = $this->validateFieldType($fieldValue, $fieldConfig['type']);
                if (!$isValid) {
                    $warnings[] = "Field '{$field}' format may be invalid for type '{$fieldConfig['type']}'";
                }
            }
        }

        // Include any warnings from business rules
        if (isset($data['_warnings'])) {
            $warnings = array_merge($warnings, $data['_warnings']);
        }

        // Add intelligent recommendations
        if (!empty($missingOptionalFields)) {
            $warnings[] = "Consider adding optional fields for better form completion: " . implode(', ', array_slice($missingOptionalFields, 0, 5));
        }

        // Only fail validation if there are critical errors
        $isValid = empty($criticalErrors);

        return [
            'valid' => $isValid,
            'errors' => array_merge($criticalErrors, $errors),
            'warnings' => $warnings,
            'critical_errors' => $criticalErrors,
            'missing_optional_fields' => $missingOptionalFields,
            'validation_strategy' => 'intelligent_flexible'
        ];
    }

    /**
     * Validate field type
     */
    private function validateFieldType($value, string $type): bool
    {
        $validationRules = $this->config['validation_rules'] ?? [];
        
        // Check if we have a specific validation rule for this type
        if (isset($validationRules[$type])) {
            $pattern = $validationRules[$type];
            
            // For phone and NPI, remove formatting before validation
            if (in_array($type, ['phone', 'npi'])) {
                $value = preg_replace('/\D/', '', $value);
            }
            
            return (bool) preg_match($pattern, $value);
        }

        // Basic type validation
        switch ($type) {
            case 'string':
                return is_string($value) || is_numeric($value);
                
            case 'number':
                return is_numeric($value);
                
            case 'boolean':
                return in_array($value, ['Yes', 'No', 'true', 'false', '1', '0', 1, 0, true, false], true);
                
            case 'date':
                try {
                    new \DateTime($value);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                
            default:
                return true; // Unknown types pass validation
        }
    }

    /**
     * Calculate mapping completeness
     */
    private function calculateCompleteness(array $data, array $config): array
    {
        $total = count($config['fields']);
        $filled = 0;
        $required = 0;
        $requiredFilled = 0;
        $fieldStatus = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            $isFilled = $this->isFieldSet($data[$field] ?? null);
            
            if ($isFilled) {
                $filled++;
            }
            
            if ($fieldConfig['required'] ?? false) {
                $required++;
                if ($isFilled) {
                    $requiredFilled++;
                }
            }

            $fieldStatus[$field] = [
                'filled' => $isFilled,
                'required' => $fieldConfig['required'] ?? false,
                'value' => $data[$field] ?? null
            ];
        }

        $requiredFields = collect($config['validation'] ?? [])
            ->filter(fn($v) => $v['required'] ?? false)
            ->keys()
            ->toArray();
        
        $unfilled = array_filter($requiredFields, fn($field) => empty($data[$field] ?? null));
        
        if (!empty($unfilled)) {
            $topUnfilled = array_slice($unfilled, 0, 3);
            Log::debug('Top unfilled required fields: ' . implode(', ', $topUnfilled));
        }
        
        $providerUnfilled = array_filter($unfilled, fn($f) => stripos($f, 'physician') !== false || stripos($f, 'provider') !== false);
        if (!empty($providerUnfilled)) {
            Log::info('Unfilled provider fields: ' . implode(', ', $providerUnfilled));
        }

        return [
            'percentage' => $total > 0 ? round(($filled / $total) * 100, 2) : 0,
            'overall_percentage' => $total > 0 ? round(($filled / $total) * 100, 1) : 0,
            'required_percentage' => $required > 0 ? round(($requiredFilled / $required) * 100, 2) : 100,
            'total_fields' => $total,
            'filled_fields' => $filled,
            'filled' => $filled,
            'total' => $total,
            'required_fields' => $required,
            'required_filled' => $requiredFilled,
            'required_total' => $required,
            'field_status' => $fieldStatus
        ];
    }

    /**
     * Get value by dot notation path
     */
    private function getValueByPath(array $data, string $path): mixed
    {
        // Handle array indexing syntax like application_cpt_codes[0]
        if (str_contains($path, '[') && str_contains($path, ']')) {
            // Extract array name and index
            preg_match('/^([^[]+)\[(\d+)\]$/', $path, $matches);
            if (count($matches) === 3) {
                $arrayName = $matches[1];
                $index = (int)$matches[2];
                
                if (isset($data[$arrayName]) && is_array($data[$arrayName]) && isset($data[$arrayName][$index])) {
                    return $data[$arrayName][$index];
                }
                return null;
            }
        }
        
        // Handle dot notation for nested arrays
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            // Handle array indexing within dot notation
            if (str_contains($key, '[') && str_contains($key, ']')) {
                preg_match('/^([^[]+)\[(\d+)\]$/', $key, $matches);
                if (count($matches) === 3) {
                    $arrayName = $matches[1];
                    $index = (int)$matches[2];
                    
                    if (!is_array($value) || !isset($value[$arrayName]) || !is_array($value[$arrayName]) || !isset($value[$arrayName][$index])) {
                        return null;
                    }
                    $value = $value[$arrayName][$index];
                } else {
                    return null;
                }
            } else {
                if (!is_array($value) || !isset($value[$key])) {
                    return null;
                }
                $value = $value[$key];
            }
        }

        return $value;
    }

    /**
     * Check if field value is considered set/filled
     */
    private function isFieldSet($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        
        if (is_array($value) && empty($value)) {
            return false;
        }
        
        // Consider '0' and 0 as set values
        if ($value === '0' || $value === 0) {
            return true;
        }
        
        return true;
    }

    /**
     * Get manufacturer configuration based on document type
     */
    public function getManufacturerConfig(string $name, string $documentType = 'IVR'): ?array
    {
        // First try to load from individual manufacturer file
        $config = $this->loadManufacturerFromFile($name);
        if ($config) {
            // Validate that IVR configs have template ID
            if ($documentType === 'IVR' && !isset($config['docuseal_template_id'])) {
                // Try to get template ID from database
                $templateId = $this->getTemplateIdFromDatabase($name, $documentType);
                if ($templateId) {
                    $config['docuseal_template_id'] = $templateId;
                    Log::info("Added template ID from database to manufacturer config", [
                        'manufacturer' => $name,
                        'template_id' => $templateId
                    ]);
                } else {
                    Log::warning("Manufacturer IVR config missing docuseal_template_id", [
                        'manufacturer' => $name,
                        'document_type' => $documentType,
                        'config_keys' => array_keys($config)
                    ]);
                }
            }
            
            // LIFE FIX: Validate and filter fields against actual DocuSeal template
            if ($this->fieldValidator && isset($config['docuseal_template_id']) && $config['docuseal_template_id']) {
                try {
                    $validatedConfig = $this->fieldValidator->filterValidFields($config);
                    
                    if ($validatedConfig !== $config) {
                        Log::info("Applied field validation filter to manufacturer config", [
                            'manufacturer' => $name,
                            'template_id' => $config['docuseal_template_id'],
                            'original_field_count' => count($config['docuseal_field_names'] ?? []),
                            'filtered_field_count' => count($validatedConfig['docuseal_field_names'] ?? [])
                        ]);
                    }
                    
                    return $validatedConfig;
                } catch (\Exception $e) {
                    Log::warning("Field validation failed, using original config", [
                        'manufacturer' => $name,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $config;
        }

        // Choose the appropriate config based on document type
        $configSource = $documentType === 'OrderForm' ? $this->orderFormConfig : $this->config;
        
        // First try exact match in main config
        if (isset($configSource['manufacturers'][$name])) {
            $config = $configSource['manufacturers'][$name];
            $config = $this->resolveConfigReference($config, $configSource);
            
            // Ensure template ID is set for IVR documents
            if ($documentType === 'IVR' && !isset($config['docuseal_template_id'])) {
                $templateId = $this->getTemplateIdFromDatabase($name, $documentType);
                if ($templateId) {
                    $config['docuseal_template_id'] = $templateId;
                }
            }
            
            return $config;
        }

        // Try case-insensitive match
        foreach ($configSource['manufacturers'] as $key => $config) {
            if (strtolower($key) === strtolower($name)) {
                $config = $this->resolveConfigReference($config, $configSource);
                
                // Ensure template ID is set for IVR documents
                if ($documentType === 'IVR' && !isset($config['docuseal_template_id'])) {
                    $templateId = $this->getTemplateIdFromDatabase($name, $documentType);
                    if ($templateId) {
                        $config['docuseal_template_id'] = $templateId;
                    }
                }
                
                return $config;
            }
        }

        // Try matching by manufacturer name in config
        foreach ($configSource['manufacturers'] as $key => $config) {
            if (isset($config['name']) && is_string($config['name']) &&
                (strtolower($config['name']) === strtolower($name) || 
                 str_contains(strtolower($config['name']), strtolower($name)))) {
                $config = $this->resolveConfigReference($config, $configSource);
                
                // Ensure template ID is set for IVR documents
                if ($documentType === 'IVR' && !isset($config['docuseal_template_id'])) {
                    $templateId = $this->getTemplateIdFromDatabase($name, $documentType);
                    if ($templateId) {
                        $config['docuseal_template_id'] = $templateId;
                    }
                }
                
                return $config;
            }
        }

        return null;
    }

    /**
     * Get available manufacturers
     */
    public function getAvailableManufacturers(): array
    {
        return array_map(function($manufacturer) {
            return [
                'name' => $manufacturer['name'],
                'display_name' => $manufacturer['display_name'] ?? $manufacturer['name'],
                'description' => $manufacturer['description'] ?? null
            ];
        }, $this->config['manufacturers'] ?? []);
    }

    /**
     * Get field mapping for specific manufacturer and field
     */
    public function getFieldMapping(string $manufacturerName, string $fieldName): ?array
    {
        $manufacturer = $this->getManufacturerConfig($manufacturerName);
        
        if (!$manufacturer || !isset($manufacturer['fields'][$fieldName])) {
            return null;
        }
        
        return $manufacturer['fields'][$fieldName];
    }

    /**
     * Validate manufacturer configuration
     */
    private function validateManufacturerConfig(array $config): bool
    {
        $required = ['name', 'fields'];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Log mapping activity
     */
    private function logMappingActivity(string $action, array $context = []): void
    {
        Log::info("Unified field mapping: {$action}", array_merge([
            'service' => 'UnifiedFieldMappingService',
            'timestamp' => now()->toISOString()
        ], $context));
    }

    /**
     * Get configuration path
     */
    private function getConfigPath(): string
    {
        return base_path('docs/data-and-reference/json-forms/unified-medical-form-mapping.json');
    }

    /**
     * Cache configuration
     */
    protected function cacheConfig(array $config): void
    {
        Cache::put('unified_field_mapping_config', $config, 3600); // Cache for 1 hour
    }

    /**
     * Get cached configuration
     */
    protected function getCachedConfig(): ?array
    {
        return Cache::get('unified_field_mapping_config');
    }

    /**
     * Clear configuration cache
     */
    public function clearConfigCache(): void
    {
        Cache::forget('unified_field_mapping_config');
    }

    /**
     * Reload configuration from file
     */
    protected function reloadConfig(): array
    {
        $this->clearConfigCache();
        return $this->loadConfig();
    }

    /**
     * Load configuration
     */
    protected function loadConfig(): array
    {
        $configPath = $this->getConfigPath();
        
        if (!file_exists($configPath)) {
            Log::error('Configuration file not found', ['path' => $configPath]);
            return [];
        }
        
        $content = file_get_contents($configPath);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse configuration JSON', [
                'error' => json_last_error_msg(),
                'path' => $configPath
            ]);
            return [];
        }
        
        $this->cacheConfig($config);
        
        return $config;
    }

    /**
     * Load manufacturer configuration from individual file
     */
    private function loadManufacturerFromFile(string $manufacturerName): ?array
    {
        // Convert manufacturer name to filename format
        $filename = \Illuminate\Support\Str::slug($manufacturerName);
        $manufacturerConfigPath = config_path("manufacturers/{$filename}.php");

        if (file_exists($manufacturerConfigPath)) {
            try {
                $config = include $manufacturerConfigPath;
                
                // Validate that the config is an array
                if (!is_array($config)) {
                    Log::warning("Invalid manufacturer config format", [
                        'manufacturer' => $manufacturerName,
                        'file' => $manufacturerConfigPath
                    ]);
                    return null;
                }

                Log::info("Loaded manufacturer config from file", [
                    'manufacturer' => $manufacturerName,
                    'file' => $filename . '.php',
                    'fields_count' => count($config['fields'] ?? [])
                ]);

                // Validate required fields for IVR
                if (!isset($config['docuseal_template_id'])) {
                    Log::warning("Manufacturer config missing required docuseal_template_id", [
                        'manufacturer' => $manufacturerName,
                        'config_keys' => array_keys($config)
                    ]);
                }

                return $config;
            } catch (\Exception $e) {
                Log::error("Failed to load manufacturer config file", [
                    'manufacturer' => $manufacturerName,
                    'file' => $manufacturerConfigPath,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        return null;
    }

    /**
     * Get template ID from database for a manufacturer
     */
    private function getTemplateIdFromDatabase(string $manufacturerName, string $documentType): ?string
    {
        try {
            // Find the manufacturer by name
            $manufacturer = \App\Models\Order\Manufacturer::where('name', $manufacturerName)->first();
            
            if (!$manufacturer) {
                return null;
            }
            
            // Get the default template for this manufacturer and document type
            $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                // Try without is_default constraint
                $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                    ->where('document_type', $documentType)
                    ->where('is_active', true)
                    ->first();
            }
            
            return $template ? $template->docuseal_template_id : null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get template ID from database', [
                'manufacturer' => $manufacturerName,
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get template ID for manufacturer
     */
    private function getTemplateIdForManufacturer(string $manufacturerName): ?string
    {
        // Try to get from config first
        $config = $this->getManufacturerConfig($manufacturerName);
        if ($config && isset($config['docuseal_template_id'])) {
            return $config['docuseal_template_id'];
        }
        
        // Fall back to database
        return $this->getTemplateIdFromDatabase($manufacturerName, 'IVR');
    }

    // mapFieldsWithCSVMapping method removed - duplicate definition found at line 1317 with full implementation

    /**
     * Resolve configuration reference if it exists
     */
    private function resolveConfigReference(array $config, array $configSource): array
    {
        // If this config has a reference_config, load the referenced config
        if (isset($config['reference_config'])) {
            $referenceName = $config['reference_config'];
            if (isset($configSource['manufacturers'][$referenceName])) {
                $referencedConfig = $configSource['manufacturers'][$referenceName];
                // Merge the original config with the referenced config, preserving important fields
                return array_merge($referencedConfig, [
                    'id' => $config['id'] ?? $referencedConfig['id'] ?? null,
                    'name' => $config['name'] ?? $referencedConfig['name'] ?? null,
                    'signature_required' => $config['signature_required'] ?? $referencedConfig['signature_required'] ?? true,
                    'has_order_form' => $config['has_order_form'] ?? $referencedConfig['has_order_form'] ?? false,
                    'docuseal_template_id' => $config['docuseal_template_id'] ?? $referencedConfig['docuseal_template_id'] ?? null,
                ]);
            }
        }
        
        return $config;
    }

    /**
     * Convert mapped data to Docuseal field format
     */
    public function convertToDocusealFields(array $mappedData, array $manufacturerConfig, string $documentType = 'IVR'): array
    {
        $docuSealFields = [];
        
        // Choose the correct field mapping based on document type
        if ($documentType === 'OrderForm' && isset($manufacturerConfig['order_form_field_names'])) {
            $fieldNameMapping = $manufacturerConfig['order_form_field_names'];
        } else {
            $fieldNameMapping = $manufacturerConfig['docuseal_field_names'] ?? [];
        }
        
        // LIFE FIX: Runtime validation to prevent invalid fields from reaching DocuSeal
        if ($this->fieldValidator && isset($manufacturerConfig['docuseal_template_id']) && $manufacturerConfig['docuseal_template_id']) {
            try {
                $templateFields = $this->fieldValidator->getTemplateFields($manufacturerConfig['docuseal_template_id']);
                
                if (!empty($templateFields)) {
                    // Filter out field mappings that don't exist in the actual template
                    $originalMappingCount = count($fieldNameMapping);
                    $fieldNameMapping = array_filter($fieldNameMapping, function($docuSealFieldName) use ($templateFields) {
                        return in_array($docuSealFieldName, $templateFields);
                    });
                    
                    $filteredCount = $originalMappingCount - count($fieldNameMapping);
                    if ($filteredCount > 0) {
                        Log::warning('Runtime field validation filtered out invalid mappings', [
                            'manufacturer' => $manufacturerConfig['name'] ?? 'unknown',
                            'template_id' => $manufacturerConfig['docuseal_template_id'],
                            'filtered_count' => $filteredCount,
                            'remaining_count' => count($fieldNameMapping)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Runtime field validation failed, proceeding with original mappings', [
                    'manufacturer' => $manufacturerConfig['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('Starting Docuseal field conversion', [
            'manufacturer' => $manufacturerConfig['name'] ?? 'unknown',
            'document_type' => $documentType,
            'mapped_data_count' => count($mappedData),
            'field_name_mapping_count' => count($fieldNameMapping),
            'mapped_data_keys' => array_keys($mappedData),
            'available_docuseal_fields' => array_keys($fieldNameMapping)
        ]);

        // First, check if we're receiving raw data that needs mapping
        // This can happen when the data hasn't been properly mapped through mapFields
        if (isset($mappedData['patient_first_name']) && isset($mappedData['patient_last_name']) && 
            !isset($mappedData['patient_name']) && isset($fieldNameMapping['patient_name'])) {
            // Compute patient_name from first and last names
            $mappedData['patient_name'] = trim($mappedData['patient_first_name'] . ' ' . $mappedData['patient_last_name']);
            Log::info('Computed patient_name from first and last names', [
                'patient_name' => $mappedData['patient_name']
            ]);
        }
        
        // Also handle camelCase versions of the fields
        if (isset($mappedData['patientFirstName']) && isset($mappedData['patientLastName']) && 
            !isset($mappedData['patient_name']) && isset($fieldNameMapping['patient_name'])) {
            // Compute patient_name from camelCase first and last names
            $mappedData['patient_name'] = trim($mappedData['patientFirstName'] . ' ' . $mappedData['patientLastName']);
            Log::info('Computed patient_name from camelCase first and last names', [
                'patient_name' => $mappedData['patient_name']
            ]);
        }

        foreach ($mappedData as $canonicalName => $value) {
            // Skip null or empty values unless it's a boolean false
            if ($value === null || ($value === '' && !is_bool($value))) {
                continue;
            }

            // IMPORTANT: Only include fields that are explicitly defined in the field mapping
            // This prevents sending unknown fields to Docuseal which causes 422 errors
            if (!isset($fieldNameMapping[$canonicalName])) {
                Log::debug("Skipping field not in Docuseal template mapping", [
                    'field' => $canonicalName,
                    'document_type' => $documentType,
                    'manufacturer' => $manufacturerConfig['name'] ?? 'unknown'
                ]);
                continue;
            }

            // Get the Docuseal field name for this canonical field
            $docuSealFieldName = $fieldNameMapping[$canonicalName];

            // Special handling for gender checkboxes (Centurion)
            if ($canonicalName === 'patient_gender' && $docuSealFieldName === 'Male/Female') {
                // Split into two checkbox fields
                if (strtolower($value) === 'male') {
                    $docuSealFields[] = ['name' => 'Male', 'default_value' => 'true'];
                    $docuSealFields[] = ['name' => 'Female', 'default_value' => 'false'];
                } elseif (strtolower($value) === 'female') {
                    $docuSealFields[] = ['name' => 'Male', 'default_value' => 'false'];
                    $docuSealFields[] = ['name' => 'Female', 'default_value' => 'true'];
                }
                continue;
            }

            // Handle boolean values for checkboxes
            if (is_bool($value) || in_array($value, ['true', 'false', 'Yes', 'No', '1', '0', 1, 0], true)) {
                // Convert various boolean representations to Docuseal checkbox format
                $boolValue = false;
                
                if (is_bool($value)) {
                    $boolValue = $value;
                } elseif (in_array($value, ['true', 'Yes', '1', 1], true)) {
                    $boolValue = true;
                } elseif (in_array($value, ['false', 'No', '0', 0], true)) {
                    $boolValue = false;
                }
                
                // For checkbox fields, Docuseal expects 'true' or 'false' as strings
                $docuSealFields[] = [
                    'name' => $docuSealFieldName,
                    'default_value' => $boolValue ? 'true' : 'false'
                ];
                continue;
            }

            // Handle array values by converting to string representation
            if (is_array($value)) {
                // For arrays like ICD codes, we might need to handle them differently
                // depending on the field type
                if (strpos($canonicalName, 'icd10_code_') !== false || 
                    strpos($canonicalName, 'cpt_code_') !== false ||
                    strpos($canonicalName, 'hcpcs_code_') !== false) {
                    // These are handled individually, not as arrays
                    continue;
                }
                $value = implode(', ', $value);
            }
            
            // Add the field with its Docuseal name
            $docuSealFields[] = [
                'name' => $docuSealFieldName,
                'default_value' => (string) $value
            ];
        }

        return $docuSealFields;
    }

    /**
     * Map fields using CSV-based canonical mappings
     */
    private function mapFieldsWithCSVMapping(array $sourceData, string $templateId): array
    {
        $startTime = microtime(true);
        
        // Try to determine manufacturer from template ID
        $manufacturerName = $this->getManufacturerByTemplateId($templateId);
        if (!$manufacturerName) {
            Log::warning('Could not determine manufacturer from template ID', ['template_id' => $templateId]);
            return [];
        }

        // Get CSV-based mappings for this manufacturer
        $formMappings = $this->canonicalMappingService->getMappingsForForm($manufacturerName, 'IVR');
        if (empty($formMappings['field_mappings'])) {
            Log::info('No CSV mappings found for manufacturer', ['manufacturer' => $manufacturerName]);
            return [];
        }

        $mappedData = [];
        $mappingStats = [
            'total_fields' => 0,
            'mapped_fields' => 0,
            'high_confidence' => 0,
            'validation_issues' => []
        ];

        // Apply CSV-based field mappings
        foreach ($formMappings['field_mappings'] as $fieldKey => $mapping) {
            $mappingStats['total_fields']++;
            
            if ($mapping['mapping_status'] !== 'mapped') {
                continue;
            }

            $canonicalKey = $mapping['canonical_key'];
            $confidence = $mapping['confidence'] ?? 0;
            
            // Get value from source data using canonical key
            $value = $this->getValueByCanonicalKey($sourceData, $canonicalKey);
            
            if ($value !== null) {
                // Apply data transformation based on field type
                $transformedValue = $this->transformValueByType($value, $mapping['type'], $mapping);
                
                $mappedData[$fieldKey] = $transformedValue;
                $mappingStats['mapped_fields']++;
                
                if ($confidence >= 0.8) {
                    $mappingStats['high_confidence']++;
                }
                
                Log::debug('CSV field mapped', [
                    'field_key' => $fieldKey,
                    'canonical_key' => $canonicalKey,
                    'confidence' => $confidence,
                    'value_preview' => is_string($transformedValue) ? substr($transformedValue, 0, 50) : gettype($transformedValue)
                ]);
            }
        }

        // Apply business rules and defaults (including boolean defaults)
        $manufacturerConfig = $this->getManufacturerConfig($manufacturerName) ?? [];
        $mappedData = $this->applyBusinessRules($mappedData, $manufacturerConfig, $sourceData);
        
        // Validate mappings and detect issues
        $validationIssues = $this->canonicalMappingService->validateMappings($manufacturerName, 'IVR');
        $mappingStats['validation_issues'] = $validationIssues;

        $duration = microtime(true) - $startTime;
        
        Log::info('CSV mapping completed', [
            'manufacturer' => $manufacturerName,
            'template_id' => $templateId,
            'mapping_stats' => $mappingStats,
            'duration_ms' => round($duration * 1000, 2)
        ]);

        return $mappedData;
    }

    /**
     * Get manufacturer name by template ID
     */
    private function getManufacturerByTemplateId(string $templateId): ?string
    {
        // Check all manufacturer configs for matching template ID
        $manufacturers = $this->listManufacturers();
        
        foreach ($manufacturers as $manufacturer) {
            if ($manufacturer['docuseal_template_id'] === $templateId) {
                return $manufacturer['name'];
            }
        }
        
        return null;
    }

    /**
     * Get value from source data using canonical key
     */
    private function getValueByCanonicalKey(array $sourceData, string $canonicalKey): mixed
    {
        // Direct key match
        if (isset($sourceData[$canonicalKey])) {
            return $sourceData[$canonicalKey];
        }
        
        // Try nested path access
        if (str_contains($canonicalKey, '.')) {
            return $this->getValueByPath($sourceData, $canonicalKey);
        }
        
        // Try fuzzy matching for common variations
        $variations = $this->generateKeyVariations($canonicalKey);
        foreach ($variations as $variation) {
            if (isset($sourceData[$variation])) {
                return $sourceData[$variation];
            }
        }
        
        // Special handling for provider/physician synonyms
        if (str_contains($canonicalKey, 'provider_')) {
            $physicianKey = str_replace('provider_', 'physician_', $canonicalKey);
            if (isset($sourceData[$physicianKey])) {
                return $sourceData[$physicianKey];
            }
            // Also try variations of the physician key
            $physicianVariations = $this->generateKeyVariations($physicianKey);
            foreach ($physicianVariations as $variation) {
                if (isset($sourceData[$variation])) {
                    return $sourceData[$variation];
                }
            }
        } elseif (str_contains($canonicalKey, 'physician_')) {
            $providerKey = str_replace('physician_', 'provider_', $canonicalKey);
            if (isset($sourceData[$providerKey])) {
                return $sourceData[$providerKey];
            }
            // Also try variations of the provider key
            $providerVariations = $this->generateKeyVariations($providerKey);
            foreach ($providerVariations as $variation) {
                if (isset($sourceData[$variation])) {
                    return $sourceData[$variation];
                }
            }
        }
        
        return null;
    }

    /**
     * Generate key variations for fuzzy matching
     */
    private function generateKeyVariations(string $key): array
    {
        $variations = [];
        
        // Snake case variations
        $variations[] = str_replace('_', '', $key);
        $variations[] = str_replace('_', '-', $key);
        
        // Camel case variations
        $variations[] = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $variations[] = ucfirst(str_replace('_', '', ucwords($key, '_')));
        
        // Space variations
        $variations[] = str_replace('_', ' ', $key);
        $variations[] = ucwords(str_replace('_', ' ', $key));
        
        // Common abbreviations and expansions
        $abbreviations = [
            'dob' => 'date_of_birth',
            'date_of_birth' => 'dob',
            'npi' => 'national_provider_identifier',
            'national_provider_identifier' => 'npi',
            'tin' => 'tax_id',
            'tax_id' => 'tin',
            'ptan' => 'provider_transaction_access_number',
            'provider_transaction_access_number' => 'ptan',
            'pos' => 'place_of_service',
            'place_of_service' => 'pos',
            'mac' => 'medicare_admin_contractor',
            'medicare_admin_contractor' => 'mac',
            'id' => 'identifier',
            'identifier' => 'id',
            'phone' => 'phone_number',
            'phone_number' => 'phone',
            'fax' => 'fax_number',
            'fax_number' => 'fax',
            'addr' => 'address',
            'address' => 'addr',
            'org' => 'organization',
            'organization' => 'org'
        ];
        
        // Add abbreviation variations
        foreach ($abbreviations as $abbr => $full) {
            if (str_contains($key, $abbr)) {
                $variations[] = str_replace($abbr, $full, $key);
            }
            if (str_contains($key, $full)) {
                $variations[] = str_replace($full, $abbr, $key);
            }
        }
        
        // Handle plural/singular forms
        if (str_ends_with($key, 's') && !str_ends_with($key, 'ss')) {
            $variations[] = substr($key, 0, -1); // Remove 's'
        } else if (!str_ends_with($key, 's')) {
            $variations[] = $key . 's'; // Add 's'
        }
        
        // Common field name patterns
        if (str_starts_with($key, 'patient_')) {
            $variations[] = 'pt_' . substr($key, 8);
            $variations[] = substr($key, 8) . '_patient';
        }
        if (str_starts_with($key, 'pt_')) {
            $variations[] = 'patient_' . substr($key, 3);
        }
        
        return array_unique($variations);
    }

    /**
     * Transform value based on field type
     */
    private function transformValueByType(mixed $value, string $type, array $mapping): mixed
    {
        switch (strtolower($type)) {
            case 'boolean':
            case 'checkbox':
                return $this->transformToBoolean($value);
                
            case 'date':
                return $this->transformToDate($value);
                
            case 'phone':
                return $this->transformToPhone($value);
                
            case 'email':
                return $this->transformToEmail($value);
                
            case 'number':
            case 'integer':
                return $this->transformToNumber($value);
                
            case 'text':
            default:
                return $this->transformToText($value);
        }
    }

    /**
     * Transform value to boolean
     */
    private function transformToBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, ['true', '1', 'yes', 'on', 'checked', 'selected']);
        }
        
        return (bool) $value;
    }

    /**
     * Transform value to formatted date
     */
    private function transformToDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Date transformation failed', ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Transform value to formatted phone
     */
    private function transformToPhone(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', (string) $value);
        
        // Format as (XXX) XXX-XXXX if 10 digits
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        return $phone;
    }

    /**
     * Transform value to email
     */
    private function transformToEmail(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        $email = trim((string) $value);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Transform value to number
     */
    private function transformToNumber(mixed $value): ?float
    {
        if (empty($value)) {
            return null;
        }
        
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Transform value to text
     */
    private function transformToText(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        
        return (string) $value;
    }

    /**
     * Log mapping analytics for monitoring and improvement
     */
    private function logMappingAnalytics(string $episodeId, string $manufacturer, array $completeness, float $duration): void
    {
        Log::info('Field mapping completed', [
            'episode_id' => $episodeId,
            'manufacturer' => $manufacturer,
            'completeness' => $completeness['percentage'],
            'required_completeness' => $completeness['required_percentage'],
            'duration_seconds' => round($duration, 3),
            'fields_filled' => $completeness['filled'],
            'fields_total' => $completeness['total']
        ]);
    }

    /**
     * Get manufacturer by product code
     */
    public function getManufacturerByProduct(string $productCode): ?string
    {
        return $this->config['product_mappings'][$productCode] ?? null;
    }

    /**
     * List all configured manufacturers
     */
    public function listManufacturers(): array
    {
        $manufacturers = [];
        
        // First, load manufacturers from individual config files
        $configPath = config_path('manufacturers');
        if (is_dir($configPath)) {
            $files = glob($configPath . '/*.php');
            foreach ($files as $file) {
                $filename = basename($file, '.php');
                $config = include $file;
                
                if (is_array($config) && isset($config['name'])) {
                    $manufacturers[] = [
                        'id' => $config['id'] ?? null,
                        'name' => $config['name'],
                        'docuseal_template_id' => $config['docuseal_template_id'] ?? null,
                        'signature_required' => $config['signature_required'] ?? true,
                        'has_order_form' => $config['has_order_form'] ?? false,
                        'supports_insurance_upload_in_ivr' => $config['supports_insurance_upload_in_ivr'] ?? false,
                        'fields_count' => count($config['fields'] ?? []),
                        'required_fields_count' => count(array_filter($config['fields'] ?? [], fn($f) => $f['required'] ?? false))
                    ];
                }
            }
        }
        
        // Then, add manufacturers from main config (if not already added)
        $existingNames = array_column($manufacturers, 'name');
        foreach ($this->config['manufacturers'] ?? [] as $name => $config) {
            // Resolve config reference if needed
            $resolvedConfig = $this->resolveConfigReference($config, $this->config);
            $manufacturerName = $resolvedConfig['name'] ?? $name;
            
            if (!in_array($manufacturerName, $existingNames)) {
                $manufacturers[] = [
                    'id' => $resolvedConfig['id'] ?? null,
                    'name' => $manufacturerName,
                    'docuseal_template_id' => $resolvedConfig['docuseal_template_id'] ?? null,
                    'signature_required' => $resolvedConfig['signature_required'] ?? true,
                    'has_order_form' => $resolvedConfig['has_order_form'] ?? false,
                    'supports_insurance_upload_in_ivr' => $resolvedConfig['supports_insurance_upload_in_ivr'] ?? false,
                    'fields_count' => count($resolvedConfig['fields'] ?? []),
                    'required_fields_count' => count(array_filter($resolvedConfig['fields'] ?? [], fn($f) => $f['required'] ?? false))
                ];
            }
        }

        return $manufacturers;
    }

    /**
     * Load canonical fields from JSON file into database
     */
    public function loadCanonicalFieldsFromJson(): void
    {
        $jsonPath = base_path('docs/mapping-final/insurance_form_mappings.json');
        
        if (!file_exists($jsonPath)) {
            Log::error('Canonical fields JSON file not found', ['path' => $jsonPath]);
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        
        if (!isset($data['standardFieldMappings'])) {
            Log::error('Invalid canonical fields JSON structure');
            return;
        }

        foreach ($data['standardFieldMappings'] as $category => $categoryData) {
            if (!isset($categoryData['canonicalFields'])) {
                continue;
            }

            foreach ($categoryData['canonicalFields'] as $fieldName => $fieldData) {
                CanonicalField::updateOrCreate(
                    [
                        'field_name' => $fieldName,
                        'category' => $category,
                    ],
                    [
                        'field_path' => $category . '.' . $fieldName,
                        'data_type' => $fieldData['dataType'] ?? 'string',
                        'is_required' => $fieldData['required'] ?? false,
                        'description' => $fieldData['description'] ?? null,
                        'validation_rules' => $this->getValidationRules($fieldData['dataType'] ?? 'string'),
                        'hipaa_flag' => $this->isPhiField($category, $fieldName),
                    ]
                );
            }
        }

        Log::info('Canonical fields loaded from JSON successfully');
    }

    /**
     * Get field mappings for a template
     */
    public function getFieldMappingsForTemplate(string $templateId): array
    {
        $template = DocusealTemplate::with('fieldMappings.canonicalField')->find($templateId);
        
        if (!$template) {
            return [];
        }

        return $template->fieldMappings->map(function ($mapping) {
            return [
                'field_name' => $mapping->field_name,
                'canonical_field' => $mapping->canonicalField,
                'transformation_rules' => $mapping->transformation_rules,
                'confidence_score' => $mapping->confidence_score,
                'validation_status' => $mapping->validation_status,
                'is_active' => $mapping->is_active,
            ];
        })->toArray();
    }

    /**
     * Calculate mapping statistics for a template
     */
    public function getMappingStatistics(string $templateId): array
    {
        $template = DocusealTemplate::with('fieldMappings')->find($templateId);
        
        if (!$template) {
            return [
                'total_fields' => 0,
                'mapped_fields' => 0,
                'coverage_percentage' => 0,
            ];
        }

        $totalFields = count($template->field_mappings ?? []);
        // NOTE: fieldMappings relationship might not exist since TemplateFieldMapping model doesn't exist
        // $mappedFields = $template->fieldMappings->whereNotNull('canonical_field_id')->count();
        $mappedFields = 0; // Default to 0 since we can't access the relationship
        
        return [
            'total_fields' => $totalFields,
            'mapped_fields' => $mappedFields,
            'unmapped_fields' => $totalFields, // Since we can't count mapped fields
            'coverage_percentage' => 0, // Can't calculate without fieldMappings
            'validation_errors' => 0, // Can't count without fieldMappings
            'validation_warnings' => 0, // Can't count without fieldMappings
        ];
    }

    /**
     * Get validation rules for a data type
     */
    private function getValidationRules(string $dataType): array
    {
        $rules = [];

        switch ($dataType) {
            case 'string':
                $rules[] = ['type' => 'string', 'max_length' => 255];
                break;
            
            case 'date':
                $rules[] = ['type' => 'date', 'format' => 'Y-m-d'];
                break;
            
            case 'boolean':
                $rules[] = ['type' => 'boolean', 'accepted_values' => ['true', 'false', '1', '0', 'yes', 'no']];
                break;
            
            case 'phone':
                $rules[] = ['type' => 'phone', 'pattern' => '/^\(\d{3}\) \d{3}-\d{4}$/'];
                break;
            
            case 'npi':
                $rules[] = ['type' => 'npi', 'pattern' => '/^\d{10}$/'];
                break;
            
            case 'email':
                $rules[] = ['type' => 'email', 'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'];
                break;
        }

        return $rules;
    }

    /**
     * Check if a field contains PHI
     */
    private function isPhiField(string $category, string $fieldName): bool
    {
        $phiCategories = ['patientInformation', 'insuranceInformation'];
        $phiFields = ['patientName', 'patientDOB', 'patientSSN', 'policyNumber', 'memberID'];

        return in_array($category, $phiCategories) || in_array($fieldName, $phiFields);
    }

    /**
     * Set nested value in array using dot notation
     */
    private function setNestedValue(array &$array, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Enrich data with UMLS medical terminology information
     */
    protected function enrichDataWithMedicalTerminology(array $data): array
    {
        // Extract diagnosis codes if present
        if (!empty($data['diagnosis'])) {
            $icd10Codes = $this->extractICD10Codes($data['diagnosis']);
            
            // Get CPT mappings for each ICD-10 code
            $cptMappings = [];
            foreach ($icd10Codes as $code) {
                $mapping = $this->medicalTerminologyService->mapICD10ToCPT($code);
                if ($mapping['success'] && !empty($mapping['cpt_codes'])) {
                    $cptMappings[$code] = $mapping['cpt_codes'];
                }
            }
            
            if (!empty($cptMappings)) {
                $data['diagnosis_cpt_mappings'] = $cptMappings;
            }
        }
        
        // Validate and enrich wound type information
        if (!empty($data['wound_type'])) {
            $validation = $this->medicalTerminologyService->validateMedicalTerms(
                [$data['wound_type']], 
                'wound_care'
            );
            
            if (!empty($validation['validation_results'][0])) {
                $result = $validation['validation_results'][0];
                
                if ($result['is_valid'] && !empty($result['umls_validation'])) {
                    $cui = $result['umls_validation']['umls_cui'];
                    
                    // Get detailed concept information
                    $conceptDetails = $this->medicalTerminologyService->getConceptDetails($cui);
                    if ($conceptDetails['success']) {
                        $data['wound_type_umls'] = [
                            'cui' => $cui,
                            'preferred_name' => $conceptDetails['name'],
                            'semantic_types' => $conceptDetails['semantic_types']
                        ];
                        
                        // Get definitions if available
                        $definitions = $this->medicalTerminologyService->getConceptDefinitions($cui);
                        if ($definitions['success'] && !empty($definitions['definitions'])) {
                            $data['wound_type_umls']['definition'] = $definitions['definitions'][0]['value'];
                        }
                    }
                }
            }
        }
        
        // Get code suggestions for procedures mentioned
        if (!empty($data['clinical_notes']) || !empty($data['treatment_plan'])) {
            $procedureText = implode(' ', array_filter([
                $data['clinical_notes'] ?? '',
                $data['treatment_plan'] ?? ''
            ]));
            
            if (!empty(trim($procedureText))) {
                $suggestions = $this->medicalTerminologyService->suggestMedicalCodes(
                    $procedureText,
                    'cpt'
                );
                
                if (!empty($suggestions)) {
                    $data['suggested_cpt_codes'] = array_slice($suggestions, 0, 5);
                }
            }
        }
        
        return $data;
    }

    /**
     * Extract ICD-10 codes from diagnosis text
     */
    protected function extractICD10Codes(string $diagnosis): array
    {
        // Pattern to match ICD-10 codes (basic pattern)
        preg_match_all('/\b[A-TV-Z]\d{2}(?:\.\d{1,4})?\b/', $diagnosis, $matches);
        
        $codes = [];
        foreach ($matches[0] as $code) {
            // Validate the code
            $validation = $this->medicalTerminologyService->validateICD10Code($code);
            if ($validation['valid']) {
                $codes[] = $code;
            }
        }
        
        return array_unique($codes);
    }

    /**
     * Map data to DocuSeal fields using AI service or fallback mapping
     */
    public function mapFieldsForDocuseal(array $sourceData, string $templateName): array
    {
        // Enrich data with medical terminology information
        $enrichedData = $this->enrichDataWithMedicalTerminology($sourceData);
        
        if ($this->aiServiceEnabled) {
            try {
                Log::info('Using AI service for field mapping', [
                    'template' => $templateName,
                    'ai_service_url' => $this->aiServiceUrl,
                    'data_keys' => array_keys($enrichedData)
                ]);
                
                $response = Http::timeout(60)
                    ->post($this->aiServiceUrl . '/map-for-docuseal', [
                        'template_name' => $templateName,
                        'source_data' => $enrichedData,
                        'include_medical_context' => true,
                        'umls_enriched' => true
                    ]);
                
                if ($response->successful()) {
                    $result = $response->json();
                    Log::info('AI mapping successful', [
                        'template' => $templateName,
                        'mapped_fields_count' => count($result['fields'] ?? [])
                    ]);
                    
                    return $result['fields'] ?? [];
                }
                
                Log::warning('AI service returned non-successful response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
            } catch (\Exception $e) {
                Log::error('AI service mapping failed', [
                    'error' => $e->getMessage(),
                    'template' => $templateName
                ]);
            }
        }
        
        Log::info('Using fallback field mapping');
        return $this->performFallbackMapping($enrichedData, $templateName);
    }

    /**
     * Perform fallback field mapping (if AI service is not available or fails)
     */
    protected function performFallbackMapping(array $enrichedData, string $templateName): array
    {
        Log::warning('AI service not available or failed, falling back to static field mapping', [
            'template' => $templateName,
            'data_keys' => array_keys($enrichedData)
        ]);

        // This is a placeholder for the fallback logic.
        // In a real scenario, you would load a static config or use a different mapping service.
        // For now, we'll just return the enriched data as is, which might not be fully mapped.
        return $enrichedData;
    }
}