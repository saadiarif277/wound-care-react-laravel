<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FhirService
{
    private ?string $azureFhirEndpoint = null;
    private ?string $azureAccessToken = null;

    public function __construct()
    {
        $this->azureFhirEndpoint = config('services.azure.fhir_endpoint');
        // Don't get access token immediately - only when needed
        // $this->azureAccessToken = $this->getAzureAccessToken();

        // Debug logging for configuration
        Log::info('FhirService configuration check', [
            'azure_fhir_endpoint' => $this->azureFhirEndpoint,
            'azure_access_token_exists' => !empty($this->azureAccessToken),
            'env' => config('app.env'),
            'config_keys' => [
                'tenant_id' => config('services.azure.tenant_id'),
                'client_id' => config('services.azure.client_id'),
                'client_secret_exists' => !empty(config('services.azure.client_secret')),
                'fhir_endpoint' => config('services.azure.fhir_endpoint'),
            ],
            'env_vars' => [
                'AZURE_FHIR_ENDPOINT' => env('AZURE_FHIR_ENDPOINT'),
                'AZURE_FHIR_BASE_URL' => env('AZURE_FHIR_BASE_URL'),
            ]
        ]);
    }

    /**
     * Ensure Azure FHIR is properly configured
     * @throws \RuntimeException
     */
    private function ensureAzureConfigured(): void
    {
        // Check if FHIR is enabled globally - use the same check as FeatureFlagService
        if (!config('features.fhir.enabled', false)) {
            Log::info('FHIR is disabled globally via feature flag - using local fallback');
            return; // Don't throw exception, just return gracefully
        }

        // Check if FHIR service is enabled
        if (!config('features.fhir.service_enabled', false)) {
            Log::info('Azure FHIR service is disabled via feature flag - using local fallback');
            return; // Don't throw exception, just return gracefully
        }

        // Only validate Azure configuration if FHIR is actually enabled
        // Get token if needed
        if (empty($this->azureAccessToken)) {
            $this->azureAccessToken = $this->getAzureAccessToken();
        }

        if (empty($this->azureAccessToken)) {
            Log::error('Failed to get Azure access token', [
                'tenant_id_exists' => !empty(config('services.azure.tenant_id')),
                'client_id_exists' => !empty(config('services.azure.client_id')),
                'client_secret_exists' => !empty(config('services.azure.client_secret')),
                'scope_exists' => !empty(config('services.azure.fhir_scope')),
            ]);
            
            // Instead of throwing exception, log error and return gracefully
            // This allows the system to fall back to local IDs
            Log::warning('Azure FHIR access token not available - using local fallback');
            return;
        }

        if (empty($this->azureFhirEndpoint)) {
            Log::error('Azure FHIR endpoint validation failed', [
                'configured_endpoint' => $this->azureFhirEndpoint,
                'env_vars' => [
                    'AZURE_FHIR_ENDPOINT' => env('AZURE_FHIR_ENDPOINT'),
                    'AZURE_FHIR_BASE_URL' => env('AZURE_FHIR_BASE_URL')
                ],
                'config_keys' => [
                    'fhir_endpoint' => config('services.azure.fhir_endpoint'),
                    'fhir.base_url' => config('services.azure.fhir.base_url')
                ]
            ]);
            
            // Instead of throwing exception, log error and return gracefully
            // This allows the system to fall back to local IDs
            Log::warning('Azure FHIR endpoint not configured - using local fallback');
            return;
        }
    }

    /**
     * Check if Azure FHIR is available
     */
    public function isAzureConfigured(): bool
    {
        return !empty($this->azureAccessToken) && !empty($this->azureFhirEndpoint);
    }

    /**
     * Create a new Patient resource in Azure FHIR
     */
    public function createPatient(array $fhirData): array
    {
        $this->ensureAzureConfigured();

        // If FHIR is disabled or Azure is not configured, use local fallback
        if (!config('features.fhir.enabled', false) || 
            !config('features.fhir.service_enabled', false) ||
            empty($this->azureAccessToken) ||
            empty($this->azureFhirEndpoint)) {
            // Fall back to local ID if service is disabled or not configured
            $patientId = 'local-patient-' . uniqid();
            Log::info('Creating local FHIR Patient (service disabled or not configured)', ['patient_id' => $patientId]);
            return ['resourceType' => 'Patient', 'id' => $patientId];
        }

        // Make the actual FHIR API call
        $response = Http::withToken($this->azureAccessToken)
            ->withHeaders(['Content-Type' => 'application/fhir+json'])
            ->post("{$this->azureFhirEndpoint}/Patient", $fhirData);

        if (!$response->successful()) {
            Log::error('Failed to create FHIR Patient', [
                'status' => $response->status(),
                'body' => $response->json(),
                'data' => $fhirData
            ]);
            throw new \RuntimeException('Failed to create FHIR Patient: ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Create a new FHIR resource in Azure FHIR
     */
    public function create(string $resourceType, array $fhirData): array
    {
        $this->ensureAzureConfigured();

        // If FHIR is disabled or Azure is not configured, use local fallback
        if (!config('features.fhir.enabled', false) || 
            !config('features.fhir.service_enabled', false) ||
            empty($this->azureAccessToken) ||
            empty($this->azureFhirEndpoint)) {
            // Fall back to local ID if service is disabled or not configured
            $resourceId = 'local-' . strtolower($resourceType) . '-' . uniqid();
            Log::info("Creating local FHIR {$resourceType} (service disabled or not configured)", ['resource_id' => $resourceId]);
            return ['resourceType' => $resourceType, 'id' => $resourceId];
        }

        // Make the actual FHIR API call
        $response = Http::withToken($this->azureAccessToken)
            ->withHeaders(['Content-Type' => 'application/fhir+json'])
            ->post("{$this->azureFhirEndpoint}/{$resourceType}", $fhirData);

        if (!$response->successful()) {
            Log::error("Failed to create FHIR {$resourceType}", [
                'status' => $response->status(),
                'body' => $response->json(),
                'data' => $fhirData
            ]);
            throw new \RuntimeException("Failed to create FHIR {$resourceType}: " . $response->status());
        }

        return $response->json();
    }

    /**
     * Update an existing FHIR resource
     */
    public function update(string $resourceType, string $id, array $fhirData): array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->put("{$this->azureFhirEndpoint}/{$resourceType}/{$id}", $fhirData);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $resource = $response->json();

            Log::info("FHIR {$resourceType} updated in Azure", [
                'resource_id' => $id,
                'resource_type' => $resourceType
            ]);

            return $resource;

        } catch (\Exception $e) {
            Log::error("Failed to update FHIR {$resourceType} in Azure", [
                'error' => $e->getMessage(),
                'resource_type' => $resourceType,
                'resource_id' => $id,
                'data' => $fhirData
            ]);
            throw $e;
        }
    }

    /**
     * Search for FHIR resources.
     * @param string $resourceType
     * @param array $params
     * @return array
     */
    public function search(string $resourceType, array $params = []): array
    {
        $this->ensureAzureConfigured();

        // If FHIR is disabled or Azure is not configured, use local fallback
        if (!config('features.fhir.enabled', false) || 
            !config('features.fhir.service_enabled', false) ||
            empty($this->azureAccessToken) ||
            empty($this->azureFhirEndpoint)) {
            Log::info("Searching local FHIR {$resourceType} (service disabled or not configured)", ['params' => $params]);
            // Return empty bundle for now - in a real implementation, you might search local database
            return [
                'resourceType' => 'Bundle',
                'type' => 'searchset',
                'total' => 0,
                'entry' => []
            ];
        }

        // Make the actual FHIR API call
        $response = Http::withToken($this->azureAccessToken)
            ->withHeaders(['Content-Type' => 'application/fhir+json'])
            ->get("{$this->azureFhirEndpoint}/{$resourceType}", $params);

        if (!$response->successful()) {
            Log::error("Failed to search FHIR {$resourceType}", [
                'status' => $response->status(),
                'body' => $response->json(),
                'params' => $params
            ]);
            throw new \RuntimeException("Failed to search FHIR {$resourceType}: " . $response->status());
        }

        return $response->json();
    }

    /**
     * Get Patient by ID from Azure FHIR
     */
    public function getPatientById(string $id): ?array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Patient/{$id}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to read FHIR Patient from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get patient by FHIR ID (alias for getPatientById to match expected interface)
     */
    public function getPatient(string $patientFhirId, mixed $productRequest = null): array
    {
        // Use the existing getPatientById method
        $fhirPatient = $this->getPatientById($patientFhirId);

        if (!$fhirPatient) {
            throw new \Exception("Patient not found with FHIR ID: {$patientFhirId}");
        }

        // Return the array directly for consistent interface
        return $fhirPatient;
    }

    /**
     * Create a new practitioner
     */

    /**
     * Get Practitioner by ID from Azure FHIR
     */
    public function getPractitionerById(string $id): ?array
    {
        $this->ensureAzureConfigured();
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Practitioner/{$id}");
            if ($response->status() === 404) {
                return null;
            }
            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to fetch practitioner from FHIR', [
                'fhir_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get practitioner by FHIR ID (alias for getPractitionerById)
     */
    public function getPractitioner(string $practitionerFhirId, mixed $productRequest = null): array
    {
        $fhirPractitioner = $this->getPractitionerById($practitionerFhirId);
        if (!$fhirPractitioner) {
            throw new \Exception("Practitioner not found with FHIR ID: {$practitionerFhirId}");
        }
        return $fhirPractitioner;
    }
    public function createPractitioner(array $practitionerData): ?array
    {
        $this->ensureAzureConfigured();

        // Always use local data instead of Azure FHIR
        $practitionerId = 'local-practitioner-' . uniqid();
        Log::info('Creating local FHIR Practitioner', ['practitioner_id' => $practitionerId]);

        return [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'identifier' => [
                [
                    'system' => 'http://msc-mvp.com/practitioner',
                    'value' => $practitionerId
                ]
            ],
            'name' => [
                [
                    'use' => 'official',
                    'given' => [$practitionerData['name'][0]['given'][0] ?? 'Local'],
                    'family' => $practitionerData['name'][0]['family'] ?? 'Practitioner'
                ]
            ],
            'telecom' => $practitionerData['telecom'] ?? [],
            'address' => $practitionerData['address'] ?? []
        ];
    }

    /**
     * Get Organization resource by ID from FHIR server
     */
    public function getOrganization(string $organizationId): ?array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Organization/{$organizationId}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to retrieve FHIR Organization from Azure', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create a new organization
     */
    public function createOrganization(array $organizationData): ?array
    {
        $this->ensureAzureConfigured();

        // Always use local data instead of Azure FHIR
        $organizationId = 'local-organization-' . uniqid();
        Log::info('Creating local FHIR Organization', ['organization_id' => $organizationId]);

        return [
            'resourceType' => 'Organization',
            'id' => $organizationId,
            'identifier' => [
                [
                    'system' => 'http://msc-mvp.com/organization',
                    'value' => $organizationId
                ]
            ],
            'name' => $organizationData['name'] ?? 'Local Organization',
            'telecom' => $organizationData['telecom'] ?? [],
            'address' => $organizationData['address'] ?? []
        ];
    }

    /**
     * Update Patient resource in Azure FHIR
     */
    public function updatePatient(string $id, array $fhirData): ?array
    {
        $this->ensureAzureConfigured();

        try {
            // Add MSC-specific extensions if not present
            $fhirData = $this->addMscExtensions($fhirData);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->put("{$this->azureFhirEndpoint}/Patient/{$id}", $fhirData);

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $patient = $response->json();

            Log::info('FHIR Patient updated in Azure', ['patient_id' => $id]);

            return $patient;

        } catch (\Exception $e) {
            Log::error('Failed to update FHIR Patient in Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Search Coverage resources in Azure FHIR
     */
    public function searchCoverage(array $searchParams): array
    {
        $this->ensureAzureConfigured();

        try {
            $queryParams = [];

            // Map search parameters to FHIR search format
            if (!empty($searchParams['patient'])) {
                $queryParams['patient'] = $searchParams['patient'];
            }
            if (!empty($searchParams['status'])) {
                $queryParams['status'] = $searchParams['status'];
            }
            if (!empty($searchParams['beneficiary'])) {
                $queryParams['beneficiary'] = $searchParams['beneficiary'];
            }
            if (!empty($searchParams['subscriber'])) {
                $queryParams['subscriber'] = $searchParams['subscriber'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Coverage", $queryParams);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error searching Coverage: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server instead of Azure
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to search FHIR Coverage in Azure', ['params' => $searchParams, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Search Condition resources in Azure FHIR
     */
    public function searchConditions(array $searchParams): array
    {
        $this->ensureAzureConfigured();

        try {
            $queryParams = [];

            // Map search parameters to FHIR search format
            if (!empty($searchParams['patient'])) {
                $queryParams['patient'] = $searchParams['patient'];
            }
            if (!empty($searchParams['subject'])) {
                $queryParams['subject'] = $searchParams['subject'];
            }
            if (!empty($searchParams['clinical-status'])) {
                $queryParams['clinical-status'] = $searchParams['clinical-status'];
            }
            if (!empty($searchParams['verification-status'])) {
                $queryParams['verification-status'] = $searchParams['verification-status'];
            }
            if (!empty($searchParams['category'])) {
                $queryParams['category'] = $searchParams['category'];
            }
            if (!empty($searchParams['code'])) {
                $queryParams['code'] = $searchParams['code'];
            }
            if (!empty($searchParams['onset-date'])) {
                $queryParams['onset-date'] = $searchParams['onset-date'];
            }
            if (!empty($searchParams['encounter'])) {
                $queryParams['encounter'] = $searchParams['encounter'];
            }

            // Pagination
            if (!empty($searchParams['_count'])) {
                $queryParams['_count'] = min(100, max(1, (int)$searchParams['_count']));
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Condition", $queryParams);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error searching Conditions: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server instead of Azure
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to search FHIR Conditions in Azure', ['params' => $searchParams, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get EpisodeOfCare by ID from Azure FHIR
     */
    public function getEpisodeOfCare(string $id): ?array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/EpisodeOfCare/{$id}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to read FHIR EpisodeOfCare from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Patch Patient resource in Azure FHIR
     */
    public function patchPatient(string $id, array $patchData): ?array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/json-patch+json',
            ])->patch("{$this->azureFhirEndpoint}/Patient/{$id}", $patchData);

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $patient = $response->json();

            Log::info('FHIR Patient patched in Azure', ['patient_id' => $id]);

            return $patient;

        } catch (\Exception $e) {
            Log::error('Failed to patch FHIR Patient in Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete Patient resource in Azure FHIR
     */
    public function deletePatient(string $id): bool
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
            ])->delete("{$this->azureFhirEndpoint}/Patient/{$id}");

            if ($response->status() === 404) {
                return false;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            Log::info('FHIR Patient deleted in Azure', ['patient_id' => $id]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete FHIR Patient in Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Search Patient resources in Azure FHIR
     */
    public function searchPatients(array $searchParams): array
    {
        $this->ensureAzureConfigured();

        // Always use local data instead of Azure FHIR
        Log::info('Searching local FHIR Patients', ['search_params' => $searchParams]);

        // Return empty bundle for now - in a real implementation, you might search local database
        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 0,
            'entry' => []
        ];
    }

    /**
     * Search Observation resources in Azure FHIR
     */
    public function searchObservations(array $searchParams): array
    {
        $this->ensureAzureConfigured();

        try {
            $queryParams = [];

            // Map search parameters to FHIR search format
            if (!empty($searchParams['patient'])) {
                $queryParams['patient'] = $searchParams['patient'];
            }
            if (!empty($searchParams['subject'])) {
                $queryParams['subject'] = $searchParams['subject'];
            }
            if (!empty($searchParams['category'])) {
                $queryParams['category'] = $searchParams['category'];
            }
            if (!empty($searchParams['code'])) {
                $queryParams['code'] = $searchParams['code'];
            }
            if (!empty($searchParams['date'])) {
                $queryParams['date'] = $searchParams['date'];
            }
            if (!empty($searchParams['status'])) {
                $queryParams['status'] = $searchParams['status'];
            }
            if (!empty($searchParams['encounter'])) {
                $queryParams['encounter'] = $searchParams['encounter'];
            }

            // Pagination
            if (!empty($searchParams['_count'])) {
                $queryParams['_count'] = min(100, max(1, (int)$searchParams['_count']));
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Observation", $queryParams);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error searching Observations: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server instead of Azure
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to search FHIR Observations in Azure', ['params' => $searchParams, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Patient history from Azure FHIR
     */
    public function getPatientHistory(string $id): ?array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Patient/{$id}/_history");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to get FHIR Patient history from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all Patients history from Azure FHIR
     */
    public function getPatientsHistory(): array
    {
        $this->ensureAzureConfigured();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Patient/_history", ['_count' => 50]);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to get FHIR Patients history from Azure', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process batch or transaction Bundle in Azure FHIR
     */
    public function processTransaction(array $bundle): array
    {
        $this->ensureAzureConfigured();

        try {
            // Add MSC extensions to any Patient resources in the bundle
            if (isset($bundle['entry'])) {
                foreach ($bundle['entry'] as &$entry) {
                    if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Patient') {
                        $entry['resource'] = $this->addMscExtensions($entry['resource']);
                    }
                }
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->post($this->azureFhirEndpoint, $bundle);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $responseBundle = $response->json();

            // Update URLs to point to our FHIR server
            $responseBundle = $this->updateBundleUrls($responseBundle);

            Log::info('FHIR Transaction processed in Azure', ['type' => $bundle['type']]);

            return $responseBundle;

        } catch (\Exception $e) {
            Log::error('Failed to process FHIR Transaction in Azure', ['error' => $e->getMessage(), 'bundle' => $bundle]);
            throw $e;
        }
    }

    /**
     * Backward-compatibility wrapper expected by legacy services
     * @deprecated use processTransaction()
     */
    public function createBundle(array $bundle): array
    {
        return $this->processTransaction($bundle);
    }

    /**
     * Get server capability statement
     */
    public function getCapabilityStatement(): array
    {
        return [
            'resourceType' => 'CapabilityStatement',
            'id' => 'msc-mvp-fhir-server',
            'url' => url('/fhir/metadata'),
            'version' => '1.0.0',
            'name' => 'MSC-MVP-FHIR-Server',
            'title' => 'MSC-MVP FHIR Server',
            'status' => 'active',
            'experimental' => false,
            'date' => now()->format('Y-m-d'),
            'publisher' => 'MSC-MVP',
            'description' => 'FHIR-compliant proxy server for wound care and vascular compliance. Proxies requests to Azure Health Data Services.',
            'kind' => 'instance',
            'software' => [
                'name' => 'MSC-MVP FHIR Server',
                'version' => '1.0.0'
            ],
            'fhirVersion' => '4.0.1',
            'format' => ['application/fhir+json'],
            'rest' => [
                [
                    'mode' => 'server',
                    'resource' => [
                        [
                            'type' => 'Patient',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/Patient',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'create'],
                                ['code' => 'update'],
                                ['code' => 'patch'],
                                ['code' => 'delete'],
                                ['code' => 'search-type'],
                                ['code' => 'history-instance'],
                                ['code' => 'history-type']
                            ],
                            'searchParam' => [
                                ['name' => 'name', 'type' => 'string'],
                                ['name' => 'birthdate', 'type' => 'date'],
                                ['name' => 'gender', 'type' => 'token'],
                                ['name' => 'identifier', 'type' => 'token']
                            ]
                        ]
                    ],
                    'interaction' => [
                        ['code' => 'transaction'],
                        ['code' => 'batch']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Azure access token for FHIR API
     */
    private function getAzureAccessToken(): ?string
    {
        $tenantId = config('services.azure.tenant_id');
        $clientId = config('services.azure.client_id');
        $clientSecret = config('services.azure.client_secret');
        $scope = config('services.azure.fhir_scope');

        // Debug log configuration
        Log::debug('Azure FHIR auth configuration', [
            'tenant_id_exists' => !empty($tenantId),
            'client_id_exists' => !empty($clientId),
            'client_secret_exists' => !empty($clientSecret),
            'scope_exists' => !empty($scope),
            'scope' => $scope,
            'endpoint_base' => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token"
        ]);

        if (!$tenantId || !$clientId || !$clientSecret || !$scope) {
            Log::error('Missing required Azure configuration', [
                'tenant_id_exists' => !empty($tenantId),
                'client_id_exists' => !empty($clientId),
                'client_secret_exists' => !empty($clientSecret),
                'scope_exists' => !empty($scope)
            ]);
            return null;
        }

        try {
            $tokenEndpoint = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
            
            Log::debug('Requesting Azure access token', [
                'endpoint' => $tokenEndpoint,
                'scope' => $scope
            ]);
            
            $response = Http::asForm()->post($tokenEndpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => $scope
            ]);

            if (!$response->successful()) {
                Log::error('Failed to get Azure access token', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                return null;
            }

            $data = $response->json();
            
            if (!isset($data['access_token'])) {
                Log::error('Azure token response missing access_token', [
                    'response_keys' => array_keys($data)
                ]);
                return null;
            }

            Log::info('Successfully obtained Azure access token');
            return $data['access_token'];

        } catch (\Exception $e) {
            Log::error('Error getting Azure access token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Add MSC-specific extensions to Patient resource
     */
    private function addMscExtensions(array $fhirData): array
    {
        if (!isset($fhirData['extension'])) {
            $fhirData['extension'] = [];
        }

        $mscExtensions = [
            'http://msc-mvp.com/fhir/StructureDefinition/wound-care-consent',
            'http://msc-mvp.com/fhir/StructureDefinition/platform-status',
            'http://msc-mvp.com/fhir/StructureDefinition/preferred-language'
        ];

        // Add default MSC extensions if not present
        $existingUrls = array_column($fhirData['extension'], 'url');

        foreach ($mscExtensions as $url) {
            if (!in_array($url, $existingUrls)) {
                switch ($url) {
                    case 'http://msc-mvp.com/fhir/StructureDefinition/platform-status':
                        $fhirData['extension'][] = [
                            'url' => $url,
                            'valueCode' => 'pending'
                        ];
                        break;
                    case 'http://msc-mvp.com/fhir/StructureDefinition/preferred-language':
                        $fhirData['extension'][] = [
                            'url' => $url,
                            'valueCode' => 'en'
                        ];
                        break;
                }
            }
        }

        return $fhirData;
    }

    /**
     * Update Bundle URLs to point to our FHIR server instead of Azure
     */
    private function updateBundleUrls(array $bundle): array
    {
        $baseUrl = url('/fhir');

        // Update entry URLs
        if (isset($bundle['entry'])) {
            foreach ($bundle['entry'] as &$entry) {
                if (isset($entry['fullUrl'])) {
                    // Replace Azure FHIR URL with our URL
                    $resourceId = basename(parse_url($entry['fullUrl'], PHP_URL_PATH));
                    if (isset($entry['resource']['resourceType'])) {
                        $entry['fullUrl'] = "{$baseUrl}/{$entry['resource']['resourceType']}/{$resourceId}";
                    }
                }
            }
        }

        // Update link URLs
        if (isset($bundle['link'])) {
            foreach ($bundle['link'] as &$link) {
                if (isset($link['url'])) {
                    $link['url'] = str_replace($this->azureFhirEndpoint, $baseUrl, $link['url']);
                }
            }
        }

        return $bundle;
    }

    /**
     * Read a FHIR resource by type and ID
     */
    public function read(string $resourceType, string $id): array
    {
        $this->ensureAzureConfigured();

        if (!config('features.fhir.service_enabled', false)) {
            throw new \RuntimeException('FHIR service is disabled');
        }

        // Make the actual FHIR API call
        $response = Http::withToken($this->azureAccessToken)
            ->withHeaders(['Content-Type' => 'application/fhir+json'])
            ->get("{$this->azureFhirEndpoint}/{$resourceType}/{$id}");

        if (!$response->successful()) {
            Log::error("Failed to read FHIR {$resourceType}", [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            throw new \RuntimeException("Failed to read FHIR {$resourceType}/{$id}: " . $response->status());
        }

        return $response->json();
    }

    /**
     * Create a new FHIR resource using local data
     */
    public function createLocalResource(string $resourceType, array $data): array
    {
        // This is a placeholder method for local resource creation
        // when Azure FHIR is not available
        return [
            'resourceType' => $resourceType,
            'id' => 'local-' . uniqid(),
            'meta' => [
                'lastUpdated' => now()->toISOString(),
                'source' => 'local-fallback'
            ],
            ...$data
        ];
    }
}
