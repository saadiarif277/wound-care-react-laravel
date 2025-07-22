<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\FormFillingOptimizer;

class TestFormOptimization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test-form-optimization {--stage=all : Stage to test (patient_info, clinical_data, insurance_data, docuseal_prefill, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AI Form Filling Optimizer service';

    /**
     * Execute the console command.
     */
    public function handle(FormFillingOptimizer $optimizer)
    {
        $stage = $this->option('stage');
        
        $this->info('🤖 Testing AI Form Filling Optimizer');
        $this->info('=====================================');
        
        // Test data sets for different stages
        $testData = [
            'patient_info' => [
                'patient_first_name' => 'john',
                'patient_last_name' => 'SMITH',
                'patient_phone' => '5551234567',
                'patient_dob' => '01/15/1980',
                'patient_gender' => '',
            ],
            'clinical_data' => [
                'wound_type' => 'diabetic_foot_ulcer',
                'wound_location' => 'Left foot plantar surface',
                'wound_size_length' => '3.5',
                'wound_size_width' => '2.8',
                'wound_size_depth' => '0.5',
                'diagnosis_code' => '',
            ],
            'insurance_data' => [
                'primary_insurance_name' => 'Medicare Advantage PPO',
                'primary_member_id' => '123456789A',
                'primary_plan_type' => '',
                'patient_member_id' => '987654321',
            ],
            'docuseal_prefill' => [
                'patient_dob' => '1980-01-15',
                'expected_service_date' => '2025-01-20',
                'place_of_service' => '11',
                'hospice_status' => true,
                'medicare_part_b_authorized' => false,
                'global_period_status' => true,
            ],
        ];
        
        // Run tests based on stage
        if ($stage === 'all') {
            foreach ($testData as $testStage => $data) {
                $this->testStage($optimizer, $testStage, $data);
            }
        } else {
            if (!isset($testData[$stage])) {
                $this->error("Invalid stage: {$stage}");
                return 1;
            }
            $this->testStage($optimizer, $stage, $testData[$stage]);
        }
        
        $this->info("\n✅ Test completed successfully!");
        return 0;
    }
    
    private function testStage(FormFillingOptimizer $optimizer, string $stage, array $data)
    {
        $this->info("\n📋 Testing stage: {$stage}");
        $this->info('Original data:');
        $this->table(['Field', 'Value'], $this->formatDataForTable($data));
        
        $enhanced = $optimizer->enhanceFormData($data, $stage);
        
        $this->info("\nEnhanced data:");
        $this->table(['Field', 'Value'], $this->formatDataForTable($enhanced));
        
        // Show what was changed/added
        $changes = array_diff_assoc($enhanced, $data);
        $additions = array_diff_key($enhanced, $data);
        
        if (!empty($changes) || !empty($additions)) {
            $this->info("\n🔄 Changes and additions:");
            foreach ($changes as $key => $value) {
                if (isset($data[$key])) {
                    $oldValue = is_array($data[$key]) ? json_encode($data[$key]) : $data[$key];
                    $newValue = is_array($value) ? json_encode($value) : $value;
                    $this->line("  - {$key}: '{$oldValue}' → '{$newValue}'");
                }
            }
            foreach ($additions as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $this->line("  + {$key}: '{$displayValue}'");
            }
        } else {
            $this->warn("  No changes made");
        }
    }
    
    private function formatDataForTable(array $data): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            } elseif ($value === null) {
                $value = 'null';
            }
            $rows[] = [$key, $value];
        }
        return $rows;
    }
} 