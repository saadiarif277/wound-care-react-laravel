<?php

declare(strict_types=1);

namespace App\Jobs\QuickRequest;

use App\Models\Episode;
use App\Notifications\EpisodeCreatedNotification;
use App\Notifications\EpisodeCreationFailedNotification;
use App\Services\Compliance\PhiAuditService;
use App\Services\FhirService;
use App\Services\QuickRequestOrchestrator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessEpisodeCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Episode $episode,
        private array $requestData,
        private int $userId
    ) {
        $this->onQueue('high-priority');
    }

    /**
     * Execute the job.
     */
    public function handle(
        QuickRequestOrchestrator $orchestrator,
        FhirService $fhirService,
        PhiAuditService $auditService
    ): void {
        Log::info('Processing episode creation', [
            'episode_id' => $this->episode->id,
            'user_id' => $this->userId,
        ]);

        DB::beginTransaction();

        try {
            // Update episode status
            $this->episode->update(['status' => 'processing']);

            // Process through orchestrator
            $result = $orchestrator->createEpisode($this->requestData);

            // Update episode with FHIR IDs
            $this->episode->update([
                'patient_fhir_id' => $result['patient_fhir_id'],
                'practitioner_fhir_id' => $result['practitioner_fhir_id'],
                'organization_fhir_id' => $result['organization_fhir_id'],
                'episode_of_care_fhir_id' => $result['episode_of_care_fhir_id'] ?? null,
                'status' => 'pending_review',
            ]);

            // Create initial order
            $order = $this->episode->orders()->create([
                'type' => 'initial',
                'status' => 'active',
                'details' => [
                    'products' => $this->requestData['productSelection']['products'] ?? [],
                    'delivery_info' => $this->requestData['productSelection']['deliveryPreferences'] ?? [],
                    'clinical_info' => $this->requestData['clinicalBilling'] ?? [],
                ],
                'device_request_fhir_id' => $result['device_request_fhir_id'] ?? null,
                'created_by' => $this->userId,
            ]);

            // Create approval task
            $this->dispatch(new CreateApprovalTask($this->episode, $order));

            // Log PHI access
            $auditService->logAccess(
                'episode_created',
                $this->episode->id,
                $this->userId,
                [
                    'patient_fhir_id' => $result['patient_fhir_id'],
                    'episode_type' => 'quick_request',
                ]
            );

            DB::commit();

            // Send notifications
            $this->episode->creator->notify(new EpisodeCreatedNotification($this->episode));

            Log::info('Episode creation completed successfully', [
                'episode_id' => $this->episode->id,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Episode creation failed', [
                'episode_id' => $this->episode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update episode status
            $this->episode->update([
                'status' => 'error',
                'metadata' => array_merge($this->episode->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Episode creation job failed permanently', [
            'episode_id' => $this->episode->id,
            'error' => $exception->getMessage(),
        ]);

        // Update episode status
        $this->episode->update([
            'status' => 'failed',
            'metadata' => array_merge($this->episode->metadata ?? [], [
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
                'attempts' => $this->attempts(),
            ]),
        ]);

        // Notify user of failure
        $this->episode->creator->notify(new EpisodeCreationFailedNotification(
            $this->episode,
            $exception->getMessage()
        ));
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'episode',
            'quick-request',
            'episode:' . $this->episode->id,
            'user:' . $this->userId,
        ];
    }
}
