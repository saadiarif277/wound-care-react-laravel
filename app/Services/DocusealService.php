<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\UnifiedFieldMappingService;
use App\Services\AI\AzureFoundryService;
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
    private function createSubmission(string $templateId, array $fields, int $episodeId, array $manufacturerConfig = []): array
    {
        // Use UnifiedFieldMappingService to convert fields to Docuseal format
        if (!empty($manufacturerConfig['docuseal_field_names'])) {
            $preparedFields = $this->fieldMappingService->convertToDocusealFields($fields, $manufacturerConfig);
        } else {
            // Fallback to old method if no mapping config
            $preparedFields = $this->prepareFieldsForDocuseal($fields, $templateId);
        }

        Log::info('Creating Docuseal submission', [
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

        return $response->json();
    }

    /**
     * Update an existing Docuseal submission
     */
    private function updateSubmission(string $submissionId, array $fields, ?string $templateId = null): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
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
            'Authorization' => 'API-Key ' . $this->apiKey,
        ])->get("{$this->apiUrl}/submissions/{$submissionId}");

        if (!$response->successful()) {
            throw new \Exception('Failed to get Docuseal submission: ' . $response->body());
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
                'X-API-TOKEN' => $this->apiKey,
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
}
