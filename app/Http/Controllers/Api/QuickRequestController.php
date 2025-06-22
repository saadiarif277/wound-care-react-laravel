<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FhirService;
use App\Services\FhirToIvrFieldExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickRequestController extends Controller
{
    protected $fhirService;
    protected $fhirExtractor;

    public function __construct(FhirService $fhirService, FhirToIvrFieldExtractor $fhirExtractor)
    {
        $this->fhirService = $fhirService;
        $this->fhirExtractor = $fhirExtractor;
    }

    /**
     * Extract IVR fields from FHIR resources
     */
    public function extractIvrFields(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'practitioner_id' => 'nullable|string',
            'organization_id' => 'nullable|string',
            'questionnaire_response_id' => 'nullable|string',
            'device_request_id' => 'nullable|string',
            'episode_id' => 'nullable|string',
            'episode_of_care_id' => 'nullable|string',
            'manufacturer_key' => 'required|string',
            'sales_rep' => 'nullable|array',
            'sales_rep.name' => 'nullable|string',
            'sales_rep.email' => 'nullable|email',
            'selected_products' => 'nullable|array',
            'selected_products.*.name' => 'nullable|string',
            'selected_products.*.code' => 'nullable|string',
            'selected_products.*.size' => 'nullable|string',
        ]);

        try {
            // Build context for extractor
            $context = [
                'patient_id' => $validated['patient_id'],
                'practitioner_id' => $validated['practitioner_id'] ?? null,
                'organization_id' => $validated['organization_id'] ?? null,
                'questionnaire_response_id' => $validated['questionnaire_response_id'] ?? null,
                'device_request_id' => $validated['device_request_id'] ?? null,
                'episode_id' => $validated['episode_id'] ?? null,
                'episode_of_care_id' => $validated['episode_of_care_id'] ?? null,
                'sales_rep' => $validated['sales_rep'] ?? null,
                'selected_products' => $validated['selected_products'] ?? [],
            ];

            // Extract IVR fields for the specified manufacturer
            $ivrFields = $this->fhirExtractor->extractForManufacturer($context, $validated['manufacturer_key']);

            // Calculate field coverage
            $totalFields = count($ivrFields);
            $filledFields = count(array_filter($ivrFields, fn($value) => !empty($value)));
            $coveragePercentage = $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;

            return response()->json([
                'success' => true,
                'ivr_fields' => $ivrFields,
                'field_coverage' => [
                    'total_fields' => $totalFields,
                    'filled_fields' => $filledFields,
                    'percentage' => $coveragePercentage,
                    'missing_fields' => array_keys(array_filter($ivrFields, fn($value) => empty($value))),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract IVR fields', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => $context ?? [],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to extract IVR fields',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create an episode for the quick request
     */
    public function createEpisode(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'patient_fhir_id' => 'required|string',
            'patient_display_id' => 'required|string',
            'manufacturer_id' => 'nullable|integer',
            'selected_product_id' => 'nullable|integer',
            'form_data' => 'nullable|array',
        ]);

        try {
            // Create episode in your local database
            // This is a placeholder - implement according to your episode model
            $episode = \App\Models\PatientManufacturerIVREpisode::create([
                'patient_id' => $validated['patient_id'],
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'patient_display_id' => $validated['patient_display_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
                'status' => 'draft',
                'metadata' => $validated['form_data'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create episode with document processing
     */
    public function createEpisodeWithDocuments(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|integer',
            'facility_id' => 'required|integer',
            'patient_name' => 'required|string',
            'request_type' => 'nullable|string',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        try {
            // Create temporary patient ID
            $tempPatientId = 'TEMP_' . uniqid();

            // Process documents if provided
            $extractedData = [];
            $fieldCoverage = null;

            if ($request->hasFile('documents')) {
                // TODO: Implement document processing with Azure Document Intelligence
                // For now, return placeholder data
                $extractedData = [
                    'patient_first_name' => 'John',
                    'patient_last_name' => 'Doe',
                    'patient_dob' => '1970-01-01',
                    'patient_gender' => 'male',
                    'insurance_card_auto_filled' => true,
                ];

                $fieldCoverage = [
                    'total_fields' => 50,
                    'filled_fields' => 15,
                    'percentage' => 30,
                ];
            }

            // Create episode
            $episode = \App\Models\PatientManufacturerIVREpisode::create([
                'patient_id' => $tempPatientId,
                'patient_fhir_id' => $tempPatientId,
                'patient_display_id' => explode(' ', $validated['patient_name'])[0] ?? 'PATIENT',
                'status' => 'draft',
                'metadata' => array_merge($validated, ['extracted_data' => $extractedData]),
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'patient_fhir_id' => $tempPatientId,
                'extracted_data' => $extractedData,
                'field_coverage' => $fieldCoverage,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode with documents', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode with documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
