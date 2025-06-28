<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnhancedDocuSealMappingService
{
    private AzureFoundryService $azureAI;
    private array $mappingData = [];
    
    public function __construct(AzureFoundryService $azureAI)
    {
        $this->azureAI = $azureAI;
        $this->loadMappingData();
    }
    
    /**
     * Load all mapping JSON files into memory
     */
    private function loadMappingData(): void
    {
        $basePath = base_path('docs/mapping-final');
        
        // Load insurance form mappings
        if (File::exists("{$basePath}/insurance_form_mappings.json")) {
            $this->mappingData['insurance'] = json_decode(
                File::get("{$basePath}/insurance_form_mappings.json"), 
                true
            );
        }
        
        // Load order form mappings
        if (File::exists("{$basePath}/order-form-mappings.json")) {
            $this->mappingData['order'] = json_decode(
                File::get("{$basePath}/order-form-mappings.json"), 
                true
            );
        }
        
        Log::info('📚 Loaded DocuSeal mapping data', [
            'insurance_loaded' => isset($this->mappingData['insurance']),
            'order_loaded' => isset($this->mappingData['order'])
        ]);
    }
    
    /**
     * Map fields using AI with complete context
     */
    public function mapFieldsWithEnhancedContext(
        array $sourceData,
        array $templateFields,
        string $manufacturerName,
        string $formId
    ): array {
        try {
            // Get the complete mapping context for this form
            $mappingContext = $this->getMappingContextForForm($formId);
            
            // Build enhanced prompt with full context
            $prompt = $this->buildEnhancedPrompt(
                $sourceData,
                $templateFields,
                $manufacturerName,
                $formId,
                $mappingContext
            );
            
            // Call AI with enhanced context using reflection
            $response = $this->callAzureOpenAI([
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getEnhancedSystemPrompt()
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
            
            $result = $this->parseEnhancedResponse($response);
            
            // Convert to DocuSeal format
            $docuSealFields = [];
            foreach ($result['field_mappings'] as $mapping) {
                if (!empty($mapping['docuseal_field']) && !empty($mapping['value'])) {
                    $docuSealFields[] = [
                        'name' => $mapping['docuseal_field'],
                        'default_value' => (string)$mapping['value']
                    ];
                }
            }
            
            Log::info('✅ Enhanced AI mapping complete', [
                'manufacturer' => $manufacturerName,
                'form_id' => $formId,
                'input_fields' => count($sourceData),
                'mapped_fields' => count($docuSealFields),
                'confidence' => $result['overall_confidence'] ?? 0
            ]);
            
            return $docuSealFields;
            
        } catch (\Exception $e) {
            Log::error('Enhanced AI mapping failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturerName,
                'form_id' => $formId
            ]);
            throw $e;
        }
    }
    
    /**
     * Get mapping context for a specific form
     */
    private function getMappingContextForForm(string $formId): array
    {
        $context = [
            'canonical_mappings' => [],
            'form_specific_fields' => []
        ];
        
        // Extract all canonical mappings for this form
        if (isset($this->mappingData['insurance']['standardFieldMappings'])) {
            foreach ($this->mappingData['insurance']['standardFieldMappings'] as $section => $sectionData) {
                if (isset($sectionData['canonicalFields'])) {
                    foreach ($sectionData['canonicalFields'] as $canonicalName => $fieldInfo) {
                        if (isset($fieldInfo['formMappings'][$formId])) {
                            $context['canonical_mappings'][$canonicalName] = [
                                'docuseal_field' => $fieldInfo['formMappings'][$formId],
                                'description' => $fieldInfo['description'] ?? '',
                                'required' => $fieldInfo['required'] ?? false
                            ];
                        }
                    }
                }
            }
        }
        
        // Add order form mappings if available
        if (isset($this->mappingData['order']['orderFormFieldMappings']['standardFields'])) {
            foreach ($this->mappingData['order']['orderFormFieldMappings']['standardFields'] as $section => $sectionData) {
                if (isset($sectionData['canonicalFields'])) {
                    foreach ($sectionData['canonicalFields'] as $canonicalName => $fieldInfo) {
                        if (isset($fieldInfo['formMappings'][$formId])) {
                            $context['canonical_mappings'][$canonicalName] = [
                                'docuseal_field' => $fieldInfo['formMappings'][$formId],
                                'description' => $fieldInfo['description'] ?? '',
                                'required' => $fieldInfo['required'] ?? false
                            ];
                        }
                    }
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Build enhanced prompt with full context
     */
    private function buildEnhancedPrompt(
        array $sourceData,
        array $templateFields,
        string $manufacturerName,
        string $formId,
        array $mappingContext
    ): string {
        $sourceJson = json_encode($sourceData, JSON_PRETTY_PRINT);
        $templateFieldsList = implode("\n", array_map(function($name, $info) {
            return "- {$name} (type: " . ($info['type'] ?? 'text') . ")";
        }, array_keys($templateFields), $templateFields));
        
        $canonicalMappingsJson = json_encode($mappingContext['canonical_mappings'], JSON_PRETTY_PRINT);
        
        // Common field name transformations
        $commonTransformations = [
            'provider_npi' => 'physicianNPI',
            'provider_name' => 'physicianName',
            'provider_tax_id' => 'taxId',
            'facility_name' => 'facilityName',
            'patient_name' => 'patientName',
            'patient_dob' => 'patientDOB',
            'primary_insurance_name' => 'insuranceName',
            'member_id' => 'policyNumber'
        ];
        
        $transformationsJson = json_encode($commonTransformations, JSON_PRETTY_PRINT);
        
        return "
TASK: Map wound care form data to DocuSeal template fields for {$manufacturerName} using form {$formId}.

DOCUSEAL TEMPLATE FIELDS (These are the ONLY valid field names you can use):
{$templateFieldsList}

SOURCE DATA TO MAP:
{$sourceJson}

CANONICAL FIELD MAPPINGS FOR {$formId}:
{$canonicalMappingsJson}

COMMON FIELD TRANSFORMATIONS:
{$transformationsJson}

INSTRUCTIONS:
1. For each field in the source data:
   a. First check if it matches a key in COMMON FIELD TRANSFORMATIONS to get the canonical name
   b. Then look up the canonical name in CANONICAL FIELD MAPPINGS to get the DocuSeal field name
   c. If no mapping exists, try fuzzy matching with the DOCUSEAL TEMPLATE FIELDS
   
2. CRITICAL: The 'docuseal_field' in your response MUST be one of the fields listed in DOCUSEAL TEMPLATE FIELDS above

3. Example mapping flow:
   - Source: 'provider_npi' = '1234567890'
   - Transform: 'provider_npi' → 'physicianNPI' (canonical)
   - Map: 'physicianNPI' → 'Physician NPI' (for form2_IVR)
   - Result: docuseal_field='Physician NPI', value='1234567890'

4. For fields without direct mappings, use intelligent matching:
   - 'wound_location' might map to 'Wound Location' or 'Wound location'
   - 'diagnosis_code' might map to 'ICD-10' or 'Diagnosis Code'

5. Format dates as MM/DD/YYYY
6. Format phone numbers as (XXX) XXX-XXXX

RESPONSE FORMAT:
{
  \"field_mappings\": [
    {
      \"source_field\": \"provider_npi\",
      \"canonical_field\": \"physicianNPI\",
      \"docuseal_field\": \"Physician NPI\",
      \"value\": \"1234567890\",
      \"confidence\": 0.95,
      \"transformation\": \"none\"
    }
  ],
  \"unmapped_source_fields\": [\"field1\", \"field2\"],
  \"invalid_mappings\": [\"fields that couldn't be mapped to valid DocuSeal fields\"],
  \"overall_confidence\": 0.87
}
        ";
    }
    
    /**
     * Get enhanced system prompt
     */
    private function getEnhancedSystemPrompt(): string
    {
        return "You are an expert medical form field mapper specializing in DocuSeal document automation. Your task is to accurately map wound care form data to DocuSeal template fields. You have deep knowledge of medical terminology, insurance forms, and the specific field naming conventions used by different manufacturers. Always use the exact field names from the DocuSeal template - field names are case-sensitive and must match exactly. Return only valid JSON.";
    }
    
    /**
     * Parse enhanced response
     */
    private function parseEnhancedResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new \Exception("Invalid JSON response from AI");
        }
        
        return $data;
    }
    
    /**
     * Use reflection to make callAzureOpenAI accessible
     */
    private function callAzureOpenAI(array $payload): array
    {
        $reflection = new \ReflectionClass($this->azureAI);
        $method = $reflection->getMethod('callAzureOpenAI');
        $method->setAccessible(true);
        return $method->invoke($this->azureAI, $payload);
    }
}