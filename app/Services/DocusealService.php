<?php

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
        $manufacturer = Manufacturer::find($episode->manufacturer_id);

        if (!$manufacturer) {
            throw new \Exception('Manufacturer not found');
        }

        // Get the IVR template from database
        $template = $manufacturer->ivrTemplate();

        if (!$template) {
            throw new \Exception("No active IVR template found for manufacturer: {$manufacturer->name}");
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
    protected function mapFieldsUsingTemplate(array $data, \App\Models\Docuseal\DocusealTemplate $template): array
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
            $tokenData = [
                'template_id' => $templateId,
                'user_email' => $submitterData['email'],
                'integration_email' => $submitterData['email'], // Same as user email for our use case
                'name' => $submitterData['name'],
                'external_id' => $submitterData['external_id'] ?? null,
            ];

            // Add pre-filled fields if provided
            if (!empty($submitterData['fields'])) {
                $tokenData['fields'] = $submitterData['fields'];
            }

            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/builder/jwt", $tokenData);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('DocuSeal builder token generated successfully', [
                    'template_id' => $templateId,
                    'user_email' => $submitterData['email'],
                    'external_id' => $submitterData['external_id'] ?? null
                ]);

                return $data['jwt'] ?? $data['token'] ?? '';
            }

            // Enhanced error handling
            if ($response->status() === 401) {
                Log::error('DocuSeal Builder Authentication Failed', [
                    'api_url' => $this->apiUrl,
                    'template_id' => $templateId,
                    'response_body' => $response->body(),
                ]);
                throw new \Exception('DocuSeal API Authentication Failed: Invalid API key');
            }

            if ($response->status() === 404) {
                Log::error('DocuSeal Builder Template Not Found', [
                    'template_id' => $templateId,
                    'response_body' => $response->body(),
                ]);
                throw new \Exception("DocuSeal Template not found: {$templateId}");
            }

            throw new \Exception('DocuSeal builder token generation failed: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('DocuSeal builder token generation failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'user_email' => $submitterData['email'] ?? null
            ]);

            throw $e;
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
}
