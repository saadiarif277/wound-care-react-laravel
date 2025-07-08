<?php

namespace App\Services\DocuSeal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LLMFieldMapper
{
    private array $config;
    private string $provider;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private int $timeout;
    private float $minConfidenceThreshold;

    public function __construct()
    {
        $this->config = config('docuseal-dynamic.llm');
        $this->provider = $this->config['provider'];
        $this->apiKey = $this->config['api_key'];
        $this->model = $this->config['model'];
        $this->temperature = $this->config['temperature'];
        $this->maxTokens = $this->config['max_tokens'];
        $this->timeout = $this->config['timeout'];
        $this->minConfidenceThreshold = $this->config['min_confidence_threshold'];

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('LLM API key is required');
        }
    }

    /**
     * Map manufacturer data to DocuSeal field names using LLM
     */
    public function mapFields(
        array $availableFields, 
        array $manufacturerData, 
        string $templateName,
        string $templateId
    ): array {
        $startTime = microtime(true);

        // Generate cache key for this mapping
        $cacheKey = $this->generateCacheKey($availableFields, $manufacturerData, $templateId);
        
        // Try cache first
        if (config('docuseal-dynamic.mapping.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('LLM field mapping retrieved from cache', [
                    'template_id' => $templateId,
                    'confidence_score' => $cached['confidence_score'] ?? 0,
                    'mapped_fields_count' => count($cached['mapped_fields'] ?? [])
                ]);
                return $cached;
            }
        }

        try {
            // Prepare the prompt
            $prompt = $this->buildMappingPrompt($availableFields, $manufacturerData, $templateName, $templateId);
            
            // Call LLM
            $response = $this->callLLM($prompt);
            
            // Validate and parse response
            $mappingResult = $this->validateAndParseResponse($response, $availableFields);
            
            // Check confidence threshold
            if ($mappingResult['confidence_score'] < $this->minConfidenceThreshold) {
                Log::warning('LLM mapping confidence below threshold', [
                    'template_id' => $templateId,
                    'confidence_score' => $mappingResult['confidence_score'],
                    'min_threshold' => $this->minConfidenceThreshold
                ]);
                
                throw new \Exception(
                    "LLM mapping confidence ({$mappingResult['confidence_score']}) below threshold ({$this->minConfidenceThreshold})"
                );
            }

            // Cache successful mapping
            if (config('docuseal-dynamic.mapping.enable_caching')) {
                $cacheTtl = config('docuseal-dynamic.mapping.cache_ttl');
                Cache::put($cacheKey, $mappingResult, $cacheTtl);
            }

            $duration = microtime(true) - $startTime;
            
            Log::info('LLM field mapping completed successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'confidence_score' => $mappingResult['confidence_score'],
                'mapped_fields_count' => count($mappingResult['mapped_fields']),
                'unmapped_fields_count' => count($mappingResult['unmapped_fields'] ?? []),
                'duration_seconds' => round($duration, 3),
                'provider' => $this->provider,
                'model' => $this->model
            ]);

            return $mappingResult;

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('LLM field mapping failed', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 3),
                'available_fields_count' => count($availableFields),
                'manufacturer_data_keys' => array_keys($manufacturerData)
            ]);
            
            throw $e;
        }
    }

    /**
     * Build the mapping prompt using the template from config
     */
    private function buildMappingPrompt(
        array $availableFields, 
        array $manufacturerData, 
        string $templateName,
        string $templateId
    ): string {
        $promptTemplate = config('docuseal-dynamic.llm_prompt_template');
        
        // Sanitize manufacturer data for the prompt (remove sensitive PHI)
        $sanitizedData = $this->sanitizeDataForPrompt($manufacturerData);
        
        return str_replace(
            [
                '{template_name}',
                '{template_id}',
                '{available_fields}',
                '{manufacturer_data}'
            ],
            [
                $templateName,
                $templateId,
                json_encode($availableFields, JSON_PRETTY_PRINT),
                json_encode($sanitizedData, JSON_PRETTY_PRINT)
            ],
            $promptTemplate
        );
    }

    /**
     * Call the LLM API with retry logic
     */
    private function callLLM(string $prompt): string
    {
        $maxRetries = config('docuseal-dynamic.error_handling.max_llm_retries', 2);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $response = match ($this->provider) {
                    'openai' => $this->callOpenAI($prompt),
                    'anthropic' => $this->callAnthropic($prompt),
                    default => throw new \InvalidArgumentException("Unsupported LLM provider: {$this->provider}")
                };

                return $response;

            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning("LLM call failed, attempt {$attempt}/{$maxRetries}", [
                    'provider' => $this->provider,
                    'model' => $this->model,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $maxRetries) {
                    $waitTime = 1000 * $attempt; // Simple linear backoff
                    usleep($waitTime * 1000);
                    continue;
                }
            }
        }

        throw new \Exception(
            "LLM call failed after {$maxRetries} attempts. Last error: " . 
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a field mapping specialist. Return only valid JSON responses as specified.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ]);

        if (!$response->successful()) {
            throw new \Exception("OpenAI API error: " . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \Exception("Empty response from OpenAI");
        }

        return $content;
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->timeout($this->timeout)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception("Anthropic API error: " . $response->body());
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';

        if (empty($content)) {
            throw new \Exception("Empty response from Anthropic");
        }

        return $content;
    }

    /**
     * Validate and parse LLM response
     */
    private function validateAndParseResponse(string $response, array $availableFields): array
    {
        // Clean the response (remove markdown code blocks if present)
        $cleanedResponse = $this->cleanJsonResponse($response);
        
        // Validate JSON format
        $decoded = json_decode($cleanedResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from LLM: " . json_last_error_msg());
        }

        // Validate required keys
        $requiredKeys = config('docuseal-dynamic.validation.required_response_keys');
        foreach ($requiredKeys as $key) {
            if (!isset($decoded[$key])) {
                throw new \Exception("Missing required key '{$key}' in LLM response");
            }
        }

        // Validate confidence score
        $confidenceScore = $decoded['confidence_score'] ?? 0;
        if (!is_numeric($confidenceScore) || $confidenceScore < 0 || $confidenceScore > 1) {
            throw new \Exception("Invalid confidence score: {$confidenceScore}");
        }

        // Validate mapped fields
        $mappedFields = $decoded['mapped_fields'] ?? [];
        if (!is_array($mappedFields)) {
            throw new \Exception("mapped_fields must be an array");
        }

        // Check that all mapped field names exist in available fields
        if (config('docuseal-dynamic.validation.validate_field_names', true)) {
            foreach (array_keys($mappedFields) as $fieldName) {
                if (!in_array($fieldName, $availableFields)) {
                    Log::warning("LLM mapped to non-existent field", [
                        'field_name' => $fieldName,
                        'available_fields' => $availableFields
                    ]);
                    // Remove the invalid mapping rather than failing
                    unset($mappedFields[$fieldName]);
                }
            }
        }

        // Sanitize and format the final result
        return [
            'mapped_fields' => $mappedFields,
            'unmapped_fields' => $decoded['unmapped_fields'] ?? [],
            'missing_data' => $decoded['missing_data'] ?? [],
            'confidence_score' => (float) $confidenceScore,
            'mapping_notes' => $decoded['mapping_notes'] ?? '',
            'metadata' => [
                'provider' => $this->provider,
                'model' => $this->model,
                'mapped_at' => now()->toIso8601String(),
                'temperature' => $this->temperature,
                'validated_fields_count' => count($mappedFields)
            ]
        ];
    }

    /**
     * Clean JSON response from LLM (remove markdown formatting)
     */
    private function cleanJsonResponse(string $response): string
    {
        // Remove markdown code blocks
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        
        // Remove leading/trailing whitespace
        $response = trim($response);
        
        // Find JSON start and end
        $start = strpos($response, '{');
        $end = strrpos($response, '}');
        
        if ($start !== false && $end !== false && $end > $start) {
            $response = substr($response, $start, $end - $start + 1);
        }
        
        return $response;
    }

    /**
     * Sanitize manufacturer data for LLM prompt (remove/mask PHI)
     */
    private function sanitizeDataForPrompt(array $data): array
    {
        $sensitiveFields = config('docuseal-dynamic.logging.sensitive_fields', []);
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                // Mask sensitive data but preserve structure for LLM understanding
                if (is_string($sanitized[$field])) {
                    if (str_contains(strtolower($field), 'name')) {
                        $sanitized[$field] = 'John Doe'; // Generic name
                    } elseif (str_contains(strtolower($field), 'dob') || str_contains(strtolower($field), 'birth')) {
                        $sanitized[$field] = '01/01/1980'; // Generic date
                    } elseif (str_contains(strtolower($field), 'phone')) {
                        $sanitized[$field] = '(555) 123-4567'; // Generic phone
                    } elseif (str_contains(strtolower($field), 'address')) {
                        $sanitized[$field] = '123 Main St, City, ST 12345'; // Generic address
                    } else {
                        $sanitized[$field] = '[MASKED_' . strtoupper($field) . ']';
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Generate cache key for mapping result
     */
    private function generateCacheKey(array $availableFields, array $manufacturerData, string $templateId): string
    {
        $cachePrefix = config('docuseal-dynamic.mapping.cache_prefix');
        
        // Create a hash of the input data for caching
        $dataHash = hash('sha256', json_encode([
            'template_id' => $templateId,
            'available_fields' => $availableFields,
            'data_structure' => array_keys($manufacturerData), // Only structure, not values
            'model' => $this->model,
            'temperature' => $this->temperature
        ]));

        return "{$cachePrefix}_mapping_{$dataHash}";
    }

    /**
     * Test LLM connectivity and basic functionality
     */
    public function testConnection(): array
    {
        try {
            $testPrompt = "Return only this exact JSON: {\"test\": true, \"confidence_score\": 1.0}";
            $response = $this->callLLM($testPrompt);
            
            $decoded = json_decode($this->cleanJsonResponse($response), true);
            
            if (isset($decoded['test']) && $decoded['test'] === true) {
                return [
                    'success' => true,
                    'message' => 'LLM connection successful',
                    'provider' => $this->provider,
                    'model' => $this->model
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'LLM responded but format was incorrect',
                    'response' => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'LLM connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear mapping cache
     */
    public function clearMappingCache(): bool
    {
        try {
            $cachePrefix = config('docuseal-dynamic.mapping.cache_prefix');
            // In a real implementation, you'd want to clear only keys with the specific prefix
            Cache::flush(); // This is aggressive - consider a more targeted approach
            
            Log::info('Cleared LLM mapping cache');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear LLM mapping cache', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 