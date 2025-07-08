<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSeal\TemplateFieldValidationService;
use Illuminate\Support\Facades\Log;

class SyncDocuSealTemplates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docuseal:sync-templates 
                           {--dry-run : Show what would be done without making changes}
                           {--force : Force sync even if no changes detected}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically sync DocuSeal template changes with manufacturer configurations';

    private TemplateFieldValidationService $validator;

    public function __construct(TemplateFieldValidationService $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 DocuSeal Template Synchronizer');
        $this->info('==================================');

        $isDryRun = $this->option('dry-run');
        $forceSync = $this->option('force');

        if ($isDryRun) {
            $this->warn('🏃 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Clear cache to get fresh template data
        $this->info('🧹 Clearing template field cache...');
        $this->validator->clearTemplateFieldCache();

        // Validate all configurations
        $this->info('📋 Checking all manufacturer configurations...');
        $results = $this->validator->validateAllManufacturerConfigs();

        if (empty($results['details'])) {
            $this->warn('⚠️  No manufacturer configurations found');
            return Command::FAILURE;
        }

        $syncedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($results['details'] as $manufacturer => $validation) {
            $syncResult = $this->syncManufacturerConfig($manufacturer, $validation, $isDryRun, $forceSync);
            
            switch ($syncResult) {
                case 'synced':
                    $syncedCount++;
                    break;
                case 'error':
                    $errorCount++;
                    break;
                case 'skipped':
                    $skippedCount++;
                    break;
            }
        }

        // Display summary
        $this->newLine();
        $this->table(['Result', 'Count'], [
            ['✅ Synced', $syncedCount],
            ['⏭️  Skipped', $skippedCount],
            ['❌ Errors', $errorCount]
        ]);

        if ($isDryRun && $syncedCount > 0) {
            $this->info('💡 Run without --dry-run to apply these changes');
        }

        if ($errorCount > 0) {
            $this->error("❌ Sync completed with {$errorCount} error(s)");
            return Command::FAILURE;
        }

        $this->success('🎉 Template synchronization completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Sync a single manufacturer configuration
     */
    private function syncManufacturerConfig(string $manufacturer, array $validation, bool $isDryRun, bool $forceSync): string
    {
        $templateId = $validation['template_id'] ?? null;
        
        if (!$templateId) {
            $this->warn("⏭️  {$manufacturer}: No template ID configured");
            return 'skipped';
        }

        // Skip if already valid and not forcing
        if ($validation['valid'] && empty($validation['invalid_fields']) && !$forceSync) {
            $this->info("✅ {$manufacturer}: Already valid, skipping");
            return 'skipped';
        }

        // Show what needs to be synced
        $invalidCount = count($validation['invalid_fields'] ?? []);
        
        if ($invalidCount === 0 && !$forceSync) {
            $this->info("✅ {$manufacturer}: No invalid fields to sync");
            return 'skipped';
        }

        $this->info("🔄 {$manufacturer}: Syncing {$invalidCount} invalid field(s)...");

        // Show invalid fields that will be removed
        if (!empty($validation['invalid_fields'])) {
            foreach ($validation['invalid_fields'] as $canonical => $docuseal) {
                $this->line("   🚫 Will remove: {$canonical} → {$docuseal}");
            }
        }

        if ($isDryRun) {
            $this->line("   📝 DRY RUN: Changes not applied");
            return 'synced';
        }

        // Apply the sync
        try {
            $configFile = config_path("manufacturers/" . \Illuminate\Support\Str::slug($manufacturer) . ".php");
            $result = $this->validator->autoFixManufacturerConfig($configFile);

            if ($result['success']) {
                $this->success("   ✅ {$manufacturer}: Synced successfully");
                
                if (!empty($result['changes'])) {
                    foreach ($result['changes'] as $change) {
                        $this->line("      • $change");
                    }
                }

                // Log the sync
                Log::info('DocuSeal template sync applied', [
                    'manufacturer' => $manufacturer,
                    'template_id' => $templateId,
                    'changes' => $result['changes'] ?? []
                ]);

                return 'synced';
            } else {
                $this->error("   ❌ {$manufacturer}: Sync failed - {$result['message']}");
                return 'error';
            }
        } catch (\Exception $e) {
            $this->error("   ❌ {$manufacturer}: Sync error - {$e->getMessage()}");
            
            Log::error('DocuSeal template sync failed', [
                'manufacturer' => $manufacturer,
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
            
            return 'error';
        }
    }

    /**
     * Display a success message
     */
    private function success(string $message): void
    {
        $this->line("<fg=green>$message</>");
    }
} 