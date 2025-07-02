<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\FhirService;
use App\Services\PhiAuditService;

class PatientService
{
    private FhirService $fhirService;

    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }
    /**
     * Create patient record and generate sequential display ID.
     * Creates actual FHIR Patient resource in Azure Health Data Services.
     * Includes fallback mechanisms for error cases.
     */
    public function createPatientRecord(array $patientData, int $facilityId): array
    {
        try {
            // Try to generate a proper display ID first
            try {
                $displayId = $this->generateDisplayId(
                    $patientData['first_name'],
                    $patientData['last_name'],
                    $facilityId
                );
            } catch (\Exception $e) {
                // Fallback to a temporary ID if sequence generation fails
                Log::warning('Sequence generation failed, using fallback ID', [
                    'error' => $e->getMessage(),
                    'data' => $patientData
                ]);
                $displayId = $this->generateFallbackId($patientData, $facilityId);
            }

            // Create FHIR Patient resource in Azure
            $fhirPatient = $this->createFhirPatient($patientData, $displayId);
            $fhirId = 'Patient/' . $fhirPatient['id'];

            // Store the record mapping
            $this->storePatientRecord($displayId, $fhirId, $facilityId, $patientData);

            // Audit PHI creation
            PhiAuditService::logCreation('Patient', $fhirId, [
                'display_id' => $displayId,
                'facility_id' => $facilityId,
                'has_member_id' => !empty($patientData['member_id'])
            ]);

            return [
                'patient_fhir_id' => $fhirId,
                'patient_display_id' => $displayId,
                'is_temporary' => str_starts_with($displayId, 'TEMP-'),
                'fhir_resource' => $fhirPatient
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create patient record', [
                'error' => $e->getMessage(),
                'data' => $patientData
            ]);
            // Return a temporary ID as last resort
            $tempId = 'Patient/' . Str::uuid();
            $tempDisplayId = $this->generateEmergencyId($facilityId);

            // Try to store the temporary mapping
            try {
                $this->storePatientRecord($tempDisplayId, $tempId, $facilityId, $patientData);
            } catch (\Exception $storeError) {
                Log::error('Failed to store temporary patient mapping', ['error' => $storeError->getMessage()]);
            }

            return [
                'patient_fhir_id' => $tempId,
                'patient_display_id' => $tempDisplayId,
                'is_temporary' => true
            ];
        }
    }

    /**
     * Create FHIR Patient resource in Azure Health Data Services.
     */
    private function createFhirPatient(array $patientData, string $displayId): array
    {
        $fhirData = [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'use' => 'usual',
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'MR',
                                'display' => 'Medical record number'
                            ]
                        ]
                    ],
                    'value' => $displayId,
                    'assigner' => [
                        'display' => 'MSC Wound Portal'
                    ]
                ]
            ],
            'active' => true,
            'name' => [
                [
                    'use' => 'official',
                    'family' => $patientData['last_name'] ?? '',
                    'given' => [$patientData['first_name'] ?? '']
                ]
            ],
            'gender' => $this->mapGenderToFhir($patientData['gender'] ?? null),
            'birthDate' => $patientData['date_of_birth'] ?? null
        ];

        // Add member ID if provided
        if (!empty($patientData['member_id'])) {
            $fhirData['identifier'][] = [
                'use' => 'usual',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MB',
                            'display' => 'Member number'
                        ]
                    ]
                ],
                'value' => $patientData['member_id']
            ];
        }

        // Add contact information if provided
        if (!empty($patientData['phone']) || !empty($patientData['email'])) {
            $fhirData['telecom'] = [];
            if (!empty($patientData['phone'])) {
                $fhirData['telecom'][] = [
                    'system' => 'phone',
                    'value' => $patientData['phone'],
                    'use' => 'mobile'
                ];
            }
            if (!empty($patientData['email'])) {
                $fhirData['telecom'][] = [
                    'system' => 'email',
                    'value' => $patientData['email'],
                    'use' => 'home'
                ];
            }
        }

        // Add address if provided
        if (!empty($patientData['address'])) {
            $fhirData['address'] = [
                [
                    'use' => 'home',
                    'line' => [$patientData['address']['line1']],
                    'city' => $patientData['address']['city'] ?? null,
                    'state' => $patientData['address']['state'] ?? null,
                    'postalCode' => $patientData['address']['zip'] ?? null,
                    'country' => 'US'
                ]
            ];
            if (!empty($patientData['address']['line2'])) {
                $fhirData['address'][0]['line'][] = $patientData['address']['line2'];
            }
        }

        return $this->fhirService->createPatient($fhirData);
    }

    /**
     * Map gender value to FHIR-compliant value.
     */
    private function mapGenderToFhir(?string $gender): string
    {
        if (!$gender) {
            return 'unknown';
        }

        $genderMap = [
            'm' => 'male',
            'male' => 'male',
            'f' => 'female',
            'female' => 'female',
            'o' => 'other',
            'other' => 'other',
            'u' => 'unknown',
            'unknown' => 'unknown'
        ];

        return $genderMap[strtolower($gender)] ?? 'unknown';
    }

    /**
     * Store patient record in database.
     */
    private function storePatientRecord(string $displayId, string $fhirId, int $facilityId, array $data): void
    {
        try {
            DB::table('product_requests')->insert([
                'patient_display_id' => $displayId,
                'patient_fhir_id' => $fhirId,
                'facility_id' => $facilityId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store patient record', [
                'display_id' => $displayId,
                'error' => $e->getMessage()
            ]);
            // Continue execution even if storage fails
        }
    }

    /**
     * Generate display ID for patient with random numbers (e.g., "JOSM473").
     * Uses first 2 letters of first name + first 2 letters of last name + 3 random digits.
     */
    private function generateDisplayId(string $firstName, string $lastName, int $facilityId): string
    {
        $initials = $this->getInitials($firstName, $lastName);

        // Generate random 3-digit number
        $randomNumber = mt_rand(100, 999);

        // Check if this combination already exists for the facility
        $attempts = 0;
        $maxAttempts = 10;

        while ($attempts < $maxAttempts) {
            $displayId = $initials . $randomNumber;

            // Check if this ID already exists
            $exists = DB::table('product_requests')
                ->where('facility_id', $facilityId)
                ->where('patient_display_id', $displayId)
                ->exists();

            if (!$exists) {
                return $displayId;
            }

            // Try a new random number
            $randomNumber = mt_rand(100, 999);
            $attempts++;
        }

        // If we couldn't find a unique ID after max attempts,
        // fall back to sequential approach
        $sequence = $this->getSequence($facilityId, $initials);
        return $initials . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a fallback ID when sequence generation fails.
     * Maximum length: 7 characters (database constraint)
     */
    private function generateFallbackId(array $patientData, int $facilityId): string
    {
        $initials = $this->getInitials($patientData['first_name'], $patientData['last_name']);
        $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        return $initials . $random; // 7 characters: 4+3
    }

    /**
     * Generate an emergency ID as last resort.
     * Maximum length: 7 characters (database constraint)
     */
    private function generateEmergencyId(int $facilityId): string
    {
        $facilityCode = str_pad((string)$facilityId, 2, '0', STR_PAD_LEFT);
        $random = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        return $facilityCode . $random; // 7 characters: 2+5
    }

    /**
     * Get patient initials from name.
     */
    private function getInitials(string $firstName, string $lastName): string
    {
        $first = substr(preg_replace('/[^a-zA-Z]/', '', $firstName), 0, 2);
        $last = substr(preg_replace('/[^a-zA-Z]/', '', $lastName), 0, 2);

        // Handle short names
        $first = str_pad($first, 2, 'X');
        $last = str_pad($last, 2, 'X');

        return strtoupper($first . $last);
    }

    /**
     * Get next sequence number for initials.
     * Includes fallback to random number if database fails.
     */
    private function getSequence(int $facilityId, string $initials): int
    {
        try {
            // Try to get sequence from database
            $result = DB::select(
                'SELECT increment_patient_sequence(?, ?) as num',
                [$facilityId, $initials]
            );

            if (!empty($result) && isset($result[0]->num)) {
                $sequence = (int) $result[0]->num;
                if ($sequence > 0) {
                    return $sequence;
                }
            }

            // Fallback to random number if database fails
            Log::warning('Using fallback sequence number', [
                'facility' => $facilityId,
                'initials' => $initials
            ]);
            return mt_rand(100, 999);

        } catch (\Exception $e) {
            Log::error('Sequence generation failed, using fallback', [
                'facility' => $facilityId,
                'initials' => $initials,
                'error' => $e->getMessage()
            ]);
            // Return a random number as fallback
            return mt_rand(100, 999);
        }
    }

    /**
     * Search patients by display ID.
     * Includes fallback for collation issues.
     */
    public function searchPatientsByDisplayId(string $searchTerm, int $facilityId): array
    {
        try {
            // Try normal search first
            $results = DB::table('product_requests')
                ->select('patient_display_id', 'patient_fhir_id')
                ->where('facility_id', $facilityId)
                ->where('patient_display_id', 'LIKE', $searchTerm . '%')
                ->distinct()
                ->get()
                ->toArray();

            if (!empty($results)) {
                return $results;
            }

            // Fallback to case-insensitive search if no results
            return DB::table('product_requests')
                ->select('patient_display_id', 'patient_fhir_id')
                ->where('facility_id', $facilityId)
                ->whereRaw('LOWER(patient_display_id) LIKE ?', [strtolower($searchTerm) . '%'])
                ->distinct()
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('Patient search failed', [
                'term' => $searchTerm,
                'facility' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get basic patient display info.
     */
    public function getPatientDisplayInfo(string $displayId): array
    {
        return [
            'patient_display_id' => $displayId,
            'display_name' => $displayId,
            'is_temporary' => str_starts_with($displayId, 'TEMP-') || str_starts_with($displayId, 'EMERG-')
        ];
    }

    /**
     * Get patient clinical factors (mock data for now).
     */
    public function getPatientClinicalFactors(string $fhirId, int $facilityId): array
    {
        return [
            'age_range' => 'unknown',
            'gender' => 'unknown',
            'diabetes_type' => null,
            'hba1c_level' => null,
            'comorbidities' => [],
            'medications' => [],
            'allergies' => [],
            'mobility_status' => 'unknown',
            'nutrition_status' => 'unknown',
            'smoking_status' => 'unknown',
            'immunocompromised' => false
        ];
    }
}

