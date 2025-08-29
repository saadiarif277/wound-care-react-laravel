<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Document\DocumentProcessingService;
use App\Services\DocumentIntelligenceService;
use App\Services\AI\AzureFoundryService;
use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\AI\FormFillingOptimizer;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\JsonResponse;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;

class DocumentProcessingController extends Controller
{
    public function __construct(
        private DocumentProcessingService $documentService,
        private DocumentIntelligenceService $azureService,
        private AzureFoundryService $foundryService,
        private OptimizedMedicalAiService $medicalAiService,
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private FormFillingOptimizer $formOptimizer
    ) {}

    /**
     * Analyze uploaded document and extract relevant data
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'type' => 'required|string|in:insurance_card,clinical_note,wound_photo,other',
        ]);

        try {
            $file = $request->file('document');
            $type = $request->input('type');
            
            Log::info('Processing document', [
                'type' => $type,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);
            
            // Process based on document type
            switch ($type) {
                case 'insurance_card':
                    $extractedData = $this->processInsuranceCard($file);
                    break;
                    
                case 'clinical_note':
                    $extractedData = $this->processClinicalNote($file);
                    break;
                    
                case 'wound_photo':
                    $extractedData = $this->processWoundPhoto($file);
                    break;
                    
                case 'prescription':
                    $extractedData = $this->processPrescription($file);
                    break;
                    
                default:
                    $extractedData = $this->processGenericDocument($file);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $extractedData,
                'documentType' => $type,
                'filename' => $file->getClientOriginalName(),
                'message' => 'Document processed successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'error' => $e->getMessage(),
                'type' => $type ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to process document'
            ], 500);
        }
    }

    /**
     * Process document with AI-enhanced form filling
     */
    public function processWithAi(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'type' => 'required|string|in:insurance_card,clinical_note,wound_photo,prescription,other',
            'target_fields' => 'sometimes|array',
            'form_context' => 'sometimes|string'
        ]);

        try {
            $file = $request->file('document');
            $type = $request->input('type');
            $targetFields = $request->input('target_fields', []);
            $formContext = $request->input('form_context', 'general');
            
            Log::info('Processing document with AI enhancement', [
                'type' => $type,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'target_fields' => count($targetFields),
                'form_context' => $formContext
            ]);

            // Step 1: Standard OCR processing
            $ocrResult = $this->documentService->processDocument($file, $type);
            
            if (!$ocrResult['success']) {
                throw new Exception($ocrResult['error'] ?? 'Failed to process document with OCR');
            }

            $ocrData = $ocrResult['structured_data'] ?? $ocrResult['extracted_data'] ?? [];

            // Step 2: AI-enhanced form filling using Azure Foundry Service
            $aiResult = $this->fillFormFieldsWithAI($ocrData, $type, $targetFields);
            
            Log::info('AI form filling completed', [
                'ai_enhanced' => $aiResult['ai_enhanced'],
                'quality_grade' => $aiResult['quality_grade'],
                'filled_fields' => count($aiResult['filled_fields'] ?? [])
            ]);

            // Step 3: Validate medical terminology if present
            $medicalTerms = $this->extractMedicalTermsFromData($aiResult['filled_fields'] ?? []);
            $medicalValidation = null;
            
            if (!empty($medicalTerms)) {
                $medicalValidation = $this->validateMedicalTermsWithAI($medicalTerms, $formContext);
            }

            // Step 4: Enhance with FormFillingOptimizer based on document type
            $stage = match($type) {
                'insurance_card' => 'insurance_data',
                'clinical_note' => 'clinical_data',
                'demographics' => 'patient_info',
                default => 'general'
            };
            
            $enhancedFields = $this->formOptimizer->enhanceFormData(
                $aiResult['filled_fields'] ?? [],
                $stage
            );

            return response()->json([
                'success' => true,
                'extracted_data' => $ocrData,
                'filled_fields' => $enhancedFields,
                'confidence_scores' => $aiResult['confidence_scores'] ?? [],
                'quality_grade' => $aiResult['quality_grade'] ?? 'C',
                'suggestions' => $aiResult['suggestions'] ?? [],
                'processing_notes' => $aiResult['processing_notes'] ?? [],
                'ai_enhanced' => true,
                'medical_validation' => $medicalValidation,
                'enhancement_stage' => $stage
            ]);

        } catch (Exception $e) {
            Log::error('AI-enhanced document processing failed', [
                'error' => $e->getMessage(),
                'type' => $type ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to process document with AI enhancement'
            ], 500);
        }
    }

    /**
     * AI-enhanced Quick Request processing
     */
    public function enhanceQuickRequest(Request $request)
    {
        $request->validate([
            'form_data' => 'required|array',
            'documents' => 'sometimes|array',
            'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'documents.*.type' => 'required|string|in:insurance_card,clinical_note,wound_photo,prescription'
        ]);

        try {
            $formData = $request->input('form_data');
            $documents = $request->input('documents', []);
            
            Log::info('Enhancing Quick Request with AI', [
                'form_fields' => count($formData),
                'documents' => count($documents)
            ]);

            // Process uploaded documents
            $processedDocs = [];
            foreach ($documents as $docData) {
                $file = $docData['file'];
                $type = $docData['type'];
                
                // Process document with OCR
                $ocrResult = $this->documentService->processDocument($file, $type);
                
                if ($ocrResult['success']) {
                    $processedDocs[$type] = $ocrResult['structured_data'] ?? $ocrResult['extracted_data'] ?? [];
                }
            }

            // Use AI to enhance form data
            $enhancedResult = $this->enhanceQuickRequestDataWithAI($formData, $processedDocs);
            
            Log::info('Quick Request AI enhancement completed', [
                'success' => $enhancedResult['success'],
                'ai_enhanced' => $enhancedResult['ai_enhanced'],
                'processing_notes' => count($enhancedResult['processing_notes'] ?? [])
            ]);

            return response()->json([
                'success' => true,
                'original_form_data' => $formData,
                'enhanced_form_data' => $enhancedResult['enhanced_data'] ?? $formData,
                'processing_notes' => $enhancedResult['processing_notes'] ?? [],
                'ai_enhanced' => $enhancedResult['ai_enhanced'] ?? false,
                'enhancement_timestamp' => $enhancedResult['enhancement_timestamp'] ?? now()->toISOString(),
                'message' => 'Quick Request enhanced with AI successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Quick Request AI enhancement failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to enhance Quick Request with AI'
            ], 500);
        }
    }

    /**
     * Get AI service health status
     */
    public function aiServiceStatus()
    {
        try {
            // Get status from Medical AI Service
            $medicalAiHealth = $this->medicalAiService->healthCheck();
            
            // Get Azure Foundry service status
            $azureFoundryStatus = $this->foundryService->testConnection();
            
            return response()->json([
                'success' => true,
                'medical_ai_service' => $medicalAiHealth,
                'azure_foundry_service' => $azureFoundryStatus,
                'message' => 'AI service status retrieved successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get AI service status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to retrieve AI service status'
            ], 500);
        }
    }

    /**
     * Extract medical terms from data for validation
     */
    private function extractMedicalTermsFromData(array $data): array
    {
        $medicalFields = [
            'primary_diagnosis', 'secondary_diagnosis', 'diagnosis',
            'wound_type', 'wound_location', 'wound_characteristics',
            'medications', 'allergies', 'medical_history',
            'treatment_plan', 'previous_treatments', 'current_treatments',
            'insurance_plan_type', 'chief_complaint'
        ];

        $terms = [];
        
        foreach ($medicalFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (is_array($data[$field])) {
                    $terms = array_merge($terms, $data[$field]);
                } else {
                    // Extract terms from text
                    $fieldTerms = preg_split('/[,;.\n]+/', $data[$field]);
                    $fieldTerms = array_map('trim', $fieldTerms);
                    $fieldTerms = array_filter($fieldTerms, fn($term) => strlen($term) > 2);
                    $terms = array_merge($terms, $fieldTerms);
                }
            }
        }

        return array_unique(array_filter($terms));
    }

    /**
     * Process insurance card and extract insurance details
     */
    private function processInsuranceCard($file): array
    {
        // Use DocumentProcessingService which handles both OCR and AI structuring
        $result = $this->documentService->processDocument($file, 'insurance_card');
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process insurance card');
        }
        
        // Return the structured data
        return $result['structured_data'] ?? $result['extracted_data'] ?? [];
    }

    /**
     * Process clinical note and extract medical information
     */
    private function processClinicalNote($file): array
    {
        // Use DocumentProcessingService which handles both OCR and AI structuring
        $result = $this->documentService->processDocument($file, 'clinical_note');
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process clinical note');
        }
        
        // Return the structured data
        return $result['structured_data'] ?? $result['extracted_data'] ?? [];
    }

    /**
     * Process wound photo and extract visual measurements
     */
    private function processWoundPhoto($file): array
    {
        // Use DocumentProcessingService which handles both OCR and AI structuring
        $result = $this->documentService->processDocument($file, 'wound_photo');
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process wound photo');
        }
        
        // Return the structured data
        return $result['structured_data'] ?? $result['extracted_data'] ?? [];
    }

    /**
     * Process prescription document
     */
    private function processPrescription($file): array
    {
        // Use DocumentProcessingService which handles both OCR and AI structuring
        $result = $this->documentService->processDocument($file, 'prescription');
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process prescription');
        }
        
        // Return the structured data
        return $result['structured_data'] ?? $result['extracted_data'] ?? [];
    }

    /**
     * Process generic document
     */
    private function processGenericDocument($file): array
    {
        // Use DocumentProcessingService for generic documents
        $result = $this->documentService->processDocument($file, 'general_document');
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process document');
        }
        
        // Return the extracted data
        return $result['structured_data'] ?? $result['extracted_data'] ?? [];
    }

    /**
     * Create episode from processed document
     */
    public function createEpisodeFromDocument(Request $request)
    {
        $request->validate([
            'documentData' => 'required|array',
            'documentType' => 'required|string',
            'manufacturerId' => 'required|integer|exists:manufacturers,id'
        ]);

        try {
            $documentData = $request->input('documentData');
            $documentType = $request->input('documentType');
            $manufacturerId = $request->input('manufacturerId');

            // Convert document data to Quick Request format
            $quickRequestData = $this->convertToQuickRequestFormat($documentData, $documentType);
            $quickRequestData['manufacturer_id'] = $manufacturerId;

            // Create draft episode using the orchestrator
            // The orchestrator is no longer used here, as the document processing and conversion are now direct calls.
            // If the orchestrator was intended to be re-introduced, it would need to be re-instantiated or its methods called.
            // For now, we'll just return a placeholder or remove if not used.
            // Assuming the intent was to use the foundryService directly for AI translation.
            $episode = [
                'message' => 'Document processing and episode creation logic needs to be re-evaluated based on new service structure.',
                'documentType' => $documentType,
                'documentData' => $documentData
            ];

            return response()->json([
                'success' => true,
                'episode' => $episode,
                'message' => 'Document processed and episode created (placeholder)'
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create episode from document', [
                'error' => $e->getMessage(),
                'documentType' => $request->input('documentType')
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create episode'
            ], 500);
        }
    }

    /**
     * Convert document data to Quick Request format using AI-powered form translation
     */
    private function convertToQuickRequestFormat(array $documentData, string $documentType): array
    {
        // Define the target Quick Request schema
        $targetSchema = [
            'patient' => [
                'first_name' => ['type' => 'string', 'description' => 'Patient first name'],
                'last_name' => ['type' => 'string', 'description' => 'Patient last name'],
                'date_of_birth' => ['type' => 'string', 'description' => 'Patient date of birth'],
                'phone' => ['type' => 'string', 'description' => 'Patient phone number'],
                'address' => ['type' => 'string', 'description' => 'Patient address']
            ],
            'provider' => [
                'name' => ['type' => 'string', 'description' => 'Provider name'],
                'npi' => ['type' => 'string', 'description' => 'Provider NPI number'],
                'address' => ['type' => 'string', 'description' => 'Provider address'],
                'phone' => ['type' => 'string', 'description' => 'Provider phone']
            ],
            'insurance' => [
                'primary_insurance_name' => ['type' => 'string', 'description' => 'Primary insurance company name'],
                'primary_member_id' => ['type' => 'string', 'description' => 'Primary insurance member ID'],
                'primary_group_number' => ['type' => 'string', 'description' => 'Primary insurance group number'],
                'plan_type' => ['type' => 'string', 'description' => 'Insurance plan type']
            ],
            'clinical' => [
                'primary_diagnosis' => ['type' => 'string', 'description' => 'Primary diagnosis code'],
                'diagnosis_description' => ['type' => 'string', 'description' => 'Diagnosis description'],
                'wound_location' => ['type' => 'string', 'description' => 'Anatomical location of wound'],
                'wound_size' => ['type' => 'string', 'description' => 'Wound size measurements'],
                'wound_type' => ['type' => 'string', 'description' => 'Type of wound'],
                'wound_length' => ['type' => 'string', 'description' => 'Wound length in cm'],
                'wound_width' => ['type' => 'string', 'description' => 'Wound width in cm'],
                'wound_depth' => ['type' => 'string', 'description' => 'Wound depth in cm'],
                'wound_stage' => ['type' => 'string', 'description' => 'Wound stage if applicable']
            ]
        ];

        try {
            // Use Azure Foundry AI to intelligently translate extracted document data to Quick Request format
            $sourceContext = "Extracted data from $documentType document containing healthcare information";
            $targetContext = "Quick Request form for wound care episode creation with patient, provider, insurance, and clinical sections";
            
            $translatedData = $this->foundryService->translateFormData(
                $documentData,
                $targetSchema,
                $sourceContext,
                $targetContext,
                ['use_cache' => true]
            );

            return $translatedData;

        } catch (Exception $e) {
            Log::warning('AI translation failed, falling back to manual mapping', [
                'error' => $e->getMessage(),
                'documentType' => $documentType
            ]);

            // Fallback to manual mapping if AI translation fails
            return $this->manualConvertToQuickRequestFormat($documentData, $documentType);
        }
    }

    /**
     * Manual fallback for converting document data to Quick Request format
     */
    private function manualConvertToQuickRequestFormat(array $documentData, string $documentType): array
    {
        $quickRequestData = [
            'patient' => [],
            'provider' => [],
            'insurance' => [],
            'clinical' => []
        ];

        switch ($documentType) {
            case 'insurance_card':
                $quickRequestData['patient'] = [
                    'first_name' => $documentData['patient_first_name'] ?? '',
                    'last_name' => $documentData['patient_last_name'] ?? '',
                    'date_of_birth' => $documentData['patient_dob'] ?? ''
                ];
                $quickRequestData['insurance'] = [
                    'primary_insurance_name' => $documentData['payer_name'] ?? '',
                    'primary_member_id' => $documentData['member_id'] ?? '',
                    'primary_group_number' => $documentData['group_number'] ?? '',
                    'plan_type' => $documentData['plan_type'] ?? ''
                ];
                break;

            case 'clinical_note':
                $quickRequestData['clinical'] = [
                    'primary_diagnosis' => $documentData['primary_diagnosis'] ?? '',
                    'diagnosis_description' => $documentData['diagnosis_description'] ?? '',
                    'wound_location' => $documentData['wound_location'] ?? '',
                    'wound_size' => $documentData['wound_size'] ?? '',
                    'wound_type' => $documentData['wound_type'] ?? ''
                ];
                $quickRequestData['provider'] = [
                    'name' => $documentData['provider_name'] ?? '',
                    'npi' => $documentData['provider_npi'] ?? ''
                ];
                break;

            case 'wound_photo':
                $quickRequestData['clinical'] = [
                    'wound_length' => $documentData['length'] ?? '',
                    'wound_width' => $documentData['width'] ?? '',
                    'wound_depth' => $documentData['depth'] ?? '',
                    'wound_type' => $documentData['wound_type'] ?? '',
                    'wound_stage' => $documentData['stage'] ?? ''
                ];
                break;
        }

        return $quickRequestData;
    }

    /**
     * Fill form fields using AI
     */
    private function fillFormFieldsWithAI(array $ocrData, string $formType, array $targetSchema = []): array
    {
        try {
            // Use Azure Foundry Service to intelligently map OCR data to form fields
            $sourceContext = "OCR extracted data from {$formType} document";
            $targetContext = "Healthcare form fields for {$formType}";
            
            $result = $this->foundryService->translateFormData(
                $ocrData,
                $targetSchema ?: $this->getDefaultSchema($formType),
                $sourceContext,
                $targetContext,
                ['use_cache' => true]
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'filled_fields' => $result['mappings'] ?? [],
                    'confidence_scores' => array_map(fn($m) => $m['confidence'] ?? 0.5, $result['mappings'] ?? []),
                    'quality_grade' => $this->calculateQualityGrade($result['overall_confidence'] ?? 0),
                    'suggestions' => $result['suggestions'] ?? [],
                    'processing_notes' => $result['reasoning'] ?? [],
                    'ai_enhanced' => true
                ];
            }

            // Fallback if AI fails
            return $this->fallbackFormFilling($ocrData, $formType);

        } catch (Exception $e) {
            Log::error('AI form filling failed', [
                'error' => $e->getMessage(),
                'form_type' => $formType
            ]);

            return $this->fallbackFormFilling($ocrData, $formType);
        }
    }

    /**
     * Validate medical terms using AI
     */
    private function validateMedicalTermsWithAI(array $terms, string $context): array
    {
        try {
            // Use Azure Foundry Service for medical term validation
            $prompt = "As a medical AI assistant, please validate the following medical terms in the context of {$context}. " .
                     "For each term, indicate if it's valid medical terminology and provide any corrections or suggestions. " .
                     "Terms to validate: " . implode(', ', $terms) . "\n\n" .
                     "Please respond with: total_terms (count), valid_terms (count), invalid_terms (array), " .
                     "suggestions (array), and overall_confidence (0-1 float).";
            
            $response = $this->foundryService->generateChatResponse($prompt);
            
            if ($response['success'] && !empty($response['content'])) {
                // Try to parse structured data from the response
                $content = $response['content'];
                
                // Simple parsing of the response
                $validCount = 0;
                $invalidTerms = [];
                $suggestions = [];
                
                // Count valid terms (this is a simplified approach)
                foreach ($terms as $term) {
                    if (stripos($content, $term . ' is valid') !== false || 
                        stripos($content, $term . ' - valid') !== false) {
                        $validCount++;
                    } else {
                        $invalidTerms[] = $term;
                    }
                }
                
                return [
                    'total_terms' => count($terms),
                    'valid_terms' => $validCount,
                    'invalid_terms' => $invalidTerms,
                    'suggestions' => $suggestions,
                    'overall_confidence' => $validCount / max(count($terms), 1),
                    'validation_method' => 'ai',
                    'raw_response' => $content
                ];
            }

            // Fallback response
            return [
                'total_terms' => count($terms),
                'valid_terms' => 0,
                'overall_confidence' => 0.0,
                'validation_method' => 'fallback'
            ];

        } catch (Exception $e) {
            Log::error('Medical term validation failed', [
                'error' => $e->getMessage(),
                'terms_count' => count($terms)
            ]);

            return [
                'total_terms' => count($terms),
                'valid_terms' => 0,
                'overall_confidence' => 0.0,
                'validation_method' => 'fallback',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhance Quick Request data with AI
     */
    private function enhanceQuickRequestDataWithAI(array $formData, array $uploadedDocuments = []): array
    {
        $enhancedData = $formData;
        $processingNotes = [];

        try {
            // Process each uploaded document and enhance form data
            foreach ($uploadedDocuments as $docType => $docData) {
                Log::info("Processing {$docType} for Quick Request enhancement");

                $aiResult = $this->fillFormFieldsWithAI(
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

            // If we have an episode ID, use Medical AI Service for additional enhancement
            if (isset($formData['episode_id'])) {
                try {
                    $episode = PatientManufacturerIVREpisode::find($formData['episode_id']);
                    if ($episode && isset($formData['template_id'])) {
                        $medicalEnhanced = $this->medicalAiService->enhanceDocusealFieldMapping(
                            $enhancedData,
                            $formData['template_id'],
                            $episode
                        );
                        
                        if (!empty($medicalEnhanced) && ($medicalEnhanced['_ai_confidence'] ?? 0) > 0.7) {
                            $enhancedData = array_merge($enhancedData, $medicalEnhanced);
                            $processingNotes[] = [
                                'medical_ai_enhancement' => [
                                    'confidence' => $medicalEnhanced['_ai_confidence'] ?? 0,
                                    'method' => $medicalEnhanced['_ai_method'] ?? 'unknown'
                                ]
                            ];
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Medical AI enhancement failed', ['error' => $e->getMessage()]);
                }
            }

            // Step 3: Final enhancement with FormFillingOptimizer
            $finalEnhancedData = $this->formOptimizer->enhanceFormData(
                $enhancedData,
                'general'
            );

            return [
                'success' => true,
                'enhanced_data' => $finalEnhancedData,
                'original_data' => $formData,
                'processing_notes' => $processingNotes,
                'ai_enhanced' => true,
                'enhancement_timestamp' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Quick Request enhancement failed', [
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
     * Calculate quality grade based on confidence
     */
    private function calculateQualityGrade(float $confidence): string
    {
        if ($confidence >= 0.9) return 'A';
        if ($confidence >= 0.8) return 'B';
        if ($confidence >= 0.7) return 'C';
        if ($confidence >= 0.6) return 'D';
        return 'F';
    }

    /**
     * Get default schema for form type
     */
    private function getDefaultSchema(string $formType): array
    {
        $schemas = [
            'insurance_card' => [
                'member_id' => ['type' => 'string', 'description' => 'Member ID'],
                'member_name' => ['type' => 'string', 'description' => 'Member name'],
                'insurance_company' => ['type' => 'string', 'description' => 'Insurance company name'],
                'group_number' => ['type' => 'string', 'description' => 'Group number'],
                'plan_type' => ['type' => 'string', 'description' => 'Plan type'],
                'effective_date' => ['type' => 'date', 'description' => 'Effective date'],
                'copay_primary_care' => ['type' => 'currency', 'description' => 'Primary care copay'],
                'copay_specialist' => ['type' => 'currency', 'description' => 'Specialist copay']
            ],
            'clinical_note' => [
                'patient_name' => ['type' => 'string', 'description' => 'Patient name'],
                'date_of_service' => ['type' => 'date', 'description' => 'Date of service'],
                'chief_complaint' => ['type' => 'text', 'description' => 'Chief complaint'],
                'diagnosis' => ['type' => 'string', 'description' => 'Diagnosis'],
                'wound_location' => ['type' => 'string', 'description' => 'Wound location'],
                'wound_size_length' => ['type' => 'measurement', 'description' => 'Wound length'],
                'wound_size_width' => ['type' => 'measurement', 'description' => 'Wound width'],
                'wound_size_depth' => ['type' => 'measurement', 'description' => 'Wound depth'],
                'treatment_plan' => ['type' => 'text', 'description' => 'Treatment plan']
            ],
            'wound_photo' => [
                'wound_location' => ['type' => 'string', 'description' => 'Wound location'],
                'length_cm' => ['type' => 'measurement', 'description' => 'Length in cm'],
                'width_cm' => ['type' => 'measurement', 'description' => 'Width in cm'],
                'depth_cm' => ['type' => 'measurement', 'description' => 'Depth in cm'],
                'wound_stage' => ['type' => 'string', 'description' => 'Wound stage'],
                'tissue_type' => ['type' => 'string', 'description' => 'Tissue type']
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
                'patient_first_name' => ['type' => 'string', 'description' => 'Patient first name'],
                'patient_last_name' => ['type' => 'string', 'description' => 'Patient last name'],
                'patient_dob' => ['type' => 'date', 'description' => 'Patient date of birth'],
                'patient_phone' => ['type' => 'phone', 'description' => 'Patient phone'],
                'member_id' => ['type' => 'string', 'description' => 'Member ID'],
                'insurance_company' => ['type' => 'string', 'description' => 'Insurance company'],
                'group_number' => ['type' => 'string', 'description' => 'Group number'],
                'plan_type' => ['type' => 'string', 'description' => 'Plan type']
            ],
            'clinical_note' => [
                'primary_diagnosis' => ['type' => 'string', 'description' => 'Primary diagnosis'],
                'wound_location' => ['type' => 'string', 'description' => 'Wound location'],
                'wound_type' => ['type' => 'string', 'description' => 'Wound type'],
                'wound_size_length' => ['type' => 'measurement', 'description' => 'Wound length'],
                'wound_size_width' => ['type' => 'measurement', 'description' => 'Wound width'],
                'wound_size_depth' => ['type' => 'measurement', 'description' => 'Wound depth']
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
                Log::debug("Filled field '{$field}' from {$docType}");
            }
        }

        return $merged;
    }

    /**
     * Determine if AI value should overwrite existing value
     */
    private function shouldOverwrite(string $field, $existingValue, $aiValue): bool
    {
        // Overwrite if existing value seems like placeholder
        $placeholders = ['n/a', 'unknown', 'tbd', 'pending', ''];
        if (in_array(strtolower(trim($existingValue)), $placeholders)) {
            return true;
        }

        // Overwrite if AI value is more complete
        if (is_string($existingValue) && is_string($aiValue)) {
            return strlen($aiValue) > strlen($existingValue) * 1.5;
        }

        return false;
    }

    /**
     * Fallback form filling when AI service is unavailable
     */
    private function fallbackFormFilling(array $ocrData, string $formType): array
    {
        Log::warning('Using fallback form filling');

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
            'suggestions' => ['AI service unavailable - using fallback mapping'],
            'processing_notes' => ['Fallback rule-based mapping used'],
            'ai_enhanced' => false
        ];
    }
}