<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\SmartFieldMappingValidator;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ValidateAndFixFieldMappings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'field-mapping:validate-and-fix 
                          {--manufacturer= : Validate specific manufacturer only}
                          {--dry-run : Show what would be fixed without making changes}
                          {--force : Force fix even if confidence is low}';

    /**
     * The console command description.
     */
    protected $description = 'Validate and automatically fix invalid field mappings in manufacturer configurations';

    private SmartFieldMappingValidator $validator;

    public function __construct(SmartFieldMappingValidator $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting AI-powered field mapping validation...');
        
        $specificManufacturer = $this->option('manufacturer');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }
        
        $this->info('');
        
        // Get validation report
        $report = $this->validator->getValidationReport();
        $this->displayValidationReport($report);
        
        // Check manufacturer config files
        $this->info('📁 Checking manufacturer configuration files...');
        $configFiles = $this->getManufacturerConfigFiles($specificManufacturer);
        
        $totalFixed = 0;
        $totalErrors = 0;
        $results = [];
        
        foreach ($configFiles as $configFile) {
            $result = $this->validateAndFixConfigFile($configFile, $dryRun, $force);
            $results[] = $result;
            $totalFixed += $result['fixed_count'];
            $totalErrors += $result['error_count'];
        }
        
        // Check database field mappings
        $this->info('🗄️ Checking database field mappings...');
        $dbResult = $this->validateAndFixDatabaseMappings($specificManufacturer, $dryRun, $force);
        $totalFixed += $dbResult['fixed_count'];
        $totalErrors += $dbResult['error_count'];
        
        // Display final summary
        $this->displaySummary($results, $dbResult, $totalFixed, $totalErrors, $dryRun);
        
        return $totalErrors > 0 ? 1 : 0;
    }
    
    private function displayValidationReport(array $report): void
    {
        $this->info('🎯 Validation System Status:');
        $this->line("   • Common mappings: {$report['common_mappings_count']}");
        $this->line("   • Invalid field history: {$report['invalid_history_count']}");
        $this->line("   • Cache status: {$report['cache_status']}");
        $this->info('');
    }
    
    private function getManufacturerConfigFiles(?string $specificManufacturer): array
    {
        $configPath = config_path('manufacturers');
        $files = File::files($configPath);
        
        if ($specificManufacturer) {
            $files = array_filter($files, function($file) use ($specificManufacturer) {
                return str_contains(strtolower($file->getFilename()), strtolower($specificManufacturer));
            });
        }
        
        return $files;
    }
    
    private function validateAndFixConfigFile(\SplFileInfo $configFile, bool $dryRun, bool $force): array
    {
        $filename = $configFile->getFilename();
        $this->line("🔧 Checking {$filename}...");
        
        $result = [
            'file' => $filename,
            'fixed_count' => 0,
            'error_count' => 0,
            'warnings' => [],
            'errors' => []
        ];
        
        try {
            // Load config
            $config = include $configFile->getPathname();
            
            if (!is_array($config)) {
                $result['errors'][] = 'Invalid config file format';
                $result['error_count']++;
                $this->error("   ❌ Invalid config format");
                return $result;
            }
            
            // Validate manufacturer config
            $validation = $this->validator->prevalidateManufacturerConfig($config);
            
            if (!$validation['is_valid']) {
                $result['errors'] = array_merge($result['errors'], $validation['errors']);
                $result['error_count'] += count($validation['errors']);
                
                foreach ($validation['errors'] as $error) {
                    $this->error("   ❌ {$error}");
                }
            }
            
            if (!empty($validation['warnings'])) {
                $result['warnings'] = array_merge($result['warnings'], $validation['warnings']);
                
                foreach ($validation['warnings'] as $warning) {
                    $this->warn("   ⚠️  {$warning}");
                }
            }
            
            // Check for field mappings that need correction
            if (isset($config['docuseal_field_names'])) {
                $correctionResult = $this->validator->validateAndCorrectFieldMappings(
                    $config['docuseal_field_names'],
                    $config['name'] ?? null
                );
                
                if (!empty($correctionResult['corrections'])) {
                    $result['fixed_count'] += count($correctionResult['corrections']);
                    
                    foreach ($correctionResult['corrections'] as $correction) {
                        $confidence = $correction['confidence'];
                        $confidenceColor = $confidence > 0.8 ? 'green' : ($confidence > 0.6 ? 'yellow' : 'red');
                        
                        $this->line("   🔄 <fg={$confidenceColor}>{$correction['original']} → {$correction['corrected']}</> (confidence: {$confidence})", null, 'vv');
                        
                        if ($confidence < 0.7 && !$force) {
                            $this->warn("   ⚠️  Low confidence correction, use --force to apply");
                            $result['fixed_count']--; // Don't count as fixed
                            continue;
                        }
                        
                        if (!$dryRun) {
                            // Apply fix to config file
                            $this->applyConfigFileFix($configFile, $correction);
                        }
                    }
                }
                
                if (!empty($correctionResult['removed_invalid'])) {
                    $removedCount = count($correctionResult['removed_invalid']);
                    $result['fixed_count'] += $removedCount;
                    
                    $this->warn("   🗑️  Removed {$removedCount} invalid field mappings");
                    
                    if (!$dryRun) {
                        // Remove invalid mappings from config file
                        $this->removeInvalidMappingsFromConfig($configFile, $correctionResult['removed_invalid']);
                    }
                }
            }
            
            if ($result['fixed_count'] > 0) {
                $this->info("   ✅ Fixed {$result['fixed_count']} field mappings");
            } else {
                $this->info("   ✅ No issues found");
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "Exception: {$e->getMessage()}";
            $result['error_count']++;
            $this->error("   ❌ Exception: {$e->getMessage()}");
        }
        
        return $result;
    }
    
    private function validateAndFixDatabaseMappings(?string $specificManufacturer, bool $dryRun, bool $force): array
    {
        $result = [
            'fixed_count' => 0,
            'error_count' => 0,
            'warnings' => [],
            'errors' => []
        ];
        
        try {
            $query = DocusealTemplate::with('manufacturer');
            
            if ($specificManufacturer) {
                $query->whereHas('manufacturer', function($q) use ($specificManufacturer) {
                    $q->where('name', 'like', "%{$specificManufacturer}%");
                });
            }
            
            $templates = $query->get();
            
            foreach ($templates as $template) {
                $manufacturerName = $template->manufacturer?->name ?? 'Unknown';
                $this->line("🔧 Checking template: {$template->template_name} ({$manufacturerName})");
                
                $fieldMappings = $template->field_mappings ?? [];
                
                if (empty($fieldMappings)) {
                    $this->line("   ℹ️  No field mappings to validate");
                    continue;
                }
                
                $correctionResult = $this->validator->validateAndCorrectFieldMappings(
                    $fieldMappings,
                    $manufacturerName
                );
                
                if (!empty($correctionResult['corrections'])) {
                    $result['fixed_count'] += count($correctionResult['corrections']);
                    
                    foreach ($correctionResult['corrections'] as $correction) {
                        $confidence = $correction['confidence'];
                        $confidenceColor = $confidence > 0.8 ? 'green' : ($confidence > 0.6 ? 'yellow' : 'red');
                        
                        $this->line("   🔄 <fg={$confidenceColor}>{$correction['original']} → {$correction['corrected']}</> (confidence: {$confidence})", null, 'vv');
                        
                        if ($confidence < 0.7 && !$force) {
                            $this->warn("   ⚠️  Low confidence correction, use --force to apply");
                            $result['fixed_count']--; // Don't count as fixed
                            continue;
                        }
                    }
                    
                    if (!$dryRun) {
                        // Update database with corrected mappings
                        $template->update([
                            'field_mappings' => $correctionResult['mappings']
                        ]);
                    }
                }
                
                if (!empty($correctionResult['removed_invalid'])) {
                    $removedCount = count($correctionResult['removed_invalid']);
                    $result['fixed_count'] += $removedCount;
                    
                    $this->warn("   🗑️  Removed {$removedCount} invalid field mappings");
                }
                
                if (!empty($correctionResult['corrections']) || !empty($correctionResult['removed_invalid'])) {
                    $this->info("   ✅ Fixed template field mappings");
                } else {
                    $this->info("   ✅ No issues found");
                }
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "Database validation error: {$e->getMessage()}";
            $result['error_count']++;
            $this->error("❌ Database validation error: {$e->getMessage()}");
        }
        
        return $result;
    }
    
    private function applyConfigFileFix(\SplFileInfo $configFile, array $correction): void
    {
        $content = File::get($configFile->getPathname());
        
        // Simple string replacement (could be improved with AST parsing)
        $search = "'{$correction['original']}'";
        $replace = "'{$correction['corrected']}'";
        
        $newContent = str_replace($search, $replace, $content);
        
        if ($content !== $newContent) {
            File::put($configFile->getPathname(), $newContent);
            Log::info("Applied field mapping fix to {$configFile->getFilename()}", $correction);
        }
    }
    
    private function removeInvalidMappingsFromConfig(\SplFileInfo $configFile, array $invalidMappings): void
    {
        $content = File::get($configFile->getPathname());
        
        foreach ($invalidMappings as $internalField => $invalidField) {
            // Remove the mapping line
            $patterns = [
                "/{$internalField}'\s*=>\s*'{$invalidField}',?\s*\n/",
                "/'{$internalField}'\s*=>\s*'{$invalidField}',?\s*\n/",
                "/\"{$internalField}\"\s*=>\s*\"{$invalidField}\",?\s*\n/",
            ];
            
            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, '', $content);
            }
        }
        
        File::put($configFile->getPathname(), $content);
        Log::info("Removed invalid field mappings from {$configFile->getFilename()}", $invalidMappings);
    }
    
    private function displaySummary(array $configResults, array $dbResult, int $totalFixed, int $totalErrors, bool $dryRun): void
    {
        $this->info('');
        $this->info('📊 VALIDATION SUMMARY');
        $this->info('====================');
        
        $this->line("Config files processed: " . count($configResults));
        $this->line("Database templates processed: " . ($dbResult['fixed_count'] + $dbResult['error_count']));
        
        if ($totalFixed > 0) {
            $this->info("✅ Total field mappings fixed: {$totalFixed}");
        }
        
        if ($totalErrors > 0) {
            $this->error("❌ Total errors encountered: {$totalErrors}");
        }
        
        if ($dryRun) {
            $this->warn("🔍 DRY RUN: No actual changes were made");
            $this->info("Run without --dry-run to apply fixes");
        }
        
        $this->info('');
        $this->info('🎯 RECOMMENDATIONS:');
        
        if ($totalFixed > 0 && !$dryRun) {
            $this->info('• Field mappings have been automatically corrected');
            $this->info('• Consider running docuseal:sync to update templates');
            $this->info('• Test IVR submissions to ensure everything works');
        }
        
        if ($totalErrors > 0) {
            $this->warn('• Some errors require manual intervention');
            $this->warn('• Check the error messages above for details');
        }
        
        $this->info('• Run this command regularly to prevent future issues');
        $this->info('• Use --force flag for low-confidence corrections if needed');
    }
} 