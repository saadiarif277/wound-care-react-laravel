<?php

namespace App\Services\PDF;

use App\Services\DocumentIntelligenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

/**
 * Specialized Document Intelligence service for PDF template analysis
 * Extends the base DocumentIntelligenceService with PDF-specific capabilities
 */
class PDFDocumentIntelligenceService extends DocumentIntelligenceService
{
    /**
     * Analyze a PDF template to extract form fields with enhanced metadata
     */
    public function analyzePDFTemplate($file): array
    {
        try {
            // First, try the general template analysis
            $baseAnalysis = $this->analyzeTemplateStructure($file);
            
            // Enhance with PDF-specific analysis
            $enhancedAnalysis = $this->enhancePDFAnalysis($baseAnalysis);
            
            // Add intelligent field type detection
            $enhancedAnalysis['fields'] = $this->enhanceFieldTypes($enhancedAnalysis['fields'] ?? []);
            
            // Add mapping suggestions based on field names
            $enhancedAnalysis['mapping_suggestions'] = $this->generateInitialMappingSuggestions($enhancedAnalysis['fields'] ?? []);
            
            return [
                'success' => true,
                'analysis' => $enhancedAnalysis,
                'field_count' => count($enhancedAnalysis['fields'] ?? []),
                'confidence' => $enhancedAnalysis['overall_confidence'] ?? 0.85,
                'processing_method' => 'azure_document_intelligence_enhanced'
            ];
            
        } catch (Exception $e) {
            Log::error('PDF template analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_available' => true // Can fall back to pdftk
            ];
        }
    }
    
    /**
     * Parse template analysis results into structured format
     */
    protected function parseTemplateResults($result): array
    {
        $fields = [];
        $documentType = 'general_form';
        $metadata = [];
        
        $analyzeResult = $result['analyzeResult'] ?? [];
        
        // Extract document metadata
        if (isset($analyzeResult['documents'][0])) {
            $doc = $analyzeResult['documents'][0];
            $documentType = $this->detectDocumentType($analyzeResult);
            $metadata = [
                'page_count' => count($analyzeResult['pages'] ?? []),
                'document_type' => $documentType,
                'confidence' => $doc['confidence'] ?? 0
            ];
        }
        
        // Extract key-value pairs as form fields
        foreach ($analyzeResult['keyValuePairs'] ?? [] as $kvp) {
            if (isset($kvp['key']['content'])) {
                $fieldName = $this->cleanFieldName($kvp['key']['content']);
                $fieldType = $this->detectFieldTypeFromKVP($kvp);
                
                $fields[] = [
                    'name' => $fieldName,
                    'display_name' => $this->humanizeFieldName($fieldName),
                    'type' => $fieldType,
                    'required' => $this->detectIfRequired($kvp),
                    'confidence' => $kvp['confidence'] ?? 0.8,
                    'location' => $this->getFieldLocation($kvp),
                    'context' => $this->extractFieldContext($kvp, $analyzeResult),
                    'original_label' => $kvp['key']['content'],
                    'has_value' => isset($kvp['value']),
                    'validation_hints' => $this->detectValidationHints($kvp, $fieldName)
                ];
            }
        }
        
        // Extract fields from tables
        foreach ($analyzeResult['tables'] ?? [] as $tableIndex => $table) {
            $tableFields = $this->extractTableFields($table, $tableIndex);
            $fields = array_merge($fields, $tableFields);
        }
        
        // Extract selection marks as checkboxes
        foreach ($analyzeResult['pages'] ?? [] as $pageIndex => $page) {
            foreach ($page['selectionMarks'] ?? [] as $markIndex => $mark) {
                $fields[] = [
                    'name' => "checkbox_p{$pageIndex}_m{$markIndex}",
                    'display_name' => "Checkbox " . ($markIndex + 1) . " (Page " . ($pageIndex + 1) . ")",
                    'type' => 'checkbox',
                    'required' => false,
                    'confidence' => $mark['confidence'] ?? 0.7,
                    'location' => [
                        'page' => $pageIndex + 1,
                        'coordinates' => $mark['polygon'] ?? []
                    ],
                    'context' => $this->findNearbyText($mark, $page)
                ];
            }
        }
        
        // Deduplicate and sort fields
        $fields = $this->deduplicateAndSortFields($fields);
        
        return [
            'fields' => $fields,
            'metadata' => $metadata,
            'document_type' => $documentType,
            'overall_confidence' => $this->calculateOverallConfidence($fields)
        ];
    }
    
    /**
     * Enhance PDF analysis with additional intelligence
     */
    protected function enhancePDFAnalysis(array $baseAnalysis): array
    {
        // Group fields by semantic categories
        $baseAnalysis['field_categories'] = $this->categorizeFields($baseAnalysis['fields'] ?? []);
        
        // Detect form sections/groups
        $baseAnalysis['form_sections'] = $this->detectFormSections($baseAnalysis['fields'] ?? []);
        
        // Add field relationships
        $baseAnalysis['field_relationships'] = $this->detectFieldRelationships($baseAnalysis['fields'] ?? []);
        
        return $baseAnalysis;
    }
    
    /**
     * Enhance field types with intelligent detection
     */
    protected function enhanceFieldTypes(array $fields): array
    {
        foreach ($fields as &$field) {
            // Enhance type detection based on multiple signals
            $enhancedType = $this->intelligentTypeDetection($field);
            if ($enhancedType !== $field['type']) {
                $field['type'] = $enhancedType;
                $field['type_confidence'] = 0.9; // High confidence for enhanced detection
            }
            
            // Add input constraints based on type
            $field['constraints'] = $this->getFieldConstraints($field['type'], $field['name']);
            
            // Add formatting hints
            $field['format_hint'] = $this->getFormatHint($field['type'], $field['name']);
        }
        
        return $fields;
    }
    
    /**
     * Generate initial mapping suggestions based on field analysis
     */
    protected function generateInitialMappingSuggestions(array $fields): array
    {
        $suggestions = [];
        
        foreach ($fields as $field) {
            $fieldSuggestions = $this->suggestMappingsForField($field);
            if (!empty($fieldSuggestions)) {
                $suggestions[$field['name']] = $fieldSuggestions;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Suggest mappings for a single field
     */
    protected function suggestMappingsForField(array $field): array
    {
        $suggestions = [];
        $fieldName = strtolower($field['name']);
        $fieldContext = strtolower($field['context'] ?? '');
        
        // Common field mappings based on patterns
        $mappingPatterns = [
            // Patient fields
            '/patient.*name|name.*patient/i' => [
                ['source' => 'patient_name', 'confidence' => 0.95],
                ['source' => 'patient_first_name', 'confidence' => 0.8],
                ['source' => 'patient_last_name', 'confidence' => 0.8]
            ],
            '/first.*name/i' => [
                ['source' => 'patient_first_name', 'confidence' => 0.9]
            ],
            '/last.*name/i' => [
                ['source' => 'patient_last_name', 'confidence' => 0.9]
            ],
            '/dob|birth.*date|date.*birth/i' => [
                ['source' => 'patient_dob', 'confidence' => 0.95]
            ],
            '/member.*id|patient.*id/i' => [
                ['source' => 'patient_member_id', 'confidence' => 0.9]
            ],
            
            // Provider fields
            '/provider.*name|physician.*name|doctor.*name/i' => [
                ['source' => 'provider_name', 'confidence' => 0.95]
            ],
            '/npi|provider.*number/i' => [
                ['source' => 'provider_npi', 'confidence' => 0.95]
            ],
            
            // Insurance fields
            '/insurance.*name|payer.*name/i' => [
                ['source' => 'primary_insurance_name', 'confidence' => 0.9]
            ],
            '/policy.*number|insurance.*number/i' => [
                ['source' => 'primary_member_id', 'confidence' => 0.85]
            ],
            '/group.*number/i' => [
                ['source' => 'primary_group_number', 'confidence' => 0.9]
            ],
            
            // Clinical fields
            '/diagnosis|icd.*10|dx/i' => [
                ['source' => 'primary_diagnosis_code', 'confidence' => 0.9]
            ],
            '/wound.*type/i' => [
                ['source' => 'wound_type', 'confidence' => 0.95]
            ],
            '/wound.*location|anatomic.*location/i' => [
                ['source' => 'wound_location', 'confidence' => 0.95]
            ],
            
            // Contact fields
            '/phone|telephone|contact.*number/i' => [
                ['source' => 'patient_phone', 'confidence' => 0.8],
                ['source' => 'provider_phone', 'confidence' => 0.7]
            ],
            '/email|e-mail/i' => [
                ['source' => 'patient_email', 'confidence' => 0.8],
                ['source' => 'provider_email', 'confidence' => 0.7]
            ],
            
            // Address fields
            '/address|street/i' => [
                ['source' => 'patient_address_line1', 'confidence' => 0.85]
            ],
            '/city/i' => [
                ['source' => 'patient_city', 'confidence' => 0.9]
            ],
            '/state/i' => [
                ['source' => 'patient_state', 'confidence' => 0.9]
            ],
            '/zip|postal/i' => [
                ['source' => 'patient_zip', 'confidence' => 0.9]
            ],
            
            // Date fields
            '/service.*date|date.*service/i' => [
                ['source' => 'expected_service_date', 'confidence' => 0.9]
            ],
            '/signature.*date|date.*signed/i' => [
                ['source' => 'signature_date', 'confidence' => 0.95]
            ]
        ];
        
        // Check each pattern
        foreach ($mappingPatterns as $pattern => $mappingSuggestions) {
            if (preg_match($pattern, $fieldName) || preg_match($pattern, $fieldContext)) {
                foreach ($mappingSuggestions as $suggestion) {
                    $suggestions[] = array_merge($suggestion, [
                        'reason' => 'Pattern match',
                        'pattern' => $pattern
                    ]);
                }
            }
        }
        
        // Sort by confidence
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        // Limit to top 3 suggestions
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Intelligent type detection using multiple signals
     */
    protected function intelligentTypeDetection(array $field): string
    {
        $name = strtolower($field['name']);
        $context = strtolower($field['context'] ?? '');
        $currentType = $field['type'] ?? 'text';
        
        // Signature detection
        if (preg_match('/signature|sign.*here|authorized.*by/i', $name . ' ' . $context)) {
            return 'signature';
        }
        
        // Date detection
        if (preg_match('/date|dob|birth|expire/i', $name) || 
            preg_match('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $context)) {
            return 'date';
        }
        
        // Checkbox detection (enhanced)
        if ($currentType === 'checkbox' || 
            preg_match('/\[\s*\]|\(\s*\)|checkbox|check.*box|agree|consent/i', $name . ' ' . $context)) {
            return 'checkbox';
        }
        
        // Radio button detection
        if (preg_match('/radio|option|choice|select.*one/i', $name . ' ' . $context)) {
            return 'radio';
        }
        
        // Select/dropdown detection
        if (preg_match('/select|dropdown|choose.*from|list.*of/i', $name . ' ' . $context)) {
            return 'select';
        }
        
        // Image/photo detection
        if (preg_match('/photo|image|picture|upload/i', $name)) {
            return 'image';
        }
        
        return $currentType;
    }
    
    /**
     * Get field constraints based on type and name
     */
    protected function getFieldConstraints(string $type, string $name): array
    {
        $constraints = [];
        
        switch ($type) {
            case 'date':
                $constraints['format'] = 'MM/DD/YYYY';
                $constraints['min'] = '01/01/1900';
                $constraints['max'] = 'today';
                break;
                
            case 'phone':
                $constraints['format'] = '(XXX) XXX-XXXX';
                $constraints['pattern'] = '/^\(\d{3}\)\s*\d{3}-\d{4}$/';
                break;
                
            case 'email':
                $constraints['pattern'] = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
                break;
                
            case 'text':
                // Add length constraints based on field name
                if (preg_match('/zip|postal/i', $name)) {
                    $constraints['maxLength'] = 10;
                    $constraints['pattern'] = '/^\d{5}(-\d{4})?$/';
                } elseif (preg_match('/state/i', $name)) {
                    $constraints['maxLength'] = 2;
                    $constraints['pattern'] = '/^[A-Z]{2}$/';
                } elseif (preg_match('/npi/i', $name)) {
                    $constraints['length'] = 10;
                    $constraints['pattern'] = '/^\d{10}$/';
                }
                break;
        }
        
        return $constraints;
    }
    
    /**
     * Get format hint for field
     */
    protected function getFormatHint(string $type, string $name): ?string
    {
        $hints = [
            'date' => 'MM/DD/YYYY',
            'phone' => '(555) 123-4567',
            'email' => 'email@example.com',
            'signature' => 'Electronic signature required'
        ];
        
        // Name-specific hints
        if (preg_match('/ssn/i', $name)) {
            return 'XXX-XX-XXXX';
        } elseif (preg_match('/npi/i', $name)) {
            return '10-digit NPI number';
        } elseif (preg_match('/zip/i', $name)) {
            return '12345 or 12345-6789';
        }
        
        return $hints[$type] ?? null;
    }
    
    /**
     * Categorize fields into semantic groups
     */
    protected function categorizeFields(array $fields): array
    {
        $categories = [
            'patient_information' => [],
            'provider_information' => [],
            'insurance_information' => [],
            'clinical_information' => [],
            'administrative' => [],
            'signatures' => [],
            'other' => []
        ];
        
        foreach ($fields as $field) {
            $category = $this->detectFieldCategory($field);
            $categories[$category][] = $field['name'];
        }
        
        // Remove empty categories
        return array_filter($categories, fn($fields) => !empty($fields));
    }
    
    /**
     * Detect field category based on name and context
     */
    protected function detectFieldCategory(array $field): string
    {
        $name = strtolower($field['name']);
        $context = strtolower($field['context'] ?? '');
        
        if (preg_match('/patient|member|subscriber|beneficiary/i', $name . ' ' . $context)) {
            return 'patient_information';
        }
        
        if (preg_match('/provider|physician|doctor|practitioner|npi/i', $name . ' ' . $context)) {
            return 'provider_information';
        }
        
        if (preg_match('/insurance|policy|coverage|payer|claim/i', $name . ' ' . $context)) {
            return 'insurance_information';
        }
        
        if (preg_match('/diagnosis|clinical|medical|wound|treatment|condition/i', $name . ' ' . $context)) {
            return 'clinical_information';
        }
        
        if ($field['type'] === 'signature') {
            return 'signatures';
        }
        
        if (preg_match('/date|time|reference|number|code/i', $name)) {
            return 'administrative';
        }
        
        return 'other';
    }
    
    /**
     * Detect form sections based on field proximity and naming
     */
    protected function detectFormSections(array $fields): array
    {
        $sections = [];
        $currentSection = null;
        
        foreach ($fields as $field) {
            // Detect section headers (fields that might be labels)
            if ($this->isPossibleSectionHeader($field)) {
                $currentSection = [
                    'name' => $field['display_name'],
                    'fields' => [],
                    'page' => $field['location']['page'] ?? 1
                ];
                $sections[] = &$currentSection;
            } elseif ($currentSection !== null) {
                $currentSection['fields'][] = $field['name'];
            }
        }
        
        return $sections;
    }
    
    /**
     * Check if field might be a section header
     */
    protected function isPossibleSectionHeader(array $field): bool
    {
        // Section headers often don't have values and contain keywords
        return !($field['has_value'] ?? true) && 
               preg_match('/information|section|details|data/i', $field['name']);
    }
    
    /**
     * Detect relationships between fields
     */
    protected function detectFieldRelationships(array $fields): array
    {
        $relationships = [];
        
        // Detect field groups (e.g., address fields)
        $addressFields = array_filter($fields, fn($f) => 
            preg_match('/address|street|city|state|zip/i', $f['name'])
        );
        
        if (count($addressFields) > 1) {
            $relationships[] = [
                'type' => 'group',
                'name' => 'address',
                'fields' => array_map(fn($f) => $f['name'], $addressFields)
            ];
        }
        
        // Detect dependent fields
        foreach ($fields as $field) {
            if (preg_match('/if.*yes|other.*specify/i', $field['name'])) {
                $relationships[] = [
                    'type' => 'conditional',
                    'field' => $field['name'],
                    'condition' => 'Previous field = Yes'
                ];
            }
        }
        
        return $relationships;
    }
    
    /**
     * Find nearby text for context
     */
    protected function findNearbyText($element, $page): string
    {
        // This would analyze nearby text elements to provide context
        // For now, return empty string
        return '';
    }
    
    /**
     * Extract field context for better mapping
     */
    protected function extractFieldContext($kvp, $analyzeResult): string
    {
        // Extract surrounding text or section headers for context
        $context = '';
        
        // Add any nearby text that might provide context
        if (isset($kvp['key']['boundingRegions'][0])) {
            $keyRegion = $kvp['key']['boundingRegions'][0];
            $pageIndex = $keyRegion['pageNumber'] - 1;
            
            // Look for section headers or nearby labels
            // This is simplified - real implementation would be more sophisticated
            $context = 'Page ' . $keyRegion['pageNumber'];
        }
        
        return $context;
    }
    
    /**
     * Detect validation hints from field analysis
     */
    protected function detectValidationHints($kvp, string $fieldName): array
    {
        $hints = [];
        
        // Check for format hints in the label
        if (isset($kvp['key']['content'])) {
            $label = $kvp['key']['content'];
            
            // Look for format examples
            if (preg_match('/\(([^)]+)\)/', $label, $matches)) {
                $hints['format_example'] = $matches[1];
            }
            
            // Look for length hints
            if (preg_match('/\d+\s*characters?/i', $label, $matches)) {
                $hints['length_hint'] = $matches[0];
            }
        }
        
        return $hints;
    }
    
    /**
     * Calculate overall confidence score
     */
    protected function calculateOverallConfidence(array $fields): float
    {
        if (empty($fields)) {
            return 0.0;
        }
        
        $totalConfidence = array_sum(array_column($fields, 'confidence'));
        return $totalConfidence / count($fields);
    }
    
    /**
     * Deduplicate and sort fields by location
     */
    protected function deduplicateAndSortFields(array $fields): array
    {
        // Remove exact duplicates
        $unique = [];
        $seen = [];
        
        foreach ($fields as $field) {
            $key = $field['name'] . '_' . ($field['location']['page'] ?? 0);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $field;
            }
        }
        
        // Sort by page and position
        usort($unique, function($a, $b) {
            $pageA = $a['location']['page'] ?? 0;
            $pageB = $b['location']['page'] ?? 0;
            
            if ($pageA !== $pageB) {
                return $pageA <=> $pageB;
            }
            
            // Sort by Y coordinate if on same page
            $yA = $a['location']['coordinates'][0]['y'] ?? 0;
            $yB = $b['location']['coordinates'][0]['y'] ?? 0;
            
            return $yA <=> $yB;
        });
        
        return $unique;
    }
    
    /**
     * Extract table fields with enhanced metadata
     */
    protected function extractTableFields(array $table, int $tableIndex): array
    {
        $fields = [];
        $headers = [];
        
        // Extract headers from first row
        foreach ($table['cells'] as $cell) {
            if ($cell['rowIndex'] === 0) {
                $headers[$cell['columnIndex']] = $cell['content'];
            }
        }
        
        // Create fields for each column
        foreach ($headers as $colIndex => $header) {
            $fieldName = "table_{$tableIndex}_" . $this->cleanFieldName($header);
            
            $fields[] = [
                'name' => $fieldName,
                'display_name' => $this->humanizeFieldName($header),
                'type' => 'text',
                'required' => false,
                'confidence' => 0.85,
                'location' => [
                    'table' => $tableIndex,
                    'column' => $colIndex,
                    'page' => 1 // Would need to extract actual page
                ],
                'context' => "Table column: {$header}",
                'is_table_field' => true,
                'table_index' => $tableIndex,
                'column_index' => $colIndex
            ];
        }
        
        return $fields;
    }
}