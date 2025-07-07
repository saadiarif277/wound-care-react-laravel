<?php

namespace App\Services\PDF;

use App\Models\PDF\ManufacturerPdfTemplate;
use App\Models\PDF\PdfFieldMapping;
use App\Services\AI\SmartFieldMappingValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AI-powered field mapping service for PDF templates
 * Provides intelligent suggestions for mapping PDF fields to data sources
 */
class AIFieldMappingService
{
    protected SmartFieldMappingValidator $validator;
    protected PDFDocumentIntelligenceService $documentIntelligence;
    
    public function __construct(
        SmartFieldMappingValidator $validator,
        PDFDocumentIntelligenceService $documentIntelligence
    ) {
        $this->validator = $validator;
        $this->documentIntelligence = $documentIntelligence;
    }
    
    /**
     * Get AI-powered mapping suggestions for a template
     */
    public function getSuggestionsForTemplate(ManufacturerPdfTemplate $template, array $options = []): array
    {
        $cacheKey = "ai_mapping_suggestions_{$template->id}";
        $cacheDuration = $options['cache_duration'] ?? 3600;
        
        return Cache::remember($cacheKey, $cacheDuration, function() use ($template, $options) {
            try {
                // Get available data sources
                $dataSources = $this->getAvailableDataSources();
                
                // Get template fields
                $templateFields = $template->template_fields ?? [];
                if (empty($templateFields)) {
                    return [
                        'success' => false,
                        'message' => 'No template fields found. Please extract fields first.',
                        'suggestions' => []
                    ];
                }
                
                // Get existing mappings to avoid suggesting already mapped fields
                $existingMappings = $template->fieldMappings->pluck('data_source', 'pdf_field_name')->toArray();
                
                // Generate suggestions for each field
                $suggestions = [];
                foreach ($templateFields as $fieldName) {
                    if (!isset($existingMappings[$fieldName])) {
                        $fieldSuggestions = $this->getSuggestionsForField(
                            $fieldName,
                            $template,
                            $dataSources,
                            $options
                        );
                        
                        if (!empty($fieldSuggestions)) {
                            $suggestions[$fieldName] = $fieldSuggestions;
                        }
                    }
                }
                
                // Learn from historical mappings
                $historicalEnhancement = $this->enhanceWithHistoricalData($suggestions, $template);
                $suggestions = array_merge($suggestions, $historicalEnhancement);
                
                return [
                    'success' => true,
                    'suggestions' => $suggestions,
                    'total_fields' => count($templateFields),
                    'mapped_fields' => count($existingMappings),
                    'suggested_fields' => count($suggestions),
                    'confidence_threshold' => $options['min_confidence'] ?? 0.5
                ];
                
            } catch (\Exception $e) {
                Log::error('Failed to generate AI mapping suggestions', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Failed to generate suggestions: ' . $e->getMessage(),
                    'suggestions' => []
                ];
            }
        });
    }
    
    /**
     * Get suggestions for a single field
     */
    public function getSuggestionsForField(
        string $fieldName,
        ManufacturerPdfTemplate $template,
        array $dataSources,
        array $options = []
    ): array {
        $suggestions = [];
        $minConfidence = $options['min_confidence'] ?? 0.5;
        
        // 1. Pattern-based matching
        $patternSuggestions = $this->getPatternBasedSuggestions($fieldName, $dataSources);
        $suggestions = array_merge($suggestions, $patternSuggestions);
        
        // 2. Semantic similarity matching
        $semanticSuggestions = $this->getSemanticSuggestions($fieldName, $dataSources);
        $suggestions = array_merge($suggestions, $semanticSuggestions);
        
        // 3. Context-aware suggestions based on document type
        $contextSuggestions = $this->getContextAwareSuggestions($fieldName, $template, $dataSources);
        $suggestions = array_merge($suggestions, $contextSuggestions);
        
        // 4. Learn from similar templates
        $learnedSuggestions = $this->getLearnedSuggestions($fieldName, $template, $dataSources);
        $suggestions = array_merge($suggestions, $learnedSuggestions);
        
        // Deduplicate and combine scores
        $suggestions = $this->deduplicateAndCombineSuggestions($suggestions);
        
        // Filter by confidence threshold
        $suggestions = array_filter($suggestions, fn($s) => $s['confidence'] >= $minConfidence);
        
        // Sort by confidence
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        // Limit to top suggestions
        return array_slice($suggestions, 0, $options['max_suggestions'] ?? 5);
    }
    
    /**
     * Apply AI suggestions to template
     */
    public function applySuggestions(ManufacturerPdfTemplate $template, array $acceptedSuggestions): array
    {
        $applied = [];
        $failed = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($acceptedSuggestions as $fieldName => $suggestion) {
                try {
                    // Create or update field mapping
                    $mapping = PdfFieldMapping::updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'pdf_field_name' => $fieldName
                        ],
                        [
                            'data_source' => $suggestion['data_source'],
                            'field_type' => $suggestion['field_type'] ?? 'text',
                            'is_required' => $suggestion['is_required'] ?? false,
                            'display_order' => $suggestion['display_order'] ?? 999,
                            'ai_suggested' => true,
                            'ai_confidence' => $suggestion['confidence'],
                            'ai_suggestion_metadata' => [
                                'method' => $suggestion['method'] ?? 'unknown',
                                'reason' => $suggestion['reason'] ?? 'AI suggestion',
                                'suggested_at' => now()->toISOString(),
                                'accepted_by' => auth()->id()
                            ]
                        ]
                    );
                    
                    $applied[] = [
                        'field' => $fieldName,
                        'mapping' => $mapping->data_source,
                        'confidence' => $suggestion['confidence']
                    ];
                    
                    // Record successful mapping for learning
                    $this->recordSuccessfulMapping($template, $fieldName, $suggestion);
                    
                } catch (\Exception $e) {
                    $failed[] = [
                        'field' => $fieldName,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            // Clear cache
            Cache::forget("ai_mapping_suggestions_{$template->id}");
            
            return [
                'success' => true,
                'applied' => $applied,
                'failed' => $failed,
                'total_applied' => count($applied),
                'total_failed' => count($failed)
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'error' => 'Failed to apply suggestions: ' . $e->getMessage(),
                'applied' => [],
                'failed' => []
            ];
        }
    }
    
    /**
     * Get pattern-based suggestions
     */
    protected function getPatternBasedSuggestions(string $fieldName, array $dataSources): array
    {
        $suggestions = [];
        $normalizedField = $this->normalizeFieldName($fieldName);
        
        // Define pattern mappings with confidence scores
        $patterns = [
            // High confidence patterns (0.9+)
            '/^patient[_\s]?name$/i' => ['patient_name', 0.95],
            '/^patient[_\s]?first[_\s]?name$/i' => ['patient_first_name', 0.95],
            '/^patient[_\s]?last[_\s]?name$/i' => ['patient_last_name', 0.95],
            '/^(dob|date[_\s]?of[_\s]?birth)$/i' => ['patient_dob', 0.95],
            '/^member[_\s]?id$/i' => ['patient_member_id', 0.95],
            '/^provider[_\s]?npi$/i' => ['provider_npi', 0.95],
            '/^signature[_\s]?date$/i' => ['signature_date', 0.95],
            
            // Good confidence patterns (0.8-0.9)
            '/patient.*phone/i' => ['patient_phone', 0.85],
            '/patient.*email/i' => ['patient_email', 0.85],
            '/patient.*address/i' => ['patient_address_line1', 0.85],
            '/city/i' => ['patient_city', 0.85],
            '/state/i' => ['patient_state', 0.85],
            '/zip/i' => ['patient_zip', 0.85],
            
            // Medium confidence patterns (0.7-0.8)
            '/insurance.*name/i' => ['primary_insurance_name', 0.75],
            '/policy.*number/i' => ['primary_member_id', 0.75],
            '/group.*number/i' => ['primary_group_number', 0.75],
            '/provider.*name/i' => ['provider_name', 0.75],
            '/facility.*name/i' => ['facility_name', 0.75],
            
            // Lower confidence patterns (0.5-0.7)
            '/phone/i' => ['patient_phone', 0.6],
            '/email/i' => ['patient_email', 0.6],
            '/name/i' => ['patient_name', 0.5],
            '/date/i' => ['signature_date', 0.5],
        ];
        
        foreach ($patterns as $pattern => $mapping) {
            if (preg_match($pattern, $normalizedField)) {
                list($dataSource, $confidence) = $mapping;
                
                // Check if data source exists
                if ($this->dataSourceExists($dataSource, $dataSources)) {
                    $suggestions[] = [
                        'data_source' => $dataSource,
                        'confidence' => $confidence,
                        'method' => 'pattern',
                        'reason' => "Field name matches pattern: {$pattern}",
                        'field_type' => $this->detectFieldType($dataSource)
                    ];
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get semantic suggestions using similarity matching
     */
    protected function getSemanticSuggestions(string $fieldName, array $dataSources): array
    {
        $suggestions = [];
        $normalizedField = $this->normalizeFieldName($fieldName);
        
        // Flatten data sources
        $flatSources = [];
        foreach ($dataSources as $category => $sources) {
            foreach ($sources as $source => $label) {
                $flatSources[$source] = [
                    'label' => $label,
                    'category' => $category
                ];
            }
        }
        
        // Calculate similarity scores
        foreach ($flatSources as $source => $info) {
            $similarity = $this->calculateSemanticSimilarity($normalizedField, $source, $info['label']);
            
            if ($similarity > 0.5) {
                $suggestions[] = [
                    'data_source' => $source,
                    'confidence' => $similarity,
                    'method' => 'semantic',
                    'reason' => "Semantic similarity with '{$info['label']}'",
                    'field_type' => $this->detectFieldType($source),
                    'category' => $info['category']
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get context-aware suggestions based on document type
     */
    protected function getContextAwareSuggestions(
        string $fieldName,
        ManufacturerPdfTemplate $template,
        array $dataSources
    ): array {
        $suggestions = [];
        
        // Document type specific mappings
        $contextMappings = [
            'ivr' => [
                'patient_signature' => ['patient_name', 0.7],
                'auth_signature' => ['patient_name', 0.7],
                'physician_signature' => ['provider_name', 0.8],
                'diagnosis' => ['primary_diagnosis_code', 0.85],
                'icd10' => ['primary_diagnosis_code', 0.9],
                'hcpcs' => ['product_code', 0.85],
            ],
            'order_form' => [
                'item_code' => ['product_code', 0.9],
                'quantity' => ['quantity', 0.95],
                'size' => ['product_size', 0.9],
                'ship_to' => ['patient_address_line1', 0.7],
                'delivery_date' => ['expected_service_date', 0.8],
            ],
            'shipping_label' => [
                'recipient' => ['patient_name', 0.85],
                'delivery_address' => ['patient_address_line1', 0.9],
                'tracking' => ['order_number', 0.7],
            ]
        ];
        
        $docType = $template->document_type;
        if (isset($contextMappings[$docType])) {
            $normalizedField = $this->normalizeFieldName($fieldName);
            
            foreach ($contextMappings[$docType] as $pattern => $mapping) {
                if (stripos($normalizedField, $pattern) !== false) {
                    list($dataSource, $confidence) = $mapping;
                    
                    if ($this->dataSourceExists($dataSource, $dataSources)) {
                        $suggestions[] = [
                            'data_source' => $dataSource,
                            'confidence' => $confidence,
                            'method' => 'context',
                            'reason' => "Common field for {$docType} documents",
                            'field_type' => $this->detectFieldType($dataSource)
                        ];
                    }
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get suggestions learned from similar templates
     */
    protected function getLearnedSuggestions(
        string $fieldName,
        ManufacturerPdfTemplate $template,
        array $dataSources
    ): array {
        $suggestions = [];
        
        // Find similar templates
        $similarTemplates = ManufacturerPdfTemplate::where('manufacturer_id', $template->manufacturer_id)
            ->where('document_type', $template->document_type)
            ->where('id', '!=', $template->id)
            ->with('fieldMappings')
            ->limit(10)
            ->get();
        
        // Look for similar field mappings
        $mappingCounts = [];
        foreach ($similarTemplates as $similarTemplate) {
            foreach ($similarTemplate->fieldMappings as $mapping) {
                // Check if field names are similar
                $similarity = $this->calculateFieldNameSimilarity($fieldName, $mapping->pdf_field_name);
                
                if ($similarity > 0.7) {
                    if (!isset($mappingCounts[$mapping->data_source])) {
                        $mappingCounts[$mapping->data_source] = [
                            'count' => 0,
                            'total_confidence' => 0,
                            'field_type' => $mapping->field_type
                        ];
                    }
                    
                    $mappingCounts[$mapping->data_source]['count']++;
                    $mappingCounts[$mapping->data_source]['total_confidence'] += $similarity;
                }
            }
        }
        
        // Convert counts to suggestions
        foreach ($mappingCounts as $dataSource => $info) {
            if ($this->dataSourceExists($dataSource, $dataSources)) {
                $avgConfidence = $info['total_confidence'] / $info['count'];
                $confidence = min(0.9, $avgConfidence * ($info['count'] / 10)); // Scale by frequency
                
                $suggestions[] = [
                    'data_source' => $dataSource,
                    'confidence' => $confidence,
                    'method' => 'learned',
                    'reason' => "Used in {$info['count']} similar templates",
                    'field_type' => $info['field_type'],
                    'similar_templates' => $info['count']
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Calculate semantic similarity between field names
     */
    protected function calculateSemanticSimilarity(string $field1, string $field2, string $label): float
    {
        // Tokenize and normalize
        $tokens1 = $this->tokenizeFieldName($field1);
        $tokens2 = $this->tokenizeFieldName($field2);
        $tokensLabel = $this->tokenizeFieldName($label);
        
        // Calculate token overlap
        $overlap1 = count(array_intersect($tokens1, $tokens2));
        $overlap2 = count(array_intersect($tokens1, $tokensLabel));
        $maxOverlap = max($overlap1, $overlap2);
        
        if ($maxOverlap === 0) {
            return 0.0;
        }
        
        // Calculate Jaccard similarity
        $union = count(array_unique(array_merge($tokens1, $tokens2, $tokensLabel)));
        $similarity = $maxOverlap / $union;
        
        // Boost score for exact substring matches
        if (stripos($field2, $field1) !== false || stripos($field1, $field2) !== false) {
            $similarity = min(1.0, $similarity + 0.3);
        }
        
        return $similarity;
    }
    
    /**
     * Calculate similarity between field names
     */
    protected function calculateFieldNameSimilarity(string $field1, string $field2): float
    {
        // Use the validator's similarity calculation
        return $this->validator->calculateSimilarity($field1, $field2);
    }
    
    /**
     * Tokenize field name into meaningful parts
     */
    protected function tokenizeFieldName(string $fieldName): array
    {
        // Replace separators with spaces
        $normalized = preg_replace('/[_\-\.]+/', ' ', $fieldName);
        
        // Split camelCase
        $normalized = preg_replace('/([a-z])([A-Z])/', '$1 $2', $normalized);
        
        // Convert to lowercase and split
        $tokens = array_filter(explode(' ', strtolower($normalized)));
        
        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'of', 'for', 'to', 'in', 'on', 'at'];
        $tokens = array_diff($tokens, $stopWords);
        
        return array_values($tokens);
    }
    
    /**
     * Normalize field name for comparison
     */
    protected function normalizeFieldName(string $fieldName): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fieldName));
    }
    
    /**
     * Check if data source exists
     */
    protected function dataSourceExists(string $dataSource, array $dataSources): bool
    {
        foreach ($dataSources as $category => $sources) {
            if (isset($sources[$dataSource])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Detect field type from data source name
     */
    protected function detectFieldType(string $dataSource): string
    {
        $typePatterns = [
            '/date|dob/i' => 'date',
            '/phone/i' => 'text',
            '/email/i' => 'text',
            '/signature/i' => 'signature',
            '/checkbox|agree|consent/i' => 'checkbox',
            '/image|photo/i' => 'image',
        ];
        
        foreach ($typePatterns as $pattern => $type) {
            if (preg_match($pattern, $dataSource)) {
                return $type;
            }
        }
        
        return 'text';
    }
    
    /**
     * Deduplicate and combine suggestions
     */
    protected function deduplicateAndCombineSuggestions(array $suggestions): array
    {
        $combined = [];
        
        foreach ($suggestions as $suggestion) {
            $key = $suggestion['data_source'];
            
            if (!isset($combined[$key])) {
                $combined[$key] = $suggestion;
                $combined[$key]['methods'] = [$suggestion['method']];
                $combined[$key]['reasons'] = [$suggestion['reason']];
            } else {
                // Combine confidence scores
                $combined[$key]['confidence'] = max(
                    $combined[$key]['confidence'],
                    $suggestion['confidence']
                );
                
                // Add method and reason
                if (!in_array($suggestion['method'], $combined[$key]['methods'])) {
                    $combined[$key]['methods'][] = $suggestion['method'];
                }
                if (!in_array($suggestion['reason'], $combined[$key]['reasons'])) {
                    $combined[$key]['reasons'][] = $suggestion['reason'];
                }
                
                // Update combined reason
                $combined[$key]['reason'] = implode(' + ', $combined[$key]['reasons']);
            }
        }
        
        return array_values($combined);
    }
    
    /**
     * Enhance suggestions with historical data
     */
    protected function enhanceWithHistoricalData(array $suggestions, ManufacturerPdfTemplate $template): array
    {
        // Get successful mappings from the same manufacturer
        $historicalMappings = PdfFieldMapping::whereHas('template', function($query) use ($template) {
                $query->where('manufacturer_id', $template->manufacturer_id);
            })
            ->where('ai_suggested', true)
            ->where('ai_confidence', '>', 0.7)
            ->get();
        
        // Boost confidence for historically successful patterns
        foreach ($suggestions as $fieldName => &$fieldSuggestions) {
            foreach ($fieldSuggestions as &$suggestion) {
                $boostCount = 0;
                
                foreach ($historicalMappings as $historical) {
                    if ($historical->data_source === $suggestion['data_source']) {
                        $similarity = $this->calculateFieldNameSimilarity($fieldName, $historical->pdf_field_name);
                        if ($similarity > 0.7) {
                            $boostCount++;
                        }
                    }
                }
                
                if ($boostCount > 0) {
                    // Boost confidence based on historical success
                    $boost = min(0.2, $boostCount * 0.05);
                    $suggestion['confidence'] = min(0.98, $suggestion['confidence'] + $boost);
                    $suggestion['historical_boost'] = $boost;
                    $suggestion['historical_matches'] = $boostCount;
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Record successful mapping for learning
     */
    protected function recordSuccessfulMapping(
        ManufacturerPdfTemplate $template,
        string $fieldName,
        array $suggestion
    ): void {
        try {
            // Log successful mapping for future learning
            Log::info('AI mapping accepted', [
                'template_id' => $template->id,
                'manufacturer' => $template->manufacturer->name,
                'document_type' => $template->document_type,
                'field_name' => $fieldName,
                'data_source' => $suggestion['data_source'],
                'confidence' => $suggestion['confidence'],
                'method' => $suggestion['method'] ?? 'unknown'
            ]);
            
            // You could also store this in a dedicated learning table
            // for more sophisticated ML in the future
            
        } catch (\Exception $e) {
            Log::warning('Failed to record successful mapping', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get available data sources
     */
    protected function getAvailableDataSources(): array
    {
        // This matches the data sources in PDFTemplateController
        return [
            'patient' => [
                'patient_first_name' => 'Patient First Name',
                'patient_last_name' => 'Patient Last Name',
                'patient_dob' => 'Patient Date of Birth',
                'patient_gender' => 'Patient Gender',
                'patient_member_id' => 'Patient Member ID',
                'patient_address_line1' => 'Patient Address Line 1',
                'patient_address_line2' => 'Patient Address Line 2',
                'patient_city' => 'Patient City',
                'patient_state' => 'Patient State',
                'patient_zip' => 'Patient ZIP Code',
                'patient_phone' => 'Patient Phone',
                'patient_email' => 'Patient Email',
            ],
            'provider' => [
                'provider_name' => 'Provider Name',
                'provider_npi' => 'Provider NPI',
                'provider_email' => 'Provider Email',
                'provider_phone' => 'Provider Phone',
            ],
            'facility' => [
                'facility_name' => 'Facility Name',
                'facility_npi' => 'Facility NPI',
                'facility_address' => 'Facility Address',
                'facility_city' => 'Facility City',
                'facility_state' => 'Facility State',
                'facility_zip' => 'Facility ZIP',
                'facility_phone' => 'Facility Phone',
            ],
            'clinical' => [
                'wound_type' => 'Wound Type',
                'wound_location' => 'Wound Location',
                'wound_size_length' => 'Wound Length (cm)',
                'wound_size_width' => 'Wound Width (cm)',
                'wound_size_depth' => 'Wound Depth (cm)',
                'wound_duration' => 'Wound Duration',
                'primary_diagnosis_code' => 'Primary Diagnosis Code',
                'secondary_diagnosis_code' => 'Secondary Diagnosis Code',
            ],
            'insurance' => [
                'primary_insurance_name' => 'Primary Insurance Name',
                'primary_member_id' => 'Primary Member ID',
                'primary_group_number' => 'Primary Group Number',
                'primary_payer_phone' => 'Primary Payer Phone',
            ],
            'product' => [
                'product_name' => 'Product Name',
                'product_code' => 'Product Code',
                'product_size' => 'Product Size',
                'quantity' => 'Quantity',
            ],
            'order' => [
                'order_number' => 'Order Number',
                'expected_service_date' => 'Expected Service Date',
                'signature_date' => 'Today\'s Date',
            ],
        ];
    }
}