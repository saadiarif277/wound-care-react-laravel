<?php

namespace App\Services\FieldMapping;

use App\Models\Episode;
use App\Models\ProductRequest;
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
    public function extractEpisodeData(int $episodeId): array
    {
        $cacheKey = "episode_data_{$episodeId}";
        
        return Cache::remember($cacheKey, 300, function() use ($episodeId) {
            try {
                $episode = Episode::with([
                    'patient',
                    'productRequests' => function($query) {
                        $query->where('status', 'approved')
                              ->orderBy('created_at', 'desc');
                    },
                    'productRequests.product',
                    'productRequests.provider',
                    'productRequests.facility',
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
                    'patient' => $this->extractPatientFields($episode->patient),
                    'product_request' => $this->extractProductRequestFields($productRequest),
                    'provider' => $this->extractProviderFields($productRequest->provider),
                    'facility' => $this->extractFacilityFields($productRequest->facility),
                    'product' => $this->extractProductFields($productRequest->product),
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
     * Extract episode-specific fields
     */
    private function extractEpisodeFields($episode): array
    {
        return [
            'episode_id' => $episode->id,
            'episode_number' => $episode->episode_number,
            'status' => $episode->status,
            'created_at' => $episode->created_at,
            'manufacturer_name' => $episode->manufacturer_name,
        ];
    }

    /**
     * Extract patient fields
     */
    private function extractPatientFields($patient): array
    {
        if (!$patient) {
            return [];
        }

        return [
            'patient_id' => $patient->id,
            'patient_first_name' => $patient->first_name,
            'patient_last_name' => $patient->last_name,
            'patient_dob' => $patient->date_of_birth,
            'patient_gender' => $patient->gender,
            'patient_phone' => $patient->phone,
            'patient_email' => $patient->email,
            'patient_address_line1' => $patient->address_line1,
            'patient_address_line2' => $patient->address_line2,
            'patient_city' => $patient->city,
            'patient_state' => $patient->state,
            'patient_zip' => $patient->zip_code,
            'patient_member_id' => $patient->primary_member_id,
            'fhir_patient_id' => $patient->fhir_patient_id,
        ];
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
     * Extract provider fields
     */
    private function extractProviderFields($provider): array
    {
        if (!$provider) {
            return [];
        }

        return [
            'provider_id' => $provider->id,
            'provider_name' => trim($provider->first_name . ' ' . $provider->last_name),
            'provider_first_name' => $provider->first_name,
            'provider_last_name' => $provider->last_name,
            'provider_npi' => $provider->npi,
            'provider_email' => $provider->email,
            'provider_phone' => $provider->phone,
            'provider_credentials' => $provider->credentials,
            'fhir_practitioner_id' => $provider->fhir_practitioner_id,
        ];
    }

    /**
     * Extract facility fields
     */
    private function extractFacilityFields($facility): array
    {
        if (!$facility) {
            return [];
        }

        return [
            'facility_id' => $facility->id,
            'facility_name' => $facility->name,
            'facility_address' => $facility->address,
            'facility_city' => $facility->city,
            'facility_state' => $facility->state,
            'facility_zip' => $facility->zip_code,
            'facility_phone' => $facility->phone,
            'facility_fax' => $facility->fax,
            'fhir_organization_id' => $facility->fhir_organization_id,
        ];
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
        if ($episode->patient && $episode->patient->fhir_patient_id) {
            try {
                $patient = $this->fhirService->getPatient($episode->patient->fhir_patient_id);
                $fhirData['patient'] = $this->parseFhirPatient($patient);
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR patient", [
                    'patient_id' => $episode->patient->fhir_patient_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Extract Coverage FHIR data
        if ($episode->patient && $episode->patient->fhir_patient_id) {
            try {
                $coverages = $this->fhirService->searchCoverage([
                    'patient' => $episode->patient->fhir_patient_id,
                    'status' => 'active'
                ]);
                
                if (!empty($coverages['entry'])) {
                    $fhirData['coverage'] = $this->parseFhirCoverage($coverages['entry'][0]['resource']);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch FHIR coverage", [
                    'patient_id' => $episode->patient->fhir_patient_id,
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
        if ($episode->patient) {
            $computed['patient_full_name'] = trim(
                ($episode->patient->first_name ?? '') . ' ' . 
                ($episode->patient->last_name ?? '')
            );
        }

        if ($productRequest->provider) {
            $computed['provider_full_name'] = trim(
                ($productRequest->provider->first_name ?? '') . ' ' . 
                ($productRequest->provider->last_name ?? '')
            );
        }

        // Full address
        if ($episode->patient) {
            $computed['patient_full_address'] = implode(', ', array_filter([
                $episode->patient->address_line1,
                $episode->patient->address_line2,
                $episode->patient->city,
                $episode->patient->state,
                $episode->patient->zip_code
            ]));
        }

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