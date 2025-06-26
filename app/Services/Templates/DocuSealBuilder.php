<?php

namespace App\Services\Templates;

use App\Models\DocuSeal\DocuSealTemplate;
use App\Models\Order\Product;
use App\Services\DocuSealService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class DocuSealBuilder
{
    private ?DocuSealService $docuSealService = null;

    public function __construct(?DocuSealService $docuSealService = null)
    {
        $this->docuSealService = $docuSealService;
    }
    /**
     * Get the DocuSeal template for a given manufacturer.
     * Templates are organized by manufacturer folders in DocuSeal.
     *
     * @param string $manufacturerId
     * @param string|null $productCode (not used, kept for compatibility)
     * @return \App\Models\DocuSeal\DocuSealTemplate
     * @throws Exception
     */
    public function getTemplate(string $manufacturerId, ?string $productCode = null): \App\Models\DocuSeal\DocuSealTemplate
    {
        // First try to find a manufacturer-specific IVR template
        $template = DocuSealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('is_active', true)
            ->where('document_type', 'IVR')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($template) {
            return $template;
        }

        // If no manufacturer-specific template, try to find a generic IVR template
        $genericTemplate = DocuSealTemplate::whereNull('manufacturer_id')
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
     * Generate DocuSeal builder token for a given manufacturer and product code.
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

        // Generate the builder token via DocuSealService
        $builderToken = $this->docuSealService->generateBuilderToken(
            $template->docuseal_template_id,
            $submitterData
        );

        // DocuSeal builder URL is typically the embed URL
        $builderUrl = config('docuseal.api_url', 'https://api.docuseal.com') . '/builder';

        return [$template->docuseal_template_id, $builderToken, $builderUrl];
    }

    /**
     * Generate pre-filled submission with FHIR data
     */
    public function createPreFilledSubmission(string $manufacturerId, array $fhirData, ?array $recipientData = null): array
    {
        $template = $this->getTemplate($manufacturerId);

        // Map FHIR data to DocuSeal fields using the mapping engine
        $mappingEngine = app(\App\Services\Templates\UnifiedTemplateMappingEngine::class);
        $mappedFields = $mappingEngine->mapInsuranceData($fhirData, 'docuseal_ivr');

        // Prepare submission data
        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false, // Manual sending for approval workflow
            'submitters' => [
                [
                    'role' => 'provider',
                    'email' => $recipientData['email'] ?? Auth::user()->email,
                    'name' => $recipientData['name'] ?? Auth::user()->name,
                    'fields' => $this->formatFieldsForDocuSeal($mappedFields, $template)
                ]
            ]
        ];

        return $this->docuSealService->createSubmission($submissionData);
    }

    /**
     * Format mapped fields for DocuSeal submission
     */
    private function formatFieldsForDocuSeal(array $mappedFields, $template): array
    {
        $docuSealFields = [];

        // Get template field mappings
        $fieldMappings = $template->field_mappings ?? [];

        foreach ($mappedFields as $fieldName => $value) {
            // Convert nested field names (e.g., 'patientInfo.patientName') to DocuSeal format
            $docuSealFieldName = $this->convertToDocuSealFieldName($fieldName, $fieldMappings);

            if ($docuSealFieldName && $value !== null) {
                $docuSealFields[] = [
                    'name' => $docuSealFieldName,
                    'value' => $value,
                    'readonly' => true // Pre-filled fields are typically readonly
                ];
            }
        }

        return $docuSealFields;
    }

    /**
     * Convert field name to DocuSeal format
     */
    private function convertToDocuSealFieldName(string $fieldName, array $fieldMappings): ?string
    {
        // Direct mapping
        if (isset($fieldMappings[$fieldName])) {
            return $fieldMappings[$fieldName]['docuseal_field'] ?? null;
        }

        // Pattern-based mapping for nested fields
        $patterns = [
            'patientInfo.patientName' => 'patient_name',
            'patientInfo.dateOfBirth' => 'patient_dob',
            'insuranceInfo.primaryInsurance.primaryInsuranceName' => 'primary_insurance',
            'insuranceInfo.primaryInsurance.primaryMemberId' => 'member_id',
            'providerInfo.providerName' => 'provider_name',
            'providerInfo.providerNPI' => 'provider_npi',
            'facilityInfo.facilityName' => 'facility_name'
        ];

        return $patterns[$fieldName] ?? null;
    }

    /**
     * Handle completed DocuSeal submission
     */
    public function handleCompletedSubmission(string $submissionId): array
    {
        try {
            // Get submission status and data
            $submission = $this->docuSealService->getSubmissionStatus($submissionId);

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
            Log::error('Failed to handle completed DocuSeal submission', [
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
