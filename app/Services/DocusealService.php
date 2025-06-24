<?php

namespace App\Services;

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
        }
    }

    /**
     * Map fields using template's stored field mappings
     */
    public function mapFieldsUsingTemplate(array $data, \App\Models\Docuseal\DocusealTemplate $template): array
    {
        $fieldMappings = $template->field_mappings ?? [];
        $mappedFields = [];

        foreach ($fieldMappings as $docusealFieldName => $mapping) {
            // Get the system field path (this maps to QuickRequest data structure)
            $systemFieldPath = $mapping['system_field'] ?? null;
            
            if ($systemFieldPath) {
                $value = $this->getNestedValue($data, $systemFieldPath);
                if ($value !== null) {
                    $mappedFields[$docusealFieldName] = $this->transformValue($value, $mapping);
                }
            }
        }

        Log::info('Mapped fields for DocuSeal submission', [
            'template_id' => $template->id,
            'docuseal_template_id' => $template->docuseal_template_id,
            'total_mappings' => count($fieldMappings),
            'fields_mapped' => count($mappedFields),
            'field_names' => array_keys($mappedFields),
            'manufacturer' => $template->manufacturer?->name
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
                    'role' => 'Signer',
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
                    'role' => 'Signer',
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

            return [];
        }
    }

    /**
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
     * Generate JWT token for DocuSeal builder
     */
    public function generateBuilderToken(string $templateId, array $submitterData): string
    {
        try {
            // Generate JWT token locally using the API key as the secret
            // Following DocuSeal's React SaaS guide exactly
            $payload = [
                'user_email' => config('docuseal.account_email', 'limitless@mscwoundcare.com'), // Admin email that owns the API key
                'integration_email' => $submitterData['email'], // SaaS user's email
                'external_id' => $submitterData['external_id'] ?? null,
                'name' => $submitterData['name'] ?? 'IVR Form',
                'template_id' => $templateId,
                // 'document_urls' => [], // Empty array if using template_id
            ];

            // Add pre-filled fields if provided
            if (!empty($submitterData['fields'])) {
                $payload['fields'] = $submitterData['fields'];
            }

            // Generate JWT token using Firebase JWT library
            $jwt = \Firebase\JWT\JWT::encode($payload, $this->apiKey, 'HS256');
            
            Log::info('DocuSeal builder JWT token generated successfully', [
                'template_id' => $templateId,
                'integration_email' => $submitterData['email'],
                'external_id' => $submitterData['external_id'] ?? null
            ]);

            return $jwt;
        } catch (\Exception $e) {
            Log::error('DocuSeal Builder JWT Generation Failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId
            ]);
            
            throw new \Exception('Failed to generate DocuSeal builder token: ' . $e->getMessage());
        }
    }

    /**
     * Fetch template from DocuSeal API and save to database
     */
    protected function fetchTemplateFromApi(Manufacturer $manufacturer, string $documentType = 'IVR'): ?\App\Models\Docuseal\DocusealTemplate
    {
        try {
            Log::info('Fetching templates from DocuSeal API for manufacturer', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType
            ]);

            // Fetch all templates from API
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/templates");

            if (!$response->successful()) {
                Log::error('Failed to fetch templates from DocuSeal API', [
                    'error' => $response->body(),
                    'status' => $response->status()
                ]);
                return null;
            }

            $templates = $response->json();
            
            if (!is_array($templates)) {
                Log::error('Invalid response format from DocuSeal API');
                return null;
            }

            // Search for matching template
            $manufacturerNameLower = strtolower($manufacturer->name);
            $manufacturerPatterns = [
                'acz distribution' => ['acz'],
                'biowound' => ['biowound'],
                'integra' => ['integra'],
                'kerecis' => ['kerecis'],
                'mimedx' => ['mimedx'],
                'organogenesis' => ['organogenesis'],
                'mtf biologics' => ['mtf'],
                'stimlabs' => ['stimlabs'],
                'sanara medtech' => ['sanara'],
                'skye biologics' => ['skye']
            ];

            $patterns = $manufacturerPatterns[$manufacturerNameLower] ?? [str_replace(' ', '', $manufacturerNameLower)];
            
            foreach ($templates as $template) {
                $templateName = strtolower($template['name'] ?? '');
                
                // Check if template matches manufacturer and document type
                $matchesManufacturer = false;
                foreach ($patterns as $pattern) {
                    if (str_contains($templateName, $pattern)) {
                        $matchesManufacturer = true;
                        break;
                    }
                }
                
                $matchesType = false;
                if ($documentType === 'IVR' && (str_contains($templateName, 'ivr') || str_contains($templateName, 'authorization'))) {
                    $matchesType = true;
                } elseif ($documentType === 'OrderForm' && str_contains($templateName, 'order')) {
                    $matchesType = true;
                } elseif ($documentType === 'OnboardingForm' && str_contains($templateName, 'onboard')) {
                    $matchesType = true;
                }
                
                if ($matchesManufacturer && $matchesType) {
                    // Fetch detailed template info
                    $detailResponse = Http::withHeaders([
                        'X-Auth-Token' => $this->apiKey,
                    ])->get("{$this->apiUrl}/templates/{$template['id']}");
                    
                    if ($detailResponse->successful()) {
                        $detailedTemplate = $detailResponse->json();
                        
                        // Extract field mappings
                        $fieldMappings = $this->extractFieldMappingsFromApi($detailedTemplate);
                        
                        // Create template in database
                        $dbTemplate = \App\Models\Docuseal\DocusealTemplate::create([
                            'template_name' => $template['name'],
                            'docuseal_template_id' => $template['id'],
                            'manufacturer_id' => $manufacturer->id,
                            'document_type' => $documentType,
                            'is_default' => false,
                            'field_mappings' => $fieldMappings,
                            'is_active' => true,
                            'extraction_metadata' => [
                                'fetched_from_api' => true,
                                'fetched_at' => now()->toISOString(),
                                'total_fields' => count($fieldMappings)
                            ],
                            'field_discovery_status' => 'completed',
                            'last_extracted_at' => now()
                        ]);
                        
                        Log::info('Successfully fetched and saved template from API', [
                            'template_id' => $dbTemplate->id,
                            'docuseal_id' => $template['id'],
                            'manufacturer' => $manufacturer->name
                        ]);
                        
                        return $dbTemplate;
                    }
                }
            }
            
            Log::warning('No matching template found in DocuSeal API', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType,
                'total_templates_checked' => count($templates)
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Error fetching template from DocuSeal API', [
                'error' => $e->getMessage(),
                'manufacturer' => $manufacturer->name
            ]);
            return null;
        }
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
        ];
    }

    /**
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
                    'role' => 'Signer',
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
            ]);

            return [
                'success' => true,
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
            ];
        }
    }

    /**
     * Test DocuSeal API connectivity and authentication
     */
    public function testConnection(): array
    {
        try {
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
}
