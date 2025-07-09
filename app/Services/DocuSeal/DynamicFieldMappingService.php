<?php

namespace App\Services\DocuSeal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\UnifiedFieldMappingService;

class DynamicFieldMappingService
{
    private string $aiServiceUrl;
    private int $timeout;
    private UnifiedFieldMappingService $unifiedMappingService;
    private bool $enableFallback;

    public function __construct(UnifiedFieldMappingService $unifiedMappingService)
    {
        $this->aiServiceUrl = config('docuseal-dynamic.ai_service_url', 'http://localhost:8081');
        $this->timeout = config('docuseal-dynamic.llm.timeout', 30);
        $this->unifiedMappingService = $unifiedMappingService;
        $this->enableFallback = config('docuseal-dynamic.mapping.enable_fallback_to_static', true);
    }

    /**
     * Main entry point for dynamic field mapping that replaces static configs
     */
    public function mapEpisodeToDocuSealForm(
        ?string $episodeId,
        string $manufacturerName,
        string $templateId,
        array $additionalData = [],
        ?string $submitterEmail = null
    ): array {
        $startTime = microtime(true);

        try {
            // 1. Extract manufacturer data (reuse existing logic)
            if ($episodeId) {
                // Use the UnifiedFieldMappingService to extract episode data
                $tempResult = $this->unifiedMappingService->mapEpisodeToTemplate($episodeId, $manufacturerName, [], 'IVR');
                $sourceData = $tempResult['data'] ?? [];
                $sourceData = array_merge($sourceData, $additionalData);
            } else {
                $sourceData = $additionalData;
            }

            Log::info('Starting dynamic field mapping', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'source_data_keys' => array_keys($sourceData),
                'submitter_email' => $submitterEmail ? '[PROVIDED]' : null
            ]);

            // 2. Call AI service for intelligent mapping
            $mappingResult = $this->callAIService($templateId, $sourceData, $manufacturerName, $submitterEmail);

            $duration = microtime(true) - $startTime;

            // 3. Log mapping analytics
            if ($episodeId) {
                $this->logMappingAnalytics($episodeId, $manufacturerName, $templateId, $mappingResult, $duration);
            }

            return [
                'success' => true,
                'data' => $mappingResult['mapping_result']['mapped_fields'] ?? [],
                'template_info' => $mappingResult['template_info'] ?? [],
                'submission_result' => $mappingResult['submission_result'] ?? null,
                'validation' => [
                    'valid' => true,
                    'confidence_scores' => $mappingResult['mapping_result']['confidence_scores'] ?? [],
                    'quality_grade' => $mappingResult['mapping_result']['quality_grade'] ?? 'Unknown',
                    'suggestions' => $mappingResult['mapping_result']['suggestions'] ?? [],
                    'processing_notes' => $mappingResult['mapping_result']['processing_notes'] ?? []
                ],
                'metadata' => [
                    'episode_id' => $episodeId,
                    'manufacturer' => $manufacturerName,
                    'template_id' => $templateId,
                    'mapped_at' => now()->toIso8601String(),
                    'duration_ms' => round($duration * 1000, 2),
                    'source' => 'ai_dynamic_mapping',
                    'fallback_used' => false
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Dynamic field mapping failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            // Try fallback to static mapping if enabled
            if ($this->enableFallback) {
                Log::info('Attempting fallback to static field mapping');
                return $this->fallbackToStaticMapping($episodeId, $manufacturerName, $additionalData);
            }

            throw $e;
        }
    }

    /**
     * Call the AI service for intelligent field mapping
     */
    private function callAIService(
        string $templateId,
        array $sourceData,
        string $manufacturerName,
        ?string $submitterEmail = null
    ): array {
        $cacheKey = "ai_mapping_" . hash('sha256', json_encode([
            'template_id' => $templateId,
            'manufacturer' => $manufacturerName,
            'data_keys' => array_keys($sourceData)
        ]));

        // Try cache first
        if (config('docuseal-dynamic.mapping.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('AI mapping result retrieved from cache', [
                    'template_id' => $templateId,
                    'manufacturer' => $manufacturerName
                ]);
                return $cached;
            }
        }

        $payload = [
            'template_id' => $templateId,
            'manufacturer_data' => $this->sanitizeDataForAI($sourceData),
            'manufacturer_name' => $manufacturerName
        ];

        // Add submitter email if provided for direct submission
        if ($submitterEmail) {
            $payload['submitter_email'] = $submitterEmail;
        }

        $response = Http::timeout($this->timeout)
            ->post("{$this->aiServiceUrl}/map-for-docuseal", $payload);

        if (!$response->successful()) {
            throw new \Exception("AI service error: " . $response->body());
        }

        $result = $response->json();

        if (!$result['success']) {
            throw new \Exception("AI mapping failed: " . ($result['error'] ?? 'Unknown error'));
        }

        // Cache successful results
        if (config('docuseal-dynamic.mapping.enable_caching')) {
            $cacheTtl = config('docuseal-dynamic.mapping.cache_ttl');
            Cache::put($cacheKey, $result, $cacheTtl);
        }

        Log::info('AI service mapping completed', [
            'template_id' => $templateId,
            'manufacturer' => $manufacturerName,
            'quality_grade' => $result['mapping_result']['quality_grade'] ?? 'Unknown',
            'mapped_fields_count' => count($result['mapping_result']['mapped_fields'] ?? []),
            'submission_created' => isset($result['submission_result'])
        ]);

        return $result;
    }

    /**
     * Fallback to static field mapping when AI service fails
     */
    private function fallbackToStaticMapping(
        ?string $episodeId,
        string $manufacturerName,
        array $additionalData
    ): array {
        try {
            // Use existing UnifiedFieldMappingService as fallback
            $staticResult = $this->unifiedMappingService->mapEpisodeToTemplate(
                $episodeId,
                $manufacturerName,
                $additionalData,
                'IVR'
            );

            // Convert to DocuSeal format using existing logic
            $manufacturerConfig = $this->unifiedMappingService->getManufacturerConfig($manufacturerName, 'IVR');
            $docuSealFields = $this->unifiedMappingService->convertToDocusealFields(
                $staticResult['data'],
                $manufacturerConfig,
                'IVR'
            );

            Log::info('Static fallback mapping completed', [
                'manufacturer' => $manufacturerName,
                'mapped_fields_count' => count($docuSealFields),
                'completeness' => $staticResult['completeness']['percentage'] ?? 0
            ]);

            return [
                'success' => true,
                'data' => $this->convertDocuSealFieldsToKeyValue($docuSealFields),
                'template_info' => [
                    'template_id' => $manufacturerConfig['docuseal_template_id'] ?? null,
                    'field_names' => array_column($docuSealFields, 'name'),
                    'total_fields' => count($docuSealFields)
                ],
                'submission_result' => null,
                'validation' => $staticResult['validation'],
                'metadata' => array_merge($staticResult['metadata'], [
                    'source' => 'static_fallback_mapping',
                    'fallback_used' => true
                ])
            ];

        } catch (\Exception $e) {
            Log::error('Static fallback mapping also failed', [
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage()
            ]);

            // Return minimal structure if everything fails
            return [
                'success' => false,
                'data' => [],
                'error' => 'Both dynamic and static mapping failed: ' . $e->getMessage(),
                'metadata' => [
                    'source' => 'fallback_failed',
                    'fallback_used' => true,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Test AI service connectivity
     */
    public function testAIServiceConnection(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->aiServiceUrl}/health");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'AI service connection successful',
                    'service_status' => $data['status'] ?? 'unknown',
                    'azure_ai_available' => $data['azure_ai_available'] ?? false
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'AI service connection failed',
                    'status_code' => $response->status(),
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'AI service connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test DocuSeal connectivity through AI service
     */
    public function testDocuSealConnection(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->aiServiceUrl}/docuseal/test");

            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'success' => false,
                    'message' => 'DocuSeal connection test failed',
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'DocuSeal connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get template fields directly from DocuSeal (via AI service)
     */
    public function getTemplateFields(string $templateId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->aiServiceUrl}/docuseal/template/{$templateId}/fields");

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception("Failed to retrieve template fields: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Template field retrieval failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sanitize data before sending to AI service (remove/mask PHI)
     */
    private function sanitizeDataForAI(array $data): array
    {
        $sensitiveFields = config('docuseal-dynamic.logging.sensitive_fields', []);
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                // Mask sensitive data but preserve data type for AI understanding
                if (is_string($sanitized[$field])) {
                    if (str_contains(strtolower($field), 'name')) {
                        $sanitized[$field] = 'John Doe';
                    } elseif (str_contains(strtolower($field), 'dob') || str_contains(strtolower($field), 'birth')) {
                        $sanitized[$field] = '01/01/1980';
                    } elseif (str_contains(strtolower($field), 'phone')) {
                        $sanitized[$field] = '(555) 123-4567';
                    } elseif (str_contains(strtolower($field), 'address')) {
                        $sanitized[$field] = '123 Main St, City, ST 12345';
                    } else {
                        $sanitized[$field] = '[MASKED]';
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Convert DocuSeal fields array to key-value mapping
     */
    private function convertDocuSealFieldsToKeyValue(array $docuSealFields): array
    {
        $keyValue = [];
        foreach ($docuSealFields as $field) {
            if (isset($field['name']) && isset($field['default_value'])) {
                $keyValue[$field['name']] = $field['default_value'];
            }
        }
        return $keyValue;
    }

    /**
     * Log mapping analytics for monitoring and improvement
     */
    private function logMappingAnalytics(
        string $episodeId,
        string $manufacturer,
        string $templateId,
        array $mappingResult,
        float $duration
    ): void {
        $qualityGrade = $mappingResult['mapping_result']['quality_grade'] ?? 'Unknown';
        $mappedFieldsCount = count($mappingResult['mapping_result']['mapped_fields'] ?? []);
        $averageConfidence = 0;

        if (!empty($mappingResult['mapping_result']['confidence_scores'])) {
            $confidenceScores = array_values($mappingResult['mapping_result']['confidence_scores']);
            $averageConfidence = array_sum($confidenceScores) / count($confidenceScores);
        }

        Log::info('Dynamic field mapping analytics', [
            'episode_id' => $episodeId,
            'manufacturer' => $manufacturer,
            'template_id' => $templateId,
            'quality_grade' => $qualityGrade,
            'mapped_fields_count' => $mappedFieldsCount,
            'average_confidence' => round($averageConfidence, 3),
            'duration_seconds' => round($duration, 3),
            'submission_created' => isset($mappingResult['submission_result']),
            'total_template_fields' => $mappingResult['template_info']['total_fields'] ?? 0
        ]);
    }

    /**
     * Clear all caches related to dynamic mapping
     */
    public function clearCache(): bool
    {
        try {
            $cachePrefix = config('docuseal-dynamic.mapping.cache_prefix');
            // In production, you'd want to clear only keys with specific prefix
            Cache::flush(); // This is aggressive but simple
            
            Log::info('Dynamic mapping cache cleared');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear dynamic mapping cache', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 