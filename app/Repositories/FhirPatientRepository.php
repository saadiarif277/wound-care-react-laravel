<?php

namespace App\Repositories;

use App\Contracts\FhirRepositoryInterface;
use App\Services\FhirClient;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Cache;
use Exception;

class FhirPatientRepository implements FhirRepositoryInterface
{
    protected string $resourceType = 'Patient';
    
    public function __construct(
        protected FhirClient $client,
        protected PhiSafeLogger $logger
    ) {}

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function create(array $data): array
    {
        try {
            $response = $this->client->create($this->resourceType, $data);
            
            $this->logger->info('FHIR Patient created', [
                'resource_id' => $response['id'] ?? null,
                'resource_type' => $this->resourceType
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to create FHIR Patient', [
                'error' => $e->getMessage(),
                'resource_type' => $this->resourceType
            ]);
            throw $e;
        }
    }

    public function find(string $id): ?array
    {
        $cacheKey = "fhir_patient_{$id}";
        
        return Cache::remember($cacheKey, 300, function () use ($id) {
            try {
                $response = $this->client->read($this->resourceType, $id);
                
                $this->logger->logPhiAccess('Patient data accessed', [
                    'resource_id' => $id,
                    'resource_type' => $this->resourceType
                ]);
                
                return $response;
            } catch (Exception $e) {
                if ($e->getCode() === 404) {
                    return null;
                }
                
                $this->logger->error('Failed to read FHIR Patient', [
                    'error' => $e->getMessage(),
                    'resource_id' => $id
                ]);
                throw $e;
            }
        });
    }

    public function update(string $id, array $data): array
    {
        try {
            // Clear cache
            Cache::forget("fhir_patient_{$id}");
            
            $response = $this->client->update($this->resourceType, $id, $data);
            
            $this->logger->info('FHIR Patient updated', [
                'resource_id' => $id,
                'resource_type' => $this->resourceType
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to update FHIR Patient', [
                'error' => $e->getMessage(),
                'resource_id' => $id
            ]);
            throw $e;
        }
    }

    public function delete(string $id): bool
    {
        try {
            // Clear cache
            Cache::forget("fhir_patient_{$id}");
            
            $this->client->delete($this->resourceType, $id);
            
            $this->logger->info('FHIR Patient deleted', [
                'resource_id' => $id,
                'resource_type' => $this->resourceType
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to delete FHIR Patient', [
                'error' => $e->getMessage(),
                'resource_id' => $id
            ]);
            throw $e;
        }
    }

    public function search(array $parameters = []): array
    {
        try {
            $response = $this->client->search($this->resourceType, $parameters);
            
            $this->logger->info('FHIR Patient search performed', [
                'resource_type' => $this->resourceType,
                'result_count' => count($response['entry'] ?? [])
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to search FHIR Patients', [
                'error' => $e->getMessage(),
                'resource_type' => $this->resourceType
            ]);
            throw $e;
        }
    }

    public function history(string $id): array
    {
        try {
            $response = $this->client->history($this->resourceType, $id);
            
            $this->logger->logPhiAccess('Patient history accessed', [
                'resource_id' => $id,
                'resource_type' => $this->resourceType
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to get FHIR Patient history', [
                'error' => $e->getMessage(),
                'resource_id' => $id
            ]);
            throw $e;
        }
    }

    public function batchCreate(array $resources): array
    {
        try {
            $bundle = [
                'resourceType' => 'Bundle',
                'type' => 'batch',
                'entry' => array_map(function ($resource) {
                    return [
                        'resource' => $resource,
                        'request' => [
                            'method' => 'POST',
                            'url' => $this->resourceType
                        ]
                    ];
                }, $resources)
            ];
            
            $response = $this->client->batch($bundle);
            
            $this->logger->info('FHIR Patient batch created', [
                'resource_type' => $this->resourceType,
                'batch_size' => count($resources)
            ]);
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to batch create FHIR Patients', [
                'error' => $e->getMessage(),
                'resource_type' => $this->resourceType,
                'batch_size' => count($resources)
            ]);
            throw $e;
        }
    }

    public function exists(string $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Search patients by identifier (e.g., MRN, SSN)
     */
    public function findByIdentifier(string $system, string $value): ?array
    {
        $results = $this->search([
            'identifier' => "{$system}|{$value}"
        ]);
        
        if (!empty($results['entry'])) {
            return $results['entry'][0]['resource'];
        }
        
        return null;
    }

    /**
     * Search patients by name
     */
    public function searchByName(string $family, ?string $given = null): array
    {
        $parameters = ['family' => $family];
        
        if ($given) {
            $parameters['given'] = $given;
        }
        
        return $this->search($parameters);
    }

    /**
     * Get active patients for an organization
     */
    public function getActivePatientsByOrganization(string $organizationId): array
    {
        return $this->search([
            'organization' => $organizationId,
            'active' => 'true',
            '_count' => 100,
            '_sort' => '-_lastUpdated'
        ]);
    }
}