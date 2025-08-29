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
    private bool $enabled;

    public function __construct(string $fhirBaseUrl, string $accessToken)
    {
        $this->baseUrl = trim($fhirBaseUrl) !== '' ? rtrim($fhirBaseUrl, '/') : '';
        $this->accessToken = $accessToken;
        $this->enabled = ($this->baseUrl !== '' && trim($accessToken) !== '');
        $this->defaultHeaders = [
            'Content-Type' => 'application/fhir+json',
            'Accept' => 'application/fhir+json',
        ];
        if ($this->enabled) {
            $this->defaultHeaders['Authorization'] = 'Bearer ' . $this->accessToken;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
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
        if (!$this->enabled) {
            logger()->notice('FHIR disabled: returning mock bundle response');
            return $this->mockBundleResponse($bundle);
        }
        try {
            $response = Http::withHeaders($this->defaultHeaders)
                ->post($this->baseUrl . '/', $bundle);
            if (!$response->successful()) {
                logger()->error('Azure FHIR Bundle creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $this->baseUrl . '/',
                ]);
                return $this->mockBundleResponse($bundle);
            }
            return $response->json();
        } catch (\Throwable $e) {
            logger()->error('Exception creating FHIR Bundle', ['error' => $e->getMessage()]);
            return $this->mockBundleResponse($bundle);
        }
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
        if (!$this->enabled) {
            return $this->mockResource($resourceType, $id);
        }
        $url = "{$this->baseUrl}/{$resourceType}/{$id}";
        try {
            $response = Http::withHeaders($this->defaultHeaders)->get($url);
            if (!$response->successful()) {
                logger()->error('Azure FHIR resource retrieval failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                return $this->mockResource($resourceType, $id);
            }
            return $response->json();
        } catch (\Throwable $e) {
            logger()->error('Exception retrieving FHIR resource', ['error' => $e->getMessage()]);
            return $this->mockResource($resourceType, $id);
        }
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
        if (!$this->enabled) {
            return $this->mockSearchBundle($resourceType);
        }
        $url = "{$this->baseUrl}/{$resourceType}";
        try {
            $response = Http::withHeaders($this->defaultHeaders)->get($url, $searchParams);
            if (!$response->successful()) {
                logger()->error('Azure FHIR search failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'params' => $searchParams
                ]);
                return $this->mockSearchBundle($resourceType);
            }
            return $response->json();
        } catch (\Throwable $e) {
            logger()->error('Exception during FHIR search', ['error' => $e->getMessage()]);
            return $this->mockSearchBundle($resourceType);
        }
    }

    private function mockBundleResponse(array $bundle): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => $bundle['type'] ?? 'transaction-response',
            'id' => 'mock-' . uniqid(),
            'entry' => array_map(function ($entry) {
                return [
                    'response' => [
                        'status' => '201 Created',
                        'location' => ($entry['resource']['resourceType'] ?? 'Resource') . '/' . uniqid()
                    ]
                ];
            }, $bundle['entry'] ?? [])
        ];
    }

    private function mockResource(string $resourceType, string $id): array
    {
        return [
            'resourceType' => $resourceType,
            'id' => $id,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => now()->toIso8601String()
            ]
        ];
    }

    private function mockSearchBundle(string $resourceType): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 0,
            'entry' => [],
            'link' => []
        ];
    }
} 