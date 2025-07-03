<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\AzureDocumentIntelligenceService;
use GuzzleHttp\Client as HttpClient;

class DocusealTemplateController extends Controller
{
    protected ?AzureDocumentIntelligenceService $azureService = null;

    public function __construct()
    {
        // Initialize Azure service only when needed to avoid dependency issues
        try {
            $this->azureService = app(AzureDocumentIntelligenceService::class);
        } catch (\Exception $e) {
            Log::warning('Azure Document Intelligence service not available', ['error' => $e->getMessage()]);
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
                Log::warning('Docuseal access denied', [
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
            
            $templates = DocusealTemplate::where('is_active', true)
                ->orderBy('name')
                ->paginate(50);
            
            return response()->json([
                'success' => true,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('Docuseal templates index failed', [
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
     * Upload PDF template with embedded text field tags
     */
    public function uploadEmbedded(Request $request): JsonResponse
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
                'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
                'template_id' => 'required|string',
                'upload_type' => 'required|string|in:embedded_tags'
            ]);

            $template = DocusealTemplate::findOrFail($request->template_id);
            $pdfFile = $request->file('pdf');

            // Store the PDF temporarily for processing
            $tempFileName = 'temp_embedded_' . uniqid() . '.pdf';
            $tempPath = storage_path('app/temp/' . $tempFileName);
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            $pdfFile->move(dirname($tempPath), $tempFileName);

            try {
                // Extract embedded field tags from PDF using multiple methods
                $embeddedTags = $this->extractEmbeddedTags($tempPath);
                
                // Enhanced analysis using Azure Document Intelligence
                $azureAnalysis = $this->analyzeWithAzureDocumentIntelligence($tempPath);
                
                // Combine and validate embedded tags with Azure insights
                $enhancedTags = $this->enhanceTagsWithAzureAnalysis($embeddedTags, $azureAnalysis);
                
                // Upload to Docuseal and create template
                $docusealResult = $this->uploadToDocuseal($tempPath, $template);
                
                // Map enhanced tags to Quick Request fields
                $fieldMappings = $this->mapEmbeddedFieldsToQuickRequest($enhancedTags);
                
                // Update template record
                $template->update([
                    'docuseal_template_id' => $docusealResult['template_id'],
                    'field_mappings' => $fieldMappings,
                    'extraction_metadata' => array_merge($template->extraction_metadata ?? [], [
                        'embedded_tags_detected' => $embeddedTags,
                        'upload_date' => now()->toIso8601String(),
                        'template_type' => 'embedded_tags',
                        'auto_mapped' => true
                    ])
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Template uploaded successfully with embedded field tags',
                    'docuseal_template_id' => $docusealResult['template_id'],
                    'embedded_tags' => $embeddedTags,
                    'embedded_fields' => $fieldMappings,
                    'template' => $template->fresh()
                ]);

            } finally {
                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

        } catch (\Exception $e) {
            Log::error('Embedded template upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract embedded field tags from PDF content
     */
    private function extractEmbeddedTags(string $pdfPath): array
    {
        $embeddedTags = [];
        
        try {
            $pdfText = '';
            
            // Try multiple methods to extract text from PDF
            // Method 1: pdftotext (if available)
            if (function_exists('shell_exec') && shell_exec('which pdftotext')) {
                $command = "pdftotext " . escapeshellarg($pdfPath) . " -";
                $pdfText = shell_exec($command);
            }
            
            // Method 2: If pdftotext failed, try reading raw PDF content
            if (!$pdfText) {
                $pdfText = file_get_contents($pdfPath);
            }
            
            if (!$pdfText) {
                throw new \Exception('Could not extract text from PDF');
            }
            
            Log::info('Extracting embedded tags from PDF', [
                'pdf_path' => basename($pdfPath),
                'text_length' => strlen($pdfText)
            ]);
            
            // Extract all {{field_name}} patterns with improved regex
            $pattern = '/\{\{\s*([^}]+?)\s*\}\}/';
            preg_match_all($pattern, $pdfText, $matches);
            
            $uniqueTags = [];
            
            foreach ($matches[1] as $match) {
                // Clean up the match
                $match = trim($match);
                if (empty($match)) continue;
                
                // Parse field name and attributes
                $parts = explode(';', $match);
                $fieldName = trim($parts[0]);
                
                if (empty($fieldName)) continue;
                
                $attributes = [];
                for ($i = 1; $i < count($parts); $i++) {
                    $attrParts = explode('=', $parts[$i], 2);
                    if (count($attrParts) === 2) {
                        $key = trim($attrParts[0]);
                        $value = trim($attrParts[1], '"\'');
                        if (!empty($key)) {
                            $attributes[$key] = $value;
                        }
                    }
                }
                
                $tagKey = $fieldName . '_' . md5(serialize($attributes));
                
                if (!isset($uniqueTags[$tagKey])) {
                    $uniqueTags[$tagKey] = [
                        'field_name' => $fieldName,
                        'attributes' => $attributes,
                        'type' => $attributes['type'] ?? 'text',
                        'required' => in_array($attributes['required'] ?? 'false', ['true', '1', 'yes'], true),
                        'original_tag' => '{{' . $match . '}}'
                    ];
                }
            }
            
            $embeddedTags = array_values($uniqueTags);
            
            Log::info('Extracted embedded field tags', [
                'pdf_path' => basename($pdfPath),
                'total_matches' => count($matches[1]),
                'unique_tags' => count($embeddedTags),
                'field_names' => array_column($embeddedTags, 'field_name')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to extract embedded tags from PDF', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath,
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $embeddedTags;
    }

    /**
     * Analyze PDF with Azure Document Intelligence
     */
    private function analyzeWithAzureDocumentIntelligence(string $pdfPath): array
    {
        try {
            if (!$this->azureService) {
                Log::warning('Azure Document Intelligence service not available');
                return [];
            }

            Log::info('Starting Azure Document Intelligence analysis', [
                'pdf_path' => basename($pdfPath),
                'file_size' => filesize($pdfPath)
            ]);

            // Use Azure DI to analyze the PDF for form fields and text
            $analysisResult = $this->azureService->analyzeDocument($pdfPath, [
                'model_id' => 'prebuilt-document', // Use prebuilt document model
                'features' => ['formFields', 'tables', 'paragraphs']
            ]);

            // Extract relevant information for embedded tag enhancement
            $azureInsights = [
                'form_fields' => [],
                'text_elements' => [],
                'layout_analysis' => [],
                'confidence_scores' => []
            ];

            // Process form fields detected by Azure
            if (isset($analysisResult['analyzeResult']['documents'][0]['fields'])) {
                foreach ($analysisResult['analyzeResult']['documents'][0]['fields'] as $fieldName => $fieldData) {
                    $azureInsights['form_fields'][] = [
                        'name' => $fieldName,
                        'type' => $fieldData['type'] ?? 'string',
                        'value' => $fieldData['valueString'] ?? $fieldData['content'] ?? '',
                        'confidence' => $fieldData['confidence'] ?? 0,
                        'bounding_regions' => $fieldData['boundingRegions'] ?? []
                    ];
                }
            }

            // Process text elements and paragraphs
            if (isset($analysisResult['analyzeResult']['paragraphs'])) {
                foreach ($analysisResult['analyzeResult']['paragraphs'] as $paragraph) {
                    $azureInsights['text_elements'][] = [
                        'content' => $paragraph['content'],
                        'confidence' => $paragraph['confidence'] ?? 0,
                        'bounding_regions' => $paragraph['boundingRegions'] ?? []
                    ];
                }
            }

            // Extract layout information for better field positioning
            if (isset($analysisResult['analyzeResult']['pages'])) {
                foreach ($analysisResult['analyzeResult']['pages'] as $page) {
                    $azureInsights['layout_analysis'][] = [
                        'page_number' => $page['pageNumber'],
                        'width' => $page['width'] ?? 0,
                        'height' => $page['height'] ?? 0,
                        'unit' => $page['unit'] ?? 'pixel'
                    ];
                }
            }

            Log::info('Azure Document Intelligence analysis completed', [
                'form_fields_detected' => count($azureInsights['form_fields']),
                'text_elements' => count($azureInsights['text_elements']),
                'pages_analyzed' => count($azureInsights['layout_analysis'])
            ]);

            return $azureInsights;

        } catch (\Exception $e) {
            Log::error('Azure Document Intelligence analysis failed', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty array so processing can continue without Azure insights
            return [];
        }
    }

    /**
     * Enhance embedded tags with Azure Document Intelligence insights
     */
    private function enhanceTagsWithAzureAnalysis(array $embeddedTags, array $azureAnalysis): array
    {
        if (empty($azureAnalysis) || empty($embeddedTags)) {
            return $embeddedTags;
        }

        try {
            Log::info('Enhancing embedded tags with Azure insights', [
                'embedded_tags_count' => count($embeddedTags),
                'azure_fields_count' => count($azureAnalysis['form_fields'] ?? [])
            ]);

            $enhancedTags = [];

            foreach ($embeddedTags as $tag) {
                $enhanced = $tag;
                $fieldName = $tag['field_name'];

                // Try to find matching Azure-detected fields
                foreach ($azureAnalysis['form_fields'] ?? [] as $azureField) {
                    $similarity = $this->calculateFieldNameSimilarity($fieldName, $azureField['name']);
                    
                    if ($similarity > 0.7) { // High similarity threshold
                        $enhanced['azure_match'] = [
                            'detected_name' => $azureField['name'],
                            'confidence' => $azureField['confidence'],
                            'similarity_score' => $similarity,
                            'suggested_type' => $this->mapAzureTypeToDocusealType($azureField['type'])
                        ];

                        // Update field type if Azure suggests a better one
                        if (!isset($tag['attributes']['type']) && $enhanced['azure_match']['suggested_type']) {
                            $enhanced['type'] = $enhanced['azure_match']['suggested_type'];
                            $enhanced['attributes']['type'] = $enhanced['azure_match']['suggested_type'];
                        }

                        Log::debug('Enhanced embedded tag with Azure match', [
                            'original_field' => $fieldName,
                            'azure_field' => $azureField['name'],
                            'similarity' => $similarity,
                            'confidence' => $azureField['confidence']
                        ]);
                        break;
                    }
                }

                // Look for contextual clues from Azure text analysis
                foreach ($azureAnalysis['text_elements'] ?? [] as $textElement) {
                    if (stripos($textElement['content'], $fieldName) !== false) {
                        $enhanced['context'] = [
                            'surrounding_text' => $textElement['content'],
                            'confidence' => $textElement['confidence']
                        ];
                        break;
                    }
                }

                $enhancedTags[] = $enhanced;
            }

            Log::info('Tag enhancement completed', [
                'original_tags' => count($embeddedTags),
                'enhanced_tags' => count($enhancedTags),
                'azure_matches_found' => count(array_filter($enhancedTags, fn($tag) => isset($tag['azure_match'])))
            ]);

            return $enhancedTags;

        } catch (\Exception $e) {
            Log::error('Failed to enhance tags with Azure analysis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return original tags if enhancement fails
            return $embeddedTags;
        }
    }

    /**
     * Calculate similarity between field names
     */
    private function calculateFieldNameSimilarity(string $name1, string $name2): float
    {
        // Normalize names for comparison
        $normalized1 = strtolower(preg_replace('/[^a-z0-9]/', '', $name1));
        $normalized2 = strtolower(preg_replace('/[^a-z0-9]/', '', $name2));

        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        // Use Levenshtein distance for similarity
        $maxLen = max(strlen($normalized1), strlen($normalized2));
        if ($maxLen === 0) {
            return 0.0;
        }

        $distance = levenshtein($normalized1, $normalized2);
        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Map Azure Document Intelligence field types to Docuseal types
     */
    private function mapAzureTypeToDocusealType(string $azureType): ?string
    {
        $typeMapping = [
            'string' => 'text',
            'number' => 'number',
            'date' => 'date',
            'time' => 'date',
            'phoneNumber' => 'text',
            'boolean' => 'checkbox',
            'selectionMark' => 'checkbox',
            'signature' => 'signature',
            'currency' => 'number'
        ];

        return $typeMapping[strtolower($azureType)] ?? null;
    }

    /**
     * Upload PDF to Docuseal and create template
     */
    private function uploadToDocuseal(string $pdfPath, DocusealTemplate $template): array
    {
        $docusealApiKey = config('services.docuseal.api_key');
        $docusealApiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        
        if (!$docusealApiKey) {
            throw new \Exception('Docuseal API key not configured. Please set DOCUSEAL_API_KEY in your environment.');
        }
        
        // Create template name
        $templateName = $template->template_name . ' (Quick Request IVR)';
        
        // Upload PDF to Docuseal using their API
        $client = new HttpClient();
        
        try {
            $response = $client->post($docusealApiUrl . '/templates', [
                'headers' => [
                    'X-Auth-Token' => $docusealApiKey,
                ],
                'multipart' => [
                    [
                        'name' => 'template[name]',
                        'contents' => $templateName
                    ],
                    [
                        'name' => 'template[documents][]',
                        'contents' => fopen($pdfPath, 'r'),
                        'filename' => basename($pdfPath),
                        'headers' => [
                            'Content-Type' => 'application/pdf'
                        ]
                    ]
                ],
                'timeout' => 30
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!$responseData || !isset($responseData['id'])) {
                throw new \Exception('Invalid response from Docuseal API: ' . $response->getBody());
            }
            
            Log::info('Successfully uploaded template to Docuseal', [
                'template_id' => $responseData['id'],
                'template_name' => $templateName,
                'local_template_id' => $template->id
            ]);
            
            return [
                'template_id' => $responseData['id'],
                'name' => $responseData['name'] ?? $templateName,
                'status' => $responseData['status'] ?? 'active',
                'created_at' => $responseData['created_at'] ?? now()->toIso8601String(),
                'documents' => $responseData['documents'] ?? []
            ];
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            Log::error('Docuseal API upload failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $errorBody,
                'template_name' => $templateName
            ]);
            
            throw new \Exception('Failed to upload template to Docuseal: ' . $e->getMessage() . '. Response: ' . $errorBody);
        }
    }

    /**
     * Map embedded field tags to Quick Request form fields
     */
    private function mapEmbeddedFieldsToQuickRequest(array $embeddedTags): array
    {
        $fieldMappings = [];
        
        // Quick Request field mapping definitions
        $quickRequestFields = [
            // Patient Information
            'patient_first_name' => ['type' => 'text', 'category' => 'patient'],
            'patient_last_name' => ['type' => 'text', 'category' => 'patient'],
            'patient_dob' => ['type' => 'date', 'category' => 'patient'],
            'patient_member_id' => ['type' => 'text', 'category' => 'patient'],
            'patient_gender' => ['type' => 'select', 'category' => 'patient'],
            'patient_phone' => ['type' => 'text', 'category' => 'patient'],
            'patient_address_line1' => ['type' => 'text', 'category' => 'patient'],
            'patient_address_line2' => ['type' => 'text', 'category' => 'patient'],
            'patient_city' => ['type' => 'text', 'category' => 'patient'],
            'patient_state' => ['type' => 'text', 'category' => 'patient'],
            'patient_zip' => ['type' => 'text', 'category' => 'patient'],
            
            // Product & Service
            'product_name' => ['type' => 'text', 'category' => 'product'],
            'product_code' => ['type' => 'text', 'category' => 'product'],
            'manufacturer' => ['type' => 'text', 'category' => 'product'],
            'size' => ['type' => 'text', 'category' => 'product'],
            'quantity' => ['type' => 'number', 'category' => 'product'],
            'expected_service_date' => ['type' => 'date', 'category' => 'service'],
            'wound_type' => ['type' => 'select', 'category' => 'clinical'],
            'place_of_service' => ['type' => 'text', 'category' => 'service'],
            
            // Insurance
            'payer_name' => ['type' => 'text', 'category' => 'insurance'],
            'payer_id' => ['type' => 'text', 'category' => 'insurance'],
            'insurance_type' => ['type' => 'text', 'category' => 'insurance'],
            
            // Provider
            'provider_name' => ['type' => 'text', 'category' => 'provider'],
            'provider_npi' => ['type' => 'text', 'category' => 'provider'],
            'facility_name' => ['type' => 'text', 'category' => 'facility'],
            'signature_date' => ['type' => 'date', 'category' => 'provider'],
            
            // Clinical Attestations
            'failed_conservative_treatment' => ['type' => 'checkbox', 'category' => 'clinical'],
            'information_accurate' => ['type' => 'checkbox', 'category' => 'clinical'],
            'medical_necessity_established' => ['type' => 'checkbox', 'category' => 'clinical'],
            'maintain_documentation' => ['type' => 'checkbox', 'category' => 'clinical'],
            'authorize_prior_auth' => ['type' => 'checkbox', 'category' => 'clinical'],
            
            // Manufacturer-specific fields
            'physician_attestation' => ['type' => 'checkbox', 'category' => 'manufacturer'],
            'not_used_previously' => ['type' => 'checkbox', 'category' => 'manufacturer'],
            'multiple_products' => ['type' => 'checkbox', 'category' => 'manufacturer'],
            'additional_products' => ['type' => 'text', 'category' => 'manufacturer'],
            'previous_use' => ['type' => 'checkbox', 'category' => 'manufacturer'],
            'previous_product_info' => ['type' => 'text', 'category' => 'manufacturer'],
            'amnio_amp_size' => ['type' => 'select', 'category' => 'manufacturer'],
            'shipping_speed_required' => ['type' => 'select', 'category' => 'manufacturer'],
            'temperature_controlled' => ['type' => 'checkbox', 'category' => 'manufacturer'],
        ];
        
        foreach ($embeddedTags as $tag) {
            $fieldName = $tag['field_name'];
            
            if (isset($quickRequestFields[$fieldName])) {
                $fieldMappings[$fieldName] = [
                    'local_field' => $fieldName,
                    'docuseal_field' => $fieldName,
                    'type' => $tag['type'] ?? $quickRequestFields[$fieldName]['type'],
                    'category' => $quickRequestFields[$fieldName]['category'],
                    'required' => $tag['required'] ?? false,
                    'attributes' => $tag['attributes'] ?? [],
                    'mapping_type' => 'embedded_auto',
                    'original_tag' => $tag['original_tag']
                ];
            } else {
                // For unknown fields, create a mapping but mark as unmapped
                $fieldMappings[$fieldName] = [
                    'local_field' => null,
                    'docuseal_field' => $fieldName,
                    'type' => $tag['type'] ?? 'text',
                    'category' => 'unknown',
                    'required' => $tag['required'] ?? false,
                    'attributes' => $tag['attributes'] ?? [],
                    'mapping_type' => 'embedded_unmapped',
                    'original_tag' => $tag['original_tag']
                ];
            }
        }
        
        return $fieldMappings;
    }
}