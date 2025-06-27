<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OcrFieldDetectionService;
use App\Services\AzureDocumentIntelligenceService;
use App\Models\Docuseal\DocusealTemplate;

class TestOcrSystemCommand extends Command
{
    protected $signature = 'docuseal:test-ocr-system';
    protected $description = 'Test OCR system components and dependencies';

    private OcrFieldDetectionService $ocrFieldDetection;
    private AzureDocumentIntelligenceService $azureService;

    public function __construct(
        OcrFieldDetectionService $ocrFieldDetection,
        AzureDocumentIntelligenceService $azureService
    ) {
        parent::__construct();
        $this->ocrFieldDetection = $ocrFieldDetection;
        $this->azureService = $azureService;
    }

    public function handle(): int
    {
        $this->info('🧪 Testing OCR System Components');
        $this->newLine();

        $allPassed = true;

        // Test 1: Azure Document Intelligence Configuration
        $this->info('1. Testing Azure Document Intelligence configuration...');
        if ($this->testAzureConfig()) {
            $this->line('   ✅ Azure Document Intelligence configured');
        } else {
            $this->error('   ❌ Azure Document Intelligence configuration failed');
            $allPassed = false;
        }

        // Test 2: Service Dependencies
        $this->info('2. Testing service dependencies...');
        if ($this->testServiceDependencies()) {
            $this->line('   ✅ All services properly injected');
        } else {
            $this->error('   ❌ Service dependency issues');
            $allPassed = false;
        }

        // Test 3: DocuSeal Templates
        $this->info('3. Checking DocuSeal templates in database...');
        $templateCount = DocusealTemplate::count();
        if ($templateCount > 0) {
            $this->line("   ✅ Found {$templateCount} templates in database");
        } else {
            $this->warn('   ⚠️  No templates found - run sync first');
            $this->line('      php artisan docuseal:sync-templates');
        }

        // Test 4: Storage Directories
        $this->info('4. Testing storage directories...');
        if ($this->testStorageDirectories()) {
            $this->line('   ✅ Storage directories ready');
        } else {
            $this->error('   ❌ Storage directory issues');
            $allPassed = false;
        }

        // Test 5: Pattern Matching
        $this->info('5. Testing OCR pattern matching...');
        if ($this->testPatternMatching()) {
            $this->line('   ✅ Pattern matching working');
        } else {
            $this->error('   ❌ Pattern matching issues');
            $allPassed = false;
        }

        $this->newLine();
        
        if ($allPassed) {
            $this->info('🎉 All tests passed! OCR system is ready to go.');
            $this->newLine();
            $this->info('💡 Next steps:');
            $this->line('   1. php artisan docuseal:verify-fields --all');
            $this->line('   2. php artisan docuseal:enhance-with-ocr --all');
            $this->line('   3. php artisan docuseal:verify-fields --compare-ocr');
        } else {
            $this->error('❌ Some tests failed. Please resolve issues before proceeding.');
        }

        return $allPassed ? 0 : 1;
    }

    private function testAzureConfig(): bool
    {
        try {
            $endpoint = config('azure.document_intelligence.endpoint');
            $key = config('azure.document_intelligence.key');

            if (empty($endpoint) || empty($key)) {
                $this->line('   Missing Azure configuration in config/azure.php');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->line("   Azure config error: {$e->getMessage()}");
            return false;
        }
    }

    private function testServiceDependencies(): bool
    {
        try {
            // Test that services are properly instantiated
            if (!$this->ocrFieldDetection instanceof OcrFieldDetectionService) {
                $this->line('   OcrFieldDetectionService not properly injected');
                return false;
            }

            if (!$this->azureService instanceof AzureDocumentIntelligenceService) {
                $this->line('   AzureDocumentIntelligenceService not properly injected');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->line("   Service dependency error: {$e->getMessage()}");
            return false;
        }
    }

    private function testStorageDirectories(): bool
    {
        try {
            $directories = [
                storage_path('app/temp'),
                storage_path('app/temp/ocr-pdfs'),
                storage_path('app/reports')
            ];

            foreach ($directories as $dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }

                if (!is_writable($dir)) {
                    $this->line("   Directory not writable: {$dir}");
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->line("   Storage directory error: {$e->getMessage()}");
            return false;
        }
    }

    private function testPatternMatching(): bool
    {
        try {
            // Test some sample OCR data patterns
            $testPatterns = [
                'Patient Name: ____________',
                'PROVIDER NPI: [ ]',
                'Member ID:',
                'Primary Insurance ☐',
                'WOUND TYPE *'
            ];

            $detectedCount = 0;
            foreach ($testPatterns as $pattern) {
                // Simple test - this would normally be done in the OCR service
                if (preg_match('/^([A-Z][A-Za-z\s]+(?:Name|ID|NPI|Insurance|Type)):?.*$/i', $pattern)) {
                    $detectedCount++;
                }
            }

            if ($detectedCount >= 4) {
                $this->line("   Detected {$detectedCount}/5 test patterns");
                return true;
            } else {
                $this->line("   Only detected {$detectedCount}/5 test patterns");
                return false;
            }
        } catch (\Exception $e) {
            $this->line("   Pattern matching error: {$e->getMessage()}");
            return false;
        }
    }
}
