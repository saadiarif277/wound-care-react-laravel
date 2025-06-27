<?php

namespace App\Console\Commands;

use App\Services\UnifiedFieldMappingService;
use App\Services\DocuSealService;
use App\Models\Episode;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FieldMappingCommand extends Command
{
    protected $signature = 'field-mapping 
                            {action : Action to perform (test|map|analyze|clean|migrate)}
                            {--episode= : Episode ID for mapping}
                            {--manufacturer= : Manufacturer name}
                            {--dry-run : Show what would be done without executing}
                            {--force : Force execution without confirmation}';

    protected $description = 'Unified field mapping system management';

    public function __construct(
        private UnifiedFieldMappingService $fieldMappingService,
        private DocuSealService $docuSealService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        match ($action) {
            'test' => $this->testFieldMapping(),
            'map' => $this->mapEpisode(),
            'analyze' => $this->analyzeFieldMappings(),
            'clean' => $this->cleanOldData(),
            'migrate' => $this->migrateOldData(),
            default => $this->error("Unknown action: {$action}")
        };

        return 0;
    }

    private function testFieldMapping(): void
    {
        $this->info('🧪 Testing Field Mapping System');
        $this->newLine();

        // Test basic configuration
        $this->info('📋 Testing manufacturer configurations...');
        $manufacturers = $this->fieldMappingService->listManufacturers();
        $this->table(
            ['ID', 'Name', 'Template ID', 'Fields', 'Required Fields'],
            collect($manufacturers)->map(fn($m) => [
                $m['id'],
                $m['name'],
                $m['template_id'],
                $m['fields_count'],
                $m['required_fields_count']
            ])
        );

        // Test with sample episode if available
        $episode = Episode::with('productRequests')->first();
        if ($episode && $episode->productRequests->isNotEmpty()) {
            $this->info("🔍 Testing with Episode #{$episode->id}...");
            
            try {
                $result = $this->fieldMappingService->mapEpisodeToTemplate(
                    $episode->id,
                    'ACZ'
                );

                $this->info('✅ Field mapping successful!');
                $this->info("   Completeness: {$result['completeness']['percentage']}%");
                $this->info("   Fields mapped: {$result['completeness']['filled']}/{$result['completeness']['total']}");
                $this->info("   Validation: " . ($result['validation']['valid'] ? '✅ Valid' : '❌ Invalid'));
                
                if (!empty($result['validation']['warnings'])) {
                    $this->warn('⚠️  Warnings:');
                    foreach ($result['validation']['warnings'] as $warning) {
                        $this->warn("   - {$warning}");
                    }
                }

            } catch (\Exception $e) {
                $this->error("❌ Field mapping failed: {$e->getMessage()}");
            }
        } else {
            $this->warn('No episodes available for testing');
        }

        $this->info('✅ Field mapping test completed!');
    }

    private function mapEpisode(): void
    {
        $episodeId = $this->option('episode');
        $manufacturer = $this->option('manufacturer');

        if (!$episodeId || !$manufacturer) {
            $this->error('Both --episode and --manufacturer options are required');
            return;
        }

        $this->info("🔄 Mapping Episode #{$episodeId} for {$manufacturer}");

        try {
            $result = $this->fieldMappingService->mapEpisodeToTemplate(
                (int) $episodeId,
                $manufacturer
            );

            $this->info('✅ Mapping completed successfully!');
            $this->newLine();

            // Show results
            $this->info('📊 Results:');
            $this->info("   Completeness: {$result['completeness']['percentage']}%");
            $this->info("   Duration: {$result['metadata']['duration_ms']}ms");
            $this->info("   Validation: " . ($result['validation']['valid'] ? '✅ Valid' : '❌ Invalid'));

            if ($this->option('verbose')) {
                $this->info('📋 Mapped Fields:');
                foreach ($result['data'] as $field => $value) {
                    if (!str_starts_with($field, '_')) {
                        $displayValue = is_array($value) ? json_encode($value) : $value;
                        $this->info("   {$field}: {$displayValue}");
                    }
                }
            }

            // Ask if user wants to create DocuSeal submission
            if ($this->confirm('Create DocuSeal submission?')) {
                $this->info('📝 Creating DocuSeal submission...');
                
                $submissionResult = $this->docuSealService->createOrUpdateSubmission(
                    (int) $episodeId,
                    $manufacturer
                );

                if ($submissionResult['success']) {
                    $this->info('✅ DocuSeal submission created successfully!');
                    $this->info("   Submission ID: {$submissionResult['submission']['id']}");
                } else {
                    $this->error('❌ DocuSeal submission failed');
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Mapping failed: {$e->getMessage()}");
        }
    }

    private function analyzeFieldMappings(): void
    {
        $this->info('📊 Analyzing Field Mapping Performance');
        $this->newLine();

        $manufacturer = $this->option('manufacturer');

        // Overall analytics
        $analytics = $this->docuSealService->generateAnalytics($manufacturer);

        $this->info('📈 Overall Statistics:');
        $this->info("   Total Submissions: {$analytics['total_submissions']}");
        $this->info("   Completion Rate: {$analytics['completion_rate']}%");
        $this->info("   Avg Field Completeness: {$analytics['average_field_completeness']}%");
        $this->info("   Avg Required Completeness: {$analytics['average_required_field_completeness']}%");
        
        if ($analytics['average_time_to_complete_minutes']) {
            $this->info("   Avg Time to Complete: {$analytics['average_time_to_complete_minutes']} minutes");
        }

        $this->newLine();

        // Status breakdown
        $this->info('📋 Status Breakdown:');
        foreach ($analytics['status_breakdown'] as $status => $count) {
            $this->info("   {$status}: {$count}");
        }

        // Field mapping logs analysis
        $this->info('🔍 Recent Field Mapping Activity:');
        $recentLogs = DB::table('field_mapping_logs')
            ->when($manufacturer, fn($q) => $q->where('manufacturer_name', $manufacturer))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentLogs->isNotEmpty()) {
            $this->table(
                ['Episode', 'Manufacturer', 'Completeness', 'Duration', 'Date'],
                $recentLogs->map(fn($log) => [
                    $log->episode_id,
                    $log->manufacturer_name,
                    $log->completeness_percentage . '%',
                    $log->mapping_duration_ms . 'ms',
                    \Carbon\Carbon::parse($log->created_at)->diffForHumans()
                ])
            );
        } else {
            $this->warn('No recent field mapping activity found');
        }

        // Performance recommendations
        $this->newLine();
        $this->info('💡 Recommendations:');
        
        if ($analytics['average_field_completeness'] < 80) {
            $this->warn('• Field completeness is below 80% - review field mappings');
        }
        
        if ($analytics['completion_rate'] < 90) {
            $this->warn('• Completion rate is below 90% - investigate failed submissions');
        }
        
        $this->info('• Monitor field mapping analytics regularly');
        $this->info('• Keep manufacturer configurations up to date');
    }

    private function cleanOldData(): void
    {
        $this->info('🧹 Cleaning Old Field Mapping Data');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will clean old data. Continue?')) {
            $this->info('Operation cancelled');
            return;
        }

        $dryRun = $this->option('dry-run');

        // Clean old field mapping logs (older than 6 months)
        $oldLogs = DB::table('field_mapping_logs')
            ->where('created_at', '<', now()->subMonths(6))
            ->count();

        if ($oldLogs > 0) {
            $this->info("🗑️  Found {$oldLogs} old field mapping logs");
            
            if (!$dryRun) {
                DB::table('field_mapping_logs')
                    ->where('created_at', '<', now()->subMonths(6))
                    ->delete();
                $this->info('✅ Old logs cleaned');
            } else {
                $this->info('   (dry-run: would delete)');
            }
        }

        // Clean field mapping cache
        $this->info('🧹 Clearing field mapping cache...');
        if (!$dryRun) {
            \Cache::flush();
            $this->info('✅ Cache cleared');
        } else {
            $this->info('   (dry-run: would clear cache)');
        }

        // Archive old IVR episodes that are completed
        $oldCompleted = PatientManufacturerIVREpisode::where('docuseal_status', 'completed')
            ->where('completed_at', '<', now()->subMonths(12))
            ->count();

        if ($oldCompleted > 0) {
            $this->info("📦 Found {$oldCompleted} old completed IVR episodes");
            $this->info('   Consider archiving these records');
        }

        $this->info('✅ Cleanup completed!');
    }

    private function migrateOldData(): void
    {
        $this->info('🔄 Migrating Old Field Mapping Data');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will migrate old data structures. Continue?')) {
            $this->info('Operation cancelled');
            return;
        }

        $dryRun = $this->option('dry-run');

        // Check for old IVR episodes without field mapping completeness
        $unmigrated = PatientManufacturerIVREpisode::whereNull('field_mapping_completeness')
            ->whereNotNull('manufacturer_fields')
            ->count();

        if ($unmigrated > 0) {
            $this->info("🔄 Found {$unmigrated} IVR episodes to migrate");
            
            if (!$dryRun) {
                $bar = $this->output->createProgressBar($unmigrated);
                $bar->start();

                PatientManufacturerIVREpisode::whereNull('field_mapping_completeness')
                    ->whereNotNull('manufacturer_fields')
                    ->chunk(100, function ($episodes) use ($bar) {
                        foreach ($episodes as $episode) {
                            // Calculate completeness from existing fields
                            $fields = json_decode($episode->manufacturer_fields, true) ?? [];
                            $totalFields = count($fields);
                            $filledFields = count(array_filter($fields, fn($v) => !empty($v)));
                            $completeness = $totalFields > 0 ? ($filledFields / $totalFields) * 100 : 0;

                            $episode->update([
                                'field_mapping_completeness' => round($completeness, 2),
                                'required_fields_completeness' => round($completeness, 2), // Estimate
                                'mapped_fields' => $episode->manufacturer_fields,
                                'validation_warnings' => json_encode([])
                            ]);

                            $bar->advance();
                        }
                    });

                $bar->finish();
                $this->newLine();
                $this->info('✅ Migration completed!');
            } else {
                $this->info('   (dry-run: would migrate data)');
            }
        } else {
            $this->info('✅ No data migration needed');
        }
    }
}