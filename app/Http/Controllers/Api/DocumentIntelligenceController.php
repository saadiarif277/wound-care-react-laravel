<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentIntelligenceService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\CanonicalField;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DocumentIntelligenceController extends Controller
{
    protected DocumentIntelligenceService $documentIntelligence;
    
    public function __construct(DocumentIntelligenceService $documentIntelligence)
    {
        $this->documentIntelligence = $documentIntelligence;
    }
    
    /**
     * Analyze a template document to extract field structure
     * This helps admins understand what fields need to be mapped
     */
    public function analyzeTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:10240', // 10MB max
            'template_id' => 'nullable|uuid' // Remove exists check as it's causing issues
        ]);
        
        try {
            // Get the uploaded file
            $file = $request->file('file');
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }
            
            // Analyze the document
            $analysis = $this->documentIntelligence->analyzeTemplateStructure($file);
            
            if (!$analysis['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $analysis['error'] ?? 'Analysis failed'
                ], 400);
            }
            
            // Get canonical fields for mapping suggestions
            $canonicalFields = CanonicalField::all()->groupBy('category')->map(function ($fields) {
                return $fields->toArray();
            })->toArray();
            
            // Get AI-powered mapping suggestions
            $suggestions = $this->documentIntelligence->suggestFieldMappings(
                $analysis['fields'],
                CanonicalField::all()->toArray()
            );
            
            // Format response for frontend
            $response = [
                'success' => true,
                'document_info' => $analysis['metadata'],
                'detected_fields' => array_map(function ($field) use ($suggestions) {
                    return [
                        'name' => $field['name'],
                        'display_name' => $field['display_name'],
                        'type' => $field['type'],
                        'required' => $field['required'],
                        'confidence' => round($field['confidence'] * 100, 1),
                        'suggestions' => $suggestions[$field['name']] ?? [],
                        'location' => $field['location'] ?? null
                    ];
                }, $analysis['fields']),
                'canonical_fields' => $canonicalFields,
                'summary' => [
                    'total_fields' => count($analysis['fields']),
                    'required_fields' => count(array_filter($analysis['fields'], fn($f) => $f['required'])),
                    'high_confidence_fields' => count(array_filter($analysis['fields'], fn($f) => $f['confidence'] > 0.8))
                ],
                'message' => $analysis['metadata']['message'] ?? null
            ];
            
            // Save analysis to template if template_id provided
            if ($request->template_id) {
                $template = DocusealTemplate::find($request->template_id);
                if ($template) {
                    $template->ai_analysis = $response;
                    $template->save();
                }
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Template analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Return more detailed error in development
            $message = 'Failed to analyze template. Please try again.';
            if (config('app.debug')) {
                $message .= ' Error: ' . $e->getMessage();
            }
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'debug' => config('app.debug') ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }
    
    /**
     * Extract data from a filled form
     * This helps with testing mappings and pre-filling forms
     */
    public function extractFormData(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:10240',
            'template_id' => 'nullable|uuid',
            'expected_fields' => 'nullable|array'
        ]);
        
        try {
            // Get template structure if available
            $templateStructure = [];
            if ($request->template_id) {
                $template = DocusealTemplate::find($request->template_id);
                $templateStructure = $template->ai_analysis['detected_fields'] ?? [];
            }
            
            // Extract data from the filled form
            $extraction = $this->documentIntelligence->extractFilledFormData(
                $request->file('file'),
                $templateStructure
            );
            
            if (!$extraction['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $extraction['error']
                ], 400);
            }
            
            // Format extracted data
            $formattedData = [];
            foreach ($extraction['data'] as $fieldName => $fieldData) {
                $formattedData[] = [
                    'field_name' => $fieldName,
                    'value' => $fieldData['value'],
                    'confidence' => round($fieldData['confidence'] * 100, 1),
                    'source' => $fieldData['source'] ?? 'ai_extraction'
                ];
            }
            
            return response()->json([
                'success' => true,
                'extracted_data' => $formattedData,
                'coverage' => round($extraction['coverage'] ?? 0, 1),
                'summary' => [
                    'total_fields_extracted' => count($formattedData),
                    'high_confidence_extractions' => count(array_filter($formattedData, fn($f) => $f['confidence'] > 80))
                ],
                'message' => $extraction['message'] ?? null,
                'detected_fields_count' => $extraction['detected_fields_count'] ?? 0,
                'matched_fields_count' => $extraction['matched_fields_count'] ?? 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Form data extraction failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract form data. Please ensure the document is clear and readable.'
            ], 500);
        }
    }
    
    /**
     * Get AI-powered field mapping suggestions
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'field_name' => 'required|string',
            'field_context' => 'nullable|string',
            'template_type' => 'nullable|string'
        ]);
        
        try {
            // Get all canonical fields
            $canonicalFields = CanonicalField::all()->toArray();
            
            // Get suggestions for a single field
            $suggestions = $this->documentIntelligence->suggestFieldMappings(
                [[
                    'name' => $request->field_name,
                    'context' => $request->field_context ?? ''
                ]],
                $canonicalFields
            );
            
            $fieldSuggestions = $suggestions[$request->field_name] ?? [];
            
            // Format suggestions with explanations
            $formattedSuggestions = array_map(function ($suggestion) {
                $field = $suggestion['canonical_field'];
                return [
                    'canonical_field_id' => $field['id'],
                    'category' => $field['category'],
                    'field_name' => $field['field_name'],
                    'display_name' => $field['display_name'] ?? $field['field_name'],
                    'confidence' => round($suggestion['confidence'] * 100, 1),
                    'reason' => $suggestion['reason'],
                    'data_type' => $field['data_type'],
                    'is_required' => $field['is_required']
                ];
            }, $fieldSuggestions);
            
            return response()->json([
                'success' => true,
                'suggestions' => $formattedSuggestions
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get AI suggestions', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate suggestions'
            ], 500);
        }
    }
    
    /**
     * Batch analyze multiple fields for mapping
     */
    public function batchAnalyze(Request $request): JsonResponse
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*.name' => 'required|string',
            'fields.*.context' => 'nullable|string'
        ]);
        
        try {
            $canonicalFields = CanonicalField::all()->toArray();
            
            // Get suggestions for all fields
            $allSuggestions = $this->documentIntelligence->suggestFieldMappings(
                $request->fields,
                $canonicalFields
            );
            
            // Calculate mapping quality metrics
            $totalFields = count($request->fields);
            $highConfidenceMatches = 0;
            $mediumConfidenceMatches = 0;
            $lowConfidenceMatches = 0;
            $noMatches = 0;
            
            foreach ($allSuggestions as $fieldName => $suggestions) {
                if (empty($suggestions)) {
                    $noMatches++;
                } else {
                    $topConfidence = $suggestions[0]['confidence'];
                    if ($topConfidence > 0.8) {
                        $highConfidenceMatches++;
                    } elseif ($topConfidence > 0.5) {
                        $mediumConfidenceMatches++;
                    } else {
                        $lowConfidenceMatches++;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'suggestions' => $allSuggestions,
                'quality_metrics' => [
                    'total_fields' => $totalFields,
                    'high_confidence' => $highConfidenceMatches,
                    'medium_confidence' => $mediumConfidenceMatches,
                    'low_confidence' => $lowConfidenceMatches,
                    'no_matches' => $noMatches,
                    'overall_quality' => $this->calculateOverallQuality($highConfidenceMatches, $mediumConfidenceMatches, $totalFields)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Batch analysis failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze fields'
            ], 500);
        }
    }
    
    /**
     * Test field mappings with sample data
     */
    public function testMappings(Request $request): JsonResponse
    {
        $request->validate([
            'mappings' => 'required|array',
            'sample_file' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240'
        ]);
        
        try {
            $results = [];
            
            if ($request->hasFile('sample_file')) {
                // Extract data from sample file
                $extraction = $this->documentIntelligence->extractFilledFormData(
                    $request->file('sample_file'),
                    []
                );
                
                // Test each mapping
                foreach ($request->mappings as $mapping) {
                    $fieldName = $mapping['field_name'];
                    $extractedValue = $extraction['data'][$fieldName]['value'] ?? null;
                    
                    $results[] = [
                        'field_name' => $fieldName,
                        'mapped_to' => $mapping['canonical_field_name'] ?? 'Not mapped',
                        'extracted_value' => $extractedValue,
                        'extraction_success' => !is_null($extractedValue),
                        'confidence' => $extraction['data'][$fieldName]['confidence'] ?? 0
                    ];
                }
            } else {
                // Generate test results without sample file
                foreach ($request->mappings as $mapping) {
                    $results[] = [
                        'field_name' => $mapping['field_name'],
                        'mapped_to' => $mapping['canonical_field_name'] ?? 'Not mapped',
                        'test_status' => 'Ready for testing',
                        'recommendation' => 'Upload a sample filled form to test extraction'
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'test_results' => $results,
                'summary' => [
                    'total_mappings' => count($results),
                    'successful_extractions' => count(array_filter($results, fn($r) => $r['extraction_success'] ?? false))
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Mapping test failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to test mappings'
            ], 500);
        }
    }
    
    /**
     * Calculate overall mapping quality
     */
    protected function calculateOverallQuality(int $high, int $medium, int $total): string
    {
        if ($total === 0) {
            return 'Unknown';
        }
        
        $score = (($high * 1.0) + ($medium * 0.5)) / $total;
        
        if ($score > 0.8) {
            return 'Excellent';
        } elseif ($score > 0.6) {
            return 'Good';
        } elseif ($score > 0.4) {
            return 'Fair';
        } else {
            return 'Needs Improvement';
        }
    }
}