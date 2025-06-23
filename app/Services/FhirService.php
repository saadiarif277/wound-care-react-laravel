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
     * Create a new Patient resource in Azure FHIR
     */
    public function createPatient(array $fhirData): array
    {
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
// ... existing code for AzureFhirClient class

/**
 * Search for FHIR resources.
 * @param string $resourceType
 * @param array $params
 * @return array
 */
public function search(string $resourceType, array $params = []): array
{
    // Example implementation using GET with query parameters
    $query = http_build_query($params);
    $url = "{$this->azureFhirEndpoint}/{$resourceType}";
    try {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->azureAccessToken}",
            'Accept' => 'application/fhir+json',
        ])->get($url, $params);

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
    public function getPatient(string $patientFhirId, $productRequest = null)
    {
        // Use the existing getPatientById method
        $fhirPatient = $this->getPatientById($patientFhirId);

        if (!$fhirPatient) {
            throw new \Exception("Patient not found with FHIR ID: {$patientFhirId}");
        }

        // Convert array to object for consistent interface
        return json_decode(json_encode($fhirPatient));
    }

    /**
     * Create a new practitioner
     *
     * @param array $practitionerData
     * @return array|null
     */
    public function createPractitioner(array $practitionerData): ?array
    {
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
     *
     * @param array $organizationData
     * @return array|null
     */
    public function createOrganization(array $organizationData): ?array
    {
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
     * Patch Patient resource in Azure FHIR
     */
    public function patchPatient(string $id, array $patchData): ?array
    {
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
     * Get Patient history from Azure FHIR
     */
    public function getPatientHistory(string $id): ?array
    {
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

    /**
     * Search Observation resources in Azure FHIR
     */
    public function searchObservations(array $searchParams): array
    {
        try {
            $queryParams = [];

            // Map search parameters to FHIR search format
            // Add more parameters as needed based on FHIR Observation search capabilities
            if (!empty($searchParams['patient'])) {
                $queryParams['patient'] = $searchParams['patient']; // Or subject, depending on how you store patient reference
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
                $queryParams['date'] = $searchParams['date']; // Can be a date or a period
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
            // _page is not a standard FHIR search parameter for pagination directly.
            // FHIR uses link relations in the bundle (next, previous) for pagination.
            // However, if your service layer or Azure FHIR supports an offset or page-like param, handle it here.
            // For simplicity, we're not implementing full cursor-based pagination here but relying on _count.

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
     * Search Coverage resources in Azure FHIR
     */
    public function searchCoverage(array $searchParams): array
    {
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

            // Extract entries from bundle
            $coverages = [];
            if (isset($bundle['entry'])) {
                foreach ($bundle['entry'] as $entry) {
                    if (isset($entry['resource'])) {
                        $coverages[] = $entry['resource'];
                    }
                }
            }

            return $coverages;

        } catch (\Exception $e) {
            Log::error('Failed to search FHIR Coverage in Azure', ['params' => $searchParams, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Practitioner by ID from Azure FHIR
     */
    public function getPractitioner(string $id): ?array
    {
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
            Log::error('Failed to read FHIR Practitioner from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Organization by ID from Azure FHIR
     */
    public function getOrganization(string $id): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/Organization/{$id}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to read FHIR Organization from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get QuestionnaireResponse by ID from Azure FHIR
     */
    public function getQuestionnaireResponse(string $id): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/QuestionnaireResponse/{$id}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to read FHIR QuestionnaireResponse from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get DeviceRequest by ID from Azure FHIR
     */
    public function getDeviceRequest(string $id): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Accept' => 'application/fhir+json',
            ])->get("{$this->azureFhirEndpoint}/DeviceRequest/{$id}");

            if ($response->status() === 404) {
                return null;
            }

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR API error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Failed to read FHIR DeviceRequest from Azure', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get EpisodeOfCare by ID from Azure FHIR
     */
    public function getEpisodeOfCare(string $id): ?array
    {
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
     * Execute a FHIR Bundle transaction
     * This method allows batching multiple FHIR operations in a single request
     */
    public function executeBundle(array $bundle): array
    {
        try {
            // Validate bundle structure
            if (!isset($bundle['resourceType']) || $bundle['resourceType'] !== 'Bundle') {
                throw new \InvalidArgumentException('Invalid bundle structure');
            }

            if (!isset($bundle['type']) || !in_array($bundle['type'], ['batch', 'transaction'])) {
                throw new \InvalidArgumentException('Bundle type must be "batch" or "transaction"');
            }

            // Check cache first for batch operations
            if ($bundle['type'] === 'batch') {
                $cachedBundle = $this->checkBundleCache($bundle);
                if ($cachedBundle && $this->isBundleCacheFresh($cachedBundle)) {
                    Log::info('Bundle retrieved from cache', [
                        'entries_count' => count($bundle['entry'] ?? [])
                    ]);
                    return $cachedBundle;
                }
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->azureAccessToken}",
                'Content-Type' => 'application/fhir+json',
                'Accept' => 'application/fhir+json',
            ])->post($this->azureFhirEndpoint, $bundle);

            if (!$response->successful()) {
                throw new \Exception("Azure FHIR Bundle execution error: " . $response->body());
            }

            $responseBundle = $response->json();

            // Cache successful batch responses
            if ($bundle['type'] === 'batch' && $this->shouldCacheBundle($responseBundle)) {
                $this->cacheBundle($bundle, $responseBundle);
            }

            Log::info('FHIR Bundle executed successfully', [
                'type' => $bundle['type'],
                'entries_count' => count($bundle['entry'] ?? []),
                'response_entries' => count($responseBundle['entry'] ?? [])
            ]);

            return $responseBundle;

        } catch (\Exception $e) {
            Log::error('Failed to execute FHIR Bundle', [
                'type' => $bundle['type'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if bundle results are in cache
     */
    private function checkBundleCache(array $bundle): ?array
    {
        $cacheKey = $this->generateBundleCacheKey($bundle);
        return Cache::get($cacheKey);
    }

    /**
     * Cache bundle results
     */
    private function cacheBundle(array $bundle, array $response): void
    {
        $cacheKey = $this->generateBundleCacheKey($bundle);
        $ttl = $this->determineBundleCacheTTL($bundle);

        Cache::put($cacheKey, $response, $ttl);
    }

    /**
     * Generate cache key for bundle
     */
    private function generateBundleCacheKey(array $bundle): string
    {
        $entries = $bundle['entry'] ?? [];
        $requests = array_map(function ($entry) {
            return $entry['request'] ?? [];
        }, $entries);

        return 'fhir:bundle:' . md5(json_encode($requests));
    }

    /**
     * Determine appropriate TTL for bundle cache
     */
    private function determineBundleCacheTTL(array $bundle): int
    {
        // Check if bundle contains only read operations
        $entries = $bundle['entry'] ?? [];
        $hasOnlyReads = true;

        foreach ($entries as $entry) {
            $method = $entry['request']['method'] ?? '';
            if (!in_array($method, ['GET', 'HEAD'])) {
                $hasOnlyReads = false;
                break;
            }
        }

        // Read-only bundles can be cached longer
        return $hasOnlyReads ? 3600 : 300; // 1 hour for reads, 5 minutes for mixed
    }

    /**
     * Check if cached bundle is still fresh
     */
    private function isBundleCacheFresh(array $cachedBundle): bool
    {
        // Could implement additional freshness checks here
        // For now, we rely on TTL expiration
        return true;
    }

    /**
     * Determine if bundle response should be cached
     */
    private function shouldCacheBundle(array $responseBundle): bool
    {
        // Only cache successful responses
        $entries = $responseBundle['entry'] ?? [];

        foreach ($entries as $entry) {
            $status = $entry['response']['status'] ?? '';
            // Don't cache if any entry failed
            if (strpos($status, '2') !== 0 && strpos($status, '304') !== 0) {
                return false;
            }
        }

        return true;
    }
}
