<?php

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocuSealService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.docuseal.api_key');
        $this->apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
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
                'email' => auth()->user()->email,
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

        // Get the universal mappings from config as fallback
        $universalMappings = $this->getUniversalMappings();

        foreach ($fieldMappings as $docusealFieldName => $mapping) {
            // Get the local field path
            $localFieldPath = $mapping['local_field'] ?? $mapping['system_field'] ?? null;

            if ($localFieldPath) {
                $value = $this->getNestedValue($data, $localFieldPath);
                if ($value !== null) {
                    $mappedFields[$docusealFieldName] = $value;
                }
            }
        }

        // Also check universal mappings for any missed fields
        $manufacturerName = $this->normalizeManufacturerName($template->manufacturer->name ?? '');
        $fallbackMappings = $universalMappings[$manufacturerName] ?? [];

        foreach ($fallbackMappings as $universalPath => $docusealFieldName) {
            if (!isset($mappedFields[$docusealFieldName])) {
                $value = $this->getNestedValue($data, $universalPath);
                if ($value !== null) {
                    $mappedFields[$docusealFieldName] = $value;
                }
            }
        }

        Log::info('Mapped fields for DocuSeal submission', [
            'template_id' => $template->id,
            'total_mappings' => count($fieldMappings),
            'fields_mapped' => count($mappedFields),
            'field_names' => array_keys($mappedFields)
        ]);

        return $mappedFields;
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
     * Get universal field mappings
     */
    protected function getUniversalMappings(): array
    {
        // This should match your UNIVERSAL_IVR_FORM.md mappings
        return [
            'ACZ' => [
                'requestInfo.salesRepName' => 'REPRESENTATIVE NAME',
                'physicianInfo.physicianName' => 'PHYSICIAN NAME',
                'physicianInfo.physicianNPI' => 'NPI',
                'physicianInfo.physicianTaxID' => 'TAX ID',
                'facilityInfo.facilityName' => 'FACILITY NAME',
                'facilityInfo.facilityAddressLine1' => 'FACILITY ADDRESS',
                'patientInfo.patientName' => 'PATIENT NAME',
                'patientInfo.patientDOB' => 'PATIENT DOB',
                // Add all other mappings from your UNIVERSAL_IVR_FORM.md
            ],
            // Add other manufacturers...
        ];
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
                    'submission_id' => $data['id'],
                    'template_id' => $templateId,
                    'external_id' => $submitterData['external_id'] ?? null
                ]);

                return [
                    'submission_id' => $data['id'],
                    'signing_url' => $data['submitters'][0]['embed_url'] ?? $data['submitters'][0]['sign_url'] ?? null,
                    'status' => $data['status'] ?? 'pending'
                ];
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
     * Get submission details (rename from getSubmission to getSubmissionStatus for consistency)
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
            $templateId = config('services.docuseal.default_template_id', '123456');

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
}
