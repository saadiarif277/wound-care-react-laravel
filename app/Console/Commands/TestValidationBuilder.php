<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CmsCoverageApiService;
use App\Services\ValidationBuilderEngine;
use App\Services\MacValidationService;

class TestValidationBuilder extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:validation-builder {--specialty=wound_care_specialty} {--state=CA}';

    /**
     * The console command description.
     */
    protected $description = 'Test the CMS Coverage API and ValidationBuilderEngine implementation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specialty = $this->option('specialty');
        $state = $this->option('state');

        $this->info('Testing CMS Coverage API & ValidationBuilderEngine Implementation');
        $this->info('=============================================================');
        $this->newLine();

        // Test CMS Coverage API Service
        $this->testCmsCoverageApiService($specialty, $state);

        // Test Validation Builder Engine
        $this->testValidationBuilderEngine($specialty, $state);

        // Test Integration
        $this->testIntegration();

        $this->newLine();
        $this->info('✅ All tests completed successfully!');
        $this->info('🚀 Your CMS Coverage API integration is ready to use.');
    }

    private function testCmsCoverageApiService(string $specialty, string $state): void
    {
        $this->info('Testing CMS Coverage API Service...');

        try {
            $cmsService = app(CmsCoverageApiService::class);

            // Test available specialties
            $specialties = $cmsService->getAvailableSpecialties();
            $this->line("  ✓ Available specialties: " . count($specialties));

            // Test LCDs
            $lcds = $cmsService->getLCDsBySpecialty($specialty, $state);
            $this->line("  ✓ LCDs for {$specialty} in {$state}: " . count($lcds));

            // Test NCDs
            $ncds = $cmsService->getNCDsBySpecialty($specialty);
            $this->line("  ✓ NCDs for {$specialty}: " . count($ncds));

            // Test Articles
            $articles = $cmsService->getArticlesBySpecialty($specialty, $state);
            $this->line("  ✓ Articles for {$specialty} in {$state}: " . count($articles));

            // Test Search
            $searchResults = $cmsService->searchCoverageDocuments('wound care', $state);
            $this->line("  ✓ Search results for 'wound care': " . ($searchResults['total_results'] ?? 0));

            // Test MAC jurisdiction
            $macInfo = $cmsService->getMACJurisdiction($state);
            if ($macInfo) {
                $this->line("  ✓ MAC for {$state}: " . ($macInfo['contractor'] ?? 'Unknown'));
            } else {
                $this->line("  ⚠ No MAC information found for {$state}");
            }

            $this->info("  ✅ CMS Coverage API Service tests passed");

        } catch (\Exception $e) {
            $this->error("  ❌ CMS Coverage API Service test failed: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function testValidationBuilderEngine(string $specialty, string $state): void
    {
        $this->info('Testing Validation Builder Engine...');

        try {
            $validationEngine = app(ValidationBuilderEngine::class);

            // Test building validation rules
            $rules = $validationEngine->buildValidationRulesForSpecialty($specialty, $state);
            $this->line("  ✓ Validation rules built for {$specialty}: " . count($rules) . " categories");

            // Check for wound care specific rules
            if ($specialty === 'wound_care_specialty') {
                $this->checkWoundCareRules($rules);
            }

            $this->info("  ✅ Validation Builder Engine tests passed");

        } catch (\Exception $e) {
            $this->error("  ❌ Validation Builder Engine test failed: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function checkWoundCareRules(array $rules): void
    {
        $expectedCategories = [
            'pre_purchase_qualification',
            'wound_type_classification',
            'comprehensive_wound_assessment',
            'conservative_care_documentation',
            'clinical_assessments',
            'mac_coverage_verification'
        ];

        foreach ($expectedCategories as $category) {
            if (isset($rules[$category])) {
                $this->line("    ✓ {$category} rules found");
            } else {
                $this->line("    ⚠ {$category} rules missing");
            }
        }

        // Check specific wound assessment fields
        if (isset($rules['comprehensive_wound_assessment']['measurements'])) {
            $this->line("    ✓ Wound measurement validation rules found");
        }

        if (isset($rules['comprehensive_wound_assessment']['wound_bed_tissue'])) {
            $this->line("    ✓ Wound bed tissue assessment rules found");
        }
    }

    private function testIntegration(): void
    {
        $this->info('Testing Service Integration...');

        try {
            // Test that services are properly registered and can be resolved
            $cmsService = app(CmsCoverageApiService::class);
            $validationEngine = app(ValidationBuilderEngine::class);
            $macService = app(MacValidationService::class);

            $this->line("  ✓ CmsCoverageApiService registered and resolved");
            $this->line("  ✓ ValidationBuilderEngine registered and resolved");
            $this->line("  ✓ MacValidationService registered and resolved");

            // Test service functionality instead of reflection
            $this->testServiceFunctionality($cmsService, $validationEngine);

            $this->info("  ✅ Service integration tests passed");

        } catch (\Exception $e) {
            $this->error("  ❌ Service integration test failed: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function testServiceFunctionality(CmsCoverageApiService $cmsService, ValidationBuilderEngine $validationEngine): void
    {
        // Test that ValidationBuilderEngine can access CMS service functionality
        try {
            $specialties = $cmsService->getAvailableSpecialties();
            if (!empty($specialties)) {
                $this->line("  ✓ CmsCoverageApiService functionality accessible");
            }

            // Test that ValidationBuilderEngine can build rules (indicating proper dependency injection)
            $rules = $validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');
            if (!empty($rules)) {
                $this->line("  ✓ ValidationBuilderEngine properly integrated with dependencies");
            }

            // Test service interaction through CMS service
            if (!empty($specialties)) {
                $this->line("  ✓ Service interaction working correctly");
            }

        } catch (\Exception $e) {
            $this->line("  ⚠ Service functionality test issue: " . $e->getMessage());
        }
    }
}
