<?php

namespace App\Services\Azure;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Exception;

class AzureHealthDataService
{
    protected int $cacheTtl = 300; // 5 minutes

    public function __construct(
        protected FhirService $fhirService,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Get patient data from Azure Health Data Services
     */
    public function getPatientData(string $patientFhirId): array
    {
        $cacheKey = "azure_patient_{$patientFhirId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($patientFhirId) {
            try {
                $this->logger->info('Fetching patient data from Azure FHIR', [
                    'patient_fhir_id' => $patientFhirId
                ]);

                $response = $this->fhirService->getPatient($patientFhirId);
                
                if (!$response) {
                    throw new Exception("Patient not found: {$patientFhirId}");
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch patient data from Azure FHIR', [
                    'patient_fhir_id' => $patientFhirId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get practitioner data from Azure Health Data Services
     */
    public function getPractitionerData(string $practitionerId): array
    {
        $cacheKey = "azure_practitioner_{$practitionerId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($practitionerId) {
            try {
                $this->logger->info('Fetching practitioner data from Azure FHIR', [
                    'practitioner_id' => $practitionerId
                ]);

                $response = $this->fhirService->getPractitioner($practitionerId);
                
                if (!$response) {
                    throw new Exception("Practitioner not found: {$practitionerId}");
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch practitioner data from Azure FHIR', [
                    'practitioner_id' => $practitionerId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get organization data from Azure Health Data Services
     */
    public function getOrganizationData(string $organizationId): array
    {
        $cacheKey = "azure_organization_{$organizationId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($organizationId) {
            try {
                $this->logger->info('Fetching organization data from Azure FHIR', [
                    'organization_id' => $organizationId
                ]);

                $response = $this->fhirService->getOrganization($organizationId);
                
                if (!$response) {
                    throw new Exception("Organization not found: {$organizationId}");
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch organization data from Azure FHIR', [
                    'organization_id' => $organizationId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get condition data from Azure Health Data Services
     */
    public function getConditionData(string $conditionId): array
    {
        $cacheKey = "azure_condition_{$conditionId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($conditionId) {
            try {
                $this->logger->info('Fetching condition data from Azure FHIR', [
                    'condition_id' => $conditionId
                ]);

                $response = $this->fhirService->search('Condition', ['_id' => $conditionId]);
                $response = $response['entry'][0]['resource'] ?? null;
                
                if (!$response) {
                    throw new Exception("Condition not found: {$conditionId}");
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch condition data from Azure FHIR', [
                    'condition_id' => $conditionId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get coverage data from Azure Health Data Services
     */
    public function getCoverageData(array $coverageIds): array
    {
        $cacheKey = "azure_coverage_" . md5(json_encode($coverageIds));
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($coverageIds) {
            try {
                $this->logger->info('Fetching coverage data from Azure FHIR', [
                    'coverage_ids' => $coverageIds,
                    'count' => count($coverageIds)
                ]);

                $coverageData = [];
                
                foreach ($coverageIds as $type => $coverageId) {
                    if ($coverageId) {
                        $response = $this->fhirService->search('Coverage', ['_id' => $coverageId]);
                        $resource = $response['entry'][0]['resource'] ?? null;
                        if ($resource) {
                            $coverageData[$type] = $resource;
                        }
                    }
                }

                return $coverageData;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch coverage data from Azure FHIR', [
                    'coverage_ids' => $coverageIds,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get episode of care data from Azure Health Data Services
     */
    public function getEpisodeOfCareData(string $episodeOfCareId): array
    {
        $cacheKey = "azure_episode_of_care_{$episodeOfCareId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($episodeOfCareId) {
            try {
                $this->logger->info('Fetching episode of care data from Azure FHIR', [
                    'episode_of_care_id' => $episodeOfCareId
                ]);

                $response = $this->fhirService->getEpisodeOfCare($episodeOfCareId);
                
                if (!$response) {
                    throw new Exception("Episode of care not found: {$episodeOfCareId}");
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch episode of care data from Azure FHIR', [
                    'episode_of_care_id' => $episodeOfCareId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get comprehensive patient context from Azure Health Data Services
     */
    public function getPatientContext(string $patientFhirId): array
    {
        $cacheKey = "azure_patient_context_{$patientFhirId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($patientFhirId) {
            try {
                $this->logger->info('Fetching comprehensive patient context from Azure FHIR', [
                    'patient_fhir_id' => $patientFhirId
                ]);

                $context = [];
                
                // Get patient data
                $context['patient'] = $this->getPatientData($patientFhirId);
                
                // Get related resources
                $context['conditions'] = $this->getPatientConditions($patientFhirId);
                $context['coverage'] = $this->getPatientCoverage($patientFhirId);
                $context['episodes'] = $this->getPatientEpisodes($patientFhirId);
                
                return $context;

            } catch (Exception $e) {
                $this->logger->error('Failed to fetch patient context from Azure FHIR', [
                    'patient_fhir_id' => $patientFhirId,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Get patient conditions from Azure Health Data Services
     */
    public function getPatientConditions(string $patientFhirId): array
    {
        try {
            $response = $this->fhirService->searchConditions(['patient' => $patientFhirId]);
            return $response['entry'] ?? [];
        } catch (Exception $e) {
            $this->logger->warning('Failed to fetch patient conditions', [
                'patient_fhir_id' => $patientFhirId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get patient coverage from Azure Health Data Services
     */
    public function getPatientCoverage(string $patientFhirId): array
    {
        try {
            $response = $this->fhirService->searchCoverage(['patient' => $patientFhirId]);
            return $response['entry'] ?? [];
        } catch (Exception $e) {
            $this->logger->warning('Failed to fetch patient coverage', [
                'patient_fhir_id' => $patientFhirId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get patient episodes from Azure Health Data Services
     */
    public function getPatientEpisodes(string $patientFhirId): array
    {
        try {
            $response = $this->fhirService->search('EpisodeOfCare', ['patient' => $patientFhirId]);
            return $response['entry'] ?? [];
        } catch (Exception $e) {
            $this->logger->warning('Failed to fetch patient episodes', [
                'patient_fhir_id' => $patientFhirId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create or update patient in Azure Health Data Services
     */
    public function upsertPatient(array $patientData): array
    {
        try {
            $this->logger->info('Upserting patient in Azure FHIR', [
                'patient_id' => $patientData['id'] ?? 'new'
            ]);

            $response = $this->fhirService->createPatient($patientData);
            
            // Clear cache for this patient
            if (isset($response['id'])) {
                Cache::forget("azure_patient_{$response['id']}");
                Cache::forget("azure_patient_context_{$response['id']}");
            }
            
            return $response;

        } catch (Exception $e) {
            $this->logger->error('Failed to upsert patient in Azure FHIR', [
                'patient_data' => $patientData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create or update practitioner in Azure Health Data Services
     */
    public function upsertPractitioner(array $practitionerData): array
    {
        try {
            $this->logger->info('Upserting practitioner in Azure FHIR', [
                'practitioner_id' => $practitionerData['id'] ?? 'new'
            ]);

            $response = $this->fhirService->createPractitioner($practitionerData);
            
            // Clear cache for this practitioner
            if (isset($response['id'])) {
                Cache::forget("azure_practitioner_{$response['id']}");
            }
            
            return $response;

        } catch (Exception $e) {
            $this->logger->error('Failed to upsert practitioner in Azure FHIR', [
                'practitioner_data' => $practitionerData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create or update organization in Azure Health Data Services
     */
    public function upsertOrganization(array $organizationData): array
    {
        try {
            $this->logger->info('Upserting organization in Azure FHIR', [
                'organization_id' => $organizationData['id'] ?? 'new'
            ]);

            $response = $this->fhirService->createOrganization($organizationData);
            
            // Clear cache for this organization
            if (isset($response['id'])) {
                Cache::forget("azure_organization_{$response['id']}");
            }
            
            return $response;

        } catch (Exception $e) {
            $this->logger->error('Failed to upsert organization in Azure FHIR', [
                'organization_data' => $organizationData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Clear cache for a specific resource
     */
    public function clearCache(string $resourceType, string $resourceId): void
    {
        $cacheKey = "azure_{$resourceType}_{$resourceId}";
        Cache::forget($cacheKey);
        
        // Clear related context cache if it's a patient
        if ($resourceType === 'patient') {
            Cache::forget("azure_patient_context_{$resourceId}");
        }
    }

    /**
     * Clear all Azure Health Data Services cache
     */
    public function clearAllCache(): void
    {
        $cacheKeys = [
            'azure_patient_*',
            'azure_practitioner_*',
            'azure_organization_*',
            'azure_condition_*',
            'azure_coverage_*',
            'azure_episode_of_care_*',
            'azure_patient_context_*'
        ];

        foreach ($cacheKeys as $pattern) {
            Cache::flush(); // For simplicity, flush all cache
        }
    }

    /**
     * Check if Azure Health Data Services is configured and available
     */
    public function isConfigured(): bool
    {
        return Config::get('fhir.azure_health_data_services.enabled', false) &&
               !empty(Config::get('fhir.azure_health_data_services.workspace_url'));
    }

    /**
     * Get health check status for Azure Health Data Services
     */
    public function getHealthStatus(): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'status' => 'not_configured',
                    'message' => 'Azure Health Data Services not configured'
                ];
            }

            // Try to fetch metadata endpoint
            $metadata = $this->fhirService->getCapabilityStatement();
            
            return [
                'status' => 'healthy',
                'message' => 'Azure Health Data Services is accessible',
                'fhir_version' => $metadata['fhirVersion'] ?? 'unknown',
                'last_checked' => now()->toISOString()
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Azure Health Data Services is not accessible',
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString()
            ];
        }
    }
} 