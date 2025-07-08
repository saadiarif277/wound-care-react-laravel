<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSeal\TemplateFieldValidationService;

class ValidateDocuSealConfigs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docuseal:validate-configs 
                           {--fix : Automatically fix invalid configurations}
                           {--manufacturer= : Validate specific manufacturer only}
                           {--clear-cache : Clear template field cache before validation}';

    /**
     * The console command description.
     */
    protected $description = 'Validate all manufacturer DocuSeal configurations against actual template fields';

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
        $this->info('🔍 DocuSeal Configuration Validator');
        $this->info('=====================================');

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing template field cache...');
            $this->validator->clearTemplateFieldCache();
        }

        $specificManufacturer = $this->option('manufacturer');
        $shouldAutoFix = $this->option('fix');

        if ($specificManufacturer) {
            return $this->validateSpecificManufacturer($specificManufacturer, $shouldAutoFix);
        }

        return $this->validateAllManufacturers($shouldAutoFix);
    }

    /**
     * Validate all manufacturer configurations
     */
    private function validateAllManufacturers(bool $shouldAutoFix): int
    {
        $this->info('📋 Validating all manufacturer configurations...');
        $this->newLine();

        $results = $this->validator->validateAllManufacturerConfigs();
        
        if (empty($results['details'])) {
            $this->warn('⚠️  No manufacturer configurations found');
            return Command::FAILURE;
        }

        // Display summary
        $summary = $results['summary'];
        $this->table(['Metric', 'Count'], [
            ['Total Configurations', $summary['total']],
            ['✅ Valid', $summary['valid']],
            ['❌ Invalid', $summary['invalid']],
            ['⚠️  Warnings', $summary['warnings']]
        ]);

        $hasErrors = false;

        // Display detailed results
        foreach ($results['details'] as $manufacturer => $validation) {
            $this->displayValidationResult($manufacturer, $validation, $shouldAutoFix);
            
            if (!$validation['valid']) {
                $hasErrors = true;
            }
        }

        // Summary message
        if ($summary['valid'] === $summary['total']) {
            $this->success('🎉 All configurations are valid!');
            return Command::SUCCESS;
        } else {
            $this->error("❌ Found {$summary['invalid']} invalid configuration(s)");
            
            if (!$shouldAutoFix) {
                $this->info('💡 Run with --fix to automatically fix configurations');
            }
            
            return $hasErrors ? Command::FAILURE : Command::SUCCESS;
        }
    }

    /**
     * Validate a specific manufacturer configuration
     */
    private function validateSpecificManufacturer(string $manufacturerName, bool $shouldAutoFix): int
    {
        $this->info("📋 Validating configuration for: {$manufacturerName}");
        $this->newLine();

        $configFile = config_path("manufacturers/" . \Illuminate\Support\Str::slug($manufacturerName) . ".php");
        
        if (!file_exists($configFile)) {
            $this->error("❌ Configuration file not found: {$configFile}");
            return Command::FAILURE;
        }

        try {
            $config = include $configFile;
            
            if (!is_array($config)) {
                $this->error('❌ Invalid configuration file format');
                return Command::FAILURE;
            }

            $validation = $this->validator->validateManufacturerConfig($config);
            $this->displayValidationResult($manufacturerName, $validation, $shouldAutoFix);

            return $validation['valid'] ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Failed to validate configuration: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Display validation result for a manufacturer
     */
    private function displayValidationResult(string $manufacturer, array $validation, bool $shouldAutoFix): void
    {
        $status = $validation['valid'] ? '✅' : '❌';
        $templateId = $validation['template_id'] ?? 'None';
        
        $this->info("$status {$manufacturer} (Template: {$templateId})");

        // Show errors
        if (!empty($validation['errors'])) {
            foreach ($validation['errors'] as $error) {
                $this->error("   ❌ $error");
            }
        }

        // Show warnings
        if (!empty($validation['warnings'])) {
            foreach ($validation['warnings'] as $warning) {
                $this->warn("   ⚠️  $warning");
            }
        }

        // Show field statistics
        if (isset($validation['valid_fields']) && isset($validation['invalid_fields'])) {
            $validCount = count($validation['valid_fields']);
            $invalidCount = count($validation['invalid_fields']);
            $totalCount = $validCount + $invalidCount;
            
            if ($totalCount > 0) {
                $this->info("   📊 Fields: {$validCount} valid, {$invalidCount} invalid");
                
                // Show invalid fields
                if ($invalidCount > 0) {
                    $this->line("   🚫 Invalid fields:");
                    foreach ($validation['invalid_fields'] as $canonical => $docuseal) {
                        $this->line("      • {$canonical} → {$docuseal}");
                    }
                }
            }
        }

        // Auto-fix if requested and needed
        if ($shouldAutoFix && !$validation['valid'] && isset($validation['template_id'])) {
            $this->attemptAutoFix($manufacturer, $validation);
        }

        $this->newLine();
    }

    /**
     * Attempt to auto-fix a manufacturer configuration
     */
    private function attemptAutoFix(string $manufacturer, array $validation): void
    {
        $configFile = config_path("manufacturers/" . \Illuminate\Support\Str::slug($manufacturer) . ".php");
        
        if (!file_exists($configFile)) {
            $this->error("   ❌ Cannot auto-fix: Configuration file not found");
            return;
        }

        $this->info("   🔧 Attempting auto-fix...");
        
        try {
            $result = $this->validator->autoFixManufacturerConfig($configFile);
            
            if ($result['success']) {
                $this->success("   ✅ Auto-fix successful: {$result['message']}");
                
                if (!empty($result['changes'])) {
                    foreach ($result['changes'] as $change) {
                        $this->info("      • $change");
                    }
                }
            } else {
                $this->error("   ❌ Auto-fix failed: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Auto-fix error: {$e->getMessage()}");
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