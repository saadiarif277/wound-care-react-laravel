<?php

namespace App\Services;

use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;
use App\Models\CanonicalField;
use App\Models\TemplateFieldMapping;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class UnifiedFieldMappingService
{
    private array $config;

    public function __construct(
        private DataExtractor $dataExtractor,
        private FieldTransformer $fieldTransformer,
        private FieldMatcher $fieldMatcher
    ) {
        $this->config = config('field-mapping');
    }

    /**
     * Main entry point for all field mapping needs
     */
    public function mapEpisodeToTemplate(
        ?int $episodeId, 
        string $manufacturerName,
        array $additionalData = []
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

            // 2. Get manufacturer configuration
            $manufacturerConfig = $this->getManufacturerConfig($manufacturerName);
            if (!$manufacturerConfig) {
                throw new \InvalidArgumentException("Unknown manufacturer: {$manufacturerName}");
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
            $values = array_map(fn($part) => $data[$part] ?? '', $parts);
            return implode(' ', array_filter($values));
        }

        // Handle multiplication (field1 * field2)
        if (str_contains($computation, ' * ')) {
            $parts = array_map('trim', explode(' * ', $computation));
            $values = array_map(fn($part) => (float)($data[$part] ?? 0), $parts);
            return array_reduce($values, fn($carry, $item) => $carry * $item, 1);
        }

        // Handle OR conditions (field1 || field2)
        if (str_contains($computation, ' || ')) {
            $parts = array_map('trim', explode(' || ', $computation));
            foreach ($parts as $part) {
                if (!empty($data[$part])) {
                    return $data[$part];
                }
            }
            return null;
        }

        // Handle division (field1 / field2)
        if (str_contains($computation, ' / ')) {
            $parts = array_map('trim', explode(' / ', $computation));
            if (count($parts) === 2) {
                $numerator = (float)($data[$parts[0]] ?? 0);
                $denominator = (float)($data[$parts[1]] ?? 1);
                return $denominator != 0 ? $numerator / $denominator : 0;
            }
        }

        Log::warning("Unknown computation type: {$computation}");
        return null;
    }

    /**
     * Apply manufacturer-specific business rules
     */
    private function applyBusinessRules(array $data, array $config, array $sourceData): array
    {
        $manufacturerName = $config['name'];

        // ACZ specific rules
        if ($manufacturerName === 'ACZ' && isset($config['duration_requirement'])) {
            if ($config['duration_requirement'] === 'greater_than_4_weeks') {
                $weeks = (int)($sourceData['wound_duration_weeks'] ?? 0);
                if ($weeks <= 4) {
                    Log::warning("ACZ requires wound duration > 4 weeks", [
                        'actual_weeks' => $weeks,
                        'episode_id' => $sourceData['episode_id'] ?? null
                    ]);
                    // You might want to add a flag or warning to the data
                    $data['_warnings'] = ($data['_warnings'] ?? []);
                    $data['_warnings'][] = 'Wound duration does not meet ACZ requirement of > 4 weeks';
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
     */
    private function validateMapping(array $data, array $config): array
    {
        $errors = [];
        $warnings = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            // Check required fields
            if ($fieldConfig['required'] ?? false) {
                if (empty($data[$field]) && $data[$field] !== '0' && $data[$field] !== 0) {
                    $errors[] = "Required field '{$field}' is missing or empty";
                }
            }

            // Validate format if specified and value exists
            if (!empty($data[$field]) && isset($fieldConfig['type'])) {
                $isValid = $this->validateFieldType($data[$field], $fieldConfig['type']);
                if (!$isValid) {
                    $warnings[] = "Field '{$field}' format may be invalid for type '{$fieldConfig['type']}'";
                }
            }
        }

        // Include any warnings from business rules
        if (isset($data['_warnings'])) {
            $warnings = array_merge($warnings, $data['_warnings']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
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
     * Get manufacturer configuration
     */
    public function getManufacturerConfig(string $name): ?array
    {
        // First try exact match
        if (isset($this->config['manufacturers'][$name])) {
            return $this->config['manufacturers'][$name];
        }

        // Try case-insensitive match
        foreach ($this->config['manufacturers'] as $key => $config) {
            if (strtolower($key) === strtolower($name)) {
                return $config;
            }
        }

        // Try matching by manufacturer name in config
        foreach ($this->config['manufacturers'] as $key => $config) {
            if (isset($config['name']) && 
                (strtolower($config['name']) === strtolower($name) || 
                 str_contains(strtolower($config['name']), strtolower($name)))) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Convert mapped data to DocuSeal field format
     */
    public function convertToDocuSealFields(array $mappedData, array $manufacturerConfig): array
    {
        $docuSealFields = [];
        $fieldNameMapping = $manufacturerConfig['docuseal_field_names'] ?? [];

        foreach ($mappedData as $canonicalName => $value) {
            // Skip null or empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Get the DocuSeal field name for this canonical field
            $docuSealFieldName = $fieldNameMapping[$canonicalName] ?? $canonicalName;

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

            // Add the field with its DocuSeal name
            // Handle array values by converting to string representation
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $docuSealFields[] = [
                'name' => $docuSealFieldName,
                'default_value' => (string) $value
            ];
        }

        return $docuSealFields;
    }

    /**
     * Get value by path (supports nested access)
     */
    private function getValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
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
        
        foreach ($this->config['manufacturers'] as $name => $config) {
            $manufacturers[] = [
                'id' => $config['id'],
                'name' => $config['name'],
                'template_id' => $config['template_id'],
                'signature_required' => $config['signature_required'],
                'has_order_form' => $config['has_order_form'],
                'fields_count' => count($config['fields']),
                'required_fields_count' => count(array_filter($config['fields'], fn($f) => $f['required'] ?? false))
            ];
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
     */
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

    /**
     * Validate a field mapping
     */
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
        $mappedFields = $template->fieldMappings->whereNotNull('canonical_field_id')->count();
        
        return [
            'total_fields' => $totalFields,
            'mapped_fields' => $mappedFields,
            'unmapped_fields' => $totalFields - $template->fieldMappings->count(),
            'coverage_percentage' => $totalFields > 0 ? round(($mappedFields / $totalFields) * 100, 2) : 0,
            'validation_errors' => $template->fieldMappings->where('validation_status', 'error')->count(),
            'validation_warnings' => $template->fieldMappings->where('validation_status', 'warning')->count(),
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
     */
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
}