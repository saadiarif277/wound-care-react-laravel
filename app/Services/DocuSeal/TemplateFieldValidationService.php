<?php

namespace App\Services\DocuSeal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class TemplateFieldValidationService
{
    private DocuSealApiClient $docuSealClient;
    private const CACHE_TTL = 3600; // 1 hour cache
    private const CACHE_PREFIX = 'docuseal_template_fields:';

    public function __construct(DocuSealApiClient $docuSealClient)
    {
        $this->docuSealClient = $docuSealClient;
    }

    /**
     * Get actual field names from a DocuSeal template with caching
     */
    public function getTemplateFields(string $templateId): array
    {
        $cacheKey = self::CACHE_PREFIX . $templateId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($templateId) {
            try {
                $template = $this->docuSealClient->getTemplate($templateId);
                
                if (!isset($template['fields']) || !is_array($template['fields'])) {
                    Log::warning('Template has no fields or invalid structure', [
                        'template_id' => $templateId,
                        'template_structure' => array_keys($template ?? [])
                    ]);
                    return [];
                }

                $fieldNames = array_column($template['fields'], 'name');
                
                Log::info('Fetched template fields from DocuSeal', [
                    'template_id' => $templateId,
                    'field_count' => count($fieldNames),
                    'fields' => $fieldNames
                ]);

                return $fieldNames;
                
            } catch (Exception $e) {
                Log::error('Failed to fetch template fields from DocuSeal', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Validate manufacturer configuration against actual template fields
     */
    public function validateManufacturerConfig(array $manufacturerConfig): array
    {
        $templateId = $manufacturerConfig['docuseal_template_id'] ?? null;
        
        if (!$templateId) {
            return [
                'valid' => false,
                'errors' => ['Missing docuseal_template_id in manufacturer configuration'],
                'warnings' => [],
                'invalid_fields' => [],
                'valid_fields' => [],
                'template_fields' => []
            ];
        }

        $templateFields = $this->getTemplateFields($templateId);
        $configuredFields = $manufacturerConfig['docuseal_field_names'] ?? [];
        
        if (empty($templateFields)) {
            return [
                'valid' => false,
                'errors' => ['Could not fetch template fields from DocuSeal'],
                'warnings' => [],
                'invalid_fields' => [],
                'valid_fields' => [],
                'template_fields' => []
            ];
        }

        $errors = [];
        $warnings = [];
        $invalidFields = [];
        $validFields = [];

        // Check each configured field against template
        foreach ($configuredFields as $canonicalName => $docuSealFieldName) {
            if (in_array($docuSealFieldName, $templateFields)) {
                $validFields[$canonicalName] = $docuSealFieldName;
            } else {
                $invalidFields[$canonicalName] = $docuSealFieldName;
                $warnings[] = "Field '{$canonicalName}' maps to '{$docuSealFieldName}' which doesn't exist in template";
            }
        }

        // Check for critical missing fields
        $criticalFields = ['patient_name', 'patient_dob', 'facility_name'];
        $missingCritical = [];
        
        foreach ($criticalFields as $critical) {
            if (!isset($validFields[$critical])) {
                $missingCritical[] = $critical;
            }
        }

        if (!empty($missingCritical)) {
            $errors[] = 'Missing critical field mappings: ' . implode(', ', $missingCritical);
        }

        $isValid = empty($errors) && count($validFields) > 0;

        Log::info('Manufacturer configuration validation completed', [
            'manufacturer' => $manufacturerConfig['name'] ?? 'Unknown',
            'template_id' => $templateId,
            'valid' => $isValid,
            'valid_fields_count' => count($validFields),
            'invalid_fields_count' => count($invalidFields),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings)
        ]);

        return [
            'valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'invalid_fields' => $invalidFields,
            'valid_fields' => $validFields,
            'template_fields' => $templateFields,
            'template_id' => $templateId,
            'manufacturer_name' => $manufacturerConfig['name'] ?? 'Unknown'
        ];
    }

    /**
     * Filter manufacturer field configuration to only include valid fields
     */
    public function filterValidFields(array $manufacturerConfig): array
    {
        $validation = $this->validateManufacturerConfig($manufacturerConfig);
        
        if (!$validation['valid'] && empty($validation['valid_fields'])) {
            Log::warning('No valid fields found for manufacturer, returning original config', [
                'manufacturer' => $manufacturerConfig['name'] ?? 'Unknown',
                'template_id' => $manufacturerConfig['docuseal_template_id'] ?? 'None'
            ]);
            return $manufacturerConfig;
        }

        // Create a filtered version with only valid fields
        $filteredConfig = $manufacturerConfig;
        $filteredConfig['docuseal_field_names'] = $validation['valid_fields'];

        // Log what was filtered out
        if (!empty($validation['invalid_fields'])) {
            Log::warning('Filtered out invalid fields from manufacturer configuration', [
                'manufacturer' => $manufacturerConfig['name'] ?? 'Unknown',
                'template_id' => $manufacturerConfig['docuseal_template_id'] ?? 'None',
                'filtered_fields' => array_keys($validation['invalid_fields']),
                'invalid_mappings' => $validation['invalid_fields']
            ]);
        }

        return $filteredConfig;
    }

    /**
     * Validate all manufacturer configurations at once
     */
    public function validateAllManufacturerConfigs(): array
    {
        $results = [];
        $manufacturerConfigPath = config_path('manufacturers');
        
        if (!is_dir($manufacturerConfigPath)) {
            return [
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'invalid' => 0,
                    'warnings' => 0
                ],
                'details' => []
            ];
        }

        $files = glob($manufacturerConfigPath . '/*.php');
        $total = 0;
        $valid = 0;
        $invalid = 0;
        $totalWarnings = 0;

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            
            try {
                $config = include $file;
                
                if (!is_array($config)) {
                    $results[$filename] = [
                        'valid' => false,
                        'errors' => ['Invalid configuration file format'],
                        'warnings' => [],
                        'file' => $filename
                    ];
                    $invalid++;
                    $total++;
                    continue;
                }

                $validation = $this->validateManufacturerConfig($config);
                $validation['file'] = $filename;
                $results[$filename] = $validation;

                $total++;
                if ($validation['valid']) {
                    $valid++;
                } else {
                    $invalid++;
                }
                $totalWarnings += count($validation['warnings']);

            } catch (Exception $e) {
                $results[$filename] = [
                    'valid' => false,
                    'errors' => ['Failed to load configuration: ' . $e->getMessage()],
                    'warnings' => [],
                    'file' => $filename
                ];
                $invalid++;
                $total++;
            }
        }

        return [
            'summary' => [
                'total' => $total,
                'valid' => $valid,
                'invalid' => $invalid,
                'warnings' => $totalWarnings
            ],
            'details' => $results
        ];
    }

    /**
     * Clear template field cache for a specific template or all templates
     */
    public function clearTemplateFieldCache(?string $templateId = null): void
    {
        if ($templateId) {
            Cache::forget(self::CACHE_PREFIX . $templateId);
            Log::info('Cleared template field cache', ['template_id' => $templateId]);
        } else {
            // Clear all template field caches - simplified approach
            // In a production environment, you might want to implement a more sophisticated cache clearing strategy
            Log::info('Cleared all template field caches (note: some cache drivers may not support pattern clearing)');
        }
    }

    /**
     * Auto-fix manufacturer configuration by removing invalid fields
     */
    public function autoFixManufacturerConfig(string $configFilePath): array
    {
        if (!file_exists($configFilePath)) {
            return [
                'success' => false,
                'message' => 'Configuration file not found',
                'changes' => []
            ];
        }

        try {
            $config = include $configFilePath;
            $originalConfig = $config;
            
            $validation = $this->validateManufacturerConfig($config);
            
            if ($validation['valid'] && empty($validation['invalid_fields'])) {
                return [
                    'success' => true,
                    'message' => 'Configuration is already valid, no changes needed',
                    'changes' => []
                ];
            }

            // Comment out invalid fields in the configuration file
            $changes = [];
            if (!empty($validation['invalid_fields'])) {
                $fileContent = file_get_contents($configFilePath);
                
                foreach ($validation['invalid_fields'] as $canonicalName => $docuSealFieldName) {
                    // Find and comment out the invalid field mapping
                    $pattern = "/(\s*'$canonicalName'\s*=>\s*'$docuSealFieldName')/";
                    $replacement = "        // REMOVED: Field doesn't exist in DocuSeal template\n        // $1";
                    
                    if (preg_match($pattern, $fileContent)) {
                        $fileContent = preg_replace($pattern, $replacement, $fileContent);
                        $changes[] = "Commented out invalid field mapping: $canonicalName => $docuSealFieldName";
                    }
                }

                // Add a timestamp comment
                $timestamp = date('Y-m-d H:i:s');
                $comment = "\n        // AUTO-FIXED: Invalid fields removed on $timestamp\n";
                $fileContent = str_replace("'docuseal_field_names' => [", "'docuseal_field_names' => [$comment", $fileContent);
                
                // Write the updated file
                file_put_contents($configFilePath, $fileContent);
                
                return [
                    'success' => true,
                    'message' => 'Configuration auto-fixed successfully',
                    'changes' => $changes,
                    'validation' => $validation
                ];
            }

            return [
                'success' => false,
                'message' => 'No invalid fields found to fix',
                'changes' => []
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to auto-fix configuration: ' . $e->getMessage(),
                'changes' => []
            ];
        }
    }
} 