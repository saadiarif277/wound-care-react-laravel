<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocuSealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DocuSealDebugController extends Controller
{
    public function __construct(
        private DocuSealService $docuSealService
    ) {}

    /**
     * Debug field mapping for MedLife
     */
    public function debugMedLifeMapping(Request $request): JsonResponse
    {
        try {
            // Find MedLife manufacturer
            $manufacturer = Manufacturer::where('name', 'LIKE', '%MEDLIFE%')->first();
            
            if (!$manufacturer) {
                return response()->json([
                    'success' => false,
                    'error' => 'MedLife manufacturer not found'
                ], 404);
            }

            // Get MedLife template
            $template = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                ->where('document_type', 'IVR')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'MedLife IVR template not found',
                    'manufacturer_id' => $manufacturer->id
                ], 404);
            }

            // Get fields from DocuSeal API
            $docuSealFields = $this->docuSealService->getTemplateFieldsFromAPI($template->docuseal_template_id);

            // Sample data with amnio_amp_size
            $sampleData = [
                'amnio_amp_size' => '4x4',
                'patient_name' => 'John Doe',
                'patient_dob' => '1980-01-15',
                'physician_name' => 'Dr. Jane Smith',
                'physician_npi' => '1234567890',
                'facility_name' => 'City Medical Center',
                'wound_location' => 'Left foot',
                'wound_size' => '4cm x 4cm',
                'diagnosis_code' => 'L97.512'
            ];

            // Test static mapping
            $staticMappedFields = $this->docuSealService->mapFieldsFromArray($sampleData, $template);

            // Test AI mapping if enabled
            $aiMappedFields = [];
            $aiEnabled = config('ai.enabled', false) && config('ai.provider') !== 'mock';
            
            if ($aiEnabled) {
                try {
                    $aiMappedFields = $this->docuSealService->mapFieldsWithAI($sampleData, $template);
                } catch (\Exception $e) {
                    Log::error('AI mapping failed in debug', ['error' => $e->getMessage()]);
                }
            }

            // Check for amnio_amp_size specifically
            $amnioFieldCheck = [
                'in_sample_data' => isset($sampleData['amnio_amp_size']),
                'sample_value' => $sampleData['amnio_amp_size'] ?? null,
                'in_template_fields' => isset($docuSealFields['amnio_amp_size']),
                'template_field_info' => $docuSealFields['amnio_amp_size'] ?? null,
                'in_static_mapping' => isset($staticMappedFields['amnio_amp_size']),
                'static_mapped_value' => $staticMappedFields['amnio_amp_size'] ?? null,
                'in_ai_mapping' => isset($aiMappedFields['amnio_amp_size']),
                'ai_mapped_value' => $aiMappedFields['amnio_amp_size'] ?? null,
            ];

            return response()->json([
                'success' => true,
                'manufacturer' => [
                    'id' => $manufacturer->id,
                    'name' => $manufacturer->name
                ],
                'template' => [
                    'id' => $template->id,
                    'docuseal_template_id' => $template->docuseal_template_id,
                    'name' => $template->template_name,
                    'field_mappings' => $template->field_mappings
                ],
                'docuseal_api_fields' => [
                    'count' => count($docuSealFields),
                    'field_names' => array_keys($docuSealFields),
                    'fields' => $docuSealFields
                ],
                'mapping_test' => [
                    'sample_data' => $sampleData,
                    'static_mapped' => [
                        'count' => count($staticMappedFields),
                        'fields' => $staticMappedFields
                    ],
                    'ai_mapped' => [
                        'enabled' => $aiEnabled,
                        'count' => count($aiMappedFields),
                        'fields' => $aiMappedFields
                    ]
                ],
                'amnio_field_check' => $amnioFieldCheck,
                'ai_config' => [
                    'ai_enabled' => config('ai.enabled', false),
                    'ai_provider' => config('ai.provider'),
                    'azure_ai_enabled' => config('azure.ai_foundry.enabled', false)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal debug error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test field mapping for any manufacturer
     */
    public function testFieldMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manufacturer_name' => 'required|string',
            'sample_data' => 'required|array',
            'use_ai' => 'nullable|boolean'
        ]);

        try {
            // Find manufacturer
            $manufacturer = Manufacturer::where('name', 'LIKE', '%' . $validated['manufacturer_name'] . '%')->first();
            
            if (!$manufacturer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manufacturer not found: ' . $validated['manufacturer_name']
                ], 404);
            }

            // Get template
            $template = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active template found for manufacturer'
                ], 404);
            }

            // Get DocuSeal fields
            $docuSealFields = $this->docuSealService->getTemplateFieldsFromAPI($template->docuseal_template_id);

            // Map fields
            if ($validated['use_ai'] ?? false) {
                $mappedFields = $this->docuSealService->mapFieldsWithAI($validated['sample_data'], $template);
            } else {
                $mappedFields = $this->docuSealService->mapFieldsFromArray($validated['sample_data'], $template);
            }

            return response()->json([
                'success' => true,
                'manufacturer' => $manufacturer->name,
                'template' => $template->template_name,
                'docuseal_fields' => array_keys($docuSealFields),
                'input_fields' => array_keys($validated['sample_data']),
                'mapped_fields' => $mappedFields,
                'mapping_stats' => [
                    'input_count' => count($validated['sample_data']),
                    'mapped_count' => count($mappedFields),
                    'success_rate' => count($validated['sample_data']) > 0 
                        ? round((count($mappedFields) / count($validated['sample_data'])) * 100, 2) . '%'
                        : '0%'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}