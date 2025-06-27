<?php

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocuSealService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct(
        private UnifiedFieldMappingService $fieldMappingService
    ) {
        $this->apiKey = config('services.docuseal.api_key');
        $this->apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.co');
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
                    $mappingResult['data']
                );
            } else {
                // Create new submission
                $response = $this->createSubmission(
                    $mappingResult['manufacturer']['template_id'],
                    $mappingResult['data'],
                    $episodeId
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
        }
    }

    /**
     * Create a new DocuSeal submission
     */
    private function createSubmission(string $templateId, array $fields, int $episodeId): array
    {
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
            'fields' => $this->prepareFieldsForDocuSeal($fields),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create DocuSeal submission: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Update an existing DocuSeal submission
     */
    private function updateSubmission(string $submissionId, array $fields): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->put("{$this->apiUrl}/submissions/{$submissionId}", [
            'fields' => $this->prepareFieldsForDocuSeal($fields),
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
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Prepare fields for DocuSeal API format
     */
    private function prepareFieldsForDocuSeal(array $fields): array
    {
        $docuSealFields = [];

        foreach ($fields as $key => $value) {
            // Skip internal fields
            if (str_starts_with($key, '_')) {
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

            $docuSealFields[] = [
                'name' => $key,
                'value' => (string) $value,
            ];
        }

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
}