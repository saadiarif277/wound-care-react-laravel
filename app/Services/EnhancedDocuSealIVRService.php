<?php

namespace App\Services;

use App\Constants\DocuSealFields;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Docuseal\DocusealSubmission;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\FhirService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

/**
 * Enhanced DocuSeal IVR Service implementing the blueprint's normalized field schema
 * 
 * This service provides:
 * - FHIR data integration with DocuSeal forms
 * - Normalized field mapping using canonical DocuSeal field keys
 * - Proper PDF field population with pre-filled values
 * - Integration with existing QuickRequest handlers
 */
class EnhancedDocuSealIVRService
{
    public function __construct(
        protected DocuSealService $docuSealService,
        protected FhirService $fhirService,
        protected PatientHandler $patientHandler,
        protected ClinicalHandler $clinicalHandler,
        protected ProviderHandler $providerHandler
    ) {}

    /**
     * Create a comprehensive DocuSeal submission with FHIR data integration
     */
    public function createEnhancedIVRSubmission(
        ProductRequest $productRequest,
        array $formData = [],
        ?PatientManufacturerIVREpisode $episode = null
    ): array {
        try {
            Log::info('🚀 Starting enhanced IVR submission creation', [
                'product_request_id' => $productRequest->id,
                'episode_id' => $episode?->id,
                'form_data_keys' => array_keys($formData)
            ]);

            // Step 1: Get comprehensive FHIR data
            $fhirData = $this->extractFhirData($productRequest, $episode);

            // Step 2: Merge with provided form data
            $completeData = array_merge($fhirData, $formData);

            // Step 3: Get the appropriate DocuSeal template
            $template = $this->getTemplateForRequest($productRequest);
            if (!$template) {
                throw new \Exception('No DocuSeal template found for this product request');
            }

            // Step 4: Apply normalized field mapping using the blueprint's canonical schema
            $normalizedFields = $this->applyNormalizedFieldMapping($completeData, $productRequest);

            // Step 5: Map to DocuSeal format with proper field structure
            $docuSealFields = $this->mapToDocuSealFormat($normalizedFields, $template);

            // Step 6: Create the submission with pre-filled fields
            $submissionData = [
                'template_id' => (int) $template->docuseal_template_id,
                'send_email' => false,
                'order' => 'preserved',
                'submitters' => [
                    [
                        'email' => Auth::user()->email ?? config('docuseal.account_email', 'provider@mscwoundcare.com'),
                        'role' => $this->getTemplateRole($template),
                        'fields' => $docuSealFields
                    ]
                ]
            ];

            // Step 7: Add external ID for tracking
            if ($episode) {
                $submissionData['external_id'] = "episode_{$episode->id}";
            } elseif ($productRequest) {
                $submissionData['external_id'] = "request_{$productRequest->id}";
            }

            Log::info('📤 Creating DocuSeal submission', [
                'template_id' => $template->docuseal_template_id,
                'fields_count' => count($docuSealFields),
                'external_id' => $submissionData['external_id'] ?? null,
                'sample_fields' => array_slice($docuSealFields, 0, 3)
            ]);

            // Step 8: Make the API call
            $response = $this->createDocuSealSubmission($submissionData);

            // Step 9: Store submission data
            $this->storeSubmissionData($productRequest, $episode, $response, $normalizedFields);

            return [
                'success' => true,
                'submission_id' => $response['submission_id'],
                'slug' => $response['slug'] ?? null,
                'embed_url' => $response['embed_url'] ?? null,
                'template_id' => $template->docuseal_template_id,
                'fields_mapped' => count($docuSealFields),
                'fhir_data_used' => count($fhirData)
            ];

        } catch (\Exception $e) {
            Log::error('❌ Enhanced IVR submission creation failed', [
                'product_request_id' => $productRequest->id,
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
     * Extract comprehensive FHIR data for the request
     */
    protected function extractFhirData(ProductRequest $productRequest, ?PatientManufacturerIVREpisode $episode): array
    {
        $fhirData = [];

        try {
            // Get patient data from FHIR
            if ($productRequest->patient_fhir_id) {
                $patient = $productRequest->getPatientAttribute();
                if ($patient) {
                    $fhirData = array_merge($fhirData, $this->mapPatientFhirData($patient));
                }
            }

            // Get provider data
            if ($productRequest->provider) {
                $fhirData = array_merge($fhirData, $this->mapProviderData($productRequest->provider));
            }

            // Get facility data
            if ($productRequest->facility) {
                $fhirData = array_merge($fhirData, $this->mapFacilityData($productRequest->facility));
            }

            // Get clinical data
            if ($productRequest->clinical_summary) {
                $fhirData = array_merge($fhirData, $this->mapClinicalData($productRequest->clinical_summary));
            }

            // Get product data
            $products = $productRequest->products;
            if ($products->isNotEmpty()) {
                $fhirData = array_merge($fhirData, $this->mapProductData($products->first(), $productRequest));
            }

            // Get episode data if available
            if ($episode) {
                $fhirData = array_merge($fhirData, $this->mapEpisodeData($episode));
            }

            Log::info('✅ FHIR data extraction completed', [
                'fhir_fields_extracted' => count($fhirData),
                'patient_data' => !empty($patient),
                'provider_data' => !empty($productRequest->provider),
                'facility_data' => !empty($productRequest->facility),
                'clinical_data' => !empty($productRequest->clinical_summary)
            ]);

        } catch (\Exception $e) {
            Log::warning('⚠️ FHIR data extraction partial failure', [
                'error' => $e->getMessage(),
                'product_request_id' => $productRequest->id
            ]);
        }

        return $fhirData;
    }

    /**
     * Map FHIR patient data to normalized format
     */
    protected function mapPatientFhirData(array $patient): array
    {
        $mapped = [];

        // Extract name
        if (!empty($patient['name'])) {
            $name = $patient['name'][0];
            $mapped['patient_first_name'] = $name['given'][0] ?? '';
            $mapped['patient_last_name'] = $name['family'] ?? '';
            $mapped['patient_name'] = trim(($mapped['patient_first_name'] ?? '') . ' ' . ($mapped['patient_last_name'] ?? ''));
        }

        // Extract demographics
        $mapped['patient_dob'] = $patient['birthDate'] ?? '';
        $mapped['patient_gender'] = $patient['gender'] ?? '';

        // Extract contact info
        if (!empty($patient['telecom'])) {
            foreach ($patient['telecom'] as $contact) {
                if ($contact['system'] === 'phone') {
                    $mapped['patient_phone_home'] = $contact['value'];
                } elseif ($contact['system'] === 'email') {
                    $mapped['patient_email'] = $contact['value'];
                }
            }
        }

        // Extract address
        if (!empty($patient['address'])) {
            $address = $patient['address'][0];
            $mapped['patient_address_line1'] = $address['line'][0] ?? '';
            $mapped['patient_address_line2'] = $address['line'][1] ?? '';
            $mapped['patient_city'] = $address['city'] ?? '';
            $mapped['patient_state'] = $address['state'] ?? '';
            $mapped['patient_zip'] = $address['postalCode'] ?? '';
            
            // Combine for full address
            $addressParts = array_filter([
                $mapped['patient_address_line1'],
                $mapped['patient_address_line2'],
                $mapped['patient_city'],
                $mapped['patient_state'],
                $mapped['patient_zip']
            ]);
            $mapped['patient_address'] = implode(', ', $addressParts);
        }

        return $mapped;
    }

    /**
     * Map provider data to normalized format
     */
    protected function mapProviderData($provider): array
    {
        return [
            'provider_name' => trim(($provider->first_name ?? '') . ' ' . ($provider->last_name ?? '')),
            'provider_email' => $provider->email ?? '',
            'provider_npi' => $provider->npi ?? '',
            'provider_credentials' => $provider->credentials ?? '',
        ];
    }

    /**
     * Map facility data to normalized format
     */
    protected function mapFacilityData($facility): array
    {
        return [
            'facility_name' => $facility->name ?? '',
            'facility_npi' => $facility->npi ?? '',
            'facility_contact_phone' => $facility->phone ?? '',
            'facility_contact_email' => $facility->email ?? '',
            'facility_address' => $facility->address ?? '',
        ];
    }

    /**
     * Map clinical data to normalized format
     */
    protected function mapClinicalData($clinicalSummary): array
    {
        if (!is_array($clinicalSummary)) {
            return [];
        }

        return [
            'icd10_primary' => $clinicalSummary['primary_diagnosis'] ?? '',
            'icd10_secondary' => $clinicalSummary['secondary_diagnosis'] ?? '',
            'wound_location' => $clinicalSummary['wound_location'] ?? '',
            'wound_size_cm2' => $clinicalSummary['wound_size'] ?? '',
        ];
    }

    /**
     * Map product data to normalized format
     */
    protected function mapProductData($product, ProductRequest $productRequest): array
    {
        return [
            'product_code' => $product->q_code ?? $product->sku ?? '',
            'product_size' => $product->pivot->size ?? '',
            'place_of_service' => $productRequest->place_of_service ?? '',
            'anticipated_application_date' => $productRequest->expected_service_date ? 
                $productRequest->expected_service_date->format('Y-m-d') : '',
        ];
    }

    /**
     * Map episode data to normalized format
     */
    protected function mapEpisodeData(PatientManufacturerIVREpisode $episode): array
    {
        return [
            'episode_id' => $episode->id,
            'manufacturer_id' => $episode->manufacturer_id,
        ];
    }

    /**
     * Apply normalized field mapping using the blueprint's canonical schema
     */
    protected function applyNormalizedFieldMapping(array $data, ProductRequest $productRequest): array
    {
        $normalized = [];

        // Apply the canonical field mapping from the blueprint
        foreach (DocuSealFields::getAllFields() as $canonicalField) {
            $value = $productRequest->getValue($canonicalField);
            
            // If not found in the model, check the provided data
            if ($value === null) {
                $value = $this->extractValueFromData($data, $canonicalField);
            }

            if ($value !== null) {
                $normalized[$canonicalField] = $this->formatFieldValue($value, $canonicalField);
            }
        }

        Log::info('🗂️ Normalized field mapping completed', [
            'total_canonical_fields' => count(DocuSealFields::getAllFields()),
            'fields_mapped' => count($normalized),
            'mapping_coverage' => round((count($normalized) / count(DocuSealFields::getAllFields())) * 100, 1) . '%'
        ]);

        return $normalized;
    }

    /**
     * Extract value from data using various field name variations
     */
    protected function extractValueFromData(array $data, string $canonicalField): mixed
    {
        // Direct match
        if (isset($data[$canonicalField])) {
            return $data[$canonicalField];
        }

        // Field mapping variations
        $variations = $this->getFieldVariations($canonicalField);
        
        foreach ($variations as $variation) {
            if (isset($data[$variation])) {
                return $data[$variation];
            }
        }

        return null;
    }

    /**
     * Get field name variations for mapping
     */
    protected function getFieldVariations(string $canonicalField): array
    {
        $variations = [];

        // Add common variations
        $variations[] = $canonicalField;
        $variations[] = str_replace('_', '', $canonicalField);
        $variations[] = lcfirst(str_replace('_', '', ucwords($canonicalField, '_')));
        
        // Add specific mappings based on the canonical field
        $specificMappings = [
            DocuSealFields::PATIENT_NAME => ['patient_first_name', 'patient_last_name', 'full_name'],
            DocuSealFields::PRIMARY_INS_NAME => ['primary_insurance_name', 'payer_name_submitted', 'insurance_name'],
            DocuSealFields::ANTICIPATED_APPLICATION_DATE => ['expected_service_date', 'service_date'],
            DocuSealFields::PLACE_OF_SERVICE => ['pos', 'place_of_service_code'],
        ];

        if (isset($specificMappings[$canonicalField])) {
            $variations = array_merge($variations, $specificMappings[$canonicalField]);
        }

        return array_unique($variations);
    }

    /**
     * Format field value according to its type
     */
    protected function formatFieldValue(mixed $value, string $canonicalField): mixed
    {
        $fieldType = DocuSealFields::getFieldType($canonicalField);

        switch ($fieldType) {
            case 'date':
                if ($value && !empty($value)) {
                    try {
                        return \Carbon\Carbon::parse($value)->format('Y-m-d');
                    } catch (\Exception $e) {
                        return $value;
                    }
                }
                break;

            case 'tel':
                if ($value) {
                    // Clean and format phone number
                    $cleaned = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($cleaned) === 10) {
                        return sprintf('(%s) %s-%s',
                            substr($cleaned, 0, 3),
                            substr($cleaned, 3, 3),
                            substr($cleaned, 6, 4)
                        );
                    }
                }
                break;

            case 'checkbox':
                return $value ? 'Yes' : 'No';

            case 'number':
                return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    /**
     * Map normalized fields to DocuSeal format with proper field structure
     */
    protected function mapToDocuSealFormat(array $normalizedFields, DocusealTemplate $template): array
    {
        $docuSealFields = [];
        $templateMappings = $template->field_mappings ?? [];

        foreach ($templateMappings as $docuSealFieldName => $mapping) {
            // Handle different mapping formats
            $canonicalField = null;
            $readonly = false;

            if (is_string($mapping)) {
                $canonicalField = $mapping;
            } elseif (is_array($mapping)) {
                $canonicalField = $mapping['canonical_field'] ?? $mapping['system_field'] ?? $mapping['local_field'] ?? null;
                $readonly = $mapping['readonly'] ?? false;
            }

            if ($canonicalField && isset($normalizedFields[$canonicalField])) {
                $value = $normalizedFields[$canonicalField];

                // Format according to DocuSeal API requirements
                $fieldData = [
                    'name' => $docuSealFieldName,
                    'default_value' => $value,
                    'readonly' => $readonly
                ];

                // Handle special field types
                $fieldType = DocuSealFields::getFieldType($canonicalField);
                if ($fieldType === 'file' && $value) {
                    // For file fields, value should be base64 encoded or a URL
                    $fieldData['default_value'] = $this->handleFileField($value);
                } elseif ($fieldType === 'signature' && $value) {
                    // For signature fields, use text or image URL
                    $fieldData['default_value'] = $this->handleSignatureField($value);
                }

                $docuSealFields[] = $fieldData;
            }
        }

        Log::info('📋 DocuSeal field mapping completed', [
            'template_mappings' => count($templateMappings),
            'fields_mapped' => count($docuSealFields),
            'template_id' => $template->docuseal_template_id
        ]);

        return $docuSealFields;
    }

    /**
     * Handle file field mapping (base64 or URL)
     */
    protected function handleFileField($value): string
    {
        // If it's already a URL, return as-is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // If it's a file path, convert to URL or base64
        // This would need to be implemented based on your file storage system
        return $value;
    }

    /**
     * Handle signature field mapping
     */
    protected function handleSignatureField($value): string
    {
        // For text-based signatures
        if (is_string($value)) {
            return $value;
        }

        // For image signatures, return URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return $value;
    }

    /**
     * Get the appropriate DocuSeal template for the request
     */
    protected function getTemplateForRequest(ProductRequest $productRequest): ?DocusealTemplate
    {
        // Get manufacturer from the first product
        $product = $productRequest->products()->first();
        if (!$product || !$product->manufacturer_id) {
            return null;
        }

        // Find active IVR template for this manufacturer
        return DocusealTemplate::where('manufacturer_id', $product->manufacturer_id)
            ->where('document_type', 'IVR')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get template role for submission
     */
    protected function getTemplateRole(DocusealTemplate $template): string
    {
        // Use template's default role or fallback
        return $template->default_role ?? 'First Party';
    }

    /**
     * Create DocuSeal submission via API
     */
    protected function createDocuSealSubmission(array $submissionData): array
    {
        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

        $response = Http::withHeaders([
            'X-Auth-Token' => $apiKey,
            'Content-Type' => 'application/json'
        ])->timeout(30)->post("{$apiUrl}/submissions", $submissionData);

        if (!$response->successful()) {
            $error = "DocuSeal API error ({$response->status()}): " . $response->body();
            Log::error('❌ DocuSeal API call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'template_id' => $submissionData['template_id']
            ]);
            throw new \Exception($error);
        }

        $responseData = $response->json();

        // Handle different response formats
        if (is_array($responseData) && !empty($responseData)) {
            $firstSubmitter = $responseData[0];
            return [
                'submission_id' => $firstSubmitter['submission_id'] ?? $firstSubmitter['id'],
                'slug' => $firstSubmitter['slug'] ?? null,
                'embed_url' => $firstSubmitter['embed_url'] ?? null,
                'status' => $firstSubmitter['status'] ?? 'pending'
            ];
        }

        return [
            'submission_id' => $responseData['id'] ?? null,
            'slug' => $responseData['slug'] ?? null,
            'embed_url' => $responseData['embed_url'] ?? null,
            'status' => $responseData['status'] ?? 'pending'
        ];
    }

    /**
     * Store submission data for tracking
     */
    protected function storeSubmissionData(
        ProductRequest $productRequest,
        ?PatientManufacturerIVREpisode $episode,
        array $response,
        array $normalizedFields
    ): void {
        try {
            // Update product request
            $productRequest->update([
                'docuseal_submission_id' => $response['submission_id'],
                'ivr_sent_at' => now(),
                'order_status' => 'ivr_sent'
            ]);

            // Update episode if available
            if ($episode) {
                $episode->update([
                    'docuseal_submission_id' => $response['submission_id'],
                    'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                    'metadata' => array_merge($episode->metadata ?? [], [
                        'normalized_fields' => $normalizedFields,
                        'submission_created_at' => now()->toISOString()
                    ])
                ]);
            }

            // Create DocusealSubmission record for tracking
            DocusealSubmission::create([
                'docuseal_submission_id' => $response['submission_id'],
                'order_id' => $productRequest->id,
                'episode_id' => $episode?->id,
                'status' => $response['status'],
                'embed_url' => $response['embed_url'],
                'response_data' => $normalizedFields,
                'created_by' => Auth::id()
            ]);

        } catch (\Exception $e) {
            Log::error('⚠️ Failed to store submission data', [
                'error' => $e->getMessage(),
                'submission_id' => $response['submission_id']
            ]);
        }
    }
}
