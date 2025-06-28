<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DocuSealFieldValidationController extends Controller
{
    public function __construct(
        private DocusealService $docuSealService,
        private UnifiedFieldMappingService $fieldMappingService
    ) {}

    /**
     * Validate fields for a specific manufacturer's template
     */
    public function validateFields(Request $request): JsonResponse
    {
        $request->validate([
            'episode_id' => 'required|integer',
            'manufacturer' => 'required|string',
        ]);

        try {
            $episodeId = $request->input('episode_id');
            $manufacturerName = $request->input('manufacturer');

            // Get mapped data
            $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate($episodeId, $manufacturerName);
            
            // Get template fields from DocuSeal
            $templateId = $mappingResult['manufacturer']['template_id'];
            $templateFields = $this->docuSealService->getTemplateFieldsFromAPI($templateId);

            // Analyze field compatibility
            $mappedFieldNames = array_keys($mappingResult['data']);
            $templateFieldNames = array_keys($templateFields);
            
            $matchingFields = array_intersect($mappedFieldNames, $templateFieldNames);
            $missingInTemplate = array_diff($mappedFieldNames, $templateFieldNames);
            $unusedTemplateFields = array_diff($templateFieldNames, $mappedFieldNames);

            // Check which required fields are missing
            $missingRequiredFields = [];
            foreach ($unusedTemplateFields as $field) {
                if ($templateFields[$field]['required'] ?? false) {
                    $missingRequiredFields[] = [
                        'field' => $field,
                        'type' => $templateFields[$field]['type'] ?? 'text',
                        'label' => $templateFields[$field]['label'] ?? $field,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'summary' => [
                    'total_mapped_fields' => count($mappedFieldNames),
                    'total_template_fields' => count($templateFieldNames),
                    'matching_fields' => count($matchingFields),
                    'fields_not_in_template' => count($missingInTemplate),
                    'unused_template_fields' => count($unusedTemplateFields),
                    'missing_required_fields' => count($missingRequiredFields),
                ],
                'field_analysis' => [
                    'matching_fields' => $matchingFields,
                    'fields_not_in_template' => $missingInTemplate,
                    'unused_template_fields' => $unusedTemplateFields,
                    'missing_required_fields' => $missingRequiredFields,
                ],
                'mapping_completeness' => $mappingResult['completeness'],
                'validation' => $mappingResult['validation'],
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal field validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get template fields for a manufacturer
     */
    public function getTemplateFields(Request $request): JsonResponse
    {
        $request->validate([
            'manufacturer' => 'required|string',
        ]);

        try {
            $manufacturerName = $request->input('manufacturer');
            
            // Get manufacturer config
            $manufacturerConfig = $this->fieldMappingService->getManufacturerConfig($manufacturerName);
            if (!$manufacturerConfig) {
                return response()->json([
                    'success' => false,
                    'error' => "Unknown manufacturer: {$manufacturerName}",
                ], 404);
            }

            // Get template fields from DocuSeal
            $templateId = $manufacturerConfig['template_id'];
            $templateFields = $this->docuSealService->getTemplateFieldsFromAPI($templateId);

            // Format fields for response
            $formattedFields = [];
            foreach ($templateFields as $name => $field) {
                $formattedFields[] = [
                    'name' => $name,
                    'id' => $field['id'] ?? $name,
                    'type' => $field['type'] ?? 'text',
                    'label' => $field['label'] ?? $name,
                    'required' => $field['required'] ?? false,
                    'options' => $field['options'] ?? [],
                ];
            }

            return response()->json([
                'success' => true,
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'field_count' => count($formattedFields),
                'fields' => $formattedFields,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get template fields', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cached template fields
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'manufacturer' => 'required|string',
        ]);

        try {
            $manufacturerName = $request->input('manufacturer');
            
            // Get manufacturer config
            $manufacturerConfig = $this->fieldMappingService->getManufacturerConfig($manufacturerName);
            if (!$manufacturerConfig) {
                return response()->json([
                    'success' => false,
                    'error' => "Unknown manufacturer: {$manufacturerName}",
                ], 404);
            }

            // Clear cache for this template
            $templateId = $manufacturerConfig['template_id'];
            $cacheKey = "docuseal_template_fields_{$templateId}";
            \Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => "Cache cleared for {$manufacturerName} template",
                'template_id' => $templateId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}