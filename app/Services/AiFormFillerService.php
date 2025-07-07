<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * AI Form Filler Service
 * 
 * Communicates with Python AI microservice for intelligent form filling,
 * field mapping, and medical terminology validation
 */
class AiFormFillerService
{
    private string $aiServiceUrl;
    private int $timeout;
    private bool $enableCache;

    public function __construct()
    {
        $this->aiServiceUrl = config('services.ai_form_filler.url', 'http://127.0.0.1:8080');
        $this->timeout = config('services.ai_form_filler.timeout', 90); // Increased timeout to 90s
        $this->enableCache = config('services.ai_form_filler.enable_cache', true);
    }

    /**
     * Fill form fields intelligently using AI agent
     */
    public function fillFormFields(array $ocrData, string $formType, array $targetSchema = []): array
    {
        try {
            Log::info('AI Form Filler: Starting intelligent form filling', [
                'form_type' => $formType,
                'ocr_fields' => count($ocrData),
                'target_fields' => count($targetSchema)
            ]);

            $response = Http::timeout($this->timeout)
                ->post($this->aiServiceUrl . '/map-fields', [
                    'ocr_data' => $ocrData,
                    'document_type' => $this->mapFormTypeToDocumentType($formType),
                    'target_schema' => $targetSchema ?: $this->getDefaultSchema($formType),
                    'include_confidence' => true
                ]);

            if (!$response->successful()) {
                throw new Exception("AI service returned status: " . $response->status());
            }

            $result = $response->json();
            
            Log::info('AI Form Filler: Successfully filled form fields', [
                'quality_grade' => $result['quality_grade'] ?? 'N/A',
                'mapped_fields' => count($result['mapped_fields'] ?? [])
            ]);

            return [
                'success' => true,
                'filled_fields' => $result['mapped_fields'] ?? [],
                'confidence_scores' => $result['confidence_scores'] ?? [],
                'quality_grade' => $result['quality_grade'] ?? 'C',
                'suggestions' => $result['suggestions'] ?? [],
                'processing_notes' => $result['processing_notes'] ?? [],
                'ai_enhanced' => true
            ];

        } catch (Exception $e) {
            Log::error('AI Form Filler: Failed to fill form fields. The service will now use a local fallback.', [
                'error' => $e->getMessage(),
                'form_type' => $formType,
            ]);

            // Fallback to basic mapping
            return $this->fallbackFormFilling($ocrData, $formType);
        }
    }

    /**
     * Validate medical terminology using AI agent
     */
    public function validateMedicalTerms(array $terms, string $context = 'general'): array
    {
        $cacheKey = 'ai_validate_' . md5(json_encode($terms) . $context);
        
        if ($this->enableCache && $cached = Cache::get($cacheKey)) {
            Log::info('AI Form Filler: Using cached medical validation');
            return $cached;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->aiServiceUrl . '/validate-terms', [
                    'terms' => $terms,
                    'context' => $this->mapContextToDocumentType($context),
                    'confidence_threshold' => 0.7,
                    'include_suggestions' => true
                ]);

            if (!$response->successful()) {
                throw new Exception("AI service returned status: " . $response->status());
            }

            $result = $response->json();
            
            if ($this->enableCache) {
                Cache::put($cacheKey, $result, now()->addHours(2));
            }

            Log::info('AI Form Filler: Validated medical terms', [
                'total_terms' => $result['total_terms'] ?? 0,
                'valid_terms' => $result['valid_terms'] ?? 0,
                'confidence' => $result['overall_confidence'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('AI Form Filler: Medical term validation failed', [
                'error' => $e->getMessage(),
                'terms_count' => count($terms)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total_terms' => count($terms),
                'valid_terms' => 0,
                'overall_confidence' => 0.0
            ];
        }
    }

    /**
     * Smart form filling for Quick Request workflow
     */
    public function enhanceQuickRequestData(array $formData, array $uploadedDocuments = []): array
    {
        $enhancedData = $formData;
        $processingNotes = [];

        try {
            // Process each uploaded document and enhance form data
            foreach ($uploadedDocuments as $docType => $docData) {
                Log::info("AI Form Filler: Processing {$docType} for Quick Request enhancement");

                $aiResult = $this->fillFormFields(
                    $docData, 
                    $docType, 
                    $this->getQuickRequestSchema($docType)
                );

                if ($aiResult['success']) {
                    $enhancedData = $this->mergeFormData(
                        $enhancedData, 
                        $aiResult['filled_fields'], 
                        $docType
                    );
                    
                    $processingNotes[] = [
                        'document' => $docType,
                        'grade' => $aiResult['quality_grade'],
                        'fields_filled' => count($aiResult['filled_fields']),
                        'suggestions' => $aiResult['suggestions']
                    ];
                }
            }

            // Validate medical terms in the enhanced data
            $medicalTerms = $this->extractMedicalTerms($enhancedData);
            if (!empty($medicalTerms)) {
                $validation = $this->validateMedicalTerms($medicalTerms, 'clinical_note');
                $processingNotes[] = [
                    'medical_validation' => [
                        'total_terms' => $validation['total_terms'] ?? 0,
                        'valid_terms' => $validation['valid_terms'] ?? 0,
                        'confidence' => $validation['overall_confidence'] ?? 0
                    ]
                ];
            }

            return [
                'success' => true,
                'enhanced_data' => $enhancedData,
                'original_data' => $formData,
                'processing_notes' => $processingNotes,
                'ai_enhanced' => true,
                'enhancement_timestamp' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('AI Form Filler: Quick Request enhancement failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'enhanced_data' => $formData,
                'error' => $e->getMessage(),
                'ai_enhanced' => false
            ];
        }
    }

    /**
     * Get AI service health status
     */
    public function getServiceHealth(): array
    {
        try {
            $response = Http::timeout(5)->get($this->aiServiceUrl . '/health');
            
            if ($response->successful()) {
                return array_merge($response->json(), ['accessible' => true]);
            }
            
            return [
                'accessible' => false,
                'status' => 'unhealthy',
                'error' => "HTTP {$response->status()}"
            ];

        } catch (Exception $e) {
            return [
                'accessible' => false,
                'status' => 'unreachable',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get terminology statistics from AI service
     */
    public function getTerminologyStats(): array
    {
        try {
            $response = Http::timeout(10)->get($this->aiServiceUrl . '/terminology-stats');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            throw new Exception("Failed to get terminology stats");

        } catch (Exception $e) {
            Log::error('AI Form Filler: Failed to get terminology stats', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage(),
                'total_terms' => 0,
                'domains' => []
            ];
        }
    }

    /**
     * Map form type to document type
     */
    private function mapFormTypeToDocumentType(string $formType): string
    {
        $mapping = [
            'insurance_card' => 'insurance_card',
            'clinical_note' => 'clinical_note',
            'wound_photo' => 'wound_photo',
            'prescription' => 'prescription',
            'demographics' => 'demographics',
            'patient_insurance' => 'insurance_card',
            'clinical_billing' => 'clinical_note'
        ];

        return $mapping[$formType] ?? 'general';
    }

    /**
     * Map context to document type
     */
    private function mapContextToDocumentType(string $context): string
    {
        $mapping = [
            'wound_care' => 'clinical_note',  // Changed from wound_photo to clinical_note
            'wound_photo' => 'wound_photo',
            'insurance' => 'insurance_card',
            'clinical' => 'clinical_note',
            'clinical_note' => 'clinical_note',
            'prescription' => 'prescription'
        ];

        return $mapping[$context] ?? 'general';
    }

    /**
     * Get default schema for form type
     */
    private function getDefaultSchema(string $formType): array
    {
        $schemas = [
            'insurance_card' => [
                'member_id' => 'string',
                'member_name' => 'string',
                'insurance_company' => 'string',
                'group_number' => 'string',
                'plan_type' => 'string',
                'effective_date' => 'date',
                'copay_primary_care' => 'currency',
                'copay_specialist' => 'currency',
                'deductible' => 'currency',
                'rx_bin' => 'string',
                'rx_pcn' => 'string'
            ],
            'clinical_note' => [
                'patient_name' => 'string',
                'date_of_service' => 'date',
                'chief_complaint' => 'text',
                'diagnosis' => 'string',
                'wound_location' => 'string',
                'wound_size_length' => 'measurement',
                'wound_size_width' => 'measurement',
                'wound_size_depth' => 'measurement',
                'wound_characteristics' => 'array',
                'treatment_plan' => 'text'
            ],
            'wound_photo' => [
                'wound_location' => 'string',
                'length_cm' => 'measurement',
                'width_cm' => 'measurement',
                'depth_cm' => 'measurement',
                'wound_stage' => 'string',
                'tissue_type' => 'string',
                'drainage_amount' => 'string',
                'surrounding_skin' => 'string'
            ]
        ];

        return $schemas[$formType] ?? [];
    }

    /**
     * Get Quick Request specific schema
     */
    private function getQuickRequestSchema(string $docType): array
    {
        $schemas = [
            'insurance_card' => [
                // Step 1: Patient & Insurance
                'patient_first_name' => 'string',
                'patient_last_name' => 'string',
                'patient_dob' => 'date',
                'patient_phone' => 'phone',
                'patient_email' => 'email',
                'member_id' => 'string',
                'insurance_company' => 'string',
                'group_number' => 'string',
                'plan_type' => 'string',
                'primary_care_copay' => 'currency',
                'specialist_copay' => 'currency'
            ],
            'clinical_note' => [
                // Step 2: Clinical & Billing
                'primary_diagnosis' => 'string',
                'wound_location' => 'string',
                'wound_type' => 'string',
                'wound_duration_weeks' => 'number',
                'wound_size_length' => 'measurement',
                'wound_size_width' => 'measurement',
                'wound_size_depth' => 'measurement',
                'previous_treatments' => 'array',
                'current_treatments' => 'array'
            ]
        ];

        return $schemas[$docType] ?? $this->getDefaultSchema($docType);
    }

    /**
     * Merge AI-filled data with existing form data
     */
    private function mergeFormData(array $formData, array $aiFilledData, string $docType): array
    {
        $merged = $formData;

        foreach ($aiFilledData as $field => $value) {
            // Only fill empty fields or enhance existing ones
            if (empty($merged[$field]) || $this->shouldOverwrite($field, $merged[$field], $value)) {
                $merged[$field] = $value;
                Log::debug("AI Form Filler: Filled field '{$field}' from {$docType}");
            }
        }

        return $merged;
    }

    /**
     * Determine if AI value should overwrite existing value
     */
    private function shouldOverwrite(string $field, $existingValue, $aiValue): bool
    {
        // Overwrite if AI value is more complete or detailed
        if (is_string($existingValue) && is_string($aiValue)) {
            return strlen($aiValue) > strlen($existingValue) * 1.5;
        }

        // Overwrite if existing value seems like placeholder
        $placeholders = ['n/a', 'unknown', 'tbd', 'pending', ''];
        return in_array(strtolower(trim($existingValue)), $placeholders);
    }

    /**
     * Extract medical terms from form data
     */
    private function extractMedicalTerms(array $formData): array
    {
        $medicalFields = [
            'primary_diagnosis', 'secondary_diagnosis', 'diagnosis',
            'wound_type', 'wound_location', 'wound_characteristics',
            'medications', 'allergies', 'medical_history',
            'treatment_plan', 'previous_treatments', 'current_treatments'
        ];

        $terms = [];
        
        foreach ($medicalFields as $field) {
            if (isset($formData[$field]) && !empty($formData[$field])) {
                if (is_array($formData[$field])) {
                    $terms = array_merge($terms, $formData[$field]);
                } else {
                    // Extract terms from text using simple splitting
                    $fieldTerms = preg_split('/[,;.\n]+/', $formData[$field]);
                    $fieldTerms = array_map('trim', $fieldTerms);
                    $fieldTerms = array_filter($fieldTerms, fn($term) => strlen($term) > 2);
                    $terms = array_merge($terms, $fieldTerms);
                }
            }
        }

        return array_unique(array_filter($terms));
    }

    /**
     * Fallback form filling when AI service is unavailable
     */
    private function fallbackFormFilling(array $ocrData, string $formType): array
    {
        Log::warning('AI Form Filler: Using fallback form filling');

        $basicMapping = [];
        
        // Simple rule-based mapping as fallback
        foreach ($ocrData as $key => $value) {
            $normalizedKey = strtolower(str_replace([' ', '-'], '_', $key));
            $basicMapping[$normalizedKey] = $value;
        }

        return [
            'success' => true,
            'filled_fields' => $basicMapping,
            'confidence_scores' => array_fill_keys(array_keys($basicMapping), 0.5),
            'quality_grade' => 'D',
            'suggestions' => ['Consider enabling AI service for better accuracy'],
            'processing_notes' => ['Fallback rule-based mapping used'],
            'ai_enhanced' => false
        ];
    }
} 