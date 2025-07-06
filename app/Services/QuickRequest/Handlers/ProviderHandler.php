<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;

class ProviderHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private PhiAuditService $auditService
    ) {}

    /**
     * Create or update provider in FHIR
     */
    public function createOrUpdateProvider(array $providerData): string
    {
        try {
            $this->logger->info('Starting provider sync process with FHIR.');

            // 1. If a FHIR ID is already passed in, use it.
            if (!empty($providerData['fhir_id'])) {
                $this->logger->info('Provider already has a FHIR ID.', ['fhir_id' => $providerData['fhir_id']]);
                // Optional: We could add a call here to update the FHIR record if data has changed.
                return $providerData['fhir_id'];
            }

            // 2. If NPI is provided, search FHIR to prevent duplicates.
            if (!empty($providerData['npi'])) {
                try {
                    $existingProvider = $this->findExistingProvider($providerData['npi']);
                    if ($existingProvider) {
                        $this->logger->info('Found existing provider in FHIR by NPI.', [
                            'practitioner_id' => $existingProvider['id'],
                            'npi' => $providerData['npi']
                        ]);
                        // NOTE: The calling service should save this ID to the local provider record.
                        $this->auditService->logAccess('provider.accessed', 'Practitioner', $existingProvider['id']);
                        return $existingProvider['id'];
                    }
                } catch (\Exception $e) {
                    $this->logger->error('FHIR search failed. Proceeding to create, but this may result in a duplicate.', [
                        'error' => $e->getMessage(),
                        'npi' => $providerData['npi']
                    ]);
                    // Don't rethrow, allow creation to be attempted.
                }
            }

            // 3. If no existing provider was found, create a new one in FHIR.
            $this->logger->info('No existing FHIR provider found. Creating a new one.');
            $fhirPractitioner = $this->mapToFhirPractitioner($providerData);
            
            try {
                $response = $this->fhirService->create('Practitioner', $fhirPractitioner);
                $this->auditService->logAccess('provider.created', 'Practitioner', $response['id']);
                $this->logger->info('Provider created successfully in FHIR.', [
                    'practitioner_id' => $response['id'],
                    'npi' => $providerData['npi'] ?? 'none'
                ]);
                // NOTE: The calling service should save this new ID to the local provider record.
                return $response['id'];
            } catch (\Exception $e) {
                $this->logger->error('FHIR is unavailable, falling back to a local-only workflow for this request.', [
                    'error' => $e->getMessage(),
                    'npi' => $providerData['npi'] ?? 'none'
                ]);
                // Return a temporary ID to allow the workflow to continue.
                // A background job should handle syncing this provider later.
                return 'local-provider-fallback-id';
            }

        } catch (\Exception $e) {
            $this->logger->error('An unexpected error occurred during provider sync.', [
                'error' => $e->getMessage(),
                'npi' => $providerData['npi'] ?? 'none'
            ]);
            // For critical failures, rethrow to stop the process.
            throw $e;
        }
    }

    /**
     * Create or update organization in FHIR
     */
    public function createOrUpdateOrganization(array $facilityData): string
    {
        try {
            $this->logger->info('Starting organization sync process with FHIR.');

            // 1. If a FHIR ID is already passed in, use it.
            if (!empty($facilityData['fhir_id'])) {
                $this->logger->info('Organization already has a FHIR ID.', ['fhir_id' => $facilityData['fhir_id']]);
                return $facilityData['fhir_id'];
            }

            // 2. If NPI is provided, search FHIR to prevent duplicates.
            if (!empty($facilityData['npi'])) {
                try {
                    $existingOrg = $this->findExistingOrganization($facilityData['npi']);
                    if ($existingOrg) {
                        $this->logger->info('Found existing organization in FHIR by NPI.', [
                            'organization_id' => $existingOrg['id'],
                            'npi' => $facilityData['npi']
                        ]);
                        $this->auditService->logAccess('organization.accessed', 'Organization', $existingOrg['id']);
                        return $existingOrg['id'];
                    }
                } catch (\Exception $e) {
                    $this->logger->error('FHIR search failed. Proceeding to create, but this may result in a duplicate.', [
                        'error' => $e->getMessage(),
                        'npi' => $facilityData['npi']
                    ]);
                }
            }

            // 3. If no existing organization was found, create a new one in FHIR.
            $this->logger->info('No existing FHIR organization found. Creating a new one.');
            $fhirOrg = $this->mapToFhirOrganization($facilityData);

            try {
                $response = $this->fhirService->create('Organization', $fhirOrg);
                $this->auditService->logAccess('organization.created', 'Organization', $response['id']);
                $this->logger->info('Organization created successfully in FHIR.', [
                    'organization_id' => $response['id'],
                    'npi' => $facilityData['npi'] ?? 'none'
                ]);
                return $response['id'];
            } catch (\Exception $e) {
                $this->logger->error('FHIR is unavailable, falling back to a local-only workflow for this request.', [
                    'error' => $e->getMessage(),
                    'npi' => $facilityData['npi'] ?? 'none'
                ]);
                return 'local-organization-fallback-id';
            }

        } catch (\Exception $e) {
            $this->logger->error('An unexpected error occurred during organization sync.', [
                'error' => $e->getMessage(),
                'npi' => $facilityData['npi'] ?? 'none'
            ]);
            throw $e;
        }
    }

    /**
     * Find existing provider by NPI
     */
    private function findExistingProvider(string $npi): ?array
    {
        $this->logger->info('Searching for existing provider by NPI', ['npi' => $npi]);

        $searchParams = [
            'identifier' => "http://hl7.org/fhir/sid/us-npi|{$npi}"
        ];

        $results = $this->fhirService->search('Practitioner', $searchParams);

        if (!empty($results['entry'])) {
            $this->logger->info('Existing provider found', ['provider_id' => $results['entry'][0]['resource']['id']]);
            return $results['entry'][0]['resource'];
        }

        $this->logger->info('No existing provider found for NPI', ['npi' => $npi]);
        return null;
    }

    /**
     * Find existing organization by NPI
     */
    private function findExistingOrganization(string $npi): ?array
    {
        $this->logger->info('Searching for existing organization by NPI', ['npi' => $npi]);

        $searchParams = [
            'identifier' => "http://hl7.org/fhir/sid/us-npi|{$npi}"
        ];

        $results = $this->fhirService->search('Organization', $searchParams);

        if (!empty($results['entry'])) {
            $this->logger->info('Existing organization found', ['organization_id' => $results['entry'][0]['resource']['id']]);
            return $results['entry'][0]['resource'];
        }

        $this->logger->info('No existing organization found for NPI', ['npi' => $npi]);
        return null;
    }

    /**
     * Map to FHIR Practitioner resource
     */
    private function mapToFhirPractitioner(array $data): array
    {
        $practitionerName = $data['name'] ?? 'Unknown Practitioner';
        if (!isset($data['name'])) {
            $this->logger->warning('Provider data is missing a name. Using default.', ['provider_data' => $data]);
        }

        return [
            'resourceType' => 'Practitioner',
            'identifier' => [
                [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $data['npi']
                ]
            ],
            'active' => true,
            'name' => [
                [
                    'text' => $practitionerName,
                    'family' => $this->extractLastName($practitionerName),
                    'given' => $this->extractFirstNames($practitionerName)
                ]
            ],
            'telecom' => array_filter([
                !empty($data['phone']) ? [
                    'system' => 'phone',
                    'value' => $data['phone'],
                    'use' => 'work'
                ] : null,
                !empty($data['email']) ? [
                    'system' => 'email',
                    'value' => $data['email'],
                    'use' => 'work'
                ] : null
            ]),
            'qualification' => !empty($data['specialty']) ? [
                [
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://nucc.org/provider-taxonomy',
                                'code' => $this->mapSpecialtyToTaxonomy($data['specialty']),
                                'display' => $data['specialty']
                            ]
                        ]
                    ]
                ]
            ] : []
        ];
    }

    /**
     * Map to FHIR Organization resource
     */
    private function mapToFhirOrganization(array $facilityData): array
    {
        $this->logger->info('Mapping facility data to FHIR Organization resource');

        $organizationName = $facilityData['name'] ?? 'Unnamed Facility';
        if (!isset($facilityData['name'])) {
            $this->logger->warning('Facility data is missing a name. Using default.', ['facility_data' => $facilityData]);
        }

        return [
            'resourceType' => 'Organization',
            'identifier' => [
                [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $facilityData['npi'] ?? null
                ]
            ],
            'name' => $organizationName,
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => $facilityData['phone'] ?? null
                ]
            ],
            'address' => [
                [
                    'line' => [$facilityData['address']['address_line1'] ?? null],
                    'city' => $facilityData['address']['city'] ?? null,
                    'state' => $facilityData['address']['state'] ?? null,
                    'postalCode' => $facilityData['address']['postalCode'] ?? null,
                    'country' => 'USA'
                ]
            ]
        ];
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return end($parts) ?: 'Unknown';
    }

    /**
     * Extract first names from full name
     */
    private function extractFirstNames(string $fullName): array
    {
        $parts = explode(' ', trim($fullName));
        array_pop($parts); // Remove last name
        return !empty($parts) ? $parts : ['Unknown'];
    }

    /**
     * Map specialty to NUCC taxonomy code
     */
    private function mapSpecialtyToTaxonomy(string $specialty): string
    {
        $taxonomyMap = [
            'Vascular Surgery' => '208600000X',
            'Wound Care' => '163WW0101X',
            'General Surgery' => '208600000X',
            'Podiatry' => '213E00000X',
            'Internal Medicine' => '207R00000X',
            'Family Medicine' => '207Q00000X',
            'Cardiology' => '207RC0000X',
            'Dermatology' => '207N00000X',
            'Infectious Disease' => '207RI0011X',
            'Physical Medicine' => '208100000X'
        ];

        return $taxonomyMap[$specialty] ?? '208D00000X'; // Default to General Practice
    }
}
