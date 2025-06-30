<?php
declare(strict_types=1);

namespace App\Services;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Services\FhirService;
use App\Services\DocuSealService;
use App\Mail\ManufacturerOrderEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

final class QuickRequestService
{
    public function __construct(
        private FhirService $fhirClient,
        private DocuSealService $docuSealService,
    ) {}

    /**
     * Get the DocuSeal service instance.
     */
    public function getDocuSealService(): DocuSealService
    {
        return $this->docuSealService;
    }

    /**
     * Start a new quick request episode and create initial order.
     */
    public function startEpisode(array $data): Episode
    {
        // Extract data for FHIR resources
        $patientData = $data['patient'] ?? [];
        $providerData = $data['provider'] ?? [];
        $facilityData = $data['facility'] ?? [];
        $clinicalData = $data['clinical'] ?? [];
        $insuranceData = $data['insurance'] ?? [];
        $productData = $data['product'] ?? [];

        $fhirIds = [];

        // Orchestrate FHIR resource creation as per workflow
        try {
            // 1. Create or get Patient
            $patientResource = $this->createFhirPatient($patientData);
            $fhirIds['patient_id'] = $patientResource['id'] ?? null;

            // 2. Create or get Practitioner
            $practitionerResource = $this->createFhirPractitioner($providerData);
            $fhirIds['practitioner_id'] = $practitionerResource['id'] ?? null;

            // 3. Create Organization
            $organizationResource = $this->createFhirOrganization($facilityData);
            $fhirIds['organization_id'] = $organizationResource['id'] ?? null;

            // 4. Create Condition (diagnosis)
            $conditionResource = $this->createFhirCondition($clinicalData, $fhirIds['patient_id']);
            $fhirIds['condition_id'] = $conditionResource['id'] ?? null;

            // 5. Create EpisodeOfCare
            $episodeOfCareResource = $this->createFhirEpisodeOfCare($fhirIds);
            $fhirIds['episode_of_care_id'] = $episodeOfCareResource['id'] ?? null;

            // 6. Create Coverage (insurance)
            $coverageResource = $this->createFhirCoverage($insuranceData, $fhirIds);
            $fhirIds['coverage_id'] = $coverageResource['id'] ?? null;

            // 7. Create Encounter
            $encounterResource = $this->createFhirEncounter($fhirIds, $clinicalData);
            $fhirIds['encounter_id'] = $encounterResource['id'] ?? null;

            // 8. Create QuestionnaireResponse (assessment)
            $questionnaireResource = $this->createFhirQuestionnaireResponse($clinicalData, $fhirIds);
            $fhirIds['questionnaire_response_id'] = $questionnaireResource['id'] ?? null;

            // 9. Create DeviceRequest (product order)
            $deviceRequestResource = $this->createFhirDeviceRequest($productData, $fhirIds);
            $fhirIds['device_request_id'] = $deviceRequestResource['id'] ?? null;

            // 10. Create Task for internal review
            $taskResource = $this->createFhirTask($fhirIds, 'internal_review');
            $fhirIds['task_id'] = $taskResource['id'] ?? null;

        } catch (\Exception $e) {
            Log::error('FHIR orchestration failed', [
                'error' => $e->getMessage(),
                'step' => $e->getCode(),
                'fhir_ids' => $fhirIds
            ]);
            // Continue with episode creation even if FHIR fails
        }

        // Persist episode with FHIR references
        $episode = Episode::create([
            'patient_id'           => $data['patient_id'] ?? null,
            'patient_fhir_id'      => $fhirIds['patient_id'] ?? $data['patient_fhir_id'] ?? null,
            'patient_display_id'   => $data['patient_display_id'] ?? $this->generatePatientDisplayId($patientData),
            'manufacturer_id'      => $data['manufacturer_id'],
            'status'               => 'draft',
            'metadata'             => array_merge($data, [
                'fhir_ids' => $fhirIds,
                'created_via' => 'quick_request'
            ]),
        ]);

        // Create initial order
        $order = Order::create([
            'episode_id' => $episode->id,
            'type'       => 'initial',
            'details'    => $data['order_details'] ?? [],
        ]);

        // DocuSeal PDF generation
        try {
            $manufacturerId = $data['manufacturer_id'];
            $productCode = $data['order_details']['product'] ?? null;

            // Note: DocuSealBuilder service not implemented yet
            // For now, use basic data without template lookup
            $dataWithTemplate = $data;
            $submission = $this->docuSealService->createIVRSubmission(
                $dataWithTemplate,
                $episode
            );
            if (!empty($submission['embed_url'])) {
                $episode->update(['docuseal_submission_url' => $submission['embed_url']]);
            }
        } catch (\Exception $e) {
            Log::error('DocuSeal PDF generation failed', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
            ]);
        }

        return $episode->load('orders');
    }

    /**
     * Add a follow-up order to an existing episode.
     */
    public function addFollowUp(Episode $episode, array $data): Order
    {
        // FHIR follow-up request
        try {
            $bundle = [
                'resourceType' => 'Bundle',
                'type'         => 'transaction',
                'entry'        => [
                    // Map follow-up device request here...
                ],
            ];
            $this->fhirClient->createBundle($bundle);
        } catch (\Exception $e) {
            Log::error('FHIR follow-up bundle creation failed', ['error' => $e->getMessage()]);
        }

        // Create follow-up order
        $order = Order::create([
            'episode_id'      => $episode->id,
            'parent_order_id' => $data['parent_order_id'],
            'type'            => 'follow_up',
            'details'         => $data['order_details'] ?? [],
        ]);

        return $order;
    }

    /**
     * Approve an episode and send notification.
     */
    public function approve(Episode $episode): void
    {
        // Create manufacturer acceptance Task in FHIR
        try {
            $fhirIds = $episode->metadata['fhir_ids'] ?? [];
            if (!empty($fhirIds['episode_of_care_id'])) {
                $this->createFhirTask($fhirIds, 'manufacturer_acceptance');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create manufacturer task', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id
            ]);
        }

        // Transition episode status
        $episode->update(['status' => 'manufacturer_review']);

        // Dispatch email to manufacturer
        try {
            $email = $episode->manufacturer->contact_email ?? null;
            if (is_string($email) && !empty($email)) {
                Mail::to([$email])->send(new ManufacturerOrderEmail($episode->toArray()));
            }
        } catch (\Exception $e) {
            Log::error('Error sending approval email', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
            ]);
        }
    }

    /**
     * Create FHIR Patient resource
     */
    private function createFhirPatient(array $patientData): array
    {
        return retry(3, function () use ($patientData) {
            $patient = [
                'resourceType' => 'Patient',
                'name' => [[
                    'use' => 'official',
                    'family' => $patientData['last_name'] ?? '',
                    'given' => [$patientData['first_name'] ?? '']
                ]],
                'gender' => strtolower($patientData['gender'] ?? 'unknown'),
                'birthDate' => $patientData['date_of_birth'] ?? null,
                'telecom' => [
                    [
                        'system' => 'phone',
                        'value' => $patientData['phone'] ?? '',
                        'use' => 'mobile'
                    ],
                    [
                        'system' => 'email',
                        'value' => $patientData['email'] ?? '',
                        'use' => 'home'
                    ]
                ],
                'address' => [[
                    'use' => 'home',
                    'line' => array_filter([
                        $patientData['address_line1'] ?? '',
                        $patientData['address_line2'] ?? ''
                    ]),
                    'city' => $patientData['city'] ?? '',
                    'state' => $patientData['state'] ?? '',
                    'postalCode' => $patientData['zip'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://mscwoundcare.com/patient-id',
                    'value' => $patientData['member_id'] ?? uniqid('PAT')
                ]]
            ];

            return $this->fhirClient->create('Patient', $patient);
        }, 1000);
    }

    /**
     * Create FHIR Practitioner resource
     */
    private function createFhirPractitioner(array $providerData): array
    {
        return retry(3, function () use ($providerData) {
            // First try to search for existing practitioner by NPI
            if (!empty($providerData['npi'])) {
                $search = $this->fhirClient->search('Practitioner', [
                    'identifier' => $providerData['npi']
                ]);

                if (!empty($search['entry'])) {
                    return $search['entry'][0]['resource'];
                }
            }

            // Create new practitioner
            $practitioner = [
                'resourceType' => 'Practitioner',
                'name' => [[
                    'use' => 'official',
                    'text' => $providerData['name'] ?? '',
                    'family' => $providerData['last_name'] ?? '',
                    'given' => [$providerData['first_name'] ?? '']
                ]],
                'identifier' => [[
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $providerData['npi'] ?? ''
                ]],
                'telecom' => [[
                    'system' => 'email',
                    'value' => $providerData['email'] ?? '',
                    'use' => 'work'
                ]],
                'qualification' => [[
                    'code' => [
                        'text' => $providerData['credentials'] ?? 'MD'
                    ]
                ]]
            ];

            return $this->fhirClient->create('Practitioner', $practitioner);
        }, 1000);
    }

    /**
     * Create FHIR Organization resource
     */
    private function createFhirOrganization(array $facilityData): array
    {
        return retry(3, function () use ($facilityData) {
            $organization = [
                'resourceType' => 'Organization',
                'name' => $facilityData['name'] ?? '',
                'type' => [[
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                        'code' => 'prov',
                        'display' => 'Healthcare Provider'
                    ]]
                ]],
                'telecom' => [[
                    'system' => 'phone',
                    'value' => $facilityData['phone'] ?? '',
                    'use' => 'work'
                ]],
                'address' => [[
                    'use' => 'work',
                    'line' => [$facilityData['address'] ?? ''],
                    'city' => $facilityData['city'] ?? '',
                    'state' => $facilityData['state'] ?? '',
                    'postalCode' => $facilityData['zip'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $facilityData['npi'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Organization', $organization);
        }, 1000);
    }

    /**
     * Create FHIR Condition resource
     */
    private function createFhirCondition(array $clinicalData, ?string $patientId): array
    {
        return retry(3, function () use ($clinicalData, $patientId) {
            $condition = [
                'resourceType' => 'Condition',
                'clinicalStatus' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active'
                    ]]
                ],
                'verificationStatus' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                        'code' => 'confirmed'
                    ]]
                ],
                'code' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => $clinicalData['diagnosis_code'] ?? '',
                        'display' => $clinicalData['diagnosis_description'] ?? ''
                    ]]
                ],
                'subject' => [
                    'reference' => "Patient/{$patientId}"
                ],
                'onsetDateTime' => $clinicalData['onset_date'] ?? date('Y-m-d'),
                'note' => [[
                    'text' => $clinicalData['clinical_notes'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Condition', $condition);
        }, 1000);
    }

    /**
     * Create FHIR EpisodeOfCare resource
     */
    private function createFhirEpisodeOfCare(array $fhirIds): array
    {
        return retry(3, function () use ($fhirIds) {
            $episodeOfCare = [
                'resourceType' => 'EpisodeOfCare',
                'status' => 'active',
                'type' => [[
                    'coding' => [[
                        'system' => 'http://snomed.info/sct',
                        'code' => '225358003',
                        'display' => 'Wound care'
                    ]]
                ]],
                'patient' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'managingOrganization' => [
                    'reference' => "Organization/{$fhirIds['organization_id']}"
                ],
                'period' => [
                    'start' => date('Y-m-d')
                ],
                'team' => [[
                    'reference' => "CareTeam/wound-care-team",
                    'display' => 'Wound Care Team'
                ]]
            ];

            return $this->fhirClient->create('EpisodeOfCare', $episodeOfCare);
        }, 1000);
    }

    /**
     * Create FHIR Coverage resource
     */
    private function createFhirCoverage(array $insuranceData, array $fhirIds): array
    {
        return retry(3, function () use ($insuranceData, $fhirIds) {
            $coverage = [
                'resourceType' => 'Coverage',
                'status' => 'active',
                'type' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                        'code' => $insuranceData['type'] ?? 'HIP',
                        'display' => $insuranceData['type_display'] ?? 'health insurance plan policy'
                    ]]
                ],
                'subscriber' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'beneficiary' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'payor' => [[
                    'display' => $insuranceData['payer_name'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://mscwoundcare.com/insurance-id',
                    'value' => $insuranceData['member_id'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Coverage', $coverage);
        }, 1000);
    }

    /**
     * Create FHIR Encounter resource
     */
    private function createFhirEncounter(array $fhirIds, array $clinicalData): array
    {
        return retry(3, function () use ($fhirIds, $clinicalData) {
            $encounter = [
                'resourceType' => 'Encounter',
                'status' => 'in-progress',
                'class' => [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                    'code' => 'AMB',
                    'display' => 'ambulatory'
                ],
                'type' => [[
                    'coding' => [[
                        'system' => 'http://snomed.info/sct',
                        'code' => '225358003',
                        'display' => 'Wound care'
                    ]]
                ]],
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'episodeOfCare' => [[
                    'reference' => "EpisodeOfCare/{$fhirIds['episode_of_care_id']}"
                ]],
                'participant' => [[
                    'type' => [[
                        'coding' => [[
                            'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                            'code' => 'PPRF',
                            'display' => 'primary performer'
                        ]]
                    ]],
                    'individual' => [
                        'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                    ]
                ]],
                'serviceProvider' => [
                    'reference' => "Organization/{$fhirIds['organization_id']}"
                ]
            ];

            return $this->fhirClient->create('Encounter', $encounter);
        }, 1000);
    }

    /**
     * Create FHIR QuestionnaireResponse resource
     */
    private function createFhirQuestionnaireResponse(array $clinicalData, array $fhirIds): array
    {
        return retry(3, function () use ($clinicalData, $fhirIds) {
            $questionnaireResponse = [
                'resourceType' => 'QuestionnaireResponse',
                'status' => 'completed',
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'encounter' => [
                    'reference' => "Encounter/{$fhirIds['encounter_id']}"
                ],
                'authored' => date('c'),
                'author' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'item' => [
                    [
                        'linkId' => 'wound-assessment',
                        'text' => 'Wound Assessment',
                        'item' => [
                            [
                                'linkId' => 'wound-type',
                                'text' => 'Wound Type',
                                'answer' => [[
                                    'valueString' => $clinicalData['wound_type'] ?? ''
                                ]]
                            ],
                            [
                                'linkId' => 'wound-location',
                                'text' => 'Wound Location',
                                'answer' => [[
                                    'valueString' => $clinicalData['wound_location'] ?? ''
                                ]]
                            ],
                            [
                                'linkId' => 'wound-size',
                                'text' => 'Wound Size (cm)',
                                'answer' => [[
                                    'valueString' => sprintf('%s x %s x %s',
                                        $clinicalData['wound_length'] ?? '0',
                                        $clinicalData['wound_width'] ?? '0',
                                        $clinicalData['wound_depth'] ?? '0'
                                    )
                                ]]
                            ]
                        ]
                    ]
                ]
            ];

            return $this->fhirClient->create('QuestionnaireResponse', $questionnaireResponse);
        }, 1000);
    }

    /**
     * Create FHIR DeviceRequest resource
     */
    private function createFhirDeviceRequest(array $productData, array $fhirIds): array
    {
        return retry(3, function () use ($productData, $fhirIds) {
            $deviceRequest = [
                'resourceType' => 'DeviceRequest',
                'status' => 'active',
                'intent' => 'order',
                'codeCodeableConcept' => [
                    'coding' => [[
                        'system' => 'http://mscwoundcare.com/product-codes',
                        'code' => $productData['code'] ?? '',
                        'display' => $productData['name'] ?? ''
                    ]]
                ],
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'encounter' => [
                    'reference' => "Encounter/{$fhirIds['encounter_id']}"
                ],
                'authoredOn' => date('c'),
                'requester' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'parameter' => [
                    [
                        'code' => [
                            'text' => 'Quantity'
                        ],
                        'valueQuantity' => [
                            'value' => $productData['quantity'] ?? 1,
                            'unit' => 'units'
                        ]
                    ],
                    [
                        'code' => [
                            'text' => 'Size'
                        ],
                        'valueCodeableConcept' => [
                            'text' => $productData['size'] ?? 'Standard'
                        ]
                    ]
                ]
            ];

            return $this->fhirClient->create('DeviceRequest', $deviceRequest);
        }, 1000);
    }

    /**
     * Create FHIR Task resource
     */
    private function createFhirTask(array $fhirIds, string $type): array
    {
        return retry(3, function () use ($fhirIds, $type) {
            $taskConfigs = [
                'internal_review' => [
                    'code' => 'approve',
                    'display' => 'Approve order',
                    'description' => 'Review and approve wound care product order',
                    'priority' => 'routine',
                    'performerType' => 'office-manager'
                ],
                'manufacturer_acceptance' => [
                    'code' => 'fulfill',
                    'display' => 'Fulfill order',
                    'description' => 'Process and fulfill wound care product order',
                    'priority' => 'normal',
                    'performerType' => 'manufacturer'
                ]
            ];

            $config = $taskConfigs[$type] ?? $taskConfigs['internal_review'];

            $task = [
                'resourceType' => 'Task',
                'status' => 'requested',
                'intent' => 'order',
                'code' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/CodeSystem/task-code',
                        'code' => $config['code'],
                        'display' => $config['display']
                    ]]
                ],
                'description' => $config['description'],
                'priority' => $config['priority'],
                'for' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'focus' => [
                    'reference' => "EpisodeOfCare/{$fhirIds['episode_of_care_id']}"
                ],
                'authoredOn' => date('c'),
                'requester' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'performerType' => [[
                    'text' => $config['performerType']
                ]]
            ];

            return $this->fhirClient->create('Task', $task);
        }, 1000);
    }

    /**
     * Generate patient display ID
     */
    private function generatePatientDisplayId(array $patientData): string
    {
        $firstName = strtoupper(substr($patientData['first_name'] ?? 'XX', 0, 2));
        $lastName = strtoupper(substr($patientData['last_name'] ?? 'XX', 0, 2));
        $randomNum = str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);

        return $firstName . $lastName . $randomNum;
    }
}
