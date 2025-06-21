<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IvrFieldMappingService;
use App\Models\Manufacturer;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\Users\Organization\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IvrFieldController extends Controller
{
    protected $ivrFieldMappingService;

    public function __construct(IvrFieldMappingService $ivrFieldMappingService)
    {
        $this->ivrFieldMappingService = $ivrFieldMappingService;
    }

    /**
     * Get manufacturer field configuration
     */
    public function getManufacturerFields($manufacturerKey)
    {
        try {
            $manufacturerConfig = $this->ivrFieldMappingService->getManufacturerConfig($manufacturerKey);

            if (!$manufacturerConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manufacturer configuration not found',
                    'fields' => []
                ], 404);
            }

            // Get field types for this manufacturer
            $fieldTypes = $this->ivrFieldMappingService->getFieldTypes($manufacturerKey);

            // Format fields for frontend consumption
            $fields = [];
            foreach ($manufacturerConfig['field_mappings'] as $ivrFieldName => $systemField) {
                $fieldType = $fieldTypes[$systemField] ?? 'text';
                $fields[] = [
                    'name' => $systemField,
                    'label' => $this->humanizeFieldName($ivrFieldName),
                    'type' => $fieldType,
                    'required' => $this->isFieldRequired($systemField, $manufacturerKey),
                    'ivr_field_name' => $ivrFieldName
                ];
            }

            return response()->json([
                'success' => true,
                'manufacturer' => $manufacturerConfig['name'],
                'template_id' => $manufacturerConfig['template_id'],
                'folder_id' => $manufacturerConfig['folder_id'],
                'fields' => $fields,
                'total_fields' => count($fields)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching manufacturer fields', [
                'manufacturer' => $manufacturerKey,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching manufacturer fields',
                'fields' => []
            ], 500);
        }
    }

    /**
     * Calculate field coverage for a manufacturer
     */
    public function calculateFieldCoverage(Request $request)
    {
        try {
            $validated = $request->validate([
                'manufacturer_key' => 'required|string',
                'form_data' => 'required|array',
                'patient_data' => 'array'
            ]);

            $manufacturerKey = $validated['manufacturer_key'];
            $formData = $validated['form_data'];
            $patientData = $validated['patient_data'] ?? [];

            // Get manufacturer configuration
            $manufacturerConfig = $this->ivrFieldMappingService->getManufacturerConfig($manufacturerKey);

            if (!$manufacturerConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manufacturer configuration not found'
                ], 404);
            }

            // Map the form data to IVR fields
            $productRequest = $this->createMockProductRequest($formData);
            $mappedFields = $this->ivrFieldMappingService->mapProductRequestToIvrFields(
                $productRequest,
                $manufacturerKey,
                $patientData
            );

            // Calculate coverage
            $totalFields = count($manufacturerConfig['field_mappings']);
            $filledFields = collect($mappedFields)->filter(function ($value) {
                return !empty($value) && $value !== 'N/A';
            })->count();

            $missingFields = [];
            $extractedFields = [];

            foreach ($manufacturerConfig['field_mappings'] as $ivrFieldName => $systemField) {
                if (empty($mappedFields[$ivrFieldName])) {
                    $missingFields[] = $this->humanizeFieldName($ivrFieldName);
                } else {
                    $extractedFields[] = $this->humanizeFieldName($ivrFieldName);
                }
            }

            $percentage = $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;

            return response()->json([
                'success' => true,
                'coverage' => [
                    'total_fields' => $totalFields,
                    'filled_fields' => $filledFields,
                    'missing_fields' => $missingFields,
                    'extracted_fields' => $extractedFields,
                    'percentage' => $percentage,
                    'coverage_level' => $this->getCoverageLevel($percentage)
                ],
                'mapped_fields' => $mappedFields
            ]);

        } catch (\Exception $e) {
            Log::error('Error calculating field coverage', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error calculating field coverage'
            ], 500);
        }
    }

    /**
     * Get all available manufacturers
     */
    public function getManufacturers()
    {
        try {
            $manufacturers = $this->ivrFieldMappingService->getAvailableManufacturers();

            return response()->json([
                'success' => true,
                'manufacturers' => $manufacturers
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching manufacturers', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching manufacturers',
                'manufacturers' => []
            ], 500);
        }
    }

    /**
     * Create a mock ProductRequest for field mapping
     */
    private function createMockProductRequest($formData)
    {
        // Create a proper ProductRequest instance
        $productRequest = new ProductRequest();
        
        // Fill with form data
        $productRequest->fill([
            'provider_id' => $formData['provider_id'] ?? null,
            'facility_id' => $formData['facility_id'] ?? null,
            'patient_fhir_id' => $formData['patient_fhir_id'] ?? null,
            'expected_service_date' => $formData['expected_service_date'] ?? null,
            'wound_type' => $formData['wound_type'] ?? null,
            'payer_name_submitted' => $formData['payer_name_submitted'] ?? null,
        ]);
        
        // Create mock relationships
        $productRequest->setRelation('provider', new \App\Models\User([
            'id' => $formData['provider_id'] ?? null,
            'first_name' => $formData['provider_first_name'] ?? '',
            'last_name' => $formData['provider_last_name'] ?? '',
            'npi_number' => $formData['provider_npi'] ?? ''
        ]));
        
        $productRequest->setRelation('facility', new \App\Models\Fhir\Facility([
            'id' => $formData['facility_id'] ?? null,
            'name' => $formData['facility_name'] ?? '',
            'address' => $formData['facility_address'] ?? '',
            'city' => $formData['facility_city'] ?? '',
            'state' => $formData['facility_state'] ?? '',
            'zip_code' => $formData['facility_zip'] ?? '',
            'npi' => $formData['facility_npi'] ?? '',
            'tax_id' => $formData['facility_tax_id'] ?? '',
            'ptan' => $formData['facility_ptan'] ?? ''
        ]));
        
        // Add organization with sales rep
        $productRequest->setRelation('organization', new Organization([
            'id' => $formData['organization_id'] ?? null,
            'notification_emails' => $formData['notification_emails'] ?? ''
        ]));
        
        // Add products collection
        $products = [];
        if (isset($formData['selected_products']) && is_array($formData['selected_products'])) {
            foreach ($formData['selected_products'] as $productData) {
                $product = new \App\Models\Order\Product([
                    'name' => $productData['name'] ?? '',
                    'code' => $productData['code'] ?? '',
                    'q_code' => $productData['q_code'] ?? '',
                    'manufacturer' => $productData['manufacturer'] ?? ''
                ]);
                $product->pivot = new \stdClass();
                $product->pivot->size = $productData['size'] ?? '';
                $product->pivot->quantity = $productData['quantity'] ?? 1;
                $products[] = $product;
            }
        }
        $productRequest->setRelation('products', collect($products));
        
        // Add episode with metadata
        $productRequest->setRelation('episode', new \App\Models\PatientManufacturerIVREpisode([
            'metadata' => [
                'extracted_data' => $formData['extracted_data'] ?? []
            ]
        ]));
        
        return $productRequest;
    }

    /**
     * Humanize field name for display
     */
    private function humanizeFieldName($fieldName)
    {
        return ucwords(str_replace(['_', '-'], ' ', $fieldName));
    }

    /**
     * Check if a field is required for a manufacturer
     */
    private function isFieldRequired($fieldName, $manufacturerKey)
    {
        // Define required fields per manufacturer
        $requiredFields = [
            'common' => [
                'patient_name', 'patient_dob', 'provider_name', 'facility_name',
                'primary_insurance_name', 'product_name', 'expected_service_date'
            ],
            'ACZ_Distribution' => [
                'physician_attestation', 'not_used_previously'
            ],
            'Advanced_Health' => [],
            'MedLife' => ['amnio_amp_size'],
            'Centurion' => [],
            'BioWerX' => [],
            'BioWound' => [],
            'Extremity_Care' => ['quarter', 'order_type'],
            'Skye_Biologics' => ['shipping_speed_required'],
            'Total_Ancillary_Forms' => []
        ];

        $commonRequired = $requiredFields['common'] ?? [];
        $manufacturerRequired = $requiredFields[$manufacturerKey] ?? [];

        return in_array($fieldName, array_merge($commonRequired, $manufacturerRequired));
    }

    /**
     * Get coverage level based on percentage
     */
    private function getCoverageLevel($percentage)
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 50) return 'fair';
        return 'poor';
    }
}
