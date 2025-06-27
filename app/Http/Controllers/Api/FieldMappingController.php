<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedFieldMappingService;
use App\Services\DocuSealService;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FieldMappingController extends Controller
{
    public function __construct(
        private UnifiedFieldMappingService $fieldMappingService,
        private DocuSealService $docuSealService
    ) {}

    /**
     * Map episode data to manufacturer template
     */
    public function mapEpisode(Request $request, int $episodeId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'manufacturer' => 'required|string',
            'additional_data' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->fieldMappingService->mapEpisodeToTemplate(
                $episodeId,
                $request->input('manufacturer'),
                $request->input('additional_data', [])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Field mapping failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $request->input('manufacturer'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Field mapping failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get manufacturer configuration
     */
    public function getManufacturerConfig(string $manufacturer): JsonResponse
    {
        try {
            $config = $this->fieldMappingService->getManufacturerConfig($manufacturer);

            if (!$config) {
                return response()->json([
                    'error' => 'Manufacturer not found',
                ], 404);
            }

            return response()->json([
                'manufacturer' => $config,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get manufacturer config',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all manufacturers
     */
    public function listManufacturers(): JsonResponse
    {
        try {
            $manufacturers = $this->fieldMappingService->listManufacturers();

            return response()->json([
                'manufacturers' => $manufacturers,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to list manufacturers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get field mapping analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'manufacturer' => 'string|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dateRange = null;
            if ($request->has('start_date') && $request->has('end_date')) {
                $dateRange = [
                    $request->input('start_date'),
                    $request->input('end_date'),
                ];
            }

            $analytics = $this->docuSealService->generateAnalytics(
                $request->input('manufacturer'),
                $dateRange
            );

            return response()->json($analytics);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get analytics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get field mapping logs for an episode
     */
    public function getEpisodeLogs(int $episodeId): JsonResponse
    {
        try {
            $logs = \DB::table('field_mapping_logs')
                ->where('episode_id', $episodeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'logs' => $logs,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate field mapping data
     */
    public function validateMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'manufacturer' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $manufacturer = $request->input('manufacturer');
            $data = $request->input('data');

            // Get manufacturer config
            $config = $this->fieldMappingService->getManufacturerConfig($manufacturer);
            if (!$config) {
                return response()->json([
                    'error' => 'Unknown manufacturer',
                ], 404);
            }

            // Validate against manufacturer requirements
            $errors = [];
            $warnings = [];

            foreach ($config['fields'] as $field => $fieldConfig) {
                if ($fieldConfig['required'] ?? false) {
                    if (empty($data[$field])) {
                        $errors[] = "Required field '$field' is missing";
                    }
                }
            }

            return response()->json([
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get field mapping patterns and suggestions
     */
    public function getFieldSuggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'manufacturer' => 'required|string',
            'field' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $manufacturer = $request->input('manufacturer');
            $field = $request->input('field');

            // Get common patterns from analytics
            $patterns = \DB::table('field_mapping_analytics')
                ->where('manufacturer_name', $manufacturer)
                ->where('field_name', $field)
                ->orderBy('usage_count', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'suggestions' => $patterns,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get suggestions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch map multiple episodes
     */
    public function batchMapEpisodes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_ids' => 'required|array',
            'episode_ids.*' => 'integer',
            'manufacturer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $episodeIds = $request->input('episode_ids');
            $manufacturer = $request->input('manufacturer');
            $results = [];

            foreach ($episodeIds as $episodeId) {
                try {
                    $result = $this->fieldMappingService->mapEpisodeToTemplate(
                        $episodeId,
                        $manufacturer
                    );
                    
                    $results[$episodeId] = [
                        'success' => true,
                        'completeness' => $result['completeness']['percentage'],
                        'validation' => $result['validation'],
                    ];
                } catch (\Exception $e) {
                    $results[$episodeId] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'results' => $results,
                'summary' => [
                    'total' => count($episodeIds),
                    'successful' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Batch mapping failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}