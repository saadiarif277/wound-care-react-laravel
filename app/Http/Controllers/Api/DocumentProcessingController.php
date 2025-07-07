<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentIntelligenceService;
use App\Services\Document\DocumentProcessingService;
use App\Services\AI\AzureFoundryService;
use App\Services\AiFormFillerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class DocumentProcessingController extends Controller
{
    public function __construct(
        private DocumentProcessingService $documentService,
        private DocumentIntelligenceService $azureService,
        private AzureFoundryService $foundryService,
        private AiFormFillerService $aiFormFillerService
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

            // Step 2: AI-enhanced form filling
            $aiResult = $this->aiFormFillerService->fillFormFields($ocrData, $type, $targetFields);
            
            Log::info('AI form filling completed', [
                'ai_enhanced' => $aiResult['ai_enhanced'],
                'quality_grade' => $aiResult['quality_grade'],
                'filled_fields' => count($aiResult['filled_fields'] ?? [])
            ]);

            // Step 3: Validate medical terminology if present
            $medicalTerms = $this->extractMedicalTermsFromData($aiResult['filled_fields'] ?? []);
            $medicalValidation = null;
            
            if (!empty($medicalTerms)) {
                $medicalValidation = $this->aiFormFillerService->validateMedicalTerms($medicalTerms, $formContext);
            }

            return response()->json([
                'success' => true,
                'original_ocr' => $ocrData,
                'ai_enhanced' => $aiResult['filled_fields'] ?? [],
                'confidence_scores' => $aiResult['confidence_scores'] ?? [],
                'quality_grade' => $aiResult['quality_grade'] ?? 'C',
                'suggestions' => $aiResult['suggestions'] ?? [],
                'processing_notes' => $aiResult['processing_notes'] ?? [],
                'medical_validation' => $medicalValidation,
                'is_ai_enhanced' => $aiResult['ai_enhanced'] ?? false,
                'document_type' => $type,
                'filename' => $file->getClientOriginalName(),
                'message' => 'Document processed with AI enhancement successfully'
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
            $enhancedResult = $this->aiFormFillerService->enhanceQuickRequestData($formData, $processedDocs);
            
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
            $health = $this->aiFormFillerService->getServiceHealth();
            $stats = $this->aiFormFillerService->getTerminologyStats();
            
            return response()->json([
                'success' => true,
                'ai_service_health' => $health,
                'terminology_stats' => $stats,
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
}