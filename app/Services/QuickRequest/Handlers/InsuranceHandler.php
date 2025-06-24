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
        $this->logger->info('Creating insurance coverage in FHIR');

        $coverageData = $this->mapToFhirCoverage($data);
        $response = $this->fhirService->createCoverage($coverageData);

        $this->auditService->logAccess(
            'coverage.created',
            'Coverage',
            $response['id'],
            ['payer' => $data['insurance']['payer_name']]
        );

        return $response['id'];
    }

    /**
     * Create multiple coverages (primary, secondary, etc.)
     */
    public function createMultipleCoverages(array $insuranceData, string $patientId): array
    {
        $coverageIds = [];

        foreach ($insuranceData as $insurance) {
            $coverage = $this->createCoverage([
                'patient_id' => $patientId,
                'insurance' => $insurance
            ]);
            
            $coverageIds[$insurance['policy_type']] = $coverage;
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
        
        $coverageData = [
            'resourceType' => 'Coverage',
            'status' => 'active',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                        'code' => $this->mapPolicyTypeToCode($insurance['policy_type']),
                        'display' => ucfirst($insurance['policy_type'])
                    ]
                ]
            ],
            'subscriber' => [
                'reference' => "Patient/{$data['patient_id']}"
            ],
            'subscriberId' => $insurance['member_id'],
            'beneficiary' => [
                'reference' => "Patient/{$data['patient_id']}"
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
                    'display' => $insurance['payer_name']
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