<?php

namespace App\Services;

<<<<<<< HEAD
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\FhirService;

class DocuSealService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('docuseal.api_key');
        $this->apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

        // Debug logging for configuration validation
        Log::info('DocuSeal Configuration Loaded', [
            'api_key_present' => !empty($this->apiKey),
            'api_key_length' => strlen($this->apiKey ?? ''),
            'api_url' => $this->apiUrl,
            'config_api_key' => config('docuseal.api_key') ? 'SET' : 'MISSING',
            'config_api_url' => config('docuseal.api_url') ?? 'MISSING',
        ]);

        if (empty($this->apiKey)) {
            Log::error('DocuSeal API Key not configured', [
                'config_check' => [
                    'docuseal.api_key' => config('docuseal.api_key'),
                    'env_DOCUSEAL_API_KEY' => env('DOCUSEAL_API_KEY'),
                ]
            ]);
        }
    }

    /**
     * Create a submission from QuickRequest data
     */
    public function createIVRSubmission(array $quickRequestData, PatientManufacturerIVREpisode $episode)
    {
        // Get manufacturer and find the IVR template
        $manufacturer = $episode->manufacturer()->first();

        if (!$manufacturer) {
            throw new \Exception('Manufacturer not found');
        }

        // Get the IVR template from database
        $template = $manufacturer->ivrTemplate();

        if (!$template) {
            Log::warning('No active IVR template found in database, attempting API fallback', [
                'manufacturer_id' => $manufacturer->id,
                'manufacturer_name' => $manufacturer->name,
                'episode_id' => $episode->id,
            ]);

            // Try to fetch from API as fallback
            $template = $this->fetchTemplateFromApi($manufacturer, 'IVR');

            if (!$template) {
                throw new \Exception("No active IVR template found for manufacturer: {$manufacturer->name}");
            }
        }

        // Map universal fields to DocuSeal format using template field mappings
        $mappedFields = $this->mapFieldsUsingTemplate($quickRequestData, $template);

        $submissionData = [
            'template_id' => $template->docuseal_template_id,
            'send_email' => false, // We'll embed it instead
            'submitters' => [[
                'role' => 'Provider',
                'email' => Auth::user()->email,
                'fields' => $mappedFields
            ]]
        ];

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/submissions", $submissionData);

            if ($response->successful()) {
                $data = $response->json();

                // Update episode with DocuSeal info
                $episode->update([
                    'docuseal_submission_id' => $data['id'],
                    'docuseal_submission_url' => $data['submitters'][0]['embed_url'] ?? null,
                    'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                    'metadata' => array_merge($episode->metadata ?? [], [
                        'docuseal_template_id' => $template->docuseal_template_id,
                        'template_name' => $template->name,
                    ])
                ]);

                return [
                    'success' => true,
                    'submission_id' => $data['id'],
                    'embed_url' => $data['submitters'][0]['embed_url'] ?? null
                ];
            }

            throw new \Exception('DocuSeal API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('DocuSeal submission creation failed', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
=======
use App\Models\Episode;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\UnifiedFieldMappingService;
use App\Services\AI\AzureFoundryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DocuSealService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct(
        private UnifiedFieldMappingService $fieldMappingService
    ) {
        $this->apiKey = config('services.docuseal.api_key');
        $this->apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
    }

    /**
     * Create or update a submission for an episode
     */
    public function createOrUpdateSubmission(
        int $episodeId,
        string $manufacturerName,
        array $additionalData = []
    ): array {
        try {
            // Get mapped data using unified service
            $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                $episodeId,
                $manufacturerName,
                $additionalData
            );

            if (!$mappingResult['validation']['valid']) {
                throw new \Exception('Field mapping validation failed: ' . 
                    implode(', ', $mappingResult['validation']['errors']));
            }

            // Get or create IVR episode record
            $ivrEpisode = $this->getOrCreateIvrEpisode($episodeId, $mappingResult);

            // Check if we need to create a new submission or update existing
            if ($ivrEpisode->docuseal_submission_id) {
                // Update existing submission
                $response = $this->updateSubmission(
                    $ivrEpisode->docuseal_submission_id,
                    $mappingResult['data'],
                    $mappingResult['manufacturer']['template_id']
                );
            } else {
                // Create new submission
                $response = $this->createSubmission(
                    $mappingResult['manufacturer']['template_id'],
                    $mappingResult['data'],
                    $episodeId,
                    $mappingResult['manufacturer']
                );

                // Update IVR episode with submission ID
                $ivrEpisode->update([
                    'docuseal_submission_id' => $response['id'],
                    'docuseal_status' => 'pending',
                    'field_mapping_completeness' => $mappingResult['completeness']['percentage'],
                    'mapped_fields' => $mappingResult['data'],
                    'validation_warnings' => $mappingResult['validation']['warnings'],
                ]);
            }

            // Log the operation
            Log::info('DocuSeal submission created/updated', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'submission_id' => $response['id'] ?? null,
                'completeness' => $mappingResult['completeness']['percentage'],
            ]);

            return [
                'success' => true,
                'submission' => $response,
                'ivr_episode' => $ivrEpisode,
                'mapping' => $mappingResult,
            ];

        } catch (\Exception $e) {
            Log::error('DocuSeal submission failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
>>>>>>> origin/provider-side
        }
    }

    /**
<<<<<<< HEAD
     * Map fields using template's stored field mappings
     */
    public function mapFieldsUsingTemplate(array $data, \App\Models\Docuseal\DocusealTemplate $template): array
    {
        $fieldMappings = $template->field_mappings ?? [];
        $mappedFields = [];

        // Transform flat data into nested structure
        $transformedData = $this->transformToNestedStructure($data);

        foreach ($fieldMappings as $docusealFieldName => $mapping) {
            // Handle multiple formats:
            // 1. New simplified format: 'CSV Field Name' => 'our.field.path'
            // 2. Legacy string format: 'docuseal_field' => 'system_field'
            // 3. Legacy complex format: 'docuseal_field' => ['system_field' => 'path', 'local_field' => 'path', 'data_type' => 'string']
            
            if (is_string($mapping)) {
                // For new format, the mapping value is our internal field path
                // We'll use it to find data in the transformed structure
                $systemFieldPath = $mapping;
                $mappingArray = ['system_field' => $systemFieldPath];
            } else {
                // Complex format: 'docuseal_field' => ['system_field' => 'path', 'local_field' => 'path', 'data_type' => 'string']
                // Try both system_field and local_field
                $systemFieldPath = $mapping['system_field'] ?? $mapping['local_field'] ?? null;
                $mappingArray = $mapping;
            }

            if ($systemFieldPath) {
                // First try transformed nested data
                $value = $this->getNestedValue($transformedData, $systemFieldPath);

                // If not found, try original flat data
                if ($value === null) {
                    $value = $this->getNestedValue($data, $systemFieldPath);
                }

                // If still not found and it's a dotted path, try without the prefix
                if ($value === null && strpos($systemFieldPath, '.') !== false) {
                    // Remove prefixes like 'patientInfo.', 'providerInfo.', etc.
                    $parts = explode('.', $systemFieldPath);
                    $fieldName = end($parts);

                    // Try common variations
                    $variations = [
                        $fieldName,
                        lcfirst($fieldName),
                        strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $fieldName)),
                    ];

                    foreach ($variations as $variant) {
                        $value = $data[$variant] ?? null;
                        if ($value !== null) {
                            break;
                        }
                    }
                }

                if ($value !== null) {
                    // Format fields as array of objects with name and value
                    $mappedFields[] = [
                        'name' => $docusealFieldName,
                        'value' => $this->transformValue($value, $mappingArray)
                    ];
                }
            }
        }

        Log::info('Mapped fields for DocuSeal submission', [
            'template_id' => $template->id,
            'docuseal_template_id' => $template->docuseal_template_id,
            'total_mappings' => count($fieldMappings),
            'fields_mapped' => count($mappedFields),
            'field_names' => array_map(fn($f) => $f['name'], $mappedFields),
            'manufacturer' => $template->manufacturer?->name,
            'sample_mappings' => array_slice($mappedFields, 0, 5) // Log first 5 mapped fields for debugging
        ]);

        return $mappedFields;
    }

    /**
     * Transform value based on field mapping configuration
     */
    protected function transformValue($value, array $mapping)
    {
        $dataType = $mapping['data_type'] ?? 'string';

        switch ($dataType) {
            case 'date':
                if ($value && !empty($value)) {
                    try {
                        return \Carbon\Carbon::parse($value)->format('Y-m-d');
                    } catch (\Exception $e) {
                        return $value;
                    }
                }
                break;

            case 'phone':
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

            case 'boolean':
                return (bool) $value;

            case 'number':
                return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    /**
     * Get value from nested array using dot notation
     */
    protected function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Transform flat data structure into nested structure expected by field mappings
     */
    protected function transformToNestedStructure(array $data): array
    {
        $nested = [];

        // Patient Information
        $nested['patientInfo'] = [
            'patientName' => trim(($data['patient_first_name'] ?? '') . ' ' . ($data['patient_last_name'] ?? '')),
            'firstName' => $data['patient_first_name'] ?? '',
            'lastName' => $data['patient_last_name'] ?? '',
            'dateOfBirth' => $data['patient_dob'] ?? '',
            'gender' => $data['patient_gender'] ?? '',
            'memberId' => $data['patient_member_id'] ?? '',
            'address' => trim(($data['patient_address_line1'] ?? '') . ' ' . ($data['patient_address_line2'] ?? '')),
            'addressLine1' => $data['patient_address_line1'] ?? '',
            'addressLine2' => $data['patient_address_line2'] ?? '',
            'city' => $data['patient_city'] ?? '',
            'state' => $data['patient_state'] ?? '',
            'zip' => $data['patient_zip'] ?? '',
            'phone' => $data['patient_phone'] ?? '',
            'homePhone' => $data['patient_home_phone'] ?? $data['home_phone'] ?? '',
            'mobile' => $data['patient_mobile'] ?? $data['mobile'] ?? '',
            'email' => $data['patient_email'] ?? '',
        ];

        // Provider Information
        $nested['providerInfo'] = [
            'providerName' => $data['provider_name'] ?? '',
            'providerEmail' => $data['provider_email'] ?? '',
            'providerNpi' => $data['provider_npi'] ?? '',
            'npi' => $data['provider_npi'] ?? '', // Alternative field name
            'credentials' => $data['provider_credentials'] ?? '',
            'specialty' => $data['provider_specialty'] ?? '',
            'ptan' => $data['provider_ptan'] ?? '',
            'taxId' => $data['provider_tax_id'] ?? '',
            'medicareProvider' => $data['medicare_provider'] ?? '',
        ];

        // Facility Information
        $nested['facilityInfo'] = [
            'facilityName' => $data['facility_name'] ?? '',
            'facilityAddress' => $data['facility_address'] ?? '',
            'facilityCity' => $data['facility_city'] ?? '',
            'facilityState' => $data['facility_state'] ?? '',
            'facilityZip' => $data['facility_zip'] ?? '',
            'facilityNpi' => $data['facility_npi'] ?? '',
            'facilityTin' => $data['facility_tin'] ?? '',
            'facilityPtan' => $data['facility_ptan'] ?? '',
            'facilityContact' => $data['facility_contact'] ?? '',
            'facilityContactPhone' => $data['facility_contact_phone'] ?? '',
            'facilityContactFax' => $data['facility_contact_fax'] ?? '',
            'facilityContactEmail' => $data['facility_contact_email'] ?? '',
        ];

        // Request Information
        $nested['requestInfo'] = [
            'salesRepName' => $data['sales_rep'] ?? $data['sales_rep_name'] ?? '',
            'episodeId' => $data['episode_id'] ?? '',
            'orderId' => $data['order_id'] ?? '',
            'serviceDate' => $data['service_date'] ?? $data['expected_service_date'] ?? '',
        ];

        // Clinical Information
        $nested['clinicalInfo'] = [
            'woundType' => $data['wound_type'] ?? '',
            'woundLocation' => $data['wound_location'] ?? '',
            'woundSize' => $data['total_wound_size'] ?? $data['wound_size'] ?? '',
            'woundDimensions' => $data['wound_dimensions'] ?? '',
            'diagnosisCode' => $data['diagnosis_code'] ?? '',
            'primaryDiagnosisCode' => $data['primary_diagnosis_code'] ?? '',
            'secondaryDiagnosisCode' => $data['secondary_diagnosis_code'] ?? '',
        ];
        
        // Wound Information (for more specific mapping)
        $nested['woundInfo'] = [
            'woundType' => $data['wound_type'] ?? '',
            'woundLocation' => $data['wound_location'] ?? '',
            'woundSize' => $data['wound_size'] ?? $data['total_wound_size'] ?? '',
            'primaryDiagnosis' => $data['primary_diagnosis'] ?? $data['primary_diagnosis_code'] ?? '',
            'secondaryDiagnosis' => $data['secondary_diagnosis'] ?? $data['secondary_diagnosis_code'] ?? '',
            'tertiaryDiagnosis' => $data['tertiary_diagnosis'] ?? '',
            'knownConditions' => $data['known_conditions'] ?? '',
        ];

        // Insurance Information
        $nested['insuranceInfo'] = [
            'primaryInsurance' => [
                'primaryInsuranceName' => $data['primary_insurance_name'] ?? $data['payer_name'] ?? '',
                'primaryMemberId' => $data['primary_member_id'] ?? $data['patient_member_id'] ?? '',
                'primaryPolicyNumber' => $data['primary_policy_number'] ?? $data['policy_number'] ?? '',
                'primaryPayerPhone' => $data['primary_payer_phone'] ?? $data['payer_phone'] ?? '',
                'primarySubscriberName' => $data['primary_subscriber_name'] ?? $data['subscriber_name'] ?? '',
                'primaryPlanType' => $data['primary_plan_type'] ?? '',
                'primaryGroupNumber' => $data['primary_group_number'] ?? $data['group_number'] ?? '',
            ],
            'secondaryInsurance' => [
                'secondaryInsuranceName' => $data['secondary_insurance_name'] ?? '',
                'secondaryPolicyNumber' => $data['secondary_policy_number'] ?? '',
                'secondaryPayerPhone' => $data['secondary_payer_phone'] ?? '',
                'secondarySubscriberName' => $data['secondary_subscriber_name'] ?? '',
            ],
            // Keep flat structure for backward compatibility
            'primaryInsuranceName' => $data['primary_insurance_name'] ?? $data['payer_name'] ?? '',
            'primaryMemberId' => $data['primary_member_id'] ?? $data['patient_member_id'] ?? '',
            'primaryPlanType' => $data['primary_plan_type'] ?? '',
        ];

        // Product Information
        $nested['productInfo'] = [
            'productName' => $data['product_name'] ?? '',
            'productCode' => $data['product_code'] ?? '',
            'productManufacturer' => $data['product_manufacturer'] ?? '',
            'productDetails' => $data['product_details_text'] ?? '',
        ];

        // Merge nested structure with original data to preserve unmapped fields
        return array_merge($data, $nested);
    }

    /**
     * Normalize manufacturer name for consistency
     */
    protected function normalizeManufacturerName(string $name): string
    {
        $normalized = str_replace([' ', '-', '_'], '', strtolower($name));

        $mappings = [
            'acz' => 'ACZ',
            'aczdistribution' => 'ACZ',
            'advancedhealth' => 'Advanced Health',
            'advancedsolution' => 'Advanced Health',
            'medlife' => 'MedLife',
            'medlifesolutions' => 'MedLife',
            'biowound' => 'BioWound',
        ];

        return $mappings[$normalized] ?? $name;
    }


    /**
     * Create a submission for QuickRequest IVR
     */
    public function createQuickRequestSubmission(string $templateId, array $submitterData)
    {
        try {
            $submissionData = [
                'template_id' => $templateId,
                'send_email' => $submitterData['send_email'] ?? false,
                'submitters' => [[
                    'role' => 'First Party', // Fixed: Use correct role name for templates
                    'email' => $submitterData['email'],
                    'name' => $submitterData['name'],
                    'fields' => $submitterData['fields'] ?? []
                ]]
            ];

            // Add external ID if provided (for episode linking)
            if (!empty($submitterData['external_id'])) {
                $submissionData['external_id'] = $submitterData['external_id'];
            }

            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/submissions", $submissionData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('DocuSeal submission created successfully', [
                    'submission_id' => $data['id'] ?? null,
                    'template_id' => $templateId,
                    'external_id' => $submitterData['external_id'] ?? null
                ]);

                // Handle both array and object response formats
                $submissionId = null;
                $signingUrl = null;

                if (is_array($data) && !empty($data)) {
                    $firstSubmitter = $data[0];
                    $submissionId = $firstSubmitter['submission_id'] ?? $firstSubmitter['id'];
                    $signingUrl = $firstSubmitter['embed_url'] ?? $firstSubmitter['sign_url'] ?? null;
                } else {
                    $submissionId = $data['id'] ?? null;
                    $signingUrl = $data['embed_url'] ?? $data['sign_url'] ?? null;
                }

                return [
                    'submission_id' => $submissionId,
                    'signing_url' => $signingUrl,
                    'status' => $data['status'] ?? 'pending'
                ];
            }

            // Enhanced error handling for specific HTTP status codes
            if ($response->status() === 401) {
                Log::error('DocuSeal Authentication Failed', [
                    'api_url' => $this->apiUrl,
                    'api_key_length' => strlen($this->apiKey ?? ''),
                    'api_key_prefix' => substr($this->apiKey ?? '', 0, 8) . '...',
                    'request_url' => "{$this->apiUrl}/submissions",
                    'response_body' => $response->body(),
                    'template_id' => $templateId,
                    'config_check' => [
                        'api_key' => config('docuseal.api_key') ? 'configured' : 'missing',
                        'api_url' => config('docuseal.api_url') ?? 'missing',
                    ]
                ]);
                throw new \Exception('DocuSeal API Authentication Failed: Invalid API key or insufficient permissions');
            }

            if ($response->status() === 404) {
                Log::error('DocuSeal Template Not Found', [
                    'template_id' => $templateId,
                    'response_body' => $response->body(),
                ]);
                throw new \Exception("DocuSeal Template not found: {$templateId}");
            }

            throw new \Exception('DocuSeal API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('DocuSeal submission creation failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId
            ]);

            throw $e;
        }
    }

    /**
     * Get submission details
     */
    public function getSubmissionStatus(string $submissionId)
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/submissions/{$submissionId}");

            if ($response->successful()) {
                $data = $response->json();

                // If it's an array of submitters, get the first one's status
                if (is_array($data) && isset($data[0])) {
                    return [
                        'status' => $data[0]['status'] ?? 'pending',
                        'completed_at' => $data[0]['completed_at'] ?? null,
                    ];
                }

                // If it's a submission object
                return [
                    'status' => $data['status'] ?? 'pending',
                    'completed_at' => $data['completed_at'] ?? null,
                ];
            }

            throw new \Exception('Failed to get submission: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Failed to get DocuSeal submission', [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId
            ]);

            throw $e;
        }
    }

    /**
     * Download completed document
     */
    public function downloadDocument(string $submissionId): ?string
    {
        try {
            // Get the submission details first
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/submissions/{$submissionId}");

            if ($response->successful()) {
                $data = $response->json();

                // Check if it's an array of submitters
                if (is_array($data) && isset($data[0]['documents'])) {
                    $documents = $data[0]['documents'];
                    if (!empty($documents)) {
                        return $documents[0]['url'] ?? null;
                    }
                }

                // Check if it's a submission object with documents
                if (isset($data['documents']) && !empty($data['documents'])) {
                    return $data['documents'][0]['url'] ?? null;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get DocuSeal document download URL', [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId
            ]);

            return null;
        }
    }

    /**
     * Generate documents for an order
     */
    public function generateDocumentsForOrder($order): array
    {
        // This is a placeholder implementation
        // You'll need to implement the actual logic based on your order structure

        try {
            $submissions = [];

            // Example: Create a submission for the order
            // You'll need to determine the appropriate template ID and fields
            $templateId = config('docuseal.default_templates.BioWound', '123456');

            $submissionData = [
                'template_id' => $templateId,
                'send_email' => false,
                'submitters' => [[
                    'role' => 'First Party', // Fixed: Use correct role name for templates
                    'email' => $order->provider->email ?? 'provider@example.com',
                    'name' => $order->provider->name ?? 'Provider',
                    'fields' => [
                        'order_number' => $order->order_number,
                        'patient_id' => $order->patient_fhir_id,
                        // Add more fields as needed
                    ]
                ]]
            ];

            $result = $this->createSubmission($submissionData);

            if ($result) {
                // Create a DocusealSubmission record if you have that model
                $submissions[] = (object)[
                    'id' => uniqid(),
                    'docuseal_submission_id' => $result['submission_id'],
                    'status' => 'pending',
                    'signing_url' => $result['submitters'][0]['embed_url'] ?? null,
                ];
            }

            return $submissions;

        } catch (\Exception $e) {
            Log::error('Failed to generate documents for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

=======
     * Create a new DocuSeal submission
     */
    private function createSubmission(string $templateId, array $fields, int $episodeId, array $manufacturerConfig = []): array
    {
        // Use UnifiedFieldMappingService to convert fields to DocuSeal format
        if (!empty($manufacturerConfig['docuseal_field_names'])) {
            $preparedFields = $this->fieldMappingService->convertToDocuSealFields($fields, $manufacturerConfig);
        } else {
            // Fallback to old method if no mapping config
            $preparedFields = $this->prepareFieldsForDocuSeal($fields, $templateId);
        }
        
        Log::info('Creating DocuSeal submission', [
            'template_id' => $templateId,
            'episode_id' => $episodeId,
            'field_count' => count($preparedFields),
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/submissions", [
            'template_id' => $templateId,
            'send_email' => false,
            'metadata' => [
                'episode_id' => $episodeId,
                'created_at' => now()->toIso8601String(),
            ],
            'fields' => $preparedFields,
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = 'Failed to create DocuSeal submission';
            
            // Extract specific error details from DocuSeal response
            if (isset($errorBody['error'])) {
                $errorMessage .= ': ' . $errorBody['error'];
            } elseif (isset($errorBody['message'])) {
                $errorMessage .= ': ' . $errorBody['message'];
            } else {
                $errorMessage .= ': ' . $response->body();
            }
            
            // Log detailed error information
            Log::error('DocuSeal submission creation failed', [
                'template_id' => $templateId,
                'episode_id' => $episodeId,
                'status_code' => $response->status(),
                'error_response' => $errorBody,
                'field_count' => count($preparedFields),
                'sample_fields' => array_slice($preparedFields, 0, 5), // Log first 5 fields for debugging
            ]);
            
            throw new \Exception($errorMessage);
        }

        return $response->json();
    }

    /**
     * Update an existing DocuSeal submission
     */
    private function updateSubmission(string $submissionId, array $fields, ?string $templateId = null): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->put("{$this->apiUrl}/submissions/{$submissionId}", [
            'fields' => $this->prepareFieldsForDocuSeal($fields, $templateId),
            'metadata' => [
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to update DocuSeal submission: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get submission status and details
     */
    public function getSubmission(string $submissionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
        ])->get("{$this->apiUrl}/submissions/{$submissionId}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get DocuSeal submission: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Download signed document
     */
    public function downloadDocument(string $submissionId, string $format = 'pdf'): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
        ])->get("{$this->apiUrl}/submissions/{$submissionId}/documents/combined/{$format}");

        if (!$response->successful()) {
            throw new \Exception('Failed to download document: ' . $response->body());
        }

        return $response->body();
    }

    /**
     * Send submission for signing
     */
    public function sendForSigning(string $submissionId, array $signers): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/submissions/{$submissionId}/send", [
            'submitters' => array_map(function($signer) {
                return [
                    'email' => $signer['email'],
                    'name' => $signer['name'] ?? null,
                    'role' => $signer['role'] ?? 'signer',
                    'message' => $signer['message'] ?? null,
                ];
            }, $signers),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to send submission for signing: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get template fields directly from DocuSeal API
     */
    public function getTemplateFieldsFromAPI(string $templateId): array
    {
        try {
            // Check cache first
            $cacheKey = "docuseal_template_fields_{$templateId}";
            $cachedFields = Cache::get($cacheKey);
            if ($cachedFields !== null) {
                return $cachedFields;
            }

            $response = Http::withHeaders([
                'X-API-TOKEN' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->apiUrl}/templates/{$templateId}");

            if (!$response->successful()) {
                Log::error('Failed to get template from DocuSeal', [
                    'template_id' => $templateId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }
            
            // Debug log the raw response
            Log::info('DocuSeal template API response', [
                'template_id' => $templateId,
                'status' => $response->status(),
                'response_keys' => array_keys($response->json() ?? [])
            ]);

            $template = $response->json();
            $fields = [];
            
            // Debug the template structure
            Log::info('DocuSeal template structure', [
                'has_documents' => isset($template['documents']),
                'has_fields' => isset($template['fields']),
                'has_schema' => isset($template['schema']),
                'template_keys' => array_keys($template ?? [])
            ]);

            // Extract fields from template documents
            if (isset($template['documents']) && is_array($template['documents'])) {
                foreach ($template['documents'] as $document) {
                    if (isset($document['fields']) && is_array($document['fields'])) {
                        foreach ($document['fields'] as $field) {
                            $fieldName = $field['name'] ?? '';
                            if (!empty($fieldName)) {
                                $fields[$fieldName] = [
                                    'id' => $field['uuid'] ?? $fieldName,
                                    'type' => $field['type'] ?? 'text',
                                    'label' => $field['title'] ?? $fieldName,
                                    'required' => $field['required'] ?? false,
                                    'options' => $field['options'] ?? [],
                                    'submitter' => $field['submitter_uuid'] ?? null,
                                    'areas' => $field['areas'] ?? []
                                ];
                            }
                        }
                    }
                }
            }

            // Cache the fields for future use
            Cache::put($cacheKey, $fields, now()->addHours(24));

            Log::info('Retrieved template fields from DocuSeal', [
                'template_id' => $templateId,
                'field_count' => count($fields),
                'field_names' => array_keys($fields)
            ]);

            return $fields;

        } catch (\Exception $e) {
            Log::error('Error getting template fields from DocuSeal', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
>>>>>>> origin/provider-side
            return [];
        }
    }

    /**
<<<<<<< HEAD
     * Create a generic submission for any template
     */
    public function createSubmission(array $submissionData)
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/submissions", $submissionData);

            if ($response->successful()) {
                $data = $response->json();

                // DocuSeal API returns an array of submitters, extract submission info
                if (is_array($data) && !empty($data)) {
                    $firstSubmitter = $data[0];
                    return [
                        'id' => $firstSubmitter['submission_id'] ?? $firstSubmitter['id'],
                        'submission_id' => $firstSubmitter['submission_id'] ?? $firstSubmitter['id'],
                        'submitter_id' => $firstSubmitter['id'],
                        'status' => $firstSubmitter['status'] ?? 'pending',
                        'submitters' => $data
                    ];
                }

                // If it's not an array, return as-is (might be direct submission object)
                return $data;
            }

            throw new \Exception('DocuSeal API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('DocuSeal submission creation failed', [
                'error' => $e->getMessage(),
                'submission_data' => $submissionData
            ]);
            throw $e;
        }
    }

    /**
     * Generate embed URL for a submission
     */
    public function generateEmbedUrl(string $submissionId): string
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/submissions/{$submissionId}/embed_url");

            if ($response->successful()) {
                $data = $response->json();
                return $data['embed_url'] ?? "{$this->apiUrl}/embed/{$submissionId}";
            }

            // Fallback to direct embed URL
            return "{$this->apiUrl}/embed/{$submissionId}";

        } catch (\Exception $e) {
            Log::error('Failed to generate DocuSeal embed URL', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);

            // Return fallback embed URL
            return "{$this->apiUrl}/embed/{$submissionId}";
        }
    }

    /**
     * Generate a builder token for DocuSeal forms
     */
    public function generateBuilderToken(string $templateId, array $submitterData): string
    {
        try {
            // Get template from database to access field mappings
            $template = \App\Models\Docuseal\DocusealTemplate::where('docuseal_template_id', $templateId)
                ->orWhere('id', $templateId)
                ->first();

            // Map the prefill data if template has field mappings
            $prefillData = $submitterData['fields'] ?? [];
            if ($template && !empty($template->field_mappings) && !empty($prefillData)) {
                $mappedFields = $this->mapFieldsUsingTemplate($prefillData, $template);
                $submitterData['fields'] = $mappedFields;
            }

            // Generate a descriptive name for the form
            $formName = 'IVR Form';
            if ($template && $template->manufacturer) {
                $formName = $template->manufacturer->name . ' IVR Form';
            } elseif ($template) {
                $formName = $template->template_name;
            }

            $payload = [
                'template_id' => $templateId,
                'user_email' => config('docuseal.account_email', 'limitless@mscwoundcare.com'),
                'integration_email' => $submitterData['email'],
                'name' => $formName,
                'external_id' => $submitterData['external_id'] ?? 'quickrequest_' . uniqid(),
                'fields' => $submitterData['fields'] ?? [],
                'brand_name' => config('app.name', 'MSC Wound Care'),
                // Disable UI elements we don't want
                'with_fields_list' => false,
                'with_replace_document' => false,
                'with_add_document' => false,
                'with_recipients_button' => false,
                'with_sign_yourself_button' => false,
                'with_send_button' => false,
                'with_title' => false,
                'iat' => time(),
                'exp' => time() + (60 * 60) // 1 hour expiration
            ];

            Log::info('Generating DocuSeal builder token', [
                'template_id' => $templateId,
                'has_fields' => !empty($payload['fields']),
                'field_count' => count($payload['fields'] ?? []),
                'integration_email' => $payload['integration_email'],
                'sample_fields' => array_slice($payload['fields'] ?? [], 0, 5)
            ]);

            // Generate JWT token
            $key = config('docuseal.api_key');
            if (!$key) {
                throw new \Exception('DocuSeal API key not configured');
            }

            // Use Firebase JWT library with HS256
            $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

            Log::info('DocuSeal builder token generated successfully', [
                'token_length' => strlen($jwt)
            ]);

            return $jwt;
        } catch (\Exception $e) {
            Log::error('Failed to generate DocuSeal builder token', [
                'error' => $e->getMessage(),
                'template_id' => $templateId
            ]);
            throw $e;
        }
    }

    /**
     * Fetch template from DocuSeal API and save to database
     */
    public function fetchTemplateFromApi(Manufacturer $manufacturer, string $documentType = 'IVR'): ?\App\Models\Docuseal\DocusealTemplate
    {
        try {
            Log::info('🔍 Fetching templates from DocuSeal API for manufacturer', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType
            ]);

            // Step 1: Try folder-specific search first (most efficient)
            $template = $this->fetchTemplateFromFolder($manufacturer, $documentType);
            if ($template) {
                return $template;
            }

            // Step 2: Fall back to comprehensive pagination search
            return $this->fetchTemplateWithPagination($manufacturer, $documentType);

        } catch (\Exception $e) {
            Log::error('❌ Error fetching template from DocuSeal API', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturer->name,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Fetch template from specific manufacturer folder
     */
    protected function fetchTemplateFromFolder(Manufacturer $manufacturer, string $documentType): ?\App\Models\Docuseal\DocusealTemplate
    {
        // Map manufacturer names to their DocuSeal folder names
        $folderMappings = [
            'ACZ Distribution' => ['ACZ', 'ACZ Distribution'],
            'Advanced Health' => ['Advanced Health', 'Advanced Health (Complete AA)'],
            'MiMedx' => ['MiMedx', 'Amnio Amp-MSC BAA', 'AmnioBand'],
            'BioWound' => ['BioWound', 'BioWound Onboarding', 'Biowound'],
            'BioWerX' => ['BioWerX'],
            'Extremity Care' => ['Extremity Care', 'Extremity Care Onboarding'],
            'MSC' => ['MSC Forms', 'MSC'],
            'Skye Biologics' => ['SKYE', 'SKYE Onboarding', 'Skye Biologics'],
            'Total Ancillary' => ['Total Ancillary Forms', 'Total Ancillary'],
            'Integra' => ['Integra'],
            'Kerecis' => ['Kerecis'],
            'Organogenesis' => ['Organogenesis'],
            'Smith & Nephew' => ['Smith & Nephew'],
            'StimLabs' => ['StimLabs'],
            'Tissue Tech' => ['Tissue Tech'],
            'MTF Biologics' => ['MTF Biologics', 'MTF'],
            'Sanara MedTech' => ['Sanara MedTech', 'Sanara'],
            'MedLife' => ['MedLife', 'Medlife'],
            'AmnioBand' => ['AmnioBand']
        ];

        $possibleFolders = $folderMappings[$manufacturer->name] ?? [$manufacturer->name];

        foreach ($possibleFolders as $folderName) {
            try {
                Log::info("🔍 Searching in folder: {$folderName}");

                // Query templates in specific folder
                $response = Http::withHeaders([
                    'X-Auth-Token' => $this->apiKey,
                ])->timeout(30)->get("{$this->apiUrl}/templates", [
                    'folder_name' => $folderName,
                    'per_page' => 100  // Get more templates per request
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $templates = $responseData['data'] ?? $responseData;

                    $templateCount = count($templates);
                    Log::info("📄 Found {$templateCount} templates in folder: {$folderName}");

                    foreach ($templates as $template) {
                        if ($this->templateMatchesDocumentType($template, $documentType)) {
                            return $this->createTemplateFromApiData($template, $manufacturer, $documentType);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("⚠️ Error searching folder {$folderName}: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Fetch template using comprehensive pagination search
     */
    protected function fetchTemplateWithPagination(Manufacturer $manufacturer, string $documentType): ?\App\Models\Docuseal\DocusealTemplate
    {
        $allTemplates = [];
        $page = 1;
        $perPage = 100;
        $maxPages = 20; // Safety limit
        $hasMore = true;

        Log::info("🔄 Starting paginated template search for {$manufacturer->name}");

        while ($hasMore && $page <= $maxPages) {
            try {
                $response = Http::withHeaders([
                    'X-Auth-Token' => $this->apiKey,
                ])->timeout(30)->get("{$this->apiUrl}/templates", [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    Log::error("❌ API request failed on page {$page}: " . $response->body());
                    break;
                }

                $responseData = $response->json();
                $templates = $responseData['data'] ?? $responseData;

                if (empty($templates)) {
                    Log::info("📄 No more templates found on page {$page}");
                    break;
                }

                Log::info("📄 Page {$page}: Found " . count($templates) . " templates");
                $allTemplates = array_merge($allTemplates, $templates);

                // Check if there are more pages
                $hasMore = count($templates) == $perPage;

                // Also check pagination metadata if available
                if (isset($responseData['pagination'])) {
                    $pagination = $responseData['pagination'];
                    $hasMore = !empty($pagination['next']) || $hasMore;
                }

                $page++;

            } catch (\Exception $e) {
                Log::error("❌ Error on page {$page}: " . $e->getMessage());
                break;
            }
        }

        Log::info("📊 Total templates fetched: " . count($allTemplates));

        // Search through all templates for manufacturer match
        return $this->findMatchingTemplate($allTemplates, $manufacturer, $documentType);
    }

    /**
     * Find matching template from array of templates
     */
    protected function findMatchingTemplate(array $templates, Manufacturer $manufacturer, string $documentType): ?\App\Models\Docuseal\DocusealTemplate
    {
        $manufacturerNameLower = strtolower($manufacturer->name);

        // Enhanced manufacturer patterns
        $manufacturerPatterns = [
            'acz distribution' => ['acz'],
            'advanced health' => ['advanced health', 'advanced', 'complete aa'],
            'biowound' => ['biowound', 'bio wound'],
            'biowerx' => ['biowerx', 'bio werx'],
            'integra' => ['integra'],
            'kerecis' => ['kerecis'],
            'mimedx' => ['mimedx', 'mimx', 'amnio amp', 'amnioband'],
            'organogenesis' => ['organogenesis', 'organo'],
            'mtf biologics' => ['mtf', 'mtf biologics'],
            'stimlabs' => ['stimlabs', 'stim labs'],
            'sanara medtech' => ['sanara', 'sanara medtech'],
            'skye biologics' => ['skye', 'skye biologics'],
            'extremity care' => ['extremity', 'extremity care'],
            'msc' => ['msc', 'msc forms'],
            'total ancillary' => ['total ancillary', 'total'],
            'smith & nephew' => ['smith', 'nephew', 'smith & nephew'],
            'tissue tech' => ['tissue tech', 'tissue'],
            'medlife' => ['medlife', 'med life'],
            'amnioband' => ['amnioband', 'amnio band']
        ];

        $patterns = $manufacturerPatterns[$manufacturerNameLower] ?? [
            str_replace(' ', '', $manufacturerNameLower),
            str_replace(' ', ' ', $manufacturerNameLower)
        ];

        foreach ($templates as $template) {
            $templateName = strtolower($template['name'] ?? '');
            $folderName = strtolower($template['folder_name'] ?? '');

            // Check manufacturer match (name or folder)
            $matchesManufacturer = false;
            foreach ($patterns as $pattern) {
                if (str_contains($templateName, $pattern) || str_contains($folderName, $pattern)) {
                    $matchesManufacturer = true;
                    break;
                }
            }

            // Check document type match
            if ($matchesManufacturer && $this->templateMatchesDocumentType($template, $documentType)) {
                $folderDisplay = $template['folder_name'] ?? 'None';
                Log::info("✅ Found matching template: {$template['name']} in folder: {$folderDisplay}");
                return $this->createTemplateFromApiData($template, $manufacturer, $documentType);
            }
        }

        Log::warning("❌ No matching template found for {$manufacturer->name} - {$documentType}");
        return null;
    }

    /**
     * Check if template matches document type
     */
    protected function templateMatchesDocumentType(array $template, string $documentType): bool
    {
        $templateName = strtolower($template['name'] ?? '');

        switch ($documentType) {
            case 'IVR':
                return str_contains($templateName, 'ivr') ||
                       str_contains($templateName, 'authorization') ||
                       str_contains($templateName, 'auth') ||
                       str_contains($templateName, 'prior auth');

            case 'OrderForm':
                return str_contains($templateName, 'order') ||
                       str_contains($templateName, 'request');

            case 'OnboardingForm':
                return str_contains($templateName, 'onboard') ||
                       str_contains($templateName, 'enrollment');

            default:
                return true; // If no specific type, accept any
        }
    }

    /**
     * Create template database record from API data
     */
    protected function createTemplateFromApiData(array $templateData, Manufacturer $manufacturer, string $documentType): \App\Models\Docuseal\DocusealTemplate
    {
        // Fetch detailed template info
        $detailResponse = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->timeout(30)->get("{$this->apiUrl}/templates/{$templateData['id']}");

        $detailedTemplate = $detailResponse->successful() ? $detailResponse->json() : $templateData;

        // Extract field mappings
        $fieldMappings = $this->extractFieldMappingsFromApi($detailedTemplate);

        // Create template in database
        $dbTemplate = \App\Models\Docuseal\DocusealTemplate::create([
            'template_name' => $templateData['name'],
            'docuseal_template_id' => $templateData['id'],
            'manufacturer_id' => $manufacturer->id,
            'document_type' => $documentType,
            'is_default' => false,
            'field_mappings' => $fieldMappings,
            'is_active' => true,
            'extraction_metadata' => [
                'fetched_from_api' => true,
                'fetched_at' => now()->toISOString(),
                'total_fields' => count($fieldMappings),
                'folder_name' => $templateData['folder_name'] ?? null,
                'api_created_at' => $templateData['created_at'] ?? null,
                'api_updated_at' => $templateData['updated_at'] ?? null
            ],
            'field_discovery_status' => 'completed',
            'last_extracted_at' => now()
        ]);

        Log::info('✅ Successfully created template from API', [
            'template_id' => $dbTemplate->id,
            'docuseal_id' => $templateData['id'],
            'manufacturer' => $manufacturer->name,
            'folder' => $templateData['folder_name'] ?? 'None',
            'fields_count' => count($fieldMappings)
        ]);

        return $dbTemplate;
    }

    /**
     * Extract field mappings from API template structure
     */
    protected function extractFieldMappingsFromApi(array $template): array
    {
        $fieldMappings = [];

        // Check various possible field structures in the API response
        $fields = $template['fields'] ?? $template['schema'] ?? $template['submitters'][0]['fields'] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['field_name'] ?? '';
            if (empty($fieldName)) continue;

            // Map common DocuSeal fields to system fields
            $systemField = $this->mapDocuSealFieldToSystem($fieldName);

            $fieldMappings[$fieldName] = [
                'docuseal_field_name' => $fieldName,
                'field_type' => $field['type'] ?? 'text',
                'required' => $field['required'] ?? false,
                'local_field' => $systemField['local_field'],
                'system_field' => $systemField['system_field'],
                'data_type' => $systemField['data_type'],
                'validation_rules' => $field['required'] ? ['required'] : [],
                'default_value' => $field['default_value'] ?? null,
                'extracted_at' => now()->toISOString()
            ];
        }

        return $fieldMappings;
    }

    /**
     * Map DocuSeal field names to system fields
     */
    protected function mapDocuSealFieldToSystem(string $fieldName): array
    {
        $fieldNameLower = strtolower(str_replace([' ', '_', '-'], '', $fieldName));

        // Common field mappings
        $mappings = [
            'patientname' => ['local_field' => 'patientInfo.patientName', 'system_field' => 'patient_name', 'data_type' => 'string'],
            'patientdob' => ['local_field' => 'patientInfo.patientDOB', 'system_field' => 'patient_dob', 'data_type' => 'date'],
            'patientphone' => ['local_field' => 'patientInfo.patientPhone', 'system_field' => 'patient_phone', 'data_type' => 'phone'],
            'patientaddress' => ['local_field' => 'patientInfo.patientAddressLine1', 'system_field' => 'patient_address', 'data_type' => 'string'],
            'primaryinsurance' => ['local_field' => 'insuranceInfo.primaryInsurance.primaryInsuranceName', 'system_field' => 'primary_insurance', 'data_type' => 'string'],
            'policynumber' => ['local_field' => 'insuranceInfo.primaryInsurance.primaryPolicyNumber', 'system_field' => 'policy_number', 'data_type' => 'string'],
            'facilityname' => ['local_field' => 'facilityInfo.facilityName', 'system_field' => 'facility_name', 'data_type' => 'string'],
            'providername' => ['local_field' => 'providerInfo.providerName', 'system_field' => 'provider_name', 'data_type' => 'string'],
            'providernpi' => ['local_field' => 'providerInfo.providerNPI', 'system_field' => 'provider_npi', 'data_type' => 'string'],
            'repname' => ['local_field' => 'requestInfo.salesRepName', 'system_field' => 'sales_rep_name', 'data_type' => 'string'],
            'requestdate' => ['local_field' => 'requestInfo.requestDate', 'system_field' => 'request_date', 'data_type' => 'date'],
        ];

        // Check for exact match
        if (isset($mappings[$fieldNameLower])) {
            return $mappings[$fieldNameLower];
        }

        // Check for partial matches
        foreach ($mappings as $key => $mapping) {
            if (str_contains($fieldNameLower, $key)) {
                return $mapping;
            }
        }

        // Default mapping
        return [
            'local_field' => 'customFields.' . $fieldName,
            'system_field' => strtolower(str_replace(' ', '_', $fieldName)),
            'data_type' => 'string'
=======
     * Get template fields for a manufacturer
     */
    public function getTemplateFields(string $manufacturerName): array
    {
        $manufacturer = $this->fieldMappingService->getManufacturerConfig($manufacturerName);
        
        if (!$manufacturer) {
            throw new \Exception("Unknown manufacturer: {$manufacturerName}");
        }

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
        ])->get("{$this->apiUrl}/templates/{$manufacturer['template_id']}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get template: ' . $response->body());
        }

        $template = $response->json();
        
        return [
            'template' => $template,
            'fields' => $template['fields'] ?? [],
            'mapped_count' => count($manufacturer['fields']),
            'manufacturer' => $manufacturer,
        ];
    }

    /**
     * Process webhook callback from DocuSeal
     */
    public function processWebhook(array $payload): array
    {
        $event = $payload['event_type'] ?? null;
        $submissionId = $payload['data']['id'] ?? null;

        if (!$submissionId) {
            throw new \Exception('No submission ID in webhook payload');
        }

        // Find the IVR episode by submission ID
        $ivrEpisode = PatientManufacturerIVREpisode::where('docuseal_submission_id', $submissionId)
            ->first();

        if (!$ivrEpisode) {
            Log::warning('Received webhook for unknown submission', [
                'submission_id' => $submissionId,
                'event' => $event,
            ]);
            return ['status' => 'ignored', 'reason' => 'Unknown submission'];
        }

        // Update status based on event
        switch ($event) {
            case 'submission.completed':
                $ivrEpisode->update([
                    'docuseal_status' => 'completed',
                    'completed_at' => now(),
                    'signed_document_url' => $payload['data']['documents'][0]['url'] ?? null,
                ]);
                break;

            case 'submission.viewed':
                $ivrEpisode->update([
                    'docuseal_status' => 'viewed',
                    'viewed_at' => now(),
                ]);
                break;

            case 'submission.started':
                $ivrEpisode->update([
                    'docuseal_status' => 'in_progress',
                    'started_at' => now(),
                ]);
                break;

            case 'submission.sent':
                $ivrEpisode->update([
                    'docuseal_status' => 'sent',
                    'sent_at' => now(),
                ]);
                break;

            case 'submission.expired':
                $ivrEpisode->update([
                    'docuseal_status' => 'expired',
                    'expired_at' => now(),
                ]);
                break;

            default:
                Log::info('Unhandled DocuSeal webhook event', [
                    'event' => $event,
                    'submission_id' => $submissionId,
                ]);
        }

        return [
            'status' => 'processed',
            'event' => $event,
            'ivr_episode_id' => $ivrEpisode->id,
        ];
    }

    /**
     * Get or create IVR episode record
     */
    private function getOrCreateIvrEpisode(int $episodeId, array $mappingResult): PatientManufacturerIVREpisode
    {
        return PatientManufacturerIVREpisode::firstOrCreate([
            'episode_id' => $episodeId,
            'manufacturer_id' => $mappingResult['manufacturer']['id'],
        ], [
            'manufacturer_name' => $mappingResult['manufacturer']['name'],
            'template_id' => $mappingResult['manufacturer']['template_id'],
            'field_mapping_completeness' => $mappingResult['completeness']['percentage'],
            'required_fields_completeness' => $mappingResult['completeness']['required_percentage'],
            'mapped_fields' => $mappingResult['data'],
            'validation_warnings' => $mappingResult['validation']['warnings'],
            'created_by' => optional(auth())->id(),
        ]);
    }

    /**
     * Prepare fields for DocuSeal API format
     */
    private function prepareFieldsForDocuSeal(array $fields, ?string $templateId = null): array
    {
        $docuSealFields = [];
        $skippedFields = [];

        // Get template field definitions if available
        $templateFields = [];
        if ($templateId) {
            $templateFields = $this->getTemplateFieldsFromAPI($templateId);
            
            Log::info('Template fields retrieved from DocuSeal', [
                'template_id' => $templateId,
                'template_field_count' => count($templateFields),
                'template_field_names' => array_keys($templateFields)
            ]);
        }

        foreach ($fields as $key => $value) {
            // Skip internal fields
            if (str_starts_with($key, '_')) {
                continue;
            }

            // If we have template fields, only include fields that exist in the template
            if (!empty($templateFields) && !isset($templateFields[$key])) {
                $skippedFields[] = $key;
                Log::debug('Skipping field not found in DocuSeal template', [
                    'field_name' => $key,
                    'value' => $value,
                    'template_id' => $templateId
                ]);
                continue;
            }

            // Convert empty values to empty strings for DocuSeal
            if ($value === null) {
                $value = '';
            }

            // Handle array values (like checkboxes)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Handle boolean values
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            // Use field UUID if available from template definition
            $fieldId = $key;
            if (!empty($templateFields[$key]['id'])) {
                $fieldId = $templateFields[$key]['id'];
                Log::debug('Using DocuSeal field UUID', [
                    'field_name' => $key,
                    'field_uuid' => $fieldId
                ]);
            }

            $docuSealFields[] = [
                'name' => $fieldId,
                'default_value' => (string) $value,
            ];
        }

        if (!empty($skippedFields)) {
            Log::warning('Fields skipped due to not existing in DocuSeal template', [
                'template_id' => $templateId,
                'skipped_fields' => $skippedFields,
                'skipped_count' => count($skippedFields)
            ]);
        }

        Log::info('Prepared fields for DocuSeal', [
            'input_count' => count($fields),
            'output_count' => count($docuSealFields),
            'skipped_count' => count($skippedFields),
            'template_id' => $templateId,
            'template_fields_available' => count($templateFields) > 0
        ]);

        return $docuSealFields;
    }

    /**
     * Batch process multiple episodes
     */
    public function batchProcessEpisodes(array $episodeIds, string $manufacturerName): array
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($episodeIds as $episodeId) {
            try {
                $result = $this->createOrUpdateSubmission($episodeId, $manufacturerName);
                $results[$episodeId] = [
                    'success' => true,
                    'submission_id' => $result['submission']['id'],
                    'completeness' => $result['mapping']['completeness']['percentage'],
                ];
                $successful++;
            } catch (\Exception $e) {
                $results[$episodeId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($episodeIds),
                'successful' => $successful,
                'failed' => $failed,
            ],
>>>>>>> origin/provider-side
        ];
    }

    /**
<<<<<<< HEAD
     * Generate PDF document from template with form data and signatures
     */
    public function generatePdf(string $templateId, array $formData, array $signatures = []): array
    {
        try {
            Log::info('Generating PDF with DocuSeal', [
                'template_id' => $templateId,
                'form_fields_count' => count($formData),
                'signatures_count' => count($signatures),
            ]);

            // Create submission data
            $submissionData = [
                'template_id' => $templateId,
                'send_email' => false, // Don't send email, we want to generate PDF directly
                'completed' => true, // Mark as completed to generate PDF immediately
                'submitters' => [[
                    'role' => 'First Party', // Fixed: Use correct role name for templates
                    'name' => $formData['signer_name'] ?? 'System Generated',
                    'email' => $formData['signer_email'] ?? 'system@example.com',
                    'fields' => array_merge($formData, $signatures),
                    'completed' => true,
                ]]
            ];

            // Create the submission
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/submissions", $submissionData);

            if (!$response->successful()) {
                Log::error('Failed to create DocuSeal submission', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'template_id' => $templateId,
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to create submission: ' . $response->body(),
                ];
            }

            $submissionResult = $response->json();
            $submissionId = null;

            // Extract submission ID from response
            if (is_array($submissionResult) && !empty($submissionResult)) {
                $submissionId = $submissionResult[0]['submission_id'] ?? $submissionResult[0]['id'] ?? null;
            } else {
                $submissionId = $submissionResult['id'] ?? null;
            }

            if (!$submissionId) {
                return [
                    'success' => false,
                    'error' => 'Unable to extract submission ID from response',
                ];
            }

            // Wait a moment for document generation
            sleep(2);

            // Get the completed document
            $documentUrl = $this->downloadDocument($submissionId);

            if (!$documentUrl) {
                return [
                    'success' => false,
                    'error' => 'Failed to get document download URL',
                ];
            }

            // Download the PDF content
            $pdfResponse = Http::get($documentUrl);

            if (!$pdfResponse->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to download PDF content',
                ];
            }

            $pdfContent = $pdfResponse->body();

            Log::info('PDF generated successfully', [
                'template_id' => $templateId,
                'submission_id' => $submissionId,
                'pdf_size' => strlen($pdfContent),
=======
     * Get IVR episodes by status
     */
    public function getEpisodesByStatus(string $status, ?string $manufacturerName = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PatientManufacturerIVREpisode::where('docuseal_status', $status);

        if ($manufacturerName) {
            $query->where('manufacturer_name', $manufacturerName);
        }

        return $query->with(['episode', 'episode.patient'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Map fields using AI when available, with fallback to static mappings
     */
    public function mapFieldsWithAI(array $data, $template): array
    {
        try {
            // Try AI mapping first if enabled
            if (config('ai.enabled', false) && config('ai.provider') !== 'mock' && config('azure.ai_foundry.enabled', false)) {
                Log::info('🤖 Attempting AI-powered field mapping', [
                    'template_id' => $template->id,
                    'input_fields' => count($data)
                ]);
                
                try {
                    $azureAI = app(AzureFoundryService::class);
                    
                    // Get template fields from DocuSeal API directly
                    $templateFields = $this->getTemplateFieldsFromAPI($template->docuseal_template_id);
                    
                    // If no fields from API, try the old method
                    if (empty($templateFields)) {
                        Log::warning('No fields from DocuSeal API, trying legacy method');
                        $templateFields = $this->getTemplateFields($template->manufacturer->name ?? 'Unknown');
                    }
                    
                    Log::info('Template fields for AI mapping', [
                        'field_count' => count($templateFields),
                        'field_names' => array_keys($templateFields),
                        'template_id' => $template->docuseal_template_id
                    ]);
                    
                    // Use AI to map the fields
                    Log::info('🤖 Calling Azure AI with template fields', [
                        'source_fields' => array_keys($data),
                        'target_fields' => array_keys($templateFields),
                        'sample_target_fields' => array_slice(array_keys($templateFields), 0, 10)
                    ]);
                    
                    $aiResult = $azureAI->translateFormData(
                        $data,
                        $templateFields,
                        'MSC Wound Care Form Data',
                        "DocuSeal template for {$template->manufacturer->name}",
                        ['preserve_unmapped' => true]
                    );
                    
                    if ($aiResult['success'] && !empty($aiResult['mappings'])) {
                        Log::info('✅ AI field mapping successful', [
                            'mapped_fields' => count($aiResult['mappings']),
                            'confidence' => $aiResult['overall_confidence'] ?? 0,
                            'sample_mappings' => array_slice($aiResult['mappings'], 0, 5, true)
                        ]);
                        
                        // Convert AI result format to DocuSeal format directly
                        $mappedFields = [];
                        foreach ($aiResult['mappings'] as $targetField => $mapping) {
                            if (isset($mapping['value']) && $mapping['value'] !== null && $mapping['value'] !== '') {
                                // Special handling for gender field - split "Male Female" into separate checkboxes
                                if ($targetField === 'Male Female' && isset($mapping['value'])) {
                                    $genderValue = strtolower($mapping['value']);
                                    Log::info('Processing AI-mapped gender field', [
                                        'original_field' => $targetField,
                                        'value' => $mapping['value'],
                                        'normalized' => $genderValue
                                    ]);
                                    
                                    if (strpos($genderValue, 'male') !== false && strpos($genderValue, 'female') === false) {
                                        $mappedFields[] = ['name' => 'Male', 'default_value' => 'true'];
                                        $mappedFields[] = ['name' => 'Female', 'default_value' => 'false'];
                                    } elseif (strpos($genderValue, 'female') !== false) {
                                        $mappedFields[] = ['name' => 'Male', 'default_value' => 'false'];
                                        $mappedFields[] = ['name' => 'Female', 'default_value' => 'true'];
                                    }
                                } else {
                                    $mappedFields[] = [
                                        'name' => $targetField,
                                        'default_value' => (string)$mapping['value']
                                    ];
                                }
                            }
                        }
                        
                        // Special handling for MedLife amnio_amp_size
                        if ($template->manufacturer && in_array($template->manufacturer->name, ['MedLife', 'MedLife Solutions'])) {
                            // Check if amnio_amp_size is in the original data but not mapped
                            $hasMappedAmnioSize = false;
                            foreach ($mappedFields as $field) {
                                if ($field['name'] === 'amnio_amp_size') {
                                    $hasMappedAmnioSize = true;
                                    break;
                                }
                            }
                            
                            if (isset($data['amnio_amp_size']) && !$hasMappedAmnioSize) {
                                $mappedFields[] = [
                                    'name' => 'amnio_amp_size',
                                    'default_value' => (string)$data['amnio_amp_size']
                                ];
                                Log::info('Added MedLife amnio_amp_size field', [
                                    'value' => $data['amnio_amp_size']
                                ]);
                            }
                        }
                        
                        // Return the mapped fields directly in DocuSeal format
                        return $mappedFields;
                    }
                } catch (\Exception $aiException) {
                    Log::warning('⚠️ AI mapping failed, falling back to static mappings', [
                        'error' => $aiException->getMessage(),
                        'error_type' => get_class($aiException),
                        'stack_trace' => $aiException->getTraceAsString(),
                        'ai_enabled' => config('ai.enabled'),
                        'ai_provider' => config('ai.provider'),
                        'azure_enabled' => config('azure.ai_foundry.enabled'),
                        'azure_endpoint' => config('azure.ai_foundry.endpoint'),
                        'azure_deployment' => config('azure.ai_foundry.deployment_name')
                    ]);
                }
            }
            
            // Fallback to static mapping
            return $this->mapFieldsFromArray($data, $template);
            
        } catch (\Exception $e) {
            Log::error('DocuSeal: Error in AI field mapping', [
                'error' => $e->getMessage()
            ]);
            return $this->mapFieldsFromArray($data, $template);
        }
    }

    /**
     * Legacy method to map fields using array data and template
     * @deprecated Use mapFieldsWithAI for better results
     */
    public function mapFieldsFromArray(array $data, $template): array
    {
        try {
            // If template is not found or invalid, return empty array
            if (!$template || !isset($template->field_mappings)) {
                Log::warning('DocuSeal: No template or field mappings found', [
                    'template_id' => $template->id ?? null
                ]);
                return [];
            }

            $mappedFields = [];
            $fieldMappings = $template->field_mappings;

            // If field_mappings is a string, decode it
            if (is_string($fieldMappings)) {
                $fieldMappings = json_decode($fieldMappings, true);
            }

            if (!is_array($fieldMappings)) {
                Log::warning('DocuSeal: Invalid field mappings format', [
                    'template_id' => $template->id,
                    'mappings_type' => gettype($fieldMappings)
                ]);
                return [];
            }

            // Map each field according to the template mappings
            foreach ($fieldMappings as $docusealField => $mappingConfig) {
                // Handle different mapping config formats
                if (is_string($mappingConfig)) {
                    // Simple string mapping
                    if (isset($data[$mappingConfig])) {
                        $mappedFields[$docusealField] = $data[$mappingConfig];
                    }
                } elseif (is_array($mappingConfig)) {
                    // Complex mapping with source field
                    $sourceField = $mappingConfig['source'] ?? $mappingConfig['field'] ?? null;
                    if ($sourceField && isset($data[$sourceField])) {
                        $value = $data[$sourceField];
                        
                        // Apply any transformations if specified
                        if (isset($mappingConfig['transform'])) {
                            $value = $this->applyTransformation($value, $mappingConfig['transform']);
                        }
                        
                        $mappedFields[$docusealField] = $value;
                    }
                }
            }

            Log::info('DocuSeal: Field mapping completed', [
                'input_fields' => count($data),
                'mapped_fields' => count($mappedFields),
                'template_id' => $template->id
            ]);

            // Convert to DocuSeal format
            $docuSealFields = [];
            foreach ($mappedFields as $fieldName => $fieldValue) {
                $docuSealFields[] = [
                    'name' => $fieldName,
                    'default_value' => (string)$fieldValue
                ];
            }

            return $docuSealFields;

        } catch (\Exception $e) {
            Log::error('DocuSeal: Error mapping fields', [
                'error' => $e->getMessage(),
                'template_id' => $template->id ?? null
            ]);
            return [];
        }
    }

    /**
     * Apply transformation to a field value
     */
    private function applyTransformation($value, $transform)
    {
        switch ($transform) {
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'date':
                return Carbon::parse($value)->format('Y-m-d');
            case 'phone':
                return preg_replace('/[^0-9]/', '', $value);
            default:
                return $value;
        }
    }

    /**
     * Map fields using template for a specific manufacturer
     */
    public function mapFieldsUsingTemplate(int $episodeId, string $manufacturerName, array $additionalData = []): array
    {
        try {
            // Use the unified field mapping service to get mapped data
            $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                $episodeId,
                $manufacturerName,
                $additionalData
            );

            // Get or create IVR episode record to store mapping data
            $ivrEpisode = $this->getOrCreateIvrEpisode($episodeId, $mappingResult);
            
            // Update with latest mapping data
            $ivrEpisode->update([
                'field_mapping_completeness' => $mappingResult['completeness']['percentage'],
                'required_fields_completeness' => $mappingResult['completeness']['required_percentage'],
                'mapped_fields' => $mappingResult['data'],
                'validation_warnings' => $mappingResult['validation']['warnings'],
>>>>>>> origin/provider-side
            ]);

            return [
                'success' => true,
<<<<<<< HEAD
                'pdf_content' => $pdfContent,
                'submission_id' => $submissionId,
                'document_id' => $submissionId, // For compatibility
                'document_url' => $documentUrl,
            ];

        } catch (\Exception $e) {
            Log::error('DocuSeal PDF generation failed', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
=======
                'data' => $mappingResult['data'],
                'completeness' => $mappingResult['completeness'],
                'validation' => $mappingResult['validation'],
                'manufacturer' => $mappingResult['manufacturer'],
                'ivr_episode' => $ivrEpisode,
            ];
        } catch (\Exception $e) {
            Log::error('Field mapping failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate analytics for IVR completions
     */
    public function generateAnalytics(?string $manufacturerName = null, ?array $dateRange = null): array
    {
        $query = PatientManufacturerIVREpisode::query();

        if ($manufacturerName) {
            $query->where('manufacturer_name', $manufacturerName);
        }

        if ($dateRange) {
            $query->whereBetween('created_at', $dateRange);
        }

        $total = $query->count();
        $completed = (clone $query)->where('docuseal_status', 'completed')->count();
        $inProgress = (clone $query)->whereIn('docuseal_status', ['in_progress', 'viewed', 'sent'])->count();
        $pending = (clone $query)->where('docuseal_status', 'pending')->count();
        $expired = (clone $query)->where('docuseal_status', 'expired')->count();

        // Average completeness
        $avgCompleteness = (clone $query)->avg('field_mapping_completeness') ?? 0;
        $avgRequiredCompleteness = (clone $query)->avg('required_fields_completeness') ?? 0;

        // Time to completion
        $completedRecords = (clone $query)
            ->where('docuseal_status', 'completed')
            ->whereNotNull('completed_at')
            ->get();

        $avgTimeToComplete = null;
        if ($completedRecords->count() > 0) {
            $totalMinutes = $completedRecords->sum(function($record) {
                return $record->created_at->diffInMinutes($record->completed_at);
            });
            $avgTimeToComplete = round($totalMinutes / $completedRecords->count());
        }

        return [
            'total_submissions' => $total,
            'status_breakdown' => [
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => $pending,
                'expired' => $expired,
            ],
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'average_field_completeness' => round($avgCompleteness, 2),
            'average_required_field_completeness' => round($avgRequiredCompleteness, 2),
            'average_time_to_complete_minutes' => $avgTimeToComplete,
            'manufacturer' => $manufacturerName,
            'date_range' => $dateRange,
        ];
    }

    /**
     * Create IVR submission (alias for createOrUpdateSubmission for legacy compatibility)
     */
    public function createIVRSubmission(array $data, Episode $episode): array
    {
        Log::info('Creating IVR submission', [
            'episode_id' => $episode->id,
            'manufacturer_id' => $data['manufacturer_id'] ?? null
        ]);

        try {
            // Extract manufacturer name from data
            $manufacturerName = $data['manufacturer_name'] ?? 'Unknown';
            
            // Use the main method with episode ID and manufacturer
            $result = $this->createOrUpdateSubmission($episode->id, $manufacturerName, $data);
            
            return [
                'success' => true,
                'embed_url' => $result['submission']['embed_url'] ?? null,
                'submission_id' => $result['submission']['id'] ?? null,
                'pdf_url' => $result['submission']['pdf_url'] ?? null
            ];
            
        } catch (\Exception $e) {
            Log::error('IVR submission creation failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
>>>>>>> origin/provider-side
            ];
        }
    }

    /**
<<<<<<< HEAD
     * Test DocuSeal API connectivity and authentication
=======
     * Test DocuSeal API connection
>>>>>>> origin/provider-side
     */
    public function testConnection(): array
    {
        try {
<<<<<<< HEAD
            Log::info('Testing DocuSeal API connection', [
                'api_url' => $this->apiUrl,
                'api_key_length' => strlen($this->apiKey ?? ''),
                'api_key_prefix' => substr($this->apiKey ?? '', 0, 8) . '...',
            ]);

            // Test with a simple API call (list templates)
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/templates");

            $result = [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'api_key_length' => strlen($this->apiKey ?? ''),
                'api_url' => $this->apiUrl,
                'config_check' => [
                    'api_key' => config('docuseal.api_key') ? 'configured' : 'missing',
                    'api_url' => config('docuseal.api_url') ?? 'missing',
                ],
            ];

            if ($response->successful()) {
                $data = $response->json();
                $result['message'] = 'Connection successful';
                $result['templates_count'] = is_array($data) ? count($data) : 0;
                $result['sample_templates'] = is_array($data) ? array_slice($data, 0, 3) : [];
            } else {
                $result['message'] = 'Connection failed';
                $result['error'] = $response->body();

                if ($response->status() === 401) {
                    $result['error_type'] = 'authentication';
                    $result['recommendation'] = 'Check API key validity and permissions';
                }
            }

            Log::info('DocuSeal connection test result', $result);
            return $result;

        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
                'api_key_length' => strlen($this->apiKey ?? ''),
                'api_url' => $this->apiUrl,
            ];

            Log::error('DocuSeal connection test exception', $result);
            return $result;
        }
    }

    protected function getTemplateForManufacturer($manufacturerId, $templateType)
    {
        $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('document_type', $templateType)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $template;
        }

        // Fallback to API if not in DB
        $manufacturer = Manufacturer::find($manufacturerId);
        if ($manufacturer) {
            return $this->fetchTemplateFromApi($manufacturer, $templateType);
        }

        return null;
    }

    protected function preparePrefillData($episode, $template)
    {
        // This method should extract data from the episode and format it
        // for use with mapFieldsUsingTemplate.
        $data = [];
        if ($episode) {
            $episodeData = is_object($episode) ? get_object_vars($episode) : $episode;
            $data = array_merge($data, $episodeData);
        }

        return is_object($episode) ? (array) $episode : $episode;
    }

    public function generateIVR($episodeId, $templateType = 'IVR'): array
    {
        try {
            $episode = DB::table('patient_manufacturer_ivr_episodes as e')
                ->leftJoin('manufacturers as m', 'e.manufacturer_id', '=', 'm.id')
                ->select('e.*', 'm.name as manufacturer_name')
                ->where('e.id', $episodeId)
                ->first();

            if (!$episode) {
                throw new \Exception('Episode not found');
            }

            // Get the template
            $template = $this->getTemplateForManufacturer($episode->manufacturer_id, $templateType);

            if (!$template) {
                throw new \Exception('No DocuSeal template found for manufacturer');
            }

            // Prepare the prefill data
            $prefillData = $this->preparePrefillData($episode, $template);

            // NEW: Enrich with FHIR data
            $prefillData = $this->enrichWithFHIRData($prefillData, $episodeId);

            // Continue with existing DocuSeal submission...
            $submissionData = [
                'template_id' => $template->docuseal_template_id,
                'send_email' => true,
                'order_index' => 1,
                'submitters' => [
                    [
                        'email' => $episode->patient_email ?? 'patient@example.com',
                        'role' => 'Patient',
                        'fields' => $this->mapFieldsUsingTemplate($prefillData, $template)
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/submissions", $submissionData);

            if ($response->successful()) {
                $data = $response->json();
                $submissionId = $data['id'] ?? ($data[0]['id'] ?? null);

                if ($submissionId) {
                    return ['submission_id' => $submissionId];
                }
            }

            throw new \Exception('DocuSeal API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('IVR generation failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function enrichWithFHIRData(array $prefillData, string $episodeId): array
    {
        try {
            $episode = DB::table('patient_manufacturer_ivr_episodes')
                ->where('id', $episodeId)
                ->first();

            if (!$episode || !$episode->azure_order_checklist_fhir_id) {
                Log::info('No FHIR data for episode', ['episode_id' => $episodeId]);
                return $prefillData;
            }

            // Fetch the DocumentReference which contains our checklist data
            $fhirService = app(FhirService::class);
            $bundle = $fhirService->search('DocumentReference', ['_id' => $episode->azure_order_checklist_fhir_id]);

            if (!isset($bundle['entry'][0]['resource'])) {
                Log::warning('DocumentReference not found in FHIR for episode', ['episode_id' => $episodeId]);
                return $prefillData;
            }
            $documentRef = $bundle['entry'][0]['resource'];

            // The checklist data is base64 encoded in the attachment
            if (isset($documentRef['content'][0]['attachment']['data'])) {
                $checklistJson = base64_decode($documentRef['content'][0]['attachment']['data']);
                $checklistData = json_decode($checklistJson, true);

                // Map FHIR checklist data to DocuSeal fields
                $fhirMappings = $this->mapFHIRToDocuSeal($checklistData);

                // Merge with existing data (FHIR data takes precedence)
                return array_merge($prefillData, $fhirMappings);
            }
        } catch (\Exception $e) {
            Log::error('Failed to enrich with FHIR data', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);
        }

        return $prefillData;
    }

    public function mapFHIRToDocuSeal(array $checklistData): array
    {
        $mappings = [];

        // Patient Information
        if (isset($checklistData['patientName'])) {
            $mappings['PATIENT NAME'] = $checklistData['patientName'];
            $mappings['Patient Name'] = $checklistData['patientName'];
            $mappings['PATIENT_NAME'] = $checklistData['patientName'];
        }

        if (isset($checklistData['dateOfBirth'])) {
            $mappings['DATE OF BIRTH'] = date('m/d/Y', strtotime($checklistData['dateOfBirth']));
            $mappings['DOB'] = date('m/d/Y', strtotime($checklistData['dateOfBirth']));
        }

        // Diagnosis mappings
        if (isset($checklistData['hasDiabetes']) && $checklistData['hasDiabetes']) {
            $mappings['DIABETES'] = 'X';
            $mappings['Has Diabetes'] = 'Yes';

            if (isset($checklistData['diabetesType'])) {
                $mappings['DIABETES TYPE ' . $checklistData['diabetesType']] = 'X';
                $mappings['Diabetes Type'] = 'Type ' . $checklistData['diabetesType'];
            }
        }

        // Wound measurements
        if (isset($checklistData['length'])) {
            $mappings['WOUND LENGTH'] = $checklistData['length'];
            $mappings['Length (cm)'] = $checklistData['length'];
            $mappings['WOUND_LENGTH_CM'] = $checklistData['length'];
        }

        if (isset($checklistData['width'])) {
            $mappings['WOUND WIDTH'] = $checklistData['width'];
            $mappings['Width (cm)'] = $checklistData['width'];
            $mappings['WOUND_WIDTH_CM'] = $checklistData['width'];
        }

        if (isset($checklistData['woundDepth'])) {
            $mappings['WOUND DEPTH'] = $checklistData['woundDepth'];
            $mappings['Depth (cm)'] = $checklistData['woundDepth'];
        }

        // Location and laterality
        if (isset($checklistData['location'])) {
            $mappings['LOCATION ' . strtoupper($checklistData['location'])] = 'X';
            $mappings['Location'] = ucfirst($checklistData['location']);
        }

        if (isset($checklistData['ulcerLocation'])) {
            $mappings['ULCER LOCATION'] = $checklistData['ulcerLocation'];
            $mappings['Wound Location'] = $checklistData['ulcerLocation'];
        }

        // Lab values
        if (isset($checklistData['hba1cResult'])) {
            $mappings['HBA1C'] = $checklistData['hba1cResult'];
            $mappings['HbA1c Result'] = $checklistData['hba1cResult'] . '%';
            $mappings['A1C_VALUE'] = $checklistData['hba1cResult'];
        }

        // Conservative treatment
        if (isset($checklistData['debridementPerformed']) && $checklistData['debridementPerformed']) {
            $mappings['DEBRIDEMENT'] = 'X';
            $mappings['Debridement Performed'] = 'Yes';
        }

        if (isset($checklistData['moistDressingsApplied']) && $checklistData['moistDressingsApplied']) {
            $mappings['MOIST DRESSINGS'] = 'X';
            $mappings['Moist Dressings Applied'] = 'Yes';
        }

        // Add procedure date if available
        if (isset($checklistData['dateOfProcedure'])) {
            $mappings['PROCEDURE DATE'] = date('m/d/Y', strtotime($checklistData['dateOfProcedure']));
            $mappings['Date of Service'] = date('m/d/Y', strtotime($checklistData['dateOfProcedure']));
        }

        return $mappings;
    }

    /**
     * Process a completed DocuSeal submission
     */
    public function processCompletedSubmission(string $submissionId, string $manufacturerId, ?string $productCode = null): array
    {
        try {
            Log::info('Processing completed DocuSeal submission', [
                'submission_id' => $submissionId,
                'manufacturer_id' => $manufacturerId,
                'product_code' => $productCode
            ]);

            // Get submission details from DocuSeal
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/submissions/{$submissionId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch submission details: ' . $response->body());
            }

            $submission = $response->json();

            // Extract submitter data
            $submitter = $submission['submitters'][0] ?? null;
            if (!$submitter) {
                throw new \Exception('No submitter data found in submission');
            }

            // Get the completed document URL
            $documentUrl = $this->downloadDocument($submissionId);

            // Store submission in database
            $docusealSubmission = \App\Models\DocuSeal\DocuSealSubmission::create([
                'submission_id' => $submissionId,
                'template_id' => $submission['template_id'] ?? null,
                'status' => 'completed',
                'submitter_email' => $submitter['email'] ?? null,
                'submitter_name' => $submitter['name'] ?? null,
                'completed_at' => now(),
                'document_url' => $documentUrl,
                'submission_data' => $submission,
                'manufacturer_id' => $manufacturerId,
                'product_code' => $productCode
            ]);

            // Process the form data for the quick request
            $formData = [];
            if (isset($submitter['values'])) {
                foreach ($submitter['values'] as $field) {
                    $formData[$field['field_name']] = $field['value'];
                }
            }

            Log::info('DocuSeal submission processed successfully', [
                'submission_id' => $submissionId,
                'document_url' => $documentUrl,
                'field_count' => count($formData)
            ]);

            return [
                'submission_id' => $submissionId,
                'document_url' => $documentUrl,
                'form_data' => $formData,
                'submission_record_id' => $docusealSubmission->id
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process DocuSeal submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Comprehensive template discovery with analytics
     * Returns detailed information about all templates organized by folders and manufacturers
     */
    public function discoverAllTemplates(): array
    {
        try {
            Log::info('🔍 Starting comprehensive template discovery...');

            $allTemplates = [];
            $seenTemplateIds = []; // Track duplicates
            $page = 1;
            $perPage = 50; // Reduced from 100 to be more conservative
            $maxPages = 10; // Reduced from 50 to be more realistic
            $hasMore = true;

                        // Fetch all templates with cursor-based pagination
            $cursor = null;
            $requestCount = 0;

            while ($hasMore && $requestCount < $maxPages) {
                $requestCount++;
                Log::info("📄 Fetching batch {$requestCount}" . ($cursor ? " (cursor: {$cursor})" : " (first page)") . "...");

                $params = ['per_page' => $perPage];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }

                $response = Http::withHeaders([
                    'X-Auth-Token' => $this->apiKey,
                ])->timeout(30)->get("{$this->apiUrl}/templates", $params);

                if (!$response->successful()) {
                    Log::error("❌ Template discovery failed on batch {$requestCount}: " . $response->body());
                    break;
                }

                $responseData = $response->json();
                $templates = $responseData['data'] ?? $responseData;

                if (empty($templates) || !is_array($templates)) {
                    Log::info("📄 No more templates found on batch {$requestCount}");
                    break;
                }

                Log::info("📄 Batch {$requestCount}: Found " . count($templates) . " templates");

                // Filter out duplicates and add to collection
                $newTemplatesCount = 0;
                foreach ($templates as $template) {
                    $templateId = $template['id'] ?? null;
                    if ($templateId && !in_array($templateId, $seenTemplateIds)) {
                        $seenTemplateIds[] = $templateId;
                        $allTemplates[] = $template;
                        $newTemplatesCount++;
                    }
                }

                Log::info("📄 Batch {$requestCount}: Added {$newTemplatesCount} new templates (skipped " . (count($templates) - $newTemplatesCount) . " duplicates)");

                // Check pagination metadata for next cursor
                $hasMore = false;
                if (isset($responseData['pagination'])) {
                    $pagination = $responseData['pagination'];
                    $cursor = $pagination['next'] ?? null;
                    $hasMore = !empty($cursor);
                    Log::info("📄 Pagination info - Next cursor: " . ($cursor ?? 'none'));
                } else {
                    // Fallback: if we got a full page, there might be more
                    $hasMore = count($templates) == $perPage;
                    Log::info("📄 No pagination metadata, using count heuristic");
                }

                // Safety check - if we're getting no new templates, stop
                if ($newTemplatesCount === 0) {
                    Log::info("📄 No new templates found on batch {$requestCount}, stopping pagination");
                    break;
                }
            }

            Log::info("📊 Template discovery complete: " . count($allTemplates) . " unique templates found across {$requestCount} batches");

            // Analyze templates
            return $this->analyzeDiscoveredTemplates($allTemplates);

        } catch (\Exception $e) {
            Log::error('❌ Template discovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'templates' => [],
                'analytics' => []
            ];
        }
    }

    /**
     * Analyze discovered templates and provide detailed analytics
     */
    protected function analyzeDiscoveredTemplates(array $templates): array
    {
        $analytics = [
            'total_templates' => count($templates),
            'by_folder' => [],
            'by_manufacturer' => [],
            'by_document_type' => [],
            'unmapped_templates' => [],
            'potential_matches' => []
        ];

        // Folder mappings for manufacturer identification
        $folderMappings = [
            'ACZ Distribution' => ['ACZ', 'ACZ Distribution'],
            'Advanced Health' => ['Advanced Health', 'Advanced Health (Complete AA)'],
            'MiMedx' => ['MiMedx', 'Amnio Amp-MSC BAA', 'AmnioBand'],
            'BioWound' => ['BioWound', 'BioWound Onboarding', 'Biowound'],
            'BioWerX' => ['BioWerX'],
            'Extremity Care' => ['Extremity Care', 'Extremity Care Onboarding'],
            'MSC' => ['MSC Forms', 'MSC'],
            'Skye Biologics' => ['SKYE', 'SKYE Onboarding', 'Skye Biologics'],
            'Total Ancillary' => ['Total Ancillary Forms', 'Total Ancillary'],
            'Integra' => ['Integra'],
            'Kerecis' => ['Kerecis'],
            'Organogenesis' => ['Organogenesis'],
            'Smith & Nephew' => ['Smith & Nephew'],
            'StimLabs' => ['StimLabs'],
            'Tissue Tech' => ['Tissue Tech'],
            'MTF Biologics' => ['MTF Biologics', 'MTF'],
            'Sanara MedTech' => ['Sanara MedTech', 'Sanara'],
            'MedLife' => ['MedLife', 'Medlife'],
            'AmnioBand' => ['AmnioBand']
        ];

        foreach ($templates as $template) {
            $folderName = $template['folder_name'] ?? 'No Folder';
            $templateName = $template['name'] ?? 'Unknown';
            $templateId = $template['id'] ?? 'unknown';

            // Analyze by folder
            if (!isset($analytics['by_folder'][$folderName])) {
                $analytics['by_folder'][$folderName] = [
                    'count' => 0,
                    'templates' => []
                ];
            }
            $analytics['by_folder'][$folderName]['count']++;
            $analytics['by_folder'][$folderName]['templates'][] = [
                'id' => $templateId,
                'name' => $templateName,
                'created_at' => $template['created_at'] ?? null
            ];

            // Determine manufacturer
            $detectedManufacturer = null;
            foreach ($folderMappings as $manufacturerName => $folderPatterns) {
                foreach ($folderPatterns as $pattern) {
                    if (stripos($folderName, $pattern) !== false || stripos($templateName, $pattern) !== false) {
                        $detectedManufacturer = $manufacturerName;
                        break 2;
                    }
                }
            }

            // Analyze by manufacturer
            if ($detectedManufacturer) {
                if (!isset($analytics['by_manufacturer'][$detectedManufacturer])) {
                    $analytics['by_manufacturer'][$detectedManufacturer] = [
                        'count' => 0,
                        'templates' => [],
                        'folders' => []
                    ];
                }
                $analytics['by_manufacturer'][$detectedManufacturer]['count']++;
                $analytics['by_manufacturer'][$detectedManufacturer]['templates'][] = [
                    'id' => $templateId,
                    'name' => $templateName,
                    'folder' => $folderName
                ];
                if (!in_array($folderName, $analytics['by_manufacturer'][$detectedManufacturer]['folders'])) {
                    $analytics['by_manufacturer'][$detectedManufacturer]['folders'][] = $folderName;
                }
            } else {
                $analytics['unmapped_templates'][] = [
                    'id' => $templateId,
                    'name' => $templateName,
                    'folder' => $folderName
                ];
            }

            // Determine document type
            $documentType = 'Unknown';
            $templateNameLower = strtolower($templateName);
            if (str_contains($templateNameLower, 'ivr') || str_contains($templateNameLower, 'authorization')) {
                $documentType = 'IVR';
            } elseif (str_contains($templateNameLower, 'onboard')) {
                $documentType = 'OnboardingForm';
            } elseif (str_contains($templateNameLower, 'order')) {
                $documentType = 'OrderForm';
            }

            // Analyze by document type
            if (!isset($analytics['by_document_type'][$documentType])) {
                $analytics['by_document_type'][$documentType] = [
                    'count' => 0,
                    'templates' => []
                ];
            }
            $analytics['by_document_type'][$documentType]['count']++;
            $analytics['by_document_type'][$documentType]['templates'][] = [
                'id' => $templateId,
                'name' => $templateName,
                'manufacturer' => $detectedManufacturer,
                'folder' => $folderName
            ];
        }

        return [
            'success' => true,
            'templates' => $templates,
            'analytics' => $analytics
        ];
    }
}
=======
            $response = Http::withHeaders([
                'X-API-TOKEN' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->apiUrl . '/templates');

            if ($response->successful()) {
                $templates = $response->json();
                return [
                    'success' => true,
                    'status' => 'connected',
                    'api_url' => $this->apiUrl,
                    'template_count' => count($templates ?? [])
                ];
            } else {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'error' => 'API returned status: ' . $response->status(),
                    'api_url' => $this->apiUrl
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'api_url' => $this->apiUrl
            ];
        }
    }

    /**
     * Map FHIR checklist data to DocuSeal fields
     * This method transforms FHIR-formatted data into DocuSeal field format
     */
    public function mapFHIRToDocuSeal(array $checklistData): array
    {
        try {
            // Use the AI service if available for intelligent mapping
            $aiService = app(AzureFoundryService::class);
            if (method_exists($aiService, 'mapFhirToDocuSeal')) {
                // The AI service expects 4 parameters: fhirData, docuSealFields, manufacturerName, context
                $docuSealFields = []; // Empty array since we don't have specific fields
                $manufacturerName = ''; // Unknown manufacturer
                $context = []; // No additional context
                
                return $aiService->mapFhirToDocuSeal($checklistData, $docuSealFields, $manufacturerName, $context);
            }
        } catch (\Exception $e) {
            Log::warning('AI service not available for FHIR mapping', ['error' => $e->getMessage()]);
        }

        // Fallback to basic mapping
        $mappedData = [];
        
        // Map common FHIR fields to DocuSeal fields
        $fieldMapping = [
            'patient_name' => 'Patient Name',
            'patient_dob' => 'Patient Date of Birth',
            'patient_id' => 'Patient ID',
            'provider_name' => 'Provider Name',
            'provider_npi' => 'Provider NPI',
            'facility_name' => 'Facility Name',
            'facility_npi' => 'Facility NPI',
            'diagnosis_codes' => 'Diagnosis Codes',
            'order_date' => 'Order Date',
            'insurance_name' => 'Insurance Name',
            'insurance_id' => 'Insurance ID',
            'wound_location' => 'Wound Location',
            'wound_type' => 'Wound Type',
            'product_name' => 'Product Name',
            'quantity' => 'Quantity',
        ];

        foreach ($fieldMapping as $fhirKey => $docuSealKey) {
            if (isset($checklistData[$fhirKey])) {
                $mappedData[$docuSealKey] = $checklistData[$fhirKey];
            }
        }

        // Handle nested FHIR structures
        if (isset($checklistData['patient']) && is_array($checklistData['patient'])) {
            $mappedData['Patient Name'] = $checklistData['patient']['name'] ?? '';
            $mappedData['Patient Date of Birth'] = $checklistData['patient']['birthDate'] ?? '';
            $mappedData['Patient ID'] = $checklistData['patient']['identifier'] ?? '';
        }

        if (isset($checklistData['provider']) && is_array($checklistData['provider'])) {
            $mappedData['Provider Name'] = $checklistData['provider']['name'] ?? '';
            $mappedData['Provider NPI'] = $checklistData['provider']['identifier'] ?? '';
        }

        if (isset($checklistData['coverage']) && is_array($checklistData['coverage'])) {
            $mappedData['Insurance Name'] = $checklistData['coverage']['payor'] ?? '';
            $mappedData['Insurance ID'] = $checklistData['coverage']['subscriberId'] ?? '';
        }

        return $mappedData;
    }
}
>>>>>>> origin/provider-side
