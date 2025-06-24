<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;

class ClinicalHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger
    ) {}

    /**
     * Create clinical FHIR resources
     */
    public function createClinicalResources(array $data): array
    {
        $this->logger->info('Creating clinical FHIR resources');

        $resources = [];

        // Create Condition
        $condition = $this->createCondition($data);
        $resources['condition_id'] = $condition['id'];

        // Create EpisodeOfCare
        $episodeOfCare = $this->createEpisodeOfCare($data, $condition['id']);
        $resources['episode_of_care_id'] = $episodeOfCare['id'];

        // Create Encounter if needed
        if ($data['create_encounter'] ?? true) {
            $encounter = $this->createEncounter($data, $episodeOfCare['id']);
            $resources['encounter_id'] = $encounter['id'];
        }

        // Create Task for approval workflow
        $task = $this->createApprovalTask($data, $episodeOfCare['id']);
        $resources['task_id'] = $task['id'];

        return $resources;
    }

    /**
     * Create FHIR Condition resource
     */
    private function createCondition(array $data): array
    {
        $conditionData = [
            'resourceType' => 'Condition',
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active'
                    ]
                ]
            ],
            'verificationStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                        'code' => 'confirmed'
                    ]
                ]
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                            'code' => 'problem-list-item',
                            'display' => 'Problem List Item'
                        ]
                    ]
                ]
            ],
            'code' => $this->mapDiagnosisCodes($data['clinical']['diagnosis_codes']),
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}"
            ],
            'onsetDateTime' => $data['clinical']['onset_date'] ?? now()->toIso8601String(),
            'recordedDate' => now()->toIso8601String(),
            'recorder' => [
                'reference' => "Practitioner/{$data['provider_id']}"
            ]
        ];

        // Add wound-specific extensions
        if (!empty($data['clinical']['wound_type'])) {
            $conditionData['extension'] = [
                [
                    'url' => 'http://mscwoundcare.com/fhir/StructureDefinition/wound-details',
                    'extension' => [
                        [
                            'url' => 'woundType',
                            'valueString' => $data['clinical']['wound_type']
                        ],
                        [
                            'url' => 'woundLocation',
                            'valueString' => $data['clinical']['wound_location'] ?? ''
                        ],
                        [
                            'url' => 'woundSize',
                            'valueString' => json_encode($data['clinical']['wound_size'] ?? [])
                        ],
                        [
                            'url' => 'woundStage',
                            'valueString' => $data['clinical']['wound_stage'] ?? ''
                        ]
                    ]
                ]
            ];
        }

        return $this->fhirService->createCondition($conditionData);
    }

    /**
     * Create FHIR EpisodeOfCare resource
     */
    private function createEpisodeOfCare(array $data, string $conditionId): array
    {
        $episodeData = [
            'resourceType' => 'EpisodeOfCare',
            'status' => 'active',
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/episodeofcare-type',
                            'code' => 'hacc',
                            'display' => 'Home and Community Care'
                        ]
                    ]
                ]
            ],
            'diagnosis' => [
                [
                    'condition' => [
                        'reference' => "Condition/{$conditionId}"
                    ],
                    'role' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/diagnosis-role',
                                'code' => 'CC',
                                'display' => 'Chief complaint'
                            ]
                        ]
                    ],
                    'rank' => 1
                ]
            ],
            'patient' => [
                'reference' => "Patient/{$data['patient_id']}"
            ],
            'managingOrganization' => [
                'reference' => "Organization/{$data['organization_id']}"
            ],
            'period' => [
                'start' => now()->toIso8601String()
            ],
            'team' => [
                [
                    'reference' => "CareTeam/wound-care-team-{$data['patient_id']}",
                    'display' => 'Wound Care Team'
                ]
            ]
        ];

        return $this->fhirService->createEpisodeOfCare($episodeData);
    }

    /**
     * Create FHIR Encounter resource
     */
    private function createEncounter(array $data, string $episodeOfCareId): array
    {
        $encounterData = [
            'resourceType' => 'Encounter',
            'status' => 'finished',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory'
            ],
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '439740005',
                            'display' => 'Postoperative follow-up visit'
                        ]
                    ]
                ]
            ],
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}"
            ],
            'episodeOfCare' => [
                [
                    'reference' => "EpisodeOfCare/{$episodeOfCareId}"
                ]
            ],
            'participant' => [
                [
                    'type' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                                    'code' => 'PPRF',
                                    'display' => 'primary performer'
                                ]
                            ]
                        ]
                    ],
                    'individual' => [
                        'reference' => "Practitioner/{$data['provider_id']}"
                    ]
                ]
            ],
            'period' => [
                'start' => now()->subHours(1)->toIso8601String(),
                'end' => now()->toIso8601String()
            ],
            'serviceProvider' => [
                'reference' => "Organization/{$data['organization_id']}"
            ]
        ];

        return $this->fhirService->createEncounter($encounterData);
    }

    /**
     * Create approval task
     */
    private function createApprovalTask(array $data, string $episodeOfCareId): array
    {
        $taskData = [
            'resourceType' => 'Task',
            'status' => 'requested',
            'businessStatus' => [
                'text' => 'Pending Review'
            ],
            'intent' => 'order',
            'priority' => 'routine',
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://hl7.org/fhir/CodeSystem/task-code',
                        'code' => 'approve',
                        'display' => 'Approve Order'
                    ]
                ]
            ],
            'description' => 'Review and approve wound care episode',
            'focus' => [
                'reference' => "EpisodeOfCare/{$episodeOfCareId}"
            ],
            'for' => [
                'reference' => "Patient/{$data['patient_id']}"
            ],
            'authoredOn' => now()->toIso8601String(),
            'requester' => [
                'reference' => "Practitioner/{$data['provider_id']}"
            ],
            'owner' => [
                'reference' => "Organization/{$data['organization_id']}"
            ],
            'restriction' => [
                'period' => [
                    'end' => now()->addDays(2)->toIso8601String()
                ]
            ]
        ];

        return $this->fhirService->createTask($taskData);
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(string $taskId, string $status, string $businessStatus): void
    {
        $this->fhirService->updateTask($taskId, [
            'status' => $status,
            'businessStatus' => [
                'text' => $businessStatus
            ],
            'lastModified' => now()->toIso8601String()
        ]);
    }

    /**
     * Complete EpisodeOfCare
     */
    public function completeEpisodeOfCare(string $episodeOfCareId): void
    {
        $this->fhirService->updateEpisodeOfCare($episodeOfCareId, [
            'status' => 'finished',
            'period' => [
                'end' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Map diagnosis codes to FHIR CodeableConcept
     */
    private function mapDiagnosisCodes(array $diagnosisCodes): array
    {
        return [
            'coding' => array_map(function ($code) {
                return [
                    'system' => 'http://hl7.org/fhir/sid/icd-10-cm',
                    'code' => $code,
                    'display' => $this->getIcd10Display($code)
                ];
            }, $diagnosisCodes),
            'text' => 'Wound diagnosis'
        ];
    }

    /**
     * Get ICD-10 display text
     */
    private function getIcd10Display(string $code): string
    {
        // This would normally lookup from a database or service
        $icd10Map = [
            'L89.154' => 'Pressure ulcer of sacral region, stage 4',
            'E11.9' => 'Type 2 diabetes mellitus without complications',
            'I70.213' => 'Atherosclerosis of native arteries of extremities with intermittent claudication, bilateral legs',
            'L97.509' => 'Non-pressure chronic ulcer of other part of unspecified foot with unspecified severity'
        ];

        return $icd10Map[$code] ?? $code;
    }
}