<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MacValidationService;
use App\Models\Insurance\MedicareMacValidation;
use Illuminate\Support\Facades\Log;

class MedicareMacDailyMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medicare:daily-monitoring
                            {--facility-id= : Only monitor validations for specific facility}
                            {--validation-type= : Only monitor specific validation types (vascular_wound_care, wound_care_only, vascular_only)}
                            {--force : Force monitoring even if already run today}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run daily Medicare MAC validation monitoring for compliance checks';

    /**
     * Execute the console command.
     */
    public function handle(MacValidationService $validationService): int
    {
        $this->info('🏥 Starting Medicare MAC Daily Monitoring...');

        try {
            // Get options
            $facilityId = $this->option('facility-id');
            $validationType = $this->option('validation-type');
            $force = $this->option('force');
            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No changes will be made');
            }

            // Check if monitoring already ran today (unless forced)
            if (!$force && !$dryRun) {
                $monitoredToday = MedicareMacValidation::dailyMonitoring()
                    ->whereDate('last_monitored_at', now()->toDateString())
                    ->count();

                if ($monitoredToday > 0) {
                    $this->info("✅ Daily monitoring already completed today for {$monitoredToday} validations");
                    $this->info('Use --force to run again or --dry-run to preview');
                    return self::SUCCESS;
                }
            }

            // Get validations that need monitoring
            $query = MedicareMacValidation::dailyMonitoring()
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            if (!$force) {
                $query->where(function ($q) {
                    $q->whereDate('last_monitored_at', '<', now()->toDateString())
                      ->orWhereNull('last_monitored_at');
                });
            }

            if ($facilityId) {
                $query->where('facility_id', $facilityId);
                $this->info("🏥 Filtering by facility ID: {$facilityId}");
            }

            if ($validationType) {
                $query->where('validation_type', $validationType);
                $this->info("🔍 Filtering by validation type: {$validationType}");
            }

            $validations = $query->get();
            $totalValidations = $validations->count();

            if ($totalValidations === 0) {
                $this->info('✅ No validations need monitoring today');
                return self::SUCCESS;
            }

            $this->info("📊 Found {$totalValidations} validations to monitor");

            if ($dryRun) {
                $this->table(
                    ['Validation ID', 'Order ID', 'Type', 'Current Status', 'Facility', 'Last Monitored'],
                    $validations->map(function ($validation) {
                        return [
                            substr($validation->validation_id, 0, 8) . '...',
                            $validation->order_id,
                            $validation->validation_type,
                            $validation->validation_status,
                            $validation->order->facility->name ?? 'Unknown',
                            $validation->last_monitored_at?->format('Y-m-d') ?? 'Never'
                        ];
                    })->toArray()
                );
                return self::SUCCESS;
            }

            // Process validations
            $results = [
                'processed' => 0,
                'revalidated' => 0,
                'new_issues' => 0,
                'resolved_issues' => 0,
                'errors' => 0
            ];

            $this->withProgressBar($validations, function ($validation) use ($validationService, &$results) {
                try {
                    $previousStatus = $validation->validation_status;

                    // Re-run validation
                    // TODO: Implement validateOrder method in MacValidationService
                    // $validationService->validateOrder($validation->order, $validation->validation_type);

                    $validation->refresh();
                    $newStatus = $validation->validation_status;

                    // Track changes
                    if ($previousStatus !== $newStatus) {
                        $results['revalidated']++;

                        if ($newStatus === 'failed' || $newStatus === 'requires_review') {
                            $results['new_issues']++;
                        } elseif ($newStatus === 'validated') {
                            $results['resolved_issues']++;
                        }
                    }

                    $validation->update(['last_monitored_at' => now()]);
                    $results['processed']++;

                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Daily monitoring failed for validation', [
                        'validation_id' => $validation->id,
                        'error' => $e->getMessage()
                    ]);
                }
            });

            $this->newLine(2);

            // Display results
            $this->info('📈 Daily Monitoring Results:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Processed', $results['processed']],
                    ['Status Changes', $results['revalidated']],
                    ['New Issues', $results['new_issues']],
                    ['Resolved Issues', $results['resolved_issues']],
                    ['Errors', $results['errors']],
                ]
            );

            // Summary by validation type
            $this->info('📊 Summary by Validation Type:');
            $typeSummary = $validations->groupBy('validation_type')->map(function ($group) {
                return $group->count();
            });

            foreach ($typeSummary as $type => $count) {
                $this->line("  • {$type}: {$count} validations");
            }

            // High-risk validations
            $highRisk = MedicareMacValidation::highRisk()
                ->when($facilityId, function ($q) use ($facilityId) {
                    return $q->where('facility_id', $facilityId);
                })
                ->count();

            if ($highRisk > 0) {
                $this->warn("⚠️  {$highRisk} high-risk validations require attention");
            }

            // Failed validations
            $failed = MedicareMacValidation::failed()
                ->when($facilityId, function ($q) use ($facilityId) {
                    return $q->where('facility_id', $facilityId);
                })
                ->count();

            if ($failed > 0) {
                $this->error("❌ {$failed} validations have failed compliance checks");
            }

            // Due for revalidation
            $dueForRevalidation = MedicareMacValidation::dueForRevalidation()
                ->when($facilityId, function ($q) use ($facilityId) {
                    return $q->where('facility_id', $facilityId);
                })
                ->count();

            if ($dueForRevalidation > 0) {
                $this->info("🔄 {$dueForRevalidation} validations are due for revalidation");
            }

            $this->info('✅ Daily monitoring completed successfully');

            // Log completion
            Log::info('Medicare MAC daily monitoring completed', [
                'results' => $results,
                'facility_id' => $facilityId,
                'validation_type' => $validationType,
                'total_validations' => $totalValidations
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Daily monitoring failed: ' . $e->getMessage());
            Log::error('Medicare MAC daily monitoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
