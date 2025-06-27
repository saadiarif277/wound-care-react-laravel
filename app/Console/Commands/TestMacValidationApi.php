<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MacValidationService;
use App\Services\CmsCoverageApiService;
use Illuminate\Support\Facades\Log;

class TestMacValidationApi extends Command
{
    protected $signature = 'test:mac-validation-api {--quick : Run only quick check test} {--thorough : Run only thorough validation test}';
    protected $description = 'Test MAC validation API integration with CMS Coverage API';

    protected MacValidationService $validationService;
    protected CmsCoverageApiService $cmsService;

    public function __construct(
        MacValidationService $validationService,
        CmsCoverageApiService $cmsService
    ) {
        parent::__construct();
        $this->validationService = $validationService;
        $this->cmsService = $cmsService;
    }

    public function handle()
    {
        $this->info('Testing MAC Validation API Integration');
        $this->info('=====================================');
        $this->newLine();

        $runQuick = !$this->option('thorough') || $this->option('quick');
        $runThorough = !$this->option('quick') || $this->option('thorough');

        if ($runQuick) {
            $this->testQuickCheck();
        }

        if ($runThorough) {
            $this->newLine();
            $this->testThoroughValidation();
        }

        $this->newLine();
        $this->info('✨ MAC Validation API testing complete!');
    }

    protected function testQuickCheck()
    {
        $this->info('1. Testing Quick Check with CMS API');
        $this->line('   Patient ZIP: 90210 (California)');
        $this->line('   Service Codes: Q4151, 97597');
        $this->line('   Wound Type: DFU (Diabetic Foot Ulcer)');
        $this->newLine();

        try {
            // Get state from ZIP
            $state = 'CA'; // 90210 is California

            // Test CMS API directly
            $this->line('   Testing CMS API connection...');
            $macInfo = $this->cmsService->getMACJurisdiction($state, '90210');

            if ($macInfo) {
                $this->info('   ✓ CMS API Connected');
                $this->line('   MAC Contractor: ' . ($macInfo['contractor'] ?? 'Unknown'));
                $this->line('   Jurisdiction: ' . ($macInfo['jurisdiction'] ?? 'Unknown'));
            } else {
                $this->warn('   ⚠ MAC jurisdiction lookup returned no data');
            }

            // Test optimized quick check
            $this->newLine();
            $this->line('   Testing optimized quick check...');
            $cmsData = $this->cmsService->performOptimizedQuickCheck(
                ['Q4151', '97597'],
                $state,
                'dfu'
            );

            if ($cmsData['success']) {
                $this->info('   ✓ Optimized Quick Check Successful');
                $this->line('   API Calls: ' . ($cmsData['summary']['total_api_calls'] ?? 'N/A'));
                $this->line('   Response Time: ' . ($cmsData['summary']['total_response_time_ms'] ?? 'N/A') . 'ms');
                $this->line('   Local Policies Found: ' . ($cmsData['summary']['local_policies_found'] ?? 0));
                $this->line('   National Policies Found: ' . ($cmsData['summary']['national_policies_found'] ?? 0));

                // Check service coverage
                if (isset($cmsData['coverage_insights']['service_coverage'])) {
                    $this->newLine();
                    $this->line('   Service Coverage Analysis:');
                    foreach ($cmsData['coverage_insights']['service_coverage'] as $coverage) {
                        $status = match($coverage['status']) {
                            'likely_covered' => '<fg=green>✓ Likely Covered</>',
                            'needs_review' => '<fg=yellow>⚠ Needs Review</>',
                            'not_covered' => '<fg=red>✗ Not Covered</>',
                            default => '<fg=gray>? Unknown</>'
                        };
                        $this->line(sprintf('     %s: %s - %s',
                            $coverage['code'],
                            $status,
                            $coverage['description'] ?? 'No description'
                        ));
                    }
                }
            } else {
                $this->error('   ✗ Optimized Quick Check Failed');
                $this->error('   Error: ' . ($cmsData['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->error('   ✗ Quick Check Failed');
            $this->error('   Error: ' . $e->getMessage());
            Log::error('MAC validation quick check test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function testThoroughValidation()
    {
        $this->info('2. Testing Thorough Validation');
        $this->line('   Patient: Los Angeles, CA 90210');
        $this->line('   Provider: Test Wound Care Center');
        $this->line('   Wound Type: DFU, 8 weeks duration');
        $this->newLine();

        try {
            // Create a mock order for testing
            $mockOrderData = [
                'patient_state' => 'CA',
                'patient_zip' => '90210',
                'provider_specialty' => 'wound_care',
                'diagnoses' => ['L97.429', 'E11.9'],
                'wound_type' => 'dfu',
                'service_codes' => ['Q4151', '97597']
            ];

            $this->line('   Testing comprehensive validation...');

            // Test ValidationBuilderEngine
            $validationEngine = app(\App\Services\ValidationBuilderEngine::class);
            $rules = $validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');

            if (!empty($rules)) {
                $this->info('   ✓ Validation Rules Built');
                $this->line('   Rule Categories: ' . count($rules));
                foreach (array_keys($rules) as $category) {
                    $this->line('     - ' . str_replace('_', ' ', $category));
                }
            } else {
                $this->warn('   ⚠ No validation rules found');
            }

            // Test CMS compliance data
            $this->newLine();
            $this->line('   Testing CMS compliance data...');
            $lcds = $this->cmsService->getLCDsBySpecialty('wound_care_specialty', 'CA');
            $ncds = $this->cmsService->getNCDsBySpecialty('wound_care_specialty');

            $this->line('   LCDs Found: ' . count($lcds));
            $this->line('   NCDs Found: ' . count($ncds));

            if (count($lcds) > 0 || count($ncds) > 0) {
                $this->info('   ✓ CMS Compliance Data Available');
            } else {
                $this->warn('   ⚠ No CMS compliance data found');
            }

        } catch (\Exception $e) {
            $this->error('   ✗ Thorough Validation Failed');
            $this->error('   Error: ' . $e->getMessage());
            Log::error('MAC validation thorough test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
