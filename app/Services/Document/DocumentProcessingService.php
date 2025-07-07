<?php

namespace App\Services\Document;

use App\Services\DocumentIntelligenceService;
use App\Services\AI\AzureFoundryService;
use App\Models\PatientManufacturerIVREpisode;
use App\Logging\PhiSafeLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

class DocumentProcessingService
{
    public function __construct(
        protected DocumentIntelligenceService $documentIntelligence,
        protected AzureFoundryService $foundryService,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Process document with 2025 healthcare OCR best practices
     */
    public function processDocument($file, ?string $documentType = null): array
    {
        try {
            // Detect document type if not provided
            if (!$documentType) {
                $documentType = $this->detectDocumentType($file);
            }

            // Use enhanced 2025 methods based on document type
            switch ($documentType) {
                case 'insurance_card':
                    $extractedData = $this->documentIntelligence->analyzeInsuranceCard($file);
                    break;
                    
                case 'clinical_note':
                    $extractedData = $this->documentIntelligence->analyzeClinicalDocument($file, 'clinical_note');
                    break;
                    
                case 'wound_photo':
                    $extractedData = $this->documentIntelligence->analyzeClinicalDocument($file, 'wound_photo');
                    break;
                    
                case 'prescription':
                    $extractedData = $this->documentIntelligence->analyzeClinicalDocument($file, 'prescription');
                    break;
                    
                default:
            $extractedData = $this->documentIntelligence->extractFilledFormData($file);
                    break;
            }

            if (!$extractedData['success']) {
                throw new Exception($extractedData['error'] ?? 'Failed to extract data from document');
            }

            // Enhanced AI structuring with medical context
            $structuredData = $this->structureDataWithMedicalContext(
                $extractedData['data'], 
                $documentType,
                $extractedData['quality_score'] ?? []
            );

            return [
                'success' => true,
                'extracted_data' => $extractedData['data'],
                'structured_data' => $structuredData,
                'document_type' => $documentType,
                'confidence' => $extractedData['confidence'] ?? 0,
                'quality_score' => $extractedData['quality_score'] ?? [],
                'validation_notes' => $extractedData['validation_notes'] ?? [],
                'processing_method' => $extractedData['processing_method'] ?? 'enhanced_2025',
                'metadata' => [
                    'processing_date' => now()->toISOString(),
                    'file_type' => $file instanceof \Illuminate\Http\UploadedFile ? 
                        $file->getMimeType() : 'unknown'
                ]
            ];

        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'error' => $e->getMessage(),
                'document_type' => $documentType,
                'file' => $file instanceof \Illuminate\Http\UploadedFile ? 
                    $file->getClientOriginalName() : 'unknown'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'document_type' => $documentType
            ];
        }
    }

    /**
     * Enhanced AI structuring with medical context
     */
    private function structureDataWithMedicalContext(array $extractedData, string $documentType, array $qualityScore): array
    {
        // Use foundry service for medical-aware structuring
        $prompt = $this->buildMedicalPrompt($extractedData, $documentType, $qualityScore);
        
        try {
            $targetSchema = $this->getTargetSchema($documentType);
            $response = $this->foundryService->extractStructuredData(
                json_encode($extractedData),
                $targetSchema,
                $prompt
            );
            
            if (is_array($response) && isset($response['extracted_data'])) {
                return $response['extracted_data'];
            }
            
            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            Log::warning('AI structuring failed, using fallback', [
                'error' => $e->getMessage(),
                'document_type' => $documentType
            ]);
        }
        
        // Fallback to rule-based structuring
        return $this->fallbackStructuring($extractedData, $documentType);
    }

    /**
     * Build medical-aware prompt for AI structuring
     */
    private function buildMedicalPrompt(array $extractedData, string $documentType, array $qualityScore): string
    {
        $qualityContext = '';
        if (!empty($qualityScore)) {
            $qualityContext = "Quality Assessment: Overall Grade {$qualityScore['overall_grade']}, " .
                            "Confidence: {$qualityScore['confidence_score']}%, " .
                            "Terminology Accuracy: {$qualityScore['terminology_accuracy']}%";
        }

        return match($documentType) {
            'insurance_card' => "You are a healthcare data extraction expert. Extract and structure insurance card information from this OCR data. Focus on: member ID, insurance company, group number, plan type, copays, deductibles, and prescription benefits. Validate medical terminology and ensure HIPAA compliance. {$qualityContext}",
            
            'clinical_note' => "You are a clinical documentation expert. Extract and structure clinical note information focusing on: patient demographics, chief complaint, history of present illness, physical examination findings, assessment, and plan. Pay special attention to wound care terminology, measurements, and clinical assessments. {$qualityContext}",
            
            'wound_photo' => "You are a wound care specialist. Extract and structure wound assessment information focusing on: wound location, size measurements (length, width, depth), wound characteristics (color, drainage, edges), staging if applicable, and any visible complications. {$qualityContext}",
            
            'prescription' => "You are a pharmaceutical data expert. Extract and structure prescription information focusing on: patient name, prescriber, medication name, strength, dosage form, quantity, directions for use, refills, and any special instructions. Validate drug names and dosages. {$qualityContext}",
            
            default => "You are a healthcare data extraction expert. Extract and structure the form data with attention to medical terminology, patient information, and clinical context. Ensure all PHI is properly categorized. {$qualityContext}"
        };
    }

    /**
     * Fallback rule-based structuring
     */
    private function fallbackStructuring(array $extractedData, string $documentType): array
    {
        $structured = [];
        
        // Extract fields based on document type
        switch ($documentType) {
            case 'insurance_card':
                $structured = $this->structureInsuranceCard($extractedData);
                break;
                
            case 'clinical_note':
                $structured = $this->structureClinicalNote($extractedData);
                break;
                
            case 'wound_photo':
                $structured = $this->structureWoundPhoto($extractedData);
                break;
                
            default:
                $structured = $this->structureGenericForm($extractedData);
                break;
        }
        
        return $structured;
    }

    /**
     * Structure insurance card data
     */
    private function structureInsuranceCard(array $data): array
    {
        $structured = [
            'member_id' => '',
            'member_name' => '',
            'insurance_company' => '',
            'group_number' => '',
            'plan_type' => '',
            'effective_date' => '',
            'copays' => [],
            'deductibles' => [],
            'rx_info' => []
        ];

        // Map common insurance fields
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace([' ', '-'], '_', $key));
            $fieldValue = is_array($value) ? ($value['value'] ?? '') : $value;
            
            switch ($normalizedKey) {
                case 'member_id':
                case 'memberid':
                case 'id':
                    $structured['member_id'] = $fieldValue;
                    break;
                case 'member_name':
                case 'member':
                case 'name':
                    $structured['member_name'] = $fieldValue;
                    break;
                case 'insurance_company':
                case 'insurer':
                case 'company':
                    $structured['insurance_company'] = $fieldValue;
                    break;
                case 'group_number':
                case 'group':
                case 'grp':
                    $structured['group_number'] = $fieldValue;
                    break;
                case 'plan_type':
                case 'plan':
                    $structured['plan_type'] = $fieldValue;
                    break;
            }
        }

        return $structured;
    }

    /**
     * Structure clinical note data
     */
    private function structureClinicalNote(array $data): array
    {
        return [
            'patient_name' => $this->extractField($data, ['patient_name', 'name', 'patient']),
            'date_of_service' => $this->extractField($data, ['date', 'service_date', 'visit_date']),
            'chief_complaint' => $this->extractField($data, ['chief_complaint', 'cc', 'complaint']),
            'diagnosis' => $this->extractField($data, ['diagnosis', 'assessment', 'dx']),
            'treatment_plan' => $this->extractField($data, ['plan', 'treatment', 'recommendations']),
            'medications' => $this->extractField($data, ['medications', 'meds', 'prescriptions']),
            'vital_signs' => $this->extractVitalSigns($data),
            'wound_assessment' => $this->extractWoundAssessment($data)
        ];
    }

    /**
     * Structure wound photo data
     */
    private function structureWoundPhoto(array $data): array
    {
        return [
            'wound_location' => $this->extractField($data, ['location', 'site', 'anatomical_location']),
            'measurements' => $this->extractMeasurements($data),
            'wound_characteristics' => $this->extractWoundCharacteristics($data),
            'staging' => $this->extractField($data, ['stage', 'staging', 'grade']),
            'drainage' => $this->extractField($data, ['drainage', 'exudate', 'discharge']),
            'tissue_type' => $this->extractField($data, ['tissue', 'tissue_type', 'wound_bed'])
        ];
    }

    /**
     * Extract field value from data
     */
    private function extractField(array $data, array $possibleKeys): string
    {
        foreach ($possibleKeys as $key) {
            if (isset($data[$key])) {
                return is_array($data[$key]) ? ($data[$key]['value'] ?? '') : $data[$key];
            }
        }
        return '';
    }

    /**
     * Extract vital signs from clinical data
     */
    private function extractVitalSigns(array $data): array
    {
        $vitals = [];
        
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower($key);
            $fieldValue = is_array($value) ? ($value['value'] ?? '') : $value;
            
            if (strpos($normalizedKey, 'blood_pressure') !== false || 
                strpos($normalizedKey, 'bp') !== false) {
                $vitals['blood_pressure'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'temperature') !== false || 
                     strpos($normalizedKey, 'temp') !== false) {
                $vitals['temperature'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'pulse') !== false || 
                     strpos($normalizedKey, 'heart_rate') !== false) {
                $vitals['pulse'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'respiratory') !== false || 
                     strpos($normalizedKey, 'resp') !== false) {
                $vitals['respiratory_rate'] = $fieldValue;
            }
        }
        
        return $vitals;
    }

    /**
     * Extract measurements from data
     */
    private function extractMeasurements(array $data): array
    {
        $measurements = [];
        
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower($key);
            $fieldValue = is_array($value) ? ($value['value'] ?? '') : $value;
            
            if (strpos($normalizedKey, 'length') !== false) {
                $measurements['length'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'width') !== false) {
                $measurements['width'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'depth') !== false) {
                $measurements['depth'] = $fieldValue;
            } elseif (preg_match('/\d+\s*(cm|mm|inch|in)/i', $fieldValue)) {
                $measurements['additional'][] = $fieldValue;
            }
        }
        
        return $measurements;
    }

    /**
     * Extract wound characteristics
     */
    private function extractWoundCharacteristics(array $data): array
    {
        $characteristics = [];
        
        $woundTerms = [
            'red', 'pink', 'yellow', 'black', 'necrotic', 'slough', 'eschar',
            'granulation', 'epithelialization', 'clean', 'infected', 'inflamed'
        ];
        
        foreach ($data as $key => $value) {
            $fieldValue = is_array($value) ? ($value['value'] ?? '') : $value;
            $normalizedValue = strtolower($fieldValue);
            
            foreach ($woundTerms as $term) {
                if (strpos($normalizedValue, $term) !== false) {
                    $characteristics[] = $term;
                }
            }
        }
        
        return array_unique($characteristics);
    }

    /**
     * Extract wound assessment from clinical data
     */
    private function extractWoundAssessment(array $data): array
    {
        $assessment = [];
        
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower($key);
            $fieldValue = is_array($value) ? ($value['value'] ?? '') : $value;
            
            if (strpos($normalizedKey, 'wound') !== false) {
                $assessment['wound_type'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'stage') !== false || 
                     strpos($normalizedKey, 'staging') !== false) {
                $assessment['staging'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'drainage') !== false || 
                     strpos($normalizedKey, 'exudate') !== false) {
                $assessment['drainage'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'size') !== false || 
                     strpos($normalizedKey, 'measurement') !== false) {
                $assessment['measurements'] = $fieldValue;
            } elseif (strpos($normalizedKey, 'color') !== false || 
                     strpos($normalizedKey, 'appearance') !== false) {
                $assessment['appearance'] = $fieldValue;
            }
        }
        
        return $assessment;
    }

    /**
     * Structure generic form data
     */
    private function structureGenericForm(array $data): array
    {
        $structured = [];
        
        foreach ($data as $key => $value) {
            $structured[$key] = is_array($value) ? ($value['value'] ?? '') : $value;
        }
        
        return $structured;
    }

    /**
     * Detect document type based on file content and name
     */
    protected function detectDocumentType(UploadedFile $file): string
    {
        $filename = strtolower($file->getClientOriginalName());
        $mimeType = $file->getMimeType();

        // Check filename patterns
        if (str_contains($filename, 'insurance') || str_contains($filename, 'card')) {
            return 'insurance_card';
        }

        if (str_contains($filename, 'clinical') || str_contains($filename, 'note')) {
            return 'clinical_note';
        }

        if (str_contains($filename, 'referral')) {
            return 'referral';
        }

        if (str_contains($filename, 'wound') || str_contains($filename, 'photo')) {
            return 'wound_photo';
        }

        // Check by mime type
        if (str_starts_with($mimeType, 'image/')) {
            return 'clinical_photo';
        }

        // Default fallback
        return 'general_document';
    }

    /**
     * Store the uploaded document securely
     */
    protected function storeDocument(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "documents/uploaded/{$filename}";

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Attach processed document to an episode
     */
    public function attachToEpisode(PatientManufacturerIVREpisode $episode, array $documentData): void
    {
        // This would integrate with your FHIR document attachment system
        // For now, we'll store it in the episode metadata
        $currentDocuments = $episode->metadata['attached_documents'] ?? [];
        $currentDocuments[] = [
            'id' => Str::uuid(),
            'type' => $documentData['document_type'],
            'file_path' => $documentData['file_path'],
            'structured_data' => $documentData['structured_data'],
            'attached_at' => now()->toISOString()
        ];

        $episode->update([
            'metadata' => array_merge($episode->metadata ?? [], [
                'attached_documents' => $currentDocuments
            ])
        ]);

        $this->logger->info('Document attached to episode', [
            'episode_id' => $episode->id,
            'document_type' => $documentData['document_type']
        ]);
    }

    /**
     * Get target schema for document type
     */
    private function getTargetSchema(string $documentType): array
    {
        switch ($documentType) {
            case 'insurance_card':
                return [
                    'member_id' => 'string',
                    'member_name' => 'string',
                    'insurance_company' => 'string',
                    'group_number' => 'string',
                    'plan_type' => 'string',
                    'effective_date' => 'string',
                    'copays' => 'array',
                    'deductibles' => 'array',
                    'rx_info' => 'array'
                ];

            case 'clinical_note':
                return [
                    'patient_name' => 'string',
                    'date_of_service' => 'string',
                    'chief_complaint' => 'string',
                    'diagnosis' => 'string',
                    'treatment_plan' => 'string',
                    'medications' => 'array',
                    'vital_signs' => 'array',
                    'wound_assessment' => 'array'
                ];

            case 'wound_photo':
                return [
                    'wound_location' => 'string',
                    'measurements' => 'array',
                    'wound_characteristics' => 'array',
                    'staging' => 'string',
                    'drainage' => 'string',
                    'tissue_type' => 'string'
                ];

            default:
                return [
                    'extracted_fields' => 'array',
                    'confidence_score' => 'number'
                ];
        }
    }

    /**
     * Get context description for document type to help AI understand extraction task
     */
    protected function getContextForDocumentType(string $documentType): string
    {
        switch ($documentType) {
            case 'insurance_card':
                return "Insurance card document containing member information, policy details, and coverage data";

            case 'clinical_note':
                return "Clinical note or medical document containing wound care information, diagnoses, and treatment details";

            case 'wound_photo':
                return "Wound photograph with measurement tools and visual characteristics for medical assessment";

            case 'referral':
                return "Medical referral document containing provider information, patient details, and referral reasons";

            default:
                return "General medical document that may contain patient, provider, or clinical information";
        }
    }
}