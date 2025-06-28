<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Azure AI Foundry Service for intelligent form translation and field mapping
 * 
 * Uses Azure OpenAI to intelligently map between different form formats,
 * reducing the need for static field mappings and handling variations automatically.
 */
final class AzureFoundryService
{
    private string $endpoint;
    private string $apiKey;
    private string $deploymentName;
    private string $apiVersion;

    public function __construct()
    {
        $this->endpoint = config('azure.ai_foundry.endpoint');
        $this->apiKey = config('azure.ai_foundry.api_key');
        $this->deploymentName = config('azure.ai_foundry.deployment_name', 'gpt-4');
        $this->apiVersion = config('azure.ai_foundry.api_version', '2024-02-15-preview');
    }

    /**
     * Translate form data between different formats using Azure OpenAI
     */
    public function translateFormData(
        array $sourceData,
        array $targetSchema,
        string $sourceContext = '',
        string $targetContext = '',
        array $options = []
    ): array {
        $cacheKey = $this->generateCacheKey('translate', $sourceData, $targetSchema);
        
        if ($options['use_cache'] ?? true) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('Azure AI: Using cached translation', ['cache_key' => $cacheKey]);
                return $cached;
            }
        }

        $prompt = $this->buildTranslationPrompt($sourceData, $targetSchema, $sourceContext, $targetContext);
        
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt('form_translator')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1, // Low temperature for consistent mapping
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object']
            ]);

            $result = $this->parseTranslationResponse($response);
            
            // Cache successful translations
            if ($options['use_cache'] ?? true) {
                Cache::put($cacheKey, $result, now()->addHours(24));
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Azure AI translation failed', [
                'error' => $e->getMessage(),
                'source_fields' => array_keys($sourceData),
                'target_fields' => array_keys($targetSchema)
            ]);
            throw $e;
        }
    }

    /**
     * Intelligently map fields between FHIR data and DocuSeal templates
     */
    public function mapFhirToDocuSeal(
        array $fhirData,
        array $docuSealFields,
        string $manufacturerName = '',
        array $context = []
    ): array {
        $prompt = $this->buildFhirMappingPrompt($fhirData, $docuSealFields, $manufacturerName, $context);
        
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt('fhir_mapper')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 3000,
                'response_format' => ['type' => 'json_object']
            ]);

            return $this->parseMappingResponse($response);

        } catch (Exception $e) {
            Log::error('FHIR to DocuSeal mapping failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturerName,
                'fhir_resources' => array_keys($fhirData),
                'docuseal_fields' => count($docuSealFields)
            ]);
            throw $e;
        }
    }

    /**
     * Extract and structure data from unstructured text (like emails or notes)
     */
    public function extractStructuredData(
        string $unstructuredText,
        array $targetSchema,
        string $context = ''
    ): array {
        $prompt = $this->buildExtractionPrompt($unstructuredText, $targetSchema, $context);
        
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt('data_extractor')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.2,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object']
            ]);

            return $this->parseExtractionResponse($response);

        } catch (Exception $e) {
            Log::error('Data extraction failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($unstructuredText),
                'target_fields' => array_keys($targetSchema)
            ]);
            throw $e;
        }
    }

    /**
     * Validate and suggest corrections for form data
     */
    public function validateAndSuggest(
        array $formData,
        array $validationRules,
        string $context = ''
    ): array {
        $prompt = $this->buildValidationPrompt($formData, $validationRules, $context);
        
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt('validator')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1500,
                'response_format' => ['type' => 'json_object']
            ]);

            return $this->parseValidationResponse($response);

        } catch (Exception $e) {
            Log::error('Validation failed', [
                'error' => $e->getMessage(),
                'form_fields' => array_keys($formData)
            ]);
            throw $e;
        }
    }

    /**
     * Generate intelligent field mapping suggestions
     */
    public function suggestFieldMappings(
        array $sourceFields,
        array $targetFields,
        array $sampleData = [],
        string $context = ''
    ): array {
        $prompt = $this->buildSuggestionPrompt($sourceFields, $targetFields, $sampleData, $context);
        
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt('mapping_suggester')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.4,
                'max_tokens' => 2500,
                'response_format' => ['type' => 'json_object']
            ]);

            return $this->parseSuggestionResponse($response);

        } catch (Exception $e) {
            Log::error('Field mapping suggestion failed', [
                'error' => $e->getMessage(),
                'source_fields' => count($sourceFields),
                'target_fields' => count($targetFields)
            ]);
            throw $e;
        }
    }

    /**
     * Call Azure OpenAI API
     */
    private function callAzureOpenAI(array $payload): array
    {
        $url = "{$this->endpoint}/openai/deployments/{$this->deploymentName}/chat/completions";
        
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout(60)
        ->retry(3, 1000)
        ->post($url, array_merge($payload, [
            'api-version' => $this->apiVersion
        ]));

        if (!$response->successful()) {
            throw new Exception("Azure OpenAI API call failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get system prompts for different AI tasks
     */
    private function getSystemPrompt(string $type): string
    {
        $prompts = [
            'form_translator' => "You are an expert medical form translator specializing in wound care and medical device orders. Your task is to intelligently map data between different form formats while preserving medical accuracy and context. Always maintain HIPAA compliance and never expose PHI unnecessarily. Return only valid JSON.",

            'fhir_mapper' => "You are a FHIR R4 expert specializing in mapping FHIR healthcare data to DocuSeal form fields for wound care documentation. You understand medical terminology, insurance verification requirements, and manufacturer-specific form variations. Map data accurately while maintaining clinical context. Return only valid JSON.",

            'data_extractor' => "You are a medical data extraction specialist. Extract structured information from unstructured medical text while maintaining accuracy and context. Focus on wound care, patient information, insurance details, and clinical assessments. Return only valid JSON.",

            'validator' => "You are a medical form validation expert. Validate form data for accuracy, completeness, and compliance with healthcare standards. Suggest corrections for common errors while maintaining clinical accuracy. Return only valid JSON.",

            'mapping_suggester' => "You are an intelligent field mapping assistant for medical forms. Suggest the best mappings between different form fields based on medical context, field names, and sample data. Provide confidence scores and explanations. Return only valid JSON."
        ];

        return $prompts[$type] ?? $prompts['form_translator'];
    }

    /**
     * Build translation prompt
     */
    private function buildTranslationPrompt(
        array $sourceData,
        array $targetSchema,
        string $sourceContext,
        string $targetContext
    ): string {
        $sourceJson = json_encode($sourceData, JSON_PRETTY_PRINT);
        $targetJson = json_encode($targetSchema, JSON_PRETTY_PRINT);
        
        return "
TASK: Translate form data from source format to target format.

SOURCE CONTEXT: {$sourceContext}
TARGET CONTEXT: {$targetContext}

SOURCE DATA:
{$sourceJson}

TARGET SCHEMA:
{$targetJson}

INSTRUCTIONS:
1. Map each source field to the most appropriate target field
2. Transform data formats as needed (dates, phone numbers, etc.)
3. Handle missing fields gracefully
4. Preserve medical accuracy and context
5. Return confidence scores for each mapping

RESPONSE FORMAT:
{
  \"mappings\": {
    \"target_field_name\": {
      \"value\": \"mapped_value\",
      \"source_field\": \"source_field_name\",
      \"confidence\": 0.95,
      \"transformation\": \"description_of_any_transformation\"
    }
  },
  \"unmapped_source_fields\": [\"field1\", \"field2\"],
  \"unmapped_target_fields\": [\"field3\", \"field4\"],
  \"overall_confidence\": 0.87,
  \"warnings\": [\"Any concerns or notes\"]
}
        ";
    }

    /**
     * Build FHIR mapping prompt
     */
    private function buildFhirMappingPrompt(
        array $fhirData,
        array $docuSealFields,
        string $manufacturerName,
        array $context
    ): string {
        $fhirJson = json_encode($fhirData, JSON_PRETTY_PRINT);
        $fieldsJson = json_encode($docuSealFields, JSON_PRETTY_PRINT);
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);
        
        return "
TASK: Map FHIR R4 healthcare data to DocuSeal form fields for {$manufacturerName}.

FHIR DATA:
{$fhirJson}

DOCUSEAL FIELDS:
{$fieldsJson}

CONTEXT:
{$contextJson}

INSTRUCTIONS:
1. Extract relevant data from FHIR resources
2. Map to DocuSeal field names accurately
3. Format data appropriately for form fields
4. Handle FHIR arrays and nested objects
5. Maintain medical accuracy and context

RESPONSE FORMAT:
{
  \"mapped_data\": {
    \"Patient Name\": \"John Doe\",
    \"DOB\": \"01/15/1980\",
    \"Physician NPI 1\": \"1234567890\"
  },
  \"mapping_details\": {
    \"Patient Name\": {
      \"fhir_path\": \"Patient.name[0].given[0] + Patient.name[0].family\",
      \"confidence\": 0.98
    }
  },
  \"unmapped_fields\": [\"field1\", \"field2\"],
  \"warnings\": [\"Any concerns\"],
  \"statistics\": {
    \"total_fields\": 25,
    \"mapped_fields\": 23,
    \"confidence_average\": 0.91
  }
}
        ";
    }

    /**
     * Build extraction prompt
     */
    private function buildExtractionPrompt(
        string $text,
        array $targetSchema,
        string $context
    ): string {
        $schemaJson = json_encode($targetSchema, JSON_PRETTY_PRINT);
        
        return "
TASK: Extract structured data from unstructured medical text.

CONTEXT: {$context}

TEXT:
{$text}

TARGET SCHEMA:
{$schemaJson}

INSTRUCTIONS:
1. Extract relevant information matching the target schema
2. Normalize data formats (dates, phones, etc.)
3. Infer missing information when reasonable
4. Maintain medical accuracy
5. Flag uncertain extractions

RESPONSE FORMAT:
{
  \"extracted_data\": {
    \"field_name\": \"extracted_value\"
  },
  \"confidence_scores\": {
    \"field_name\": 0.85
  },
  \"uncertain_fields\": [\"field1\"],
  \"notes\": \"Additional observations\"
}
        ";
    }

    /**
     * Build validation prompt
     */
    private function buildValidationPrompt(
        array $formData,
        array $validationRules,
        string $context
    ): string {
        $dataJson = json_encode($formData, JSON_PRETTY_PRINT);
        $rulesJson = json_encode($validationRules, JSON_PRETTY_PRINT);
        
        return "
TASK: Validate medical form data and suggest corrections.

CONTEXT: {$context}

FORM DATA:
{$dataJson}

VALIDATION RULES:
{$rulesJson}

INSTRUCTIONS:
1. Check data against validation rules
2. Identify errors, inconsistencies, or missing data
3. Suggest specific corrections
4. Consider medical context and best practices
5. Flag potential HIPAA compliance issues

RESPONSE FORMAT:
{
  \"is_valid\": false,
  \"errors\": [
    {
      \"field\": \"phone\",
      \"error\": \"Invalid format\",
      \"suggestion\": \"(555) 123-4567\"
    }
  ],
  \"warnings\": [
    {
      \"field\": \"diagnosis\",
      \"warning\": \"Consider more specific ICD-10 code\"
    }
  ],
  \"suggestions\": [
    \"Add secondary insurance information if available\"
  ],
  \"compliance_notes\": [\"Any HIPAA or regulatory concerns\"]
}
        ";
    }

    /**
     * Build suggestion prompt
     */
    private function buildSuggestionPrompt(
        array $sourceFields,
        array $targetFields,
        array $sampleData,
        string $context
    ): string {
        $sourceJson = json_encode($sourceFields, JSON_PRETTY_PRINT);
        $targetJson = json_encode($targetFields, JSON_PRETTY_PRINT);
        $sampleJson = json_encode($sampleData, JSON_PRETTY_PRINT);
        
        return "
TASK: Suggest intelligent field mappings between source and target forms.

CONTEXT: {$context}

SOURCE FIELDS:
{$sourceJson}

TARGET FIELDS:
{$targetJson}

SAMPLE DATA:
{$sampleJson}

INSTRUCTIONS:
1. Suggest the best mapping for each target field
2. Consider medical terminology and context
3. Use sample data to inform decisions
4. Provide confidence scores and explanations
5. Handle variations in field naming

RESPONSE FORMAT:
{
  \"suggestions\": [
    {
      \"target_field\": \"Patient Name\",
      \"suggested_source\": \"patient_full_name\",
      \"confidence\": 0.95,
      \"explanation\": \"Direct semantic match\",
      \"alternatives\": [
        {
          \"source_field\": \"name\",
          \"confidence\": 0.8,
          \"explanation\": \"Generic name field\"
        }
      ]
    }
  ],
  \"unmappable_targets\": [\"field1\"],
  \"unused_sources\": [\"field2\"],
  \"mapping_strategy\": \"semantic_with_context\"
}
        ";
    }

    /**
     * Parse various response types
     */
    private function parseTranslationResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON response from Azure AI");
        }

        return [
            'success' => true,
            'mappings' => $data['mappings'] ?? [],
            'unmapped_source_fields' => $data['unmapped_source_fields'] ?? [],
            'unmapped_target_fields' => $data['unmapped_target_fields'] ?? [],
            'overall_confidence' => $data['overall_confidence'] ?? 0.0,
            'warnings' => $data['warnings'] ?? [],
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    private function parseMappingResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON response from Azure AI");
        }

        return [
            'success' => true,
            'mapped_data' => $data['mapped_data'] ?? [],
            'mapping_details' => $data['mapping_details'] ?? [],
            'unmapped_fields' => $data['unmapped_fields'] ?? [],
            'warnings' => $data['warnings'] ?? [],
            'statistics' => $data['statistics'] ?? [],
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    private function parseExtractionResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON response from Azure AI");
        }

        return [
            'success' => true,
            'extracted_data' => $data['extracted_data'] ?? [],
            'confidence_scores' => $data['confidence_scores'] ?? [],
            'uncertain_fields' => $data['uncertain_fields'] ?? [],
            'notes' => $data['notes'] ?? '',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    private function parseValidationResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON response from Azure AI");
        }

        return [
            'success' => true,
            'is_valid' => $data['is_valid'] ?? false,
            'errors' => $data['errors'] ?? [],
            'warnings' => $data['warnings'] ?? [],
            'suggestions' => $data['suggestions'] ?? [],
            'compliance_notes' => $data['compliance_notes'] ?? [],
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    private function parseSuggestionResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON response from Azure AI");
        }

        return [
            'success' => true,
            'suggestions' => $data['suggestions'] ?? [],
            'unmappable_targets' => $data['unmappable_targets'] ?? [],
            'unused_sources' => $data['unused_sources'] ?? [],
            'mapping_strategy' => $data['mapping_strategy'] ?? 'unknown',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    /**
     * Generate cache key for responses
     */
    private function generateCacheKey(string $operation, ...$params): string
    {
        $data = ['operation' => $operation, 'params' => $params];
        return 'azure_ai_' . md5(serialize($data));
    }

    /**
     * Test the Azure AI connection
     */
    public function testConnection(): array
    {
        try {
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Return a JSON object with "status": "connected" and "message": "Azure AI Foundry is working correctly"'
                    ]
                ],
                'max_tokens' => 100,
                'response_format' => ['type' => 'json_object']
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';
            $data = json_decode($content, true);

            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'message' => $data['message'] ?? 'Connection successful',
                'tokens_used' => $response['usage']['total_tokens'] ?? 0
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
