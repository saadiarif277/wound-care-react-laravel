<?php

namespace App\Services\Templates;

use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Product;
use App\Services\DocusealService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class DocusealBuilder
{
    private ?DocusealService $DocusealService = null;

    public function __construct(?DocusealService $DocusealService = null)
    {
        $this->DocusealService = $DocusealService;
    }
    /**
     * Get the Docuseal template for a given manufacturer.
     * Templates are organized by manufacturer folders in Docuseal.
     *
     * @param string $manufacturerId
     * @param string|null $productCode (not used, kept for compatibility)
     * @return \App\Models\Docuseal\DocusealTemplate
     * @throws Exception
     */
    public function getTemplate(string $manufacturerId, ?string $productCode = null): \App\Models\Docuseal\DocusealTemplate
    {
        // First try to find a manufacturer-specific IVR template
        $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('is_active', true)
            ->where('document_type', 'IVR')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($template) {
            return $template;
        }

        // If no manufacturer-specific template, try to find a generic IVR template
        $genericTemplate = DocusealTemplate::whereNull('manufacturer_id')
            ->where('is_active', true)
            ->where('document_type', 'IVR')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($genericTemplate) {
            return $genericTemplate;
        }

        throw new Exception("No active IVR template found for manufacturer ID: {$manufacturerId}");
    }

    /**
     * Generate Docuseal builder token for a given manufacturer and product code.
     *
     * @param string $manufacturerId
     * @param string|null $productCode
     * @return array [templateId, builderToken, builderUrl]
     * @throws Exception
     */
    public function generateBuilderToken(string $manufacturerId, ?string $productCode = null): array
    {
        // Get the template first
        $template = $this->getTemplate($manufacturerId, $productCode);

        // Prepare submitter data
        $user = Auth::user();
        $submitterData = [
            'email' => $user->email,
            'name' => $user->name,
            'external_id' => "episode_" . uniqid(),
            'fields' => [] // No pre-filled fields for builder mode
        ];

        // Generate the builder token via DocusealService
        $builderToken = $this->DocusealService->generateBuilderToken(
            $template->docuseal_template_id,
            $submitterData
        );

        // Docuseal builder URL is typically the embed URL
        $builderUrl = config('docuseal.api_url', 'https://api.docuseal.com') . '/builder';

        return [$template->docuseal_template_id, $builderToken, $builderUrl];
    }

    /**
     * Generate pre-filled submission with FHIR data
     */
    public function createPreFilledSubmission(string $manufacturerId, array $fhirData, ?array $recipientData = null): array
    {
        $template = $this->getTemplate($manufacturerId);

        // Map FHIR data to Docuseal fields using the mapping engine with fuzzy matching
        $mappingEngine = app(\App\Services\Templates\UnifiedTemplateMappingEngine::class);
        
        // Extract additional data from current context
        $additionalData = [
            'user_email' => Auth::user()->email ?? '',
            'user_name' => Auth::user()->name ?? '',
            'submission_date' => now()->format('Y-m-d'),
            'facility_data' => Auth::user()->primaryFacility ? Auth::user()->primaryFacility->toArray() : [],
            'organization_data' => Auth::user()->currentOrganization ? Auth::user()->currentOrganization->toArray() : [],
        ];
        
        // Use fuzzy matching if manufacturer ID is numeric
        if (is_numeric($manufacturerId)) {
            $mappedFields = $mappingEngine->mapWithFuzzyMatching(
                $fhirData, 
                $additionalData, 
                (int)$manufacturerId, 
                'insurance-verification'
            );
        } else {
            // Fall back to standard mapping for non-numeric IDs
            $mappedFields = $mappingEngine->mapInsuranceData(
                array_merge($fhirData, $additionalData), 
                'docuseal_ivr'
            );
        }

        // Prepare submission data
        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false, // Manual sending for approval workflow
            'submitters' => [
                [
                    'role' => 'provider',
                    'email' => $recipientData['email'] ?? Auth::user()->email,
                    'name' => $recipientData['name'] ?? Auth::user()->name,
                    'fields' => $this->formatFieldsForDocuseal($mappedFields, $template)
                ]
            ]
        ];

        return $this->DocusealService->createSubmission($submissionData);
    }

    /**
     * Format mapped fields for Docuseal submission
     */
    private function formatFieldsForDocuseal(array $mappedFields, $template): array
    {
        $DocusealFields = [];

        // Get template field mappings
        $fieldMappings = $template->field_mappings ?? [];

        foreach ($mappedFields as $fieldName => $value) {
            // Convert nested field names (e.g., 'patientInfo.patientName') to Docuseal format
            $DocusealFieldName = $this->convertToDocusealFieldName($fieldName, $fieldMappings);

            if ($DocusealFieldName && $value !== null) {
                $DocusealFields[] = [
                    'name' => $DocusealFieldName,
                    'value' => $value,
                    'readonly' => true // Pre-filled fields are typically readonly
                ];
            }
        }

        return $DocusealFields;
    }

    /**
     * Convert field name to Docuseal format
     */
    private function convertToDocusealFieldName(string $fieldName, array $fieldMappings): ?string
    {
        // Handle new simplified format where CSV field names are keys
        // and our internal field paths are values
        foreach ($fieldMappings as $csvFieldName => $ourFieldPath) {
            // Check if this is the new format (string values) or old format (array values)
            if (is_string($ourFieldPath) && $ourFieldPath === $fieldName) {
                return $csvFieldName;
            }
        }

        // Handle legacy format where field mappings contain metadata
        if (isset($fieldMappings[$fieldName])) {
            if (is_array($fieldMappings[$fieldName])) {
                return $fieldMappings[$fieldName]['docuseal_field'] ?? 
                       $fieldMappings[$fieldName]['docuseal_field_name'] ?? null;
            }
            return $fieldMappings[$fieldName];
        }

        // Pattern-based mapping for nested fields (using exact CSV field names)
        $patterns = [
            'patientInfo.patientName' => 'Patient Name',
            'patientInfo.dateOfBirth' => 'DOB',
            'patientInfo.gender' => 'Gender',
            'patientInfo.address' => 'Address',
            'patientInfo.city' => 'City',
            'patientInfo.state' => 'State',
            'patientInfo.zip' => 'Zip',
            'patientInfo.homePhone' => 'Home Phone',
            'patientInfo.mobile' => 'Mobile',
            'insuranceInfo.primaryInsurance.primaryInsuranceName' => 'Primary Insurance',
            'insuranceInfo.primaryInsurance.primaryPolicyNumber' => 'Policy Number',
            'insuranceInfo.primaryInsurance.primaryPayerPhone' => 'Payer Phone',
            'insuranceInfo.primaryInsurance.primarySubscriberName' => 'Suscriber Name',
            'providerInfo.providerName' => 'Provider Name',
            'providerInfo.npi' => 'NPI',
            'providerInfo.ptan' => 'PTAN',
            'providerInfo.taxId' => 'Tax ID',
            'providerInfo.specialty' => 'Specialty',
            'facilityInfo.facilityName' => 'Facility Name',
            'facilityInfo.facilityAddress' => 'Facility Address',
            'facilityInfo.facilityCity' => 'Facility City',
            'facilityInfo.facilityState' => 'Facility State',
            'facilityInfo.facilityZip' => 'Facility Zip',
            'facilityInfo.facilityNpi' => 'Facility NPI',
            'woundInfo.woundType' => 'Known Conditions',
            'woundInfo.woundLocation' => 'Primary',
            'woundInfo.woundSize' => 'Wound Size',
        ];

        return $patterns[$fieldName] ?? null;
    }

    /**
     * Handle completed Docuseal submission
     */
    public function handleCompletedSubmission(string $submissionId): array
    {
        try {
            // Get submission status and data
            $submission = $this->DocusealService->getSubmissionStatus($submissionId);

            if ($submission['status'] !== 'completed') {
                throw new Exception("Submission {$submissionId} is not completed");
            }

            // Extract completed field data
            $completedData = $this->extractCompletedFieldData($submission);

            // Update episode with completed data
            $this->updateEpisodeWithCompletedData($submissionId, $completedData);

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'completed_data' => $completedData,
                'pdf_url' => $submission['pdf_url'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle completed Docuseal submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Extract field data from completed submission
     */
    private function extractCompletedFieldData(array $submission): array
    {
        $completedFields = [];

        if (isset($submission['submitters'])) {
            foreach ($submission['submitters'] as $submitter) {
                if (isset($submitter['fields'])) {
                    foreach ($submitter['fields'] as $field) {
                        $completedFields[$field['name']] = $field['value'] ?? null;
                    }
                }
            }
        }

        return $completedFields;
    }

    /**
     * Update episode with completed submission data
     */
    private function updateEpisodeWithCompletedData(string $submissionId, array $completedData): void
    {
        // Find episode by submission ID
        $episode = \App\Models\Episode::where('docuseal_submission_id', $submissionId)->first();

        if ($episode) {
            $episode->update([
                'ivr_status' => 'completed',
                'completed_ivr_data' => $completedData,
                'ivr_completed_at' => now(),
                'metadata' => array_merge($episode->metadata ?? [], [
                    'docuseal_completed_at' => now()->toIso8601String(),
                    'field_count' => count($completedData)
                ])
            ]);
        }
    }
}
