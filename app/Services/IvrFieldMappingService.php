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
     */
    public function mapProductRequestToIvrFields(
        ProductRequest $productRequest,
        string $manufacturerKey
    ): array {
        $manufacturer = $this->getManufacturerConfig($manufacturerKey);

        if (!$manufacturer) {
            throw new \Exception("Unknown manufacturer: {$manufacturerKey}");
        }

        // Load all required relationships
        $productRequest->load([
            'patient',
            'provider',
            'provider.profile',
            'facility',
            'organization',
            'products',
            'order'
        ]);

        // Get field mappings for this manufacturer
        $mappings = $manufacturer['field_mappings'] ?? [];
        $mappedFields = [];

        // Process each field mapping
        foreach ($mappings as $ivrFieldName => $systemField) {
            $value = $this->getFieldValue($productRequest, $systemField);
            if ($value !== null) {
                // Detect field type and format appropriately
                $fieldType = $this->fieldFormatter->detectFieldType($ivrFieldName, $value);
                $formattedValue = $this->fieldFormatter->formatFieldValue($value, $fieldType);
                $mappedFields[$ivrFieldName] = $formattedValue;
            }
        }

        // Add any additional fields from Azure FHIR if needed
        $mappedFields = $this->enrichWithFhirData($mappedFields, $productRequest, $manufacturerKey);

        // Apply any manufacturer-specific formatting rules
        $mappedFields = $this->applyManufacturerSpecificFormatting($mappedFields, $manufacturerKey);

        return $mappedFields;
    }

    /**
     * Get field value from product request and related models
     */
    private function getFieldValue(ProductRequest $productRequest, string $fieldName): mixed
    {
        // Split field name for nested access (e.g., 'provider.name')
        $parts = explode('.', $fieldName);
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
                $value = $productRequest->provider?->profile?->npi ?? '';
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
                if ($facility && $facility->address) {
                    $value = $facility->address['line'][0] ?? '';
                }
                break;

            case 'facility_city':
                $value = $productRequest->facility?->address['city'] ?? '';
                break;

            case 'facility_state':
                $value = $productRequest->facility?->address['state'] ?? '';
                break;

            case 'facility_zip':
                $value = $productRequest->facility?->address['postalCode'] ?? '';
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

            // Patient Information (Minimal PHI)
            case 'patient_name':
                // Only show display ID, not actual name
                $value = $productRequest->patient?->patient_display_id ?? '';
                break;

            case 'patient_dob':
                // Format date of birth if available
                if ($productRequest->patient?->birth_date) {
                    $value = Carbon::parse($productRequest->patient->birth_date)->format('m/d/Y');
                }
                break;

            case 'patient_gender':
                $value = $productRequest->patient?->gender ?? '';
                break;

            case 'patient_address':
            case 'patient_city':
            case 'patient_state':
            case 'patient_zip':
            case 'patient_phone':
                // These fields might come from FHIR if needed
                $value = ''; // To be filled from FHIR if required
                break;

            // Insurance Information
            case 'primary_insurance_name':
                $value = $productRequest->primary_insurance_name ?? '';
                break;

            case 'primary_policy_number':
                $value = $productRequest->primary_insurance_id ?? '';
                break;

            case 'primary_payer_phone':
                $value = $productRequest->primary_insurance_phone ?? '';
                break;

            case 'primary_subscriber_name':
                $value = $productRequest->primary_subscriber_name ?? '';
                break;

            case 'primary_subscriber_dob':
                if ($productRequest->primary_subscriber_dob) {
                    $value = Carbon::parse($productRequest->primary_subscriber_dob)->format('m/d/Y');
                }
                break;

            case 'primary_plan_type':
                $value = $productRequest->primary_plan_type ?? '';
                break;

            case 'primary_network_status':
                $value = $productRequest->primary_network_status ?? '';
                break;

            case 'secondary_insurance_name':
                $value = $productRequest->secondary_insurance_name ?? '';
                break;

            case 'secondary_policy_number':
                $value = $productRequest->secondary_insurance_id ?? '';
                break;

            case 'secondary_payer_phone':
                $value = $productRequest->secondary_insurance_phone ?? '';
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
                $value = $productRequest->wound_location ?? '';
                break;

            case 'wound_size_length':
                $value = $productRequest->wound_length ?? '';
                break;

            case 'wound_size_width':
                $value = $productRequest->wound_width ?? '';
                break;

            case 'wound_size_depth':
                $value = $productRequest->wound_depth ?? '';
                break;

            case 'wound_size_total':
                // Calculate total if not provided
                if ($productRequest->wound_area_cm2) {
                    $value = $productRequest->wound_area_cm2;
                } elseif ($productRequest->wound_length && $productRequest->wound_width) {
                    $value = $productRequest->wound_length * $productRequest->wound_width;
                }
                break;

            case 'wound_duration':
                $value = $productRequest->wound_duration_weeks . ' weeks' ?? '';
                break;

            case 'diagnosis_codes':
            case 'primary_diagnosis_code':
                $value = $productRequest->primary_diagnosis_code ?? '';
                break;

            case 'secondary_diagnosis_codes':
                $value = implode(', ', $productRequest->additional_diagnosis_codes ?? []);
                break;

            case 'application_cpt_codes':
                $value = implode(', ', $productRequest->cpt_codes ?? []);
                break;

            case 'anticipated_treatment_date':
                if ($productRequest->anticipated_treatment_date) {
                    $value = Carbon::parse($productRequest->anticipated_treatment_date)->format('m/d/Y');
                }
                break;

            case 'anticipated_applications':
                $value = $productRequest->anticipated_applications ?? 1;
                break;

            // Product Information
            case 'selected_products':
                $products = $productRequest->products;
                if ($products->isNotEmpty()) {
                    $value = $products->pluck('name')->join(', ');
                }
                break;

            case 'product_sizes':
                $products = $productRequest->products;
                if ($products->isNotEmpty()) {
                    $sizes = [];
                    foreach ($products as $product) {
                        if ($product->pivot->size) {
                            $sizes[] = $product->pivot->size;
                        }
                    }
                    $value = implode(', ', $sizes);
                }
                break;

            case 'graft_size_requested':
                // Get from first product's pivot data
                $firstProduct = $productRequest->products->first();
                if ($firstProduct && $firstProduct->pivot->size) {
                    $value = $firstProduct->pivot->size;
                }
                break;

            // Status flags - return raw boolean values, formatter will handle conversion
            case 'snf_status':
                $value = $productRequest->snf_status;
                break;

            case 'snf_days':
                $value = $productRequest->snf_days ?? '';
                break;

            case 'snf_over_100_days':
                $value = ($productRequest->snf_days > 100);
                break;

            case 'hospice_status':
                $value = $productRequest->hospice_status;
                break;

            case 'part_a_status':
                $value = $productRequest->part_a_status;
                break;

            case 'global_period_status':
                $value = $productRequest->global_period_status;
                break;

            case 'global_period_cpt_codes':
                $value = $productRequest->global_period_cpt_codes ?? '';
                break;

            case 'global_period_surgery_date':
                if ($productRequest->global_period_surgery_date) {
                    $value = Carbon::parse($productRequest->global_period_surgery_date)->format('m/d/Y');
                }
                break;

            // Additional fields
            case 'request_type':
                $value = 'New Request'; // Default, could be customized
                break;

            case 'request_date':
                $value = Carbon::now()->format('m/d/Y');
                break;

            case 'signature_date':
                $value = Carbon::now()->format('m/d/Y');
                break;

            case 'authorization_permission':
                $value = 'Yes'; // Default to yes for authorization
                break;

            case 'request_prior_auth_assistance':
                $value = $productRequest->requires_prior_auth ? 'Yes' : '';
                break;

            case 'cards_attached':
                $value = 'Yes'; // Assume cards are attached
                break;

            case 'comorbidities':
                $value = implode(', ', $productRequest->comorbidities ?? []);
                break;

            case 'previous_treatments':
                $value = $productRequest->previous_treatments ?? '';
                break;

            default:
                Log::warning("Unknown IVR field mapping: {$fieldName}");
                break;
        }

        return $value;
    }

    /**
     * Enrich mapped fields with data from Azure FHIR if needed
     */
    private function enrichWithFhirData(
        array $mappedFields,
        ProductRequest $productRequest,
        string $manufacturerKey
    ): array {
        // Check if we need to fetch additional data from FHIR
        $fieldsNeedingFhir = [
            'patient_address',
            'patient_city',
            'patient_state',
            'patient_zip',
            'patient_phone',
            'patient_contact_permission'
        ];

        $manufacturer = $this->getManufacturerConfig($manufacturerKey);
        $fieldMappings = $manufacturer['field_mappings'] ?? [];

        // Check if any FHIR fields are needed for this manufacturer
        $needsFhir = false;
        foreach ($fieldsNeedingFhir as $field) {
            if (array_search($field, $fieldMappings) !== false) {
                $needsFhir = true;
                break;
            }
        }

        if (!$needsFhir || !$productRequest->patient?->fhir_id) {
            return $mappedFields;
        }

        try {
            // Fetch patient data from FHIR
            $fhirPatient = $this->fhirService->getPatient($productRequest->patient->fhir_id);

            if ($fhirPatient) {
                // Map FHIR address data
                if (isset($fhirPatient['address'][0])) {
                    $address = $fhirPatient['address'][0];

                    // Find the IVR field names for each system field
                    foreach ($fieldMappings as $ivrField => $systemField) {
                        switch ($systemField) {
                            case 'patient_address':
                                $mappedFields[$ivrField] = $address['line'][0] ?? '';
                                break;
                            case 'patient_city':
                                $mappedFields[$ivrField] = $address['city'] ?? '';
                                break;
                            case 'patient_state':
                                $mappedFields[$ivrField] = $address['state'] ?? '';
                                break;
                            case 'patient_zip':
                                $mappedFields[$ivrField] = $address['postalCode'] ?? '';
                                break;
                        }
                    }
                }

                // Map FHIR telecom data
                if (isset($fhirPatient['telecom'])) {
                    foreach ($fhirPatient['telecom'] as $telecom) {
                        if ($telecom['system'] === 'phone' && isset($telecom['value'])) {
                            // Find the IVR field name for patient_phone
                            $phoneFieldKey = array_search('patient_phone', $fieldMappings);
                            if ($phoneFieldKey !== false) {
                                $mappedFields[$phoneFieldKey] = $telecom['value'];
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch FHIR data for IVR mapping', [
                'patient_fhir_id' => $productRequest->patient->fhir_id,
                'error' => $e->getMessage()
            ]);
        }

        return $mappedFields;
    }

    /**
     * Get manufacturer configuration
     */
    private function getManufacturerConfig(string $manufacturerKey): ?array
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
        // Define required fields per manufacturer
        $requiredByManufacturer = [
            'ACZ_Distribution' => [
                'Physician Name',
                'NPI',
                'Tax ID',
                'Facility Name',
                'Patient Name',
                'Primary Insurance',
                'ICD-10 Codes',
                'Product',
            ],
            'Advanced_Health' => [
                'Physician Name',
                'Patient Name',
                'Primary Insurance',
                'Wound Type',
                'Product Information',
            ],
            // Add more as needed
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
                // ACZ uses specific checkbox formatting
                foreach ($fields as $fieldName => &$value) {
                    if (in_array($fieldName, ['Is Patient in Hospice', 'Is Patient in Part A', 'Global Period Status'])) {
                        // ACZ prefers uppercase YES/NO for these fields
                        $value = strtoupper($value);
                    }

                    // ACZ expects permission fields as "YES" or blank
                    if ($fieldName === 'Prior Auth Permission' && $value === 'No') {
                        $value = '';
                    }
                }
                break;

            case 'Advanced_Health':
                // Advanced Health uses different checkbox format
                foreach ($fields as $fieldName => &$value) {
                    if (Str::contains($fieldName, ['OK to Contact', 'Prior Auth Required'])) {
                        // They use checkmark character for true
                        $value = ($value === 'Yes' || $value === 'YES') ? '✓' : '';
                    }
                }
                break;

            case 'Amnio_Amp':
                // MedLife Solutions uses 'X' for checked boxes
                foreach ($fields as $fieldName => &$value) {
                    if ($fieldName === 'Insurance Cards Attached' && $value === 'Yes') {
                        $value = 'X';
                    }
                }
                break;

            case 'BioWound':
                // BioWound uses different date format
                foreach ($fields as $fieldName => &$value) {
                    if (Str::contains($fieldName, ['Date', 'DOB']) && !empty($value)) {
                        try {
                            $date = Carbon::parse($value);
                            $value = $date->format('m-d-Y'); // Uses dashes instead of slashes
                        } catch (\Exception $e) {
                            // Keep original format if parsing fails
                        }
                    }
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
        // Define specific field types for each manufacturer
        $fieldTypeDefinitions = [
            'ACZ_Distribution' => [
                'Is Patient in Hospice' => 'checkbox',
                'Is Patient in Part A' => 'checkbox',
                'Global Period Status' => 'checkbox',
                'Prior Auth Permission' => 'checkbox',
                'Place of Service' => 'select',
                'Patient DOB' => 'date',
                'Surgery Date' => 'date',
                'Phone #' => 'phone',
                'Fax #' => 'phone',
                'Patient Phone' => 'phone',
                'Primary Payer Phone' => 'phone',
                'Secondary Payer Phone' => 'phone',
                'Total Wound Size' => 'number',
                'ICD-10 Codes' => 'multiselect',
            ],
            'Advanced_Health' => [
                'OK to Contact Patient?' => 'checkbox',
                'Is patient in SNF?' => 'checkbox',
                'Global Period Status' => 'checkbox',
                'Prior Auth Required' => 'checkbox',
                'Place of Service' => 'select',
                'Type of Plan (Primary)' => 'select',
                'Type of Plan (Secondary)' => 'select',
                'Provider Network Status (Primary)' => 'select',
                'Provider Network Status (Secondary)' => 'select',
                'Date of Birth' => 'date',
                'Subscriber DOB (Primary)' => 'date',
                'Subscriber DOB (Secondary)' => 'date',
                'Date of Procedure' => 'date',
                'Date' => 'date',
                'Phone' => 'phone',
                'Fax' => 'phone',
                'Wound Size(s)' => 'text',
                'Application CPT(s)' => 'multiselect',
                'ICD-10 Diagnosis Code(s)' => 'multiselect',
            ],
            // Add more manufacturers as needed
        ];

        return $fieldTypeDefinitions[$manufacturerKey] ?? [];
    }
}
