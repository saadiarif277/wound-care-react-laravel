<?php

namespace App\Services\HealthData\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\RequestException;

class AzureFhirClient
{
    private string $baseUrl;
    private string $accessToken;
    private array $defaultHeaders;

    public function __construct(string $fhirBaseUrl, string $accessToken)
    {
        $this->baseUrl = rtrim($fhirBaseUrl, '/');
        $this->accessToken = $accessToken;
        $this->defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/fhir+json',
            'Accept' => 'application/fhir+json',
        ];
    }

    /**
     * Create a FHIR Bundle
     *
     * @param array $bundle The FHIR Bundle to create.
     * @return array The response from the FHIR server, typically the created Bundle with server-assigned IDs.
     * @throws RequestException If the request fails.
     */
    public function createBundle(array $bundle): array
    {
        $response = Http::withHeaders($this->defaultHeaders)
            ->post($this->baseUrl . '/', $bundle); // Bundles are posted to the base URL

        if (!$response->successful()) {
            // Log detailed error information
            logger()->error('Azure FHIR Bundle creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $this->baseUrl . '/',
                // 'bundle_id' => $bundle['id'] ?? 'N/A' // Be careful logging full bundle if it contains PHI
            ]);
            $response->throw(); // Throws Illuminate\Http\Client\RequestException
        }

        return $response->json();
    }

    /**
     * Get a FHIR Resource
     *
     * @param string $resourceType The type of the resource (e.g., 'Patient', 'Observation').
     * @param string $id The ID of the resource.
     * @return array The FHIR resource.
     * @throws RequestException If the request fails.
     */
    public function getResource(string $resourceType, string $id): array
    {
        $url = "{$this->baseUrl}/{$resourceType}/{$id}";
        $response = Http::withHeaders($this->defaultHeaders)
            ->get($url);

        if (!$response->successful()) {
            logger()->error('Azure FHIR resource retrieval failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Search FHIR Resources
     *
     * @param string $resourceType The type of the resource to search.
     * @param array $searchParams Query parameters for the search.
     * @return array The FHIR Bundle containing search results.
     * @throws RequestException If the request fails.
     */
    public function searchResources(string $resourceType, array $searchParams): array
    {
        $url = "{$this->baseUrl}/{$resourceType}";
        $response = Http::withHeaders($this->defaultHeaders)
            ->get($url, $searchParams);

        if (!$response->successful()) {
            logger()->error('Azure FHIR search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url,
                'params' => $searchParams
            ]);
            $response->throw();
        }

        return $response->json();
    }
} 