<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\UnifiedFieldMappingService;
use App\Services\AI\AzureFoundryService;
use App\Services\AI\IntelligentFieldMappingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DocusealService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct(
        private UnifiedFieldMappingService $fieldMappingService,
        private ?IntelligentFieldMappingService $intelligentMapping = null
    ) {
        $this->apiKey = config('services.docuseal.api_key');
        $this->apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

        // Initialize intelligent mapping service if available
        if (!$this->intelligentMapping) {
            try {
                $this->intelligentMapping = app(IntelligentFieldMappingService::class);
            } catch (\Exception $e) {
                Log::warning('Intelligent mapping service not available, using standard mapping', [
                    'error' => $e->getMessage()
                ]);
            }
        }
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

                // Log the submission ID update
                Log::info('Updated IVR episode with docuseal submission ID', [
                    'episode_id' => $episodeId,
                    'ivr_episode_id' => $ivrEpisode->id,
                    'docuseal_submission_id' => $response['id'],
                    'manufacturer' => $manufacturerName
                ]);

                // Update ProductRequest with episode and submission info
                $this->updateProductRequestWithEpisode($episodeId, $ivrEpisode->id, $response['id'], $mappingResult['manufacturer']['template_id']);
            }

            // Log the operation
            Log::info('Docuseal submission created/updated', [
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
            Log::error('Docuseal submission failed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a new Docuseal submission
     */
    private function createSubmission(string $templateId, array $fields, string $episodeId, array $manufacturerConfig = [], string $submitterEmail = 'provider@example.com', string $submitterName = 'Healthcare Provider'): array
    {
        // Log the incoming fields for debugging
        Log::info('Fields received in createSubmission', [
            'field_count' => count($fields),
            'has_patient_name' => isset($fields['patient_name']),
            'has_patient_first_name' => isset($fields['patient_first_name']),
            'has_patient_last_name' => isset($fields['patient_last_name']),
            'sample_fields' => array_slice(array_keys($fields), 0, 10)
        ]);

        // Use UnifiedFieldMappingService to convert fields to Docuseal format
        if (!empty($manufacturerConfig['docuseal_field_names'])) {
            $preparedFields = $this->fieldMappingService->convertToDocusealFields($fields, $manufacturerConfig);
        } else {
            // Fallback to old method if no mapping config
            $preparedFields = $this->prepareFieldsForDocuseal($fields, $templateId);
        }

        // Extract emails from fields if available
        $patientEmail = $fields['patient_email'] ?? null;
        $providerEmail = $fields['provider_email'] ?? $submitterEmail;

        Log::info('Creating Docuseal submission', [
            'template_id' => $templateId,
            'episode_id' => $episodeId,
            'field_count' => count($preparedFields),
            'submitter_email' => $providerEmail,
            'prepared_field_names' => array_map(function($field) { return $field['name'] ?? 'unknown'; }, $preparedFields),
            'sample_prepared_fields' => array_slice($preparedFields, 0, 5)
        ]);

        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/submissions", [
            'template_id' => $templateId,
            'send_email' => false,
            'submitters' => [
                [
                    'email' => $providerEmail, // Use the actual provider email who will sign
                    'role' => 'First Party',
                    'name' => $submitterName,
                    'fields' => $preparedFields, // Pre-fill fields for this submitter
                ]
            ],
            'metadata' => [
                'episode_id' => $episodeId,
                'provider_email' => $providerEmail,
                'patient_email' => $patientEmail,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = 'Failed to create Docuseal submission';

            // Extract specific error details from Docuseal response
            if (isset($errorBody['error'])) {
                $errorMessage .= ': ' . $errorBody['error'];
            } elseif (isset($errorBody['message'])) {
                $errorMessage .= ': ' . $errorBody['message'];
            } else {
                $errorMessage .= ': ' . $response->body();
            }

            // Log detailed error information
            Log::error('Docuseal submission creation failed', [
                'template_id' => $templateId,
                'episode_id' => $episodeId,
                'status_code' => $response->status(),
                'error_response' => $errorBody,
                'field_count' => count($preparedFields),
                'sample_fields' => array_slice($preparedFields, 0, 5), // Log first 5 fields for debugging
            ]);

            throw new \Exception($errorMessage);
        }

        $responseData = $response->json();

        // DocuSeal API returns an array of submitters, we need the first one
        if (is_array($responseData) && !empty($responseData)) {
            $submitter = $responseData[0];

            // Return the submission data in the expected format
            return [
                'id' => $submitter['submission_id'], // This is what the calling code expects
                'submission_id' => $submitter['submission_id'],
                'slug' => $submitter['slug'],
                'submitter_id' => $submitter['id'],
                'embed_src' => $submitter['embed_src'] ?? null,
                'status' => $submitter['status'],
                'submitters' => $responseData, // Keep original data too
            ];
        }

        // Fallback for unexpected response format
        return $responseData;
    }

    /**
     * Update an existing Docuseal submission
     */
    private function updateSubmission(string $submissionId, array $fields, ?string $templateId = null): array
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->put("{$this->apiUrl}/submissions/{$submissionId}", [
            'fields' => $this->prepareFieldsForDocuseal($fields, $templateId),
            'metadata' => [
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to update Docuseal submission: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get submission status and details
     */
    public function getSubmission(string $submissionId): array
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->get("{$this->apiUrl}/submissions/{$submissionId}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get Docuseal submission: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get submission document for viewing/downloading
     */
    public function getSubmissionDocument(string $submissionId): array
    {
        try {
            // Get submission details
            $submission = $this->getSubmission($submissionId);

            // Get document URL
            $documentUrl = $this->getDocumentUrl($submissionId);

            // Get audit log URL if available
            $auditLogUrl = $this->getAuditLogUrl($submissionId);

            return [
                'submission' => $submission,
                'url' => $documentUrl,
                'audit_log_url' => $auditLogUrl,
                'status' => $submission['status'] ?? 'unknown',
                'created_at' => $submission['created_at'] ?? null,
                'completed_at' => $submission['completed_at'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get submission document', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get submission document URL from Docuseal
     */
    public function getSubmissionDocumentUrl(string $submissionId): string
    {
        try {
            return $this->getDocumentUrl($submissionId);
        } catch (\Exception $e) {
            Log::error('Failed to get submission document URL from Docuseal', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get document URL for viewing
     */
    private function getDocumentUrl(string $submissionId): string
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->get("{$this->apiUrl}/submissions/{$submissionId}/documents");

        if (!$response->successful()) {
            throw new \Exception('Failed to get document URL: ' . $response->body());
        }

        $data = $response->json();

        // Return the first document URL or generate a viewing URL
        if (!empty($data['documents'])) {
            return $data['documents'][0]['url'] ?? $this->generateViewUrl($submissionId);
        }

        return $this->generateViewUrl($submissionId);
    }

    /**
     * Generate viewing URL for submission
     */
    private function generateViewUrl(string $submissionId): string
    {
        return "{$this->apiUrl}/submissions/{$submissionId}/view";
    }

    /**
     * Get audit log URL
     */
    private function getAuditLogUrl(string $submissionId): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
            ])->get("{$this->apiUrl}/submissions/{$submissionId}/audit_log");

            if ($response->successful()) {
                $data = $response->json();
                return $data['url'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get audit log URL', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Download signed document
     */
    public function downloadDocument(string $submissionId, string $format = 'pdf'): string
    {
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
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
            'X-Auth-Token' => $this->apiKey,
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
     * Get template fields directly from Docuseal API
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
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->apiUrl}/templates/{$templateId}");

            if (!$response->successful()) {
                Log::error('Failed to get template from Docuseal', [
                    'template_id' => $templateId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            // Debug log the raw response
            Log::info('Docuseal template API response', [
                'template_id' => $templateId,
                'status' => $response->status(),
                'response_keys' => array_keys($response->json() ?? [])
            ]);

            $template = $response->json();
            $fields = [];

            // Debug the template structure
            Log::info('Docuseal template structure', [
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

            Log::info('Retrieved template fields from Docuseal', [
                'template_id' => $templateId,
                'field_count' => count($fields),
                'field_names' => array_keys($fields)
            ]);

            return $fields;

        } catch (\Exception $e) {
            Log::error('Error getting template fields from Docuseal', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get template fields for a manufacturer
     */
    public function getTemplateFields(string $manufacturerName): array
    {
        $manufacturer = $this->fieldMappingService->getManufacturerConfig($manufacturerName);

        if (!$manufacturer) {
            throw new \Exception("Unknown manufacturer: {$manufacturerName}");
        }

        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
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
     * Process webhook callback from Docuseal
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
                    'docuseal_completed_at' => now(),
                    'signed_document_url' => $payload['data']['documents'][0]['url'] ?? null,
                ]);

                // Also update any associated product requests
                $productRequests = \App\Models\Order\ProductRequest::where('docuseal_submission_id', $submissionId)->get();
                foreach ($productRequests as $productRequest) {
                    $productRequest->update([
                        'ivr_signed_at' => now(),
                        'ivr_document_url' => $payload['data']['documents'][0]['url'] ?? null,
                    ]);
                }

                Log::info('Docuseal submission completed', [
                    'submission_id' => $submissionId,
                    'ivr_episode_id' => $ivrEpisode->id,
                    'product_requests_updated' => $productRequests->count(),
                    'document_url' => $payload['data']['documents'][0]['url'] ?? null,
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
                Log::info('Unhandled Docuseal webhook event', [
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
     * Get default safe fields to exclude when template fields can't be retrieved
     */
    private function getDefaultExcludedFields(): array
    {
        return [
            'patient_first_name',
            'patient_last_name',
            'patient_middle_name',
            'provider_first_name',
            'provider_last_name',
            'patientFirstName',
            'patientLastName',
            'providerFirstName',
            'providerLastName',
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            '_token',
            '_method',
        ];
    }

    /**
     * Prepare fields for Docuseal API format
     */
    private function prepareFieldsForDocuseal(array $fields, ?string $templateId = null): array
    {
        $DocusealFields = [];
        $skippedFields = [];

        // Get template field definitions if available
        $templateFields = [];
        if ($templateId) {
            $templateFields = $this->getTemplateFieldsFromAPI($templateId);

            Log::info('Template fields retrieved from Docuseal', [
                'template_id' => $templateId,
                'template_field_count' => count($templateFields),
                'template_field_names' => array_keys($templateFields)
            ]);
        }

        // Handle special case: if we have patient_first_name and patient_last_name but template expects patient_name
        if (isset($fields['patient_first_name']) && isset($fields['patient_last_name']) &&
            !isset($fields['patient_name']) && isset($templateFields['patient_name'])) {
            $fields['patient_name'] = trim($fields['patient_first_name'] . ' ' . $fields['patient_last_name']);
            Log::info('Computed patient_name for DocuSeal template', [
                'patient_name' => $fields['patient_name']
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
                Log::debug('Skipping field not found in Docuseal template', [
                    'field_name' => $key,
                    'value' => $value,
                    'template_id' => $templateId
                ]);
                continue;
            }

            // If we don't have template fields (e.g., due to API error), skip fields that are known to cause issues
            if (empty($templateFields) && in_array($key, $this->getDefaultExcludedFields())) {
                $skippedFields[] = $key;
                Log::debug('Skipping field from default exclusion list', [
                    'field_name' => $key,
                    'template_id' => $templateId,
                    'reason' => 'no_template_fields_available'
                ]);
                continue;
            }

            // Convert empty values to empty strings for Docuseal
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
                Log::debug('Using Docuseal field UUID', [
                    'field_name' => $key,
                    'field_uuid' => $fieldId
                ]);
            }

            $DocusealFields[] = [
                'name' => $fieldId,
                'default_value' => (string) $value,
            ];
        }

        if (!empty($skippedFields)) {
            Log::warning('Fields skipped due to not existing in Docuseal template', [
                'template_id' => $templateId,
                'skipped_fields' => $skippedFields,
                'skipped_count' => count($skippedFields)
            ]);
        }

        Log::info('Prepared fields for Docuseal', [
            'input_count' => count($fields),
            'output_count' => count($DocusealFields),
            'skipped_count' => count($skippedFields),
            'template_id' => $templateId,
            'template_fields_available' => count($templateFields) > 0
        ]);

        return $DocusealFields;
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
        ];
    }

    /**
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

                    // Get template fields from Docuseal API directly
                    $templateFields = $this->getTemplateFieldsFromAPI($template->docuseal_template_id);

                    // If no fields from API, try the old method
                    if (empty($templateFields)) {
                        Log::warning('No fields from Docuseal API, trying legacy method');
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
                        "Docuseal template for {$template->manufacturer->name}",
                        ['preserve_unmapped' => true]
                    );

                    if ($aiResult['success'] && !empty($aiResult['mappings'])) {
                        Log::info('✅ AI field mapping successful', [
                            'mapped_fields' => count($aiResult['mappings']),
                            'confidence' => $aiResult['overall_confidence'] ?? 0,
                            'sample_mappings' => array_slice($aiResult['mappings'], 0, 5, true)
                        ]);

                        // Convert AI result format to Docuseal format directly
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

                        // Return the mapped fields directly in Docuseal format
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
            Log::error('Docuseal: Error in AI field mapping', [
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
                Log::warning('Docuseal: No template or field mappings found', [
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
                Log::warning('Docuseal: Invalid field mappings format', [
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

            Log::info('Docuseal: Field mapping completed', [
                'input_fields' => count($data),
                'mapped_fields' => count($mappedFields),
                'template_id' => $template->id
            ]);

            // Convert to Docuseal format
            $DocusealFields = [];
            foreach ($mappedFields as $fieldName => $fieldValue) {
                $DocusealFields[] = [
                    'name' => $fieldName,
                    'default_value' => (string)$fieldValue
                ];
            }

            return $DocusealFields;

        } catch (\Exception $e) {
            Log::error('Docuseal: Error mapping fields', [
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
            ]);

            return [
                'success' => true,
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
     * Find manufacturer by template ID
     */
    private function findManufacturerByTemplateId(string $templateId): ?string
    {
        // Map of template IDs to manufacturer names (based on config files)
        $templateToManufacturerMap = [
            '1233913' => 'MEDLIFE SOLUTIONS',      // MedLife IVR template
            '1234279' => 'MEDLIFE SOLUTIONS',      // MedLife Order Form template
            '1199885' => 'ADVANCED SOLUTION',      // Advanced Solution IVR template
            '1299488' => 'ADVANCED SOLUTION ORDER FORM', // Advanced Solution Order Form template
            '1254774' => 'BIOWOUND SOLUTIONS',     // Biowound IVR template
            '1299495' => 'BIOWOUND SOLUTIONS',     // Biowound Order Form template
            '1233918' => 'CENTURION THERAPEUTICS', // Centurion IVR template
            // Add more mappings as needed
        ];

        return $templateToManufacturerMap[$templateId] ?? null;
    }

    /**
     * Create DocuSeal submission for Quick Request workflow
     * This method handles the specific data structure coming from the frontend
     */
    public function createSubmissionForQuickRequest(
        string $templateId,
        string $integrationEmail,  // Our DocuSeal account email (limitless@mscwoundcare.com)
        string $submitterEmail,    // The person who will sign (provider@example.com)
        string $submitterName,     // The person's name
        array $prefillData = [],
        ?int $episodeId = null
    ): array {
        Log::info('Creating DocuSeal submission for Quick Request with AI mapping', [
            'template_id' => $templateId,
            'integration_email' => $integrationEmail,
            'submitter_email' => $submitterEmail,
            'submitter_name' => $submitterName,
            'episode_id' => $episodeId,
            'prefill_data_keys' => array_keys($prefillData)
        ]);

        try {
            // Find manufacturer by template ID
            $manufacturerName = $this->findManufacturerByTemplateId($templateId);

            if (!$manufacturerName) {
                Log::warning('No manufacturer found for template ID, using fallback mapping', [
                    'template_id' => $templateId
                ]);

                // Use old static mapping as fallback
                $docusealFields = $this->transformQuickRequestData($prefillData, $templateId, $manufacturerName);

                return $this->createDocusealSubmission($templateId, $docusealFields, $integrationEmail, $submitterEmail, $submitterName, $episodeId, $manufacturerName, false, 0.0, 'static_fallback');
            }

            // Use AI service for intelligent field mapping
            Log::info('Using AI service for field mapping', [
                'template_id' => $templateId,
                'manufacturer' => $manufacturerName,
                'prefill_data_count' => count($prefillData)
            ]);

            // Call the AI service via DynamicFieldMappingService
            $dynamicMappingService = app(\App\Services\DocuSeal\DynamicFieldMappingService::class);
            $aiMappingResult = $dynamicMappingService->mapForDocuseal($templateId, $manufacturerName, $prefillData);

            if ($aiMappingResult['success'] && !empty($aiMappingResult['field_mappings'])) {
                // AI mapping successful - use the AI-mapped fields
                $docusealFields = $aiMappingResult['field_mappings'];

                Log::info('AI mapping successful', [
                    'template_id' => $templateId,
                    'manufacturer' => $manufacturerName,
                    'ai_fields_mapped' => count($docusealFields),
                    'ai_confidence' => $aiMappingResult['confidence'] ?? 0.0,
                    'ai_quality_grade' => $aiMappingResult['quality_grade'] ?? 'unknown'
                ]);

                return $this->createDocusealSubmission(
                    $templateId,
                    $docusealFields,
                    $integrationEmail,
                    $submitterEmail,
                    $submitterName,
                    $episodeId,
                    $manufacturerName,
                    true,
                    $aiMappingResult['confidence'] ?? 0.95,
                    'azure_ai'
                );
            } else {
                // AI mapping failed - fall back to static mapping
                Log::warning('AI mapping failed, using static fallback', [
                    'template_id' => $templateId,
                    'manufacturer' => $manufacturerName,
                    'ai_error' => $aiMappingResult['error'] ?? 'unknown error'
                ]);

                $docusealFields = $this->transformQuickRequestData($prefillData, $templateId, $manufacturerName);

                return $this->createDocusealSubmission($templateId, $docusealFields, $integrationEmail, $submitterEmail, $submitterName, $episodeId, $manufacturerName, false, 0.0, 'static_fallback');
            }

        } catch (\Exception $e) {
            Log::error('Failed to create DocuSeal submission for Quick Request', [
                'template_id' => $templateId,
                'integration_email' => $integrationEmail,
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper method to create DocuSeal submission with unified response format
     */
    private function createDocusealSubmission(
        string $templateId,
        array $docusealFields,
        string $integrationEmail,
        string $submitterEmail,
        string $submitterName,
        ?int $episodeId,
        ?string $manufacturerName,
        bool $aiMappingUsed,
        float $aiConfidence,
        string $mappingMethod
    ): array {
        // Convert fields to DocuSeal values format
        $docusealValues = $this->convertFieldsToDocusealValues($docusealFields);

        Log::info('Preparing DocuSeal submission data', [
            'template_id' => $templateId,
            'manufacturer' => $manufacturerName,
            'raw_fields_count' => count($docusealFields),
            'converted_values_count' => count($docusealValues),
            'sample_raw_fields' => array_slice($docusealFields, 0, 3),
            'sample_converted_values' => array_slice($docusealValues, 0, 3),
            'mapping_method' => $mappingMethod,
            'ai_used' => $aiMappingUsed
        ]);

        // Prepare submission data according to DocuSeal API format
        $submissionData = [
            'template_id' => $templateId,
            'send_email' => false, // Don't send email automatically
            'metadata' => [
                'source' => 'quick_request',
                'episode_id' => $episodeId,
                'created_at' => now()->toIso8601String(),
                'submitter_email' => $submitterEmail,
                'integration_email' => $integrationEmail,
                'manufacturer' => $manufacturerName,
                'mapping_method' => $mappingMethod,
                'ai_mapping_used' => $aiMappingUsed,
                'ai_confidence' => $aiConfidence,
            ],
            'submitters' => [
                [
                    'name' => $submitterName,
                    'email' => $submitterEmail,
                    'role' => 'First Party',
                    'values' => $docusealValues
                ]
            ]
        ];

        // Make API call to DocuSeal
        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/submissions", $submissionData);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = 'Failed to create DocuSeal submission';

            if (isset($errorBody['error'])) {
                $errorMessage .= ': ' . $errorBody['error'];
            } elseif (isset($errorBody['message'])) {
                $errorMessage .= ': ' . $errorBody['message'];
            } else {
                $errorMessage .= ': HTTP ' . $response->status();
            }

            Log::error('DocuSeal API error', [
                'template_id' => $templateId,
                'manufacturer' => $manufacturerName,
                'status' => $response->status(),
                'error' => $errorBody,
                'fields_count' => count($docusealFields),
                'mapping_method' => $mappingMethod,
                'sample_values' => array_slice($this->convertFieldsToDocusealValues($docusealFields), 0, 5) // Log sample converted values
            ]);

            throw new \Exception($errorMessage);
        }

        $result = $response->json();

        // Extract submission info from response (DocuSeal returns array of submitters)
        $submissionData = $result[0] ?? $result;
        $submissionId = $submissionData['submission_id'] ?? null;
        $slug = $submissionData['slug'] ?? null;

        if (!$slug) {
            throw new \Exception('No slug returned from DocuSeal API');
        }

        Log::info('DocuSeal submission created successfully', [
            'template_id' => $templateId,
            'manufacturer' => $manufacturerName,
            'submission_id' => $submissionId,
            'slug' => $slug,
            'fields_mapped' => count($docusealFields),
            'mapping_method' => $mappingMethod,
            'ai_mapping_used' => $aiMappingUsed,
            'ai_confidence' => $aiConfidence
        ]);

        return [
            'success' => true,
            'data' => [
                'slug' => $slug,
                'submission_id' => $submissionId,
                'embed_url' => "https://docuseal.com/s/{$slug}",
                'template_id' => $templateId,
                'manufacturer' => $manufacturerName
            ],
            'ai_mapping_used' => $aiMappingUsed,
            'ai_confidence' => $aiConfidence,
            'mapping_method' => $mappingMethod,
            'fields_mapped' => count($docusealFields)
        ];
    }

    /**
     * Convert field mappings to DocuSeal values format
     * DocuSeal expects values as key-value pairs, not the field format
     */
    private function convertFieldsToDocusealValues(array $docusealFields): array
    {
        $values = [];

        foreach ($docusealFields as $fieldName => $fieldValue) {
            // Handle different field formats
            if (is_array($fieldValue)) {
                // If it's an array with 'name' and 'default_value', use that (old format)
                if (isset($fieldValue['name']) && isset($fieldValue['default_value'])) {
                    $values[$fieldValue['name']] = $fieldValue['default_value'];
                } else {
                    // Otherwise convert array to comma-separated string
                    $values[$fieldName] = $this->formatFieldValue($fieldValue);
                }
            } else {
                // Simple key-value pair
                $values[$fieldName] = $this->formatFieldValue($fieldValue);
            }
        }

        return $values;
    }

    /**
     * Format field value for DocuSeal
     */
    private function formatFieldValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        } elseif (is_array($value)) {
            // Handle nested arrays by converting to comma-separated string
            $flattenedValues = array_map(function($item) {
                return is_array($item) ? json_encode($item) : (string)$item;
            }, $value);
            return implode(', ', $flattenedValues);
        } elseif ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Transform Quick Request data to DocuSeal field format using manufacturer config
     */
    private function transformQuickRequestData(array $prefillData, string $templateId, ?string $manufacturerName = null): array
    {
        $docusealFields = [];

        // If we have a manufacturer name, use manufacturer-specific field mappings
        if ($manufacturerName) {
            $manufacturerConfig = $this->fieldMappingService->getManufacturerConfig($manufacturerName);

            if ($manufacturerConfig && isset($manufacturerConfig['docuseal_field_names'])) {
                $fieldMappings = $manufacturerConfig['docuseal_field_names'];

                Log::info('Using manufacturer-specific field mappings', [
                    'manufacturer' => $manufacturerName,
                    'template_id' => $templateId,
                    'available_mappings' => count($fieldMappings),
                    'mapping_fields' => array_keys($fieldMappings)
                ]);

                // Apply manufacturer-specific field mappings
                foreach ($fieldMappings as $canonicalField => $docusealField) {
                    if (isset($prefillData[$canonicalField]) && $prefillData[$canonicalField] !== null && $prefillData[$canonicalField] !== '') {
                        $value = $prefillData[$canonicalField];

                        // Convert boolean values to text
                        if (is_bool($value)) {
                            $value = $value ? 'Yes' : 'No';
                        }

                        $docusealFields[$docusealField] = $value;
                    }
                }

                // Handle special computed fields based on manufacturer config
                if (isset($manufacturerConfig['fields'])) {
                    foreach ($manufacturerConfig['fields'] as $fieldName => $fieldConfig) {
                        if (isset($fieldConfig['source']) && $fieldConfig['source'] === 'computed') {
                            $computedValue = $this->computeFieldValue($fieldConfig, $prefillData);
                            if ($computedValue !== null && isset($fieldMappings[$fieldName])) {
                                $docusealFields[$fieldMappings[$fieldName]] = $computedValue;
                            }
                        }
                    }
                }

                Log::info('Applied manufacturer-specific field mappings', [
                    'manufacturer' => $manufacturerName,
                    'template_id' => $templateId,
                    'input_fields' => count($prefillData),
                    'output_fields' => count($docusealFields),
                    'mapped_fields' => array_keys($docusealFields)
                ]);

                return $docusealFields;
            }
        }

        // Fallback to generic field mappings if no manufacturer config found
        Log::warning('Using fallback field mappings - manufacturer config not found', [
            'manufacturer' => $manufacturerName,
            'template_id' => $templateId
        ]);

        // Common field mappings for Quick Request data (fallback)
        $fieldMappings = [
            // Patient information
            'patient_name' => 'Patient Name',
            'patient_first_name' => 'Patient First Name',
            'patient_last_name' => 'Patient Last Name',
            'patient_dob' => 'Patient Date of Birth',
            'patient_gender' => 'Patient Gender',
            'patient_phone' => 'Patient Phone',
            'patient_email' => 'Patient Email',
            'patient_address_line1' => 'Patient Address',
            'patient_city' => 'Patient City',
            'patient_state' => 'Patient State',
            'patient_zip' => 'Patient ZIP',

            // Provider information
            'provider_name' => 'Provider Name',
            'provider_npi' => 'Provider NPI',
            'provider_ptan' => 'Provider PTAN',
            'provider_credentials' => 'Provider Credentials',
            'provider_email' => 'Provider Email',

            // Facility information
            'facility_name' => 'Facility Name',
            'facility_address' => 'Facility Address',
            'facility_phone' => 'Facility Phone',
            'facility_npi' => 'Facility NPI',

            // Insurance information
            'primary_insurance_name' => 'Primary Insurance',
            'primary_member_id' => 'Member ID',
            'primary_plan_type' => 'Plan Type',

            // Clinical information
            'wound_type' => 'Wound Type',
            'wound_location' => 'Wound Location',
            'wound_size_length' => 'Wound Length',
            'wound_size_width' => 'Wound Width',
            'wound_size_depth' => 'Wound Depth',
            'wound_dimensions' => 'Wound Dimensions',
            'wound_duration' => 'Wound Duration',
            'primary_diagnosis_code' => 'Primary Diagnosis',
            'secondary_diagnosis_code' => 'Secondary Diagnosis',

            // Product information
            'product_name' => 'Product Name',
            'product_code' => 'Product Code',
            'product_manufacturer' => 'Manufacturer',

            // Other fields
            'service_date' => 'Service Date',
            'prior_applications' => 'Prior Applications',
            'hospice_status' => 'Hospice Status',
        ];

        // Apply field mappings
        foreach ($fieldMappings as $sourceKey => $targetKey) {
            if (isset($prefillData[$sourceKey]) && $prefillData[$sourceKey] !== null && $prefillData[$sourceKey] !== '') {
                $value = $prefillData[$sourceKey];

                // Convert boolean values to text
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                $docusealFields[$targetKey] = $value;
            }
        }

        // Handle special field transformations
        if (isset($prefillData['product_details_text'])) {
            $docusealFields['Product Details'] = $prefillData['product_details_text'];
        }

        if (isset($prefillData['diagnosis_codes_display'])) {
            $docusealFields['Diagnosis Codes'] = $prefillData['diagnosis_codes_display'];
        }

        // Handle manufacturer-specific fields
        if (isset($prefillData['manufacturer_fields']) && is_array($prefillData['manufacturer_fields'])) {
            foreach ($prefillData['manufacturer_fields'] as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }
                $docusealFields[ucfirst(str_replace('_', ' ', $key))] = $value;
            }
        }

        Log::info('Transformed Quick Request data to DocuSeal fields (fallback)', [
            'template_id' => $templateId,
            'input_fields' => count($prefillData),
            'output_fields' => count($docusealFields),
            'sample_mapping' => array_slice($docusealFields, 0, 5, true)
        ]);

        return $docusealFields;
    }

    /**
     * Compute field value based on field configuration
     */
    private function computeFieldValue(array $fieldConfig, array $data): mixed
    {
        if (!isset($fieldConfig['computation'])) {
            return null;
        }

        $computation = $fieldConfig['computation'];

        // Handle simple concatenation (e.g., "patient_first_name + patient_last_name")
        if (strpos($computation, '+') !== false) {
            $parts = array_map('trim', explode('+', $computation));
            $values = [];
            foreach ($parts as $part) {
                if (isset($data[$part])) {
                    $values[] = $data[$part];
                }
            }
            return implode(' ', $values);
        }

        // Handle simple field references
        if (isset($data[$computation])) {
            return $data[$computation];
        }

        // Handle mathematical operations (e.g., "wound_size_length * wound_size_width")
        if (strpos($computation, '*') !== false) {
            $parts = array_map('trim', explode('*', $computation));
            if (count($parts) === 2 && isset($data[$parts[0]]) && isset($data[$parts[1]])) {
                return floatval($data[$parts[0]]) * floatval($data[$parts[1]]);
            }
        }

        return null;
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
            ];
        }
    }

    /**
     * Test Docuseal API connection
     */
    public function testConnection(): array
    {
        try {
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
     * Map FHIR checklist data to Docuseal fields
     * This method transforms FHIR-formatted data into Docuseal field format
     */
    public function mapFHIRToDocuseal(array $checklistData): array
    {
        try {
            // Use the AI service if available for intelligent mapping
            $aiService = app(AzureFoundryService::class);
            if (method_exists($aiService, 'mapFhirToDocuseal')) {
                // The AI service expects 4 parameters: fhirData, DocusealFields, manufacturerName, context
                $DocusealFields = []; // Empty array since we don't have specific fields
                $manufacturerName = ''; // Unknown manufacturer
                $context = []; // No additional context

                return $aiService->mapFhirToDocuseal($checklistData, $DocusealFields, $manufacturerName, $context);
            }
        } catch (\Exception $e) {
            Log::warning('AI service not available for FHIR mapping', ['error' => $e->getMessage()]);
        }

        // Fallback to basic mapping
        $mappedData = [];

        // Map common FHIR fields to Docuseal fields
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

        foreach ($fieldMapping as $fhirKey => $DocusealKey) {
            if (isset($checklistData[$fhirKey])) {
                $mappedData[$DocusealKey] = $checklistData[$fhirKey];
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

    /**
     * Update ProductRequest with episode and submission information
     * TODO: Implement this method properly based on the actual ProductRequest model
     */
    private function updateProductRequestWithEpisode(int $episodeId, int $ivrEpisodeId, string $submissionId, string $templateId): void
    {
        // Placeholder method - implement based on actual ProductRequest model structure
        Log::info('updateProductRequestWithEpisode called', [
            'episode_id' => $episodeId,
            'ivr_episode_id' => $ivrEpisodeId,
            'submission_id' => $submissionId,
            'template_id' => $templateId
        ]);
    }

    /**
     * Create or update a submission using comprehensive orchestrator data
     */
    public function createSubmissionFromOrchestratorData(
        PatientManufacturerIVREpisode $episode,
        array $comprehensiveData,
        string $manufacturerName
    ): array {
        try {
            // Use intelligent mapping if available, otherwise fallback to standard
            if ($this->intelligentMapping) {
                Log::info('Using AI-enhanced field mapping for Docuseal submission');
                $mappingResult = $this->intelligentMapping->mapEpisodeWithAI(
                    null, // No episode ID since we're providing data directly
                    $manufacturerName,
                    $comprehensiveData,
                    ['use_cache' => true, 'adaptive_validation' => true]
                );
            } else {
                Log::info('Using standard field mapping for Docuseal submission');
                $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                    null,
                    $manufacturerName,
                    $comprehensiveData
                );
            }

            // Log the mapping result for debugging
            Log::info('Field mapping result', [
                'has_mapped_data' => isset($mappingResult['data']),
                'mapped_field_count' => isset($mappingResult['data']) ? count($mappingResult['data']) : 0,
                'has_patient_name' => isset($mappingResult['data']['patient_name']),
                'has_patient_first_name' => isset($mappingResult['data']['patient_first_name']),
                'sample_mapped_fields' => isset($mappingResult['data']) ? array_slice(array_keys($mappingResult['data']), 0, 10) : []
            ]);

            // Check validation - be more flexible with AI-enhanced mapping
            $canProceed = $mappingResult['validation']['valid'] ||
                         ($mappingResult['validation']['can_proceed'] ?? false);

            if (!$canProceed) {
                $criticalErrors = array_filter($mappingResult['validation']['errors'] ?? [], function($error) {
                    return strpos($error, 'Critical field') !== false;
                });

                if (!empty($criticalErrors)) {
                    throw new \Exception('Critical field mapping validation failed: ' .
                        implode(', ', $criticalErrors));
                }

                // If only non-critical errors, log warnings but proceed
                Log::warning('Non-critical field mapping issues, proceeding with submission', [
                    'warnings' => $mappingResult['validation']['warnings'] ?? [],
                    'errors' => $mappingResult['validation']['errors'] ?? []
                ]);
            }

            // Log the mapping result for debugging
            Log::info('DocuSeal mapping result received', [
                'has_manufacturer' => isset($mappingResult['manufacturer']),
                'manufacturer_type' => gettype($mappingResult['manufacturer'] ?? null),
                'mapping_result_keys' => array_keys($mappingResult),
                'manufacturer_data' => $mappingResult['manufacturer'] ?? null
            ]);

            // Extract template ID from mapping result or look it up from database
            $templateId = null;

            // First try to get from mapping result
            if (isset($mappingResult['manufacturer']['docuseal_template_id'])) {
                $templateId = $mappingResult['manufacturer']['docuseal_template_id'];
            } elseif (isset($mappingResult['manufacturer']['template_id'])) {
                $templateId = $mappingResult['manufacturer']['template_id'];
            }

            // If not found in mapping result, look up from database
            if (!$templateId) {
                // Try to find the manufacturer by name
                $manufacturer = \App\Models\Order\Manufacturer::where('name', $manufacturerName)->first();

                if ($manufacturer) {
                    // Get the default IVR template for this manufacturer
                    $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                        ->where('document_type', 'IVR')
                        ->where('is_active', true)
                        ->where('is_default', true)
                        ->first();

                    if (!$template) {
                        // Try without is_default constraint
                        $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                            ->where('document_type', 'IVR')
                            ->where('is_active', true)
                            ->first();
                    }

                    if ($template) {
                        $templateId = $template->docuseal_template_id;
                        Log::info('Found template ID from database', [
                            'manufacturer' => $manufacturerName,
                            'manufacturer_id' => $manufacturer->id,
                            'template_id' => $templateId
                        ]);
                    }
                }
            }

            if (!$templateId) {
                // Log the full manufacturer object for debugging
                Log::error('Template ID not found for manufacturer', [
                    'manufacturer_name' => $manufacturerName,
                    'manufacturer_config' => $mappingResult['manufacturer'] ?? null,
                    'available_keys' => array_keys($mappingResult['manufacturer'] ?? []),
                    'manufacturer_exists' => isset($manufacturer) ? 'yes' : 'no'
                ]);

                throw new \Exception("No DocuSeal template found for manufacturer: {$manufacturerName}. Please ensure the manufacturer has an active IVR template configured.");
            }

            // Check if we need to create a new submission or update existing
            if ($episode->docuseal_submission_id) {
                // Update existing submission
                $response = $this->updateSubmission(
                    $episode->docuseal_submission_id,
                    $mappingResult['data'],
                    $templateId
                );
            } else {
                // Create new submission
                $response = $this->createSubmission(
                    $templateId,
                    $mappingResult['data'],
                    $episode->id,
                    $mappingResult['manufacturer']
                );

                // Update episode with submission ID
                $episode->update([
                    'docuseal_submission_id' => $response['id'],
                    'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PENDING,
                ]);
            }

            Log::info('Docuseal submission created successfully from orchestrator data', [
                'episode_id' => $episode->id,
                'submission_id' => $response['id'],
                'manufacturer' => $manufacturerName,
                'template_id' => $templateId,
                'mapped_fields_count' => count($mappingResult['data']),
                'ai_enhanced' => $mappingResult['ai_enhanced'] ?? false
            ]);

            // Learn from successful mapping for future AI improvements
            if ($this->intelligentMapping && ($mappingResult['ai_enhanced'] ?? false)) {
                $this->intelligentMapping->learnFromSuccess(
                    $manufacturerName,
                    $mappingResult['data'],
                    ['success' => true, 'submission_id' => $response['id']]
                );
            }

            return [
                'success' => true,
                'submission' => $response,
                'manufacturer' => $mappingResult['manufacturer'],
                'mapped_data' => $mappingResult['data'],
                'validation' => $mappingResult['validation'],
                'completeness' => $mappingResult['completeness'] ?? [],
                'ai_enhanced' => $mappingResult['ai_enhanced'] ?? false
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Docuseal submission from orchestrator data', [
                'episode_id' => $episode->id,
                'manufacturer' => $manufacturerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'submission' => null,
                'manufacturer' => null
            ];
        }
    }
}
