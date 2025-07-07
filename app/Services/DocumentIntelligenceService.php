<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentIntelligenceService
{
    private ?string $endpoint;
    private ?string $apiKey;
    private string $apiVersion;
    
    // Healthcare terminology validation
    private array $medicalTerminologies = [
        'insurance_terms' => [
            'member_id', 'policy_number', 'group_number', 'plan_type',
            'deductible', 'copay', 'coinsurance', 'out_of_pocket_max',
            'primary_care_physician', 'pcp', 'specialist_copay',
            'rx_bin', 'rx_pcn', 'rx_grp', 'formulary'
        ],
        'clinical_terms' => [
            'wound', 'ulcer', 'pressure_injury', 'diabetic_foot',
            'venous_stasis', 'arterial_ulcer', 'surgical_wound',
            'dehiscence', 'infection', 'cellulitis', 'osteomyelitis',
            'necrosis', 'slough', 'eschar', 'granulation', 'epithelialization'
        ],
        'anatomical_terms' => [
            'sacrum', 'coccyx', 'heel', 'ankle', 'malleolus',
            'dorsum', 'plantar', 'lateral', 'medial', 'anterior',
            'posterior', 'proximal', 'distal', 'superficial', 'deep'
        ],
        'measurement_terms' => [
            'length', 'width', 'depth', 'area', 'volume',
            'cm', 'mm', 'centimeter', 'millimeter', 'inch',
            'tunneling', 'undermining', 'sinus_tract'
        ]
    ];
    
    // Confidence thresholds for 2025 standards
    private array $confidenceThresholds = [
        'insurance_card' => 0.85,
        'clinical_note' => 0.80,
        'demographic_form' => 0.75,
        'wound_photo' => 0.70,
        'prescription' => 0.85
    ];
    
    public function __construct()
    {
        // Check for config keys in both formats
        $this->endpoint = config('services.azure.document_intelligence.endpoint') 
            ?: config('services.azure_di.endpoint');
        $this->apiKey = config('services.azure.document_intelligence.key') 
            ?: config('services.azure_di.key');
        $this->apiVersion = config('services.azure.document_intelligence.api_version') 
            ?: config('services.azure_di.api_version', '2023-07-31');
        
        // Clean endpoint if present
        if ($this->endpoint !== null) {
            $this->endpoint = rtrim($this->endpoint, '/');
        }
        
        // Log configuration for debugging
        Log::debug('DocumentIntelligenceService initialized', [
            'endpoint' => $this->endpoint ? 'configured' : 'missing',
            'apiKey' => $this->apiKey ? 'configured' : 'missing',
            'apiVersion' => $this->apiVersion
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
     * 2025 Enhanced Insurance Card Analysis
     * Uses specialized prebuilt model with healthcare validation
     */
    public function analyzeInsuranceCard($file): array
    {
        if (!$this->isConfigured()) {
            return $this->createErrorResponse('Azure Document Intelligence not configured');
        }

        try {
            // Step 1: Azure Document Intelligence extraction
            $azureResult = $this->extractWithAzureModel($file, 'prebuilt-healthInsuranceCard.us');
            
            // Step 2: Healthcare terminology validation
            $validatedData = $this->validateHealthcareTerms($azureResult, 'insurance_card');
            
            // Step 3: Confidence scoring and quality assessment
            $qualityScore = $this->calculateQualityScore($validatedData, 'insurance_card');
            
            // Step 4: Structured data mapping
            $mappedData = $this->mapInsuranceCardData($validatedData);
            
            return [
                'success' => true,
                'data' => $mappedData,
                'quality_score' => $qualityScore,
                'confidence' => $validatedData['confidence'] ?? 0,
                'validation_notes' => $validatedData['validation_notes'] ?? [],
                'extracted_raw' => $azureResult,
                'processing_method' => 'azure_di_2025_enhanced'
            ];
            
        } catch (Exception $e) {
            Log::error('Insurance card analysis failed', [
                'error' => $e->getMessage(),
                'file' => $file instanceof UploadedFile ? $file->getClientOriginalName() : 'unknown'
            ]);
            
            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * 2025 Enhanced Clinical Document Analysis
     * Multi-model approach with terminology validation
     */
    public function analyzeClinicalDocument($file, $documentType = 'clinical_note'): array
    {
        if (!$this->isConfigured()) {
            return $this->createErrorResponse('Azure Document Intelligence not configured');
        }

        try {
            // Step 1: Use general document model for clinical notes
            $azureResult = $this->extractWithAzureModel($file, 'prebuilt-document');
            
            // Step 2: Healthcare terminology validation
            $validatedData = $this->validateHealthcareTerms($azureResult, $documentType);
            
            // Step 3: Clinical context enhancement
            $enhancedData = $this->enhanceWithClinicalContext($validatedData, $documentType);
            
            // Step 4: Confidence scoring
            $qualityScore = $this->calculateQualityScore($enhancedData, $documentType);
            
            return [
                'success' => true,
                'data' => $enhancedData,
                'quality_score' => $qualityScore,
                'confidence' => $validatedData['confidence'] ?? 0,
                'validation_notes' => $validatedData['validation_notes'] ?? [],
                'extracted_raw' => $azureResult,
                'processing_method' => 'azure_di_2025_clinical_enhanced'
            ];
            
        } catch (Exception $e) {
            Log::error('Clinical document analysis failed', [
                'error' => $e->getMessage(),
                'file' => $file instanceof UploadedFile ? $file->getClientOriginalName() : 'unknown'
            ]);
            
            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Enhanced filled form data extraction with 2025 best practices
     */
    public function extractFilledFormData($file): array
    {
        if (!$this->isConfigured()) {
            return $this->createErrorResponse('Azure Document Intelligence not configured');
        }

        try {
            // Step 1: Extract with Azure Document Intelligence
            $azureResult = $this->extractWithAzureModel($file, 'prebuilt-document');
            
            // Step 2: Validate and enhance
            $validatedData = $this->validateHealthcareTerms($azureResult, 'form');
            
            // Step 3: Structure the data
            $structuredData = $this->structureFormData($validatedData);
            
            return [
                'success' => true,
                'data' => $structuredData,
                'confidence' => $validatedData['confidence'] ?? 0,
                'validation_notes' => $validatedData['validation_notes'] ?? []
            ];
            
        } catch (Exception $e) {
            Log::error('Form data extraction failed', [
                'error' => $e->getMessage(),
                'file' => $file instanceof UploadedFile ? $file->getClientOriginalName() : 'unknown'
            ]);
            
            return $this->createErrorResponse($e->getMessage());
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
     * Core Azure Document Intelligence extraction
     */
    private function extractWithAzureModel($file, $model): array
    {
        $url = "{$this->endpoint}/formrecognizer/documentModels/{$model}:analyze";
        
        $fileContent = $file instanceof UploadedFile ? 
            file_get_contents($file->getRealPath()) : 
            file_get_contents($file);
            
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Content-Type' => 'application/octet-stream',
        ])->timeout(60)->post($url . '?api-version=' . $this->apiVersion, $fileContent);

        if (!$response->successful()) {
            throw new Exception("Azure Document Intelligence API error: " . $response->body());
        }

        $operationLocation = $response->header('Operation-Location');
        if (!$operationLocation) {
            throw new Exception('No operation location returned from Azure DI');
        }

        // Poll for results
        return $this->pollForResults($operationLocation);
    }

    /**
     * Healthcare terminology validation with 2025 standards
     */
    private function validateHealthcareTerms(array $extractedData, string $documentType): array
    {
        $validationNotes = [];
        $overallConfidence = 0;
        $termCounts = ['validated' => 0, 'total' => 0];

        // Get relevant terminologies for document type
        $relevantTerms = $this->getRelevantTerminologies($documentType);
        
        // Validate each extracted field
        foreach ($extractedData['documents'] ?? [] as $docIndex => $document) {
            foreach ($document['fields'] ?? [] as $fieldName => $field) {
                $termCounts['total']++;
                
                // Check if field name matches medical terminology
                if ($this->isValidMedicalTerm($fieldName, $relevantTerms)) {
                    $termCounts['validated']++;
                }
                
                // Check field value for medical terms
                if (isset($field['content']) && $this->containsMedicalTerms($field['content'], $relevantTerms)) {
                    $termCounts['validated']++;
                    $validationNotes[] = "Medical terminology detected in field: {$fieldName}";
                }
                
                // Confidence validation
                if (isset($field['confidence'])) {
                    $threshold = $this->confidenceThresholds[$documentType] ?? 0.75;
                    if ($field['confidence'] < $threshold) {
                        $validationNotes[] = "Low confidence field: {$fieldName} ({$field['confidence']})";
                    }
                }
            }
        }

        // Calculate overall confidence
        $overallConfidence = $termCounts['total'] > 0 ? 
            ($termCounts['validated'] / $termCounts['total']) * 100 : 0;

        $extractedData['confidence'] = $overallConfidence;
        $extractedData['validation_notes'] = $validationNotes;
        $extractedData['term_validation'] = $termCounts;
        
        return $extractedData;
    }

    /**
     * Get relevant medical terminologies for document type
     */
    private function getRelevantTerminologies(string $documentType): array
    {
        switch ($documentType) {
            case 'insurance_card':
                return array_merge(
                    $this->medicalTerminologies['insurance_terms'],
                    ['member', 'subscriber', 'beneficiary', 'coverage']
                );
            
            case 'clinical_note':
                return array_merge(
                    $this->medicalTerminologies['clinical_terms'],
                    $this->medicalTerminologies['anatomical_terms'],
                    $this->medicalTerminologies['measurement_terms']
                );
            
            case 'wound_photo':
                return array_merge(
                    $this->medicalTerminologies['clinical_terms'],
                    $this->medicalTerminologies['measurement_terms']
                );
                
            default:
                return array_merge(...array_values($this->medicalTerminologies));
        }
    }

    /**
     * Check if term matches medical terminology
     */
    private function isValidMedicalTerm(string $term, array $relevantTerms): bool
    {
        $normalizedTerm = strtolower(str_replace(['-', '_', ' '], '', $term));
        
        foreach ($relevantTerms as $medicalTerm) {
            $normalizedMedicalTerm = strtolower(str_replace(['-', '_', ' '], '', $medicalTerm));
            if (strpos($normalizedTerm, $normalizedMedicalTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if content contains medical terms
     */
    private function containsMedicalTerms(string $content, array $relevantTerms): bool
    {
        $normalizedContent = strtolower($content);
        
        foreach ($relevantTerms as $term) {
            if (strpos($normalizedContent, strtolower($term)) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate quality score based on 2025 standards
     */
    private function calculateQualityScore(array $data, string $documentType): array
    {
        $qualityMetrics = [
            'confidence_score' => 0,
            'terminology_accuracy' => 0,
            'completeness' => 0,
            'overall_grade' => 'F'
        ];

        // Base confidence score
        $baseConfidence = $data['confidence'] ?? 0;
        $qualityMetrics['confidence_score'] = $baseConfidence;

        // Terminology accuracy
        $termValidation = $data['term_validation'] ?? ['validated' => 0, 'total' => 1];
        $terminologyAccuracy = ($termValidation['validated'] / max($termValidation['total'], 1)) * 100;
        $qualityMetrics['terminology_accuracy'] = $terminologyAccuracy;

        // Completeness (based on expected fields for document type)
        $expectedFields = $this->getExpectedFieldsForDocumentType($documentType);
        $extractedFields = $this->countExtractedFields($data);
        $completeness = ($extractedFields / max($expectedFields, 1)) * 100;
        $qualityMetrics['completeness'] = min($completeness, 100);

        // Overall grade (2025 healthcare standards)
        $overallScore = ($baseConfidence + $terminologyAccuracy + $completeness) / 3;
        
        if ($overallScore >= 90) {
            $qualityMetrics['overall_grade'] = 'A';
        } elseif ($overallScore >= 80) {
            $qualityMetrics['overall_grade'] = 'B';
        } elseif ($overallScore >= 70) {
            $qualityMetrics['overall_grade'] = 'C';
        } elseif ($overallScore >= 60) {
            $qualityMetrics['overall_grade'] = 'D';
        } else {
            $qualityMetrics['overall_grade'] = 'F';
        }

        return $qualityMetrics;
    }

    /**
     * Enhanced clinical context processing
     */
    private function enhanceWithClinicalContext(array $data, string $documentType): array
    {
        // Add clinical context based on document type
        switch ($documentType) {
            case 'clinical_note':
                $data['clinical_context'] = $this->extractClinicalContext($data);
                break;
            
            case 'wound_photo':
                $data['wound_context'] = $this->extractWoundContext($data);
                break;
        }
        
        return $data;
    }

    /**
     * Extract clinical context from document
     */
    private function extractClinicalContext(array $data): array
    {
        $context = [
            'conditions' => [],
            'medications' => [],
            'measurements' => [],
            'procedures' => []
        ];

        // Extract content and analyze for clinical terms
        $allContent = $this->extractAllContent($data);
        
        // Simple pattern matching for clinical context
        if (preg_match_all('/\b(diabetes|hypertension|wound|ulcer|infection)\b/i', $allContent, $matches)) {
            $context['conditions'] = array_unique($matches[0]);
        }
        
        if (preg_match_all('/\b(\d+\s*(cm|mm|inch|in))\b/i', $allContent, $matches)) {
            $context['measurements'] = array_unique($matches[0]);
        }

        return $context;
    }

    /**
     * Extract wound-specific context
     */
    private function extractWoundContext(array $data): array
    {
        $context = [
            'wound_type' => null,
            'location' => null,
            'measurements' => [],
            'characteristics' => []
        ];

        $allContent = $this->extractAllContent($data);

        // Wound type detection
        $woundTypes = ['pressure', 'diabetic', 'venous', 'arterial', 'surgical'];
        foreach ($woundTypes as $type) {
            if (stripos($allContent, $type) !== false) {
                $context['wound_type'] = $type;
                break;
            }
        }

        // Location detection
        $locations = ['heel', 'ankle', 'sacrum', 'coccyx', 'foot', 'leg'];
        foreach ($locations as $location) {
            if (stripos($allContent, $location) !== false) {
                $context['location'] = $location;
                break;
            }
        }

        return $context;
    }

    /**
     * Map insurance card data to standard format
     */
    private function mapInsuranceCardData(array $data): array
    {
        $mapped = [];
        
        foreach ($data['documents'] ?? [] as $document) {
            foreach ($document['fields'] ?? [] as $fieldName => $field) {
                $mappedKey = $this->mapInsuranceFieldName($fieldName);
                $mapped[$mappedKey] = [
                    'value' => $field['content'] ?? $field['value'] ?? '',
                    'confidence' => $field['confidence'] ?? 0,
                    'original_field' => $fieldName
                ];
            }
        }

        return $mapped;
    }

    /**
     * Map insurance field names to standard format
     */
    private function mapInsuranceFieldName(string $fieldName): string
    {
        $mapping = [
            'Insurer' => 'insurance_company',
            'Member' => 'member_name',
            'MemberId' => 'member_id',
            'Subscriber' => 'subscriber_name',
            'SubscriberId' => 'subscriber_id',
            'Dependents' => 'dependents',
            'GroupNumber' => 'group_number',
            'PlanNumber' => 'plan_number',
            'PlanType' => 'plan_type',
            'RxBIN' => 'rx_bin',
            'RxPCN' => 'rx_pcn',
            'RxGroup' => 'rx_group',
            'Copays' => 'copays',
            'Deductibles' => 'deductibles'
        ];

        return $mapping[$fieldName] ?? strtolower(str_replace(' ', '_', $fieldName));
    }

    /**
     * Structure form data for output
     */
    private function structureFormData(array $data): array
    {
        $structured = [];
        
        foreach ($data['documents'] ?? [] as $document) {
            foreach ($document['fields'] ?? [] as $fieldName => $field) {
                $structured[$fieldName] = [
                    'value' => $field['content'] ?? $field['value'] ?? '',
                    'confidence' => $field['confidence'] ?? 0,
                    'type' => $field['type'] ?? 'string'
                ];
            }
        }

        return $structured;
    }

    /**
     * Extract all content from document for analysis
     */
    private function extractAllContent(array $data): string
    {
        $content = '';
        
        foreach ($data['documents'] ?? [] as $document) {
            foreach ($document['fields'] ?? [] as $field) {
                $content .= ($field['content'] ?? $field['value'] ?? '') . ' ';
            }
        }

        return trim($content);
    }

    /**
     * Get expected field count for document type
     */
    private function getExpectedFieldsForDocumentType(string $documentType): int
    {
        switch ($documentType) {
            case 'insurance_card':
                return 12; // member_id, insurance_company, group_number, etc.
            case 'clinical_note':
                return 8;  // patient_name, date, diagnosis, etc.
            case 'wound_photo':
                return 5;  // measurements, location, etc.
            default:
                return 6;
        }
    }

    /**
     * Count extracted fields
     */
    private function countExtractedFields(array $data): int
    {
        $count = 0;
        
        foreach ($data['documents'] ?? [] as $document) {
            $count += count($document['fields'] ?? []);
        }

        return $count;
    }

    /**
     * Poll for Azure DI results
     */
    private function pollForResults(string $operationLocation): array
    {
        $maxAttempts = 30;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->apiKey,
            ])->get($operationLocation);

            if (!$response->successful()) {
                throw new Exception("Failed to get operation status: " . $response->body());
            }

            $result = $response->json();
            
            if ($result['status'] === 'succeeded') {
                return $result['analyzeResult'] ?? [];
            }
            
            if ($result['status'] === 'failed') {
                throw new Exception("Azure Document Intelligence analysis failed: " . 
                    ($result['error']['message'] ?? 'Unknown error'));
            }

            $attempt++;
            sleep(2); // Wait 2 seconds before next attempt
        }

        throw new Exception('Azure Document Intelligence operation timed out');
    }

    /**
     * Check if service is configured
     */
    private function isConfigured(): bool
    {
        return !empty($this->endpoint) && !empty($this->apiKey);
    }

    /**
     * Create standardized error response
     */
    private function createErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'data' => [],
            'confidence' => 0,
            'validation_notes' => ["Error: {$message}"]
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
                return file_get_contents($file);
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