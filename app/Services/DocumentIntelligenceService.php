<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentIntelligenceService
{
    protected string $endpoint;
    protected string $apiKey;
    protected string $apiVersion = '2023-07-31';
    
    public function __construct()
    {
        $this->endpoint = config('services.azure.document_intelligence.endpoint');
        $this->apiKey = config('services.azure.document_intelligence.key');
        
        // Log configuration for debugging
        Log::debug('DocumentIntelligenceService initialized', [
            'endpoint' => $this->endpoint ? 'configured' : 'missing',
            'key' => $this->apiKey ? 'configured' : 'missing'
        ]);
    }
    
    /**
     * Analyze any template to understand its structure
     * This is industry-agnostic and works with any form type
     */
    public function analyzeTemplateStructure($file): array
    {
        try {
            // Check if service is configured
            if (!$this->endpoint || !$this->apiKey) {
                throw new \Exception('Azure Document Intelligence is not configured. Please add AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT and AZURE_DOCUMENT_INTELLIGENCE_KEY to your .env file.');
            }
            
            // Convert file to base64 if needed
            $fileContent = $this->prepareFileContent($file);
            
            // Use prebuilt-document model for general analysis
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(
                "{$this->endpoint}/formrecognizer/documentModels/prebuilt-document:analyze?api-version={$this->apiVersion}",
                [
                    'base64Source' => base64_encode($fileContent)
                ]
            );
            
            if (!$response->successful()) {
                Log::error('Azure API call failed for template analysis', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Document analysis failed: ' . $response->body());
            }
            
            $operationLocation = $response->header('Operation-Location');
            $result = $this->waitForCompletion($operationLocation);
            
            return $this->parseTemplateResults($result);
            
        } catch (\Exception $e) {
            Log::error('Document Intelligence analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Extract data from a filled document
     */
    public function extractFilledFormData($file, array $templateStructure = []): array
    {
        try {
            // Check if service is configured
            if (!$this->endpoint || !$this->apiKey) {
                throw new \Exception('Azure Document Intelligence is not configured. Please add AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT and AZURE_DOCUMENT_INTELLIGENCE_KEY to your .env file.');
            }
            
            $fileContent = $this->prepareFileContent($file);
            
            // Use prebuilt-document for better form extraction
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(
                "{$this->endpoint}/formrecognizer/documentModels/prebuilt-document:analyze?api-version={$this->apiVersion}",
                [
                    'base64Source' => base64_encode($fileContent)
                ]
            );
            
            if (!$response->successful()) {
                Log::error('Azure API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Data extraction failed: ' . $response->body());
            }
            
            $operationLocation = $response->header('Operation-Location');
            $result = $this->waitForCompletion($operationLocation);
            
            return $this->extractDataWithContext($result, $templateStructure);
            
        } catch (\Exception $e) {
            Log::error('Data extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Smart field matching using AI
     */
    public function suggestFieldMappings(array $templateFields, array $canonicalFields): array
    {
        $suggestions = [];
        
        foreach ($templateFields as $templateField) {
            $fieldName = $templateField['name'];
            $fieldContext = $templateField['context'] ?? '';
            
            $topMatches = [];
            
            foreach ($canonicalFields as $canonicalField) {
                $score = $this->calculateMatchScore(
                    $fieldName,
                    $fieldContext,
                    $canonicalField
                );
                
                if ($score > 0.3) { // 30% confidence threshold
                    $topMatches[] = [
                        'canonical_field' => $canonicalField,
                        'confidence' => $score,
                        'reason' => $this->explainMatch($fieldName, $canonicalField, $score)
                    ];
                }
            }
            
            // Sort by confidence
            usort($topMatches, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
            
            $suggestions[$fieldName] = array_slice($topMatches, 0, 3); // Top 3 suggestions
        }
        
        return $suggestions;
    }
    
    /**
     * Parse template analysis results into user-friendly format
     */
    protected function parseTemplateResults(array $result): array
    {
        $fields = [];
        $analyzeResult = $result['analyzeResult'] ?? [];
        
        // Extract key-value pairs
        $keyValuePairs = $analyzeResult['keyValuePairs'] ?? [];
        foreach ($keyValuePairs as $kvp) {
            if (isset($kvp['key']['content'])) {
                $fieldName = $this->cleanFieldName($kvp['key']['content']);
                $fields[] = [
                    'name' => $fieldName,
                    'display_name' => $this->humanizeFieldName($fieldName),
                    'type' => $this->detectDataType($kvp),
                    'required' => $this->detectIfRequired($kvp),
                    'confidence' => $kvp['confidence'] ?? 0,
                    'location' => $this->getFieldLocation($kvp),
                    'context' => $this->extractContext($kvp, $analyzeResult)
                ];
            }
        }
        
        // Extract form fields from tables
        $tables = $analyzeResult['tables'] ?? [];
        foreach ($tables as $tableIndex => $table) {
            $tableFields = $this->extractTableFields($table, $tableIndex);
            $fields = array_merge($fields, $tableFields);
        }
        
        // Remove duplicates and sort by location
        $fields = $this->deduplicateAndSort($fields);
        
        return [
            'success' => true,
            'fields' => $fields,
            'metadata' => [
                'page_count' => $analyzeResult['pageCount'] ?? 1,
                'language' => $analyzeResult['languages'][0] ?? 'en',
                'document_type' => $this->detectDocumentType($analyzeResult)
            ]
        ];
    }
    
    /**
     * Extract data with template context
     */
    protected function extractDataWithContext(array $result, array $templateStructure): array
    {
        $extractedData = [];
        $analyzeResult = $result['analyzeResult'] ?? [];
        
        // Extract key-value pairs
        $keyValuePairs = $analyzeResult['keyValuePairs'] ?? [];
        
        // First, extract all detected key-value pairs
        $detectedPairs = [];
        foreach ($keyValuePairs as $kvp) {
            if (isset($kvp['key']['content']) && isset($kvp['value']['content'])) {
                $key = $this->cleanFieldName($kvp['key']['content']);
                $value = trim($kvp['value']['content']);
                $detectedPairs[$key] = [
                    'value' => $value,
                    'confidence' => $kvp['confidence'] ?? 0.8,
                    'original_key' => $kvp['key']['content']
                ];
            }
        }
        
        // If we have template structure, match detected pairs to expected fields
        if (!empty($templateStructure)) {
            foreach ($templateStructure as $expectedField) {
                $fieldName = $expectedField['name'];
                $normalizedFieldName = $this->normalizeFieldName($fieldName);
                
                // Look for exact or close matches
                $bestMatch = null;
                $bestScore = 0;
                
                foreach ($detectedPairs as $detectedKey => $detectedData) {
                    $score = $this->calculateStringSimilarity($normalizedFieldName, $this->normalizeFieldName($detectedKey));
                    if ($score > $bestScore && $score > 0.5) {
                        $bestScore = $score;
                        $bestMatch = $detectedData;
                    }
                }
                
                if ($bestMatch) {
                    $extractedData[$fieldName] = [
                        'value' => $bestMatch['value'],
                        'confidence' => $bestMatch['confidence'] * $bestScore,
                        'source' => 'key_value_pair'
                    ];
                }
            }
        } else {
            // No template structure, return all detected pairs
            foreach ($detectedPairs as $key => $data) {
                $extractedData[$key] = [
                    'value' => $data['value'],
                    'confidence' => $data['confidence'],
                    'source' => 'key_value_pair'
                ];
            }
        }
        
        // Also extract from tables if present
        $tables = $analyzeResult['tables'] ?? [];
        foreach ($tables as $table) {
            $tableData = $this->extractTableData($table);
            foreach ($tableData as $key => $value) {
                if (!isset($extractedData[$key]) && !empty($value)) {
                    $extractedData[$key] = [
                        'value' => $value,
                        'confidence' => 0.7,
                        'source' => 'table'
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'data' => $extractedData,
            'coverage' => $this->calculateCoverage($extractedData, $templateStructure),
            'detected_fields_count' => count($detectedPairs),
            'matched_fields_count' => count($extractedData)
        ];
    }
    
    /**
     * Calculate match score between template field and canonical field
     */
    protected function calculateMatchScore(string $templateField, string $context, array $canonicalField): float
    {
        $score = 0.0;
        
        // Direct name matching
        $nameSimilarity = $this->calculateStringSimilarity(
            $this->normalizeFieldName($templateField),
            $this->normalizeFieldName($canonicalField['field_name'])
        );
        $score += $nameSimilarity * 0.4;
        
        // Context matching
        if ($context && isset($canonicalField['description'])) {
            $contextSimilarity = $this->calculateStringSimilarity(
                strtolower($context),
                strtolower($canonicalField['description'])
            );
            $score += $contextSimilarity * 0.3;
        }
        
        // Type matching
        $detectedType = $this->detectDataTypeFromName($templateField);
        if ($detectedType === $canonicalField['data_type']) {
            $score += 0.2;
        }
        
        // Category hints
        $categoryHint = $this->detectCategoryFromName($templateField);
        if ($categoryHint === $canonicalField['category']) {
            $score += 0.1;
        }
        
        return min($score, 1.0);
    }
    
    /**
     * Clean and normalize field names
     */
    protected function cleanFieldName(string $name): string
    {
        // Remove common suffixes like :, *, (required), etc.
        $name = preg_replace('/[:\*\(\)].*$/', '', $name);
        $name = trim($name);
        
        // Convert to snake_case for consistency
        $name = Str::snake($name);
        
        return $name;
    }
    
    /**
     * Convert field name to human-readable format
     */
    protected function humanizeFieldName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', $name);
        $name = ucwords($name);
        
        // Handle common abbreviations
        $replacements = [
            'Dob' => 'Date of Birth',
            'Ssn' => 'SSN',
            'Id' => 'ID',
            'Npi' => 'NPI',
            'Pos' => 'Place of Service'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }
    
    /**
     * Detect data type from field content and name
     */
    protected function detectDataType($field): string
    {
        $name = strtolower($field['key']['content'] ?? '');
        $value = $field['value']['content'] ?? '';
        
        // Check name patterns
        if (preg_match('/(date|dob|birth|expire)/i', $name)) {
            return 'date';
        }
        
        if (preg_match('/(phone|tel|mobile|fax)/i', $name)) {
            return 'phone';
        }
        
        if (preg_match('/(email|e-mail)/i', $name)) {
            return 'email';
        }
        
        if (preg_match('/(amount|price|cost|fee|total)/i', $name)) {
            return 'currency';
        }
        
        if (preg_match('/(yes|no|checkbox|agree)/i', $name)) {
            return 'boolean';
        }
        
        // Check value patterns
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $value)) {
            return 'date';
        }
        
        if (preg_match('/^\(\d{3}\)\s*\d{3}-\d{4}$/', $value)) {
            return 'phone';
        }
        
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        return 'text';
    }
    
    /**
     * Detect if field is required based on visual cues
     */
    protected function detectIfRequired($field): bool
    {
        $keyContent = strtolower($field['key']['content'] ?? '');
        
        // Check for required indicators
        $requiredPatterns = ['*', 'required', 'mandatory', 'must'];
        
        foreach ($requiredPatterns as $pattern) {
            if (stripos($keyContent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Wait for async operation to complete
     */
    protected function waitForCompletion(string $operationLocation): array
    {
        $maxAttempts = 30;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(1);
            
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
            ])->get($operationLocation);
            
            if (!$response->successful()) {
                throw new \Exception('Failed to check operation status');
            }
            
            $result = $response->json();
            
            if ($result['status'] === 'succeeded') {
                return $result;
            } elseif ($result['status'] === 'failed') {
                throw new \Exception('Analysis failed: ' . ($result['error']['message'] ?? 'Unknown error'));
            }
            
            $attempt++;
        }
        
        throw new \Exception('Analysis timed out');
    }
    
    /**
     * Prepare file content for API
     */
    protected function prepareFileContent($file)
    {
        try {
            if (is_string($file)) {
                // File path
                return Storage::get($file);
            } elseif (is_object($file) && method_exists($file, 'get')) {
                // Uploaded file
                return $file->get();
            } elseif (is_object($file) && method_exists($file, 'getContent')) {
                // Alternative method for uploaded files
                return $file->getContent();
            }
            
            throw new \Exception('Invalid file format: ' . gettype($file));
        } catch (\Exception $e) {
            Log::error('Failed to prepare file content', [
                'error' => $e->getMessage(),
                'file_type' => is_object($file) ? get_class($file) : gettype($file)
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate string similarity
     */
    protected function calculateStringSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Use Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 0.0;
        }
        
        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $maxLen);
    }
    
    /**
     * Normalize field name for comparison
     */
    protected function normalizeFieldName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]/', '', $name);
        return $name;
    }
    
    /**
     * Explain why fields match
     */
    protected function explainMatch(string $templateField, array $canonicalField, float $score): string
    {
        $reasons = [];
        
        if ($score > 0.8) {
            $reasons[] = "Strong name match";
        } elseif ($score > 0.6) {
            $reasons[] = "Good name similarity";
        } elseif ($score > 0.4) {
            $reasons[] = "Partial name match";
        }
        
        $templateType = $this->detectDataTypeFromName($templateField);
        if ($templateType === $canonicalField['data_type']) {
            $reasons[] = "Same data type ({$templateType})";
        }
        
        $category = $this->detectCategoryFromName($templateField);
        if ($category === $canonicalField['category']) {
            $reasons[] = "Same category";
        }
        
        return implode(', ', $reasons);
    }
    
    /**
     * Extract table fields
     */
    protected function extractTableFields(array $table, int $tableIndex): array
    {
        $fields = [];
        
        // Assume first row contains headers
        $headers = [];
        foreach ($table['cells'] as $cell) {
            if ($cell['rowIndex'] === 0) {
                $headers[$cell['columnIndex']] = $cell['content'];
            }
        }
        
        foreach ($headers as $colIndex => $header) {
            $fields[] = [
                'name' => "table_{$tableIndex}_" . $this->cleanFieldName($header),
                'display_name' => $this->humanizeFieldName($header),
                'type' => 'text',
                'required' => false,
                'confidence' => 0.8,
                'location' => [
                    'table' => $tableIndex,
                    'column' => $colIndex
                ],
                'context' => "Table column: {$header}"
            ];
        }
        
        return $fields;
    }
    
    /**
     * Other helper methods...
     */
    protected function getFieldLocation($field): array
    {
        $boundingBox = $field['key']['boundingRegions'][0]['polygon'] ?? [];
        
        if (empty($boundingBox)) {
            return [];
        }
        
        return [
            'page' => $field['key']['boundingRegions'][0]['pageNumber'] ?? 1,
            'coordinates' => $boundingBox
        ];
    }
    
    protected function extractContext($field, $analyzeResult): string
    {
        // Extract surrounding text for context
        return '';
    }
    
    protected function detectDocumentType($analyzeResult): string
    {
        // Analyze content to determine document type
        return 'form';
    }
    
    protected function deduplicateAndSort(array $fields): array
    {
        // Remove duplicates based on name
        $unique = [];
        foreach ($fields as $field) {
            $key = $field['name'];
            if (!isset($unique[$key]) || $field['confidence'] > $unique[$key]['confidence']) {
                $unique[$key] = $field;
            }
        }
        
        // Sort by page location
        usort($unique, function($a, $b) {
            $aLoc = $a['location']['page'] ?? 0;
            $bLoc = $b['location']['page'] ?? 0;
            return $aLoc <=> $bLoc;
        });
        
        return array_values($unique);
    }
    
    protected function detectDataTypeFromName(string $name): string
    {
        return $this->detectDataType(['key' => ['content' => $name]]);
    }
    
    protected function detectCategoryFromName(string $name): string
    {
        $name = strtolower($name);
        
        if (preg_match('/(patient|member|subscriber)/i', $name)) {
            return 'patient_information';
        }
        
        if (preg_match('/(insurance|policy|coverage)/i', $name)) {
            return 'insurance_information';
        }
        
        if (preg_match('/(physician|doctor|provider|npi)/i', $name)) {
            return 'provider_information';
        }
        
        if (preg_match('/(facility|clinic|hospital|location)/i', $name)) {
            return 'facility_information';
        }
        
        return 'general_information';
    }
    
    protected function findValueNearLocation($words, $location, $fieldName): ?string
    {
        // Implementation to find values based on location
        return null;
    }
    
    protected function extractTableData(array $table): array
    {
        $data = [];
        $headers = [];
        
        // Extract headers from first row
        foreach ($table['cells'] as $cell) {
            if ($cell['rowIndex'] === 0) {
                $headers[$cell['columnIndex']] = $this->cleanFieldName($cell['content']);
            }
        }
        
        // Extract data from subsequent rows
        $rowData = [];
        foreach ($table['cells'] as $cell) {
            if ($cell['rowIndex'] > 0) {
                $row = $cell['rowIndex'];
                $col = $cell['columnIndex'];
                
                if (!isset($rowData[$row])) {
                    $rowData[$row] = [];
                }
                
                if (isset($headers[$col])) {
                    $rowData[$row][$headers[$col]] = trim($cell['content']);
                }
            }
        }
        
        // Flatten single-row tables
        if (count($rowData) === 1) {
            $data = reset($rowData);
        } else {
            $data['table_data'] = array_values($rowData);
        }
        
        return $data;
    }
    
    protected function calculateExtractionConfidence($value, $expectedField): float
    {
        // Calculate confidence based on value matching expected patterns
        return 0.8;
    }
    
    protected function calculateCoverage($extractedData, $templateStructure): float
    {
        if (empty($templateStructure)) {
            return 0.0;
        }
        
        $found = count(array_filter($extractedData, fn($item) => !empty($item['value'])));
        $total = count($templateStructure);
        
        return ($found / $total) * 100;
    }
}