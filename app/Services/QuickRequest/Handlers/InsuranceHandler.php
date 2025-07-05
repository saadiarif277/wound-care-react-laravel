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

            $coverageData = $this->mapToFhirCoverage($data);
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

        // Transform flat InsuranceData structure to nested format for FHIR
        $coverages = $this->transformInsuranceData($insuranceData);

        foreach ($coverages as $index => $insurance) {
            try {
                $this->logger->info('Creating coverage for insurance', [
                    'index' => $index,
                    'policy_type' => $insurance['policy_type'] ?? 'unknown',
                    'payer_name' => $insurance['payer_name'] ?? 'unknown'
                ]);

                $coverage = $this->createCoverage([
                    'patient_id' => $patientId,
                    'insurance' => $insurance
                ]);

                $coverageIds[$insurance['policy_type']] = $coverage;

                $this->logger->info('Coverage created successfully', [
                    'policy_type' => $insurance['policy_type'],
                    'coverage_id' => $coverage
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Failed to create coverage for insurance', [
                    'index' => $index,
                    'policy_type' => $insurance['policy_type'] ?? 'unknown',
                    'payer_name' => $insurance['payer_name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                // Continue with other coverages instead of failing completely
                continue;
            }
        }

        if (empty($coverageIds)) {
            throw new \Exception('Failed to create any insurance coverages');
        }

        return $coverageIds;
    }

    /**
     * Transform flat InsuranceData structure to nested format for FHIR
     */
    private function transformInsuranceData(array $insuranceData): array
    {
        $coverages = [];

        // Primary insurance
        if (!empty($insuranceData['primary_name'])) {
            $coverages[] = [
                'policy_type' => 'primary',
                'payer_name' => $insuranceData['primary_name'],
                'member_id' => $insuranceData['primary_member_id'] ?? '',
                'plan_type' => $insuranceData['primary_plan_type'] ?? '',
                'effective_date' => now()->startOfYear()->toIso8601String(),
                'termination_date' => now()->endOfYear()->toIso8601String()
            ];
        }

        // Secondary insurance
        if (!empty($insuranceData['has_secondary']) && !empty($insuranceData['secondary_name'])) {
            $coverages[] = [
                'policy_type' => 'secondary',
                'payer_name' => $insuranceData['secondary_name'],
                'member_id' => $insuranceData['secondary_member_id'] ?? '',
                'plan_type' => $insuranceData['secondary_plan_type'] ?? '',
                'effective_date' => now()->startOfYear()->toIso8601String(),
                'termination_date' => now()->endOfYear()->toIso8601String()
            ];
        }

        // If no insurance data provided, create a default coverage
        if (empty($coverages)) {
            $coverages[] = [
                'policy_type' => 'primary',
                'payer_name' => 'Self Pay',
                'member_id' => '',
                'plan_type' => 'self_pay',
                'effective_date' => now()->startOfYear()->toIso8601String(),
                'termination_date' => now()->endOfYear()->toIso8601String()
            ];
        }

        return $coverages;
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
            'deductible_remaining' => 500.00,
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
    private function mapToFhirCoverage(array $data): array
    {
        $insurance = $data['insurance'];

        // Ensure required fields have defaults
        $policyType = $insurance['policy_type'] ?? 'primary';
        $payerName = $insurance['payer_name'] ?? 'Unknown Insurance';
        $memberId = $insurance['member_id'] ?? '';
        $patientId = $data['patient_id'] ?? '';

        $coverageData = [
            'resourceType' => 'Coverage',
            'status' => 'active',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                        'code' => $this->mapPolicyTypeToCode($policyType),
                        'display' => ucfirst($policyType)
                    ]
                ]
            ],
            'subscriber' => [
                'reference' => "Patient/{$patientId}"
            ],
            'subscriberId' => $memberId,
            'beneficiary' => [
                'reference' => "Patient/{$patientId}"
            ],
            'relationship' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/subscriber-relationship',
                        'code' => 'self',
                        'display' => 'Self'
                    ]
                ]
            ],
            'period' => [
                'start' => $insurance['effective_date'] ?? now()->startOfYear()->toIso8601String(),
                'end' => $insurance['termination_date'] ?? now()->endOfYear()->toIso8601String()
            ],
            'payor' => [
                [
                    'display' => $payerName
                ]
            ],
            'class' => array_filter([
                !empty($insurance['group_number']) ? [
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/coverage-class',
                                'code' => 'group',
                                'display' => 'Group'
                            ]
                        ]
                    ],
                    'value' => $insurance['group_number'],
                    'name' => 'Group Number'
                ] : null,
                !empty($insurance['plan_name']) ? [
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/coverage-class',
                                'code' => 'plan',
                                'display' => 'Plan'
                            ]
                        ]
                    ],
                    'value' => $insurance['plan_name'],
                    'name' => 'Plan Name'
                ] : null
            ])
        ];

        // Add order if secondary or tertiary
        if ($insurance['policy_type'] !== 'primary') {
            $orderMap = [
                'secondary' => 2,
                'tertiary' => 3
            ];
            $coverageData['order'] = $orderMap[$insurance['policy_type']] ?? 9;
        }

        // Add Medicare-specific fields
        if ($this->isMedicare($insurance['payer_name'])) {
            $coverageData['extension'] = [
                [
                    'url' => 'http://mscwoundcare.com/fhir/StructureDefinition/medicare-details',
                    'extension' => array_filter([
                        !empty($insurance['medicare_type']) ? [
                            'url' => 'medicareType',
                            'valueString' => $insurance['medicare_type'] // Part A, B, C, D
                        ] : null,
                        !empty($insurance['medicare_number']) ? [
                            'url' => 'medicareNumber',
                            'valueString' => $insurance['medicare_number']
                        ] : null
                    ])
                ]
            ];
        }

        return $coverageData;
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
