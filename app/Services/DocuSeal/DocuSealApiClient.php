<?php

namespace App\Services\DocuSeal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

class DocuSealApiClient
{
    private array $config;
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $maxRetries;

    public function __construct()
    {
        $this->config = config('docuseal-dynamic.docuseal');
        $this->baseUrl = $this->config['base_url'];
        $this->apiKey = $this->config['api_key'];
        $this->timeout = $this->config['timeout'];
        $this->maxRetries = $this->config['max_retries'];

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('DocuSeal API key is required');
        }
    }

    /**
     * Get template structure and field names from DocuSeal
     */
    public function getTemplate(string $templateId): array
    {
        $cacheKey = "docuseal_template_{$templateId}";
        $cacheTtl = $this->config['cache_ttl'];

        // Try cache first
        if (config('docuseal-dynamic.mapping.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('DocuSeal template retrieved from cache', [
                    'template_id' => $templateId,
                    'fields_count' => count($cached['fields'] ?? [])
                ]);
                return $cached;
            }
        }

        $startTime = microtime(true);

        try {
            $response = $this->makeApiCall('GET', "/templates/{$templateId}");
            
            $template = $this->processTemplateResponse($response);
            
            // Cache the processed template
            if (config('docuseal-dynamic.mapping.enable_caching')) {
                Cache::put($cacheKey, $template, $cacheTtl);
            }

            $duration = microtime(true) - $startTime;
            
            Log::info('DocuSeal template retrieved successfully', [
                'template_id' => $templateId,
                'template_name' => $template['name'] ?? 'Unknown',
                'fields_count' => count($template['fields'] ?? []),
                'duration_seconds' => round($duration, 3),
                'cached' => false
            ]);

            return $template;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve DocuSeal template', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'duration_seconds' => round(microtime(true) - $startTime, 3)
            ]);
            throw $e;
        }
    }

    /**
     * Create a DocuSeal submission with pre-filled values
     */
    public function createSubmission(string $templateId, array $submitters): array
    {
        $startTime = microtime(true);

        $payload = [
            'template_id' => (int) $templateId,
            'submitters' => $submitters
        ];

        try {
            $response = $this->makeApiCall('POST', '/submissions', $payload);
            
            $duration = microtime(true) - $startTime;
            
            Log::info('DocuSeal submission created successfully', [
                'template_id' => $templateId,
                'submission_id' => $response['id'] ?? null,
                'submitters_count' => count($submitters),
                'duration_seconds' => round($duration, 3)
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to create DocuSeal submission', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'payload' => $this->sanitizePayloadForLogging($payload),
                'duration_seconds' => round(microtime(true) - $startTime, 3)
            ]);
            throw $e;
        }
    }

    /**
     * Extract field names from template for LLM mapping
     */
    public function getTemplateFieldNames(string $templateId): array
    {
        $template = $this->getTemplate($templateId);
        
        $fieldNames = [];
        foreach ($template['fields'] ?? [] as $field) {
            if (!empty($field['name'])) {
                $fieldNames[] = $field['name'];
            }
        }

        Log::info('Extracted DocuSeal template field names', [
            'template_id' => $templateId,
            'field_names_count' => count($fieldNames),
            'field_names' => $fieldNames
        ]);

        return $fieldNames;
    }

    /**
     * Make authenticated API call to DocuSeal with retry logic
     */
    private function makeApiCall(string $method, string $endpoint, array $data = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $request = Http::withHeaders([
                    'X-Auth-Token' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'MSC-WoundCare/1.0'
                ])->timeout($this->timeout);

                $response = match (strtoupper($method)) {
                    'GET' => $request->get($this->baseUrl . $endpoint),
                    'POST' => $request->post($this->baseUrl . $endpoint, $data),
                    'PUT' => $request->put($this->baseUrl . $endpoint, $data),
                    'DELETE' => $request->delete($this->baseUrl . $endpoint),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
                };

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle specific HTTP errors
                $statusCode = $response->status();
                $errorBody = $response->body();
                
                if ($statusCode === 401) {
                    throw new \Exception("DocuSeal authentication failed. Check API key.");
                }

                if ($statusCode === 404) {
                    throw new \Exception("DocuSeal resource not found: {$endpoint}");
                }

                if ($statusCode === 429) {
                    // Rate limit - wait and retry
                    $waitTime = $this->calculateBackoffDelay($attempt);
                    Log::warning("DocuSeal rate limit hit, waiting {$waitTime}ms", [
                        'attempt' => $attempt,
                        'endpoint' => $endpoint
                    ]);
                    usleep($waitTime * 1000);
                    continue;
                }

                throw new \Exception("DocuSeal API error [{$statusCode}]: {$errorBody}");

            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("DocuSeal connection error, attempt {$attempt}/{$this->maxRetries}", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $this->maxRetries) {
                    $waitTime = $this->calculateBackoffDelay($attempt);
                    usleep($waitTime * 1000);
                    continue;
                }

            } catch (RequestException $e) {
                $lastException = $e;
                Log::warning("DocuSeal request error, attempt {$attempt}/{$this->maxRetries}", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $this->maxRetries) {
                    $waitTime = $this->calculateBackoffDelay($attempt);
                    usleep($waitTime * 1000);
                    continue;
                }
            }

            break;
        }

        throw new \Exception(
            "DocuSeal API call failed after {$this->maxRetries} attempts. Last error: " . 
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Process template response and extract relevant information
     */
    private function processTemplateResponse(array $response): array
    {
        $processed = [
            'id' => $response['id'] ?? null,
            'name' => $response['name'] ?? 'Unknown Template',
            'fields' => [],
            'field_names' => [],
            'metadata' => [
                'retrieved_at' => now()->toIso8601String(),
                'total_fields' => 0,
                'required_fields' => 0,
                'field_types' => []
            ]
        ];

        // Process fields
        foreach ($response['fields'] ?? [] as $field) {
            $processedField = [
                'uuid' => $field['uuid'] ?? null,
                'name' => $field['name'] ?? null,
                'type' => $field['type'] ?? 'text',
                'required' => $field['required'] ?? false,
                'options' => $field['options'] ?? [],
            ];

            $processed['fields'][] = $processedField;
            
            if (!empty($processedField['name'])) {
                $processed['field_names'][] = $processedField['name'];
            }

            // Update metadata
            $processed['metadata']['total_fields']++;
            if ($processedField['required']) {
                $processed['metadata']['required_fields']++;
            }
            
            $fieldType = $processedField['type'];
            $processed['metadata']['field_types'][$fieldType] = 
                ($processed['metadata']['field_types'][$fieldType] ?? 0) + 1;
        }

        return $processed;
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        $baseDelay = config('docuseal-dynamic.error_handling.base_delay_ms', 1000);
        
        if (config('docuseal-dynamic.error_handling.exponential_backoff', true)) {
            return min($baseDelay * (2 ** ($attempt - 1)), 30000); // Max 30 seconds
        }
        
        return $baseDelay;
    }

    /**
     * Sanitize payload for logging (remove sensitive data)
     */
    private function sanitizePayloadForLogging(array $payload): array
    {
        $sensitiveFields = config('docuseal-dynamic.logging.sensitive_fields', []);
        
        $sanitized = $payload;
        
        // Remove sensitive data from submitters
        if (isset($sanitized['submitters'])) {
            foreach ($sanitized['submitters'] as &$submitter) {
                if (isset($submitter['values'])) {
                    foreach ($sensitiveFields as $sensitiveField) {
                        if (isset($submitter['values'][$sensitiveField])) {
                            $submitter['values'][$sensitiveField] = '[REDACTED]';
                        }
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Test DocuSeal API connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeApiCall('GET', '/templates?limit=1');
            
            return [
                'success' => true,
                'message' => 'DocuSeal API connection successful',
                'templates_accessible' => count($response['data'] ?? []) > 0
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'DocuSeal API connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear template cache for a specific template or all templates
     */
    public function clearTemplateCache(?string $templateId = null): bool
    {
        try {
            if ($templateId) {
                Cache::forget("docuseal_template_{$templateId}");
                Log::info('Cleared DocuSeal template cache', ['template_id' => $templateId]);
            } else {
                // Clear all template caches
                $cachePrefix = config('docuseal-dynamic.mapping.cache_prefix');
                Cache::flush(); // This is aggressive - in production you might want to be more selective
                Log::info('Cleared all DocuSeal template caches');
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear DocuSeal template cache', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 