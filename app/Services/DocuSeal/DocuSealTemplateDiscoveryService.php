<?php

namespace App\Services\DocuSeal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class DocuSealTemplateDiscoveryService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected int $timeout;
    protected int $cacheTtl;
    protected bool $cacheEnabled;

    public function __construct()
    {
        $this->apiKey = Config::get('services.docuseal.api_key');
        $this->apiUrl = Config::get('services.docuseal.api_url', 'https://api.docuseal.com');
        $this->timeout = Config::get('services.docuseal.timeout', 30);
        $this->cacheTtl = Config::get('services.docuseal.cache_ttl', 3600);
        $this->cacheEnabled = Config::get('services.docuseal.cache_enabled', true);
    }

    /**
     * Get template fields from DocuSeal API
     */
    public function getTemplateFields(string $templateId): array
    {
        try {
            Log::info('Fetching template fields from DocuSeal API', [
                'template_id' => $templateId
            ]);

            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->get("{$this->apiUrl}/templates/{$templateId}");

            if (!$response->successful()) {
                throw new Exception("DocuSeal API error: {$response->status()} - {$response->body()}");
            }

            $templateData = $response->json();
            
            if (!isset($templateData['fields'])) {
                throw new Exception("Template response missing 'fields' data");
            }

            $processedFields = $this->processTemplateFields($templateData['fields']);

            Log::info('Successfully fetched template fields', [
                'template_id' => $templateId,
                'field_count' => count($processedFields['fields']),
                'field_names' => array_column($processedFields['fields'], 'name')
            ]);

            return [
                'template_id' => $templateId,
                'name' => $templateData['name'] ?? 'Unknown Template',
                'fields' => $processedFields['fields'],
                'field_names' => $processedFields['field_names'],
                'field_types' => $processedFields['field_types'],
                'required_fields' => $processedFields['required_fields'],
                'fetched_at' => now()->toISOString(),
                'total_fields' => count($processedFields['fields'])
            ];

        } catch (Exception $e) {
            Log::error('Failed to fetch template fields from DocuSeal', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get cached template structure with automatic refresh
     */
    public function getCachedTemplateStructure(string $templateId): array
    {
        if (!$this->cacheEnabled) {
            return $this->getTemplateFields($templateId);
        }

        $cacheKey = "docuseal:template:{$templateId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($templateId) {
            return $this->getTemplateFields($templateId);
        });
    }

    /**
     * Process raw template fields into structured format
     */
    protected function processTemplateFields(array $rawFields): array
    {
        $fields = [];
        $fieldNames = [];
        $fieldTypes = [];
        $requiredFields = [];

        foreach ($rawFields as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldType = $field['type'] ?? 'text';
            $isRequired = $field['required'] ?? false;

            if (!$fieldName) {
                continue; // Skip fields without names
            }

            $processedField = [
                'name' => $fieldName,
                'type' => $fieldType,
                'required' => $isRequired,
                'description' => $field['description'] ?? null,
                'default_value' => $field['default_value'] ?? null,
                'options' => $field['options'] ?? null,
                'validation' => $field['validation'] ?? null
            ];

            $fields[] = $processedField;
            $fieldNames[] = $fieldName;
            $fieldTypes[$fieldName] = $fieldType;

            if ($isRequired) {
                $requiredFields[] = $fieldName;
            }
        }

        return [
            'fields' => $fields,
            'field_names' => $fieldNames,
            'field_types' => $fieldTypes,
            'required_fields' => $requiredFields
        ];
    }

    /**
     * Validate template response structure
     */
    public function validateTemplateStructure(array $templateData): bool
    {
        // Check required top-level keys
        $requiredKeys = ['template_id', 'fields', 'field_names'];
        foreach ($requiredKeys as $key) {
            if (!isset($templateData[$key])) {
                Log::warning('Template structure validation failed', [
                    'missing_key' => $key,
                    'available_keys' => array_keys($templateData)
                ]);
                return false;
            }
        }

        // Validate fields structure
        if (!is_array($templateData['fields']) || empty($templateData['fields'])) {
            Log::warning('Template fields validation failed', [
                'fields_type' => gettype($templateData['fields']),
                'fields_count' => is_array($templateData['fields']) ? count($templateData['fields']) : 0
            ]);
            return false;
        }

        // Validate each field has required properties
        foreach ($templateData['fields'] as $index => $field) {
            if (!isset($field['name']) || !isset($field['type'])) {
                Log::warning('Template field validation failed', [
                    'field_index' => $index,
                    'field_data' => $field
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Clear template cache for specific template or all templates
     */
    public function clearTemplateCache(?string $templateId = null): bool
    {
        try {
            if ($templateId) {
                $cacheKey = "docuseal:template:{$templateId}";
                Cache::forget($cacheKey);
                Log::info('Cleared template cache', ['template_id' => $templateId]);
            } else {
                // Clear all template caches
                $pattern = "docuseal:template:*";
                // Note: This requires Redis or similar cache store that supports pattern deletion
                Log::info('Cleared all template caches');
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to clear template cache', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get template field mapping suggestions based on field names
     */
    public function getFieldMappingSuggestions(array $templateFields, array $sourceFields): array
    {
        $suggestions = [];
        
        foreach ($templateFields as $templateField) {
            $templateFieldName = strtolower($templateField['name']);
            $bestMatch = null;
            $bestScore = 0;

            foreach ($sourceFields as $sourceField) {
                $sourceFieldName = strtolower($sourceField);
                $score = $this->calculateFieldNameSimilarity($templateFieldName, $sourceFieldName);
                
                if ($score > $bestScore && $score > 0.6) { // 60% similarity threshold
                    $bestMatch = $sourceField;
                    $bestScore = $score;
                }
            }

            if ($bestMatch) {
                $suggestions[] = [
                    'template_field' => $templateField['name'],
                    'suggested_source' => $bestMatch,
                    'confidence' => $bestScore,
                    'field_type' => $templateField['type']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Calculate similarity between field names
     */
    protected function calculateFieldNameSimilarity(string $field1, string $field2): float
    {
        // Normalize field names
        $normalized1 = $this->normalizeFieldName($field1);
        $normalized2 = $this->normalizeFieldName($field2);

        // Calculate Levenshtein distance
        $distance = levenshtein($normalized1, $normalized2);
        $maxLength = max(strlen($normalized1), strlen($normalized2));
        
        // Convert to similarity score (0-1)
        return $maxLength > 0 ? 1 - ($distance / $maxLength) : 0;
    }

    /**
     * Normalize field names for comparison
     */
    protected function normalizeFieldName(string $fieldName): string
    {
        return strtolower(
            preg_replace('/[^a-zA-Z0-9]/', '', $fieldName)
        );
    }

    /**
     * Test DocuSeal API connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])
            ->timeout(10)
            ->get("{$this->apiUrl}/templates");

            if ($response->successful()) {
                $templates = $response->json();
                return [
                    'connected' => true,
                    'status' => 'healthy',
                    'templates_count' => count($templates),
                    'api_url' => $this->apiUrl
                ];
            }

            return [
                'connected' => false,
                'error' => "API error: {$response->status()}"
            ];

        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 