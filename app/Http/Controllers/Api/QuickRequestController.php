<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Product;
use App\Services\FhirService;
use App\Services\DocuSealService;
use App\Services\Templates\DocuSealBuilder;
use Illuminate\Support\Str;

class QuickRequestController extends Controller
{
    private FhirService $fhirService;
    private DocuSealService $docuSealService;

    public function __construct(FhirService $fhirService, DocuSealService $docuSealService)
    {
        $this->fhirService = $fhirService;
        $this->docuSealService = $docuSealService;
    }

    /**
     * Create episode for QuickRequest workflow
     */
    public function createEpisode(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'patient_id' => 'required|string',
                'patient_fhir_id' => 'required|string',
                'patient_display_id' => 'required|string',
                'manufacturer_id' => 'nullable|exists:manufacturers,id',
                'selected_product_id' => 'nullable|exists:msc_products,id',
                'form_data' => 'required|array',
            ]);

            // Log the incoming data for debugging
            Log::info('QuickRequest createEpisode - incoming data', [
                'manufacturer_id' => $data['manufacturer_id'],
                'selected_product_id' => $data['selected_product_id'],
                'user_id' => Auth::id()
            ]);

            // Determine manufacturer from product if not provided
            if (!$data['manufacturer_id'] && $data['selected_product_id']) {
                $product = Product::find($data['selected_product_id']);

                Log::info('QuickRequest createEpisode - product lookup', [
                    'selected_product_id' => $data['selected_product_id'],
                    'product_found' => $product ? true : false,
                    'product_manufacturer_id' => $product?->manufacturer_id,
                    'product_name' => $product?->name
                ]);

                if ($product && $product->manufacturer_id) {
                    $data['manufacturer_id'] = $product->manufacturer_id;
                    Log::info('QuickRequest createEpisode - manufacturer determined from product', [
                        'manufacturer_id' => $data['manufacturer_id']
                    ]);
                } else {
                    Log::warning('QuickRequest createEpisode - product found but no manufacturer_id', [
                        'product_id' => $data['selected_product_id'],
                        'product' => $product ? $product->toArray() : null
                    ]);
                }
            }

            if (!$data['manufacturer_id']) {
                Log::error('QuickRequest createEpisode - no manufacturer_id determined', [
                    'request_data' => $request->all(),
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine manufacturer. Please ensure a product is selected.',
                    'debug_info' => [
                        'selected_product_id' => $data['selected_product_id'],
                        'manufacturer_id_provided' => $request->input('manufacturer_id'),
                        'product_has_manufacturer' => $data['selected_product_id'] ? (Product::find($data['selected_product_id'])?->manufacturer_id ? true : false) : false
                    ]
                ], 422);
            }

            // Use patient_fhir_id as patient_id since the frontend sends non-UUID strings
            $patientId = $data['patient_fhir_id'];

            // Find or create episode
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_fhir_id' => $data['patient_fhir_id'],
                'manufacturer_id' => $data['manufacturer_id'],
            ], [
                'patient_id' => $patientId,
                'patient_display_id' => $data['patient_display_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => [
                    'facility_id' => $data['form_data']['facility_id'] ?? null,
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request',
                    'form_data' => $data['form_data']
                ]
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $data['manufacturer_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for QuickRequest', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create episode with documents (enhanced version)
     */
    public function createEpisodeWithDocuments(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'patient_fhir_id' => 'required|string',
                'patient_display_id' => 'required|string',
                'manufacturer_id' => 'required|exists:manufacturers,id',
                'form_data' => 'required|array',
                'clinical_data' => 'sometimes|array',
                'insurance_data' => 'sometimes|array',
            ]);

            DB::beginTransaction();

            // Create the episode
            $episode = PatientManufacturerIVREpisode::create([
                'patient_id' => $data['patient_fhir_id'], // Using FHIR ID as patient_id
                'patient_fhir_id' => $data['patient_fhir_id'],
                'patient_display_id' => $data['patient_display_id'],
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => [
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request_enhanced',
                    'form_data' => $data['form_data'],
                    'clinical_data' => $data['clinical_data'] ?? [],
                    'insurance_data' => $data['insurance_data'] ?? [],
                    'created_at' => now()->toISOString()
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $data['manufacturer_id'],
                'patient_display_id' => $data['patient_display_id']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create episode with documents', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract IVR fields from form data and FHIR resources
     */
    public function extractIvrFields(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'patient_id' => 'sometimes|string',
                'practitioner_id' => 'sometimes|string',
                'organization_id' => 'sometimes|string',
                'questionnaire_response_id' => 'sometimes|string',
                'device_request_id' => 'sometimes|string',
                'episode_id' => 'sometimes|string',
                'episode_of_care_id' => 'sometimes|string',
                'manufacturer_key' => 'required|string',
                'sales_rep' => 'sometimes|array',
                'selected_products' => 'sometimes|array',
            ]);

            $extractedFields = [];
            $fieldCoverage = ['total' => 0, 'filled' => 0, 'percentage' => 0];

            // Try to extract from FHIR resources if available and configured
            if ($this->fhirService->isAzureConfigured()) {
                try {
                    // Extract from Patient resource
                    if (!empty($data['patient_id'])) {
                        $patient = $this->fhirService->getPatientById($data['patient_id']);
                        if ($patient) {
                            $extractedFields = array_merge($extractedFields, $this->extractPatientFields($patient));
                        }
                    }

                    // Extract from QuestionnaireResponse
                    if (!empty($data['questionnaire_response_id'])) {
                        $questionnaireResponse = $this->fhirService->search('QuestionnaireResponse', [
                            '_id' => $data['questionnaire_response_id']
                        ]);
                        if (!empty($questionnaireResponse['entry'])) {
                            $qr = $questionnaireResponse['entry'][0]['resource'];
                            $extractedFields = array_merge($extractedFields, $this->extractQuestionnaireFields($qr));
                        }
                    }

                    // Extract from DeviceRequest
                    if (!empty($data['device_request_id'])) {
                        $deviceRequest = $this->fhirService->search('DeviceRequest', [
                            '_id' => $data['device_request_id']
                        ]);
                        if (!empty($deviceRequest['entry'])) {
                            $dr = $deviceRequest['entry'][0]['resource'];
                            $extractedFields = array_merge($extractedFields, $this->extractDeviceRequestFields($dr));
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('FHIR extraction failed, using fallback', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add sales rep information if provided
            if (!empty($data['sales_rep'])) {
                $extractedFields['sales_rep_name'] = $data['sales_rep']['name'] ?? '';
                $extractedFields['sales_rep_email'] = $data['sales_rep']['email'] ?? '';
            }

            // Add product information if provided
            if (!empty($data['selected_products'])) {
                $firstProduct = $data['selected_products'][0] ?? [];
                $extractedFields['product_name'] = $firstProduct['name'] ?? '';
                $extractedFields['product_code'] = $firstProduct['code'] ?? '';
                $extractedFields['product_size'] = $firstProduct['size'] ?? '';
            }

            // Calculate field coverage
            $totalFields = 50; // Approximate number of typical IVR fields
            $filledFields = count(array_filter($extractedFields, function($value) {
                return !empty($value);
            }));

            $fieldCoverage = [
                'total' => $totalFields,
                'filled' => $filledFields,
                'percentage' => $totalFields > 0 ? round(($filledFields / $totalFields) * 100, 1) : 0
            ];

            return response()->json([
                'success' => true,
                'ivr_fields' => $extractedFields,
                'field_coverage' => $fieldCoverage,
                'fhir_available' => $this->fhirService->isAzureConfigured()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract IVR fields', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to extract IVR fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract patient fields from FHIR Patient resource
     */
    private function extractPatientFields(array $patient): array
    {
        $fields = [];

        // Extract name
        if (!empty($patient['name'][0])) {
            $name = $patient['name'][0];
            $fields['patient_first_name'] = implode(' ', $name['given'] ?? []);
            $fields['patient_last_name'] = $name['family'] ?? '';
        }

        // Extract gender
        if (!empty($patient['gender'])) {
            $fields['patient_gender'] = $patient['gender'];
        }

        // Extract birth date
        if (!empty($patient['birthDate'])) {
            $fields['patient_dob'] = $patient['birthDate'];
        }

        // Extract contact information
        if (!empty($patient['telecom'])) {
            foreach ($patient['telecom'] as $contact) {
                if ($contact['system'] === 'phone') {
                    $fields['patient_phone'] = $contact['value'] ?? '';
                } elseif ($contact['system'] === 'email') {
                    $fields['patient_email'] = $contact['value'] ?? '';
                }
            }
        }

        // Extract address
        if (!empty($patient['address'][0])) {
            $address = $patient['address'][0];
            $fields['patient_address_line1'] = $address['line'][0] ?? '';
            $fields['patient_address_line2'] = $address['line'][1] ?? '';
            $fields['patient_city'] = $address['city'] ?? '';
            $fields['patient_state'] = $address['state'] ?? '';
            $fields['patient_zip'] = $address['postalCode'] ?? '';
        }

        return $fields;
    }

    /**
     * Extract fields from QuestionnaireResponse
     */
    private function extractQuestionnaireFields(array $questionnaireResponse): array
    {
        $fields = [];

        if (!empty($questionnaireResponse['item'])) {
            foreach ($questionnaireResponse['item'] as $item) {
                $linkId = $item['linkId'] ?? '';
                $answer = $item['answer'][0] ?? null;

                if ($answer) {
                    switch ($linkId) {
                        case 'wound-type':
                            $fields['wound_type'] = $answer['valueCoding']['display'] ?? $answer['valueString'] ?? '';
                            break;
                        case 'wound-location':
                            $fields['wound_location'] = $answer['valueString'] ?? '';
                            break;
                        case 'wound-size-length':
                            $fields['wound_size_length'] = (string)($answer['valueDecimal'] ?? '');
                            break;
                        case 'wound-size-width':
                            $fields['wound_size_width'] = (string)($answer['valueDecimal'] ?? '');
                            break;
                        case 'place-of-service':
                            $fields['place_of_service'] = $answer['valueCoding']['code'] ?? '';
                            break;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Extract fields from DeviceRequest
     */
    private function extractDeviceRequestFields(array $deviceRequest): array
    {
        $fields = [];

        // Extract product information
        if (!empty($deviceRequest['code']['coding'][0])) {
            $coding = $deviceRequest['code']['coding'][0];
            $fields['product_code'] = $coding['code'] ?? '';
            $fields['product_name'] = $coding['display'] ?? '';
        }

        // Extract parameters
        if (!empty($deviceRequest['parameter'])) {
            foreach ($deviceRequest['parameter'] as $param) {
                $code = $param['code']['text'] ?? '';
                if ($code === 'Quantity' && !empty($param['valueQuantity'])) {
                    $fields['quantity'] = (string)($param['valueQuantity']['value'] ?? '');
                } elseif ($code === 'Size' && !empty($param['valueCodeableConcept'])) {
                    $fields['size'] = $param['valueCodeableConcept']['text'] ?? '';
                }
            }
        }

        // Extract service date
        if (!empty($deviceRequest['occurrenceDateTime'])) {
            $fields['expected_service_date'] = $deviceRequest['occurrenceDateTime'];
        }

        return $fields;
    }
}
