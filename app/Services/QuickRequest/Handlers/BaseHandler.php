<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;
use App\Services\FeatureFlagService;

abstract class BaseHandler
{
    public function __construct(
        protected FhirService $fhirService,
        protected PhiSafeLogger $logger,
        protected PhiAuditService $auditService
    ) {}

    /**
     * Execute FHIR operation with feature flag check
     *
     * @param string $operation
     * @param callable $fhirOperation
     * @param callable $fallbackOperation
     * @return mixed
     */
    protected function executeFhirOperation(string $operation, callable $fhirOperation, callable $fallbackOperation)
    {
        if (!FeatureFlagService::isFhirEnabled()) {
            $this->logger->info("FHIR {$operation} disabled - using fallback");
            return $fallbackOperation();
        }

        try {
            $this->logger->info("Executing FHIR {$operation}");
            return $fhirOperation();
        } catch (\Exception $e) {
            $this->logger->error("FHIR {$operation} failed", [
                'error' => $e->getMessage(),
                'operation' => $operation
            ]);
            
            // If FHIR fails, use fallback
            $this->logger->info("Using fallback for {$operation} due to FHIR failure");
            return $fallbackOperation();
        }
    }

    /**
     * Generate a local ID for fallback operations
     *
     * @param string $prefix
     * @param array $data
     * @return string
     */
    protected function generateLocalId(string $prefix, array $data = []): string
    {
        $identifier = uniqid();
        
        // Use meaningful identifiers if available
        if (!empty($data['npi'])) {
            $identifier = $data['npi'];
        } elseif (!empty($data['email'])) {
            $identifier = md5($data['email']);
        } elseif (!empty($data['first_name']) && !empty($data['last_name'])) {
            $identifier = md5($data['first_name'] . $data['last_name'] . ($data['dob'] ?? ''));
        }

        return "local-{$prefix}-{$identifier}";
    }

    /**
     * Log audit access with error handling
     *
     * @param string $action
     * @param string $resourceType
     * @param string $resourceId
     * @param array $context
     */
    protected function logAuditAccess(string $action, string $resourceType, string $resourceId, array $context = []): void
    {
        try {
            $this->auditService->logAccess($action, $resourceType, $resourceId, $context);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log audit access', [
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate required fields in data array
     *
     * @param array $data
     * @param array $requiredFields
     * @throws \InvalidArgumentException
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', $missingFields));
        }
    }

    /**
     * Sanitize and normalize data
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeData(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return trim($value);
            }
            return $value;
        }, $data);
    }

    /**
     * Search for existing FHIR resource
     *
     * @param string $resourceType
     * @param array $searchParams
     * @return array|null
     */
    protected function findExistingFhirResource(string $resourceType, array $searchParams): ?array
    {
        if (!FeatureFlagService::isFhirEnabled()) {
            return null;
        }

        try {
            $results = $this->fhirService->search($resourceType, $searchParams);
            
            if (!empty($results['entry'])) {
                return $results['entry'][0]['resource'];
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to search for existing {$resourceType}", [
                'error' => $e->getMessage(),
                'search_params' => $searchParams
            ]);
        }

        return null;
    }

    /**
     * Create FHIR resource with error handling
     *
     * @param string $method
     * @param array $resource
     * @return array
     * @throws \Exception
     */
    protected function createFhirResource(string $method, array $resource): array
    {
        if (!FeatureFlagService::isFhirEnabled()) {
            throw new \Exception('FHIR operations are disabled');
        }

        try {
            return $this->fhirService->{$method}($resource);
        } catch (\Exception $e) {
            $this->logger->error("Failed to create FHIR resource via {$method}", [
                'error' => $e->getMessage(),
                'resource_type' => $resource['resourceType'] ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * Extract name parts from full name
     *
     * @param string $fullName
     * @return array ['first' => string, 'last' => string, 'middle' => string|null]
     */
    protected function parseFullName(string $fullName): array
    {
        $parts = array_filter(explode(' ', trim($fullName)));
        
        if (empty($parts)) {
            return ['first' => '', 'last' => '', 'middle' => null];
        }

        if (count($parts) === 1) {
            return ['first' => $parts[0], 'last' => '', 'middle' => null];
        }

        $first = array_shift($parts);
        $last = array_pop($parts);
        $middle = !empty($parts) ? implode(' ', $parts) : null;

        return [
            'first' => $first,
            'last' => $last,
            'middle' => $middle
        ];
    }

    /**
     * Format phone number to standard format
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as (xxx) xxx-xxxx if 10 digits
        if (strlen($cleaned) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($cleaned, 0, 3),
                substr($cleaned, 3, 3),
                substr($cleaned, 6, 4)
            );
        }

        return $phone; // Return original if can't format
    }

    /**
     * Map gender to FHIR standard
     *
     * @param string $gender
     * @return string
     */
    protected function mapGenderToFhir(string $gender): string
    {
        $gender = strtolower(trim($gender));
        
        return match ($gender) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other', 'o' => 'other',
            default => 'unknown'
        };
    }
} 