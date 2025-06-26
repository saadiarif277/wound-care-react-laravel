<?php

namespace App\Services\FuzzyMapping;

use App\Models\Order\Manufacturer;
use App\Models\IVRFieldMapping;
use App\Models\IVRTemplateField;
use App\Models\IVRMappingAudit;
use App\Services\FhirService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class IVRMappingOrchestrator
{
    protected EnhancedFuzzyFieldMatcher $fuzzyMatcher;
    protected ManufacturerTemplateHandler $templateHandler;
    protected ValidationEngine $validationEngine;
    protected FallbackStrategy $fallbackStrategy;
    protected FhirService $fhirService;

    public function __construct(
        EnhancedFuzzyFieldMatcher $fuzzyMatcher,
        ManufacturerTemplateHandler $templateHandler,
        ValidationEngine $validationEngine,
        FallbackStrategy $fallbackStrategy,
        FhirService $fhirService
    ) {
        $this->fuzzyMatcher = $fuzzyMatcher;
        $this->templateHandler = $templateHandler;
        $this->validationEngine = $validationEngine;
        $this->fallbackStrategy = $fallbackStrategy;
        $this->fhirService = $fhirService;
    }

    public function mapDataForIVR(
        array $fhirData,
        array $additionalData,
        int $manufacturerId,
        string $templateName = 'insurance-verification'
    ): array {
        $startTime = microtime(true);
        
        try {
            // Get manufacturer details
            $manufacturer = Manufacturer::findOrFail($manufacturerId);
            $manufacturerName = $manufacturer->name;
            
            Log::info("Starting IVR mapping", [
                'manufacturer' => $manufacturerName,
                'template' => $templateName,
                'fhir_data_count' => count($fhirData),
                'additional_data_count' => count($additionalData),
            ]);
            
            // Flatten and prepare all available data
            $availableData = $this->prepareAvailableData($fhirData, $additionalData);
            
            // Get template fields
            $templateFields = $this->getTemplateFields($manufacturerId, $templateName);
            
            // Perform mapping
            $mappedData = [];
            $unmappedFields = [];
            
            foreach ($templateFields as $field) {
                $fieldName = is_array($field) ? $field['field_name'] : $field->field_name;
                
                $mapping = $this->fuzzyMatcher->findBestMatch(
                    $fieldName,
                    $availableData,
                    $manufacturerId,
                    $templateName
                );
                
                if ($mapping) {
                    // Apply manufacturer-specific transformations
                    $mapping['value'] = $this->templateHandler->applyManufacturerSpecificTransformations(
                        $manufacturerName,
                        $fieldName,
                        $mapping['value']
                    );
                    
                    // Enrich with metadata
                    $mapping = $this->templateHandler->enrichFieldWithMetadata(
                        $manufacturerName,
                        $fieldName,
                        $mapping
                    );
                    
                    $mappedData[$fieldName] = $mapping;
                } else {
                    $unmappedFields[] = $fieldName;
                }
            }
            
            // Apply fallback strategies for unmapped fields
            if (!empty($unmappedFields)) {
                $mappedData = $this->fallbackStrategy->applyFallbacks(
                    $mappedData,
                    $unmappedFields,
                    $manufacturerName
                );
                
                // Handle remaining unmapped fields
                foreach ($unmappedFields as $field) {
                    if (!isset($mappedData[$field])) {
                        $mappedData[$field] = $this->fallbackStrategy->handleUnmappableField(
                            $field,
                            $manufacturerName
                        );
                    }
                }
            }
            
            // Validate mapped data
            $validationResults = $this->validationEngine->validateMappedData(
                $mappedData,
                $manufacturerName,
                $templateName
            );
            
            // Build response
            $response = [
                'success' => $validationResults['valid'],
                'manufacturer' => $manufacturerName,
                'template' => $templateName,
                'mapped_fields' => $this->formatMappedFields($mappedData),
                'mapped_fields_detailed' => $mappedData, // Include full mapping data
                'validation' => $validationResults,
                'statistics' => [
                    'total_fields' => count($templateFields),
                    'mapped_fields' => count(array_filter($mappedData, fn($m) => $m['strategy'] !== 'unmappable')),
                    'fallback_fields' => count(array_filter($mappedData, fn($m) => in_array($m['strategy'], ['default', 'derived', 'conditional_default']))),
                    'unmapped_fields' => count(array_filter($mappedData, fn($m) => $m['strategy'] === 'unmappable')),
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
            ];
            
            // Audit the mapping
            $this->auditMapping($mappedData, $manufacturerId, $templateName, $validationResults['valid']);
            
            // Cache successful mappings
            if ($validationResults['valid']) {
                $this->cacheSuccessfulMapping($fhirData, $additionalData, $manufacturerId, $templateName, $response);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("IVR mapping failed", [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'template' => $templateName,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'error' => 'Mapping failed: ' . $e->getMessage(),
                'manufacturer' => isset($manufacturer) ? $manufacturer->name : 'Unknown',
                'template' => $templateName,
                'mapped_fields' => [],
                'mapped_fields_detailed' => [],
                'validation' => ['valid' => false, 'errors' => ['system' => [$e->getMessage()]]],
                'statistics' => [
                    'total_fields' => 0,
                    'mapped_fields' => 0,
                    'fallback_fields' => 0,
                    'unmapped_fields' => 0,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
            ];
        }
    }

    protected function prepareAvailableData(array $fhirData, array $additionalData): array
    {
        $flattened = [];
        
        // Flatten FHIR data with dot notation
        $this->flattenArray($fhirData, $flattened, 'fhir');
        
        // Add additional data
        foreach ($additionalData as $key => $value) {
            if (!is_array($value) || $this->isAssociativeArray($value)) {
                $flattened[$key] = $value;
            } else {
                $this->flattenArray($value, $flattened, $key);
            }
        }
        
        return $flattened;
    }

    protected function flattenArray(array $array, array &$result, string $prefix = ''): void
    {
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value) && !empty($value)) {
                if ($this->isAssociativeArray($value)) {
                    $this->flattenArray($value, $result, $newKey);
                } else {
                    // Handle arrays - take first element for now
                    if (isset($value[0])) {
                        if (is_array($value[0])) {
                            $this->flattenArray($value[0], $result, $newKey);
                        } else {
                            $result[$newKey] = $value[0];
                        }
                    }
                }
            } else {
                $result[$newKey] = $value;
            }
        }
    }

    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function getTemplateFields(int $manufacturerId, string $templateName): array
    {
        $cacheKey = "template_fields:{$manufacturerId}:{$templateName}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($manufacturerId, $templateName) {
            return IVRTemplateField::forTemplate($manufacturerId, $templateName)
                ->orderBy('field_order')
                ->get()
                ->toArray();
        });
    }

    protected function formatMappedFields(array $mappedData): array
    {
        $formatted = [];
        
        foreach ($mappedData as $fieldName => $data) {
            $formatted[$fieldName] = $data['value'] ?? '';
        }
        
        return $formatted;
    }

    protected function auditMapping(
        array $mappedData,
        int $manufacturerId,
        string $templateName,
        bool $wasSuccessful
    ): void {
        DB::transaction(function () use ($mappedData, $manufacturerId, $templateName, $wasSuccessful) {
            foreach ($mappedData as $fieldName => $data) {
                try {
                    IVRMappingAudit::create([
                        'manufacturer_id' => $manufacturerId,
                        'template_name' => $templateName,
                        'fhir_path' => $data['fhir_path'] ?? '',
                        'ivr_field_name' => $fieldName,
                        'mapped_value' => substr($data['value'] ?? '', 0, 255), // Truncate for privacy
                        'mapping_strategy' => $data['strategy'] ?? 'unknown',
                        'confidence_score' => $data['confidence'] ?? 0,
                        'was_successful' => $wasSuccessful && ($data['strategy'] !== 'unmappable'),
                        'user_id' => auth()->id(),
                        'session_id' => session()->getId(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to audit field mapping", [
                        'field' => $fieldName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    protected function cacheSuccessfulMapping(
        array $fhirData,
        array $additionalData,
        int $manufacturerId,
        string $templateName,
        array $response
    ): void {
        try {
            $cacheKey = $this->generateCacheKey($fhirData, $additionalData, $manufacturerId, $templateName);
            Cache::put($cacheKey, $response, now()->addMinutes(30));
        } catch (\Exception $e) {
            Log::warning("Failed to cache mapping", ['error' => $e->getMessage()]);
        }
    }

    protected function generateCacheKey(
        array $fhirData,
        array $additionalData,
        int $manufacturerId,
        string $templateName
    ): string {
        $dataHash = md5(json_encode([
            'fhir' => $fhirData,
            'additional' => $additionalData,
        ]));
        
        return "ivr_mapping:{$manufacturerId}:{$templateName}:{$dataHash}";
    }

    public function analyzeTemplateCompatibility(int $manufacturerId, string $templateName): array
    {
        $manufacturer = Manufacturer::findOrFail($manufacturerId);
        $templateFields = $this->getTemplateFields($manufacturerId, $templateName);
        
        // Get existing mappings
        $existingMappings = IVRFieldMapping::forManufacturerTemplate($manufacturerId, $templateName)
            ->get()
            ->keyBy('ivr_field_name');
        
        $analysis = [
            'manufacturer' => $manufacturer->name,
            'template' => $templateName,
            'total_fields' => count($templateFields),
            'mapped_fields' => 0,
            'high_confidence_fields' => 0,
            'low_confidence_fields' => 0,
            'unmapped_fields' => [],
            'field_analysis' => [],
        ];
        
        foreach ($templateFields as $field) {
            $fieldAnalysis = [
                'field_name' => $field['field_name'],
                'field_type' => $field['field_type'],
                'is_required' => $field['is_required'],
                'section' => $field['section'],
            ];
            
            if (isset($existingMappings[$field['field_name']])) {
                $mapping = $existingMappings[$field['field_name']];
                $fieldAnalysis['mapped'] = true;
                $fieldAnalysis['confidence'] = $mapping->confidence_score;
                $fieldAnalysis['mapping_type'] = $mapping->mapping_type;
                $fieldAnalysis['usage_count'] = $mapping->usage_count;
                $fieldAnalysis['success_rate'] = $mapping->usage_count > 0 
                    ? round($mapping->success_count / $mapping->usage_count * 100, 2) 
                    : 0;
                
                $analysis['mapped_fields']++;
                
                if ($mapping->confidence_score >= 0.8) {
                    $analysis['high_confidence_fields']++;
                } else {
                    $analysis['low_confidence_fields']++;
                }
            } else {
                $fieldAnalysis['mapped'] = false;
                $fieldAnalysis['suggested_source'] = $this->fallbackStrategy->suggestDataSource($field['field_name']);
                $analysis['unmapped_fields'][] = $field['field_name'];
            }
            
            $analysis['field_analysis'][] = $fieldAnalysis;
        }
        
        $analysis['compatibility_score'] = $analysis['total_fields'] > 0 
            ? round(($analysis['mapped_fields'] / $analysis['total_fields']) * 100, 2)
            : 0;
        
        return $analysis;
    }

    public function getMappingStatistics(int $manufacturerId = null): array
    {
        $query = IVRMappingAudit::query();
        
        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }
        
        $stats = [
            'total_mappings' => $query->count(),
            'successful_mappings' => $query->where('was_successful', true)->count(),
            'failed_mappings' => $query->where('was_successful', false)->count(),
            'by_strategy' => [],
            'by_manufacturer' => [],
            'recent_activity' => [],
        ];
        
        // Stats by strategy
        $strategyStats = IVRMappingAudit::select('mapping_strategy', DB::raw('COUNT(*) as count'), DB::raw('AVG(confidence_score) as avg_confidence'))
            ->when($manufacturerId, fn($q) => $q->where('manufacturer_id', $manufacturerId))
            ->groupBy('mapping_strategy')
            ->get();
        
        foreach ($strategyStats as $stat) {
            $stats['by_strategy'][$stat->mapping_strategy] = [
                'count' => $stat->count,
                'avg_confidence' => round($stat->avg_confidence, 2),
            ];
        }
        
        // Stats by manufacturer
        if (!$manufacturerId) {
            $manufacturerStats = IVRMappingAudit::select('manufacturer_id', DB::raw('COUNT(*) as count'))
                ->with('manufacturer:id,name')
                ->groupBy('manufacturer_id')
                ->get();
            
            foreach ($manufacturerStats as $stat) {
                $stats['by_manufacturer'][$stat->manufacturer->name ?? 'Unknown'] = $stat->count;
            }
        }
        
        // Recent activity
        $recentActivity = IVRMappingAudit::with(['manufacturer:id,name', 'user:id,name'])
            ->when($manufacturerId, fn($q) => $q->where('manufacturer_id', $manufacturerId))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        foreach ($recentActivity as $activity) {
            $stats['recent_activity'][] = [
                'timestamp' => $activity->created_at->toIso8601String(),
                'manufacturer' => $activity->manufacturer->name ?? 'Unknown',
                'template' => $activity->template_name,
                'field' => $activity->ivr_field_name,
                'strategy' => $activity->mapping_strategy,
                'confidence' => $activity->confidence_score,
                'success' => $activity->was_successful,
                'user' => $activity->user->name ?? 'System',
            ];
        }
        
        $stats['success_rate'] = $stats['total_mappings'] > 0 
            ? round($stats['successful_mappings'] / $stats['total_mappings'] * 100, 2) 
            : 0;
        
        return $stats;
    }
}