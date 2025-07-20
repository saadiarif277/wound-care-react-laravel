<?php

namespace App\Services;

use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\Fhir\Organization;
use App\Models\Provider\ProviderProfile;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for extracting entity data based on user role
 * This service ensures we only extract the fields that are actually needed
 */
class EntityDataService
{
    public function __construct(
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Extract data based on user role and requested fields
     * 
     * @param int $userId The current user ID
     * @param int|null $facilityId Selected facility ID (for providers)
     * @param int|null $providerId Selected provider ID (for office managers)
     * @param array $requiredFields List of fields to extract
     * @return array Extracted data containing only requested fields
     */
    public function extractDataByRole(
        int $userId,
        ?int $facilityId,
        ?int $providerId,
        array $requiredFields
    ): array {
        $extractedData = [];
        $user = User::find($userId);
        
        if (!$user) {
            $this->logger->error('User not found for data extraction', ['user_id' => $userId]);
            return [];
        }

        // Determine role and extract accordingly
        if ($user->hasRole('provider')) {
            // Provider: Use their own data + selected facility
            $extractedData = $this->extractForProvider($user, $facilityId, $requiredFields);
        } elseif ($user->hasRole('office_manager')) {
            // Office Manager: Use their facility + selected provider
            $extractedData = $this->extractForOfficeManager($user, $providerId, $requiredFields);
        }

        // Add common fields if requested
        $extractedData = $this->addCommonFields($extractedData, $requiredFields);

        $this->logger->info('Role-based data extraction completed', [
            'user_id' => $userId,
            'role' => $user->roles->pluck('name')->first(),
            'requested_fields' => count($requiredFields),
            'extracted_fields' => count($extractedData)
        ]);

        return $extractedData;
    }

    /**
     * Extract data for provider users
     */
    protected function extractForProvider(User $user, ?int $facilityId, array $requiredFields): array
    {
        $data = [];

        // Extract provider fields
        $providerFields = $this->getFieldsByPrefix($requiredFields, 'physician_');
        if (!empty($providerFields)) {
            $providerData = $this->extractProviderData($user->id, $providerFields);
            $data = array_merge($data, $providerData);
        }

        // Extract facility fields if facility is selected
        if ($facilityId) {
            $facilityFields = $this->getFieldsByPrefix($requiredFields, 'facility_');
            if (!empty($facilityFields)) {
                $facilityData = $this->extractFacilityData($facilityId, $facilityFields);
                $data = array_merge($data, $facilityData);
            }
        }

        return $data;
    }

    /**
     * Extract data for office manager users
     */
    protected function extractForOfficeManager(User $user, ?int $providerId, array $requiredFields): array
    {
        $data = [];

        // Get office manager's facility
        $facility = $user->facilities()->first();
        if ($facility) {
            $facilityFields = $this->getFieldsByPrefix($requiredFields, 'facility_');
            if (!empty($facilityFields)) {
                $facilityData = $this->extractFacilityData($facility->id, $facilityFields);
                $data = array_merge($data, $facilityData);
            }
        }

        // Extract provider fields if provider is selected
        if ($providerId) {
            $providerFields = $this->getFieldsByPrefix($requiredFields, 'physician_');
            if (!empty($providerFields)) {
                $providerData = $this->extractProviderData($providerId, $providerFields);
                $data = array_merge($data, $providerData);
            }
        }

        return $data;
    }

    /**
     * Extract only requested provider fields
     */
    public function extractProviderData(int $providerId, array $fields): array
    {
        // Load provider with profile and credentials relationships
        $provider = User::with(['profile', 'providerCredentials'])->find($providerId);
        if (!$provider) {
            $this->logger->warning('Provider not found for data extraction', ['provider_id' => $providerId]);
            return [];
        }

        $data = [];
        $profile = $provider->profile;
        $credentials = $provider->providerCredentials;

        // Map requested fields to data sources - FIXED to match ACZ config exactly
        $fieldMapping = [
            'physician_name' => fn() => $provider->full_name ?? trim(($provider->first_name ?? '') . ' ' . ($provider->last_name ?? '')),
            'physician_npi' => fn() => $profile?->npi ?? $provider->npi_number ?? '',
            'physician_tax_id' => fn() => $profile?->tax_id ?? '',
            'physician_ptan' => fn() => $profile?->ptan ?? '',
            'physician_medicaid' => fn() => $profile?->medicaid_number ?? '', // Fixed: was physician_medicaid_number
            'physician_phone' => fn() => $this->formatPhoneNumber($profile?->phone ?? $provider->phone ?? ''),
            'physician_fax' => fn() => $profile?->fax ?? '',
            'physician_specialty' => fn() => $profile?->specialty ?? $profile?->primary_specialty ?? '',
            'physician_organization' => fn() => $profile?->practice_name ?? '',
            
            // Network status - default to in-network
            'physician_status_primary_in_network' => fn() => 'true',
            'physician_status_primary_out_of_network' => fn() => 'false',
            'physician_status_secondary_in_network' => fn() => 'true',
            'physician_status_secondary_out_of_network' => fn() => 'false',
            
            // Legacy field names for backward compatibility
            'provider_name' => fn() => $provider->full_name ?? trim(($provider->first_name ?? '') . ' ' . ($provider->last_name ?? '')),
            'provider_npi' => fn() => $profile?->npi ?? $provider->npi_number ?? '',
            'provider_tax_id' => fn() => $profile?->tax_id ?? '',
            'provider_tin' => fn() => $profile?->tax_id ?? '', // Alias for tax_id
            'provider_ptan' => fn() => $profile?->ptan ?? '',
            'provider_specialty' => fn() => $profile?->specialty ?? $profile?->primary_specialty ?? '',
            'provider_phone' => fn() => $this->formatPhoneNumber($profile?->phone ?? $provider->phone ?? ''),
            'provider_fax' => fn() => $profile?->fax ?? '',
            'provider_medicaid_number' => fn() => $profile?->medicaid_number ?? '',
            'provider_medicaid' => fn() => $profile?->medicaid_number ?? '', // Alias
        ];

        // Add provider credentials data if available
        if ($credentials && $credentials->count() > 0) {
            foreach ($credentials as $credential) {
                switch ($credential->credential_type) {
                    case 'npi_number':
                        if (empty($fieldMapping['physician_npi']()) && empty($fieldMapping['provider_npi']())) {
                            $fieldMapping['physician_npi'] = fn() => $credential->credential_number;
                            $fieldMapping['provider_npi'] = fn() => $credential->credential_number;
                        }
                        break;
                    case 'medical_license':
                        $fieldMapping['physician_license_number'] = fn() => $credential->credential_number;
                        $fieldMapping['physician_license_state'] = fn() => $credential->issuing_state;
                        $fieldMapping['physician_license_expiration'] = fn() => $credential->expiration_date?->format('Y-m-d');
                        break;
                    case 'dea_registration':
                        $fieldMapping['physician_dea_number'] = fn() => $credential->credential_number;
                        $fieldMapping['physician_dea_expiration'] = fn() => $credential->expiration_date?->format('Y-m-d');
                        break;
                }
            }
        }

        // Extract only requested fields
        foreach ($fields as $field) {
            if (isset($fieldMapping[$field])) {
                $value = $fieldMapping[$field]();
                if (!empty($value) || $value === '0') {
                    $data[$field] = $value;
                }
            }
        }

        $this->logger->info('Provider data extracted', [
            'provider_id' => $providerId,
            'requested_fields' => count($fields),
            'extracted_fields' => count($data),
            'has_profile' => !is_null($profile),
            'sample_data' => array_slice($data, 0, 5, true)
        ]);

        return $data;
    }

    /**
     * Extract only requested facility fields
     */
    public function extractFacilityData(int $facilityId, array $fields): array
    {
        $facility = Facility::with(['organization'])->find($facilityId);
        if (!$facility) {
            return [];
        }

        $data = [];

        // Map requested fields to data sources
        $fieldMapping = [
            'facility_name' => fn() => $facility->name ?? '',
            'facility_npi' => fn() => $facility->npi ?? '',
            'facility_tax_id' => fn() => $facility->tax_id ?? '',
            'facility_ptan' => fn() => $facility->ptan ?? '',
            'facility_medicaid_number' => fn() => $facility->medicaid_number ?? '',
            'facility_address' => fn() => $facility->address ?? '',
            'facility_phone' => fn() => $facility->phone ?? '',
            'facility_fax' => fn() => $facility->fax ?? '',
            'facility_contact_name' => fn() => $facility->contact_name ?? '',
            'facility_contact_number' => fn() => $facility->contact_phone ?? $facility->contact_email ?? '',
            'facility_organization' => fn() => $facility->organization?->name ?? '',
            'facility_city_state_zip' => fn() => trim(
                ($facility->city ?? '') . ', ' . 
                ($facility->state ?? '') . ' ' . 
                ($facility->zip_code ?? '')
            ),
        ];

        // Extract only requested fields
        foreach ($fields as $field) {
            if (isset($fieldMapping[$field])) {
                $data[$field] = $fieldMapping[$field]();
            }
        }

        // Handle place of service fields
        if ($this->hasPlaceOfServiceFields($fields)) {
            $posData = $this->extractPlaceOfServiceData($facility, $fields);
            $data = array_merge($data, $posData);
        }

        return $data;
    }

    /**
     * Extract place of service checkbox fields
     */
    protected function extractPlaceOfServiceData(Facility $facility, array $fields): array
    {
        $pos = $facility->default_place_of_service ?? '';
        $data = [];

        $posMapping = [
            'pos_11' => '11',  // Office
            'pos_22' => '22',  // Outpatient Hospital
            'pos_24' => '24',  // Ambulatory Surgical Center
            'pos_12' => '12',  // Home
            'pos_32' => '32',  // Nursing Facility
        ];

        foreach ($fields as $field) {
            if (isset($posMapping[$field])) {
                $data[$field] = ($pos === $posMapping[$field]) ? 'true' : 'false';
            } elseif ($field === 'pos_other') {
                $data[$field] = !in_array($pos, array_values($posMapping)) ? 'true' : 'false';
            } elseif ($field === 'pos_other_specify') {
                $data[$field] = !in_array($pos, array_values($posMapping)) ? $pos : '';
            }
        }

        return $data;
    }

    /**
     * Add common fields that aren't entity-specific
     */
    protected function addCommonFields(array $data, array $requiredFields): array
    {
        $currentUser = Auth::user();

        $commonMapping = [
            'name' => fn() => $currentUser?->full_name ?? trim(($currentUser?->first_name ?? '') . ' ' . ($currentUser?->last_name ?? '')),
            'email' => fn() => $currentUser?->email ?? '',
            'phone' => fn() => $currentUser?->phone ?? '',
            'sales_rep' => fn() => $currentUser?->full_name ?? trim(($currentUser?->first_name ?? '') . ' ' . ($currentUser?->last_name ?? '')),
            'distributor_company' => fn() => 'MSC Wound Care',
        ];

        foreach ($requiredFields as $field) {
            if (isset($commonMapping[$field]) && !isset($data[$field])) {
                $data[$field] = $commonMapping[$field]();
            }
        }

        return $data;
    }

    /**
     * Get fields that match a prefix
     */
    protected function getFieldsByPrefix(array $fields, string $prefix): array
    {
        return array_filter($fields, fn($field) => str_starts_with($field, $prefix));
    }

    /**
     * Check if any place of service fields are requested
     */
    protected function hasPlaceOfServiceFields(array $fields): bool
    {
        return !empty(array_filter($fields, fn($field) => str_starts_with($field, 'pos_')));
    }

    /**
     * Format phone number to (XXX) XXX-XXXX format
     */
    private function formatPhoneNumber(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Format if we have 10 digits
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }

        // Return original if not 10 digits
        return $phone;
    }
} 