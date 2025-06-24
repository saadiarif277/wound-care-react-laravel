<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FhirService
{
    private ?string $azureFhirEndpoint;
    private ?string $azureAccessToken;

    public function __construct()
    {
        $this->azureFhirEndpoint = config('services.azure.fhir_endpoint');
        $this->azureAccessToken = $this->getAzureAccessToken();
    }

    /**
     * Ensure Azure FHIR is properly configured
     */
    private function ensureAzureConfigured(): void
    {
        if (!$this->azureAccessToken || !$this->azureFhirEndpoint) {
            throw new \App\Exceptions\FhirException('Azure FHIR service is not properly configured. Please check your Azure FHIR settings in your environment configuration.');
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
        
        try {
            // Add MSC-specific extensions if not present
            $fhirData = $this->addMscExtensions($fhirData);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->post("{$this->azureFhirEndpoint}/Patient", $fhirData);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $patient = $response->json();

            Log::info('FHIR Patient created in Azure', ['patient_id' => $patient['id']]);

            return $patient;

        } catch (\Exception $e) {
            Log::error('Failed to create FHIR Patient in Azure', ['error' => $e->getMessage(), 'data' => $fhirData]);
            throw $e;
        }
    }

    /**
     * Create a new FHIR resource
     */
    public function create(string $resourceType, array $fhirData): array
    {
        $this->ensureAzureConfigured();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->post("{$this->azureFhirEndpoint}/{$resourceType}", $fhirData);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $resource = $response->json();

            Log::info("FHIR {$resourceType} created in Azure", [
                'resource_id' => $resource['id'] ?? null,
                'resource_type' => $resourceType
            ]);

            return $resource;

        } catch (\Exception $e) {
            Log::error("Failed to create FHIR {$resourceType} in Azure", [
                'error' => $e->getMessage(), 
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
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/{$resourceType}", $params);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $bundle = $response->json();

            // Optionally update URLs to point to your FHIR server
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;
        } catch (\Exception $e) {
            Log::error('Failed to search FHIR resource in Azure', [
                'resourceType' => $resourceType,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
    public function getPatient(string $patientFhirId, $productRequest = null): array
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
    public function createPractitioner(array $practitionerData): ?array
    {
        $this->ensureAzureConfigured();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->post("{$this->azureFhirEndpoint}/Practitioner", $practitionerData);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $practitioner = $response->json();

            Log::info('FHIR Practitioner created in Azure', ['practitioner_id' => $practitioner['id']]);

            return $practitioner;

        } catch (\Exception $e) {
            Log::error('Failed to create FHIR Practitioner in Azure', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a new organization
     */
    public function createOrganization(array $organizationData): ?array
    {
        $this->ensureAzureConfigured();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
            ])->post("{$this->azureFhirEndpoint}/Organization", $organizationData);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $organization = $response->json();

            Log::info('FHIR Organization created in Azure', ['organization_id' => $organization['id']]);

            return $organization;

        } catch (\Exception $e) {
            Log::error('Failed to create FHIR Organization in Azure', ['error' => $e->getMessage()]);
            throw $e;
        }
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
        
        try {
            $queryParams = [];

            // Map search parameters to FHIR search format
            if (!empty($searchParams['name'])) {
                $queryParams['name'] = $searchParams['name'];
            }

            if (!empty($searchParams['birthdate'])) {
                $queryParams['birthdate'] = $searchParams['birthdate'];
            }

            if (!empty($searchParams['gender'])) {
                $queryParams['gender'] = $searchParams['gender'];
            }

            if (!empty($searchParams['identifier'])) {
                $queryParams['identifier'] = $searchParams['identifier'];
            }

            // Pagination
            if (!empty($searchParams['_count'])) {
                $queryParams['_count'] = min(100, max(1, $searchParams['_count']));
            }

            if (!empty($searchParams['_page'])) {
                $queryParams['_page'] = max(1, $searchParams['_page']);
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Patient", $queryParams);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            $bundle = $response->json();

            // Update URLs to point to our FHIR server instead of Azure
            $bundle = $this->updateBundleUrls($bundle);

            return $bundle;

        } catch (\Exception $e) {
            Log::error('Failed to search FHIR Patients in Azure', ['params' => $searchParams, 'error' => $e->getMessage()]);
            throw $e;
        }
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

        if (!$tenantId || !$clientId || !$clientSecret || !$this->azureFhirEndpoint) {
            Log::warning('Azure FHIR configuration not complete. FHIR service will be disabled.');
            return null;
        }

        // Cache the token for 50 minutes (Azure tokens typically last 1 hour)
        return Cache::remember('azure_fhir_token', 3000, function () use ($tenantId, $clientId, $clientSecret) {
            try {
                $response = Http::asForm()->post('https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $this->azureFhirEndpoint . '/.default'
                ]);

                if (!$response->successful()) {
                    throw new \Exception("Failed to get Azure access token: " . $response->body());
                }

                $tokenData = $response->json();
                return $tokenData['access_token'];

            } catch (\Exception $e) {
                Log::error('Failed to get Azure access token', ['error' => $e->getMessage()]);
                throw $e;
            }
        });
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
}
