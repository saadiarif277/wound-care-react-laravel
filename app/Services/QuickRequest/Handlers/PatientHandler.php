<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;

class PatientHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiSafeLogger $logger,
        private PhiAuditService $auditService
    ) {}

    /**
     * Create or update patient in FHIR
     */
    public function createOrUpdatePatient(array $patientData): string
    {
        try {
            $this->logger->info('Creating or updating patient in FHIR');

            // Search for existing patient
            $existingPatient = $this->findExistingPatient($patientData);

            if ($existingPatient) {
                $this->auditService->logAccess('patient.accessed', 'Patient', $existingPatient['id']);
                return $existingPatient['id'];
            }

            // Create new patient
            $fhirPatient = $this->mapToFhirPatient($patientData);
            $response = $this->fhirService->createPatient($fhirPatient);

            $this->auditService->logAccess('patient.created', 'Patient', $response['id']);

            $this->logger->info('Patient created successfully in FHIR', [
                'patient_id' => $response['id']
            ]);

            return $response['id'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create/update patient in FHIR', [
                'error' => $e->getMessage(),
                'patient_name' => $patientData['first_name'] . ' ' . $patientData['last_name']
            ]);
            throw new \Exception('Failed to create/update patient: ' . $e->getMessage());
        }
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

        $results = $this->fhirService->searchPatients($searchParams);

        if (!empty($results['entry'])) {
            // Additional validation to ensure it's the same patient
            foreach ($results['entry'] as $entry) {
                $resource = $entry['resource'];
                if ($this->matchesPatient($resource, $patientData)) {
                    return $resource;
                }
            }
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
            'gender' => $this->mapGender($data['gender'] ?? 'unknown'),
            'birthDate' => $data['dob']
        ];

        // Add contact information
        $telecom = [];
        if (!empty($data['phone'])) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $data['phone'],
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
     * Map gender to FHIR values
     */
    private function mapGender(string $gender): string
    {
        $genderMap = [
            'm' => 'male',
            'f' => 'female',
            'male' => 'male',
            'female' => 'female',
            'other' => 'other',
            'unknown' => 'unknown'
        ];

        return $genderMap[strtolower($gender)] ?? 'unknown';
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