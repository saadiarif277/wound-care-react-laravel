<?php

namespace App\Services\Medical;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\DocusealService;
use App\Services\FhirService;
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
    protected bool $enabled;
    protected bool $debugMode;
    protected bool $fallbackEnabled;

    public function __construct(
        protected DocusealService $docusealService,
        protected FhirService $fhirService,
        protected AzureHealthDataService $azureHealthService,
        protected PhiSafeLogger $logger
    ) {
        $this->aiServiceUrl = Config::get('services.medical_base_ai.base_url', 'http://127.0.0.1:8081');
        $this->aiServiceKey = Config::get('services.medical_base_ai.key', '');
        $this->enabled = Config::get('services.medical_base_ai.enabled', true);
        $this->debugMode = Config::get('services.medical_base_ai.debug', false);
        $this->fallbackEnabled = Config::get('services.medical_base_ai.fallback_enabled', true);
        $this->timeout = Config::get('services.medical_base_ai.timeout', 30);
    }

    /**
     * Check if AI service is healthy and properly configured
     */
    public function healthCheck(): array
    {
        try {
            $response = Http::timeout($this->timeout)->get("{$this->aiServiceUrl}/health");
            
            if ($response->successful()) {
                $data = $response->json();
                
                $this->logger->info('Medical AI Service health check passed', [
                    'status' => $data['status'] ?? 'unknown',
                    'azure_configured' => $data['azure_configured'] ?? false,
                    'knowledge_base_loaded' => $data['knowledge_base_loaded'] ?? false
                ]);

                return [
                    'healthy' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'azure_configured' => $data['azure_configured'] ?? false,
                    'knowledge_base_loaded' => $data['knowledge_base_loaded'] ?? false,
                    'response_time' => $response->handlerStats()['total_time'] ?? 0
                ];
            }

            throw new Exception("Health check failed with status: {$response->status()}");

        } catch (Exception $e) {
            $this->logger->warning('Medical AI Service health check failed', [
                'error' => $e->getMessage(),
                'service_url' => $this->aiServiceUrl
            ]);

            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'service_url' => $this->aiServiceUrl,
                'fallback_available' => $this->fallbackEnabled
            ];
        }
    }

    /**
     * Get service configuration and status
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->enabled,
            'service_url' => $this->aiServiceUrl,
            'timeout' => $this->timeout,
            'debug_mode' => $this->debugMode,
            'fallback_enabled' => $this->fallbackEnabled,
            'health_check' => $this->healthCheck(),
            'connection_test' => $this->testConnection()
        ];
    }

    /**
     * Test AI service connectivity and configuration
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->aiServiceUrl}/api/v1/test");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'connected' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'version' => $data['version'] ?? 'unknown',
                    'features' => $data['features'] ?? [],
                    'azure_openai_configured' => $data['azure_configured'] ?? false
                ];
            }

            return [
                'connected' => false,
                'error' => "Test failed with status: {$response->status()}"
            ];

        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Start the AI service if it's not running (for development)
     */
    public function startService(): bool
    {
        try {
            // First check if already running
            $healthCheck = $this->healthCheck();
            if ($healthCheck['healthy']) {
                $this->logger->info('Medical AI Service already running');
                return true;
            }

            // Try to start the service
            $this->logger->info('Attempting to start Medical AI Service');
            
            $scriptPath = base_path('scripts/medical_ai_service.py');
            if (!file_exists($scriptPath)) {
                $this->logger->error('Medical AI Service script not found', ['path' => $scriptPath]);
                return false;
            }

            // Start the service in background
            $command = "cd " . base_path('scripts') . " && python3 medical_ai_service.py > /dev/null 2>&1 &";
            exec($command);

            // Wait a moment and check if it started
            sleep(3);
            $healthCheck = $this->healthCheck();
            
            if ($healthCheck['healthy']) {
                $this->logger->info('Medical AI Service started successfully');
                return true;
            }

            $this->logger->error('Failed to start Medical AI Service');
            return false;

        } catch (Exception $e) {
            $this->logger->error('Error starting Medical AI Service', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Enhanced DocuSeal field mapping using AI with FHIR context
     */
    public function enhanceDocusealFieldMapping(?PatientManufacturerIVREpisode $episode = null, array $baseData, string $templateId): array
    {
        try {
            // Check if service is enabled
            if (!$this->enabled) {
                $this->logger->info('Medical AI Service disabled, using fallback');
                return $this->getFallbackMapping($baseData);
            }

            $this->logger->info('Starting AI-enhanced DocuSeal field mapping', [
                'episode_id' => $episode->id ?? 'N/A',
                'template_id' => $templateId
            ]);

            // Get comprehensive FHIR context
            if ($episode === null) {
                $this->logger->warning('Enhancing mapping without episode context', ['template_id' => $templateId]);
                $fhirContext = [];
            } else {
                $fhirContext = $this->buildFhirContext($episode);
            }
            
            // Get template structure from DocuSeal
            $templateStructure = $this->docusealService->getTemplateFieldsFromAPI($templateId);
            
            // Create enriched context for AI processing
            $enrichedContext = $this->buildEnrichedContext($episode, $baseData, $fhirContext, $templateStructure);
            
            // Call AI service for enhanced mapping
            $aiResponse = $this->callMedicalAiService($enrichedContext);
            
            // Check if we got a fallback response
            if (($aiResponse['method'] ?? '') === 'fallback' && $this->fallbackEnabled) {
                return $this->getFallbackMapping($baseData);
            }
            
            // Merge AI recommendations with base data
            $enhancedData = $this->mergeAiRecommendations($baseData, $aiResponse);
            
            // Validate and optimize field mappings
            $validatedData = $this->validateAndOptimizeFieldMappings($enhancedData, $templateStructure);
            
            $this->logger->info('AI-enhanced DocuSeal field mapping completed', [
                'episode_id' => $episode->id ?? 'N/A',
                'base_fields' => count($baseData),
                'enhanced_fields' => count($validatedData),
                'ai_confidence' => $aiResponse['confidence'] ?? 0
            ]);

            return $validatedData;

        } catch (Exception $e) {
            $this->logger->error('AI-enhanced DocuSeal field mapping failed', [
                'episode_id' => $episode->id ?? 'N/A',
                'error' => $e->getMessage()
            ]);

            // Fallback to base data or basic mapping if AI fails
            if ($this->fallbackEnabled) {
                return $this->getFallbackMapping($baseData);
            }
            
            return $baseData;
        }
    }

    /**
     * Get fallback mapping when AI service is unavailable
     */
    protected function getFallbackMapping(array $sourceData): array
    {
        $this->logger->info('Using fallback field mapping');
        
        // Perform basic field mapping
        $mappedFields = $this->performBasicFieldMapping($sourceData);
        
        // Add metadata about fallback usage
        $mappedFields['_ai_method'] = 'fallback';
        $mappedFields['_ai_confidence'] = 0.5;
        $mappedFields['_ai_recommendations'] = ['AI service unavailable - using basic field mapping'];
        
        return $mappedFields;
    }

    /**
     * Perform basic field mapping without AI
     */
    protected function performBasicFieldMapping(array $sourceData): array
    {
        $basicMapping = [
            // Patient information
            'patient_first_name' => $sourceData['first_name'] ?? $sourceData['patient_first_name'] ?? null,
            'patient_last_name' => $sourceData['last_name'] ?? $sourceData['patient_last_name'] ?? null,
            'patient_dob' => $sourceData['dob'] ?? $sourceData['date_of_birth'] ?? $sourceData['patient_dob'] ?? null,
            'patient_phone' => $sourceData['phone'] ?? $sourceData['patient_phone'] ?? null,
            'patient_email' => $sourceData['email'] ?? $sourceData['patient_email'] ?? null,
            
            // Address
            'patient_address_line1' => $sourceData['address'] ?? $sourceData['address_line1'] ?? null,
            'patient_city' => $sourceData['city'] ?? null,
            'patient_state' => $sourceData['state'] ?? null,
            'patient_zip' => $sourceData['zip'] ?? $sourceData['postal_code'] ?? null,
            
            // Insurance
            'primary_insurance_name' => $sourceData['insurance_name'] ?? $sourceData['primary_insurance'] ?? null,
            'primary_member_id' => $sourceData['member_id'] ?? $sourceData['policy_number'] ?? null,
            
            // Provider
            'provider_name' => $sourceData['doctor_name'] ?? $sourceData['provider_name'] ?? null,
            'provider_npi' => $sourceData['npi'] ?? $sourceData['provider_npi'] ?? null,
        ];

        // Remove null values and keep original data that wasn't mapped
        $result = array_filter($basicMapping, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Merge with original data to preserve unmapped fields
        return array_merge($sourceData, $result);
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
        if (Config::get('services.medical_base_ai.cache_enabled', true)) {
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
                        'Content-Type' => 'application/json'
                    ])
                    ->post($this->aiServiceUrl . '/api/v1/enhance-mapping', [
                        'context' => $context,
                        'optimization_level' => 'high',
                        'confidence_threshold' => 0.7
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    
                    // Cache successful response
                    if (Config::get('services.medical_base_ai.cache_enabled', true)) {
                        Cache::put($cacheKey, $result, 300); // 5 minutes
                    }
                    
                    return $result;
                }

                throw new Exception("AI service returned status: " . $response->status());
                
            } catch (Exception $e) {
                $attempt++;
                $this->logger->warning('AI service call failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'service_url' => $this->aiServiceUrl
                ]);

                if ($attempt >= $this->retryAttempts) {
                    // Return fallback response
                    return [
                        'enhanced_fields' => [],
                        'confidence' => 0,
                        'method' => 'fallback',
                        'recommendations' => []
                    ];
                }

                // Wait before retry (exponential backoff)
                sleep(pow(2, $attempt));
            }
        }

        return [];
    }

    /**
     * Merge AI recommendations with base data
     */
    protected function mergeAiRecommendations(array $baseData, array $aiResponse): array
    {
        $enhancedData = $baseData;
        $aiFields = $aiResponse['enhanced_fields'] ?? [];
        
        foreach ($aiFields as $field => $aiValue) {
            if ($this->shouldUseAiValue($field, $baseData[$field] ?? null, $aiValue, $aiResponse)) {
                $enhancedData[$field] = $aiValue;
            }
        }

        // Add AI-generated recommendations
        if (!empty($aiResponse['recommendations'])) {
            $enhancedData['_ai_recommendations'] = $aiResponse['recommendations'];
        }

        // Add confidence score
        $enhancedData['_ai_confidence'] = $aiResponse['confidence'] ?? 0;
        $enhancedData['_ai_method'] = $aiResponse['method'] ?? 'unknown';

        return $enhancedData;
    }

    /**
     * Validate and optimize field mappings
     */
    protected function validateAndOptimizeFieldMappings(array $data, array $templateStructure): array
    {
        $validatedData = [];
        
        foreach ($data as $field => $value) {
            // Skip AI metadata fields
            if (str_starts_with($field, '_ai_')) {
                $validatedData[$field] = $value;
                continue;
            }

            $fieldStructure = $templateStructure[$field] ?? null;
            if ($fieldStructure) {
                $validatedValue = $this->validateFieldValue($value, $fieldStructure);
                if ($validatedValue !== null) {
                    $validatedData[$field] = $validatedValue;
                }
            } else {
                // Keep fields that don't have template structure (dynamic fields)
                $validatedData[$field] = $value;
            }
        }

        return $validatedData;
    }

    /**
     * Validate individual field value based on field structure
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
            
            case 'phone':
                return $this->validatePhoneValue($value);
            
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ?: null;
            
            case 'checkbox':
                return $this->validateCheckboxValue($value);
            
            case 'number':
                return is_numeric($value) ? (float)$value : null;
            
            case 'text':
            case 'textarea':
            default:
                return $this->sanitizeTextValue($value);
        }
    }

    /**
     * Validate date value
     */
    protected function validateDateValue($value): ?string
    {
        if (empty($value)) return null;
        
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
        if (empty($value)) return null;
        
        // Remove all non-numeric characters
        $cleaned = preg_replace('/\D/', '', $value);
        
        // Must be at least 10 digits
        if (strlen($cleaned) >= 10) {
            // Format as (XXX) XXX-XXXX if 10 digits
            if (strlen($cleaned) === 10) {
                return sprintf('(%s) %s-%s', 
                    substr($cleaned, 0, 3),
                    substr($cleaned, 3, 3),
                    substr($cleaned, 6, 4)
                );
            }
            // Return as-is if longer (international, etc.)
            return $cleaned;
        }
        
        return null;
    }

    /**
     * Validate checkbox value
     */
    protected function validateCheckboxValue($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', '1', 'yes', 'on', 'checked']);
        }
        return (bool)$value;
    }

    /**
     * Sanitize text value
     */
    protected function sanitizeTextValue($value): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        // Remove potentially harmful content but preserve medical text
        $value = strip_tags($value);
        $value = trim($value);
        
        return $value;
    }

    /**
     * Determine if AI value should be used over existing value
     */
    protected function shouldUseAiValue(string $field, $existingValue, $aiValue, array $aiResponse): bool
    {
        // Never replace if AI value is empty
        if (empty($aiValue)) return false;
        
        // Always use AI value if no existing value
        if (empty($existingValue)) return true;
        
        // Check AI confidence for this field
        $fieldConfidence = $aiResponse['field_confidence'][$field] ?? $aiResponse['confidence'] ?? 0;
        
        // Only replace if AI is confident (>0.8) and existing value seems incomplete
        if ($fieldConfidence > 0.8) {
            // Replace if existing value is clearly incomplete
            if (is_string($existingValue) && strlen($existingValue) < 3) return true;
            if (is_string($existingValue) && str_contains(strtolower($existingValue), 'unknown')) return true;
        }
        
        // Don't replace otherwise
        return false;
    }

    /**
     * Get manufacturer-specific requirements
     */
    protected function getManufacturerRequirements(int $manufacturerId): array
    {
        return Cache::remember("manufacturer_requirements_{$manufacturerId}", 3600, function() use ($manufacturerId) {
            $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
            if (!$manufacturer) return [];
            $filename = \Illuminate\Support\Str::slug($manufacturer->name);
            $path = config_path("manufacturers/{$filename}.php");
            if (!file_exists($path)) return [];
            $config = include $path;
            return $config['requirements'] ?? [];
        });
    }

    /**
     * Get manufacturer field preferences
     */
    protected function getManufacturerFieldPreferences(int $manufacturerId): array
    {
        return Cache::remember("manufacturer_field_prefs_{$manufacturerId}", 3600, function() use ($manufacturerId) {
            $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
            if (!$manufacturer) return [];
            $filename = \Illuminate\Support\Str::slug($manufacturer->name);
            $path = config_path("manufacturers/{$filename}.php");
            if (!file_exists($path)) return [];
            $config = include $path;
            return $config['field_preferences'] ?? [];
        });
    }

    /**
     * Build FHIR context from episode metadata when direct FHIR access fails
     */
    protected function buildFhirContextFromMetadata(PatientManufacturerIVREpisode $episode): array
    {
        $metadata = $episode->metadata ?? [];
        
        return [
            'patient' => $this->normalizeFhirPatientData($metadata['patient_data'] ?? []),
            'practitioner' => $this->normalizeFhirPractitionerData($metadata['practitioner_data'] ?? []),
            'organization' => $this->normalizeFhirOrganizationData($metadata['organization_data'] ?? []),
            'condition' => $this->normalizeFhirConditionData($metadata['clinical_data'] ?? []),
            'coverage' => $this->normalizeFhirCoverageData($metadata['insurance_data'] ?? []),
            'episode_of_care' => [
                'id' => $episode->id,
                'status' => $episode->status,
                'period' => [
                    'start' => $episode->created_at->toISOString()
                ]
            ]
        ];
    }

    /**
     * Normalize FHIR patient data to standard format
     */
    protected function normalizeFhirPatientData(array $patientData): array
    {
        if (empty($patientData)) return [];

        $names = $patientData['name'] ?? [];
        $addresses = $patientData['address'] ?? [];
        $telecom = $patientData['telecom'] ?? [];

        return [
            'id' => $patientData['id'] ?? null,
            'name' => $this->extractHumanName($names),
            'gender' => $patientData['gender'] ?? null,
            'birthDate' => $patientData['birthDate'] ?? null,
            'address' => $this->extractAddress($addresses),
            'telecom' => $this->extractTelecom($telecom),
            'identifier' => $patientData['identifier'] ?? []
        ];
    }

    /**
     * Normalize FHIR practitioner data
     */
    protected function normalizeFhirPractitionerData(array $practitionerData): array
    {
        if (empty($practitionerData)) return [];

        return [
            'id' => $practitionerData['id'] ?? null,
            'name' => $this->extractHumanName($practitionerData['name'] ?? []),
            'telecom' => $this->extractTelecom($practitionerData['telecom'] ?? []),
            'identifier' => $practitionerData['identifier'] ?? [],
            'qualification' => $practitionerData['qualification'] ?? []
        ];
    }

    /**
     * Normalize FHIR organization data
     */
    protected function normalizeFhirOrganizationData(array $organizationData): array
    {
        if (empty($organizationData)) return [];

        return [
            'id' => $organizationData['id'] ?? null,
            'name' => $organizationData['name'] ?? null,
            'address' => $this->extractAddress($organizationData['address'] ?? []),
            'telecom' => $this->extractTelecom($organizationData['telecom'] ?? []),
            'identifier' => $organizationData['identifier'] ?? []
        ];
    }

    /**
     * Normalize FHIR condition data
     */
    protected function normalizeFhirConditionData(array $conditionData): array
    {
        if (empty($conditionData)) return [];

        return [
            'id' => $conditionData['id'] ?? null,
            'code' => $conditionData['code'] ?? null,
            'category' => $conditionData['category'] ?? null,
            'clinicalStatus' => $conditionData['clinicalStatus'] ?? null,
            'verificationStatus' => $conditionData['verificationStatus'] ?? null,
            'onset' => $conditionData['onset'] ?? null,
            'bodySite' => $conditionData['bodySite'] ?? null
        ];
    }

    /**
     * Normalize FHIR coverage data
     */
    protected function normalizeFhirCoverageData(array $coverageData): array
    {
        if (empty($coverageData)) return [];

        return [
            'id' => $coverageData['id'] ?? null,
            'status' => $coverageData['status'] ?? null,
            'type' => $coverageData['type'] ?? null,
            'policyHolder' => $coverageData['policyHolder'] ?? null,
            'beneficiary' => $coverageData['beneficiary'] ?? null,
            'payor' => $coverageData['payor'] ?? null,
            'period' => $coverageData['period'] ?? null
        ];
    }

    /**
     * Extract human name from FHIR name array
     */
    protected function extractHumanName(array $names): array
    {
        if (empty($names)) return [];

        $name = $names[0] ?? [];
        return [
            'use' => $name['use'] ?? 'official',
            'family' => $name['family'] ?? null,
            'given' => $name['given'] ?? [],
            'text' => $name['text'] ?? null
        ];
    }

    /**
     * Extract address from FHIR address array
     */
    protected function extractAddress(array $addresses): array
    {
        if (empty($addresses)) return [];

        $address = $addresses[0] ?? [];
        return [
            'use' => $address['use'] ?? 'home',
            'line' => $address['line'] ?? [],
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postalCode' => $address['postalCode'] ?? null,
            'country' => $address['country'] ?? null
        ];
    }

    /**
     * Extract telecom from FHIR telecom array
     */
    protected function extractTelecom(array $telecom): array
    {
        $result = [];
        
        foreach ($telecom as $contact) {
            $system = $contact['system'] ?? 'unknown';
            $value = $contact['value'] ?? null;
            
            if ($value) {
                $result[$system] = $value;
            }
        }
        
        return $result;
    }
}