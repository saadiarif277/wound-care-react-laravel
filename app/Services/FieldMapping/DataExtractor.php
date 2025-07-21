<?php

namespace App\Services\FieldMapping;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Services\FhirService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataExtractor
{
    public function __construct(
        private FhirService $fhirService
    ) {}

    /**
     * Extract all data for an episode, including FHIR data
     */
    public function extractEpisodeData(string $episodeId): array
    {
        $cacheKey = "episode_data_{$episodeId}";

        return Cache::remember($cacheKey, 300, function() use ($episodeId) {
            try {
                $episode = PatientManufacturerIVREpisode::with([
                    // NOTE: No 'patient' relationship - patient data is in FHIR only
                    'productRequests' => function($query) {
                        $query->where('status', 'approved')
                              ->orderBy('created_at', 'desc');
                    },
                    'productRequests.product',
                    'productRequests.provider', // Keep basic provider
                    'productRequests.provider.profile', // Load provider profile
                    'productRequests.provider.providerCredentials', // Load provider credentials
                    'productRequests.facility', // Keep basic facility
                    'productRequests.facility.organization', // Load organization through facility
                    'productRequests.referralSource',
                ])->findOrFail($episodeId);

                // Get the most recent product request
                $productRequest = $episode->productRequests->first();
                if (!$productRequest) {
                    throw new \Exception("No approved product requests found for episode {$episodeId}");
                }

                // Extract all data sources
                $data = [
                    'episode' => $this->extractEpisodeFields($episode),
                    'patient' => $this->extractPatientFields(null), // No local patient model
                    'product_request' => $this->extractProductRequestFields($productRequest),
                    'provider' => $this->extractProviderFields($productRequest->provider ?? null),
                    'facility' => $this->extractFacilityFields($productRequest->facility ?? null),
                    'product' => $this->extractProductFields($productRequest->product ?? null),
                    'fhir' => $this->extractFhirData($episode, $productRequest),
                    'computed' => $this->computeDerivedFields($episode, $productRequest),
                ];

                // Flatten all data into a single array
                return $this->flattenData($data);

            } catch (\Exception $e) {
                Log::error("Failed to extract episode data", [
                    'episode_id' => $episodeId,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Extract provider data without requiring an episode
     * Public method for use by QuickRequestOrchestrator
     */
    public function extractProviderData($provider): array
    {
        return $this->extractProviderFields($provider);
    }
    
    /**
     * Extract facility data without requiring an episode
     * Public method for use by QuickRequestOrchestrator
     */
    public function extractFacilityData($facility): array
    {
        return $this->extractFacilityFields($facility);
    }

    /**
     * Extract episode-specific fields
     */
    private function extractEpisodeFields($episode): array
    {
        return [
            'episode_id' => $episode->id,
            'episode_number' => $episode->id, // UUID is used as the episode number
            'status' => $episode->status,
            'created_at' => $episode->created_at,
            'manufacturer_name' => $episode->manufacturer->name ?? null,
            'ivr_status' => $episode->ivr_status,
            'patient_display_id' => $episode->patient_display_id,
        ];
    }

    /**
     * Extract patient fields
     */
    private function extractPatientFields($patient): array
    {
        // Since we don't have a local patients table, return empty array
        // Patient data comes from FHIR only
        return [];
    }

    /**
     * Extract product request fields
     */
    private function extractProductRequestFields($productRequest): array
    {
        if (!$productRequest) {
            return [];
        }

        return [
            'product_request_id' => $productRequest->id,
            'wound_type' => $productRequest->wound_type,
            'wound_location' => $productRequest->wound_location,
            'wound_size_length' => $productRequest->wound_length,
            'wound_size_width' => $productRequest->wound_width,
            'wound_size_depth' => $productRequest->wound_depth,
            'wound_start_date' => $productRequest->wound_start_date,
            'primary_diagnosis_code' => $productRequest->primary_diagnosis_code,
            'secondary_diagnosis_code' => $productRequest->secondary_diagnosis_code,
            'diagnosis_code' => $productRequest->diagnosis_code,
            'expected_service_date' => $productRequest->expected_service_date,
            'primary_insurance_name' => $productRequest->primary_insurance_name,
            'primary_member_id' => $productRequest->primary_member_id,
            'primary_plan_type' => $productRequest->primary_plan_type,
            'secondary_insurance_name' => $productRequest->secondary_insurance_name,
            'secondary_member_id' => $productRequest->secondary_member_id,
            'prior_applications' => $productRequest->prior_applications,
            'prior_application_product' => $productRequest->prior_application_product,
            'prior_application_within_12_months' => $productRequest->prior_application_within_12_months,
            'hospice_status' => $productRequest->hospice_status,
            'hospice_family_consent' => $productRequest->hospice_family_consent,
            'hospice_clinically_necessary' => $productRequest->hospice_clinically_necessary,
            'manufacturer_fields' => $productRequest->manufacturer_fields,
            'selected_products' => $productRequest->selected_products,
        ];
    }

    /**
     * Extract provider fields - enhanced with comprehensive profile data
     */
    private function extractProviderFields($provider): array
    {
        if (!$provider) {
            return [];
        }

        $fields = [
            // Basic provider info from User model
            'provider_id' => $provider->id,
            'provider_name' => trim($provider->first_name . ' ' . $provider->last_name),
            'provider_first_name' => $provider->first_name,
            'provider_last_name' => $provider->last_name,
            'provider_email' => $provider->email,
            'provider_phone' => $provider->phone, // From User model
            'fhir_practitioner_id' => $provider->fhir_practitioner_id,
        ];

        // Add provider profile data (collected during onboarding)
        if ($provider->profile) {
            $profile = $provider->profile;
            $fields = array_merge($fields, [
                'provider_npi' => $profile->npi,
                'provider_tax_id' => $profile->tax_id,
                'provider_tin' => $profile->tax_id, // Alias for tax_id
                'provider_ptan' => $profile->ptan,
                'provider_specialty' => $profile->specialty,
                'provider_phone' => $profile->phone ?: $provider->phone, // Profile phone overrides user phone
                'provider_fax' => $profile->fax,
                'provider_medicaid_number' => $profile->medicaid_number,
                'provider_medicaid' => $profile->medicaid_number, // Alias
                'provider_verification_status' => $profile->verification_status,
                'provider_specializations' => $profile->specializations,
                'provider_languages_spoken' => $profile->languages_spoken,
                'provider_professional_bio' => $profile->professional_bio,
            ]);
        }

        // Add provider credentials (licenses, certifications, etc.)
        if ($provider->providerCredentials && $provider->providerCredentials->count() > 0) {
            foreach ($provider->providerCredentials as $credential) {
                // Map different credential types to specific fields
                switch ($credential->credential_type) {
                    case 'npi_number':
                        $fields['provider_npi'] = $credential->credential_number;
                        break;
                    case 'medical_license':
                        $fields['provider_license_number'] = $credential->credential_number;
                        $fields['provider_license_state'] = $credential->issuing_state;
                        $fields['provider_license_expiration'] = $credential->expiration_date;
                        break;
                    case 'dea_registration':
                        $fields['provider_dea_number'] = $credential->credential_number;
                        $fields['provider_dea_expiration'] = $credential->expiration_date;
                        break;
                    case 'state_license':
                        $fields['provider_state_license'] = $credential->credential_number;
                        $fields['provider_state_license_state'] = $credential->issuing_state;
                        break;
                    default:
                        // Store other credentials in a generic format
                        $fields["provider_{$credential->credential_type}"] = $credential->credential_number;
                        break;
                }
            }
        }

        return $fields;
    }

    /**
     * Extract facility fields - enhanced with comprehensive facility and organization data
     */
    private function extractFacilityFields($facility): array
    {
        if (!$facility) {
            return [];
        }

        $fields = [
            // Basic facility info
            'facility_id' => $facility->id,
            'facility_name' => $facility->name,
            'facility_type' => $facility->facility_type,
            'facility_status' => $facility->status,
            'facility_active' => $facility->active,
            
            // Address information - enhanced with line1/line2 support
            'facility_address' => $facility->address,
            'facility_address_line1' => $facility->address_line1 ?: $facility->address,
            'facility_address_line2' => $facility->address_line2,
            'facility_city' => $facility->city,
            'facility_state' => $facility->state,
            'facility_zip' => $facility->zip_code,
            'facility_zip_code' => $facility->zip_code, // Alias
            
            // Contact information - comprehensive
            'facility_phone' => $facility->phone,
            'facility_fax' => $facility->fax,
            'facility_email' => $facility->email,
            
            // Contact person details (from onboarding)
            'facility_contact_name' => $facility->contact_name,
            'facility_contact_phone' => $facility->contact_phone,
            'facility_contact_email' => $facility->contact_email,
            'facility_contact_fax' => $facility->contact_fax,
            
            // Practice/Business identifiers (from onboarding)
            'facility_npi' => $facility->npi,
            'facility_group_npi' => $facility->group_npi,
            'facility_tax_id' => $facility->tax_id,
            'facility_tin' => $facility->tax_id, // Alias for tax_id
            'facility_ptan' => $facility->ptan,
            'facility_medicaid_number' => $facility->medicaid_number,
            'facility_medicaid' => $facility->medicaid_number, // Alias
            'facility_medicare_admin_contractor' => $facility->medicare_admin_contractor,
            'medicare_admin_contractor' => $facility->medicare_admin_contractor, // Alias
            'mac' => $facility->medicare_admin_contractor, // Short alias
            
            // Place of service
            'facility_default_place_of_service' => $facility->default_place_of_service,
            'place_of_service' => $facility->default_place_of_service, // Alias
            
            // Business operations
            'facility_business_hours' => $facility->business_hours,
            'facility_npi_verified_at' => $facility->npi_verified_at,
            
            // FHIR integration
            'fhir_organization_id' => $facility->fhir_organization_id,
        ];

        // Add organization data (billing, AP contacts, etc.)
        if ($facility->organization) {
            $org = $facility->organization;
            $fields = array_merge($fields, [
                // Organization basic info
                'organization_id' => $org->id,
                'organization_name' => $org->name,
                'organization_type' => $org->type,
                'organization_status' => $org->status,
                'organization_tax_id' => $org->tax_id,
                'organization_email' => $org->email,
                'organization_phone' => $org->phone,
                
                // Organization address
                'organization_address' => $org->address,
                'organization_city' => $org->city,
                'organization_state' => $org->region, // region is used as state
                'organization_region' => $org->region,
                'organization_country' => $org->country,
                'organization_postal_code' => $org->postal_code,
                'organization_zip' => $org->postal_code, // Alias
                
                // Billing information (from onboarding)
                'billing_address' => $org->billing_address,
                'billing_city' => $org->billing_city,
                'billing_state' => $org->billing_state,
                'billing_zip' => $org->billing_zip,
                
                // Accounts Payable contact information (from onboarding)
                'ap_contact_name' => $org->ap_contact_name,
                'ap_contact_phone' => $org->ap_contact_phone,
                'ap_contact_email' => $org->ap_contact_email,
                'accounts_payable_contact' => $org->ap_contact_name, // Alias
                'accounts_payable_phone' => $org->ap_contact_phone, // Alias
                'accounts_payable_email' => $org->ap_contact_email, // Alias
                
                // Sales rep relationship
                'organization_sales_rep_id' => $org->sales_rep_id,
                'primary_sales_rep_id' => $org->sales_rep_id, // Alias
                
                // FHIR integration
                'organization_fhir_id' => $org->fhir_id,
            ]);
        }

        return $fields;
    }

    /**
     * Extract product fields
     */
    private function extractProductFields($product): array
    {
        if (!$product) {
            return [];
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'product_manufacturer' => $product->manufacturer,
            'product_manufacturer_id' => $product->manufacturer_id,
            'product_category' => $product->category,
        ];
    }

    /**
     * Extract FHIR data
     */
    private function extractFhirData($episode, $productRequest): array
    {
        $fhirData = [];

        // Extract Patient FHIR data
        if ($episode->patient_fhir_id) {
            try {
                $patient = $this->fhirService->getPatient($episode->patient_fhir_id);
                $fhirData['patient'] = $this->parseFhirPatient($patient);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR patient", [
                    'patient_id' => $episode->patient_fhir_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Extract Coverage FHIR data
        if ($episode->patient_fhir_id) {
            try {
                $coverages = $this->fhirService->searchCoverage([
                    'patient' => $episode->patient_fhir_id,
                    'status' => 'active'
                ]);

                if (!empty($coverages['entry'])) {
                    $fhirData['coverage'] = $this->parseFhirCoverage($coverages['entry'][0]['resource']);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR coverage", [
                    'patient_id' => $episode->patient_fhir_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Extract Practitioner FHIR data
        if ($productRequest->provider && $productRequest->provider->fhir_practitioner_id) {
            try {
                $practitioner = $this->fhirService->getPractitioner($productRequest->provider->fhir_practitioner_id);
                $fhirData['practitioner'] = $this->parseFhirPractitioner($practitioner);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR practitioner", [
                    'practitioner_id' => $productRequest->provider->fhir_practitioner_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Extract Organization FHIR data
        if ($productRequest->facility && $productRequest->facility->fhir_organization_id) {
            try {
                $organization = $this->fhirService->getOrganization($productRequest->facility->fhir_organization_id);
                $fhirData['organization'] = $this->parseFhirOrganization($organization);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR organization", [
                    'organization_id' => $productRequest->facility->fhir_organization_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $fhirData;
    }

    /**
     * Compute derived fields
     */
    private function computeDerivedFields($episode, $productRequest): array
    {
        $computed = [];

        // Calculate wound size
        if ($productRequest->wound_length && $productRequest->wound_width) {
            $computed['wound_size_total'] =
                (float)$productRequest->wound_length * (float)$productRequest->wound_width;
        }

        // Calculate wound duration
        if ($productRequest->wound_start_date) {
            $start = new \DateTime($productRequest->wound_start_date);
            $now = new \DateTime();
            $diff = $start->diff($now);

            $computed['wound_duration_days'] = $diff->days;
            $computed['wound_duration_weeks'] = floor($diff->days / 7);
            $computed['wound_duration_months'] = $diff->m + ($diff->y * 12);
            $computed['wound_duration_years'] = $diff->y;
        }

        // Full names
        // NOTE: Patient names come from FHIR data, not local model
        // The parseFhirPatient method extracts first_name and last_name into fhir_patient_ fields

        if ($productRequest->provider) {
            $computed['provider_full_name'] = trim(
                ($productRequest->provider->first_name ?? '') . ' ' .
                ($productRequest->provider->last_name ?? '')
            );
        }

        // Full address
        // NOTE: Patient address also comes from FHIR data only

        return $computed;
    }

    /**
     * Parse FHIR Patient resource
     */
    private function parseFhirPatient($patient): array
    {
        $data = [
            'id' => $patient['id'] ?? null,
            'identifier' => $patient['identifier'][0]['value'] ?? null,
        ];

        // Parse name
        if (!empty($patient['name'][0])) {
            $name = $patient['name'][0];
            $data['first_name'] = $name['given'][0] ?? null;
            $data['last_name'] = $name['family'] ?? null;
            $data['full_name'] = $name['text'] ?? null;
        }

        // Parse demographics
        $data['birth_date'] = $patient['birthDate'] ?? null;
        $data['gender'] = $patient['gender'] ?? null;

        // Parse contact info
        if (!empty($patient['telecom'])) {
            foreach ($patient['telecom'] as $telecom) {
                if ($telecom['system'] === 'phone') {
                    $data['phone'] = $telecom['value'] ?? null;
                } elseif ($telecom['system'] === 'email') {
                    $data['email'] = $telecom['value'] ?? null;
                }
            }
        }

        // Parse address
        if (!empty($patient['address'][0])) {
            $address = $patient['address'][0];
            $data['address'] = [
                'line1' => $address['line'][0] ?? '',
                'line2' => $address['line'][1] ?? '',
                'city' => $address['city'] ?? '',
                'state' => $address['state'] ?? '',
                'postal_code' => $address['postalCode'] ?? '',
                'country' => $address['country'] ?? 'US',
            ];
        }

        return $data;
    }

    /**
     * Parse FHIR Coverage resource
     */
    private function parseFhirCoverage($coverage): array
    {
        $data = [
            'id' => $coverage['id'] ?? null,
            'status' => $coverage['status'] ?? null,
        ];

        // Parse subscriber info
        if (!empty($coverage['subscriberId'])) {
            $data['subscriber_id'] = $coverage['subscriberId'];
        }

        // Parse payor info
        if (!empty($coverage['payor'][0]['display'])) {
            $data['payor_name'] = $coverage['payor'][0]['display'];
        }

        // Parse plan info
        if (!empty($coverage['type']['coding'][0])) {
            $data['plan_type'] = $coverage['type']['coding'][0]['display'] ??
                               $coverage['type']['coding'][0]['code'] ?? null;
        }

        // Parse period
        if (!empty($coverage['period'])) {
            $data['start_date'] = $coverage['period']['start'] ?? null;
            $data['end_date'] = $coverage['period']['end'] ?? null;
        }

        return $data;
    }

    /**
     * Parse FHIR Practitioner resource
     */
    private function parseFhirPractitioner($practitioner): array
    {
        $data = [
            'id' => $practitioner['id'] ?? null,
        ];

        // Parse identifier (NPI)
        if (!empty($practitioner['identifier'])) {
            foreach ($practitioner['identifier'] as $identifier) {
                if ($identifier['system'] === 'http://hl7.org/fhir/sid/us-npi') {
                    $data['npi'] = $identifier['value'] ?? null;
                    break;
                }
            }
        }

        // Parse name
        if (!empty($practitioner['name'][0])) {
            $name = $practitioner['name'][0];
            $data['first_name'] = $name['given'][0] ?? null;
            $data['last_name'] = $name['family'] ?? null;
            $data['full_name'] = $name['text'] ?? null;
        }

        // Parse qualifications
        if (!empty($practitioner['qualification'])) {
            $qualifications = [];
            foreach ($practitioner['qualification'] as $qual) {
                if (!empty($qual['code']['text'])) {
                    $qualifications[] = $qual['code']['text'];
                }
            }
            $data['credentials'] = implode(', ', $qualifications);
        }

        return $data;
    }

    /**
     * Parse FHIR Organization resource
     */
    private function parseFhirOrganization($organization): array
    {
        $data = [
            'id' => $organization['id'] ?? null,
            'name' => $organization['name'] ?? null,
        ];

        // Parse identifiers
        if (!empty($organization['identifier'])) {
            foreach ($organization['identifier'] as $identifier) {
                if ($identifier['system'] === 'http://hl7.org/fhir/sid/us-npi') {
                    $data['npi'] = $identifier['value'] ?? null;
                }
            }
        }

        // Parse address
        if (!empty($organization['address'][0])) {
            $address = $organization['address'][0];
            $data['address'] = [
                'line1' => $address['line'][0] ?? '',
                'line2' => $address['line'][1] ?? '',
                'city' => $address['city'] ?? '',
                'state' => $address['state'] ?? '',
                'postal_code' => $address['postalCode'] ?? '',
            ];
        }

        // Parse contact info
        if (!empty($organization['telecom'])) {
            foreach ($organization['telecom'] as $telecom) {
                if ($telecom['system'] === 'phone') {
                    $data['phone'] = $telecom['value'] ?? null;
                } elseif ($telecom['system'] === 'fax') {
                    $data['fax'] = $telecom['value'] ?? null;
                }
            }
        }

        return $data;
    }

    /**
     * Flatten nested data structure into single-level array
     */
    private function flattenData(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}_{$key}" : $key;

            if (is_array($value) && !isset($value[0])) {
                // Recursively flatten nested arrays
                $result = array_merge($result, $this->flattenData($value, $newKey));
            } else {
                // Add scalar values and arrays with numeric keys
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Clear cache for an episode
     */
    public function clearCache(int $episodeId): void
    {
        Cache::forget("episode_data_{$episodeId}");
    }
}
