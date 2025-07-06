<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\PhiAuditService;

class ClinicalHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private PhiAuditService $auditService
    ) {}

    /**
     * Create clinical resources in FHIR (Condition, EpisodeOfCare)
     */
    public function createClinicalResources(array $data): array
    {
        try {
            $this->logger->info('Creating clinical resources in FHIR');

            // Get primary diagnosis code from the diagnosisCodes array
            $diagnosisCodes = $data['clinical']['diagnosis_codes'] ?? [];
            $primaryDiagnosisCode = !empty($diagnosisCodes) ? $diagnosisCodes[0] : 'Z00.00'; // Default to general examination if no diagnosis

            // Create Condition for primary diagnosis
            $conditionData = [
                'resourceType' => 'Condition',
                'subject' => [
                    'reference' => "Patient/{$data['patient_id']}"
                ],
                'asserter' => [
                    'reference' => "Practitioner/{$data['provider_id']}"
                ],
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://hl7.org/fhir/sid/icd-10',
                            'code' => $primaryDiagnosisCode,
                            'display' => $this->getICDDisplayName($primaryDiagnosisCode)
                        ]
                    ]
                ],
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
                'bodySite' => [
                    [
                        'text' => $data['clinical']['wound_location'] ?? 'Unknown location'
                    ]
                ],
                'note' => [
                    [
                        'text' => $data['clinical']['wound_type'] ?? 'Wound care treatment'
                    ]
                ]
            ];

            // Add measurements if available
            if (!empty($data['clinical']['wound_size_length']) && !empty($data['clinical']['wound_size_width'])) {
                $conditionData['extension'] = [
                    [
                        'url' => 'http://msc-mvp.com/fhir/StructureDefinition/wound-measurements',
                        'extension' => [
                            [
                                'url' => 'length',
                                'valueDecimal' => $data['clinical']['wound_size_length']
                            ],
                            [
                                'url' => 'width',
                                'valueDecimal' => $data['clinical']['wound_size_width']
                            ]
                        ]
                    ]
                ];

                // Add depth if available
                if (!empty($data['clinical']['wound_size_depth'])) {
                    $conditionData['extension'][0]['extension'][] = [
                        'url' => 'depth',
                        'valueDecimal' => $data['clinical']['wound_size_depth']
                    ];
                }
            }

            $condition = $this->fhirService->create('Condition', $conditionData);
            $conditionId = $condition['id'];

            $this->auditService->logAccess('condition.created', 'Condition', $conditionId);

            // Create EpisodeOfCare
            $episodeData = [
                'resourceType' => 'EpisodeOfCare',
                'status' => 'active',
                'patient' => [
                    'reference' => "Patient/{$data['patient_id']}"
                ],
                'managingOrganization' => [
                    'reference' => "Organization/{$data['organization_id']}"
                ],
                'careManager' => [
                    'reference' => "Practitioner/{$data['provider_id']}"
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
                                    'code' => 'CC'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $episode = $this->fhirService->create('EpisodeOfCare', $episodeData);
            $episodeId = $episode['id'];

            $this->auditService->logAccess('episode.created', 'EpisodeOfCare', $episodeId);

            $this->logger->info('Clinical resources created successfully', [
                'condition_id' => $conditionId,
                'episode_id' => $episodeId,
                'primary_diagnosis' => $primaryDiagnosisCode
            ]);

            return [
                'condition_id' => $conditionId,
                'episode_of_care_id' => $episodeId
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create clinical resources', [
                'error' => $e->getMessage(),
                'clinical_data' => $data['clinical'] ?? 'no clinical data'
            ]);
            throw $e;
        }
    }

    /**
     * Get ICD-10 code display name (placeholder - should use a proper terminology service)
     */
    private function getICDDisplayName(string $code): string
    {
        // TODO: Implement proper ICD-10 code lookup
        $commonCodes = [
            'L89.004' => 'Pressure ulcer of sacral region, stage 4',
            'E11.9' => 'Type 2 diabetes mellitus without complications'
        ];

        return $commonCodes[$code] ?? "ICD-10 Code: {$code}";
    }

    /**
     * Update the status of a FHIR Task resource.
     */
    public function updateTaskStatus(string $taskId, string $status, string $statusReason): array
    {
        try {
            $this->logger->info('Updating FHIR Task status', [
                'task_id' => $taskId,
                'status' => $status,
                'status_reason' => $statusReason
            ]);

            // First, read the existing task to ensure we have the full resource
            $existingTask = $this->fhirService->read('Task', $taskId);

            // Update the status and statusReason
            $existingTask['status'] = $status;
            $existingTask['statusReason'] = ['text' => $statusReason];
            $existingTask['lastModified'] = now()->toIso8601String();


            $response = $this->fhirService->update('Task', $taskId, $existingTask);

            $this->auditService->logAccess('task.updated', 'Task', $taskId);

            $this->logger->info('FHIR Task status updated successfully', ['task_id' => $taskId]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update FHIR Task status', [
                'error' => $e->getMessage(),
                'task_id' => $taskId,
            ]);
            throw $e;
        }
    }

    /**
     * Complete an EpisodeOfCare resource by updating its status.
     */
    public function completeEpisodeOfCare(string $episodeOfCareId): array
    {
        try {
            $this->logger->info('Completing FHIR EpisodeOfCare', ['episode_of_care_id' => $episodeOfCareId]);

            // Read the existing resource
            $episodeOfCare = $this->fhirService->read('EpisodeOfCare', $episodeOfCareId);

            // Update status and period end
            $episodeOfCare['status'] = 'finished';
            $episodeOfCare['period']['end'] = now()->toIso8601String();

            $response = $this->fhirService->update('EpisodeOfCare', $episodeOfCareId, $episodeOfCare);

            $this->auditService->logAccess('episode.completed', 'EpisodeOfCare', $episodeOfCareId);

            $this->logger->info('FHIR EpisodeOfCare completed successfully', ['episode_of_care_id' => $episodeOfCareId]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete FHIR EpisodeOfCare', [
                'error' => $e->getMessage(),
                'episode_of_care_id' => $episodeOfCareId,
            ]);
            throw $e;
        }
    }
}
