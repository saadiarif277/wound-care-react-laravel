<?php

namespace App\Console\Commands;

use App\Services\Learning\MLDataPipelineService;
use App\Services\Learning\ContinuousLearningService;
use App\Services\Learning\BehavioralTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class IngestManufacturerConfigs extends Command
{
    protected $signature = 'manufacturer:ingest-configs {--retrain : Retrain ML models after ingestion} {--validate : Validate configs before ingestion}';
    
    protected $description = 'Ingest manufacturer configuration data into ML learning system';

    public function __construct(
        protected MLDataPipelineService $dataPipeline,
        protected ContinuousLearningService $continuousLearning,
        protected BehavioralTrackingService $behavioralTracker
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔄 Ingesting Manufacturer Configuration Data into ML System');
        $this->line('');

        // Step 1: Discover manufacturer configuration files
        $configFiles = $this->discoverManufacturerConfigs();
        $this->info("📁 Found {$configFiles->count()} manufacturer configuration files");

        // Step 2: Validate configurations if requested
        if ($this->option('validate')) {
            $this->validateConfigurations($configFiles);
        }

        // Step 3: Process each manufacturer configuration
        $processedCount = 0;
        $fieldMappings = [];
        
        foreach ($configFiles as $configFile) {
            $this->line("📋 Processing: {$configFile}");
            
            $manufacturerData = $this->processManufacturerConfig($configFile);
            if ($manufacturerData) {
                $fieldMappings[] = $manufacturerData;
                $processedCount++;
                $this->info("  ✅ Processed {$manufacturerData['name']} ({$manufacturerData['field_count']} fields)");
            }
        }

        // Step 4: Feed data to ML pipeline
        $this->info("🤖 Feeding {$processedCount} manufacturer configs to ML system...");
        $this->feedToMLSystem($fieldMappings);

        // Step 5: Retrain models if requested
        if ($this->option('retrain')) {
            $this->info('🔄 Retraining ML models with new manufacturer data...');
            $this->retrainModels($fieldMappings);
        }

        $this->info("✅ Successfully ingested {$processedCount} manufacturer configurations");
        $this->line('');
        $this->info('🎯 ML system now has access to manufacturer field patterns and IVR forms!');
    }

    protected function discoverManufacturerConfigs(): \Illuminate\Support\Collection
    {
        $configPath = config_path('manufacturers');
        
        if (!File::exists($configPath)) {
            $this->error("❌ Manufacturer config directory not found: {$configPath}");
            return collect();
        }

        return collect(File::files($configPath))
            ->filter(fn($file) => $file->getExtension() === 'php')
            ->map(fn($file) => $file->getPathname());
    }

    protected function processManufacturerConfig(string $configFile): ?array
    {
        try {
            $manufacturerName = basename($configFile, '.php');
            $config = include $configFile;
            
            if (!is_array($config)) {
                $this->warn("  ⚠️ Invalid config format in {$configFile}");
                return null;
            }

            // Extract field mappings and patterns
            $fieldMappings = $this->extractFieldMappings($config);
            $ivrFormData = $this->extractIVRFormData($config);
            $validationRules = $this->extractValidationRules($config);

            return [
                'name' => $manufacturerName,
                'display_name' => $config['name'] ?? $manufacturerName,
                'field_mappings' => $fieldMappings,
                'ivr_form_data' => $ivrFormData,
                'validation_rules' => $validationRules,
                'field_count' => count($fieldMappings),
                'template_patterns' => $this->extractTemplatePatterns($config),
                'priority_fields' => $this->extractPriorityFields($config),
                'data_transforms' => $this->extractDataTransforms($config)
            ];
        } catch (\Exception $e) {
            $this->error("  ❌ Error processing {$configFile}: {$e->getMessage()}");
            return null;
        }
    }

    protected function extractFieldMappings(array $config): array
    {
        $mappings = [];
        
        // Extract from docuseal_field_mappings (new format)
        if (isset($config['docuseal_field_mappings']) && is_array($config['docuseal_field_mappings'])) {
            foreach ($config['docuseal_field_mappings'] as $fieldName => $mapping) {
                $mappings[$fieldName] = [
                    'source' => $mapping['source'] ?? 'unknown',
                    'transform' => $mapping['transform'] ?? null,
                    'validation' => $mapping['validation'] ?? null,
                    'type' => $mapping['type'] ?? 'text',
                    'required' => $mapping['required'] ?? false,
                    'default' => $mapping['default'] ?? null
                ];
            }
        }

        // Extract from fields array (current format)
        if (isset($config['fields']) && is_array($config['fields'])) {
            foreach ($config['fields'] as $fieldName => $fieldConfig) {
                if (!isset($mappings[$fieldName])) {
                    $mappings[$fieldName] = [
                        'source' => $fieldConfig['source'] ?? 'unknown',
                        'transform' => $fieldConfig['transform'] ?? null,
                        'validation' => $fieldConfig['validation'] ?? null,
                        'type' => $fieldConfig['type'] ?? 'text',
                        'required' => $fieldConfig['required'] ?? false,
                        'default' => $fieldConfig['default'] ?? null,
                        'computation' => $fieldConfig['computation'] ?? null
                    ];
                }
            }
        }

        // Extract from docuseal_field_names (field name mapping)
        if (isset($config['docuseal_field_names']) && is_array($config['docuseal_field_names'])) {
            foreach ($config['docuseal_field_names'] as $fieldKey => $docusealFieldName) {
                if (!isset($mappings[$fieldKey])) {
                    $mappings[$fieldKey] = [
                        'source' => $fieldKey,
                        'docuseal_field_name' => $docusealFieldName,
                        'type' => 'text',
                        'required' => false
                    ];
                }
            }
        }

        // Extract from field_mappings (legacy format)
        if (isset($config['field_mappings']) && is_array($config['field_mappings'])) {
            foreach ($config['field_mappings'] as $fieldName => $source) {
                if (!isset($mappings[$fieldName])) {
                    $mappings[$fieldName] = [
                        'source' => $source,
                        'type' => 'text',
                        'required' => false
                    ];
                }
            }
        }

        return $mappings;
    }

    protected function extractIVRFormData(array $config): array
    {
        $ivrData = [];
        
        // Extract IVR form structure
        if (isset($config['ivr_form_fields']) && is_array($config['ivr_form_fields'])) {
            $ivrData['form_fields'] = $config['ivr_form_fields'];
        }

        // Extract form sections
        if (isset($config['form_sections']) && is_array($config['form_sections'])) {
            $ivrData['form_sections'] = $config['form_sections'];
        }

        // Extract required fields
        if (isset($config['required_fields']) && is_array($config['required_fields'])) {
            $ivrData['required_fields'] = $config['required_fields'];
        }

        return $ivrData;
    }

    protected function extractValidationRules(array $config): array
    {
        $rules = [];
        
        if (isset($config['validation_rules']) && is_array($config['validation_rules'])) {
            $rules = $config['validation_rules'];
        }

        if (isset($config['field_validation']) && is_array($config['field_validation'])) {
            $rules = array_merge($rules, $config['field_validation']);
        }

        return $rules;
    }

    protected function extractTemplatePatterns(array $config): array
    {
        $patterns = [];
        
        if (isset($config['template_patterns']) && is_array($config['template_patterns'])) {
            $patterns = $config['template_patterns'];
        }

        return $patterns;
    }

    protected function extractPriorityFields(array $config): array
    {
        $priorities = [];
        
        if (isset($config['priority_fields']) && is_array($config['priority_fields'])) {
            $priorities = $config['priority_fields'];
        }

        return $priorities;
    }

    protected function extractDataTransforms(array $config): array
    {
        $transforms = [];
        
        if (isset($config['data_transforms']) && is_array($config['data_transforms'])) {
            $transforms = $config['data_transforms'];
        }

        return $transforms;
    }

    protected function validateConfigurations(\Illuminate\Support\Collection $configFiles): void
    {
        $this->info('🔍 Validating manufacturer configurations...');
        
        $errors = [];
        foreach ($configFiles as $configFile) {
            $manufacturerName = basename($configFile, '.php');
            
            try {
                $config = include $configFile;
                
                if (!is_array($config)) {
                    $errors[] = "❌ {$manufacturerName}: Invalid config format";
                    continue;
                }

                // Validate required fields
                if (!isset($config['name'])) {
                    $errors[] = "⚠️ {$manufacturerName}: Missing 'name' field";
                }

                if (!isset($config['docuseal_field_mappings']) && !isset($config['field_mappings']) && !isset($config['fields']) && !isset($config['docuseal_field_names'])) {
                    $errors[] = "⚠️ {$manufacturerName}: No field mappings found";
                }

                $this->line("  ✅ {$manufacturerName}: Valid");
                
            } catch (\Exception $e) {
                $errors[] = "❌ {$manufacturerName}: {$e->getMessage()}";
            }
        }

        if (!empty($errors)) {
            $this->warn('Validation errors found:');
            foreach ($errors as $error) {
                $this->line("  {$error}");
            }
        }
    }

    protected function feedToMLSystem(array $fieldMappings): void
    {
        foreach ($fieldMappings as $manufacturerData) {
            try {
                // Create behavioral events for field mappings
                $this->behavioralTracker->trackEvent('manufacturer_config_ingested', [
                    'manufacturer' => $manufacturerData['name'],
                    'field_count' => $manufacturerData['field_count'],
                    'field_mappings' => $manufacturerData['field_mappings'],
                    'ivr_form_data' => $manufacturerData['ivr_form_data'],
                    'validation_rules' => $manufacturerData['validation_rules']
                ]);

                // Feed to ML data pipeline
                $this->dataPipeline->processManufacturerConfigData($manufacturerData);
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error feeding {$manufacturerData['name']} to ML system: {$e->getMessage()}");
            }
        }
    }

    protected function retrainModels(array $fieldMappings): void
    {
        try {
            // Prepare training data with manufacturer configurations
            $trainingData = $this->dataPipeline->buildTrainingDataset([
                'include_manufacturer_configs' => true,
                'manufacturer_data' => $fieldMappings,
                'days' => 365, // Use historical data
                'features' => ['all']
            ]);

            // Retrain models with new data
            $this->continuousLearning->trainModels($trainingData);
            
            $this->info('✅ ML models retrained with manufacturer configuration data');
        } catch (\Exception $e) {
            $this->error("❌ Error retraining models: {$e->getMessage()}");
        }
    }
} 