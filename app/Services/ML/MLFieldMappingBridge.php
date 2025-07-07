<?php

namespace App\Services\ML;

use App\Services\UnifiedFieldMappingService;
use App\Services\FieldMapping\FieldTransformer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class MLFieldMappingBridge
{
    private string $pythonMLServerUrl;
    private UnifiedFieldMappingService $unifiedMapping;
    private FieldTransformer $fieldTransformer;
    private int $confidenceThreshold = 80; // 80% confidence minimum
    
    public function __construct(
        UnifiedFieldMappingService $unifiedMapping,
        FieldTransformer $fieldTransformer
    ) {
        $this->unifiedMapping = $unifiedMapping;
        $this->fieldTransformer = $fieldTransformer;
        $this->pythonMLServerUrl = config('ml.field_mapping_server_url', 'http://localhost:8000');
    }
    
    /**
     * Get ML-enhanced field mapping predictions for a manufacturer
     */
    public function predictFieldMappings(
        string $manufacturerName,
        string $documentType = 'IVR',
        array $sourceFields = [],
        array $contextData = []
    ): array {
        try {
            // First try to get predictions from ML system
            $mlPredictions = $this->getMlPredictions($manufacturerName, $documentType, $sourceFields, $contextData);
            
            // Fallback to existing unified mapping service
            $fallbackMappings = $this->unifiedMapping->getManufacturerConfig($manufacturerName, $documentType);
            
            // Combine ML predictions with fallback mappings
            return $this->combinePredictions($mlPredictions, $fallbackMappings, $sourceFields);
            
        } catch (Exception $e) {
            Log::warning('ML field mapping failed, using fallback', [
                'manufacturer' => $manufacturerName,
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            
            // Return fallback mappings only
            return $this->unifiedMapping->getManufacturerConfig($manufacturerName, $documentType);
        }
    }
    
    /**
     * Record field mapping result for ML training
     */
    public function recordMappingResult(
        string $sourceField,
        string $targetField,
        string $manufacturerName,
        string $documentType,
        float $confidence,
        bool $success,
        string $mappingMethod = 'ai',
        ?string $userFeedback = null
    ): void {
        try {
            $payload = [
                'source_field' => $sourceField,
                'target_field' => $targetField,
                'manufacturer' => $manufacturerName,
                'document_type' => $documentType,
                'confidence' => $confidence,
                'success' => $success,
                'mapping_method' => $mappingMethod,
                'user_feedback' => $userFeedback
            ];
            
            // Send to Python ML system for training
            Http::timeout(5)->post($this->pythonMLServerUrl . '/api/record-mapping', $payload);
            
            Log::info('ML field mapping result recorded', [
                'source_field' => $sourceField,
                'target_field' => $targetField,
                'manufacturer' => $manufacturerName,
                'success' => $success,
                'confidence' => $confidence
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to record ML mapping result', [
                'error' => $e->getMessage(),
                'source_field' => $sourceField,
                'target_field' => $targetField
            ]);
        }
    }
    
    /**
     * Get ML predictions from Python server
     */
    private function getMlPredictions(
        string $manufacturerName,
        string $documentType,
        array $sourceFields,
        array $contextData
    ): array {
        $cacheKey = "ml_predictions:{$manufacturerName}:{$documentType}:" . md5(json_encode($sourceFields));
        
        return Cache::remember($cacheKey, 300, function() use ($manufacturerName, $documentType, $sourceFields, $contextData) {
            $predictions = [];
            
            foreach ($sourceFields as $sourceField) {
                try {
                    $response = Http::timeout(10)->post($this->pythonMLServerUrl . '/api/predict', [
                        'source_field' => $sourceField,
                        'manufacturer' => $manufacturerName,
                        'document_type' => $documentType,
                        'context' => $contextData
                    ]);
                    
                    if ($response->successful()) {
                        $prediction = $response->json();
                        
                        // Only use predictions above confidence threshold
                        if ($prediction['confidence'] >= ($this->confidenceThreshold / 100)) {
                            $predictions[$sourceField] = [
                                'predicted_field' => $prediction['predicted_field'],
                                'confidence' => $prediction['confidence'],
                                'alternatives' => $prediction['alternative_suggestions'] ?? [],
                                'model_used' => $prediction['model_used'] ?? 'unknown'
                            ];
                        }
                    }
                    
                } catch (Exception $e) {
                    Log::debug('ML prediction failed for field', [
                        'source_field' => $sourceField,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $predictions;
        });
    }
    
    /**
     * Combine ML predictions with fallback mappings
     */
    private function combinePredictions(array $mlPredictions, array $fallbackMappings, array $sourceFields): array {
        $combinedMappings = [];
        
        foreach ($sourceFields as $sourceField) {
            if (isset($mlPredictions[$sourceField])) {
                // Use ML prediction
                $prediction = $mlPredictions[$sourceField];
                $combinedMappings[$sourceField] = [
                    'target_field' => $prediction['predicted_field'],
                    'confidence' => $prediction['confidence'],
                    'method' => 'ml_prediction',
                    'model_used' => $prediction['model_used'],
                    'alternatives' => $prediction['alternatives']
                ];
            } elseif (isset($fallbackMappings['fields'][$sourceField])) {
                // Use fallback mapping
                $fallback = $fallbackMappings['fields'][$sourceField];
                $combinedMappings[$sourceField] = [
                    'target_field' => $fallback['source'] ?? $sourceField,
                    'confidence' => 0.7, // Default confidence for fallback mappings
                    'method' => 'fallback_mapping',
                    'transform' => $fallback['transform'] ?? null
                ];
            } else {
                // No mapping found, use heuristic
                $combinedMappings[$sourceField] = [
                    'target_field' => $this->generateHeuristicMapping($sourceField),
                    'confidence' => 0.5,
                    'method' => 'heuristic'
                ];
            }
        }
        
        return $combinedMappings;
    }
    
    /**
     * Generate heuristic mapping based on field name patterns
     */
    private function generateHeuristicMapping(string $sourceField): string {
        $sourceFieldLower = strtolower($sourceField);
        
        // Common field patterns
        $patterns = [
            'patient_name' => 'patient_full_name',
            'patient_first_name' => 'patient_name_first',
            'patient_last_name' => 'patient_name_last',
            'dob' => 'date_of_birth',
            'date_of_birth' => 'patient_dob',
            'insurance_id' => 'member_id',
            'member_id' => 'insurance_member_id',
            'npi' => 'provider_npi',
            'provider_npi' => 'physician_npi',
            'facility_name' => 'practice_name',
            'practice_name' => 'facility_name',
            'diagnosis_code' => 'icd_code',
            'icd_code' => 'diagnosis_code',
            'wound_size' => 'wound_dimensions',
            'wound_location' => 'wound_site'
        ];
        
        foreach ($patterns as $pattern => $mapping) {
            if (str_contains($sourceFieldLower, $pattern)) {
                return $mapping;
            }
        }
        
        // If no pattern matches, return the original field with standard formatting
        return str_replace([' ', '-'], '_', $sourceFieldLower);
    }
    
    /**
     * Train ML models with new data
     */
    public function triggerMLTraining(bool $force = false): array {
        try {
            $response = Http::timeout(30)->post($this->pythonMLServerUrl . '/api/train', [
                'force' => $force
            ]);
            
            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('ML model training completed', [
                    'training_samples' => $result['training_samples'] ?? 0,
                    'model_results' => $result['results'] ?? []
                ]);
                
                return $result;
            }
            
            throw new Exception('Training request failed: ' . $response->body());
            
        } catch (Exception $e) {
            Log::error('ML training failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get ML system analytics
     */
    public function getMLAnalytics(): array {
        try {
            $response = Http::timeout(10)->get($this->pythonMLServerUrl . '/api/analytics');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'error' => 'Failed to fetch ML analytics',
                'ml_server_available' => false
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get ML analytics', ['error' => $e->getMessage()]);
            
            return [
                'error' => $e->getMessage(),
                'ml_server_available' => false
            ];
        }
    }
    
    /**
     * Enhanced field mapping with ML for IVR forms
     */
    public function mapIVRFieldsWithML(
        array $formData,
        string $manufacturerName,
        string $templateId,
        array $templateFields = []
    ): array {
        // Get source fields from form data
        $sourceFields = array_keys($formData);
        
        // Get ML predictions
        $predictions = $this->predictFieldMappings(
            $manufacturerName,
            'IVR',
            $sourceFields,
            [
                'template_id' => $templateId,
                'template_fields' => $templateFields,
                'form_data_sample' => array_slice($formData, 0, 10, true) // Sample for context
            ]
        );
        
        // Apply mappings to form data
        $mappedData = [];
        $mappingResults = [];
        
        foreach ($formData as $sourceField => $value) {
            if (isset($predictions[$sourceField])) {
                $prediction = $predictions[$sourceField];
                $targetField = $prediction['target_field'];
                
                // Apply any transformations
                $transformedValue = $this->applyTransformations($value, $prediction);
                
                $mappedData[$targetField] = $transformedValue;
                
                // Record the mapping result for ML training
                $this->recordMappingResult(
                    $sourceField,
                    $targetField,
                    $manufacturerName,
                    'IVR',
                    $prediction['confidence'],
                    true, // Assume success for now
                    $prediction['method'] ?? 'ml_prediction'
                );
                
                $mappingResults[] = [
                    'source' => $sourceField,
                    'target' => $targetField,
                    'confidence' => $prediction['confidence'],
                    'method' => $prediction['method'] ?? 'unknown',
                    'value' => $transformedValue
                ];
            }
        }
        
        Log::info('ML-enhanced IVR field mapping completed', [
            'manufacturer' => $manufacturerName,
            'template_id' => $templateId,
            'source_fields' => count($sourceFields),
            'mapped_fields' => count($mappedData),
            'mapping_results' => $mappingResults
        ]);
        
        return [
            'mapped_data' => $mappedData,
            'mapping_results' => $mappingResults,
            'unmapped_fields' => array_diff($sourceFields, array_keys($predictions))
        ];
    }
    
    /**
     * Apply transformations to field values
     */
    private function applyTransformations($value, array $prediction): mixed {
        if (isset($prediction['transform'])) {
            // Use existing field transformer
            return $this->fieldTransformer->transform($value, $prediction['transform']);
        }
        
        return $value;
    }
} 