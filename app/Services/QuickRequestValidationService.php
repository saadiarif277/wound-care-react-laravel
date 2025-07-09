<?php

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Logging\PhiSafeLogger;
use App\Services\FhirService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QuickRequestValidationService
{
    protected FhirService $fhirService;
    protected PhiSafeLogger $logger;

    // Required fields for FHIR compliance
    protected array $requiredFhirFields = [
        'patient' => ['name', 'birthDate', 'gender', 'identifier'],
        'practitioner' => ['name', 'identifier'],
        'organization' => ['name', 'identifier'],
        'condition' => ['code', 'subject'],
        'coverage' => ['beneficiary', 'payor', 'subscriberId'],
    ];

    // Common required fields across all manufacturers
    protected array $commonRequiredFields = [
        'patient_name',
        'patient_dob',
        'patient_gender',
        'patient_address',
        'patient_phone',
        'provider_name',
        'provider_npi',
        'facility_name',
        'facility_address',
        'primary_insurance_name',
        'primary_member_id',
        'wound_type',
        'wound_location',
        'diagnosis_code',
        'product_name',
        'product_hcpcs',
        'date_of_service',
        'place_of_service',
    ];

    // Manufacturer-specific required fields
    protected array $manufacturerRequiredFields = [
        'biowound-solutions' => [
            'new_request', 'patient_snf_yes', 'patient_snf_no',
            'pos_11', 'pos_21', 'pos_24', 'pos_22', 'pos_32',
            'wound_dfu', 'wound_vlu', 'wound_chronic_ulcer',
            'q4161', 'q4205', 'q4290', 'q4238', 'q4239',
            'prior_auth_yes', 'prior_auth_no'
        ],
        'medlife-solutions' => [
            'name', 'email', 'phone', 'distributor_company',
            'tax_id', 'practice_ptan', 'practice_npi',
            'icd10_code_1', 'cpt_code_1', 'hcpcs_code_1',
            'patient_global_yes', 'patient_global_no'
        ],
        'centurion-therapeutics' => [
            'check_new_wound', 'check_additional_application',
            'check_reverification', 'patient_mobile',
            'provider_medicare_number'
        ],
        'acz-associates' => [
            'authorization_number', 'urgency_level',
            'shipping_address', 'shipping_city', 'shipping_state'
        ],
        'advanced-solution' => [
            'procedure_date', 'expected_service_date',
            'clinical_notes', 'special_instructions'
        ],
    ];

    public function __construct(FhirService $fhirService, PhiSafeLogger $logger)
    {
        $this->fhirService = $fhirService;
        $this->logger = $logger;
    }

    /**
     * Validate FHIR resource compliance
     */
    public function validateFhirCompliance(array $fhirIds, array $metadata): array
    {
        $errors = [];
        $warnings = [];

        try {
            // Validate Patient resource
            if (!empty($fhirIds['patient_id'])) {
                $patientErrors = $this->validatePatientResource($fhirIds['patient_id']);
                if (!empty($patientErrors)) {
                    $errors['patient'] = $patientErrors;
                }
            } else {
                $errors['patient'] = ['Patient FHIR ID is required'];
            }

            // Validate Practitioner resource
            if (!empty($fhirIds['practitioner_id'])) {
                $practitionerErrors = $this->validatePractitionerResource($fhirIds['practitioner_id']);
                if (!empty($practitionerErrors)) {
                    $errors['practitioner'] = $practitionerErrors;
                }
            } else {
                $warnings['practitioner'] = ['Practitioner FHIR ID is missing'];
            }

            // Validate Organization resource
            if (!empty($fhirIds['organization_id'])) {
                $organizationErrors = $this->validateOrganizationResource($fhirIds['organization_id']);
                if (!empty($organizationErrors)) {
                    $errors['organization'] = $organizationErrors;
                }
            } else {
                $warnings['organization'] = ['Organization FHIR ID is missing'];
            }

            // Validate Condition resource
            if (!empty($fhirIds['condition_id'])) {
                $conditionErrors = $this->validateConditionResource($fhirIds['condition_id']);
                if (!empty($conditionErrors)) {
                    $errors['condition'] = $conditionErrors;
                }
            } else {
                $warnings['condition'] = ['Condition FHIR ID is missing'];
            }

            // Validate Coverage resource
            if (!empty($fhirIds['coverage_id'])) {
                $coverageErrors = $this->validateCoverageResource($fhirIds['coverage_id']);
                if (!empty($coverageErrors)) {
                    $errors['coverage'] = $coverageErrors;
                }
            } else {
                $warnings['coverage'] = ['Coverage FHIR ID is missing'];
            }

            // Validate resource relationships
            $relationshipErrors = $this->validateResourceRelationships($fhirIds);
            if (!empty($relationshipErrors)) {
                $errors['relationships'] = $relationshipErrors;
            }

            $this->logger->info('FHIR compliance validation completed', [
                'fhir_ids' => array_keys($fhirIds),
                'errors_count' => count($errors),
                'warnings_count' => count($warnings)
            ]);

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'compliance_score' => $this->calculateComplianceScore($errors, $warnings)
            ];

        } catch (\Exception $e) {
            $this->logger->error('FHIR compliance validation failed', [
                'error' => $e->getMessage(),
                'fhir_ids' => $fhirIds
            ]);

            return [
                'valid' => false,
                'errors' => ['validation' => ['Failed to validate FHIR compliance: ' . $e->getMessage()]],
                'warnings' => [],
                'compliance_score' => 0
            ];
        }
    }

    /**
     * Validate IVR form completeness
     */
    public function validateIvrFormCompleteness(array $data, string $manufacturerName): array
    {
        $errors = [];
        $warnings = [];

        try {
            // Validate common required fields
            foreach ($this->commonRequiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = "Required field '{$field}' is missing or empty";
                }
            }

            // Validate manufacturer-specific required fields
            $manufacturerKey = $this->getManufacturerKey($manufacturerName);
            if (isset($this->manufacturerRequiredFields[$manufacturerKey])) {
                foreach ($this->manufacturerRequiredFields[$manufacturerKey] as $field) {
                    if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                        $errors[$field] = "Manufacturer-specific field '{$field}' is required for {$manufacturerName}";
                    }
                }
            }

            // Validate data types and formats
            $formatErrors = $this->validateDataFormats($data);
            if (!empty($formatErrors)) {
                $errors = array_merge($errors, $formatErrors);
            }

            // Validate business rules
            $businessRuleErrors = $this->validateBusinessRules($data);
            if (!empty($businessRuleErrors)) {
                $errors = array_merge($errors, $businessRuleErrors);
            }

            // Check for data quality issues
            $qualityWarnings = $this->validateDataQuality($data);
            if (!empty($qualityWarnings)) {
                $warnings = array_merge($warnings, $qualityWarnings);
            }

            $completenessScore = $this->calculateCompletenessScore($data, $manufacturerName);

            $this->logger->info('IVR form completeness validation completed', [
                'manufacturer' => $manufacturerName,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
                'completeness_score' => $completenessScore
            ]);

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'completeness_score' => $completenessScore,
                'manufacturer' => $manufacturerName,
                'total_fields' => count($data),
                'completed_fields' => count(array_filter($data, fn($value) => !empty($value)))
            ];

        } catch (\Exception $e) {
            $this->logger->error('IVR form completeness validation failed', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturerName
            ]);

            return [
                'valid' => false,
                'errors' => ['validation' => ['Failed to validate IVR form completeness: ' . $e->getMessage()]],
                'warnings' => [],
                'completeness_score' => 0
            ];
        }
    }

    /**
     * Validate episode data consistency
     */
    public function validateEpisodeConsistency(PatientManufacturerIVREpisode $episode): array
    {
        $errors = [];
        $warnings = [];

        try {
            $metadata = $episode->metadata ?? [];

            // Validate patient data consistency
            if (isset($metadata['patient_data'])) {
                $patientConsistency = $this->validatePatientDataConsistency($metadata['patient_data'], $episode);
                if (!empty($patientConsistency['errors'])) {
                    $errors['patient_consistency'] = $patientConsistency['errors'];
                }
                if (!empty($patientConsistency['warnings'])) {
                    $warnings['patient_consistency'] = $patientConsistency['warnings'];
                }
            }

            // Validate provider data consistency
            if (isset($metadata['provider_data'])) {
                $providerConsistency = $this->validateProviderDataConsistency($metadata['provider_data']);
                if (!empty($providerConsistency['errors'])) {
                    $errors['provider_consistency'] = $providerConsistency['errors'];
                }
                if (!empty($providerConsistency['warnings'])) {
                    $warnings['provider_consistency'] = $providerConsistency['warnings'];
                }
            }

            // Validate clinical data consistency
            if (isset($metadata['clinical_data'])) {
                $clinicalConsistency = $this->validateClinicalDataConsistency($metadata['clinical_data']);
                if (!empty($clinicalConsistency['errors'])) {
                    $errors['clinical_consistency'] = $clinicalConsistency['errors'];
                }
                if (!empty($clinicalConsistency['warnings'])) {
                    $warnings['clinical_consistency'] = $clinicalConsistency['warnings'];
                }
            }

            // Validate insurance data consistency
            if (isset($metadata['insurance_data'])) {
                $insuranceConsistency = $this->validateInsuranceDataConsistency($metadata['insurance_data']);
                if (!empty($insuranceConsistency['errors'])) {
                    $errors['insurance_consistency'] = $insuranceConsistency['errors'];
                }
                if (!empty($insuranceConsistency['warnings'])) {
                    $warnings['insurance_consistency'] = $insuranceConsistency['warnings'];
                }
            }

            $this->logger->info('Episode consistency validation completed', [
                'episode_id' => $episode->id,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings)
            ]);

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'consistency_score' => $this->calculateConsistencyScore($errors, $warnings)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Episode consistency validation failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'errors' => ['validation' => ['Failed to validate episode consistency: ' . $e->getMessage()]],
                'warnings' => [],
                'consistency_score' => 0
            ];
        }
    }

    /**
     * Validate Patient FHIR resource
     */
    protected function validatePatientResource(string $patientId): array
    {
        $errors = [];

        try {
            $patient = $this->fhirService->read('Patient', $patientId);
            
            if (!$patient) {
                return ['Patient resource not found'];
            }

            // Validate required fields
            if (empty($patient['name'])) {
                $errors[] = 'Patient name is required';
            }

            if (empty($patient['birthDate'])) {
                $errors[] = 'Patient birth date is required';
            }

            if (empty($patient['gender'])) {
                $errors[] = 'Patient gender is required';
            }

            if (empty($patient['identifier'])) {
                $errors[] = 'Patient identifier is required';
            }

            // Validate data formats
            if (!empty($patient['birthDate']) && !$this->isValidDate($patient['birthDate'])) {
                $errors[] = 'Patient birth date format is invalid';
            }

            if (!empty($patient['gender']) && !in_array($patient['gender'], ['male', 'female', 'other', 'unknown'])) {
                $errors[] = 'Patient gender must be one of: male, female, other, unknown';
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate Patient resource: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate Practitioner FHIR resource
     */
    protected function validatePractitionerResource(string $practitionerId): array
    {
        $errors = [];

        try {
            $practitioner = $this->fhirService->read('Practitioner', $practitionerId);
            
            if (!$practitioner) {
                return ['Practitioner resource not found'];
            }

            // Validate required fields
            if (empty($practitioner['name'])) {
                $errors[] = 'Practitioner name is required';
            }

            if (empty($practitioner['identifier'])) {
                $errors[] = 'Practitioner identifier is required';
            }

            // Validate identifier systems
            if (!empty($practitioner['identifier'])) {
                $hasNpi = false;
                foreach ($practitioner['identifier'] as $identifier) {
                    if (($identifier['system'] ?? '') === 'NPI') {
                        $hasNpi = true;
                        break;
                    }
                }
                if (!$hasNpi) {
                    $errors[] = 'Practitioner must have an NPI identifier';
                }
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate Practitioner resource: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate Organization FHIR resource
     */
    protected function validateOrganizationResource(string $organizationId): array
    {
        $errors = [];

        try {
            $organization = $this->fhirService->read('Organization', $organizationId);
            
            if (!$organization) {
                return ['Organization resource not found'];
            }

            // Validate required fields
            if (empty($organization['name'])) {
                $errors[] = 'Organization name is required';
            }

            if (empty($organization['identifier'])) {
                $errors[] = 'Organization identifier is required';
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate Organization resource: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate Condition FHIR resource
     */
    protected function validateConditionResource(string $conditionId): array
    {
        $errors = [];

        try {
            $condition = $this->fhirService->read('Condition', $conditionId);
            
            if (!$condition) {
                return ['Condition resource not found'];
            }

            // Validate required fields
            if (empty($condition['code'])) {
                $errors[] = 'Condition code is required';
            }

            if (empty($condition['subject'])) {
                $errors[] = 'Condition subject is required';
            }

            // Validate code system
            if (!empty($condition['code']['coding'])) {
                $hasIcd10 = false;
                foreach ($condition['code']['coding'] as $coding) {
                    if (strpos($coding['system'] ?? '', 'icd') !== false) {
                        $hasIcd10 = true;
                        break;
                    }
                }
                if (!$hasIcd10) {
                    $errors[] = 'Condition must have an ICD-10 code';
                }
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate Condition resource: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate Coverage FHIR resource
     */
    protected function validateCoverageResource(string $coverageId): array
    {
        $errors = [];

        try {
            $coverage = $this->fhirService->read('Coverage', $coverageId);
            
            if (!$coverage) {
                return ['Coverage resource not found'];
            }

            // Validate required fields
            if (empty($coverage['beneficiary'])) {
                $errors[] = 'Coverage beneficiary is required';
            }

            if (empty($coverage['payor'])) {
                $errors[] = 'Coverage payor is required';
            }

            if (empty($coverage['subscriberId'])) {
                $errors[] = 'Coverage subscriber ID is required';
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate Coverage resource: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate resource relationships
     */
    protected function validateResourceRelationships(array $fhirIds): array
    {
        $errors = [];

        // Validate that Patient is referenced by other resources
        if (!empty($fhirIds['patient_id'])) {
            if (!empty($fhirIds['condition_id'])) {
                // Validate that Condition references the Patient
                try {
                    $condition = $this->fhirService->read('Condition', $fhirIds['condition_id']);
                    if ($condition && !str_contains($condition['subject']['reference'] ?? '', $fhirIds['patient_id'])) {
                        $errors[] = 'Condition does not reference the correct Patient';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Failed to validate Condition-Patient relationship';
                }
            }

            if (!empty($fhirIds['coverage_id'])) {
                // Validate that Coverage references the Patient
                try {
                    $coverage = $this->fhirService->read('Coverage', $fhirIds['coverage_id']);
                    if ($coverage && !str_contains($coverage['beneficiary']['reference'] ?? '', $fhirIds['patient_id'])) {
                        $errors[] = 'Coverage does not reference the correct Patient';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Failed to validate Coverage-Patient relationship';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate data formats
     */
    protected function validateDataFormats(array $data): array
    {
        $errors = [];

        // Validate date formats
        $dateFields = ['patient_dob', 'procedure_date', 'expected_service_date'];
        foreach ($dateFields as $field) {
            if (!empty($data[$field]) && !$this->isValidDate($data[$field])) {
                $errors[$field] = "Invalid date format for {$field}";
            }
        }

        // Validate phone numbers
        $phoneFields = ['patient_phone', 'provider_phone', 'facility_phone'];
        foreach ($phoneFields as $field) {
            if (!empty($data[$field]) && !$this->isValidPhoneNumber($data[$field])) {
                $errors[$field] = "Invalid phone number format for {$field}";
            }
        }

        // Validate email addresses
        $emailFields = ['patient_email', 'provider_email', 'facility_email'];
        foreach ($emailFields as $field) {
            if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Invalid email format for {$field}";
            }
        }

        // Validate NPI numbers
        if (!empty($data['provider_npi']) && !$this->isValidNpi($data['provider_npi'])) {
            $errors['provider_npi'] = 'Invalid NPI number format';
        }

        return $errors;
    }

    /**
     * Validate business rules
     */
    protected function validateBusinessRules(array $data): array
    {
        $errors = [];

        // Validate that wound type matches diagnosis
        if (!empty($data['wound_type']) && !empty($data['diagnosis_code'])) {
            if (!$this->isWoundTypeCompatibleWithDiagnosis($data['wound_type'], $data['diagnosis_code'])) {
                $errors['wound_type_diagnosis'] = 'Wound type is not compatible with diagnosis code';
            }
        }

        // Validate that patient is not deceased
        if (!empty($data['patient_dob'])) {
            $age = $this->calculateAge($data['patient_dob']);
            if ($age > 120) {
                $errors['patient_age'] = 'Patient age seems unrealistic';
            }
        }

        // Validate place of service
        if (!empty($data['place_of_service']) && !$this->isValidPlaceOfService($data['place_of_service'])) {
            $errors['place_of_service'] = 'Invalid place of service code';
        }

        return $errors;
    }

    /**
     * Validate data quality
     */
    protected function validateDataQuality(array $data): array
    {
        $warnings = [];

        // Check for missing optional but important fields
        $importantFields = ['patient_email', 'provider_email', 'facility_fax', 'secondary_insurance_name'];
        foreach ($importantFields as $field) {
            if (empty($data[$field])) {
                $warnings[] = "Optional field '{$field}' is missing but recommended";
            }
        }

        // Check for suspicious data patterns
        if (!empty($data['patient_phone']) && !empty($data['provider_phone']) && $data['patient_phone'] === $data['provider_phone']) {
            $warnings[] = 'Patient and provider have the same phone number';
        }

        return $warnings;
    }

    /**
     * Calculate compliance score
     */
    protected function calculateComplianceScore(array $errors, array $warnings): int
    {
        $totalIssues = count($errors) + (count($warnings) * 0.5);
        $maxScore = 100;
        $penaltyPerIssue = 10;

        return max(0, $maxScore - ($totalIssues * $penaltyPerIssue));
    }

    /**
     * Calculate completeness score
     */
    protected function calculateCompletenessScore(array $data, string $manufacturerName): int
    {
        $manufacturerKey = $this->getManufacturerKey($manufacturerName);
        $requiredFields = array_merge(
            $this->commonRequiredFields,
            $this->manufacturerRequiredFields[$manufacturerKey] ?? []
        );

        $totalFields = count($requiredFields);
        $completedFields = 0;

        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $completedFields++;
            }
        }

        return $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
    }

    /**
     * Calculate consistency score
     */
    protected function calculateConsistencyScore(array $errors, array $warnings): int
    {
        $totalIssues = count($errors) + (count($warnings) * 0.3);
        $maxScore = 100;
        $penaltyPerIssue = 8;

        return max(0, $maxScore - ($totalIssues * $penaltyPerIssue));
    }

    /**
     * Helper methods
     */
    protected function getManufacturerKey(string $manufacturerName): string
    {
        return strtolower(str_replace([' ', '&'], ['-', ''], $manufacturerName));
    }

    protected function isValidDate(string $date): bool
    {
        return (bool) strtotime($date);
    }

    protected function isValidPhoneNumber(string $phone): bool
    {
        return (bool) preg_match('/^[\+]?[1-9][\d]{3,14}$/', preg_replace('/[^\d+]/', '', $phone));
    }

    protected function isValidNpi(string $npi): bool
    {
        return (bool) preg_match('/^\d{10}$/', $npi);
    }

    protected function isValidPlaceOfService(string $pos): bool
    {
        $validCodes = ['11', '12', '13', '21', '22', '24', '32', '85'];
        return in_array($pos, $validCodes);
    }

    protected function calculateAge(string $birthDate): int
    {
        return (int) date_diff(date_create($birthDate), date_create('today'))->y;
    }

    protected function isWoundTypeCompatibleWithDiagnosis(string $woundType, string $diagnosisCode): bool
    {
        // This would contain actual business logic for wound type/diagnosis compatibility
        // For now, return true as a placeholder
        return true;
    }

    protected function validatePatientDataConsistency(array $patientData, PatientManufacturerIVREpisode $episode): array
    {
        $errors = [];
        $warnings = [];

        // Validate patient display ID consistency
        if (!empty($patientData['display_id']) && !empty($episode->patient_display_id)) {
            if ($patientData['display_id'] !== $episode->patient_display_id) {
                $errors[] = 'Patient display ID mismatch between form data and episode';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    protected function validateProviderDataConsistency(array $providerData): array
    {
        $errors = [];
        $warnings = [];

        // Validate provider NPI consistency
        if (!empty($providerData['npi']) && !$this->isValidNpi($providerData['npi'])) {
            $errors[] = 'Provider NPI format is invalid';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    protected function validateClinicalDataConsistency(array $clinicalData): array
    {
        $errors = [];
        $warnings = [];

        // Validate wound size consistency
        if (!empty($clinicalData['wound_length']) && !empty($clinicalData['wound_width'])) {
            if (!is_numeric($clinicalData['wound_length']) || !is_numeric($clinicalData['wound_width'])) {
                $errors[] = 'Wound dimensions must be numeric';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    protected function validateInsuranceDataConsistency(array $insuranceData): array
    {
        $errors = [];
        $warnings = [];

        // Validate insurance data format
        if (!empty($insuranceData['primary_member_id']) && strlen($insuranceData['primary_member_id']) < 3) {
            $warnings[] = 'Primary member ID seems too short';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
} 