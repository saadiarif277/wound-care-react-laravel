<?php

namespace App\Services\Medical;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\Docuseal\DocusealService;
use App\Services\Fhir\FhirService;
use App\Services\Azure\AzureHealthDataService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Exception;

class OptimizedMedicalAiService
{
    protected string $aiServiceUrl;
    protected string $aiServiceKey;
    protected int $timeout = 30;
    protected int $retryAttempts = 3;

    public function __construct(
        protected DocusealService $docusealService,
        protected FhirService $fhirService,
        protected AzureHealthDataService $azureHealthService,
        protected PhiSafeLogger $logger
    ) {
        $this->aiServiceUrl = Config::get('services.medical_ai.url', 'http://localhost:8001');
        $this->aiServiceKey = Config::get('services.medical_ai.key', '');
    }

    /**
     * Enhanced DocuSeal field mapping using AI with FHIR context
     */
    public function enhanceDocusealFieldMapping(PatientManufacturerIVREpisode $episode, array $baseData, string $templateId): array
    {
        try {
            $this->logger->info('Starting AI-enhanced DocuSeal field mapping', [
                'episode_id' => $episode->id,
                'template_id' => $templateId
            ]);

            // Get comprehensive FHIR context
            $fhirContext = $this->buildFhirContext($episode);
            
            // Get template structure from DocuSeal
            $templateStructure = $this->docusealService->getTemplateStructure($templateId);
            
            // Create enriched context for AI processing
            $enrichedContext = $this->buildEnrichedContext($episode, $baseData, $fhirContext, $templateStructure);
            
            // Call AI service for enhanced mapping
            $aiResponse = $this->callMedicalAiService($enrichedContext);
            
            // Merge AI recommendations with base data
            $enhancedData = $this->mergeAiRecommendations($baseData, $aiResponse);
            
            // Validate and optimize field mappings
            $validatedData = $this->validateAndOptimizeFieldMappings($enhancedData, $templateStructure);
            
            $this->logger->info('AI-enhanced DocuSeal field mapping completed', [
                'episode_id' => $episode->id,
                'base_fields' => count($baseData),
                'enhanced_fields' => count($validatedData),
                'ai_confidence' => $aiResponse['confidence'] ?? 0
            ]);

            return $validatedData;

        } catch (Exception $e) {
            $this->logger->error('AI-enhanced DocuSeal field mapping failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            // Fallback to base data if AI fails
            return $baseData;
        }
    }

    /**
     * Build comprehensive FHIR context for AI processing
     */
    protected function buildFhirContext(PatientManufacturerIVREpisode $episode): array
    {
        $fhirContext = [
            'patient' => [],
            'practitioner' => [],
            'organization' => [],
            'condition' => [],
            'episode_of_care' => [],
            'coverage' => []
        ];

        try {
            // Get patient data from Azure FHIR
            if ($episode->patient_fhir_id) {
                $patientData = $this->azureHealthService->getPatientData($episode->patient_fhir_id);
                $fhirContext['patient'] = $this->normalizeFhirPatientData($patientData);
            }

            // Get practitioner data
            $practitionerId = $episode->metadata['practitioner_fhir_id'] ?? null;
            if ($practitionerId) {
                $practitionerData = $this->azureHealthService->getPractitionerData($practitionerId);
                $fhirContext['practitioner'] = $this->normalizeFhirPractitionerData($practitionerData);
            }

            // Get organization data
            $organizationId = $episode->metadata['organization_fhir_id'] ?? null;
            if ($organizationId) {
                $organizationData = $this->azureHealthService->getOrganizationData($organizationId);
                $fhirContext['organization'] = $this->normalizeFhirOrganizationData($organizationData);
            }

            // Get condition data
            $conditionId = $episode->metadata['condition_id'] ?? null;
            if ($conditionId) {
                $conditionData = $this->azureHealthService->getConditionData($conditionId);
                $fhirContext['condition'] = $this->normalizeFhirConditionData($conditionData);
            }

            // Get coverage data
            $coverageIds = $episode->metadata['coverage_ids'] ?? [];
            if (!empty($coverageIds)) {
                $coverageData = $this->azureHealthService->getCoverageData($coverageIds);
                $fhirContext['coverage'] = $this->normalizeFhirCoverageData($coverageData);
            }

        } catch (Exception $e) {
            $this->logger->warning('FHIR context building failed, using metadata fallback', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            // Fallback to episode metadata
            $fhirContext = $this->buildFhirContextFromMetadata($episode);
        }

        return $fhirContext;
    }

    /**
     * Build enriched context for AI processing
     */
    protected function buildEnrichedContext(PatientManufacturerIVREpisode $episode, array $baseData, array $fhirContext, array $templateStructure): array
    {
        return [
            'episode' => [
                'id' => $episode->id,
                'manufacturer' => $episode->manufacturer->name ?? 'Unknown',
                'status' => $episode->status,
                'created_at' => $episode->created_at->toISOString()
            ],
            'fhir_context' => $fhirContext,
            'base_data' => $baseData,
            'template_structure' => $templateStructure,
            'manufacturer_context' => [
                'name' => $episode->manufacturer->name ?? 'Unknown',
                'requirements' => $this->getManufacturerRequirements($episode->manufacturer_id),
                'field_preferences' => $this->getManufacturerFieldPreferences($episode->manufacturer_id)
            ],
            'clinical_context' => [
                'primary_diagnosis' => $episode->metadata['clinical_data']['primary_diagnosis'] ?? null,
                'symptoms' => $episode->metadata['clinical_data']['symptoms'] ?? [],
                'wound_assessment' => $episode->metadata['clinical_data']['wound_assessment'] ?? null
            ],
            'ai_instructions' => [
                'task' => 'enhance_docuseal_field_mapping',
                'objectives' => [
                    'maximize_field_completion',
                    'ensure_clinical_accuracy',
                    'optimize_for_manufacturer_requirements',
                    'maintain_fhir_compliance'
                ],
                'constraints' => [
                    'preserve_existing_data',
                    'validate_medical_codes',
                    'ensure_phi_compliance'
                ]
            ]
        ];
    }

    /**
     * Call medical AI service with enhanced context
     */
    protected function callMedicalAiService(array $context): array
    {
        $cacheKey = 'medical_ai_' . md5(json_encode($context));
        
        // Check cache first (5 minute TTL)
        if (Config::get('services.medical_ai.cache_enabled', true)) {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                $this->logger->info('Using cached AI response');
                return $cachedResult;
            }
        }

        $attempt = 0;
        while ($attempt < $this->retryAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->aiServiceKey,
                        'Content-Type' => 'application/json',
                        'X-Service-Version' => '2.0'
                    ])
                    ->post($this->aiServiceUrl . '/api/v2/enhance-docuseal-mapping', $context);

                if ($response->successful()) {
                    $result = $response->json();
                    
                    // Cache successful response
                    if (Config::get('services.medical_ai.cache_enabled', true)) {
                        Cache::put($cacheKey, $result, now()->addMinutes(5));
                    }
                    
                    return $result;
                }

                $this->logger->warning('AI service HTTP error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'attempt' => $attempt + 1
                ]);

            } catch (Exception $e) {
                $this->logger->warning('AI service call failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);
            }

            $attempt++;
            if ($attempt < $this->retryAttempts) {
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }

        throw new Exception('Medical AI service unavailable after ' . $this->retryAttempts . ' attempts');
    }

    /**
     * Merge AI recommendations with base data
     */
    protected function mergeAiRecommendations(array $baseData, array $aiResponse): array
    {
        $enhancedData = $baseData;
        $aiSuggestions = $aiResponse['field_mappings'] ?? [];
        $confidence = $aiResponse['confidence'] ?? 0;

        foreach ($aiSuggestions as $field => $aiValue) {
            $shouldUseAi = $this->shouldUseAiValue($field, $baseData[$field] ?? null, $aiValue, $aiResponse);
            
            if ($shouldUseAi) {
                $enhancedData[$field] = $aiValue;
                $enhancedData['_ai_enhanced_fields'][] = $field;
            }
        }

        // Add AI metadata
        $enhancedData['_ai_metadata'] = [
            'confidence' => $confidence,
            'enhanced_fields' => $enhancedData['_ai_enhanced_fields'] ?? [],
            'ai_version' => $aiResponse['ai_version'] ?? 'unknown',
            'processed_at' => now()->toISOString()
        ];

        return $enhancedData;
    }

    /**
     * Validate and optimize field mappings
     */
    protected function validateAndOptimizeFieldMappings(array $data, array $templateStructure): array
    {
        $validatedData = [];
        $templateFields = $templateStructure['fields'] ?? [];

        foreach ($templateFields as $field) {
            $fieldName = $field['name'] ?? $field['id'] ?? null;
            if (!$fieldName) continue;

            $value = $data[$fieldName] ?? null;
            
            // Validate field type and format
            $validatedValue = $this->validateFieldValue($value, $field);
            
            if ($validatedValue !== null) {
                $validatedData[$fieldName] = $validatedValue;
            }
        }

        // Preserve AI metadata
        if (isset($data['_ai_metadata'])) {
            $validatedData['_ai_metadata'] = $data['_ai_metadata'];
        }

        return $validatedData;
    }

    /**
     * Validate individual field value
     */
    protected function validateFieldValue($value, array $fieldStructure): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $fieldType = $fieldStructure['type'] ?? 'text';
        
        switch ($fieldType) {
            case 'date':
                return $this->validateDateValue($value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
            case 'phone':
                return $this->validatePhoneValue($value);
            case 'checkbox':
                return $this->validateCheckboxValue($value);
            case 'number':
                return is_numeric($value) ? $value : null;
            default:
                return $this->sanitizeTextValue($value);
        }
    }

    /**
     * Validate date value
     */
    protected function validateDateValue($value): ?string
    {
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate phone value
     */
    protected function validatePhoneValue($value): ?string
    {
        $cleaned = preg_replace('/[^\d]/', '', $value);
        if (strlen($cleaned) >= 10) {
            return $cleaned;
        }
        return null;
    }

    /**
     * Validate checkbox value
     */
    protected function validateCheckboxValue($value): bool
    {
        return in_array(strtolower($value), ['true', '1', 'yes', 'on', 'checked']);
    }

    /**
     * Sanitize text value
     */
    protected function sanitizeTextValue($value): string
    {
        return strip_tags(trim($value));
    }

    /**
     * Determine if AI value should be used
     */
    protected function shouldUseAiValue(string $field, $existingValue, $aiValue, array $aiResponse): bool
    {
        // Don't override existing values unless AI confidence is high
        if ($existingValue && !empty($existingValue)) {
            $confidence = $aiResponse['field_confidence'][$field] ?? $aiResponse['confidence'] ?? 0;
            return $confidence > 0.8;
        }

        // Use AI value if no existing value
        return true;
    }

    /**
     * Get manufacturer requirements
     */
    protected function getManufacturerRequirements(int $manufacturerId): array
    {
        // This would typically come from a database or configuration
        return Cache::remember("manufacturer_requirements_{$manufacturerId}", 3600, function() use ($manufacturerId) {
            // Fetch from database or API
            return [
                'required_fields' => [],
                'preferred_formats' => [],
                'validation_rules' => []
            ];
        });
    }

    /**
     * Get manufacturer field preferences
     */
    protected function getManufacturerFieldPreferences(int $manufacturerId): array
    {
        return Cache::remember("manufacturer_field_preferences_{$manufacturerId}", 3600, function() use ($manufacturerId) {
            return [
                'field_mappings' => [],
                'format_preferences' => [],
                'validation_overrides' => []
            ];
        });
    }

    /**
     * Build FHIR context from episode metadata (fallback)
     */
    protected function buildFhirContextFromMetadata(PatientManufacturerIVREpisode $episode): array
    {
        $metadata = $episode->metadata ?? [];
        
        return [
            'patient' => $metadata['patient_data'] ?? [],
            'practitioner' => $metadata['provider_data'] ?? [],
            'organization' => $metadata['organization_data'] ?? $metadata['facility_data'] ?? [],
            'condition' => $metadata['clinical_data'] ?? [],
            'coverage' => $metadata['insurance_data'] ?? []
        ];
    }

    /**
     * Normalize FHIR patient data
     */
    protected function normalizeFhirPatientData(array $patientData): array
    {
        return [
            'id' => $patientData['id'] ?? null,
            'name' => $this->extractHumanName($patientData['name'] ?? []),
            'gender' => $patientData['gender'] ?? null,
            'birthDate' => $patientData['birthDate'] ?? null,
            'address' => $this->extractAddress($patientData['address'] ?? []),
            'telecom' => $this->extractTelecom($patientData['telecom'] ?? [])
        ];
    }

    /**
     * Normalize FHIR practitioner data
     */
    protected function normalizeFhirPractitionerData(array $practitionerData): array
    {
        return [
            'id' => $practitionerData['id'] ?? null,
            'name' => $this->extractHumanName($practitionerData['name'] ?? []),
            'identifier' => $practitionerData['identifier'] ?? [],
            'telecom' => $this->extractTelecom($practitionerData['telecom'] ?? [])
        ];
    }

    /**
     * Normalize FHIR organization data
     */
    protected function normalizeFhirOrganizationData(array $organizationData): array
    {
        return [
            'id' => $organizationData['id'] ?? null,
            'name' => $organizationData['name'] ?? null,
            'identifier' => $organizationData['identifier'] ?? [],
            'address' => $this->extractAddress($organizationData['address'] ?? []),
            'telecom' => $this->extractTelecom($organizationData['telecom'] ?? [])
        ];
    }

    /**
     * Normalize FHIR condition data
     */
    protected function normalizeFhirConditionData(array $conditionData): array
    {
        return [
            'id' => $conditionData['id'] ?? null,
            'code' => $conditionData['code'] ?? null,
            'display' => $conditionData['code']['text'] ?? null,
            'clinicalStatus' => $conditionData['clinicalStatus'] ?? null,
            'verificationStatus' => $conditionData['verificationStatus'] ?? null,
            'onset' => $conditionData['onsetDateTime'] ?? null
        ];
    }

    /**
     * Normalize FHIR coverage data
     */
    protected function normalizeFhirCoverageData(array $coverageData): array
    {
        $normalized = [];
        
        foreach ($coverageData as $coverage) {
            $normalized[] = [
                'id' => $coverage['id'] ?? null,
                'status' => $coverage['status'] ?? null,
                'type' => $coverage['type'] ?? null,
                'subscriber' => $coverage['subscriber'] ?? null,
                'payor' => $coverage['payor'] ?? null,
                'period' => $coverage['period'] ?? null
            ];
        }
        
        return $normalized;
    }

    /**
     * Extract human name from FHIR format
     */
    protected function extractHumanName(array $names): array
    {
        $name = $names[0] ?? [];
        
        return [
            'family' => $name['family'] ?? null,
            'given' => $name['given'] ?? [],
            'full' => $name['text'] ?? null
        ];
    }

    /**
     * Extract address from FHIR format
     */
    protected function extractAddress(array $addresses): array
    {
        $address = $addresses[0] ?? [];
        
        return [
            'line' => $address['line'] ?? [],
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postalCode' => $address['postalCode'] ?? null,
            'country' => $address['country'] ?? null
        ];
    }

    /**
     * Extract telecom from FHIR format
     */
    protected function extractTelecom(array $telecom): array
    {
        $normalized = [];
        
        foreach ($telecom as $contact) {
            $normalized[] = [
                'system' => $contact['system'] ?? null,
                'value' => $contact['value'] ?? null,
                'use' => $contact['use'] ?? null
            ];
        }
        
        return $normalized;
    }
} 