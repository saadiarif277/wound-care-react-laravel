<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientService
{
    /**
     * Create patient record and generate sequential display ID.
     */
    public function createPatientRecord(array $patientData, int $facilityId): array
    {
        // 1. Create FHIR Patient resource in Azure HDS
        $patientFhirId = $this->createFhirPatient($patientData);

        // 2. Generate sequential display ID
        $patientDisplayId = $this->generateSequentialDisplayId(
            $patientData['first_name'],
            $patientData['last_name'],
            $facilityId
        );

        // 3. Return identifiers for Supabase storage
        return [
            'patient_fhir_id' => $patientFhirId,
            'patient_display_id' => $patientDisplayId, // "JoSm001" format for UI display
        ];
    }

    /**
     * Generate patient initials from first and last name.
     */
    private function generateInitials(string $firstName, string $lastName): string
    {
        // Extract first 2 letters of each name, handle edge cases
        $cleanFirst = trim($firstName);
        $cleanFirst = preg_replace('/[^a-zA-Z]/', '', $cleanFirst);

        $cleanLast = trim($lastName);
        $cleanLast = preg_replace('/[^a-zA-Z]/', '', $cleanLast);

        if (strlen($cleanFirst) < 2 || strlen($cleanLast) < 2) {
            // Fallback for short names or special characters
            $firstInit = strlen($cleanFirst) > 0
                ? substr($cleanFirst, 0, min(2, strlen($cleanFirst)))
                : 'XX';
            $firstInit = str_pad($firstInit, 2, 'X');

            $lastInit = strlen($cleanLast) > 0
                ? substr($cleanLast, 0, min(2, strlen($cleanLast)))
                : 'XX';
            $lastInit = str_pad($lastInit, 2, 'X');

            return strtoupper($firstInit . $lastInit);
        }

        $firstInit = strtoupper(substr($cleanFirst, 0, 2));
        $lastInit = strtoupper(substr($cleanLast, 0, 2));

        return $firstInit . $lastInit;
    }

    /**
     * Generate sequential display ID for patient.
     */
    private function generateSequentialDisplayId(
        string $firstName,
        string $lastName,
        int $facilityId
    ): string {
        $baseInitials = $this->generateInitials($firstName, $lastName);

        // Get or create sequence record for this initials+facility combo
        $sequence = $this->getNextSequenceNumber($baseInitials, $facilityId);

        // Format as "JoSm001"
        return $baseInitials . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get next sequence number atomically.
     */
    private function getNextSequenceNumber(string $baseInitials, int $facilityId): int
    {
        // Call the PostgreSQL function for atomic increment
        $result = DB::select('SELECT increment_patient_sequence(?, ?) AS next_sequence', [
            $facilityId,
            $baseInitials
        ]);

        return $result[0]->next_sequence;
    }

    /**
     * Create FHIR Patient resource in Azure HDS.
     * TODO: Implement actual FHIR patient creation.
     */
    private function createFhirPatient(array $patientData): string
    {
        // TODO: Implement actual FHIR patient creation in Azure HDS
        // This should:
        // 1. Connect to Azure Health Data Services
        // 2. Create Patient FHIR resource with demographics
        // 3. Return the FHIR resource ID

        // For now, return a mock FHIR ID
        return 'Patient/' . uniqid();
    }

    /**
     * Retrieve patient data from Azure HDS by FHIR ID.
     * TODO: Implement actual FHIR patient retrieval.
     */
    public function getPatientData(string $patientFhirId): ?array
    {
        // TODO: Implement actual patient data retrieval from Azure HDS
        // This should only be called when PHI is actually needed

        return null;
    }

    /**
     * Search for existing patients by display ID.
     */
    public function searchPatientsByDisplayId(string $searchTerm, int $facilityId): array
    {
        // Search local display IDs without hitting Azure HDS
        return DB::table('product_requests')
            ->select('patient_display_id', 'patient_fhir_id')
            ->where('facility_id', $facilityId)
            ->where('patient_display_id', 'LIKE', $searchTerm . '%')
            ->distinct()
            ->get()
            ->toArray();
    }

    /**
     * Get patient display information for UI (non-PHI).
     */
    public function getPatientDisplayInfo(string $patientDisplayId): array
    {
        return [
            'patient_display_id' => $patientDisplayId,
            'display_name' => $patientDisplayId, // No age information for better privacy
        ];
    }

    /**
     * Get patient clinical factors for recommendations (non-PHI aggregated data).
     */
    public function getPatientClinicalFactors(string $patientFhirId, int $facilityId): array
    {
        // TODO: Implement actual clinical data retrieval from Azure HDS
        // This should aggregate clinical data without exposing PHI
        // Return generalized clinical factors useful for product recommendations

        return [
            'age_range' => 'unknown', // e.g., '65-74', '75-84', etc.
            'gender' => 'unknown',
            'diabetes_type' => null, // 'type1', 'type2', or null
            'hba1c_level' => null, // 'controlled', 'poor_control', etc.
            'comorbidities' => [], // array of condition codes
            'medications' => [], // array of relevant medication classes
            'allergies' => [], // array of allergy categories
            'mobility_status' => 'unknown', // 'ambulatory', 'wheelchair', 'bedbound'
            'nutrition_status' => 'unknown', // 'normal', 'malnourished', 'obese'
            'smoking_status' => 'unknown', // 'never', 'former', 'current'
            'immunocompromised' => false
        ];
    }
}
