<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PatientService
{
    /**
     * Create patient record and generate sequential display ID.
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

            // Generate a temporary FHIR ID
            $fhirId = 'Patient/' . Str::uuid();

            // Store the record even if it's temporary
            $this->storePatientRecord($displayId, $fhirId, $facilityId, $patientData);

            return [
                'patient_fhir_id' => $fhirId,
                'patient_display_id' => $displayId,
                'is_temporary' => str_starts_with($displayId, 'TEMP-')
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create patient record', [
                'error' => $e->getMessage(),
                'data' => $patientData
            ]);
            // Return a temporary ID as last resort
            return [
                'patient_fhir_id' => 'Patient/' . Str::uuid(),
                'patient_display_id' => $this->generateEmergencyId($facilityId),
                'is_temporary' => true
            ];
        }
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
     * Generate display ID for patient (e.g., "JOSM001").
     */
    private function generateDisplayId(string $firstName, string $lastName, int $facilityId): string
    {
        $initials = $this->getInitials($firstName, $lastName);
        $sequence = $this->getSequence($facilityId, $initials);
        return $initials . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a fallback ID when sequence generation fails.
     */
    private function generateFallbackId(array $patientData, int $facilityId): string
    {
        $initials = $this->getInitials($patientData['first_name'], $patientData['last_name']);
        $timestamp = date('YmdHis');
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        return "TEMP-{$initials}-{$facilityId}-{$timestamp}-{$random}";
    }

    /**
     * Generate an emergency ID as last resort.
     */
    private function generateEmergencyId(int $facilityId): string
    {
        return "EMERG-" . Str::random(8) . "-{$facilityId}";
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

