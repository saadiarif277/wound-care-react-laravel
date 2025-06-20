<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CleanupDuplicateCode extends Command
{
    protected $signature = 'cleanup:duplicates {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Find and clean up duplicate/overlapping code';

    private array $duplicatesToRemove = [
        // Controllers
        'app/Http/Controllers/UserController.php' => 'Keep UsersController.php instead',
        'app/Http/Controllers/RoleController.php' => 'Keep RoleManagementController.php instead',
        
        // Services
        'app/Services/EligibilityService.php' => 'Replaced by UnifiedEligibilityService',
        'app/Services/OptumEligibilityService.php' => 'Replaced by UnifiedEligibilityService',
        'app/Services/AvailityServiceReviewsService.php' => 'Duplicate Availity service',
        
        // Old eligibility configs
        'config/availity.php' => 'Consolidated into config/eligibility.php',
        
        // Test/Debug files
        'app/Http/Controllers/AccessControlController.php' => 'Deprecated controller',
        'app/Http/Controllers/EcwController.php' => 'Unused eClinicalWorks integration',
        'app/Http/Controllers/TeamController.php' => 'Not referenced anywhere',
        'app/Http/Controllers/RunMigrationController.php' => 'Dangerous in production',
    ];
    
    private array $patternsToCheck = [
        'app/Services' => ['*Service.php', '*ServiceOld.php', '*ServiceBackup.php'],
        'app/Http/Controllers' => ['*ControllerOld.php', '*ControllerBackup.php', '*Test.php'],
        'database/migrations' => ['*_backup.php', '*_old.php'],
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info($isDryRun ? 'DRY RUN - No files will be deleted' : 'Starting cleanup...');
        $this->line('');
        
        // Remove known duplicates
        $this->removeKnownDuplicates($isDryRun);
        
        // Find pattern-based duplicates
        $this->findPatternBasedDuplicates($isDryRun);
        
        // Find similar named files
        $this->findSimilarNamedFiles($isDryRun);
        
        $this->info($isDryRun ? "\nRun without --dry-run to actually delete files." : "\nCleanup completed!");
    }
    
    private function removeKnownDuplicates(bool $isDryRun): void
    {
        $this->info('Checking known duplicates...');
        
        foreach ($this->duplicatesToRemove as $file => $reason) {
            $path = base_path($file);
            
            if (File::exists($path)) {
                $this->warn("Found: $file");
                $this->line("  Reason: $reason");
                
                if (!$isDryRun) {
                    File::delete($path);
                    $this->info("  ✓ Deleted");
                } else {
                    $this->line("  → Would delete");
                }
            }
        }
    }
    
    private function findPatternBasedDuplicates(bool $isDryRun): void
    {
        $this->info("\nChecking for pattern-based duplicates...");
        
        foreach ($this->patternsToCheck as $directory => $patterns) {
            $dirPath = base_path($directory);
            
            if (!File::isDirectory($dirPath)) {
                continue;
            }
            
            foreach ($patterns as $pattern) {
                $files = File::glob($dirPath . '/' . $pattern);
                
                foreach ($files as $file) {
                    $relativePath = str_replace(base_path() . '/', '', $file);
                    $this->warn("Found suspicious file: $relativePath");
                    
                    if (!$isDryRun && $this->confirm("Delete $relativePath?")) {
                        File::delete($file);
                        $this->info("  ✓ Deleted");
                    } else {
                        $this->line("  → Would prompt for deletion");
                    }
                }
            }
        }
    }
    
    private function findSimilarNamedFiles(bool $isDryRun): void
    {
        $this->info("\nChecking for similar named files...");
        
        $controllers = File::files(base_path('app/Http/Controllers'));
        $services = File::files(base_path('app/Services'));
        
        $this->checkForSimilarFiles($controllers, 'Controllers', $isDryRun);
        $this->checkForSimilarFiles($services, 'Services', $isDryRun);
    }
    
    private function checkForSimilarFiles($files, string $type, bool $isDryRun): void
    {
        $names = [];
        
        foreach ($files as $file) {
            $basename = $file->getBasename('.php');
            $normalized = Str::lower(Str::singular($basename));
            
            if (!isset($names[$normalized])) {
                $names[$normalized] = [];
            }
            
            $names[$normalized][] = $file->getPathname();
        }
        
        foreach ($names as $normalized => $paths) {
            if (count($paths) > 1) {
                $this->warn("\nFound similar $type:");
                foreach ($paths as $path) {
                    $this->line("  - " . str_replace(base_path() . '/', '', $path));
                }
                
                if (!$isDryRun) {
                    $this->info("  → Manual review recommended");
                }
            }
        }
    }
}
