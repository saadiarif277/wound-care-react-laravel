<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AzureDocumentIntelligenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OcrFieldDetectionService
{
    private AzureDocumentIntelligenceService $ocrService;

    public function __construct(AzureDocumentIntelligenceService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    /**
     * Extract field labels from PDF using OCR
     */
    public function extractFieldLabelsFromPdf(string $pdfPath): array
    {
        try {
            Log::info('Starting OCR field extraction', ['pdf_path' => $pdfPath]);

            // Analyze document with Azure Document Intelligence
            $ocrResult = $this->ocrService->analyzeDocument($pdfPath, 'prebuilt-layout');

            if (!$ocrResult) {
                throw new \Exception('OCR analysis failed or returned empty results');
            }

            // Azure service returns analyzeResult directly, so wrap it if needed
            if (!isset($ocrResult['analyzeResult'])) {
                $ocrResult = ['analyzeResult' => $ocrResult];
            }

            // Extract field labels using multiple detection methods
            $detectedFields = $this->extractFieldsFromOcrResult($ocrResult);

            Log::info('OCR field extraction completed', [
                'pdf_path' => $pdfPath,
                'fields_detected' => count($detectedFields)
            ]);

            return $detectedFields;

        } catch (\Exception $e) {
            Log::error('OCR field extraction failed', [
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Extract field labels from OCR result using multiple detection methods
     */
    private function extractFieldsFromOcrResult(array $ocrResult): array
    {
        $fields = [];

        // Method 1: Extract from content text using patterns
        $contentFields = $this->extractFieldsFromContent($ocrResult);
        $fields = array_merge($fields, $contentFields);

        // Method 2: Extract from table structures
        $tableFields = $this->extractFieldsFromTables($ocrResult);
        $fields = array_merge($fields, $tableFields);

        // Method 3: Extract from key-value pairs
        $keyValueFields = $this->extractFieldsFromKeyValuePairs($ocrResult);
        $fields = array_merge($fields, $keyValueFields);

        // Method 4: Extract from form elements (if available)
        $formFields = $this->extractFieldsFromFormElements($ocrResult);
        $fields = array_merge($fields, $formFields);

        // Deduplicate and clean up fields
        return $this->cleanAndDeduplicateFields($fields);
    }

    /**
     * Extract fields from document content using pattern matching
     */
    private function extractFieldsFromContent(array $ocrResult): array
    {
        $content = $ocrResult['analyzeResult']['content'] ?? '';
        $lines = explode("\n", $content);
        $fields = [];

        // Enhanced field detection patterns
        $fieldPatterns = [
            // Standard form patterns
            '/^([A-Z][A-Z\s&\/\-\.]+[A-Z]):?\s*[_\.\-]{2,}.*$/i', // PATIENT NAME: ______
            '/^([A-Z][A-Z\s&\/\-\.]+[A-Z]):?\s*\[\s*\].*$/i',      // PATIENT NAME: [ ]
            '/^([A-Z][A-Z\s&\/\-\.]+[A-Z]):\s*$/i',                // PATIENT NAME:
            '/^([A-Z][A-Z\s&\/\-\.]+[A-Z])\s*:\s*☐.*$/i',         // PATIENT NAME: ☐
            '/^([A-Z][A-Z\s&\/\-\.]+[A-Z])\s*\*.*$/i',             // PATIENT NAME *
            
            // Medical form specific patterns
            '/^([A-Z][A-Za-z\s]+\s+(?:Name|ID|Number|Date|Code|Phone|Email|Address)):?.*$/i',
            '/^(.*(?:Patient|Provider|Physician|Doctor|Facility|Insurance|Member|Group|NPI|Tax|PTAN)):?.*$/i',
            
            // Insurance form patterns
            '/^(Primary|Secondary)\s+(Insurance|Payer|Plan):?.*$/i',
            '/^(Member|Policy|Group)\s+(ID|Number):?.*$/i',
            
            // Clinical patterns
            '/^(Wound|Diagnosis|Treatment|Procedure|CPT|ICD-?10?):?.*$/i',
            '/^(Date\s+of\s+(?:Birth|Service|Procedure)):?.*$/i',
            
            // Signature and date patterns
            '/^(Provider|Physician|Doctor)\s+(Signature|Name):?.*$/i',
            '/^(Today\'?s?\s+Date|Current\s+Date|Date):?.*$/i',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 3) continue;

            foreach ($fieldPatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $fieldLabel = trim($matches[1]);
                    if ($this->isValidFieldLabel($fieldLabel)) {
                        $fields[] = [
                            'label' => $fieldLabel,
                            'type' => $this->determineFieldType($line),
                            'source' => 'content_pattern',
                            'confidence' => $this->calculatePatternConfidence($pattern, $line)
                        ];
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Extract fields from table structures
     */
    private function extractFieldsFromTables(array $ocrResult): array
    {
        $fields = [];
        $tables = $ocrResult['analyzeResult']['tables'] ?? [];

        foreach ($tables as $table) {
            $cells = $table['cells'] ?? [];
            
            foreach ($cells as $cell) {
                $content = trim($cell['content'] ?? '');
                if (empty($content)) continue;

                // Look for cells that appear to be field labels
                if ($this->looksLikeFieldLabel($content)) {
                    $fields[] = [
                        'label' => $content,
                        'type' => $this->determineFieldType($content),
                        'source' => 'table_cell',
                        'confidence' => 0.7,
                        'table_info' => [
                            'row' => $cell['rowIndex'] ?? null,
                            'column' => $cell['columnIndex'] ?? null
                        ]
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * Extract fields from key-value pairs detected by Azure Document Intelligence
     */
    private function extractFieldsFromKeyValuePairs(array $ocrResult): array
    {
        $fields = [];
        $keyValuePairs = $ocrResult['analyzeResult']['keyValuePairs'] ?? [];

        foreach ($keyValuePairs as $pair) {
            $key = $pair['key']['content'] ?? '';
            $value = $pair['value']['content'] ?? '';
            
            if (!empty($key) && $this->isValidFieldLabel($key)) {
                $fields[] = [
                    'label' => trim($key, ':'),
                    'type' => $this->determineFieldTypeFromValue($value),
                    'source' => 'key_value_pair',
                    'confidence' => $pair['confidence'] ?? 0.8,
                    'value_sample' => $value
                ];
            }
        }

        return $fields;
    }

    /**
     * Extract fields from form elements if detected
     */
    private function extractFieldsFromFormElements(array $ocrResult): array
    {
        $fields = [];
        
        // Check for document fields (form fields)
        $documentFields = $ocrResult['analyzeResult']['documents'][0]['fields'] ?? [];
        
        foreach ($documentFields as $fieldName => $fieldData) {
            if ($this->isValidFieldLabel($fieldName)) {
                $fields[] = [
                    'label' => $fieldName,
                    'type' => $fieldData['type'] ?? 'text',
                    'source' => 'form_element',
                    'confidence' => $fieldData['confidence'] ?? 0.9,
                    'field_data' => $fieldData
                ];
            }
        }

        return $fields;
    }

    /**
     * Clean up and deduplicate detected fields
     */
    private function cleanAndDeduplicateFields(array $fields): array
    {
        $cleanedFields = [];
        $seenLabels = [];

        foreach ($fields as $field) {
            $label = $this->cleanFieldLabel($field['label']);
            $normalizedLabel = strtoupper(trim($label));

            // Skip if we've already seen this label or if it's too short
            if (isset($seenLabels[$normalizedLabel]) || strlen($label) < 3) {
                continue;
            }

            // Skip common false positives
            if ($this->isFalsePositive($label)) {
                continue;
            }

            $cleanedFields[] = [
                'label' => $label,
                'normalized_label' => $normalizedLabel,
                'type' => $field['type'],
                'source' => $field['source'],
                'confidence' => $field['confidence'],
                'mapping_key' => $this->generateMappingKey($label),
                'suggested_system_field' => $this->suggestSystemField($label)
            ];

            $seenLabels[$normalizedLabel] = true;
        }

        // Sort by confidence score
        usort($cleanedFields, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $cleanedFields;
    }

    /**
     * Determine if text looks like a field label
     */
    private function looksLikeFieldLabel(string $text): bool
    {
        $text = trim($text);
        
        // Must be reasonable length
        if (strlen($text) < 3 || strlen($text) > 50) {
            return false;
        }

        // Should contain common field keywords
        $fieldKeywords = [
            'name', 'id', 'number', 'date', 'phone', 'email', 'address',
            'patient', 'provider', 'physician', 'doctor', 'facility',
            'insurance', 'member', 'group', 'npi', 'tax', 'wound',
            'diagnosis', 'treatment', 'signature'
        ];

        $textLower = strtolower($text);
        foreach ($fieldKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                return true;
            }
        }

        // Check if it ends with colon (common for labels)
        if (preg_match('/^[A-Z][A-Za-z\s&\/\-\.]+:$/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a field label is valid
     */
    private function isValidFieldLabel(string $label): bool
    {
        $label = trim($label, ': ');
        
        // Must be reasonable length
        if (strlen($label) < 3 || strlen($label) > 100) {
            return false;
        }

        // Must start with letter
        if (!preg_match('/^[A-Za-z]/', $label)) {
            return false;
        }

        // Should not be all uppercase unless it's an acronym or standard field
        if (ctype_upper($label) && strlen($label) > 10 && !$this->isKnownField($label)) {
            return false;
        }

        return true;
    }

    /**
     * Check if label is a known field type
     */
    private function isKnownField(string $label): bool
    {
        $knownFields = [
            'PATIENT NAME', 'PATIENT DOB', 'PROVIDER NAME', 'NPI',
            'TAX ID', 'MEMBER ID', 'GROUP NUMBER', 'INSURANCE NAME',
            'WOUND TYPE', 'DIAGNOSIS CODE', 'FACILITY NAME'
        ];

        return in_array(strtoupper($label), $knownFields);
    }

    /**
     * Determine field type from content or context
     */
    private function determineFieldType(string $content): string
    {
        $contentLower = strtolower($content);

        if (strpos($contentLower, 'date') !== false || strpos($contentLower, 'dob') !== false) {
            return 'date';
        }
        if (strpos($contentLower, 'phone') !== false) {
            return 'phone';
        }
        if (strpos($contentLower, 'email') !== false) {
            return 'email';
        }
        if (strpos($contentLower, 'signature') !== false) {
            return 'signature';
        }
        if (preg_match('/\[\s*\]|☐|checkbox/i', $content)) {
            return 'checkbox';
        }
        if (strpos($contentLower, 'number') !== false || strpos($contentLower, 'id') !== false) {
            return 'number';
        }

        return 'text';
    }

    /**
     * Determine field type from sample value
     */
    private function determineFieldTypeFromValue(string $value): string
    {
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $value)) {
            return 'date';
        }
        if (preg_match('/^\d{3}-\d{3}-\d{4}$/', $value)) {
            return 'phone';
        }
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value)) {
            return 'email';
        }
        if (preg_match('/^\d+$/', $value)) {
            return 'number';
        }

        return 'text';
    }

    /**
     * Calculate confidence score for pattern matches
     */
    private function calculatePatternConfidence(string $pattern, string $line): float
    {
        $baseConfidence = 0.6;

        // Higher confidence for more specific patterns
        if (strpos($pattern, 'Name|ID|Number') !== false) {
            $baseConfidence += 0.2;
        }
        if (strpos($pattern, 'Patient|Provider|Insurance') !== false) {
            $baseConfidence += 0.1;
        }
        if (preg_match('/[_]{3,}|\[\s*\]/', $line)) {
            $baseConfidence += 0.1;
        }

        return min($baseConfidence, 0.95);
    }

    /**
     * Clean field label text
     */
    private function cleanFieldLabel(string $label): string
    {
        // Remove common suffixes and prefixes
        $label = trim($label, ': *_[]()');
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);

        // Convert to title case if all uppercase
        if (ctype_upper($label) && strlen($label) > 3) {
            $label = ucwords(strtolower($label));
            // But keep known acronyms uppercase
            $label = preg_replace('/\bNpi\b/', 'NPI', $label);
            $label = preg_replace('/\bDob\b/', 'DOB', $label);
            $label = preg_replace('/\bId\b/', 'ID', $label);
        }

        return $label;
    }

    /**
     * Check for common false positives
     */
    private function isFalsePositive(string $label): bool
    {
        $falsePositives = [
            'yes', 'no', 'true', 'false', 'page', 'form', 'please',
            'note', 'notes', 'optional', 'required', 'total', 'copy',
            'attach', 'attachment', 'fax', 'email', 'phone', 'call'
        ];

        $labelLower = strtolower($label);
        foreach ($falsePositives as $fp) {
            if ($labelLower === $fp) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate consistent mapping key for database storage
     */
    private function generateMappingKey(string $label): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\s]/', '', $label));
    }

    /**
     * Suggest system field mapping based on label
     */
    private function suggestSystemField(string $label): string
    {
        $labelUpper = strtoupper($label);
        
        $mappings = [
            'PATIENT NAME' => 'patient_name',
            'PATIENT FIRST NAME' => 'patient_first_name',
            'PATIENT LAST NAME' => 'patient_last_name',
            'PATIENT DOB' => 'patient_dob',
            'DATE OF BIRTH' => 'patient_dob',
            'MEMBER ID' => 'patient_member_id',
            'PROVIDER NAME' => 'provider_name',
            'PHYSICIAN NAME' => 'provider_name',
            'NPI' => 'provider_npi',
            'NPI NUMBER' => 'provider_npi',
            'TAX ID' => 'provider_tax_id',
            'PRIMARY INSURANCE' => 'primary_insurance_name',
            'INSURANCE NAME' => 'primary_insurance_name',
            'GROUP NUMBER' => 'group_number',
            'FACILITY NAME' => 'facility_name',
            'WOUND TYPE' => 'wound_type',
            'DIAGNOSIS CODE' => 'diagnosis_code_display',
        ];

        // Try exact match first
        if (isset($mappings[$labelUpper])) {
            return $mappings[$labelUpper];
        }

        // Try partial matches
        foreach ($mappings as $pattern => $field) {
            if (strpos($labelUpper, $pattern) !== false) {
                return $field;
            }
        }

        // Generate snake_case version as fallback
        return Str::snake($label);
    }

    /**
     * Compare OCR-detected fields with DocuSeal API fields
     */
    public function compareWithDocuSealFields(array $ocrFields, array $docusealFields): array
    {
        $comparison = [
            'ocr_fields' => count($ocrFields),
            'docuseal_fields' => count($docusealFields),
            'matched_fields' => [],
            'ocr_only_fields' => [],
            'docuseal_only_fields' => [],
            'mapping_suggestions' => []
        ];

        $ocrLabels = array_column($ocrFields, 'normalized_label');
        $docusealNames = array_map('strtoupper', array_column($docusealFields, 'name'));

        // Find matches
        foreach ($ocrFields as $ocrField) {
            $matched = false;
            foreach ($docusealFields as $dsField) {
                if ($this->fieldsMatch($ocrField['normalized_label'], strtoupper($dsField['name']))) {
                    $comparison['matched_fields'][] = [
                        'ocr_label' => $ocrField['label'],
                        'docuseal_name' => $dsField['name'],
                        'confidence' => $ocrField['confidence']
                    ];
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                $comparison['ocr_only_fields'][] = $ocrField;
            }
        }

        // Find DocuSeal-only fields
        foreach ($docusealFields as $dsField) {
            $matched = false;
            foreach ($ocrLabels as $ocrLabel) {
                if ($this->fieldsMatch($ocrLabel, strtoupper($dsField['name']))) {
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                $comparison['docuseal_only_fields'][] = $dsField;
            }
        }

        // Generate mapping suggestions
        $comparison['mapping_suggestions'] = $this->generateMappingSuggestions(
            $comparison['ocr_only_fields'],
            $comparison['docuseal_only_fields']
        );

        return $comparison;
    }

    /**
     * Check if two field names/labels match
     */
    private function fieldsMatch(string $field1, string $field2): bool
    {
        $field1 = preg_replace('/[^A-Z0-9]/', '', $field1);
        $field2 = preg_replace('/[^A-Z0-9]/', '', $field2);
        
        return $field1 === $field2;
    }

    /**
     * Generate mapping suggestions between OCR and DocuSeal fields
     */
    private function generateMappingSuggestions(array $ocrFields, array $docusealFields): array
    {
        $suggestions = [];
        
        foreach ($ocrFields as $ocrField) {
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($docusealFields as $dsField) {
                $score = $this->calculateSimilarity(
                    $ocrField['normalized_label'],
                    strtoupper($dsField['name'])
                );
                
                if ($score > $bestScore && $score > 0.5) {
                    $bestScore = $score;
                    $bestMatch = $dsField;
                }
            }
            
            if ($bestMatch) {
                $suggestions[] = [
                    'ocr_field' => $ocrField,
                    'docuseal_field' => $bestMatch,
                    'similarity_score' => $bestScore,
                    'suggested_mapping' => [
                        'docuseal_field_name' => $bestMatch['name'],
                        'field_label' => $ocrField['label'],
                        'system_field' => $ocrField['suggested_system_field'],
                        'field_type' => $ocrField['type']
                    ]
                ];
            }
        }
        
        return $suggestions;
    }

    /**
     * Calculate similarity between two strings
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = preg_replace('/[^A-Z0-9]/', '', $str1);
        $str2 = preg_replace('/[^A-Z0-9]/', '', $str2);
        
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        return 1 - (levenshtein($str1, $str2) / max(strlen($str1), strlen($str2)));
    }
}
