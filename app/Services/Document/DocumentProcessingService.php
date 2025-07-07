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

class DocumentProcessingService
{
    public function __construct(
        protected DocumentIntelligenceService $documentIntelligence,
        protected AzureFoundryService $foundryService,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Process an uploaded document with OCR and intelligent extraction
     */
    public function processDocument(UploadedFile $file, ?string $documentType = null): array
    {
        $this->logger->info('Starting document processing', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'type' => $documentType
        ]);

        try {
            // Detect document type if not provided
            if (!$documentType) {
                $documentType = $this->detectDocumentType($file);
            }

            // Extract text using Azure Document Intelligence
            $extractedData = $this->documentIntelligence->extractFilledFormData($file);

            if (empty($extractedData)) {
                throw new Exception('No data could be extracted from the document');
            }

            // Convert structured data to text for AI processing
            $extractedText = json_encode($extractedData, JSON_PRETTY_PRINT);

            // Use AI to structure the extracted data
            $structuredData = $this->structureExtractedData($extractedText, $documentType);

            // Ensure confidence_score is always present
            if (!isset($structuredData['confidence_score'])) {
                $structuredData['confidence_score'] = 0.8;
            }

            // Store the document
            $storedPath = $this->storeDocument($file);

            $result = [
                'success' => true,
                'document_type' => $documentType,
                'extracted_data' => $extractedData, // Original structured data from OCR
                'extracted_text' => $extractedText, // JSON representation for AI
                'structured_data' => $structuredData, // AI-enhanced structured data
                'file_path' => $storedPath,
                'confidence_score' => $structuredData['confidence_score']
            ];

            $this->logger->info('Document processing completed successfully', [
                'document_type' => $documentType,
                'confidence_score' => $result['confidence_score']
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Document processing failed', [
                'error' => $e->getMessage(),
                'filename' => $file->getClientOriginalName()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'document_type' => $documentType ?? 'unknown'
            ];
        }
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
     * Use AI to structure extracted text based on document type
     */
    protected function structureExtractedData(string $extractedText, string $documentType): array
    {
        try {
            // Use the correct AzureFoundryService method for data extraction
            $targetSchema = $this->getTargetSchema($documentType);
            $context = $this->getContextForDocumentType($documentType);

            $response = $this->foundryService->extractStructuredData(
                $extractedText,
                $targetSchema,
                $context
            );

            // If the response is an array and contains 'extracted_data', return it
            if (is_array($response) && isset($response['extracted_data'])) {
                return $response['extracted_data'];
            }

            // Otherwise, return the response as-is or an error
            return is_array($response) ? $response : ['error' => 'Failed to structure data'];

        } catch (Exception $e) {
            $this->logger->warning('AI structuring failed, using basic extraction', [
                'error' => $e->getMessage()
            ]);

            // Fallback to basic extraction
            return $this->basicDataExtraction($extractedText, $documentType);
        }
    }

    /**
     * Basic data extraction fallback when AI fails
     */
    protected function basicDataExtraction(string $text, string $documentType): array
    {
        $data = ['confidence_score' => 0.5];

        // Basic regex patterns for common fields
        $patterns = [
            'member_id' => '/(?:member|id|policy)[\s#:]*([A-Z0-9]{6,20})/i',
            'phone' => '/(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})/',
            'date' => '/(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            'name' => '/(?:name|patient)[\s:]*([A-Z][a-z]+\s+[A-Z][a-z]+)/i'
        ];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data[$field] = trim($matches[1]);
            }
        }

        return $data;
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
     * Get target schema for structured data extraction based on document type
     */
    protected function getTargetSchema(string $documentType): array
    {
        switch ($documentType) {
            case 'insurance_card':
                return [
                    'payer_name' => ['type' => 'string', 'description' => 'Insurance company name'],
                    'member_id' => ['type' => 'string', 'description' => 'Policy/member ID number'],
                    'group_number' => ['type' => 'string', 'description' => 'Group number'],
                    'plan_type' => ['type' => 'string', 'description' => 'HMO, PPO, Medicare, etc.'],
                    'patient_first_name' => ['type' => 'string', 'description' => 'Patient first name'],
                    'patient_last_name' => ['type' => 'string', 'description' => 'Patient last name'],
                    'patient_dob' => ['type' => 'string', 'description' => 'Date of birth'],
                    'effective_date' => ['type' => 'string', 'description' => 'Policy effective date'],
                    'copay_amount' => ['type' => 'string', 'description' => 'Copay amount'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Confidence in extraction (0-1)']
                ];

            case 'clinical_note':
                return [
                    'primary_diagnosis' => ['type' => 'string', 'description' => 'ICD-10 code if available'],
                    'diagnosis_description' => ['type' => 'string', 'description' => 'Detailed diagnosis description'],
                    'wound_location' => ['type' => 'string', 'description' => 'Anatomical location of wound'],
                    'wound_size' => ['type' => 'string', 'description' => 'Measurements in cm'],
                    'wound_type' => ['type' => 'string', 'description' => 'Pressure ulcer, diabetic foot ulcer, etc.'],
                    'duration_weeks' => ['type' => 'string', 'description' => 'How long the wound has existed'],
                    'previous_treatments' => ['type' => 'string', 'description' => 'Treatments tried'],
                    'provider_name' => ['type' => 'string', 'description' => 'Physician name'],
                    'provider_npi' => ['type' => 'string', 'description' => 'NPI number if available'],
                    'date_of_service' => ['type' => 'string', 'description' => 'Date of service'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Confidence in extraction (0-1)']
                ];

            case 'wound_photo':
                return [
                    'length' => ['type' => 'string', 'description' => 'Length in cm'],
                    'width' => ['type' => 'string', 'description' => 'Width in cm'],
                    'depth' => ['type' => 'string', 'description' => 'Depth in cm if visible'],
                    'wound_type' => ['type' => 'string', 'description' => 'Pressure ulcer, diabetic foot ulcer, venous ulcer, etc.'],
                    'stage' => ['type' => 'string', 'description' => 'If pressure ulcer: Stage 1-4'],
                    'wound_bed_color' => ['type' => 'string', 'description' => 'Red, yellow, black, mixed'],
                    'drainage_amount' => ['type' => 'string', 'description' => 'None, minimal, moderate, heavy'],
                    'surrounding_skin_condition' => ['type' => 'string', 'description' => 'Description of surrounding skin'],
                    'measurement_tool_visible' => ['type' => 'boolean', 'description' => 'Whether ruler or measuring device is visible'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Confidence in extraction (0-1)']
                ];

            case 'referral':
                return [
                    'referring_provider' => ['type' => 'string', 'description' => 'Name of referring provider'],
                    'receiving_provider' => ['type' => 'string', 'description' => 'Name of receiving provider'],
                    'patient_name' => ['type' => 'string', 'description' => 'Patient full name'],
                    'patient_dob' => ['type' => 'string', 'description' => 'Patient date of birth'],
                    'diagnosis' => ['type' => 'string', 'description' => 'Primary diagnosis'],
                    'reason_for_referral' => ['type' => 'string', 'description' => 'Reason for referral'],
                    'urgency_level' => ['type' => 'string', 'description' => 'Urgency level'],
                    'requested_services' => ['type' => 'string', 'description' => 'Requested services'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Confidence in extraction (0-1)']
                ];

            default:
                return [
                    'patient_name' => ['type' => 'string', 'description' => 'Patient name if present'],
                    'provider_name' => ['type' => 'string', 'description' => 'Provider name if present'],
                    'date' => ['type' => 'string', 'description' => 'Any date found'],
                    'phone' => ['type' => 'string', 'description' => 'Phone number if present'],
                    'member_id' => ['type' => 'string', 'description' => 'Member or ID number if present'],
                    'confidence_score' => ['type' => 'number', 'description' => 'Confidence in extraction (0-1)']
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