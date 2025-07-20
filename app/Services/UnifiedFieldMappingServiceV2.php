<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * UnifiedFieldMappingServiceV2 - Pure mapping engine
 * 
 * This service is responsible ONLY for:
 * 1. Loading manufacturer configurations
 * 2. Applying field mappings from configs
 * 3. Running transformations defined in configs
 * 4. Validating based on config rules
 * 
 * It does NOT contain any mapping logic itself.
 * All mapping logic lives in the manufacturer config files.
 */
class UnifiedFieldMappingServiceV2
{
    /**
     * Map data for a specific manufacturer and document type
     */
    public function mapData(
        array $sourceData,
        string $manufacturerName,
        string $documentType = 'ivr'
    ): array {
        try {
            // 1. Load manufacturer configuration
            $config = $this->loadManufacturerConfig($manufacturerName);
            
            if (!$config) {
                throw new \Exception("No configuration found for manufacturer: {$manufacturerName}");
            }
            
            // 2. Get field mappings for the document type
            $fieldMappings = $config['field_mappings'][$documentType] ?? null;
            
            if (!$fieldMappings) {
                throw new \Exception("No field mappings found for document type: {$documentType}");
            }
            
            // 3. Apply mappings
            $mappedData = $this->applyFieldMappings($sourceData, $fieldMappings, $config);
            
            // 4. Validate mapped data
            $validation = $this->validateMappedData($mappedData, $fieldMappings, $config);
            
            // 5. Apply business rules
            $mappedData = $this->applyBusinessRules($mappedData, $config);
            
            return [
                'data' => $mappedData,
                'validation' => $validation,
                'metadata' => [
                    'manufacturer' => $manufacturerName,
                    'document_type' => $documentType,
                    'mapped_at' => now()->toIso8601String()
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Field mapping failed', [
                'manufacturer' => $manufacturerName,
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Load manufacturer configuration
     */
    protected function loadManufacturerConfig(string $manufacturerName): ?array
    {
        $slug = Str::slug($manufacturerName);
        
        // Try v2 config first
        $v2ConfigPath = config_path("manufacturers/{$slug}-v2.php");
        if (file_exists($v2ConfigPath)) {
            return include $v2ConfigPath;
        }
        
        // Fall back to original config
        $configPath = config_path("manufacturers/{$slug}.php");
        if (file_exists($configPath)) {
            // Convert old format to new format on the fly
            return $this->convertLegacyConfig(include $configPath);
        }
        
        return null;
    }
    
    /**
     * Apply field mappings from configuration
     */
    protected function applyFieldMappings(array $sourceData, array $fieldMappings, array $config): array
    {
        $mappedData = [];
        
        foreach ($fieldMappings as $fieldKey => $mapping) {
            try {
                // Get the value based on source type
                $value = $this->extractValue($sourceData, $mapping);
                
                // Apply transformation if specified
                if ($value !== null && isset($mapping['transform'])) {
                    $value = $this->applyTransformation(
                        $value,
                        $mapping['transform'],
                        $mapping['transform_params'] ?? [],
                        $config
                    );
                }
                
                // Apply default if value is still null and default is specified
                if ($value === null && isset($mapping['default'])) {
                    $value = $mapping['default'];
                }
                
                // Set the mapped value
                if ($value !== null) {
                    $mappedData[$fieldKey] = $value;
                }
                
            } catch (\Exception $e) {
                Log::warning('Failed to map field', [
                    'field' => $fieldKey,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $mappedData;
    }
    
    /**
     * Extract value from source data based on mapping configuration
     */
    protected function extractValue(array $sourceData, array $mapping): mixed
    {
        // Handle computed fields
        if (($mapping['source'] ?? '') === 'computed') {
            return $this->computeValue($sourceData, $mapping);
        }
        
        // Try primary source
        $value = $this->getValueByPath($sourceData, $mapping['source'] ?? '');
        
        // Try fallback sources if primary is null
        if ($value === null && isset($mapping['fallback'])) {
            foreach ($mapping['fallback'] as $fallbackPath) {
                $value = $this->getValueByPath($sourceData, $fallbackPath);
                if ($value !== null) {
                    break;
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Get value by dot notation path
     */
    protected function getValueByPath(array $data, string $path): mixed
    {
        if (empty($path)) {
            return null;
        }
        
        // Handle array notation like "diagnosis_codes[0]"
        if (preg_match('/^(.+)\[(\d+)\]$/', $path, $matches)) {
            $arrayPath = $matches[1];
            $index = (int)$matches[2];
            
            $array = data_get($data, $arrayPath);
            return is_array($array) && isset($array[$index]) ? $array[$index] : null;
        }
        
        // Use Laravel's data_get helper for dot notation
        return data_get($data, $path);
    }
    
    /**
     * Compute value based on computation configuration
     */
    protected function computeValue(array $sourceData, array $mapping): mixed
    {
        if (!isset($mapping['computation']) || !isset($mapping['computation_fields'])) {
            return null;
        }
        
        // Extract values for computation
        $values = [];
        foreach ($mapping['computation_fields'] as $field) {
            $values[] = $this->getValueByPath($sourceData, $field);
        }
        
        // Apply computation
        $computation = $mapping['computation'];
        
        // Built-in computations
        switch ($computation) {
            case 'concat_name':
                return trim(implode(' ', array_filter($values)));
                
            case 'concat_address':
                return trim(implode(', ', array_filter($values)));
                
            default:
                return null;
        }
    }
    
    /**
     * Apply transformation to a value
     */
    protected function applyTransformation($value, string $transform, array $params, array $config): mixed
    {
        // Check if transformation exists in config
        if (isset($config['transformations'][$transform])) {
            $transformer = $config['transformations'][$transform];
            if (is_callable($transformer)) {
                return $transformer($value, $params);
            }
        }
        
        // Built-in transformations
        switch ($transform) {
            case 'uppercase':
                return strtoupper($value);
                
            case 'lowercase':
                return strtolower($value);
                
            case 'trim':
                return trim($value);
                
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            default:
                return $value;
        }
    }
    
    /**
     * Validate mapped data against configuration rules
     */
    protected function validateMappedData(array $mappedData, array $fieldMappings, array $config): array
    {
        $errors = [];
        $warnings = [];
        
        foreach ($fieldMappings as $fieldKey => $mapping) {
            $value = $mappedData[$fieldKey] ?? null;
            
            // Check required fields
            if (($mapping['required'] ?? false) && empty($value)) {
                $errors[] = "Required field '{$fieldKey}' is missing";
            }
            
            // Apply validation rules
            if (!empty($value) && isset($mapping['validation'])) {
                $rule = $mapping['validation'];
                if (isset($config['validation_rules'][$rule])) {
                    $pattern = $config['validation_rules'][$rule];
                    if (!preg_match($pattern, $value)) {
                        $warnings[] = "Field '{$fieldKey}' has invalid format for {$rule}";
                    }
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Apply business rules from configuration
     */
    protected function applyBusinessRules(array $mappedData, array $config): array
    {
        $businessRules = $config['business_rules'] ?? [];
        
        // Example: Add warnings based on business rules
        if (isset($businessRules['wound_duration_requirement'])) {
            // This would be implemented based on specific business logic
        }
        
        return $mappedData;
    }
    
    /**
     * Convert legacy config format to new format
     */
    protected function convertLegacyConfig(array $legacyConfig): array
    {
        // Convert the old flat docuseal_field_names to new structure
        $fieldMappings = [];
        
        foreach ($legacyConfig['docuseal_field_names'] ?? [] as $internalField => $docusealField) {
            $fieldMappings[$internalField] = [
                'docuseal_field' => $docusealField,
                'source' => $internalField,
                'required' => false
            ];
        }
        
        // Apply any field-specific configurations from the legacy fields array
        foreach ($legacyConfig['fields'] ?? [] as $field => $fieldConfig) {
            if (isset($fieldMappings[$field])) {
                $fieldMappings[$field] = array_merge($fieldMappings[$field], [
                    'source' => $fieldConfig['source'] ?? $field,
                    'required' => $fieldConfig['required'] ?? false,
                    'transform' => $fieldConfig['transform'] ?? null,
                    'type' => $fieldConfig['type'] ?? 'string'
                ]);
            }
        }
        
        return [
            'id' => $legacyConfig['id'] ?? null,
            'name' => $legacyConfig['name'] ?? '',
            'signature_required' => $legacyConfig['signature_required'] ?? true,
            'has_order_form' => $legacyConfig['has_order_form'] ?? false,
            'field_mappings' => [
                'ivr' => $fieldMappings
            ],
            'transformations' => [],
            'computations' => [],
            'validation_rules' => [],
            'business_rules' => []
        ];
    }
    
    /**
     * Convert mapped data to DocuSeal format
     */
    public function convertToDocuSealFormat(array $mappedData, array $fieldMappings): array
    {
        $docuSealFields = [];
        
        foreach ($mappedData as $fieldKey => $value) {
            if (isset($fieldMappings[$fieldKey]['docuseal_field'])) {
                $docuSealField = $fieldMappings[$fieldKey]['docuseal_field'];
                $fieldType = $fieldMappings[$fieldKey]['type'] ?? 'string';
                
                // Format value based on type
                if ($fieldType === 'boolean') {
                    $value = $value ? 'true' : 'false';
                } else {
                    $value = (string)$value;
                }
                
                $docuSealFields[] = [
                    'name' => $docuSealField,
                    'default_value' => $value
                ];
            }
        }
        
        return $docuSealFields;
    }
} 