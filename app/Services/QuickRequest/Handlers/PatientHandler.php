<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;

class PatientHandler extends BaseHandler
{
    public function __construct(
        FhirService $fhirService,
        PhiSafeLogger $logger,
        PhiAuditService $auditService
    ) {
        parent::__construct($fhirService, $logger, $auditService);
    }

    /**
     * Create or update patient in FHIR
     */
    public function createOrUpdatePatient(array $patientData): string
    {
        // Validate required fields
        $this->validateRequiredFields($patientData, ['first_name', 'last_name', 'dob']);
        
        // Sanitize input data
        $patientData = $this->sanitizeData($patientData);

        return $this->executeFhirOperation(
            'patient creation',
            function () use ($patientData) {
                // Search for existing patient
                $existingPatient = $this->findExistingPatient($patientData);

                if ($existingPatient) {
                    $this->logAuditAccess('patient.accessed', 'Patient', $existingPatient['id']);
                    return $existingPatient['id'];
                }

                // Create new patient
                $fhirPatient = $this->mapToFhirPatient($patientData);
                $response = $this->fhirService->createPatient($fhirPatient);

                $this->logAuditAccess('patient.created', 'Patient', $response['id']);

                $this->logger->info('Patient created successfully in FHIR', [
                    'patient_id' => $response['id']
                ]);

                return $response['id'];
            },
            function () use ($patientData) {
                // Fallback: generate local patient ID
                $localPatientId = $this->generateLocalId('patient', $patientData);
                
                $this->logger->info('Using local patient ID (FHIR disabled)', [
                    'patient_id' => $localPatientId,
                    'patient_name' => $patientData['first_name'] . ' ' . $patientData['last_name']
                ]);
                
                return $localPatientId;
            }
        );
    }

    /**
     * Find existing patient by demographics
     */
    private function findExistingPatient(array $patientData): ?array
    {
        // Search by name and birthdate
        $searchParams = [
            'family' => $patientData['last_name'],
            'given' => $patientData['first_name'],
            'birthdate' => $patientData['dob']
        ];

        $resource = $this->findExistingFhirResource('Patient', $searchParams);
        
        if ($resource && $this->matchesPatient($resource, $patientData)) {
            return $resource;
        }

        return null;
    }

    /**
     * Check if FHIR patient matches our patient data
     */
    private function matchesPatient(array $fhirPatient, array $patientData): bool
    {
        // Match on name and birthdate
        $nameMatch = false;
        $dobMatch = false;

        if (!empty($fhirPatient['name'])) {
            foreach ($fhirPatient['name'] as $name) {
                $family = $name['family'] ?? '';
                $given = $name['given'][0] ?? '';

                if (strcasecmp($family, $patientData['last_name']) === 0 &&
                    strcasecmp($given, $patientData['first_name']) === 0) {
                    $nameMatch = true;
                    break;
                }
            }
        }

        if (!empty($fhirPatient['birthDate'])) {
            $dobMatch = $fhirPatient['birthDate'] === $patientData['dob'];
        }

        return $nameMatch && $dobMatch;
    }

    /**
     * Map patient data to FHIR Patient resource
     */
    private function mapToFhirPatient(array $data): array
    {
        $resource = [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'system' => 'https://mscwoundcare.com/patient-id',
                    'value' => $this->generatePatientIdentifier($data)
                ]
            ],
            'active' => true,
            'name' => [
                [
                    'use' => 'official',
                    'family' => $data['last_name'],
                    'given' => [$data['first_name']]
                ]
            ],
            'gender' => $this->mapGenderToFhir($data['gender'] ?? 'unknown'),
            'birthDate' => $data['dob']
        ];

        // Add contact information
        $telecom = [];
        if (!empty($data['phone'])) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $this->formatPhoneNumber($data['phone']),
                'use' => 'home'
            ];
        }
        if (!empty($data['email'])) {
            $telecom[] = [
                'system' => 'email',
                'value' => $data['email']
            ];
        }
        if (!empty($telecom)) {
            $resource['telecom'] = $telecom;
        }

        // Add address
        if (!empty($data['address_line1'])) {
            $resource['address'] = [
                [
                    'use' => 'home',
                    'type' => 'physical',
                    'line' => array_filter([
                        $data['address_line1'],
                        $data['address_line2'] ?? null
                    ]),
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'postalCode' => $data['zip'] ?? null,
                    'country' => 'USA'
                ]
            ];
        }

        // Add marital status if provided
        if (!empty($data['marital_status'])) {
            $resource['maritalStatus'] = [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus',
                        'code' => $this->mapMaritalStatus($data['marital_status'])
                    ]
                ]
            ];
        }

        return $resource;
    }

    /**
     * Generate patient identifier (e.g., JOSM473)
     */
    private function generatePatientIdentifier(array $patientData): string
    {
        $first = substr(strtoupper($patientData['first_name']), 0, 2);
        $last = substr(strtoupper($patientData['last_name']), 0, 2);
        $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

        return $first . $last . $random;
    }



    /**
     * Map marital status to FHIR values
     */
    private function mapMaritalStatus(string $status): string
    {
        $statusMap = [
            'single' => 'S',
            'married' => 'M',
            'divorced' => 'D',
            'widowed' => 'W',
            'separated' => 'L',
            'unknown' => 'UNK'
        ];

        return $statusMap[strtolower($status)] ?? 'UNK';
    }
}