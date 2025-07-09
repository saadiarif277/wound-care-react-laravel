<?php

namespace App\Services;

use App\Models\Fhir\Patient;
use App\Models\Fhir\Practitioner;
use App\Models\Fhir\Coverage;
use App\Models\Fhir\Condition;
use App\Models\Fhir\Observation;
use App\Models\Fhir\Encounter;
use App\Models\Fhir\DocumentReference;
use App\Models\Users\User;
use App\Models\Organization\Organization;
use App\Models\Organization\Facility;
use App\Logging\PhiSafeLogger;
use App\Services\FhirService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FhirToIvrFieldMapper
{
    protected FhirService $fhirService;
    protected PhiSafeLogger $logger;
    protected array $fieldAliases;

    public function __construct(FhirService $fhirService, PhiSafeLogger $logger)
    {
        $this->fhirService = $fhirService;
        $this->logger = $logger;
        $this->fieldAliases = $this->getFieldAliases();
    }

    /**
     * Extract comprehensive data from FHIR resources for IVR form filling
     */
    public function extractDataFromFhir(array $fhirIds, array $fallbackData = []): array
    {
        $extractedData = [];

        try {
            // 1. Extract patient data
            if (!empty($fhirIds['patient_id'])) {
                $patientData = $this->extractPatientData($fhirIds['patient_id']);
                $extractedData = array_merge($extractedData, $patientData);
            }

            // 2. Extract provider/practitioner data
            if (!empty($fhirIds['practitioner_id'])) {
                $providerData = $this->extractProviderData($fhirIds['practitioner_id']);
                $extractedData = array_merge($extractedData, $providerData);
            }

            // 3. Extract organization/facility data
            if (!empty($fhirIds['organization_id'])) {
                $facilityData = $this->extractFacilityData($fhirIds['organization_id']);
                $extractedData = array_merge($extractedData, $facilityData);
            }

            // 4. Extract clinical data
            if (!empty($fhirIds['condition_id'])) {
                $clinicalData = $this->extractClinicalData($fhirIds['condition_id']);
                $extractedData = array_merge($extractedData, $clinicalData);
            }

            // 5. Extract insurance data
            if (!empty($fhirIds['coverage_id'])) {
                $insuranceData = $this->extractInsuranceData($fhirIds['coverage_id']);
                $extractedData = array_merge($extractedData, $insuranceData);
            }

            // 6. Extract current user contact data
            $userContactData = $this->extractUserContactData();
            $extractedData = array_merge($extractedData, $userContactData);

            // 7. Merge with fallback data (form data takes precedence)
            $extractedData = array_merge($extractedData, $fallbackData);

            // 8. Apply field aliases and transformations
            $extractedData = $this->applyFieldAliases($extractedData);
            $extractedData = $this->applyTransformations($extractedData);

            $this->logger->info('FHIR data extracted successfully', [
                'fhir_ids' => $fhirIds,
                'extracted_fields' => count($extractedData)
            ]);

            return $extractedData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract FHIR data', [
                'error' => $e->getMessage(),
                'fhir_ids' => $fhirIds
            ]);

            // Return fallback data if FHIR extraction fails
            return $fallbackData;
        }
    }

    /**
     * Extract patient data from FHIR Patient resource
     */
    protected function extractPatientData(string $patientId): array
    {
        $data = [];

        try {
            $patientResource = $this->fhirService->read('Patient', $patientId);
            
            if ($patientResource) {
                // Basic demographics
                $data['patient_first_name'] = $patientResource['name'][0]['given'][0] ?? '';
                $data['patient_last_name'] = $patientResource['name'][0]['family'] ?? '';
                $data['patient_name'] = trim(($data['patient_first_name'] ?? '') . ' ' . ($data['patient_last_name'] ?? ''));
                $data['patient_gender'] = $patientResource['gender'] ?? '';
                $data['patient_dob'] = $patientResource['birthDate'] ?? '';
                
                // Contact information
                if (!empty($patientResource['telecom'])) {
                    foreach ($patientResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'phone') {
                            $data['patient_phone'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                    foreach ($patientResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'email') {
                            $data['patient_email'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                }

                // Address information
                if (!empty($patientResource['address'][0])) {
                    $address = $patientResource['address'][0];
                    $data['patient_address'] = $address['text'] ?? '';
                    $data['patient_address_line1'] = $address['line'][0] ?? '';
                    $data['patient_address_line2'] = $address['line'][1] ?? '';
                    $data['patient_city'] = $address['city'] ?? '';
                    $data['patient_state'] = $address['state'] ?? '';
                    $data['patient_zip'] = $address['postalCode'] ?? '';
                    $data['patient_country'] = $address['country'] ?? 'US';
                }

                // Identifiers
                if (!empty($patientResource['identifier'])) {
                    foreach ($patientResource['identifier'] as $identifier) {
                        if ($identifier['system'] === 'mrn') {
                            $data['patient_display_id'] = $identifier['value'] ?? '';
                            break;
                        }
                    }
                }

                // MSC Extensions
                if (!empty($patientResource['extension'])) {
                    foreach ($patientResource['extension'] as $extension) {
                        if ($extension['url'] === 'http://msc-mvp.com/fhir/StructureDefinition/wound-care-consent') {
                            $data['wound_care_consent'] = $extension['valueCode'] ?? '';
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract patient data', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Extract provider/practitioner data from FHIR Practitioner resource
     */
    protected function extractProviderData(string $practitionerId): array
    {
        $data = [];

        try {
            $practitionerResource = $this->fhirService->read('Practitioner', $practitionerId);
            
            if ($practitionerResource) {
                // Basic information
                $data['provider_first_name'] = $practitionerResource['name'][0]['given'][0] ?? '';
                $data['provider_last_name'] = $practitionerResource['name'][0]['family'] ?? '';
                $data['provider_name'] = trim(($data['provider_first_name'] ?? '') . ' ' . ($data['provider_last_name'] ?? ''));
                $data['physician_name'] = $data['provider_name']; // Alias

                // Contact information
                if (!empty($practitionerResource['telecom'])) {
                    foreach ($practitionerResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'phone') {
                            $data['provider_phone'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                    foreach ($practitionerResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'email') {
                            $data['provider_email'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                }

                // Identifiers
                if (!empty($practitionerResource['identifier'])) {
                    foreach ($practitionerResource['identifier'] as $identifier) {
                        switch ($identifier['system']) {
                            case 'NPI':
                                $data['provider_npi'] = $identifier['value'] ?? '';
                                $data['physician_npi'] = $identifier['value'] ?? '';
                                break;
                            case 'PTAN':
                                $data['provider_ptan'] = $identifier['value'] ?? '';
                                $data['physician_ptan'] = $identifier['value'] ?? '';
                                break;
                            case 'DEA':
                                $data['provider_dea_number'] = $identifier['value'] ?? '';
                                break;
                            case 'LICENSE':
                                $data['provider_license_number'] = $identifier['value'] ?? '';
                                break;
                        }
                    }
                }

                // Qualifications
                if (!empty($practitionerResource['qualification'])) {
                    foreach ($practitionerResource['qualification'] as $qualification) {
                        if ($qualification['code']['coding'][0]['system'] === 'specialty') {
                            $data['provider_specialty'] = $qualification['code']['coding'][0]['display'] ?? '';
                            $data['physician_specialty'] = $data['provider_specialty'];
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract provider data', [
                'practitioner_id' => $practitionerId,
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Extract facility/organization data from FHIR Organization resource
     */
    protected function extractFacilityData(string $organizationId): array
    {
        $data = [];

        try {
            $organizationResource = $this->fhirService->read('Organization', $organizationId);
            
            if ($organizationResource) {
                // Basic information
                $data['facility_name'] = $organizationResource['name'] ?? '';
                $data['organization_name'] = $data['facility_name'];
                $data['practice_name'] = $data['facility_name'];

                // Contact information
                if (!empty($organizationResource['telecom'])) {
                    foreach ($organizationResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'phone') {
                            $data['facility_phone'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                    foreach ($organizationResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'fax') {
                            $data['facility_fax'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                    foreach ($organizationResource['telecom'] as $telecom) {
                        if ($telecom['system'] === 'email') {
                            $data['facility_email'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                }

                // Address information
                if (!empty($organizationResource['address'][0])) {
                    $address = $organizationResource['address'][0];
                    $data['facility_address'] = $address['text'] ?? '';
                    $data['facility_address_line1'] = $address['line'][0] ?? '';
                    $data['facility_address_line2'] = $address['line'][1] ?? '';
                    $data['facility_city'] = $address['city'] ?? '';
                    $data['facility_state'] = $address['state'] ?? '';
                    $data['facility_zip_code'] = $address['postalCode'] ?? '';
                    
                    // BioWound specific field
                    $data['city_state_zip'] = trim(
                        ($data['facility_city'] ?? '') . ', ' .
                        ($data['facility_state'] ?? '') . ' ' .
                        ($data['facility_zip_code'] ?? '')
                    );
                }

                // Identifiers
                if (!empty($organizationResource['identifier'])) {
                    foreach ($organizationResource['identifier'] as $identifier) {
                        switch ($identifier['system']) {
                            case 'NPI':
                                $data['facility_npi'] = $identifier['value'] ?? '';
                                break;
                            case 'PTAN':
                                $data['facility_ptan'] = $identifier['value'] ?? '';
                                break;
                            case 'TAX_ID':
                                $data['facility_tax_id'] = $identifier['value'] ?? '';
                                $data['organization_tax_id'] = $identifier['value'] ?? '';
                                break;
                        }
                    }
                }

                // Type and extensions
                if (!empty($organizationResource['type'])) {
                    foreach ($organizationResource['type'] as $type) {
                        if ($type['coding'][0]['system'] === 'facility_type') {
                            $data['facility_type'] = $type['coding'][0]['display'] ?? '';
                        }
                        if ($type['coding'][0]['system'] === 'place_of_service') {
                            $data['place_of_service'] = $type['coding'][0]['code'] ?? '';
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract facility data', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Extract clinical data from FHIR Condition resource
     */
    protected function extractClinicalData(string $conditionId): array
    {
        $data = [];

        try {
            $conditionResource = $this->fhirService->read('Condition', $conditionId);
            
            if ($conditionResource) {
                // Basic diagnosis information
                if (!empty($conditionResource['code']['coding'])) {
                    $coding = $conditionResource['code']['coding'][0];
                    $data['primary_diagnosis_code'] = $coding['code'] ?? '';
                    $data['diagnosis_code'] = $data['primary_diagnosis_code'];
                    $data['diagnosis_description'] = $coding['display'] ?? '';
                    $data['icd10_code_1'] = $data['primary_diagnosis_code'];
                    $data['primary_icd10'] = $data['primary_diagnosis_code'];
                }

                // Body site information
                if (!empty($conditionResource['bodySite'])) {
                    foreach ($conditionResource['bodySite'] as $bodySite) {
                        if (!empty($bodySite['coding'][0])) {
                            $data['wound_location'] = $bodySite['coding'][0]['display'] ?? '';
                            $data['location_of_wound'] = $data['wound_location'];
                        }
                    }
                }

                // Wound care extensions
                if (!empty($conditionResource['extension'])) {
                    foreach ($conditionResource['extension'] as $extension) {
                        switch ($extension['url']) {
                            case 'http://msc-mvp.com/fhir/StructureDefinition/wound-type':
                                $data['wound_type'] = $extension['valueString'] ?? '';
                                break;
                            case 'http://msc-mvp.com/fhir/StructureDefinition/wound-stage':
                                $data['wound_stage'] = $extension['valueString'] ?? '';
                                break;
                            case 'http://msc-mvp.com/fhir/StructureDefinition/wound-duration':
                                $data['wound_duration_weeks'] = $extension['valueInteger'] ?? '';
                                break;
                        }
                    }
                }

                // Onset date
                if (!empty($conditionResource['onsetDateTime'])) {
                    $data['onset_date'] = $conditionResource['onsetDateTime'];
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract clinical data', [
                'condition_id' => $conditionId,
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Extract insurance data from FHIR Coverage resource
     */
    protected function extractInsuranceData(string $coverageId): array
    {
        $data = [];

        try {
            $coverageResource = $this->fhirService->read('Coverage', $coverageId);
            
            if ($coverageResource) {
                // Basic coverage information
                $data['insurance_name'] = $coverageResource['payor'][0]['display'] ?? '';
                $data['primary_insurance_name'] = $data['insurance_name'];
                $data['primary_name'] = $data['insurance_name'];
                
                // Subscriber information
                if (!empty($coverageResource['subscriberId'])) {
                    $data['insurance_member_id'] = $coverageResource['subscriberId'];
                    $data['primary_member_id'] = $data['insurance_member_id'];
                    $data['primary_policy'] = $data['insurance_member_id'];
                }

                // Contact information
                if (!empty($coverageResource['payor'][0]['telecom'])) {
                    foreach ($coverageResource['payor'][0]['telecom'] as $telecom) {
                        if ($telecom['system'] === 'phone') {
                            $data['primary_phone'] = $telecom['value'] ?? '';
                            break;
                        }
                    }
                }

                // Coverage period
                if (!empty($coverageResource['period'])) {
                    $data['coverage_start'] = $coverageResource['period']['start'] ?? '';
                    $data['coverage_end'] = $coverageResource['period']['end'] ?? '';
                }

                // Plan information
                if (!empty($coverageResource['class'])) {
                    foreach ($coverageResource['class'] as $class) {
                        if ($class['type']['coding'][0]['code'] === 'plan') {
                            $data['plan_name'] = $class['name'] ?? '';
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract insurance data', [
                'coverage_id' => $coverageId,
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Extract current user contact data
     */
    protected function extractUserContactData(): array
    {
        $data = [];

        try {
            $currentUser = Auth::user();
            
            if ($currentUser) {
                $data['name'] = $currentUser->full_name ?? trim($currentUser->first_name . ' ' . $currentUser->last_name);
                $data['email'] = $currentUser->email ?? '';
                $data['contact_name'] = $data['name'];
                $data['contact_email'] = $data['email'];
                $data['sales_rep'] = $data['name'];
                $data['rep_email'] = $data['email'];

                // Phone number from various sources
                $phone = '';
                if (!empty($currentUser->phone)) {
                    $phone = $currentUser->phone;
                } elseif ($currentUser->currentOrganization && !empty($currentUser->currentOrganization->phone)) {
                    $phone = $currentUser->currentOrganization->phone;
                }
                $data['phone'] = $phone;

                // Organization information
                if ($currentUser->currentOrganization) {
                    $data['territory'] = $currentUser->currentOrganization->territory ?? 
                                       $currentUser->currentOrganization->region ?? 
                                       $currentUser->currentOrganization->state ?? '';
                }

                // Always set distributor company
                $data['distributor_company'] = 'MSC Wound Care';
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract user contact data', [
                'error' => $e->getMessage()
            ]);
        }

        return $data;
    }

    /**
     * Get field aliases for manufacturer compatibility
     */
    protected function getFieldAliases(): array
    {
        return [
            // Patient name aliases
            'patient_name' => ['patient_first_name', 'patient_last_name'],
            
            // Provider aliases
            'physician_name' => ['provider_name'],
            'physician_npi' => ['provider_npi'],
            'physician_ptan' => ['provider_ptan'],
            'physician_specialty' => ['provider_specialty'],
            
            // Insurance aliases
            'insurance_name' => ['primary_insurance_name'],
            'insurance_member_id' => ['primary_member_id'],
            'primary_name' => ['primary_insurance_name'],
            'primary_policy' => ['primary_member_id'],
            
            // Diagnosis aliases
            'diagnosis_code' => ['primary_diagnosis_code'],
            'icd10_code_1' => ['primary_diagnosis_code'],
            'primary_icd10' => ['primary_diagnosis_code'],
            
            // Wound location aliases
            'location_of_wound' => ['wound_location'],
            
            // Organization aliases
            'practice_name' => ['facility_name'],
            'organization_name' => ['facility_name'],
        ];
    }

    /**
     * Apply field aliases to ensure compatibility across manufacturers
     */
    protected function applyFieldAliases(array $data): array
    {
        foreach ($this->fieldAliases as $alias => $sourceFields) {
            if (!isset($data[$alias])) {
                foreach ($sourceFields as $sourceField) {
                    if (isset($data[$sourceField]) && !empty($data[$sourceField])) {
                        $data[$alias] = $data[$sourceField];
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Apply common transformations to extracted data
     */
    protected function applyTransformations(array $data): array
    {
        // Date transformations
        $dateFields = ['patient_dob', 'procedure_date', 'date', 'expected_service_date'];
        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                try {
                    $data[$field] = Carbon::parse($data[$field])->format('m/d/Y');
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }
        }

        // Phone number transformations
        $phoneFields = ['patient_phone', 'provider_phone', 'facility_phone', 'phone', 'primary_phone'];
        foreach ($phoneFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->formatPhoneNumber($data[$field]);
            }
        }

        // Default values
        $data['distributor_company'] = 'MSC Wound Care';
        $data['procedure_date'] = $data['procedure_date'] ?? now()->format('m/d/Y');
        $data['date'] = $data['date'] ?? $data['procedure_date'];

        return $data;
    }

    /**
     * Format phone number to US format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }
        
        return $phone; // Return as-is if not 10 digits
    }
} 