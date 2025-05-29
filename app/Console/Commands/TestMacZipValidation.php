<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MedicareMacValidationService;

class TestMacZipValidation extends Command
{
    protected $signature = 'test:mac-zip-validation {zip?} {state?}';
    protected $description = 'Test MAC validation using patient ZIP code instead of facility address';

    public function handle(MedicareMacValidationService $macValidationService)
    {
        $this->info('Testing MAC Validation with Patient ZIP Code');
        $this->line('===============================================');

        // Test cases
        $testCases = [
            ['zip' => '90210', 'state' => 'CA', 'description' => 'Beverly Hills, CA (Standard)'],
            ['zip' => '06830', 'state' => 'CT', 'description' => 'Greenwich, CT (Special ZIP)'],
            ['zip' => '64108', 'state' => 'MO', 'description' => 'Kansas City, MO (Metro Area)'],
            ['zip' => '33101', 'state' => 'FL', 'description' => 'Miami, FL (Standard)'],
            ['zip' => '10001', 'state' => 'NY', 'description' => 'New York, NY (Standard)'],
            ['zip' => null, 'state' => 'TX', 'description' => 'Texas (No ZIP - State Only)'],
        ];

        // Allow override with command arguments
        if ($this->argument('zip') || $this->argument('state')) {
            $testCases = [[
                'zip' => $this->argument('zip'),
                'state' => $this->argument('state'),
                'description' => 'Custom Test Case'
            ]];
        }

        foreach ($testCases as $testCase) {
            $this->line('');
            $this->info("Testing: {$testCase['description']}");
            $this->line("ZIP: {$testCase['zip']}, State: {$testCase['state']}");

            try {
                // Use the private method via reflection for testing
                $reflection = new \ReflectionClass($macValidationService);
                $method = $reflection->getMethod('getMacContractorByPatientZip');
                $method->setAccessible(true);

                $result = $method->invoke(
                    $macValidationService,
                    $testCase['zip'],
                    $testCase['state']
                );

                $this->line("✓ MAC Contractor: {$result['contractor']}");
                $this->line("✓ Jurisdiction: {$result['jurisdiction']}");
                $this->line("✓ Addressing Method: {$result['addressing_method']}");
                $this->line("✓ Website: " . ($result['website'] ?? 'N/A'));

                if (isset($result['zip_override_reason'])) {
                    $this->warn("⚠ Special ZIP Override: {$result['zip_override_reason']}");
                }

            } catch (\Exception $e) {
                $this->error("✗ Error: " . $e->getMessage());
            }

            $this->line('---');
        }

        $this->line('');
        $this->info('MAC Validation Test Complete!');

        // Show benefits summary
        $this->line('');
        $this->comment('Benefits of Patient ZIP-based MAC Validation:');
        $this->line('• Complies with CMS billing requirements');
        $this->line('• Handles cross-border metropolitan areas');
        $this->line('• Provides accurate LCD/NCD determination');
        $this->line('• Maintains audit trail of addressing methods');
        $this->line('• Graceful fallback for incomplete data');
    }
}
