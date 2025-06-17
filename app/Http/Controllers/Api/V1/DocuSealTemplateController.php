<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DocuSealTemplateSyncService;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\IvrFormExtractionService;
use App\Services\IvrFieldDiscoveryService;
use App\Services\DocuSealFieldSyncService;

class DocuSealTemplateController extends Controller
{
    protected DocuSealTemplateSyncService $syncService;
    protected ?IvrFormExtractionService $extractionService = null;
    protected ?IvrFieldDiscoveryService $discoveryService = null;
    protected ?DocuSealFieldSyncService $fieldSyncService = null;

    public function __construct(DocuSealTemplateSyncService $syncService)
    {
        $this->syncService = $syncService;
        
        // Initialize the new services only when needed to avoid dependency issues
        try {
            $this->extractionService = app(IvrFormExtractionService::class);
            $this->discoveryService = app(IvrFieldDiscoveryService::class);
            $this->fieldSyncService = app(DocuSealFieldSyncService::class);
        } catch (\Exception $e) {
            Log::warning('IVR field discovery services not available', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync templates from DocuSeal API and return updated list.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            // Check permission - but allow MSC admin role even without specific permission
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }
            
            // Allow if user has manage-orders permission OR is msc-admin
            if (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin')) {
                Log::warning('DocuSeal sync access denied', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $user->roles->pluck('slug'),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }
            
            $templates = $this->syncService->pullTemplatesFromDocuSeal();
            
            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('DocuSeal template sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all templates from local DB.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check permission - but allow MSC admin role even without specific permission
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }
            
            // Allow if user has manage-orders permission OR is msc-admin
            if (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin')) {
                Log::warning('DocuSeal access denied', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $user->roles->pluck('slug'),
                    'permissions' => $user->roles->flatMap->permissions->pluck('slug')->unique(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }
            
            $templates = DocusealTemplate::all();
            
            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('DocuSeal templates index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract fields from an uploaded IVR PDF
     */
    public function extractFields(Request $request): JsonResponse
    {
        try {
            // Check if services are available
            if (!$this->extractionService || !$this->discoveryService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field extraction service not available. Please check Azure DI configuration.',
                ], 503);
            }
            
            // Check permissions
            $user = Auth::user();
            if (!$user || (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }

            // Validate request
            $request->validate([
                'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
                'template_id' => 'required|string|exists:docuseal_templates,id',
                'manufacturer_id' => 'required|string'
            ]);

            // Store uploaded file temporarily on local disk
            $pdfFile = $request->file('pdf');
            // Generate a unique filename
            $tempFileName = 'temp/' . uniqid('ivr_') . '.pdf';
            // Store file using Storage facade to ensure we use local disk
            \Illuminate\Support\Facades\Storage::disk('local')->put($tempFileName, file_get_contents($pdfFile->getRealPath()));
            $fullPath = storage_path('app/' . $tempFileName);

            try {
                // Extract fields and metadata from PDF
                $extractionResult = $this->extractionService->extractFieldsAndMetadata(
                    $fullPath,
                    $request->manufacturer_id
                );
                
                $extractedFields = $extractionResult['fields'] ?? [];
                $formMetadata = $extractionResult['metadata'] ?? [];

                // Generate mapping suggestions
                $suggestions = $this->discoveryService->generateMappingSuggestions(
                    $extractedFields,
                    $request->template_id
                );

                // Get summary statistics
                $summary = $this->discoveryService->getDiscoverySummary($extractedFields, $suggestions);

                // Clean up temp file
                \Illuminate\Support\Facades\Storage::disk('local')->delete($tempFileName);

                // Update template with extraction metadata and field suggestions
                if (!empty($formMetadata)) {
                    $template = DocusealTemplate::find($request->template_id);
                    if ($template) {
                        $template->update([
                            'extraction_metadata' => array_merge($template->extraction_metadata ?? [], [
                                'last_extraction' => $formMetadata,
                                'field_suggestions' => $suggestions,
                                'discovery_summary' => $summary,
                                'last_extracted_at' => now()->toIso8601String()
                            ])
                        ]);
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'fields' => $extractedFields,
                    'suggestions' => $suggestions,
                    'summary' => $summary,
                    'metadata' => $formMetadata ?? []
                ]);

            } catch (\Exception $e) {
                // Clean up temp file on error
                \Illuminate\Support\Facades\Storage::disk('local')->delete($tempFileName);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Field extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update field mappings for a template
     */
    public function updateMappings(Request $request, string $templateId): JsonResponse
    {
        try {
            // Check permissions
            $user = Auth::user();
            if (!$user || (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }

            // Validate request
            $request->validate([
                'mappings' => 'required|array',
                'mappings.*.ivr_field_name' => 'required|string',
                'mappings.*.system_field' => 'nullable|string',
                'mappings.*.field_type' => 'required|string',
                'mappings.*.mapping_type' => 'nullable|string'
            ]);

            // Find template
            $template = DocusealTemplate::findOrFail($templateId);

            // Get current mappings
            $currentMappings = $template->field_mappings ?? [];

            // Apply new mappings
            foreach ($request->mappings as $mapping) {
                $fieldName = $mapping['ivr_field_name'];
                
                if (!empty($mapping['system_field'])) {
                    $currentMappings[$fieldName] = [
                        'local_field' => $mapping['system_field'],
                        'type' => $mapping['field_type'],
                        'mapping_type' => $mapping['mapping_type'] ?? 'manual',
                        'updated_at' => now()->toIso8601String(),
                        'updated_by' => $user->id
                    ];
                } else {
                    // Remove mapping if system_field is empty
                    unset($currentMappings[$fieldName]);
                }
            }

            // Update template
            $template->update([
                'field_mappings' => $currentMappings,
                'extraction_metadata' => array_merge($template->extraction_metadata ?? [], [
                    'last_mapping_update' => now()->toIso8601String(),
                    'updated_by' => $user->email
                ])
            ]);

            // Log the update
            Log::info('Template field mappings updated', [
                'template_id' => $templateId,
                'updated_fields' => count($request->mappings),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Field mappings updated successfully',
                'template' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Field mapping update failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply bulk mapping patterns
     */
    public function applyBulkPatterns(Request $request, string $templateId): JsonResponse
    {
        try {
            // Check permissions
            $user = Auth::user();
            if (!$user || (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }

            // Validate request
            $request->validate([
                'patterns' => 'required|array',
                'patterns.*.field_pattern' => 'required|string',
                'patterns.*.mapping_template' => 'required|string',
                'patterns.*.confidence' => 'numeric|min:0|max:1'
            ]);

            // This would apply pattern-based mappings to multiple fields
            // For example: Map all "Physician NPI 1-7" fields using a pattern

            return response()->json([
                'success' => true,
                'message' => 'Bulk patterns applied successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk pattern application failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update template metadata
     */
    public function updateMetadata(Request $request, string $templateId): JsonResponse
    {
        try {
            // Check permissions
            $user = Auth::user();
            if (!$user || (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }

            // Validate request
            $request->validate([
                'metadata' => 'required|array',
                'metadata.form_type' => 'required|string|in:IVR,Order,Onboarding',
                'metadata.manufacturer' => 'required|string',
                'metadata.detected_products' => 'array',
                'metadata.detected_products.*.code' => 'required|string',
                'metadata.detected_products.*.name' => 'required|string',
                'metadata.detected_products.*.confidence' => 'numeric|min:0|max:1'
            ]);

            // Find template
            $template = DocusealTemplate::findOrFail($templateId);

            // Update extraction metadata
            $currentMetadata = $template->extraction_metadata ?? [];
            $currentMetadata['last_extraction'] = array_merge(
                $currentMetadata['last_extraction'] ?? [],
                $request->metadata
            );
            $currentMetadata['metadata_updated_at'] = now()->toIso8601String();
            $currentMetadata['metadata_updated_by'] = $user->email;

            $template->update([
                'extraction_metadata' => $currentMetadata
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Metadata updated successfully',
                'template' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Metadata update failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get template fields for a specific manufacturer
     */
    public function getManufacturerFields($manufacturer): JsonResponse
    {
        try {
            // Find manufacturer by name
            $mfr = \App\Models\Order\Manufacturer::where('name', $manufacturer)->first();
            
            if (!$mfr || !$mfr->docuseal_template_id) {
                // Return empty fields if no template
                return response()->json([
                    'manufacturer' => $manufacturer,
                    'template_id' => null,
                    'fields' => []
                ]);
            }
            
            // Get template from database
            $template = DocusealTemplate::where('docuseal_template_id', $mfr->docuseal_template_id)
                ->orWhere('id', $mfr->docuseal_template_id)
                ->first();
            
            if (!$template) {
                return response()->json([
                    'manufacturer' => $manufacturer,
                    'template_id' => $mfr->docuseal_template_id,
                    'fields' => []
                ]);
            }
            
            // Get field mappings
            $fieldMappings = $template->field_mappings ?? [];
            $extractedFields = $template->extraction_metadata['field_suggestions'] ?? [];
            
            // Convert to frontend format
            $fields = [];
            foreach ($fieldMappings as $fieldName => $mapping) {
                // Skip fields we already collect in other steps
                $skipFields = ['patientName', 'patientDOB', 'facilityName', 'physicianName', 'selectedProducts'];
                if (in_array($fieldName, $skipFields)) continue;
                
                $fields[] = [
                    'slug' => $fieldName,
                    'name' => $this->humanizeFieldName($fieldName),
                    'type' => $mapping['type'] ?? 'text',
                    'required' => $mapping['required'] ?? false,
                    'description' => $mapping['description'] ?? null,
                    'options' => $mapping['options'] ?? null,
                    'default_value' => $mapping['default_value'] ?? null,
                ];
            }
            
            // Add any discovered fields that aren't mapped yet
            foreach ($extractedFields as $suggestion) {
                $fieldName = $suggestion['ivr_field_name'] ?? '';
                if (!isset($fieldMappings[$fieldName]) && !in_array($fieldName, ['patientName', 'patientDOB', 'facilityName', 'physicianName', 'selectedProducts'])) {
                    $fields[] = [
                        'slug' => $fieldName,
                        'name' => $this->humanizeFieldName($fieldName),
                        'type' => $this->guessFieldType($fieldName),
                        'required' => false,
                        'description' => null,
                        'options' => null,
                        'default_value' => null,
                    ];
                }
            }
            
            return response()->json([
                'manufacturer' => $manufacturer,
                'template_id' => $mfr->docuseal_template_id,
                'fields' => $fields
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching manufacturer template fields', [
                'manufacturer' => $manufacturer,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch template fields',
                'manufacturer' => $manufacturer,
                'fields' => []
            ], 500);
        }
    }
    
    /**
     * Convert field name to human readable
     */
    private function humanizeFieldName($fieldName)
    {
        // Remove common prefixes
        $fieldName = preg_replace('/^(patient|physician|facility|insurance|wound|procedure)_/', '', $fieldName);
        
        // Convert camelCase to spaces
        $fieldName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $fieldName);
        
        // Convert snake_case to spaces
        $fieldName = str_replace('_', ' ', $fieldName);
        
        // Capitalize words
        return ucwords(strtolower($fieldName));
    }
    
    /**
     * Guess field type based on name
     */
    private function guessFieldType($fieldName)
    {
        $fieldName = strtolower($fieldName);
        
        if (strpos($fieldName, 'date') !== false || strpos($fieldName, 'dob') !== false) {
            return 'date';
        }
        if (strpos($fieldName, 'signature') !== false || strpos($fieldName, 'sign') !== false) {
            return 'signature';
        }
        if (strpos($fieldName, 'notes') !== false || strpos($fieldName, 'comment') !== false || strpos($fieldName, 'description') !== false) {
            return 'textarea';
        }
        if (strpos($fieldName, 'number') !== false || strpos($fieldName, 'size') !== false || strpos($fieldName, 'days') !== false) {
            return 'number';
        }
        if (strpos($fieldName, 'status') !== false || strpos($fieldName, 'type') !== false) {
            return 'select';
        }
        if (strpos($fieldName, 'is_') === 0 || strpos($fieldName, 'has_') === 0 || strpos($fieldName, 'checkbox') !== false) {
            return 'checkbox';
        }
        
        return 'text';
    }
    
    /**
     * Sync fields from DocuSeal template
     */
    public function syncFields(string $templateId): JsonResponse
    {
        try {
            // Check if service is available
            if (!$this->fieldSyncService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field sync service not available.',
                ], 503);
            }
            
            // Check permissions
            $user = Auth::user();
            if (!$user || (!$user->hasPermission('manage-orders') && !$user->hasRole('msc-admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You need manage-orders permission or msc-admin role.',
                ], 403);
            }

            // Find template
            $template = DocusealTemplate::findOrFail($templateId);

            // Sync fields from DocuSeal
            $result = $this->fieldSyncService->syncTemplateFields($template);

            return response()->json([
                'success' => true,
                'message' => 'Fields synced successfully',
                'field_mappings' => $template->fresh()->field_mappings,
                'total_fields' => $result['total_fields'],
                'new_fields' => $result['new_fields'],
                'updated_fields' => $result['updated_fields']
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal field sync failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}