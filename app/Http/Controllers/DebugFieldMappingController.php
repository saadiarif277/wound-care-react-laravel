<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DebugFieldMappingController extends Controller
{
    /**
     * Debug field mapping without database
     */
    public function debugMapping(Request $request): JsonResponse
    {
        // Sample data that includes amnio_amp_size
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

        // Expected MedLife field mappings
        $medLifeFieldMappings = [
            'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
            'patient_name' => ['source' => 'patient_name', 'type' => 'text'],
            'patient_first_name' => ['source' => 'patient_first_name', 'type' => 'text'],
            'patient_last_name' => ['source' => 'patient_last_name', 'type' => 'text'],
            'patient_dob' => ['source' => 'patient_dob', 'type' => 'date'],
            'patient_gender' => ['source' => 'patient_gender', 'type' => 'radio'],
            'physician_name' => ['source' => 'physician_name', 'type' => 'text'],
            'physician_npi' => ['source' => 'physician_npi', 'type' => 'text'],
            'provider_name' => ['source' => 'provider_name', 'type' => 'text'],
            'provider_npi' => ['source' => 'provider_npi', 'type' => 'text'],
            'facility_name' => ['source' => 'facility_name', 'type' => 'text'],
            'facility_address' => ['source' => 'facility_address', 'type' => 'text'],
            'facility_city' => ['source' => 'facility_city', 'type' => 'text'],
            'facility_state' => ['source' => 'facility_state', 'type' => 'text'],
            'facility_zip' => ['source' => 'facility_zip', 'type' => 'text'],
            'wound_location' => ['source' => 'wound_location', 'type' => 'text'],
            'wound_size' => ['source' => 'wound_size', 'type' => 'text'],
            'wound_length' => ['source' => 'wound_length', 'type' => 'text'],
            'wound_width' => ['source' => 'wound_width', 'type' => 'text'],
            'wound_depth' => ['source' => 'wound_depth', 'type' => 'text'],
            'diagnosis_code' => ['source' => 'diagnosis_code', 'type' => 'text'],
            'icd10_code' => ['source' => 'icd10_code', 'type' => 'text'],
            'insurance_name' => ['source' => 'insurance_name', 'type' => 'text'],
            'insurance_id' => ['source' => 'insurance_id', 'type' => 'text'],
            'policy_number' => ['source' => 'policy_number', 'type' => 'text'],
            'group_number' => ['source' => 'group_number', 'type' => 'text'],
        ];

        // Simulate field mapping
        $mappedFields = [];
        foreach ($medLifeFieldMappings as $docusealField => $mappingConfig) {
            $sourceField = $mappingConfig['source'] ?? $mappingConfig['field'] ?? null;
            if ($sourceField && isset($sampleData[$sourceField])) {
                $mappedFields[$docusealField] = $sampleData[$sourceField];
            }
        }

        // Check AI configuration
        $aiConfig = [
            'ai_enabled' => config('ai.enabled', false),
            'ai_provider' => config('ai.provider'),
            'azure_ai_enabled' => config('azure.ai_foundry.enabled', false),
            'azure_endpoint' => config('azure.ai_foundry.endpoint') ? 'Set' : 'Not Set',
            'azure_api_key' => config('azure.ai_foundry.api_key') ? 'Set' : 'Not Set',
        ];

        // Amnio field specific check
        $amnioFieldCheck = [
            'in_sample_data' => isset($sampleData['amnio_amp_size']),
            'sample_value' => $sampleData['amnio_amp_size'] ?? null,
            'in_field_mappings' => isset($medLifeFieldMappings['amnio_amp_size']),
            'in_mapped_output' => isset($mappedFields['amnio_amp_size']),
            'mapped_value' => $mappedFields['amnio_amp_size'] ?? null,
        ];

        return response()->json([
            'success' => true,
            'sample_data' => $sampleData,
            'field_mappings' => $medLifeFieldMappings,
            'mapped_fields' => $mappedFields,
            'amnio_field_check' => $amnioFieldCheck,
            'ai_config' => $aiConfig,
            'mapping_stats' => [
                'input_fields' => count($sampleData),
                'available_mappings' => count($medLifeFieldMappings),
                'successfully_mapped' => count($mappedFields),
                'mapping_rate' => round((count($mappedFields) / count($sampleData)) * 100, 2) . '%'
            ],
            'recommendations' => [
                '1. The amnio_amp_size field is properly configured in the mapping',
                '2. The field should map correctly if the template has this field',
                '3. Check if DocuSeal template actually has a field named "amnio_amp_size"',
                '4. If AI is enabled, it should help map fields even if names don\'t match exactly'
            ]
        ]);
    }

    /**
     * Test the actual field mapping process
     */
    public function testActualMapping(Request $request): JsonResponse
    {
        try {
            // Get the DocuSeal service
            $docuSealService = app(\App\Services\DocuSealService::class);
            
            // Create a mock template object
            $mockTemplate = new \stdClass();
            $mockTemplate->id = 1;
            $mockTemplate->docuseal_template_id = '1233913'; // MedLife IVR template ID
            $mockTemplate->field_mappings = [
                'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
                'patient_name' => ['source' => 'patient_name', 'type' => 'text'],
                'patient_dob' => ['source' => 'patient_dob', 'type' => 'date'],
                'physician_name' => ['source' => 'physician_name', 'type' => 'text'],
                'physician_npi' => ['source' => 'physician_npi', 'type' => 'text'],
                'facility_name' => ['source' => 'facility_name', 'type' => 'text'],
                'wound_location' => ['source' => 'wound_location', 'type' => 'text'],
                'wound_size' => ['source' => 'wound_size', 'type' => 'text'],
                'diagnosis_code' => ['source' => 'diagnosis_code', 'type' => 'text'],
            ];

            // Sample data
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

            // Test the mapping
            $mappedFields = $docuSealService->mapFieldsFromArray($sampleData, $mockTemplate);

            return response()->json([
                'success' => true,
                'input_data' => $sampleData,
                'mapped_fields' => $mappedFields,
                'amnio_field_mapped' => isset($mappedFields['amnio_amp_size']),
                'amnio_field_value' => $mappedFields['amnio_amp_size'] ?? null,
                'total_mapped' => count($mappedFields),
                'mapping_details' => array_map(function($value, $key) use ($sampleData) {
                    return [
                        'docuseal_field' => $key,
                        'mapped_value' => $value,
                        'source_exists' => isset($sampleData[$key])
                    ];
                }, $mappedFields, array_keys($mappedFields))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}