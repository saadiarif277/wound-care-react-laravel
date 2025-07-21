<?php

namespace App\Services;

use App\Services\FhirService;
use App\Services\FhirToIvrFieldExtractor;
use App\Services\DocusealService;
use App\Services\FuzzyMapping\IVRMappingOrchestrator;
use App\Models\Episode;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FhirDocusealIntegrationServiceRV
{
    public function __construct(
        private FhirService $fhirService,
        private FhirToIvrFieldExtractor $fhirExtractor,
        private DocusealService $DocusealService,
        private ?IVRMappingOrchestrator $fuzzyMapper = null
    ) {
        try {
            $this->fuzzyMapper = app(IVRMappingOrchestrator::class);
        } catch (\Exception $e) {
            Log::warning('Fuzzy mapping service not available in FhirDocuSealIntegrationService');
        }
    }

    /**
     * Create Docuseal submission with FHIR data for provider wound care orders
     */
    public function createProviderOrderSubmission(Episode $episode, array $additionalData = []): array
    {
        try {
            Log::info('Creating Docuseal submission with FHIR data', [
                'episode_id' => $episode->id,
                'manufacturer_id' => $episode->manufacturer_id,
                'has_fhir_ids' => !empty($episode->metadata['fhir_ids'] ?? [])
            ]);

            // Extract comprehensive FHIR data
            $fhirData = $this->extractFhirDataForEpisode($episode);

            // Get manufacturer's Docuseal template
            $template = $this->getManufacturerTemplate($episode->manufacturer_id);
            if (!$template) {
                throw new \Exception("No Docuseal template found for manufacturer ID: {$episode->manufacturer_id}");
            }

            // Map FHIR data to Docuseal fields
            $mappedFields = $this->mapFhirToDocuSealFields($fhirData, $template);

            // Merge with any additional data provided
            $allMappedFields = array_merge($mappedFields, $this->formatAdditionalFields($additionalData));

            // Get provider email for submission
            $providerEmail = $this->getProviderEmail($episode, $fhirData);

            // Create Docuseal submission
            $submissionData = [
                'template_id' => (int) $template->docuseal_template_id,
                'send_email' => false, // Embedding in workflow
                'submitters' => [
                    [
                        'email' => $providerEmail,
                        'role' => $this->getTemplateRole($template),
                        'fields' => $allMappedFields
                    ]
                ]
            ];

            Log::info('Creating Docuseal submission', [
                'template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'fields_count' => count($allMappedFields),
                'provider_email' => $providerEmail,
                'manufacturer' => $template->manufacturer->name ?? 'unknown'
            ]);

            // Use existing Docuseal service to create submission
            $response = $this->DocusealService->createSubmission($submissionData);

            if (!$response || !isset($response['submitters'][0]['slug'])) {
                throw new \Exception('Failed to create Docuseal submission or no slug returned');
            }

            $slug = $response['submitters'][0]['slug'];

            Log::info('Docuseal submission created successfully', [
                'episode_id' => $episode->id,
                'submission_id' => $response['id'] ?? null,
                'slug' => $slug,
                'fhir_fields_used' => count($fhirData),
                'total_fields_mapped' => count($allMappedFields)
            ]);

            return [
                'success' => true,
                'submission_id' => $response['id'] ?? null,
                'slug' => $slug,
                'embed_url' => "https://docuseal.com/s/{$slug}",
                'template_id' => $template->docuseal_template_id,
                'fields_mapped' => count($allMappedFields),
                'fhir_data_used' => count($fhirData)
            ];

        } catch (\Exception $e) {
            Log::error('FHIR-Docuseal integration failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract comprehensive FHIR data for an episode
     */
    private function extractFhirDataForEpisode(Episode $episode): array
    {
        $fhirContext = [
            'patient_id' => $episode->patient_fhir_id,
            'practitioner_id' => $episode->practitioner_fhir_id ?? $episode->metadata['fhir_ids']['practitioner_id'] ?? null,
            'organization_id' => $episode->organization_fhir_id ?? $episode->metadata['fhir_ids']['organization_id'] ?? null,
            'episode_of_care_id' => $episode->episode_of_care_fhir_id ?? $episode->metadata['fhir_ids']['episode_of_care_id'] ?? null,
        ];

        // Add additional FHIR IDs from metadata
        $metadata = $episode->metadata ?? [];
        if (isset($metadata['fhir_ids'])) {
            $fhirContext = array_merge($fhirContext, [
                'condition_id' => $metadata['fhir_ids']['condition_id'] ?? null,
                'coverage_id' => $metadata['fhir_ids']['coverage_id'] ?? null,
                'encounter_id' => $metadata['fhir_ids']['encounter_id'] ?? null,
                'questionnaire_response_id' => $metadata['fhir_ids']['questionnaire_response_id'] ?? null,
                'device_request_id' => $metadata['fhir_ids']['device_request_id'] ?? null,
            ]);
        }

        // Filter out null values
        $fhirContext = array_filter($fhirContext);

        Log::info('FHIR context prepared', [
            'episode_id' => $episode->id,
            'fhir_resources' => array_keys($fhirContext),
            'has_patient' => !empty($fhirContext['patient_id']),
            'has_provider' => !empty($fhirContext['practitioner_id']),
            'has_organization' => !empty($fhirContext['organization_id'])
        ]);

        // Get manufacturer name for field mapping
        $manufacturer = Manufacturer::find($episode->manufacturer_id);
        $manufacturerKey = $manufacturer ? $manufacturer->name : 'default';

        try {
            // Use existing FHIR extractor service
            $fhirData = $this->fhirExtractor->extractForManufacturer($fhirContext, $manufacturerKey);

            Log::info('FHIR data extracted', [
                'episode_id' => $episode->id,
                'manufacturer' => $manufacturerKey,
                'extracted_fields' => array_keys($fhirData),
                'field_count' => count($fhirData)
            ]);

            return $fhirData;

        } catch (\Exception $e) {
            Log::warning('FHIR extraction failed, continuing with empty data', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Map FHIR data to Docuseal fields using comprehensive field variations
     */
    private function mapFhirToDocusealFields(array $fhirData, $template): array
    {
        // Try fuzzy mapping first if available
        if ($this->fuzzyMapper && isset($template->manufacturer_id)) {
            try {
                // Extract additional context data
                $additionalData = [
                    'template_id' => $template->id,
                    'user_email' => Auth::user()->email ?? '',
                    'user_name' => Auth::user()->name ?? '',
                    'submission_date' => now()->format('Y-m-d'),
                ];

                // Use fuzzy mapping
                $result = $this->fuzzyMapper->mapDataForIVR(
                    $fhirData,
                    $additionalData,
                    $template->manufacturer_id,
                    'insurance-verification'
                );

                if ($result['success']) {
                    Log::info('Using fuzzy mapping for Docuseal fields', [
                        'template_id' => $template->id,
                        'mapped_fields' => count($result['mapped_fields']),
                        'statistics' => $result['statistics']
                    ]);

                    return $result['mapped_fields'];
                }
            } catch (\Exception $e) {
                Log::warning('Fuzzy mapping failed, falling back to standard mapping', [
                    'error' => $e->getMessage(),
                    'template_id' => $template->id
                ]);
            }
        }

        // Fall back to standard mapping
        $mappings = [];

        // Patient Demographics - Multiple field name variations
        $this->mapPatientFields($fhirData, $mappings);

        // Provider Information
        $this->mapProviderFields($fhirData, $mappings);

        // Facility/Organization Information
        $this->mapFacilityFields($fhirData, $mappings);

        // Insurance Information
        $this->mapInsuranceFields($fhirData, $mappings);

        // Clinical Information
        $this->mapClinicalFields($fhirData, $mappings);

        // Order Information
        $this->mapOrderFields($fhirData, $mappings);

        Log::info('FHIR to Docuseal mapping completed (standard)', [
            'template_id' => $template->id,
            'input_fields' => count($fhirData),
            'mapped_fields' => count($mappings),
            'sample_mappings' => array_slice($mappings, 0, 10, true)
        ]);

        return $mappings;
    }

    /**
     * Map patient fields with multiple variations
     */
    private function mapPatientFields(array $fhirData, array &$mappings): void
    {
        if (isset($fhirData['patient_name'])) {
            $mappings['PATIENT NAME'] = $fhirData['patient_name'];
            $mappings['Patient Name'] = $fhirData['patient_name'];
            $mappings['PATIENT_NAME'] = $fhirData['patient_name'];
            $mappings['PatientName'] = $fhirData['patient_name'];
            $mappings['patient_name'] = $fhirData['patient_name'];
        }

        if (isset($fhirData['patient_dob'])) {
            $formattedDob = $this->formatDate($fhirData['patient_dob']);
            $mappings['DATE OF BIRTH'] = $formattedDob;
            $mappings['DOB'] = $formattedDob;
            $mappings['Patient DOB'] = $formattedDob;
            $mappings['PATIENT_DOB'] = $formattedDob;
            $mappings['patient_dob'] = $formattedDob;
            $mappings['Date of Birth'] = $formattedDob;
        }

        if (isset($fhirData['patient_gender'])) {
            $mappings['GENDER'] = $fhirData['patient_gender'];
            $mappings['Patient Gender'] = $fhirData['patient_gender'];
            $mappings['PATIENT_GENDER'] = $fhirData['patient_gender'];
            $mappings['gender'] = $fhirData['patient_gender'];
        }

        if (isset($fhirData['patient_phone'])) {
            $formattedPhone = $this->formatPhoneNumber($fhirData['patient_phone']);
            $mappings['PATIENT PHONE'] = $formattedPhone;
            $mappings['Patient Phone'] = $formattedPhone;
            $mappings['PHONE'] = $formattedPhone;
            $mappings['patient_phone'] = $formattedPhone;
            $mappings['Phone Number'] = $formattedPhone;
        }

        // Patient Address
        $this->mapPatientAddress($fhirData, $mappings);
    }

    /**
     * Map provider fields
     */
    private function mapProviderFields(array $fhirData, array &$mappings): void
    {
        // First try to load from authenticated user if available
        if (Auth::check()) {
            $user = Auth::user();
            $user->load(['providerProfile', 'providerCredentials']);

            // Provider name
            $providerName = $user->first_name . ' ' . $user->last_name;
            $mappings['PROVIDER NAME'] = $providerName;
            $mappings['Provider Name'] = $providerName;
            $mappings['PHYSICIAN NAME'] = $providerName;
            $mappings['Physician Name'] = $providerName;
            $mappings['DOCTOR NAME'] = $providerName;
            $mappings['Doctor Name'] = $providerName;
            $mappings['provider_name'] = $providerName;

            // Provider NPI
            $npi = $user->npi_number;
            if (!$npi) {
                $npiCredential = $user->providerCredentials->where('credential_type', 'npi_number')->first();
                if ($npiCredential) {
                    $npi = $npiCredential->credential_number;
                }
            }
            if ($npi) {
                $mappings['PROVIDER NPI'] = $npi;
                $mappings['Provider NPI'] = $npi;
                $mappings['NPI'] = $npi;
                $mappings['PHYSICIAN NPI'] = $npi;
                $mappings['provider_npi'] = $npi;
            }

            // Provider phone
            if ($user->phone) {
                $formattedPhone = $this->formatPhoneNumber($user->phone);
                $mappings['PROVIDER PHONE'] = $formattedPhone;
                $mappings['Provider Phone'] = $formattedPhone;
                $mappings['PHYSICIAN PHONE'] = $formattedPhone;
                $mappings['provider_phone'] = $formattedPhone;
            }

            // Provider specialty from profile
            if ($user->providerProfile && $user->providerProfile->primary_specialty) {
                $mappings['SPECIALTY'] = $user->providerProfile->primary_specialty;
                $mappings['Provider Specialty'] = $user->providerProfile->primary_specialty;
                $mappings['PHYSICIAN SPECIALTY'] = $user->providerProfile->primary_specialty;
                $mappings['provider_specialty'] = $user->providerProfile->primary_specialty;
            }

            // Additional provider details
            if ($user->providerProfile) {
                if ($user->providerProfile->credentials) {
                    $mappings['PROVIDER CREDENTIALS'] = $user->providerProfile->credentials;
                    $mappings['Provider Credentials'] = $user->providerProfile->credentials;
                }
                if ($user->providerProfile->dea_number) {
                    $mappings['DEA NUMBER'] = $user->providerProfile->dea_number;
                    $mappings['Provider DEA'] = $user->providerProfile->dea_number;
                }
            }
        }

        // Override with FHIR data if available
        if (isset($fhirData['provider_name'])) {
            $mappings['PROVIDER NAME'] = $fhirData['provider_name'];
            $mappings['Provider Name'] = $fhirData['provider_name'];
            $mappings['PHYSICIAN NAME'] = $fhirData['provider_name'];
            $mappings['Physician Name'] = $fhirData['provider_name'];
            $mappings['DOCTOR NAME'] = $fhirData['provider_name'];
            $mappings['Doctor Name'] = $fhirData['provider_name'];
            $mappings['provider_name'] = $fhirData['provider_name'];
        }

        if (isset($fhirData['provider_npi'])) {
            $mappings['PROVIDER NPI'] = $fhirData['provider_npi'];
            $mappings['Provider NPI'] = $fhirData['provider_npi'];
            $mappings['NPI'] = $fhirData['provider_npi'];
            $mappings['PHYSICIAN NPI'] = $fhirData['provider_npi'];
            $mappings['provider_npi'] = $fhirData['provider_npi'];
        }

        if (isset($fhirData['provider_phone'])) {
            $formattedPhone = $this->formatPhoneNumber($fhirData['provider_phone']);
            $mappings['PROVIDER PHONE'] = $formattedPhone;
            $mappings['Provider Phone'] = $formattedPhone;
            $mappings['PHYSICIAN PHONE'] = $formattedPhone;
            $mappings['provider_phone'] = $formattedPhone;
        }

        if (isset($fhirData['provider_specialty'])) {
            $mappings['SPECIALTY'] = $fhirData['provider_specialty'];
            $mappings['Provider Specialty'] = $fhirData['provider_specialty'];
            $mappings['PHYSICIAN SPECIALTY'] = $fhirData['provider_specialty'];
            $mappings['provider_specialty'] = $fhirData['provider_specialty'];
        }
    }

    /**
     * Map facility fields
     */
    private function mapFacilityFields(array $fhirData, array &$mappings): void
    {
        // First try to load from authenticated user's facilities if available
        if (Auth::check()) {
            $user = Auth::user();
            $user->load(['facilities', 'currentOrganization', 'organizations']);

            // Get primary facility or first available facility
            $facility = $user->facilities()->wherePivot('is_primary', true)->first();
            if (!$facility && $user->facilities->count() > 0) {
                $facility = $user->facilities->first();
            }

            if ($facility) {
                $mappings['FACILITY NAME'] = $facility->name;
                $mappings['Facility Name'] = $facility->name;
                $mappings['CLINIC NAME'] = $facility->name;
                $mappings['Clinic Name'] = $facility->name;
                $mappings['facility_name'] = $facility->name;

                // Facility address
                $facilityAddress = $facility->full_address ?? $facility->address_line1;
                if ($facilityAddress) {
                    $mappings['FACILITY ADDRESS'] = $facilityAddress;
                    $mappings['Facility Address'] = $facilityAddress;
                    $mappings['CLINIC ADDRESS'] = $facilityAddress;
                    $mappings['facility_address'] = $facilityAddress;
                }

                // Facility NPI
                if ($facility->npi) {
                    $mappings['FACILITY NPI'] = $facility->npi;
                    $mappings['Facility NPI'] = $facility->npi;
                    $mappings['CLINIC NPI'] = $facility->npi;
                    $mappings['facility_npi'] = $facility->npi;
                }

                // Additional facility details
                if ($facility->phone) {
                    $mappings['FACILITY PHONE'] = $this->formatPhoneNumber($facility->phone);
                    $mappings['Facility Phone'] = $this->formatPhoneNumber($facility->phone);
                }

                if ($facility->fax) {
                    $mappings['FACILITY FAX'] = $this->formatPhoneNumber($facility->fax);
                    $mappings['Facility Fax'] = $this->formatPhoneNumber($facility->fax);
                }
            }

            // Organization information
            $organization = $user->currentOrganization ?? $user->primaryOrganization();
            if (!$organization && $user->organizations->count() > 0) {
                $organization = $user->organizations->first();
            }

            if ($organization) {
                $mappings['ORGANIZATION NAME'] = $organization->name;
                $mappings['Organization Name'] = $organization->name;
                $mappings['PRACTICE NAME'] = $organization->name;
                $mappings['Practice Name'] = $organization->name;

                if ($organization->phone) {
                    $mappings['ORGANIZATION PHONE'] = $this->formatPhoneNumber($organization->phone);
                    $mappings['Organization Phone'] = $this->formatPhoneNumber($organization->phone);
                }
            }
        }

        // Override with FHIR data if available
        if (isset($fhirData['facility_name'])) {
            $mappings['FACILITY NAME'] = $fhirData['facility_name'];
            $mappings['Facility Name'] = $fhirData['facility_name'];
            $mappings['CLINIC NAME'] = $fhirData['facility_name'];
            $mappings['Clinic Name'] = $fhirData['facility_name'];
            $mappings['facility_name'] = $fhirData['facility_name'];
        }

        if (isset($fhirData['facility_address'])) {
            $mappings['FACILITY ADDRESS'] = $fhirData['facility_address'];
            $mappings['Facility Address'] = $fhirData['facility_address'];
            $mappings['CLINIC ADDRESS'] = $fhirData['facility_address'];
            $mappings['facility_address'] = $fhirData['facility_address'];
        }

        if (isset($fhirData['facility_npi'])) {
            $mappings['FACILITY NPI'] = $fhirData['facility_npi'];
            $mappings['Facility NPI'] = $fhirData['facility_npi'];
            $mappings['CLINIC NPI'] = $fhirData['facility_npi'];
            $mappings['facility_npi'] = $fhirData['facility_npi'];
        }
    }

    /**
     * Map insurance fields
     */
    private function mapInsuranceFields(array $fhirData, array &$mappings): void
    {
        if (isset($fhirData['primary_insurance_name'])) {
            $mappings['PRIMARY INSURANCE'] = $fhirData['primary_insurance_name'];
            $mappings['Primary Insurance'] = $fhirData['primary_insurance_name'];
            $mappings['INSURANCE NAME'] = $fhirData['primary_insurance_name'];
            $mappings['Insurance Name'] = $fhirData['primary_insurance_name'];
            $mappings['PAYER'] = $fhirData['primary_insurance_name'];
            $mappings['Payer'] = $fhirData['primary_insurance_name'];
            $mappings['primary_insurance'] = $fhirData['primary_insurance_name'];
        }

        if (isset($fhirData['primary_policy_number'])) {
            $mappings['POLICY NUMBER'] = $fhirData['primary_policy_number'];
            $mappings['Policy Number'] = $fhirData['primary_policy_number'];
            $mappings['MEMBER ID'] = $fhirData['primary_policy_number'];
            $mappings['Member ID'] = $fhirData['primary_policy_number'];
            $mappings['primary_policy_number'] = $fhirData['primary_policy_number'];
        }

        if (isset($fhirData['primary_subscriber_name'])) {
            $mappings['SUBSCRIBER NAME'] = $fhirData['primary_subscriber_name'];
            $mappings['Subscriber Name'] = $fhirData['primary_subscriber_name'];
            $mappings['POLICYHOLDER NAME'] = $fhirData['primary_subscriber_name'];
            $mappings['subscriber_name'] = $fhirData['primary_subscriber_name'];
        }
    }

    /**
     * Map clinical fields
     */
    private function mapClinicalFields(array $fhirData, array &$mappings): void
    {
        if (isset($fhirData['primary_diagnosis_code'])) {
            $mappings['DIAGNOSIS CODE'] = $fhirData['primary_diagnosis_code'];
            $mappings['Diagnosis Code'] = $fhirData['primary_diagnosis_code'];
            $mappings['ICD CODE'] = $fhirData['primary_diagnosis_code'];
            $mappings['PRIMARY DIAGNOSIS'] = $fhirData['primary_diagnosis_code'];
            $mappings['diagnosis_code'] = $fhirData['primary_diagnosis_code'];
        }

        if (isset($fhirData['wound_type'])) {
            $mappings['WOUND TYPE'] = $fhirData['wound_type'];
            $mappings['Wound Type'] = $fhirData['wound_type'];
            $mappings['WOUND_TYPE'] = $fhirData['wound_type'];
            $mappings['wound_type'] = $fhirData['wound_type'];
        }

        if (isset($fhirData['wound_location'])) {
            $mappings['WOUND LOCATION'] = $fhirData['wound_location'];
            $mappings['Wound Location'] = $fhirData['wound_location'];
            $mappings['ANATOMICAL LOCATION'] = $fhirData['wound_location'];
            $mappings['wound_location'] = $fhirData['wound_location'];
        }

        // Wound Measurements
        if (isset($fhirData['wound_size_length'])) {
            $mappings['WOUND LENGTH'] = $fhirData['wound_size_length'];
            $mappings['Wound Length'] = $fhirData['wound_size_length'];
            $mappings['LENGTH'] = $fhirData['wound_size_length'];
            $mappings['wound_length'] = $fhirData['wound_size_length'];
        }

        if (isset($fhirData['wound_size_width'])) {
            $mappings['WOUND WIDTH'] = $fhirData['wound_size_width'];
            $mappings['Wound Width'] = $fhirData['wound_size_width'];
            $mappings['WIDTH'] = $fhirData['wound_size_width'];
            $mappings['wound_width'] = $fhirData['wound_size_width'];
        }
    }

    /**
     * Map order fields
     */
    private function mapOrderFields(array $fhirData, array &$mappings): void
    {
        if (isset($fhirData['selected_products'])) {
            $mappings['PRODUCT'] = $fhirData['selected_products'];
            $mappings['Product'] = $fhirData['selected_products'];
            $mappings['SELECTED PRODUCTS'] = $fhirData['selected_products'];
            $mappings['selected_products'] = $fhirData['selected_products'];
        }

        // Request Information
        $mappings['REQUEST DATE'] = date('m/d/Y');
        $mappings['Request Date'] = date('m/d/Y');
        $mappings['ORDER DATE'] = date('m/d/Y');
        $mappings['Order Date'] = date('m/d/Y');
        $mappings['request_date'] = date('m/d/Y');

        // Sales Rep Information (if available)
        if (isset($fhirData['sales_rep_name'])) {
            $mappings['SALES REP'] = $fhirData['sales_rep_name'];
            $mappings['Sales Rep'] = $fhirData['sales_rep_name'];
            $mappings['REPRESENTATIVE'] = $fhirData['sales_rep_name'];
            $mappings['sales_rep'] = $fhirData['sales_rep_name'];
        }
    }

    /**
     * Map patient address components
     */
    private function mapPatientAddress(array $fhirData, array &$mappings): void
    {
        $addressParts = array_filter([
            $fhirData['patient_address'] ?? null,
            $fhirData['patient_city'] ?? null,
            $fhirData['patient_state'] ?? null,
            $fhirData['patient_zip'] ?? null
        ]);

        if (!empty($addressParts)) {
            $fullAddress = implode(', ', $addressParts);
            $mappings['PATIENT ADDRESS'] = $fullAddress;
            $mappings['Patient Address'] = $fullAddress;
            $mappings['ADDRESS'] = $fullAddress;
            $mappings['patient_address'] = $fullAddress;
        }

        if (isset($fhirData['patient_city'])) {
            $mappings['CITY'] = $fhirData['patient_city'];
            $mappings['Patient City'] = $fhirData['patient_city'];
            $mappings['PATIENT_CITY'] = $fhirData['patient_city'];
        }

        if (isset($fhirData['patient_state'])) {
            $mappings['STATE'] = $fhirData['patient_state'];
            $mappings['Patient State'] = $fhirData['patient_state'];
            $mappings['PATIENT_STATE'] = $fhirData['patient_state'];
        }

        if (isset($fhirData['patient_zip'])) {
            $mappings['ZIP'] = $fhirData['patient_zip'];
            $mappings['Patient Zip'] = $fhirData['patient_zip'];
            $mappings['POSTAL CODE'] = $fhirData['patient_zip'];
            $mappings['PATIENT_ZIP'] = $fhirData['patient_zip'];
        }
    }

    /**
     * Get manufacturer's Docuseal template (prioritize IVR templates)
     */
    private function getManufacturerTemplate(int $manufacturerId): ?\App\Models\Docuseal\DocusealTemplate
    {
        // First try to find an IVR template
        $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('document_type', 'IVR')
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();

        // If no IVR template found, look for InsuranceVerification templates (legacy)
        if (!$template) {
            $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturerId)
                ->where('document_type', 'InsuranceVerification')
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->first();
        }

        // If still no template, get any active template for this manufacturer
        if (!$template) {
            $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturerId)
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->first();
        }

        return $template;
    }

    /**
     * Get provider email from episode or FHIR data
     */
    private function getProviderEmail(Episode $episode, array $fhirData): string
    {
        // Try to get from authenticated user
        if (Auth::check() && Auth::user()->email) {
            return Auth::user()->email;
        }

        // Try to get from FHIR data
        if (isset($fhirData['provider_email'])) {
            return $fhirData['provider_email'];
        }

        // Fallback to a default
        return 'limitless@mscwoundcare.com';
    }

    /**
     * Get the template role for Docuseal submission
     */
    private function getTemplateRole($template): string
    {
        // For provider orders, typically use "First Party" or "Provider"
        return 'First Party'; // This should match the role in your Docuseal templates
    }

    /**
     * Format additional fields for Docuseal
     */
    private function formatAdditionalFields(array $additionalData): array
    {
        $formatted = [];

        foreach ($additionalData as $key => $value) {
            $formatted[] = [
                'name' => $key,
                'value' => $value
            ];
        }

        return $formatted;
    }

    /**
     * Format date for Docuseal
     */
    private function formatDate(?string $date): ?string
    {
        if (!$date) return null;

        try {
            return date('m/d/Y', strtotime($date));
        } catch (\Exception $e) {
            return $date; // Return as-is if parsing fails
        }
    }

    /**
     * Format phone number for Docuseal
     */
    private function formatPhoneNumber(?string $phone): ?string
    {
        if (!$phone) return null;

        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Format as (XXX) XXX-XXXX if 10 digits
        if (strlen($cleaned) === 10) {
            return sprintf('(%s) %s-%s',
                substr($cleaned, 0, 3),
                substr($cleaned, 3, 3),
                substr($cleaned, 6, 4)
            );
        }

        // Return original if not 10 digits
        return $phone;
    }
}
