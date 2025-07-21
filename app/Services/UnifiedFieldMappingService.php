<?php

namespace App\Services;

use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;

use App\Services\DocuSeal\TemplateFieldValidationService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\CanonicalField;
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
        private ?TemplateFieldValidationService $fieldValidator = null
    ) {
        $this->fhirService = $fhirService;
        $this->medicalTerminologyService = $medicalTerminologyService;
        $this->aiServiceUrl = config('services.medical_base_ai.base_url');
        $this->aiServiceEnabled = !empty($this->aiServiceUrl);
        
                // Initialize configs directly since we're using manufacturer-specific configs
        $this->config = [];
        $this->orderFormConfig = [];

        // Set default structure for configs
        $this->config = [
            'transformations' => [],
            'validation' => [],
            'defaults' => [],
            'manufacturers' => []
        ];

        $this->orderFormConfig = [
            'templates' => [],
            'field_mapping' => []
        ];
        
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
                        $result = $aiService->enhanceDocusealFieldMapping($episode, $additionalData, $templateId);
                    
                        Log::info('AI mapping completed successfully', [
                            'episode_id' => $episodeId,
                            'manufacturer' => $manufacturerName,
                            'template_id' => $templateId,
                            'ai_confidence' => $result['_ai_confidence'] ?? 0
                        ]);
                        
                        // Convert to expected format
                        return [
                            'data' => $result,
                            'validation' => ['quality_grade' => 'ai_enhanced'],
                            'manufacturer' => ['name' => $manufacturerName],
                            'completeness' => ['percentage' => 100],
                            'metadata' => [
                                'episode_id' => $episodeId,
                                'manufacturer' => $manufacturerName,
                                'mapped_at' => now()->toIso8601String(),
                                'source' => 'ai_enhanced'
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
     * Main entry point for all field mapping needs (original static method)
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

            // 2. Get manufacturer configuration based on document type
            $manufacturerConfig = $this->getManufacturerConfig($manufacturerName, $documentType);
            if (!$manufacturerConfig) {
                throw new \InvalidArgumentException("Unknown manufacturer: {$manufacturerName} for document type: {$documentType}");
            }

            // 3. Map fields according to configuration
            $mappedData = $this->mapFields($sourceData, $manufacturerConfig['fields']);

            // 4. Apply manufacturer-specific business rules
            $mappedData = $this->applyBusinessRules($mappedData, $manufacturerConfig, $sourceData);

            // 5. Validate mapped data
            $validation = $this->validateMapping($mappedData, $manufacturerConfig);

            // 6. Calculate completeness
            $completeness = $this->calculateCompleteness($mappedData, $manufacturerConfig);

            // 7. Log mapping analytics
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

        // Handle concatenation (field1 + field2)
        if (str_contains($computation, ' + ')) {
            $parts = array_map('trim', explode(' + ', $computation));
            $values = array_map(fn($part) => $this->getValueByPath($data, $part), $parts);
            return implode(' ', array_filter($values));
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
    private function applyBusinessRules(array $data, array $config, array $sourceData): array
    {
        $manufacturerName = $config['name'];

        // Apply duration requirements from configuration
        if (isset($config['duration_requirement'])) {
            if ($config['duration_requirement'] === 'greater_than_4_weeks') {
                $weeks = (int)($sourceData['wound_duration_weeks'] ?? 0);
                if ($weeks <= 4) {
                    Log::warning("Manufacturer requires wound duration > 4 weeks", [
                        'manufacturer' => $manufacturerName,
                        'actual_weeks' => $weeks,
                        'episode_id' => $sourceData['episode_id'] ?? null
                    ]);
                    // Add a flag or warning to the data
                    $data['_warnings'] = ($data['_warnings'] ?? []);
                    $data['_warnings'][] = 'Wound duration does not meet manufacturer requirement of > 4 weeks';
                }
            }
        }

        // Advanced Health specific rules
        if ($manufacturerName === 'Advanced Health') {
            // Example: Advanced Health might require specific formatting for certain fields
            if (isset($data['wound_type'])) {
                $data['wound_type'] = str_replace('_', ' ', ucwords($data['wound_type']));
            }
        }

        // Add more manufacturer-specific rules as needed
        
        return $data;
    }

    /**
     * Validate mapped data against manufacturer requirements
     * Uses intelligent validation that adapts to different IVR form requirements
     */
    private function validateMapping(array $data, array $config): array
    {
        $errors = [];
        $warnings = [];
        $criticalErrors = [];
        $missingOptionalFields = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            $isRequired = $fieldConfig['required'] ?? false;
            $fieldValue = $data[$field] ?? null;
            $isEmpty = empty($fieldValue) && $fieldValue !== '0' && $fieldValue !== 0;

            // Check required fields - but be intelligent about it
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
        // Most IVR forms can work with missing non-critical fields
        $isValid = empty($criticalErrors);
        
        // If we have too many warnings, suggest using AI enhancement
        if (count($warnings) > 5 && class_exists('\App\Services\Medical\OptimizedMedicalAiService')) {
            $warnings[] = "Consider using AI-enhanced field mapping for better results";
        }

        Log::info('Unified field mapping validation completed', [
            'is_valid' => $isValid,
            'critical_errors' => count($criticalErrors),
            'warnings' => count($warnings),
            'missing_optional' => count($missingOptionalFields),
            'total_fields' => count($config['fields']),
            'provided_fields' => count(array_filter($data, fn($v) => !empty($v) || $v === '0' || $v === 0))
        ]);

        return [
            'valid' => $isValid,
            'errors' => array_merge($criticalErrors, $errors), // Put critical errors first
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
            $isFilled = !empty($data[$field]) || $data[$field] === '0' || $data[$field] === 0;
            
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

        return [
            'percentage' => $total > 0 ? round(($filled / $total) * 100, 2) : 0,
            'required_percentage' => $required > 0 ? round(($requiredFilled / $required) * 100, 2) : 0,
            'filled' => $filled,
            'total' => $total,
            'required_filled' => $requiredFilled,
            'required_total' => $required,
            'field_status' => $fieldStatus
        ];
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
            // TEMPORARILY DISABLED: This is filtering out POS fields that might exist in the template
            /*
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
            */
            
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
        
        // DEBUG: Log exact field mapping to identify mismatches
        $skippedFields = [];
        $mappedFields = [];

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
                $skippedFields[] = $canonicalName;
                Log::debug("Skipping field not in Docuseal template mapping", [
                    'field' => $canonicalName,
                    'document_type' => $documentType,
                    'manufacturer' => $manufacturerConfig['name'] ?? 'unknown'
                ]);
                continue;
            }
            
            $mappedFields[] = $canonicalName;

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
            // Special handling for Yes/No radio buttons - preserve the string values
            if (in_array($value, ['Yes', 'No'], true) && 
                (strpos(strtolower($docuSealFieldName), 'patient') !== false || 
                 strpos($docuSealFieldName, '?') !== false)) {
                // These are likely radio button fields that expect Yes/No, not true/false
                $docuSealFields[] = [
                    'name' => $docuSealFieldName,
                    'default_value' => $value
                ];
                continue;
            }
            
            if (is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true)) {
                // Convert various boolean representations to Docuseal checkbox format
                $boolValue = false;
                
                if (is_bool($value)) {
                    $boolValue = $value;
                } elseif (in_array($value, ['true', '1', 1], true)) {
                    $boolValue = true;
                } elseif (in_array($value, ['false', '0', 0], true)) {
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

        // DEBUG: Log comprehensive field mapping results
        Log::warning('DocuSeal field conversion complete - DETAILED DEBUG', [
            'manufacturer' => $manufacturerConfig['name'] ?? 'unknown',
            'total_input_fields' => count($mappedData),
            'total_output_fields' => count($docuSealFields),
            'successfully_mapped' => $mappedFields,
            'skipped_fields' => $skippedFields,
            'skipped_count' => count($skippedFields),
            'field_name_mapping' => $fieldNameMapping,
            'sample_input_data' => array_slice($mappedData, 0, 10, true),
            'sample_output_fields' => array_slice($docuSealFields, 0, 10)
        ]);
        
        return $docuSealFields;
    }

    /**
     * Get value by path (supports nested access)
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
     * Log mapping analytics for monitoring and improvement
     */
    private function logMappingAnalytics(int $episodeId, string $manufacturer, array $completeness, float $duration): void
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
        foreach ($this->config['manufacturers'] as $name => $config) {
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
     * Update field mapping for a template
     * NOTE: Commented out as TemplateFieldMapping model doesn't exist
     */
    /*
    public function updateFieldMapping(
        string $templateId, 
        string $fieldName, 
        ?int $canonicalFieldId, 
        array $transformationRules = []
    ): TemplateFieldMapping {
        $mapping = TemplateFieldMapping::updateOrCreate(
            [
                'template_id' => $templateId,
                'field_name' => $fieldName,
            ],
            [
                'canonical_field_id' => $canonicalFieldId,
                'updated_by' => (Auth::user())->id
            ]
        );

        // Validate the mapping
        $validation = $this->validateFieldMapping($mapping);
        $mapping->validation_status = $validation['status'];
        $mapping->validation_messages = $validation['messages'];
        $mapping->save();

        return $mapping;
    }
    */

    /**
     * Validate a field mapping
     * NOTE: Commented out as TemplateFieldMapping model doesn't exist
     */
    /*
    public function validateFieldMapping(TemplateFieldMapping $mapping): array
    {
        $status = 'valid';
        $messages = [];

        // Check if canonical field exists
        if (!$mapping->canonical_field_id) {
            $status = 'warning';
            $messages[] = 'No canonical field mapped';
            return compact('status', 'messages');
        }

        $canonicalField = $mapping->canonicalField;
        if (!$canonicalField) {
            $status = 'error';
            $messages[] = 'Invalid canonical field reference';
            return compact('status', 'messages');
        }

        // Validate transformation rules
        if (!empty($mapping->transformation_rules)) {
            foreach ($mapping->transformation_rules as $rule) {
                if (!isset($rule['type']) || !isset($rule['operation'])) {
                    $status = 'warning';
                    $messages[] = 'Invalid transformation rule format';
                }
            }
        }

        // Check data type compatibility
        if ($canonicalField->data_type === 'date' && empty($mapping->transformation_rules)) {
            $hasDateTransform = false;
            foreach ($mapping->transformation_rules ?? [] as $rule) {
                if ($rule['type'] === 'format' && $rule['operation'] === 'date') {
                    $hasDateTransform = true;
                    break;
                }
            }
            if (!$hasDateTransform) {
                $status = 'warning';
                $messages[] = 'Date field may need format transformation';
            }
        }

        // Check for required field
        if ($canonicalField->is_required && !$mapping->is_active) {
            $status = 'warning';
            $messages[] = 'Required field is not active';
        }

        return compact('status', 'messages');
    }
    */

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
     * Apply mapping configuration to data
     * NOTE: Commented out as TemplateFieldMapping model doesn't exist
     */
    /*
    public function applyMappingConfiguration(array $data, string $templateId): array
    {
        $mappings = TemplateFieldMapping::where('template_id', $templateId)
            ->where('is_active', true)
            ->with('canonicalField')
            ->get();

        $mappedData = [];
        $rulesEngine = app(MappingRulesEngine::class);

        foreach ($mappings as $mapping) {
            if (!isset($data[$mapping->field_name])) {
                continue;
            }

            $value = $data[$mapping->field_name];

            // Apply transformation rules if any
            if (!empty($mapping->transformation_rules)) {
                $value = $rulesEngine->applyTransformationRules($value, $mapping->transformation_rules);
            }

            // Map to canonical field path
            if ($mapping->canonicalField) {
                $this->setNestedValue(
                    $mappedData, 
                    $mapping->canonicalField->field_path, 
                    $value
                );
            }
        }

        return $mappedData;
    }
    */

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