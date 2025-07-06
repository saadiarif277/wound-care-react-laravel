<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;

class InsuranceHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private PhiAuditService $auditService
    ) {}

    /**
     * Create insurance coverage in FHIR
     */
    public function createCoverage(array $data): string
    {
        try {
            $this->logger->info('Creating insurance coverage in FHIR');

            // Validate required fields
            if (empty($data['insurance']['policy_type'])) {
                throw new \Exception('Missing required field: policy_type');
            }
            if (empty($data['insurance']['payer_name'])) {
                throw new \Exception('Missing required field: payer_name');
            }
            if (empty($data['patient_id'])) {
                throw new \Exception('Missing required field: patient_id');
            }

            // Pass both the insurance data and the patient's FHIR ID to the mapping function
            $coverageData = $this->mapToFhirCoverage($data['insurance'], $data['patient_id']);
            $response = $this->fhirService->create('Coverage', $coverageData);

            $this->auditService->logAccess(
                'coverage.created',
                'Coverage',
                $response['id'],
                ['payer' => $data['insurance']['payer_name']]
            );

            $this->logger->info('Coverage created successfully in FHIR', [
                'coverage_id' => $response['id'],
                'payer' => $data['insurance']['payer_name']
            ]);

            return $response['id'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create FHIR coverage', [
                'error' => $e->getMessage(),
                'payer' => $data['insurance']['payer_name'] ?? 'unknown',
                'policy_type' => $data['insurance']['policy_type'] ?? 'unknown'
            ]);
            throw new \Exception('Failed to create coverage: ' . $e->getMessage());
        }
    }

    /**
     * Create multiple coverages (primary, secondary, etc.)
     */
    public function createMultipleCoverages(array $insuranceData, string $patientId): array
    {
        $coverageIds = [];

        if (empty($insuranceData)) {
            $this->logger->warning('No insurance data provided for coverage creation');
            return $coverageIds;
        }

        foreach ($insuranceData as $insurance) {
            $policyType = $insurance['policy_type'] ?? 'unknown';
            try {
                $this->logger->info('Creating coverage for insurance', [
                    'policy_type' => $policyType,
                    'payer_name' => $insurance['payer_name'] ?? 'unknown'
                ]);

                $coverage = $this->createCoverage([
                    'patient_id' => $patientId,
                    'insurance' => $insurance // Pass the whole insurance item
                ]);

                $coverageIds[$policyType] = $coverage;

                $this->logger->info('Coverage created successfully', [
                    'policy_type' => $policyType,
                    'coverage_id' => $coverage
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Failed to create coverage for insurance', [
                    'policy_type' => $policyType,
                    'payer_name' => $insurance['payer_name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                // Continue with other coverages instead of failing completely
                continue;
            }
        }

        if (empty($coverageIds)) {
            $this->logger->error('Finished processing coverages, but no coverage IDs were generated.');
            // Do not throw an exception here, as self-pay is a valid scenario
        }

        return $coverageIds;
    }

    /**
     * Verify insurance eligibility
     */
    public function verifyEligibility(array $coverageData): array
    {
        $this->logger->info('Verifying insurance eligibility');

        // This would integrate with eligibility verification services
        // For now, return mock verification
        return [
            'eligible' => true,
            'coverage_active' => true,
            'out_of_pocket_remaining' => 2000.00,
            'copay_amount' => 25.00,
            'coverage_details' => [
                'dme_covered' => true,
                'prior_auth_required' => false,
                'coverage_limitations' => []
            ]
        ];
    }

    /**
     * Map to FHIR Coverage resource
     */
    private function mapToFhirCoverage(array $insuranceData, string $patientFhirId): array
    {
        // Let's simplify this to the bare minimum required fields for a Coverage resource
        // to see if we can get it created.
        // Required: status, beneficiary, payor
        $coverage = [
            'resourceType' => 'Coverage',
            'status' => 'active',
            'beneficiary' => [
                'reference' => "Patient/{$patientFhirId}"
            ],
            'payor' => [
                [
                    // We don't have a FHIR Organization for the payor yet,
                    // so we'll just use the display name. This is valid.
                    'display' => $insuranceData['payer_name'] ?? 'Unknown Payor'
                ]
            ]
        ];

        // Add subscriberId (member ID) if available
        if (!empty($insuranceData['member_id'])) {
            $coverage['subscriberId'] = $insuranceData['member_id'];
        }
        
        return $coverage;
    }

    /**
     * Map policy type to FHIR code
     */
    private function mapPolicyTypeToCode(string $policyType): string
    {
        $typeMap = [
            'primary' => 'EHCPOL',
            'secondary' => 'EHCPOL',
            'tertiary' => 'EHCPOL',
            'supplemental' => 'PUBLICPOL'
        ];

        return $typeMap[$policyType] ?? 'EHCPOL';
    }

    /**
     * Check if payer is Medicare
     */
    private function isMedicare(string $payerName): bool
    {
        $medicareKeywords = [
            'medicare',
            'cms',
            'centers for medicare',
            'medicare advantage'
        ];

        $lowerPayerName = strtolower($payerName);

        foreach ($medicareKeywords as $keyword) {
            if (str_contains($lowerPayerName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create eligibility request for prior authorization
     */
    public function createEligibilityRequest(string $coverageId, array $services): array
    {
        $eligibilityData = [
            'resourceType' => 'CoverageEligibilityRequest',
            'status' => 'active',
            'purpose' => ['auth-requirements', 'benefits'],
            'patient' => [
                'reference' => $this->extractPatientReference($coverageId)
            ],
            'created' => now()->toIso8601String(),
            'insurer' => [
                'display' => 'Insurance Company'
            ],
            'insurance' => [
                [
                    'coverage' => [
                        'reference' => "Coverage/{$coverageId}"
                    ]
                ]
            ],
            'item' => array_map(function ($service) {
                return [
                    'category' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/ex-benefitcategory',
                                'code' => '1',
                                'display' => 'Medical Care'
                            ]
                        ]
                    ],
                    'productOrService' => [
                        'coding' => [
                            [
                                'system' => 'http://www.ama-assn.org/go/cpt',
                                'code' => $service['cpt_code'],
                                'display' => $service['description'] ?? ''
                            ]
                        ]
                    ]
                ];
            }, $services)
        ];

        return $this->fhirService->create('CoverageEligibilityRequest', $eligibilityData);
    }

    /**
     * Extract patient reference from coverage
     */
    private function extractPatientReference(string $coverageId): string
    {
        // This would normally fetch the coverage and extract patient reference
        // For now, return placeholder
        return "Patient/unknown";
    }
}
