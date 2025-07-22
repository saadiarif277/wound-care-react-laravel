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

        // Add US Core 8.0.0 Extensions for enhanced compliance
        $extensions = [];
        
        // US Core Race Extension
        if (!empty($data['race'])) {
            $extensions[] = [
                'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-race',
                'extension' => [
                    [
                        'url' => 'ombCategory',
                        'valueCoding' => [
                            'system' => 'urn:oid:2.16.840.1.113883.6.238',
                            'code' => $this->mapRaceToOmbCode($data['race']),
                            'display' => $data['race']
                        ]
                    ],
                    [
                        'url' => 'text',
                        'valueString' => $data['race']
                    ]
                ]
            ];
        }
        
        // US Core Ethnicity Extension  
        if (!empty($data['ethnicity'])) {
            $extensions[] = [
                'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-ethnicity',
                'extension' => [
                    [
                        'url' => 'ombCategory',
                        'valueCoding' => [
                            'system' => 'urn:oid:2.16.840.1.113883.6.238',
                            'code' => $this->mapEthnicityToOmbCode($data['ethnicity']),
                            'display' => $data['ethnicity']
                        ]
                    ],
                    [
                        'url' => 'text',
                        'valueString' => $data['ethnicity']
                    ]
                ]
            ];
        }
        
        // US Core Birth Sex Extension
        if (!empty($data['birth_sex'])) {
            $extensions[] = [
                'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-birthsex',
                'valueCode' => strtoupper($data['birth_sex'])
            ];
        }
        
        // US Core Sex Extension (for clinical use)
        if (!empty($data['sex_for_clinical_use'])) {
            $extensions[] = [
                'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-sex',
                'valueCoding' => [
                    'system' => 'http://terminology.hl7.org/CodeSystem/usage-context-type',
                    'code' => $data['sex_for_clinical_use']
                ]
            ];
        }
        
        if (!empty($extensions)) {
            $resource['extension'] = $extensions;
        }

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

        // Add address (FHIR R4 2025 compliant)
        if (isset($data['address']) && is_array($data['address'])) {
            // New FHIR-compliant address structure
            $address = $data['address'];
            $resource['address'] = [
                [
                    'use' => 'home',
                    'type' => 'physical',
                    'text' => $address['text'] ?? null,
                    'line' => array_filter($address['line'] ?? []),
                    'city' => $address['city'] ?? null,
                    'district' => $address['district'] ?? null, // Added for enhanced compliance
                    'state' => $address['state'] ?? null,
                    'postalCode' => $address['postalCode'] ?? null,
                    'country' => $address['country'] ?? 'US',
                    'period' => [
                        'start' => date('Y-m-d')
                    ]
                ]
            ];
        } elseif (!empty($data['address_line1'])) {
            // Legacy address structure (backwards compatibility)
            $addressLines = array_filter([
                $data['address_line1'],
                $data['address_line2'] ?? null
            ]);
            
            $resource['address'] = [
                [
                    'use' => 'home',
                    'type' => 'physical',
                    'text' => implode(', ', array_filter([
                        implode(' ', $addressLines),
                        $data['city'] ?? null,
                        $data['state'] ?? null,
                        $data['zip'] ?? null
                    ])),
                    'line' => $addressLines,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'postalCode' => $data['zip'] ?? null,
                    'country' => 'US',
                    'period' => [
                        'start' => date('Y-m-d')
                    ]
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
     * Extract patient data for DocuSeal pre-filling
     * This method formats patient data specifically for DocuSeal forms
     */
    public function extractPatientDataForDocuseal(array $patientData): array
    {
        $docusealData = [];

        // Basic patient information
        $docusealData['patient_name'] = trim(($patientData['first_name'] ?? '') . ' ' . ($patientData['last_name'] ?? ''));
        $docusealData['patient_first_name'] = $patientData['first_name'] ?? '';
        $docusealData['patient_last_name'] = $patientData['last_name'] ?? '';
        $docusealData['patient_dob'] = $this->formatDateForDocuseal($patientData['dob'] ?? '');
        $docusealData['patient_gender'] = ucfirst($patientData['gender'] ?? 'unknown');
        
        // Contact information
        $docusealData['patient_phone'] = $this->formatPhoneNumber($patientData['phone'] ?? '');
        $docusealData['patient_email'] = $patientData['email'] ?? '';
        
        // Address handling - support both FHIR and legacy formats
        if (isset($patientData['address']) && is_array($patientData['address'])) {
            // FHIR-compliant address
            $address = $patientData['address'];
            $docusealData['patient_address'] = $address['line'][0] ?? '';
            $docusealData['patient_address_line1'] = $address['line'][0] ?? '';
            $docusealData['patient_address_line2'] = $address['line'][1] ?? '';
            $docusealData['patient_city'] = $address['city'] ?? '';
            $docusealData['patient_state'] = $address['state'] ?? '';
            $docusealData['patient_zip'] = $address['postalCode'] ?? '';
            
            // Combined address field for forms that expect it
            $docusealData['patient_city_state_zip'] = trim(
                ($address['city'] ?? '') . ', ' . 
                ($address['state'] ?? '') . ' ' . 
                ($address['postalCode'] ?? '')
            );
        } else {
            // Legacy address format
            $docusealData['patient_address'] = $patientData['address_line1'] ?? '';
            $docusealData['patient_address_line1'] = $patientData['address_line1'] ?? '';
            $docusealData['patient_address_line2'] = $patientData['address_line2'] ?? '';
            $docusealData['patient_city'] = $patientData['city'] ?? '';
            $docusealData['patient_state'] = $patientData['state'] ?? '';
            $docusealData['patient_zip'] = $patientData['zip'] ?? '';
            
            // Combined address field
            if (!empty($patientData['city']) && !empty($patientData['state']) && !empty($patientData['zip'])) {
                $docusealData['patient_city_state_zip'] = 
                    $patientData['city'] . ', ' . 
                    $patientData['state'] . ' ' . 
                    $patientData['zip'];
            }
        }
        
        // Caregiver information if patient is not the subscriber
        if (isset($patientData['caregiver_name']) && !empty($patientData['caregiver_name'])) {
            $caregiverInfo = $patientData['caregiver_name'];
            if (!empty($patientData['caregiver_relationship'])) {
                $caregiverInfo .= ' - ' . $patientData['caregiver_relationship'];
            }
            if (!empty($patientData['caregiver_phone'])) {
                $caregiverInfo .= ' - ' . $this->formatPhoneNumber($patientData['caregiver_phone']);
            }
            $docusealData['patient_caregiver_info'] = $caregiverInfo;
        }
        
        // Additional clinical information if available
        if (!empty($patientData['medical_history'])) {
            $docusealData['medical_history'] = $patientData['medical_history'];
        }
        
        // Member ID if available
        if (!empty($patientData['member_id'])) {
            $docusealData['patient_member_id'] = $patientData['member_id'];
        }
        
        return $docusealData;
    }

    /**
     * Format date for DocuSeal (MM/DD/YYYY format)
     */
    private function formatDateForDocuseal(string $date): string
    {
        if (empty($date)) {
            return '';
        }
        
        try {
            $dateObj = new \DateTime($date);
            return $dateObj->format('m/d/Y');
        } catch (\Exception $e) {
            return $date; // Return as-is if parsing fails
        }
    }

    /**
     * Format phone number for display
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as (XXX) XXX-XXXX if 10 digits
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        // Format as 1-XXX-XXX-XXXX if 11 digits starting with 1
        if (strlen($phone) === 11 && $phone[0] === '1') {
            return sprintf('1-%s-%s-%s',
                substr($phone, 1, 3),
                substr($phone, 4, 3),
                substr($phone, 7, 4)
            );
        }
        
        return $phone; // Return as-is if not standard format
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

    /**
     * Map race to OMB Category codes for US Core compliance
     */
    private function mapRaceToOmbCode(string $race): string
    {
        $raceMap = [
            'american indian or alaska native' => '1002-5',
            'asian' => '2028-9',
            'black or african american' => '2054-5',
            'native hawaiian or other pacific islander' => '2076-8',
            'white' => '2106-3',
            'other' => 'OTH',
            'unknown' => 'UNK',
            'asked but unknown' => 'ASKU'
        ];

        return $raceMap[strtolower($race)] ?? 'UNK';
    }

    /**
     * Map ethnicity to OMB Category codes for US Core compliance  
     */
    private function mapEthnicityToOmbCode(string $ethnicity): string
    {
        $ethnicityMap = [
            'hispanic or latino' => '2135-2',
            'not hispanic or latino' => '2186-5',
            'unknown' => 'UNK',
            'asked but unknown' => 'ASKU'
        ];

        return $ethnicityMap[strtolower($ethnicity)] ?? 'UNK';
    }
}