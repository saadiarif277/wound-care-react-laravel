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
            $this->logger->info('Creating or updating provider in FHIR');

            // Check if NPI is provided
            if (empty($providerData['npi'])) {
                $this->logger->warning('No NPI provided for provider, creating new provider without NPI search');
                // Create new provider without searching
                $fhirPractitioner = $this->mapToFhirPractitioner($providerData);
                $response = $this->fhirService->createPractitioner($fhirPractitioner);

                $this->auditService->logAccess('provider.created', 'Practitioner', $response['id']);

                $this->logger->info('Provider created successfully in FHIR', [
                    'practitioner_id' => $response['id'],
                    'npi' => $providerData['npi'] ?? 'none'
                ]);

                return $response['id'];
            }

            // Search for existing provider by NPI
            $existingProvider = $this->findProviderByNpi($providerData['npi']);

            if ($existingProvider) {
                $this->auditService->logAccess('provider.accessed', 'Practitioner', $existingProvider['id']);
                return $existingProvider['id'];
            }

            // Create new provider
            $fhirPractitioner = $this->mapToFhirPractitioner($providerData);
            $response = $this->fhirService->createPractitioner($fhirPractitioner);

            $this->auditService->logAccess('provider.created', 'Practitioner', $response['id']);

            $this->logger->info('Provider created successfully in FHIR', [
                'practitioner_id' => $response['id'],
                'npi' => $providerData['npi']
            ]);

            return $response['id'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create/update provider in FHIR', [
                'error' => $e->getMessage(),
                'npi' => $providerData['npi'] ?? 'unknown'
            ]);
            throw new \Exception('Failed to create/update provider: ' . $e->getMessage());
        }
    }

        /**
     * Create or update organization/facility in FHIR
     */
    public function createOrUpdateOrganization(array $facilityData): string
    {
        try {
            $this->logger->info('Creating or updating organization in FHIR');

            // Search for existing organization
            $existingOrg = $this->findOrganizationByNpi($facilityData['npi'] ?? null);

            if ($existingOrg) {
                $this->logger->info('Existing organization found in FHIR', [
                    'organization_id' => $existingOrg['id']
                ]);
                return $existingOrg['id'];
            }

            // Create new organization
            $fhirOrganization = $this->mapToFhirOrganization($facilityData);
            $response = $this->fhirService->createOrganization($fhirOrganization);

            $this->logger->info('Organization created successfully in FHIR', [
                'organization_id' => $response['id'],
                'name' => $facilityData['name']
            ]);

            return $response['id'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create/update organization in FHIR', [
                'error' => $e->getMessage(),
                'organization_name' => $facilityData['name'] ?? 'unknown'
            ]);
            throw new \Exception('Failed to create/update organization: ' . $e->getMessage());
        }
    }

    /**
     * Find provider by NPI
     */
    private function findProviderByNpi(?string $npi): ?array
    {
        if (empty($npi)) {
            return null;
        }

        $searchParams = [
            'identifier' => "http://hl7.org/fhir/sid/us-npi|{$npi}"
        ];

        $results = $this->fhirService->search('Practitioner', $searchParams);

        if (!empty($results['entry'])) {
            return $results['entry'][0]['resource'];
        }

        return null;
    }

    /**
     * Find organization by NPI
     */
    private function findOrganizationByNpi(?string $npi): ?array
    {
        if (!$npi) {
            return null;
        }

        $searchParams = [
            'identifier' => "http://hl7.org/fhir/sid/us-npi|{$npi}"
        ];

        $results = $this->fhirService->search('Organization', $searchParams);

        if (!empty($results['entry'])) {
            return $results['entry'][0]['resource'];
        }

        return null;
    }

    /**
     * Map to FHIR Practitioner resource
     */
    private function mapToFhirPractitioner(array $data): array
    {
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
                    'text' => $data['name'],
                    'family' => $this->extractLastName($data['name']),
                    'given' => $this->extractFirstNames($data['name'])
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
    private function mapToFhirOrganization(array $data): array
    {
        return [
            'resourceType' => 'Organization',
            'identifier' => array_filter([
                !empty($data['npi']) ? [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $data['npi']
                ] : null
            ]),
            'active' => true,
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                            'code' => 'prov',
                            'display' => 'Healthcare Provider'
                        ]
                    ]
                ]
            ],
            'name' => $data['name'],
            'telecom' => array_filter([
                !empty($data['phone']) ? [
                    'system' => 'phone',
                    'value' => $data['phone'],
                    'use' => 'work'
                ] : null,
                !empty($data['fax']) ? [
                    'system' => 'fax',
                    'value' => $data['fax'],
                    'use' => 'work'
                ] : null
            ]),
            'address' => !empty($data['address']) && is_array($data['address']) ? [
                [
                    'use' => 'work',
                    'type' => 'physical',
                    'line' => array_filter([
                        $data['address']['line1'] ?? null,
                        $data['address']['line2'] ?? null
                    ]),
                    'city' => $data['address']['city'] ?? null,
                    'state' => $data['address']['state'] ?? null,
                    'postalCode' => $data['address']['postal_code'] ?? null,
                    'country' => 'USA'
                ]
            ] : []
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
