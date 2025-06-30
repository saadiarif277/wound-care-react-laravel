<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\TemplateFieldMapping;
use App\Models\CanonicalField;
use App\Models\MappingAuditLog;
use App\Services\UnifiedFieldMappingService;
use App\Services\MappingRulesEngine;
use App\Services\FieldMappingSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TemplateMappingController extends Controller
{
    public function __construct(
        private UnifiedFieldMappingService $mappingService,
        private MappingRulesEngine $rulesEngine,
        private FieldMappingSuggestionService $suggestionService
    ) {}

    /**
     * Get field mappings for a template
     */
    public function getFieldMappings(string $templateId): JsonResponse
    {
        $template = DocusealTemplate::with(['fieldMappings.canonicalField', 'manufacturer'])
            ->findOrFail($templateId);

        // Get all canonical fields grouped by category
        $canonicalFields = CanonicalField::orderBy('category')
            ->orderBy('field_name')
            ->get()
            ->groupBy('category');

        // Get existing mappings indexed by field name
        $existingMappings = $template->fieldMappings->keyBy('field_name');

        // Get template fields from field_mappings JSON
        $templateFields = array_keys($template->field_mappings ?? []);

        // Build response with all template fields and their mappings
        $mappings = collect($templateFields)->map(function ($fieldName) use ($existingMappings) {
            $mapping = $existingMappings->get($fieldName);
            
            return [
                'field_name' => $fieldName,
                'canonical_field_id' => $mapping?->canonical_field_id,
                'canonical_field' => $mapping?->canonicalField,
                'transformation_rules' => $mapping?->transformation_rules ?? [],
                'confidence_score' => $mapping?->confidence_score ?? 0,
                'validation_status' => $mapping?->validation_status ?? 'valid',
                'validation_messages' => $mapping?->validation_messages ?? [],
                'is_active' => $mapping?->is_active ?? true,
            ];
        });

        return response()->json([
            'template' => $template,
            'mappings' => $mappings,
            'canonical_fields' => $canonicalFields,
            'statistics' => $this->getMappingStatistics($templateId)->original,
        ]);
    }

    /**
     * Update field mappings for a template
     */
    public function updateFieldMappings(Request $request, string $templateId): JsonResponse
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.field_name' => 'required|string',
            'mappings.*.canonical_field_id' => 'nullable|exists:canonical_fields,id',
            'mappings.*.transformation_rules' => 'nullable|array',
            'mappings.*.is_active' => 'nullable|boolean',
        ]);

        $template = DocusealTemplate::findOrFail($templateId);

        DB::beginTransaction();
        try {
            foreach ($request->mappings as $mappingData) {
                $fieldName = $mappingData['field_name'];
                
                // Find or create the mapping
                $mapping = TemplateFieldMapping::firstOrNew([
                    'template_id' => $templateId,
                    'field_name' => $fieldName,
                ]);

                // Store before state for audit
                $before = $mapping->exists ? $mapping->toArray() : null;

                // Update mapping
                $mapping->canonical_field_id = $mappingData['canonical_field_id'] ?? null;
                $mapping->transformation_rules = $mappingData['transformation_rules'] ?? [];
                $mapping->is_active = $mappingData['is_active'] ?? true;
                $mapping->is_composite = $mappingData['is_composite'] ?? false;
                $mapping->composite_fields = $mappingData['composite_fields'] ?? [];
                $mapping->updated_by = auth()->id();

                // Calculate confidence score if canonical field is set
                if ($mapping->canonical_field_id) {
                    $canonicalField = CanonicalField::find($mapping->canonical_field_id);
                    $mapping->confidence_score = $this->suggestionService->calculateSimilarity(
                        $fieldName,
                        $canonicalField->field_name
                    );
                }

                // Validate the mapping
                $validation = $this->mappingService->validateFieldMapping($mapping);
                $mapping->validation_status = $validation['status'];
                $mapping->validation_messages = $validation['messages'];

                $mapping->save();

                // Log the change
                if ($before) {
                    MappingAuditLog::logUpdate($templateId, $before, $mapping->toArray());
                } else {
                    MappingAuditLog::logCreation($templateId, $mapping->toArray());
                }
            }

            // Update template statistics
            $template->updateMappingStatistics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mappings updated successfully',
                'statistics' => $this->getMappingStatistics($templateId)->original,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update field mappings', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update mappings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update mappings
     */
    public function bulkUpdateMappings(Request $request, string $templateId): JsonResponse
    {
        $request->validate([
            'operation' => 'required|in:map_by_pattern,copy_from_template,reset_category,apply_transformation',
            'parameters' => 'required|array',
        ]);

        $template = DocusealTemplate::findOrFail($templateId);

        DB::beginTransaction();
        try {
            $changes = [];

            switch ($request->operation) {
                case 'map_by_pattern':
                    $changes = $this->mapByPattern($template, $request->parameters);
                    break;
                
                case 'copy_from_template':
                    $changes = $this->copyFromTemplate($template, $request->parameters);
                    break;
                
                case 'reset_category':
                    $changes = $this->resetCategory($template, $request->parameters);
                    break;
                
                case 'apply_transformation':
                    $changes = $this->applyTransformation($template, $request->parameters);
                    break;
            }

            // Log bulk operation
            MappingAuditLog::logBulkUpdate($templateId, [
                'operation' => $request->operation,
                'parameters' => $request->parameters,
                'affected_fields' => count($changes),
            ]);

            // Update template statistics
            $template->updateMappingStatistics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk operation completed successfully',
                'affected_fields' => count($changes),
                'changes' => $changes,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to perform bulk mapping operation', [
                'template_id' => $templateId,
                'operation' => $request->operation,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-map fields using AI
     */
    public function autoMapFields(Request $request, string $templateId): JsonResponse
    {
        $request->validate([
            'use_ai' => 'nullable|boolean',
            'confidence_threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        $template = DocusealTemplate::findOrFail($templateId);
        $useAI = $request->use_ai ?? true;
        $threshold = $request->confidence_threshold ?? 70;

        DB::beginTransaction();
        try {
            $mappedCount = 0;
            $templateFields = array_keys($template->field_mappings ?? []);
            $canonicalFields = CanonicalField::all();

            foreach ($templateFields as $fieldName) {
                // Skip if already mapped
                $existingMapping = TemplateFieldMapping::where('template_id', $templateId)
                    ->where('field_name', $fieldName)
                    ->first();
                
                if ($existingMapping && $existingMapping->canonical_field_id) {
                    continue;
                }

                // Get AI suggestions
                $suggestions = $this->suggestionService->suggestMapping($fieldName, $canonicalFields);
                
                if (empty($suggestions)) {
                    continue;
                }

                // Use the highest confidence suggestion
                $bestSuggestion = collect($suggestions)->sortByDesc('confidence')->first();
                
                if ($bestSuggestion['confidence'] < $threshold) {
                    continue;
                }

                // Create or update mapping
                $mapping = TemplateFieldMapping::firstOrNew([
                    'template_id' => $templateId,
                    'field_name' => $fieldName,
                ]);

                $mapping->canonical_field_id = $bestSuggestion['canonical_field_id'];
                $mapping->confidence_score = $bestSuggestion['confidence'];
                $mapping->transformation_rules = $bestSuggestion['transformation_rules'] ?? [];
                $mapping->is_active = true;
                $mapping->save();

                $mappedCount++;
            }

            DB::commit();

            // Audit log
            MappingAuditLog::create([
                'template_id' => $templateId,
                'action' => 'auto_map',
                'user_id' => auth()->id(),
                'changes' => [
                    'mapped_count' => $mappedCount,
                    'use_ai' => $useAI,
                    'threshold' => $threshold,
                ],
            ]);

            return response()->json([
                'success' => true,
                'mapped_count' => $mappedCount,
                'total_fields' => count($templateFields),
                'message' => "Successfully mapped {$mappedCount} fields using AI",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to auto-map fields', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-map fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suggest mappings using AI
     */
    public function suggestMappings(Request $request, string $templateId): JsonResponse
    {
        $request->validate([
            'field_names' => 'nullable|array',
            'confidence_threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        $template = DocusealTemplate::findOrFail($templateId);
        $fieldNames = $request->field_names ?? array_keys($template->field_mappings ?? []);
        $threshold = $request->confidence_threshold ?? 70;

        $suggestions = [];
        $canonicalFields = CanonicalField::all();

        foreach ($fieldNames as $fieldName) {
            $fieldSuggestions = $this->suggestionService->suggestMapping($fieldName, $canonicalFields);
            
            // Filter by confidence threshold
            $fieldSuggestions = array_filter($fieldSuggestions, function ($suggestion) use ($threshold) {
                return $suggestion['confidence'] >= $threshold;
            });

            if (!empty($fieldSuggestions)) {
                $suggestions[$fieldName] = array_values($fieldSuggestions);
            }
        }

        return response()->json([
            'suggestions' => $suggestions,
            'threshold_used' => $threshold,
            'total_fields' => count($fieldNames),
            'fields_with_suggestions' => count($suggestions),
        ]);
    }

    /**
     * Validate mappings
     */
    public function validateMappings(Request $request, string $templateId): JsonResponse
    {
        $template = DocusealTemplate::with('fieldMappings.canonicalField')->findOrFail($templateId);

        $validationResults = [];
        $summary = [
            'total_fields' => 0,
            'valid' => 0,
            'warnings' => 0,
            'errors' => 0,
            'unmapped' => 0,
        ];

        // Validate each mapping
        foreach ($template->fieldMappings as $mapping) {
            $validation = $this->mappingService->validateFieldMapping($mapping);
            
            $validationResults[$mapping->field_name] = $validation;
            
            $summary['total_fields']++;
            $summary[$validation['status']]++;
        }

        // Check for unmapped template fields
        $templateFields = array_keys($template->field_mappings ?? []);
        $mappedFields = $template->fieldMappings->pluck('field_name')->toArray();
        $unmappedFields = array_diff($templateFields, $mappedFields);
        
        foreach ($unmappedFields as $fieldName) {
            $validationResults[$fieldName] = [
                'status' => 'unmapped',
                'messages' => ['This field has not been mapped to a canonical field'],
            ];
            $summary['total_fields']++;
            $summary['unmapped']++;
        }

        // Check required canonical fields coverage
        $requiredFields = CanonicalField::required()->get();
        $mappedCanonicalIds = $template->fieldMappings->pluck('canonical_field_id')->filter()->toArray();
        $missingRequired = [];

        foreach ($requiredFields as $required) {
            if (!in_array($required->id, $mappedCanonicalIds)) {
                $missingRequired[] = [
                    'field' => $required->field_name,
                    'category' => $required->category,
                    'description' => $required->description,
                ];
            }
        }

        return response()->json([
            'validation_results' => $validationResults,
            'summary' => $summary,
            'missing_required_fields' => $missingRequired,
            'coverage_percentage' => $template->mapping_coverage,
            'is_complete' => $template->hasCompleteRequiredMappings(),
        ]);
    }

    /**
     * Get canonical fields
     */
    public function getCanonicalFields(): JsonResponse
    {
        // First, check if we have canonical fields in the database
        $dbFields = CanonicalField::orderBy('category')->orderBy('field_name')->get();
        
        if ($dbFields->isEmpty()) {
            // Load from JSON file if database is empty
            $this->mappingService->loadCanonicalFieldsFromJson();
            $dbFields = CanonicalField::orderBy('category')->orderBy('field_name')->get();
        }

        $fieldsByCategory = $dbFields->groupBy('category');

        return response()->json([
            'fields' => $dbFields,
            'by_category' => $fieldsByCategory,
            'total_fields' => $dbFields->count(),
            'required_fields' => $dbFields->where('is_required', true)->count(),
            'categories' => $fieldsByCategory->keys(),
        ]);
    }

    /**
     * Import mappings
     */
    public function importMappings(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json',
            'template_id' => 'required|exists:docuseal_templates,id',
            'mode' => 'required|in:replace,merge',
        ]);

        $template = DocusealTemplate::findOrFail($request->template_id);
        $content = json_decode($request->file('file')->get(), true);

        if (!$content || !isset($content['mappings'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mapping file format',
            ], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->mode === 'replace') {
                // Delete existing mappings
                $template->fieldMappings()->delete();
            }

            $imported = 0;
            foreach ($content['mappings'] as $mappingData) {
                $mapping = TemplateFieldMapping::updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'field_name' => $mappingData['field_name'],
                    ],
                    [
                        'canonical_field_id' => $mappingData['canonical_field_id'] ?? null,
                        'transformation_rules' => $mappingData['transformation_rules'] ?? [],
                        'confidence_score' => $mappingData['confidence_score'] ?? 0,
                        'is_active' => $mappingData['is_active'] ?? true,
                        'updated_by' => auth()->id(),
                    ]
                );
                $imported++;
            }

            // Update template statistics
            $template->updateMappingStatistics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} mappings",
                'imported_count' => $imported,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to import mappings', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export mappings
     */
    public function exportMappings(string $templateId): JsonResponse
    {
        $template = DocusealTemplate::with(['fieldMappings.canonicalField', 'manufacturer'])
            ->findOrFail($templateId);

        $exportData = [
            'template' => [
                'id' => $template->id,
                'name' => $template->template_name,
                'docuseal_template_id' => $template->docuseal_template_id,
                'manufacturer' => $template->manufacturer?->name,
                'document_type' => $template->document_type,
            ],
            'exported_at' => now()->toIso8601String(),
            'exported_by' => auth()->user()->name,
            'statistics' => $this->getMappingStatistics($templateId)->original,
            'mappings' => $template->fieldMappings->map(function ($mapping) {
                return [
                    'field_name' => $mapping->field_name,
                    'canonical_field_id' => $mapping->canonical_field_id,
                    'canonical_field_name' => $mapping->canonicalField?->field_name,
                    'canonical_field_path' => $mapping->canonicalField?->field_path,
                    'transformation_rules' => $mapping->transformation_rules,
                    'confidence_score' => $mapping->confidence_score,
                    'validation_status' => $mapping->validation_status,
                    'is_active' => $mapping->is_active,
                ];
            }),
        ];

        $filename = "mapping-export-{$template->template_name}-" . now()->format('Y-m-d-His') . '.json';

        return response()->json($exportData)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get mapping statistics
     */
    public function getMappingStatistics(string $templateId): JsonResponse
    {
        $template = DocusealTemplate::with('fieldMappings')->findOrFail($templateId);
        
        $totalTemplateFields = count($template->field_mappings ?? []);
        $mappedFields = $template->fieldMappings->whereNotNull('canonical_field_id')->count();
        $activeFields = $template->fieldMappings->where('is_active', true)->count();
        $highConfidence = $template->fieldMappings->where('confidence_score', '>=', 80)->count();
        
        // Get validation status counts
        $validationCounts = $template->fieldMappings
            ->groupBy('validation_status')
            ->map->count();

        // Check required fields coverage
        $requiredCanonicalFields = CanonicalField::required()->pluck('id');
        $mappedCanonicalIds = $template->fieldMappings
            ->whereNotNull('canonical_field_id')
            ->pluck('canonical_field_id');
        $requiredFieldsMapped = $requiredCanonicalFields->intersect($mappedCanonicalIds)->count();

        return response()->json([
            'totalFields' => $totalTemplateFields,
            'mappedFields' => $mappedFields,
            'unmappedFields' => $totalTemplateFields - $template->fieldMappings->count(),
            'activeFields' => $activeFields,
            'requiredFieldsMapped' => $requiredFieldsMapped,
            'totalRequiredFields' => $requiredCanonicalFields->count(),
            'optionalFieldsMapped' => $mappedFields - $requiredFieldsMapped,
            'coveragePercentage' => $totalTemplateFields > 0 ? round(($mappedFields / $totalTemplateFields) * 100, 2) : 0,
            'requiredCoveragePercentage' => $requiredCanonicalFields->count() > 0 
                ? round(($requiredFieldsMapped / $requiredCanonicalFields->count()) * 100, 2) 
                : 0,
            'highConfidenceCount' => $highConfidence,
            'validationStatus' => [
                'valid' => $validationCounts->get('valid', 0),
                'warning' => $validationCounts->get('warning', 0),
                'error' => $validationCounts->get('error', 0),
            ],
            'lastUpdated' => $template->last_mapping_update,
            'lastUpdatedBy' => $template->lastMappedBy?->name,
        ]);
    }

    /**
     * Private helper methods
     */

    private function mapByPattern(DocusealTemplate $template, array $parameters): array
    {
        $pattern = $parameters['pattern'] ?? '';
        $canonicalFieldId = $parameters['canonical_field_id'] ?? null;
        $transformationRules = $parameters['transformation_rules'] ?? [];

        $changes = [];
        $templateFields = array_keys($template->field_mappings ?? []);

        foreach ($templateFields as $fieldName) {
            if (preg_match($pattern, $fieldName)) {
                $mapping = TemplateFieldMapping::updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'field_name' => $fieldName,
                    ],
                    [
                        'canonical_field_id' => $canonicalFieldId,
                        'transformation_rules' => $transformationRules,
                        'updated_by' => auth()->id(),
                    ]
                );
                $changes[] = $fieldName;
            }
        }

        return $changes;
    }

    private function copyFromTemplate(DocusealTemplate $template, array $parameters): array
    {
        $sourceTemplateId = $parameters['source_template_id'] ?? null;
        $overwrite = $parameters['overwrite'] ?? false;

        $sourceTemplate = DocusealTemplate::with('fieldMappings')->findOrFail($sourceTemplateId);
        $changes = [];

        foreach ($sourceTemplate->fieldMappings as $sourceMapping) {
            // Check if this field exists in target template
            if (isset($template->field_mappings[$sourceMapping->field_name])) {
                $existingMapping = TemplateFieldMapping::where('template_id', $template->id)
                    ->where('field_name', $sourceMapping->field_name)
                    ->first();

                if (!$existingMapping || $overwrite) {
                    TemplateFieldMapping::updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'field_name' => $sourceMapping->field_name,
                        ],
                        [
                            'canonical_field_id' => $sourceMapping->canonical_field_id,
                            'transformation_rules' => $sourceMapping->transformation_rules,
                            'confidence_score' => $sourceMapping->confidence_score,
                            'updated_by' => auth()->id(),
                        ]
                    );
                    $changes[] = $sourceMapping->field_name;
                }
            }
        }

        return $changes;
    }

    private function resetCategory(DocusealTemplate $template, array $parameters): array
    {
        $category = $parameters['category'] ?? '';
        
        $canonicalFieldIds = CanonicalField::where('category', $category)->pluck('id');
        
        $changes = [];
        $mappings = $template->fieldMappings()
            ->whereIn('canonical_field_id', $canonicalFieldIds)
            ->get();

        foreach ($mappings as $mapping) {
            $mapping->update([
                'canonical_field_id' => null,
                'transformation_rules' => [],
                'confidence_score' => 0,
                'updated_by' => auth()->id(),
            ]);
            $changes[] = $mapping->field_name;
        }

        return $changes;
    }

    private function applyTransformation(DocusealTemplate $template, array $parameters): array
    {
        $fieldNames = $parameters['field_names'] ?? [];
        $transformationRules = $parameters['transformation_rules'] ?? [];

        $changes = [];
        
        foreach ($fieldNames as $fieldName) {
            $mapping = TemplateFieldMapping::where('template_id', $template->id)
                ->where('field_name', $fieldName)
                ->first();

            if ($mapping) {
                $mapping->update([
                    'transformation_rules' => array_merge(
                        $mapping->transformation_rules ?? [],
                        $transformationRules
                    ),
                    'updated_by' => auth()->id(),
                ]);
                $changes[] = $fieldName;
            }
        }

        return $changes;
    }
}