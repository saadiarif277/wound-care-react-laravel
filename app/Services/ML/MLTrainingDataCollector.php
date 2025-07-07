<?php

namespace App\Services\ML;

use App\Services\ML\MLFieldMappingBridge;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MLTrainingDataCollector
{
    private MLFieldMappingBridge $mlBridge;
    private array $batchedData = [];
    private int $batchSize = 100;
    
    public function __construct(MLFieldMappingBridge $mlBridge)
    {
        $this->mlBridge = $mlBridge;
    }
    
    /**
     * Collect field mapping data from various Laravel sources for ML training
     */
    public function collectTrainingData(array $options = []): array
    {
        $startTime = microtime(true);
        $results = [
            'total_mappings_collected' => 0,
            'sources' => [],
            'errors' => [],
            'training_data_submitted' => false,
            'batch_submissions' => 0
        ];
        
        try {
            // 1. Collect from IVR field mappings
            $ivrResults = $this->collectFromIVRFieldMappings($options);
            $results['sources']['ivr_field_mappings'] = $ivrResults;
            $results['total_mappings_collected'] += $ivrResults['count'];
            
            // 2. Collect from PDF field metadata
            $pdfResults = $this->collectFromPdfFieldMetadata($options);
            $results['sources']['pdf_field_metadata'] = $pdfResults;
            $results['total_mappings_collected'] += $pdfResults['count'];
            
            // 3. Collect from template field mappings (if the table exists)
            $templateResults = $this->collectFromTemplateFieldMappings($options);
            $results['sources']['template_field_mappings'] = $templateResults;
            $results['total_mappings_collected'] += $templateResults['count'];
            
            // 4. Collect from docuseal field mappings
            $docusealResults = $this->collectFromDocusealMappings($options);
            $results['sources']['docuseal_mappings'] = $docusealResults;
            $results['total_mappings_collected'] += $docusealResults['count'];
            
            // 5. Submit batched training data to ML system
            if (!empty($this->batchedData)) {
                $submissionResults = $this->submitBatchedTrainingData();
                $results['training_data_submitted'] = $submissionResults['success'];
                $results['batch_submissions'] = $submissionResults['batches_sent'];
                
                if (!$submissionResults['success']) {
                    $results['errors'][] = $submissionResults['error'];
                }
            }
            
            $results['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('ML training data collection completed', $results);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('ML training data collection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $results['errors'][] = $e->getMessage();
            return $results;
        }
    }
    
    /**
     * Collect training data from IVR field mappings table
     */
    private function collectFromIVRFieldMappings(array $options = []): array
    {
        try {
            $limit = $options['limit'] ?? 1000;
            $since = $options['since'] ?? Carbon::now()->subDays(30);
            
            $query = DB::table('ivr_field_mappings')
                ->join('manufacturers', 'ivr_field_mappings.manufacturer_id', '=', 'manufacturers.id')
                ->where('ivr_field_mappings.created_at', '>=', $since)
                ->orderBy('ivr_field_mappings.created_at', 'desc')
                ->limit($limit);
            
            $mappings = $query->get([
                'ivr_field_mappings.*',
                'manufacturers.name as manufacturer_name'
            ]);
            
            $count = 0;
            foreach ($mappings as $mapping) {
                $this->addToBatch([
                    'source_field' => $mapping->source_field,
                    'target_field' => $mapping->target_field,
                    'manufacturer' => $mapping->manufacturer_name,
                    'document_type' => 'IVR',
                    'confidence' => $mapping->confidence ?? 0.8,
                    'success' => true, // Assume existing mappings are successful
                    'mapping_method' => $mapping->match_type ?? 'manual',
                    'user_feedback' => null,
                    'metadata' => [
                        'source' => 'ivr_field_mappings',
                        'template_id' => $mapping->template_id,
                        'usage_count' => $mapping->usage_count ?? 0,
                        'created_at' => $mapping->created_at
                    ]
                ]);
                $count++;
            }
            
            return [
                'count' => $count,
                'table' => 'ivr_field_mappings',
                'status' => 'success'
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to collect from IVR field mappings', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'count' => 0,
                'table' => 'ivr_field_mappings',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Collect training data from PDF field metadata
     */
    private function collectFromPdfFieldMetadata(array $options = []): array
    {
        try {
            $limit = $options['limit'] ?? 1000;
            $since = $options['since'] ?? Carbon::now()->subDays(30);
            
            $query = DB::table('pdf_field_metadata')
                ->join('manufacturers', 'pdf_field_metadata.manufacturer_id', '=', 'manufacturers.id')
                ->where('pdf_field_metadata.extracted_at', '>=', $since)
                ->where('pdf_field_metadata.extraction_verified', true)
                ->where('pdf_field_metadata.confidence_score', '>=', 0.7)
                ->orderBy('pdf_field_metadata.usage_frequency', 'desc')
                ->limit($limit);
            
            $fields = $query->get([
                'pdf_field_metadata.*',
                'manufacturers.name as manufacturer_name'
            ]);
            
            $count = 0;
            foreach ($fields as $field) {
                // Create training data based on field categorization
                if ($field->medical_category) {
                    $targetField = $this->mapMedicalCategoryToCanonicalField($field->medical_category, $field->field_name_normalized);
                    
                    $this->addToBatch([
                        'source_field' => $field->field_name_normalized,
                        'target_field' => $targetField,
                        'manufacturer' => $field->manufacturer_name,
                        'document_type' => 'PDF',
                        'confidence' => $field->confidence_score,
                        'success' => true,
                        'mapping_method' => 'ai_categorization',
                        'user_feedback' => null,
                        'metadata' => [
                            'source' => 'pdf_field_metadata',
                            'medical_category' => $field->medical_category,
                            'field_type' => $field->field_type,
                            'usage_frequency' => $field->usage_frequency,
                            'extracted_at' => $field->extracted_at
                        ]
                    ]);
                    $count++;
                }
            }
            
            return [
                'count' => $count,
                'table' => 'pdf_field_metadata',
                'status' => 'success'
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to collect from PDF field metadata', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'count' => 0,
                'table' => 'pdf_field_metadata',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Collect training data from template field mappings
     */
    private function collectFromTemplateFieldMappings(array $options = []): array
    {
        try {
            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable('template_field_mappings')) {
                return [
                    'count' => 0,
                    'table' => 'template_field_mappings',
                    'status' => 'skipped',
                    'reason' => 'table_not_exists'
                ];
            }
            
            $limit = $options['limit'] ?? 500;
            $since = $options['since'] ?? Carbon::now()->subDays(30);
            
            $mappings = DB::table('template_field_mappings')
                ->where('updated_at', '>=', $since)
                ->where('is_active', true)
                ->limit($limit)
                ->get();
            
            $count = 0;
            foreach ($mappings as $mapping) {
                $this->addToBatch([
                    'source_field' => $mapping->field_name,
                    'target_field' => $mapping->canonical_field_path ?? $mapping->field_name,
                    'manufacturer' => 'generic', // Template mappings may not have specific manufacturer
                    'document_type' => 'TEMPLATE',
                    'confidence' => 0.9, // Template mappings are usually manually verified
                    'success' => $mapping->validation_status !== 'error',
                    'mapping_method' => 'template_mapping',
                    'user_feedback' => null,
                    'metadata' => [
                        'source' => 'template_field_mappings',
                        'template_id' => $mapping->template_id,
                        'validation_status' => $mapping->validation_status,
                        'updated_at' => $mapping->updated_at
                    ]
                ]);
                $count++;
            }
            
            return [
                'count' => $count,
                'table' => 'template_field_mappings',
                'status' => 'success'
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to collect from template field mappings', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'count' => 0,
                'table' => 'template_field_mappings',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Collect training data from Docuseal mappings
     */
    private function collectFromDocusealMappings(array $options = []): array
    {
        try {
            $limit = $options['limit'] ?? 500;
            $since = $options['since'] ?? Carbon::now()->subDays(30);
            
            // Look for recent successful field mappings in audit logs or mapping tables
            $query = DB::table('mapping_audit_logs')
                ->join('docuseal_templates', 'mapping_audit_logs.template_id', '=', 'docuseal_templates.id')
                ->join('manufacturers', 'docuseal_templates.manufacturer_id', '=', 'manufacturers.id')
                ->where('mapping_audit_logs.created_at', '>=', $since)
                ->where('mapping_audit_logs.action', 'updated')
                ->limit($limit);
            
            $auditLogs = $query->get([
                'mapping_audit_logs.*',
                'manufacturers.name as manufacturer_name',
                'docuseal_templates.template_name'
            ]);
            
            $count = 0;
            foreach ($auditLogs as $log) {
                $changes = is_string($log->changes) ? json_decode($log->changes, true) : $log->changes;
                
                if (isset($changes['after']['field_name']) && isset($changes['after']['canonical_field_path'])) {
                    $this->addToBatch([
                        'source_field' => $changes['after']['field_name'],
                        'target_field' => $changes['after']['canonical_field_path'],
                        'manufacturer' => $log->manufacturer_name,
                        'document_type' => 'DOCUSEAL',
                        'confidence' => 0.95, // Manual mappings have high confidence
                        'success' => true,
                        'mapping_method' => 'manual_docuseal',
                        'user_feedback' => 'positive',
                        'metadata' => [
                            'source' => 'docuseal_mappings',
                            'template_name' => $log->template_name,
                            'template_id' => $log->template_id,
                            'user_id' => $log->user_id,
                            'created_at' => $log->created_at
                        ]
                    ]);
                    $count++;
                }
            }
            
            return [
                'count' => $count,
                'table' => 'mapping_audit_logs',
                'status' => 'success'
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to collect from Docuseal mappings', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'count' => 0,
                'table' => 'mapping_audit_logs',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Add training data to batch for submission
     */
    private function addToBatch(array $trainingData): void
    {
        $this->batchedData[] = $trainingData;
        
        // Submit batch if it reaches the batch size
        if (count($this->batchedData) >= $this->batchSize) {
            $this->submitBatch();
        }
    }
    
    /**
     * Submit a batch of training data to ML system
     */
    private function submitBatch(): void
    {
        try {
            foreach ($this->batchedData as $data) {
                $this->mlBridge->recordMappingResult(
                    $data['source_field'],
                    $data['target_field'],
                    $data['manufacturer'],
                    $data['document_type'],
                    $data['confidence'],
                    $data['success'],
                    $data['mapping_method'],
                    $data['user_feedback']
                );
            }
            
            Log::info('Submitted training data batch to ML system', [
                'batch_size' => count($this->batchedData)
            ]);
            
            // Clear the batch
            $this->batchedData = [];
            
        } catch (\Exception $e) {
            Log::error('Failed to submit training data batch', [
                'batch_size' => count($this->batchedData),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Submit any remaining batched training data
     */
    private function submitBatchedTrainingData(): array
    {
        $batches = 0;
        $errors = [];
        
        try {
            if (!empty($this->batchedData)) {
                $this->submitBatch();
                $batches++;
            }
            
            return [
                'success' => true,
                'batches_sent' => $batches,
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'batches_sent' => $batches,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Map medical category to canonical field name
     */
    private function mapMedicalCategoryToCanonicalField(string $category, string $fieldName): string
    {
        $categoryMappings = [
            'patient' => [
                'name' => 'patient_name',
                'first_name' => 'patient_first_name',
                'last_name' => 'patient_last_name',
                'dob' => 'patient_date_of_birth',
                'gender' => 'patient_gender',
                'phone' => 'patient_phone',
                'address' => 'patient_address'
            ],
            'provider' => [
                'name' => 'provider_name',
                'npi' => 'provider_npi',
                'phone' => 'provider_phone',
                'address' => 'provider_address'
            ],
            'facility' => [
                'name' => 'facility_name',
                'npi' => 'facility_npi',
                'phone' => 'facility_phone',
                'address' => 'facility_address'
            ],
            'insurance' => [
                'name' => 'insurance_name',
                'member_id' => 'insurance_member_id',
                'group_number' => 'insurance_group_number',
                'policy_number' => 'insurance_policy_number'
            ]
        ];
        
        $fieldNameLower = strtolower($fieldName);
        
        if (isset($categoryMappings[$category])) {
            foreach ($categoryMappings[$category] as $pattern => $canonicalField) {
                if (str_contains($fieldNameLower, $pattern)) {
                    return $canonicalField;
                }
            }
        }
        
        // Default: return a canonical field name based on category and field
        return $category . '_' . str_replace([' ', '-'], '_', $fieldNameLower);
    }
    
    /**
     * Schedule automatic training data collection
     */
    public function scheduleAutomaticCollection(): void
    {
        $lastCollection = Cache::get('ml_training_last_collection');
        $shouldCollect = !$lastCollection || Carbon::parse($lastCollection)->addHours(6)->isPast();
        
        if ($shouldCollect) {
            try {
                $results = $this->collectTrainingData([
                    'limit' => 500,
                    'since' => Carbon::now()->subHours(12) // Only collect recent data
                ]);
                
                Cache::put('ml_training_last_collection', now()->toISOString(), 3600);
                
                Log::info('Automatic ML training data collection completed', $results);
                
            } catch (\Exception $e) {
                Log::error('Automatic ML training data collection failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Get collection statistics
     */
    public function getCollectionStatistics(): array
    {
        try {
            $stats = [
                'last_collection' => Cache::get('ml_training_last_collection'),
                'available_sources' => [],
                'estimated_total_mappings' => 0
            ];
            
            // Check available data sources
            $sources = [
                'ivr_field_mappings',
                'pdf_field_metadata',
                'template_field_mappings',
                'mapping_audit_logs'
            ];
            
            foreach ($sources as $table) {
                try {
                    if (DB::getSchemaBuilder()->hasTable($table)) {
                        $count = DB::table($table)->count();
                        $stats['available_sources'][$table] = $count;
                        $stats['estimated_total_mappings'] += $count;
                    }
                } catch (\Exception $e) {
                    $stats['available_sources'][$table] = 'error: ' . $e->getMessage();
                }
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'last_collection' => Cache::get('ml_training_last_collection'),
                'available_sources' => [],
                'estimated_total_mappings' => 0
            ];
        }
    }
} 