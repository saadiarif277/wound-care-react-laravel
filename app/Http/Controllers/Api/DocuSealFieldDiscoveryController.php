<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocuSealService;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DocuSealFieldDiscoveryController extends Controller
{
    protected DocuSealService $docuSealService;
    
    public function __construct(DocuSealService $docuSealService)
    {
        $this->docuSealService = $docuSealService;
    }
    
    /**
     * Get template fields from DocuSeal API
     */
    public function getTemplateFields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'force_refresh' => 'boolean'
        ]);
        
        try {
            $templateId = $validated['template_id'];
            $forceRefresh = $validated['force_refresh'] ?? false;
            
            // Clear cache if force refresh
            if ($forceRefresh) {
                Cache::forget("docuseal_template_fields_{$templateId}");
            }
            
            // Get fields from DocuSeal API
            $fields = $this->docuSealService->getTemplateFieldsFromAPI($templateId);
            
            if (empty($fields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fields found for template',
                    'template_id' => $templateId
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'template_id' => $templateId,
                'field_count' => count($fields),
                'fields' => $fields,
                'cached' => !$forceRefresh
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get template fields', [
                'error' => $e->getMessage(),
                'template_id' => $validated['template_id'] ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template fields',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get fields for a manufacturer's template
     */
    public function getManufacturerTemplateFields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'document_type' => 'string|in:IVR,OrderForm,OnboardingForm'
        ]);
        
        try {
            $documentType = $validated['document_type'] ?? 'IVR';
            
            // Get the template for this manufacturer
            $template = DocusealTemplate::where('manufacturer_id', $validated['manufacturer_id'])
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->first();
                
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active template found for manufacturer'
                ], 404);
            }
            
            // Get fields from DocuSeal API
            $fields = $this->docuSealService->getTemplateFieldsFromAPI($template->docuseal_template_id);
            
            return response()->json([
                'success' => true,
                'manufacturer_id' => $validated['manufacturer_id'],
                'template' => [
                    'id' => $template->id,
                    'docuseal_template_id' => $template->docuseal_template_id,
                    'template_name' => $template->template_name,
                    'document_type' => $template->document_type
                ],
                'field_count' => count($fields),
                'fields' => $fields
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get manufacturer template fields', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $validated['manufacturer_id'] ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template fields',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Preview how fields will be mapped
     */
    public function previewFieldMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'form_data' => 'required|array',
            'use_ai' => 'boolean'
        ]);
        
        try {
            $templateId = $validated['template_id'];
            $formData = $validated['form_data'];
            $useAI = $validated['use_ai'] ?? true;
            
            // Get template from database
            $template = DocusealTemplate::where('docuseal_template_id', $templateId)->first();
            
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found in database'
                ], 404);
            }
            
            // Get template fields
            $templateFields = $this->docuSealService->getTemplateFieldsFromAPI($templateId);
            
            // Map fields (with or without AI)
            $mappedFields = [];
            if ($useAI && config('ai.enabled', false)) {
                $mappedFields = $this->docuSealService->mapFieldsWithAI($formData, $template);
            } else {
                $mappedFields = $this->docuSealService->mapFieldsFromArray($formData, $template);
            }
            
            // Analyze mapping coverage
            $coverage = $this->analyzeMappingCoverage($templateFields, $mappedFields, $formData);
            
            return response()->json([
                'success' => true,
                'mapping_method' => $useAI && config('ai.enabled', false) ? 'AI' : 'Static',
                'template_fields' => count($templateFields),
                'input_fields' => count($formData),
                'mapped_fields' => count($mappedFields),
                'coverage' => $coverage,
                'mapped_data' => $mappedFields,
                'preview' => $this->generatePreviewData($templateFields, $mappedFields)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to preview field mapping', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview field mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Analyze mapping coverage
     */
    private function analyzeMappingCoverage(array $templateFields, array $mappedFields, array $inputData): array
    {
        $totalTemplateFields = count($templateFields);
        $requiredFields = array_filter($templateFields, fn($field) => $field['required'] ?? false);
        $totalRequiredFields = count($requiredFields);
        
        $mappedFieldNames = array_keys($mappedFields);
        $mappedRequiredCount = 0;
        
        foreach ($requiredFields as $fieldName => $field) {
            if (in_array($fieldName, $mappedFieldNames) || in_array($field['id'] ?? '', $mappedFieldNames)) {
                $mappedRequiredCount++;
            }
        }
        
        return [
            'total_template_fields' => $totalTemplateFields,
            'total_required_fields' => $totalRequiredFields,
            'mapped_fields' => count($mappedFields),
            'mapped_required_fields' => $mappedRequiredCount,
            'coverage_percentage' => $totalTemplateFields > 0 
                ? round((count($mappedFields) / $totalTemplateFields) * 100, 2) 
                : 0,
            'required_coverage_percentage' => $totalRequiredFields > 0 
                ? round(($mappedRequiredCount / $totalRequiredFields) * 100, 2) 
                : 100,
            'unmapped_input_fields' => array_diff(array_keys($inputData), $mappedFieldNames)
        ];
    }
    
    /**
     * Generate preview data showing how fields will appear in DocuSeal
     */
    private function generatePreviewData(array $templateFields, array $mappedData): array
    {
        $preview = [];
        
        foreach ($templateFields as $fieldName => $fieldDef) {
            $value = $mappedData[$fieldName] ?? null;
            
            $preview[] = [
                'field_name' => $fieldName,
                'field_label' => $fieldDef['label'] ?? $fieldName,
                'field_type' => $fieldDef['type'] ?? 'text',
                'required' => $fieldDef['required'] ?? false,
                'mapped_value' => $value,
                'has_value' => !empty($value),
                'field_id' => $fieldDef['id'] ?? $fieldName
            ];
        }
        
        return $preview;
    }
}