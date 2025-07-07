<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AzureDocumentIntelligenceService;
use App\Services\DocumentIntelligenceService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\AI\AzureFoundryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class DocumentProcessingController extends Controller
{
    public function __construct(
        private AzureDocumentIntelligenceService $azureService,
        private DocumentIntelligenceService $documentService,
        private QuickRequestOrchestrator $orchestrator,
        private AzureFoundryService $foundryService
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
     * Process insurance card and extract insurance details
     */
    private function processInsuranceCard($file): array
    {
        // First, use Azure Document Intelligence for OCR
        $ocrData = $this->documentService->extractFilledFormData($file);
        
        // Use Azure Foundry AI to structure the data specifically for insurance cards
        $context = "Insurance card document containing member information, policy details, and coverage data";
        
        $targetSchema = [
            'payer_name' => ['type' => 'string', 'description' => 'Insurance company name'],
            'member_id' => ['type' => 'string', 'description' => 'Policy/member ID number'],
            'group_number' => ['type' => 'string', 'description' => 'Group number'],
            'plan_type' => ['type' => 'string', 'description' => 'HMO, PPO, Medicare, etc.'],
            'patient_first_name' => ['type' => 'string', 'description' => 'Patient first name'],
            'patient_last_name' => ['type' => 'string', 'description' => 'Patient last name'],
            'patient_dob' => ['type' => 'string', 'description' => 'Date of birth'],
            'effective_date' => ['type' => 'string', 'description' => 'Policy effective date'],
            'copay_amount' => ['type' => 'string', 'description' => 'Copay amount']
        ];

        $structuredData = $this->foundryService->extractStructuredData(
            json_encode($ocrData),
            $targetSchema,
            $context
        );

        return $structuredData;
    }

    /**
     * Process clinical note and extract medical information
     */
    private function processClinicalNote($file): array
    {
        $ocrData = $this->documentService->extractFilledFormData($file);
        
        // Use Azure Foundry AI to extract clinical information
        $context = "Clinical note or medical document containing wound care information, diagnoses, and treatment details";
        
        $targetSchema = [
            'primary_diagnosis' => ['type' => 'string', 'description' => 'ICD-10 code if available'],
            'diagnosis_description' => ['type' => 'string', 'description' => 'Detailed diagnosis description'],
            'wound_location' => ['type' => 'string', 'description' => 'Anatomical location of wound'],
            'wound_size' => ['type' => 'string', 'description' => 'Measurements in cm'],
            'wound_type' => ['type' => 'string', 'description' => 'Pressure ulcer, diabetic foot ulcer, etc.'],
            'duration_weeks' => ['type' => 'string', 'description' => 'How long the wound has existed'],
            'previous_treatments' => ['type' => 'string', 'description' => 'Treatments tried'],
            'provider_name' => ['type' => 'string', 'description' => 'Physician name'],
            'provider_npi' => ['type' => 'string', 'description' => 'NPI number if available'],
            'date_of_service' => ['type' => 'string', 'description' => 'Date of service']
        ];

        $structuredData = $this->foundryService->extractStructuredData(
            json_encode($ocrData),
            $targetSchema,
            $context
        );

        return $structuredData;
    }

    /**
     * Process wound photo and extract visual measurements
     */
    private function processWoundPhoto($file): array
    {
        // For wound photos, we'll use a combination of OCR and image analysis
        $ocrData = $this->documentService->extractFilledFormData($file);
        
        // Use Azure Foundry AI to analyze wound photo characteristics
        $context = "Wound photograph with measurement tools and visual characteristics for medical assessment";
        
        $targetSchema = [
            'length' => ['type' => 'string', 'description' => 'Length in cm'],
            'width' => ['type' => 'string', 'description' => 'Width in cm'],
            'depth' => ['type' => 'string', 'description' => 'Depth in cm if visible'],
            'wound_type' => ['type' => 'string', 'description' => 'Pressure ulcer, diabetic foot ulcer, venous ulcer, etc.'],
            'stage' => ['type' => 'string', 'description' => 'If pressure ulcer: Stage 1-4'],
            'wound_bed_color' => ['type' => 'string', 'description' => 'Red, yellow, black, mixed'],
            'drainage_amount' => ['type' => 'string', 'description' => 'None, minimal, moderate, heavy'],
            'surrounding_skin_condition' => ['type' => 'string', 'description' => 'Description of surrounding skin'],
            'measurement_tool_visible' => ['type' => 'boolean', 'description' => 'Whether ruler or measuring device is visible']
        ];

        $structuredData = $this->foundryService->extractStructuredData(
            json_encode($ocrData),
            $targetSchema,
            $context
        );

        return $structuredData;
    }

    /**
     * Process generic document
     */
    private function processGenericDocument($file): array
    {
        $ocrData = $this->documentService->extractFilledFormData($file);
        
        // Return the raw OCR data for generic documents
        return [
            'extracted_text' => $ocrData['data'] ?? [],
            'document_type' => 'generic',
            'processing_method' => 'ocr_only'
        ];
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
            $episode = $this->orchestrator->createDraftEpisode($quickRequestData);

            return response()->json([
                'success' => true,
                'episode' => $episode,
                'message' => 'Draft episode created from document data'
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