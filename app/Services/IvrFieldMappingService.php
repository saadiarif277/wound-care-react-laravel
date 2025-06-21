<?php

namespace App\Services;

use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\Fhir\Patient;
use App\Models\Fhir\Practitioner;
use App\Models\Fhir\Facility;
use App\Models\Users\Provider\ProviderProfile;
use App\Services\FhirService;
use App\Services\DocuSealFieldFormatterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class IvrFieldMappingService
{
    private array $fieldMappings;
    private FhirService $fhirService;
    private DocuSealFieldFormatterService $fieldFormatter;

    public function __construct(FhirService $fhirService, DocuSealFieldFormatterService $fieldFormatter)
    {
        $this->fieldMappings = config('ivr-field-mappings') ?? [];
        $this->fhirService = $fhirService;
        $this->fieldFormatter = $fieldFormatter;
    }

    /**
     * Map product request data to IVR fields for a specific manufacturer
     * This now also accepts patient data from FHIR separately
     */
    public function mapProductRequestToIvrFields(
        ProductRequest $productRequest,
        string $manufacturerKey,
        array $patientData = []
    ): array {
        $manufacturer = $this->getManufacturerConfig($manufacturerKey);

        if (!$manufacturer) {
            throw new \Exception("Unknown manufacturer: {$manufacturerKey}");
        }

        // Load all required relationships
        $productRequest->load([
            'provider',
            'provider.profile',
            'facility',
            'facility.organization',
            'products'
        ]);

        // Get field mappings for this manufacturer
        $mappings = $manufacturer['field_mappings'] ?? [];
        $mappedFields = [];

        // First, map standard DocuSeal fields as per the guide
        $mappedFields = $this->mapStandardDocuSealFields($productRequest, $patientData);

        // Then overlay manufacturer-specific field mappings
        foreach ($mappings as $ivrFieldName => $systemField) {
            $value = $this->getFieldValue($productRequest, $systemField, $patientData);
            if ($value !== null) {
                // Detect field type and format appropriately
                $fieldType = $this->fieldFormatter->detectFieldType($ivrFieldName, $value);
                $formattedValue = $this->fieldFormatter->formatFieldValue($value, $fieldType);
                $mappedFields[$ivrFieldName] = $formattedValue;
            }
        }

        // Apply any manufacturer-specific formatting rules
        $mappedFields = $this->applyManufacturerSpecificFormatting($mappedFields, $manufacturerKey);

        return $mappedFields;
    }

    /**
     * Map standard DocuSeal fields according to the embedded fields guide
     * Enhanced to include episode extracted data
     */
    private function mapStandardDocuSealFields(ProductRequest $productRequest, array $patientData): array
    {
        $fields = [];

        // Patient Information Fields (enhanced from FHIR + extracted data)
        if (!empty($patientData)) {
            $fields['patient_first_name'] = $patientData['given'][0] ?? '';
            $fields['patient_last_name'] = $patientData['family'] ?? '';
            $fields['patient_dob'] = isset($patientData['birthDate']) ? Carbon::parse($patientData['birthDate'])->format('Y-m-d') : '';
            $fields['patient_member_id'] = $productRequest->payer_id ?? '';
            $fields['patient_gender'] = $patientData['gender'] ?? '';

            // Enhanced Patient Contact Information
            $fields['patient_email'] = $patientData['email'] ?? '';

            // Patient Address from FHIR (enhanced)
            if (isset($patientData['address'][0])) {
                $address = $patientData['address'][0];
                $fields['patient_address_line1'] = $address['line'][0] ?? '';
                $fields['patient_address_line2'] = $address['line'][1] ?? '';
                $fields['patient_city'] = $address['city'] ?? '';
                $fields['patient_state'] = $address['state'] ?? '';
                $fields['patient_zip'] = $address['postalCode'] ?? '';
            }

            // Patient Phone from FHIR
            if (isset($patientData['telecom'])) {
                foreach ($patientData['telecom'] as $telecom) {
                    if ($telecom['system'] === 'phone') {
                        $fields['patient_phone'] = $telecom['value'] ?? '';
                        break;
                    }
                }
            }

            // Enhanced Caregiver Information (from extracted data)
            $fields['caregiver_name'] = $patientData['caregiver_name'] ?? '';
            $fields['caregiver_relationship'] = $patientData['caregiver_relationship'] ?? '';
            $fields['caregiver_phone'] = $patientData['caregiver_phone'] ?? '';
        }

        // Patient Display ID (de-identified)
        $fields['patient_display_id'] = $productRequest->patient_display_id ?? '';

        // Enhanced Insurance Information Fields (from extracted data)
        $fields['primary_insurance_name'] = $productRequest->payer_name_submitted ?? '';
        $fields['primary_member_id'] = $productRequest->payer_id ?? '';
        $fields['primary_plan_type'] = $productRequest->insurance_type ?? '';

        // Additional insurance fields from episode extraction
        $episodeData = $productRequest->episode?->metadata['extracted_data'] ?? [];
        $fields['primary_payer_phone'] = $episodeData['primary_payer_phone'] ?? '';
        $fields['secondary_insurance_name'] = $episodeData['secondary_insurance_name'] ?? '';
        $fields['secondary_member_id'] = $episodeData['secondary_member_id'] ?? '';
        $fields['insurance_group_number'] = $episodeData['insurance_group_number'] ?? '';

        // Legacy fields for backwards compatibility
        $fields['payer_name'] = $fields['primary_insurance_name'];
        $fields['payer_id'] = $fields['primary_member_id'];
        $fields['insurance_type'] = $fields['primary_plan_type'];

        // Product Information Fields
        $product = $productRequest->products->first();
        if ($product) {
            $fields['product_name'] = $product->name;
            $fields['product_code'] = $product->q_code ?? $product->code ?? '';
            $fields['manufacturer'] = $product->manufacturer;
            $fields['size'] = $product->pivot->size ?? '';
            $fields['quantity'] = $product->pivot->quantity ?? 1;
        }

        // Service Information Fields
        $fields['expected_service_date'] = $productRequest->expected_service_date ?
            Carbon::parse($productRequest->expected_service_date)->format('Y-m-d') : '';
        $fields['wound_type'] = $productRequest->wound_type ?? '';
        $fields['place_of_service'] = $this->mapPlaceOfService($productRequest->place_of_service);

        // Provider Information Fields
        $provider = $productRequest->provider;
        if ($provider) {
            $fields['provider_name'] = $provider->first_name . ' ' . $provider->last_name;
            $fields['provider_npi'] = $provider->npi_number ?? '';
            $fields['signature_date'] = Carbon::now()->format('Y-m-d');
        }

        // Facility Information
        $facility = $productRequest->facility;
        if ($facility) {
            $fields['facility_name'] = $facility->name;
            $fields['facility_address'] = $facility->address ?? '';
        }

        // Clinical Attestations - Convert boolean to Yes/No
        $fields['failed_conservative_treatment'] = $productRequest->failed_conservative_treatment ? 'Yes' : 'No';
        $fields['information_accurate'] = $productRequest->information_accurate ? 'Yes' : 'No';
        $fields['medical_necessity_established'] = $productRequest->medical_necessity_established ? 'Yes' : 'No';
        $fields['maintain_documentation'] = $productRequest->maintain_documentation ? 'Yes' : 'No';
        $fields['authorize_prior_auth'] = $productRequest->authorize_prior_auth ? 'Yes' : 'No';

        // Auto-Generated Fields
        $fields['todays_date'] = Carbon::now()->format('m/d/Y');
        $fields['current_time'] = Carbon::now()->format('h:i:s A');

        // Enhanced Clinical Information (from episode extraction + clinical summary)
        $episodeData = $productRequest->episode?->metadata['extracted_data'] ?? [];

        // Wound details from extraction
        $fields['wound_location'] = $episodeData['wound_location'] ?? '';
        $fields['wound_size_length'] = $episodeData['wound_size_length'] ?? '';
        $fields['wound_size_width'] = $episodeData['wound_size_width'] ?? '';
        $fields['wound_size_depth'] = $episodeData['wound_size_depth'] ?? '';
        $fields['wound_duration'] = $episodeData['wound_duration'] ?? '';
        $fields['total_wound_area'] = $episodeData['total_wound_area'] ?? '';

        // Diagnosis and treatment codes
        $fields['yellow_diagnosis_code'] = $episodeData['yellow_diagnosis_code'] ?? '';
        $fields['orange_diagnosis_code'] = $episodeData['orange_diagnosis_code'] ?? '';
        $fields['previous_treatments'] = $episodeData['previous_treatments'] ?? '';
        $fields['application_cpt_codes'] = $episodeData['application_cpt_codes'] ?? [];

        // Clinical Summary Fields (fallback to existing if extraction not available)
        if ($productRequest->clinical_summary && empty($fields['wound_location'])) {
            $clinical = json_decode($productRequest->clinical_summary, true);
            if (isset($clinical['woundDetails'])) {
                $fields['wound_location'] = $clinical['woundDetails']['location'] ?? '';
                $fields['wound_size'] = $clinical['woundDetails']['size'] ?? '';
                $fields['wound_duration'] = $clinical['woundDetails']['duration'] ?? '';
            }
        }

        return $fields;
    }

    /**
     * Get field value from product request and related models
     * Now also accepts patient data from FHIR
     */
    private function getFieldValue(ProductRequest $productRequest, string $fieldName, array $patientData = []): mixed
    {
        // First check if it's a patient field that should come from FHIR data
        if (str_starts_with($fieldName, 'patient_') && !empty($patientData)) {
            return $this->getPatientFieldFromFhir($fieldName, $patientData);
        }

        // Otherwise use existing mapping logic
        $value = null;

        switch ($fieldName) {
            // Sales Rep Information
            case 'sales_rep_name':
                $salesRep = $productRequest->organization?->salesRep;
                $value = $salesRep ? $salesRep->first_name . ' ' . $salesRep->last_name : '';
                break;

            case 'sales_rep_email':
                $value = $productRequest->organization?->salesRep?->email ?? '';
                break;

            case 'additional_notification_emails':
                // Get additional emails from organization settings
                $value = $productRequest->organization?->notification_emails ?? '';
                break;

            // Provider Information
            case 'provider_name':
                $provider = $productRequest->provider;
                $value = $provider ? $provider->first_name . ' ' . $provider->last_name : '';
                break;

            case 'provider_specialty':
                $value = $productRequest->provider?->profile?->specialty ?? '';
                break;

            case 'provider_npi':
                $value = $productRequest->provider?->npi_number ?? '';
                break;

            case 'provider_tax_id':
                $value = $productRequest->provider?->profile?->tax_id ?? '';
                break;

            case 'provider_ptan':
                $value = $productRequest->provider?->profile?->ptan ?? '';
                break;

            case 'provider_medicaid_number':
                $value = $productRequest->provider?->profile?->medicaid_number ?? '';
                break;

            case 'provider_phone':
                $value = $productRequest->provider?->profile?->phone ?? '';
                break;

            case 'provider_fax':
                $value = $productRequest->provider?->profile?->fax ?? '';
                break;

            // Facility Information
            case 'facility_name':
                $value = $productRequest->facility?->name ?? '';
                break;

            case 'facility_address':
                $facility = $productRequest->facility;
                if ($facility) {
                    $value = $facility->address ?? '';
                    if (is_array($value) && isset($value['line'])) {
                        $value = $value['line'][0] ?? '';
                    }
                }
                break;

            case 'facility_city':
                $facility = $productRequest->facility;
                if ($facility && is_array($facility->address)) {
                    $value = $facility->address['city'] ?? '';
                } else {
                    $value = $facility?->city ?? '';
                }
                break;

            case 'facility_state':
                $facility = $productRequest->facility;
                if ($facility && is_array($facility->address)) {
                    $value = $facility->address['state'] ?? '';
                } else {
                    $value = $facility?->state ?? '';
                }
                break;

            case 'facility_zip':
                $facility = $productRequest->facility;
                if ($facility && is_array($facility->address)) {
                    $value = $facility->address['postalCode'] ?? '';
                } else {
                    $value = $facility?->zip_code ?? '';
                }
                break;

            case 'facility_contact_name':
                $value = $productRequest->facility?->contact_name ?? '';
                break;

            case 'facility_contact_phone':
                $value = $productRequest->facility?->contact_phone ?? '';
                break;

            case 'facility_contact_fax':
                $value = $productRequest->facility?->contact_fax ?? '';
                break;

            case 'facility_contact_email':
                $value = $productRequest->facility?->contact_email ?? '';
                break;

            case 'facility_npi':
                $value = $productRequest->facility?->npi ?? '';
                break;

            case 'facility_tax_id':
                $value = $productRequest->facility?->tax_id ?? '';
                break;

            case 'facility_ptan':
                $value = $productRequest->facility?->ptan ?? '';
                break;

            case 'facility_medicare_contractor':
                $value = $productRequest->facility?->medicare_contractor ?? '';
                break;

            // Insurance Information
            case 'primary_insurance_name':
            case 'payer_name':
                $value = $productRequest->payer_name_submitted ?? '';
                break;

            case 'primary_policy_number':
            case 'payer_id':
                $value = $productRequest->payer_id ?? '';
                break;

            case 'insurance_type':
                $value = $productRequest->insurance_type ?? '';
                break;

            // Clinical Information
            case 'place_of_service':
                $value = $this->mapPlaceOfService($productRequest->place_of_service);
                break;

            case 'wound_type':
                $value = $productRequest->wound_type ?? '';
                break;

            case 'wound_location':
            case 'wound_location_details':
                // Try to get from clinical summary first
                if ($productRequest->clinical_summary) {
                    $clinical = is_string($productRequest->clinical_summary) ?
                        json_decode($productRequest->clinical_summary, true) : $productRequest->clinical_summary;
                    $value = $clinical['woundDetails']['location'] ?? $productRequest->wound_location ?? '';
                } else {
                    $value = $productRequest->wound_location ?? '';
                }
                break;

            case 'wound_size':
                // Try to get from clinical summary
                if ($productRequest->clinical_summary) {
                    $clinical = is_string($productRequest->clinical_summary) ?
                        json_decode($productRequest->clinical_summary, true) : $productRequest->clinical_summary;
                    $value = $clinical['woundDetails']['size'] ?? '';
                }
                break;

            case 'wound_duration':
                // Try to get from clinical summary
                if ($productRequest->clinical_summary) {
                    $clinical = is_string($productRequest->clinical_summary) ?
                        json_decode($productRequest->clinical_summary, true) : $productRequest->clinical_summary;
                    $value = $clinical['woundDetails']['duration'] ?? '';
                }
                break;

            case 'expected_service_date':
                if ($productRequest->expected_service_date) {
                    $value = Carbon::parse($productRequest->expected_service_date)->format('m/d/Y');
                }
                break;

            // Product Information
            case 'product_name':
                $product = $productRequest->products->first();
                $value = $product ? $product->name : '';
                break;

            case 'product_code':
                $product = $productRequest->products->first();
                $value = $product ? ($product->q_code ?? $product->code ?? '') : '';
                break;

            case 'manufacturer':
                $product = $productRequest->products->first();
                $value = $product ? $product->manufacturer : '';
                break;

            case 'size':
            case 'graft_size_requested':
                $product = $productRequest->products->first();
                $value = $product && $product->pivot ? $product->pivot->size : '';
                break;

            case 'quantity':
                $product = $productRequest->products->first();
                $value = $product && $product->pivot ? $product->pivot->quantity : 1;
                break;

            // Attestation fields
            case 'failed_conservative_treatment':
                $value = $productRequest->failed_conservative_treatment ? 'Yes' : 'No';
                break;

            case 'information_accurate':
                $value = $productRequest->information_accurate ? 'Yes' : 'No';
                break;

            case 'medical_necessity_established':
                $value = $productRequest->medical_necessity_established ? 'Yes' : 'No';
                break;

            case 'maintain_documentation':
                $value = $productRequest->maintain_documentation ? 'Yes' : 'No';
                break;

            case 'authorize_prior_auth':
                $value = $productRequest->authorize_prior_auth ? 'Yes' : 'No';
                break;

            // Date fields
            case 'todays_date':
            case 'signature_date':
                $value = Carbon::now()->format('m/d/Y');
                break;

            case 'current_time':
                $value = Carbon::now()->format('h:i:s A');
                break;

            // Manufacturer-specific fields
            case 'physician_attestation':
                $value = 'Yes'; // Default attestation
                break;

            case 'not_used_previously':
                $value = 'Yes'; // Default for new requests
                break;

            case 'stat_order':
                $value = $productRequest->is_stat_order ? 'Yes' : 'No';
                break;

            case 'first_application':
                $value = 'Yes'; // Default for new requests
                break;

            default:
                Log::debug("Unknown IVR field mapping: {$fieldName}");
                break;
        }

        return $value;
    }

    /**
     * Get patient field value from FHIR data
     */
    private function getPatientFieldFromFhir(string $fieldName, array $patientData): mixed
    {
        switch ($fieldName) {
            case 'patient_first_name':
                return $patientData['given'][0] ?? '';

            case 'patient_last_name':
                return $patientData['family'] ?? '';

            case 'patient_dob':
                return isset($patientData['birthDate']) ?
                    Carbon::parse($patientData['birthDate'])->format('Y-m-d') : '';

            case 'patient_gender':
                return $patientData['gender'] ?? '';

            case 'patient_address_line1':
                return $patientData['address'][0]['line'][0] ?? '';

            case 'patient_address_line2':
                return $patientData['address'][0]['line'][1] ?? '';

            case 'patient_city':
                return $patientData['address'][0]['city'] ?? '';

            case 'patient_state':
                return $patientData['address'][0]['state'] ?? '';

            case 'patient_zip':
                return $patientData['address'][0]['postalCode'] ?? '';

            case 'patient_phone':
                foreach ($patientData['telecom'] ?? [] as $telecom) {
                    if ($telecom['system'] === 'phone') {
                        return $telecom['value'] ?? '';
                    }
                }
                return '';

            default:
                return '';
        }
    }

    /**
     * Get manufacturer configuration
     */
    public function getManufacturerConfig(string $manufacturerKey): ?array
    {
        return $this->fieldMappings['manufacturers'][$manufacturerKey] ?? null;
    }

    /**
     * Map place of service code to description
     */
    private function mapPlaceOfService(?string $code): string
    {
        $posMap = [
            '11' => 'Physician Office',
            '22' => 'Hospital Outpatient',
            '24' => 'Ambulatory Surgical Center',
            '12' => 'Home',
            '13' => 'Assisted Living Facility',
            '31' => 'Skilled Nursing Facility',
            '32' => 'Nursing Facility',
        ];

        return $posMap[$code] ?? $code ?? '';
    }

    /**
     * Get DocuSeal template and folder IDs for a manufacturer
     */
    public function getDocuSealConfig(string $manufacturerKey): array
    {
        $manufacturer = $this->getManufacturerConfig($manufacturerKey);

        if (!$manufacturer) {
            throw new \Exception("Unknown manufacturer: {$manufacturerKey}");
        }

        return [
            'template_id' => $manufacturer['template_id'] ?? null,
            'folder_id' => $manufacturer['folder_id'] ?? null,
            'name' => $manufacturer['name'] ?? $manufacturerKey,
        ];
    }

    /**
     * Get all available manufacturers
     */
    public function getAvailableManufacturers(): array
    {
        $manufacturers = [];

        foreach ($this->fieldMappings['manufacturers'] as $key => $config) {
            $manufacturers[$key] = [
                'key' => $key,
                'name' => $config['name'],
                'template_id' => $config['template_id'] ?? null,
                'has_mapping' => !empty($config['field_mappings']),
            ];
        }

        return $manufacturers;
    }

    /**
     * Validate that all required fields are mapped for a manufacturer
     */
    public function validateMapping(string $manufacturerKey, array $mappedData): array
    {
        $errors = [];
        $manufacturer = $this->getManufacturerConfig($manufacturerKey);

        if (!$manufacturer) {
            return ['Unknown manufacturer'];
        }

        // Check for required fields based on manufacturer
        $requiredFields = $this->getRequiredFieldsForManufacturer($manufacturerKey);

        foreach ($requiredFields as $field) {
            if (empty($mappedData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        return $errors;
    }

    /**
     * Get required fields for a specific manufacturer
     */
    private function getRequiredFieldsForManufacturer(string $manufacturerKey): array
    {
        // Define required fields per manufacturer based on DocuSeal guide
        $requiredByManufacturer = [
            'ACZ_Distribution' => [
                'provider_name',
                'provider_npi',
                'facility_name',
                'patient_display_id',
                'payer_name',
                'product_name',
                'failed_conservative_treatment',
                'medical_necessity_established',
            ],
            'Advanced_Health' => [
                'provider_name',
                'patient_display_id',
                'payer_name',
                'wound_type',
                'product_name',
                'multiple_products',
            ],
            'MedLife' => [
                'provider_name',
                'patient_display_id',
                'payer_name',
                'product_name',
                'amnio_amp_size',
            ],
            'Centurion' => [
                'provider_name',
                'patient_display_id',
                'previous_amnion_use',
                'stat_order',
            ],
            'BioWerX' => [
                'provider_name',
                'patient_display_id',
                'first_application',
                'reapplication',
            ],
            'BioWound' => [
                'provider_name',
                'california_facility',
                'mesh_configuration',
                'previous_biologics_failed',
            ],
            'Extremity_Care' => [
                'provider_name',
                'quarter',
                'order_type',
            ],
            'Skye_Biologics' => [
                'provider_name',
                'shipping_speed_required',
                'temperature_controlled',
            ],
            'Total_Ancillary_Forms' => [
                'provider_name',
                'universal_benefits_verified',
                'facility_account_number',
            ],
        ];

        return $requiredByManufacturer[$manufacturerKey] ?? [];
    }

    /**
     * Apply manufacturer-specific formatting rules
     */
    private function applyManufacturerSpecificFormatting(array $fields, string $manufacturerKey): array
    {
        switch ($manufacturerKey) {
            case 'ACZ_Distribution':
                // ACZ specific formatting
                if (isset($fields['physician_attestation'])) {
                    $fields['physician_attestation'] = ($fields['physician_attestation'] === 'Yes') ? 'Yes' : 'No';
                }
                if (isset($fields['not_used_previously'])) {
                    $fields['not_used_previously'] = ($fields['not_used_previously'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'Advanced_Health':
                // Advanced Health checkbox formatting
                if (isset($fields['multiple_products'])) {
                    $fields['multiple_products'] = ($fields['multiple_products'] === 'Yes') ? 'Yes' : 'No';
                }
                if (isset($fields['previous_use'])) {
                    $fields['previous_use'] = ($fields['previous_use'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'Centurion':
                // Centurion specific fields
                if (isset($fields['stat_order'])) {
                    $fields['stat_order'] = ($fields['stat_order'] === 'Yes') ? 'Yes' : 'No';
                }
                if (isset($fields['previous_amnion_use'])) {
                    $fields['previous_amnion_use'] = ($fields['previous_amnion_use'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'BioWerX':
                // BioWerX specific fields
                if (isset($fields['first_application'])) {
                    $fields['first_application'] = ($fields['first_application'] === 'Yes') ? 'Yes' : 'No';
                }
                if (isset($fields['reapplication'])) {
                    $fields['reapplication'] = ($fields['reapplication'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'BioWound':
                // BioWound specific fields
                if (isset($fields['california_facility'])) {
                    $fields['california_facility'] = ($fields['california_facility'] === 'Yes') ? 'Yes' : 'No';
                }
                if (isset($fields['previous_biologics_failed'])) {
                    $fields['previous_biologics_failed'] = ($fields['previous_biologics_failed'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'Skye_Biologics':
                // Skye specific fields
                if (isset($fields['temperature_controlled'])) {
                    $fields['temperature_controlled'] = ($fields['temperature_controlled'] === 'Yes') ? 'Yes' : 'No';
                }
                break;

            case 'Total_Ancillary_Forms':
                // Total Ancillary specific fields
                if (isset($fields['universal_benefits_verified'])) {
                    $fields['universal_benefits_verified'] = ($fields['universal_benefits_verified'] === 'Yes') ? 'Yes' : 'No';
                }
                break;
        }

        return $fields;
    }

    /**
     * Get field type definitions for a manufacturer
     */
    public function getFieldTypes(string $manufacturerKey): array
    {
        // Define specific field types for each manufacturer based on DocuSeal guide
        $fieldTypeDefinitions = [
            'common' => [
                // Standard fields from guide
                'patient_first_name' => 'text',
                'patient_last_name' => 'text',
                'patient_dob' => 'date',
                'patient_member_id' => 'text',
                'patient_gender' => 'select',
                'patient_phone' => 'phone',
                'patient_address_line1' => 'text',
                'patient_address_line2' => 'text',
                'patient_city' => 'text',
                'patient_state' => 'text',
                'patient_zip' => 'text',
                'payer_name' => 'text',
                'payer_id' => 'text',
                'insurance_type' => 'select',
                'product_name' => 'text',
                'product_code' => 'text',
                'manufacturer' => 'text',
                'size' => 'text',
                'quantity' => 'number',
                'expected_service_date' => 'date',
                'wound_type' => 'select',
                'place_of_service' => 'select',
                'provider_name' => 'text',
                'provider_npi' => 'text',
                'signature_date' => 'date',
                'facility_name' => 'text',
                'facility_address' => 'text',
                'failed_conservative_treatment' => 'checkbox',
                'information_accurate' => 'checkbox',
                'medical_necessity_established' => 'checkbox',
                'maintain_documentation' => 'checkbox',
                'authorize_prior_auth' => 'checkbox',
                'todays_date' => 'datenow',
                'current_time' => 'text',
                'provider_signature' => 'signature',
            ],
            'ACZ_Distribution' => [
                'physician_attestation' => 'checkbox',
                'not_used_previously' => 'checkbox',
            ],
            'Advanced_Health' => [
                'multiple_products' => 'checkbox',
                'additional_products' => 'text',
                'previous_use' => 'checkbox',
                'previous_product_info' => 'text',
            ],
            'MedLife' => [
                'amnio_amp_size' => 'select',
            ],
            'Centurion' => [
                'previous_amnion_use' => 'checkbox',
                'previous_product' => 'text',
                'previous_date' => 'date',
                'stat_order' => 'checkbox',
            ],
            'BioWerX' => [
                'first_application' => 'checkbox',
                'reapplication' => 'checkbox',
                'previous_product' => 'text',
            ],
            'BioWound' => [
                'california_facility' => 'checkbox',
                'mesh_configuration' => 'select',
                'previous_biologics_failed' => 'checkbox',
                'failed_biologics_list' => 'text',
            ],
            'Extremity_Care' => [
                'quarter' => 'radio',
                'order_type' => 'select',
            ],
            'Skye_Biologics' => [
                'shipping_speed_required' => 'select',
                'temperature_controlled' => 'checkbox',
            ],
            'Total_Ancillary_Forms' => [
                'universal_benefits_verified' => 'checkbox',
                'facility_account_number' => 'text',
            ],
        ];

        // Merge common fields with manufacturer-specific fields
        $manufacturerFields = $fieldTypeDefinitions[$manufacturerKey] ?? [];
        return array_merge($fieldTypeDefinitions['common'], $manufacturerFields);
    }
}
