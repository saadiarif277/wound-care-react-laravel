<?php

namespace App\Jobs\QuickRequest;

use App\Models\Episode;
use App\Models\Order;
use App\Models\Task;
use App\Services\Fhir\FhirService;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateApprovalTask implements ShouldQueue
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
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Episode $episode,
        private Order $order
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(FhirService $fhirService): void
    {
        Log::info('Creating approval task', [
            'episode_id' => $this->episode->id,
            'order_id' => $this->order->id,
        ]);

        try {
            // Determine assignee based on manufacturer requirements
            $assigneeRole = $this->determineAssigneeRole();
            $assignee = $this->findAssignee($assigneeRole);

            // Create FHIR Task resource
            $fhirTask = $this->createFhirTask($fhirService);

            // Create local task
            $task = Task::create([
                'taskable_type' => Episode::class,
                'taskable_id' => $this->episode->id,
                'fhir_task_id' => $fhirTask['id'] ?? null,
                'type' => 'approval',
                'status' => 'pending',
                'priority' => $this->determinePriority(),
                'title' => 'Review and approve wound care order',
                'description' => $this->generateTaskDescription(),
                'assigned_to' => $assignee?->id,
                'assigned_role' => $assigneeRole,
                'due_date' => $this->calculateDueDate(),
                'metadata' => [
                    'order_id' => $this->order->id,
                    'manufacturer_id' => $this->episode->manufacturer_id,
                    'requires_medical_review' => $this->requiresMedicalReview(),
                ],
            ]);

            // Send notification to assignee
            if ($assignee) {
                $assignee->notify(new TaskAssignedNotification($task));
            }

            // Update episode status
            $this->episode->update(['status' => 'pending_review']);

            Log::info('Approval task created successfully', [
                'task_id' => $task->id,
                'assigned_to' => $assignee?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create approval task', [
                'episode_id' => $this->episode->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determine who should be assigned the approval task
     */
    private function determineAssigneeRole(): string
    {
        // Check manufacturer requirements
        $manufacturer = $this->episode->manufacturer;
        
        if ($manufacturer?->requires_medical_review) {
            return 'medical_director';
        }

        if ($manufacturer?->requires_office_manager_approval) {
            return 'office_manager';
        }

        return 'manufacturer_representative';
    }

    /**
     * Find appropriate user for the role
     */
    private function findAssignee(string $role): ?\App\Models\User
    {
        switch ($role) {
            case 'medical_director':
                return \App\Models\User::role('medical_director')
                    ->where('organization_id', $this->episode->organization_id)
                    ->first();
                    
            case 'office_manager':
                return \App\Models\User::role('office_manager')
                    ->whereHas('facilities', function ($query) {
                        $query->where('facilities.id', $this->episode->facility_id);
                    })
                    ->first();
                    
            case 'manufacturer_representative':
                return \App\Models\User::role('manufacturer_rep')
                    ->where('manufacturer_id', $this->episode->manufacturer_id)
                    ->first();
                    
            default:
                return null;
        }
    }

    /**
     * Create FHIR Task resource
     */
    private function createFhirTask(FhirService $fhirService): array
    {
        $taskData = [
            'resourceType' => 'Task',
            'status' => 'requested',
            'intent' => 'order',
            'priority' => strtolower($this->determinePriority()),
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://mscwoundcare.com/CodeSystem/task-type',
                        'code' => 'approval',
                        'display' => 'Order Approval',
                    ],
                ],
            ],
            'description' => $this->generateTaskDescription(),
            'focus' => [
                'reference' => "EpisodeOfCare/{$this->episode->episode_of_care_fhir_id}",
                'display' => "Episode {$this->episode->id}",
            ],
            'for' => [
                'reference' => "Patient/{$this->episode->patient_fhir_id}",
            ],
            'authoredOn' => now()->toIso8601String(),
            'requester' => [
                'reference' => "Practitioner/{$this->episode->practitioner_fhir_id}",
            ],
            'restriction' => [
                'period' => [
                    'end' => $this->calculateDueDate()->toIso8601String(),
                ],
            ],
        ];

        return $fhirService->create('Task', $taskData);
    }

    /**
     * Determine task priority
     */
    private function determinePriority(): string
    {
        // Urgent if patient needs supplies immediately
        $orderDetails = $this->order->details;
        
        if ($orderDetails['delivery_info']['method'] ?? '' === 'overnight') {
            return 'urgent';
        }

        if ($this->requiresMedicalReview()) {
            return 'high';
        }

        return 'routine';
    }

    /**
     * Generate task description
     */
    private function generateTaskDescription(): string
    {
        $products = $this->order->details['products'] ?? [];
        $productCount = count($products);
        
        return sprintf(
            'Review and approve wound care order for patient %s. Order contains %d product%s from %s.',
            $this->episode->patient_display,
            $productCount,
            $productCount === 1 ? '' : 's',
            $this->episode->manufacturer->name
        );
    }

    /**
     * Check if medical review is required
     */
    private function requiresMedicalReview(): bool
    {
        // Check for high-risk diagnoses
        $primaryDiagnosis = $this->order->details['clinical_info']['diagnosis']['primary']['code'] ?? '';
        $highRiskDiagnoses = ['L89.', 'I70.', 'E11.'];
        
        foreach ($highRiskDiagnoses as $prefix) {
            if (str_starts_with($primaryDiagnosis, $prefix)) {
                return true;
            }
        }

        // Check for complex wound characteristics
        $woundDetails = $this->order->details['clinical_info']['woundDetails'] ?? [];
        if (($woundDetails['woundStage'] ?? '') === '4' || 
            ($woundDetails['woundSize']['depth'] ?? 0) > 5) {
            return true;
        }

        return false;
    }

    /**
     * Calculate task due date
     */
    private function calculateDueDate(): \Carbon\Carbon
    {
        $priority = $this->determinePriority();
        
        return match ($priority) {
            'urgent' => now()->addHours(4),
            'high' => now()->addDay(),
            default => now()->addDays(2),
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Failed to create approval task', [
            'episode_id' => $this->episode->id,
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);

        // Update episode metadata
        $this->episode->update([
            'metadata' => array_merge($this->episode->metadata ?? [], [
                'task_creation_failed' => [
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
    }
}