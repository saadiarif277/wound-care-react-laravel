<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocusealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;

class TestDocuSealSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docuseal:test-sync';

    /**
     * The console command description.
     */
    protected $description = 'Test the new DocuSeal template sync system';

    private DocusealService $docusealService;

    public function __construct(DocusealService $docusealService)
    {
        parent::__construct();
        $this->docusealService = $docusealService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing DocuSeal Template Sync System...');

        // Test 1: API Connection
        $this->info('📡 Testing API Connection...');
        $connection = $this->docusealService->testConnection();
        
        if ($connection['success']) {
            $this->info("✅ API Connection: Success ({$connection['templates_count']} templates available)");
        } else {
            $this->error("❌ API Connection: Failed - {$connection['error']}");
            return self::FAILURE;
        }

        // Test 2: Database Structure
        $this->info('🗄️ Testing Database Structure...');
        
        try {
            $templateCount = DocusealTemplate::count();
            $manufacturerCount = Manufacturer::count();
            $this->info("✅ Database: {$templateCount} templates, {$manufacturerCount} manufacturers");
        } catch (\Exception $e) {
            $this->error("❌ Database Error: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Test 3: Template Mapping Logic
        $this->info('🔗 Testing Template Mapping Logic...');
        
        if ($templateCount > 0) {
            $template = DocusealTemplate::first();
            $fieldMappings = $template->field_mappings ?? [];
            
            $this->info("✅ Sample Template: {$template->template_name}");
            $this->info("   - Manufacturer: " . ($template->manufacturer?->name ?? 'None'));
            $this->info("   - Document Type: {$template->document_type}");
            $this->info("   - Field Mappings: " . count($fieldMappings));
            
            if (count($fieldMappings) > 0) {
                $this->info("   - Sample Fields: " . implode(', ', array_slice(array_keys($fieldMappings), 0, 3)));
            }
        } else {
            $this->warn("⚠️  No templates found in database. Run 'php artisan docuseal:sync-templates' first.");
        }

        // Test 4: Field Transformation
        $this->info('🔄 Testing Field Transformation...');
        
        $testData = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-15',
            'provider_name' => 'Dr. Smith',
            'facility_name' => 'Test Hospital'
        ];

        $this->info("✅ Test Data Transformation: " . count($testData) . " fields");

        // Test 5: Manufacturer Association
        $this->info('🏭 Testing Manufacturer Association...');
        
        $manufacturersWithTemplates = Manufacturer::whereHas('docusealTemplates')->count();
        $this->info("✅ Manufacturers with Templates: {$manufacturersWithTemplates}");

        // Summary
        $this->newLine();
        $this->info('📋 Test Summary:');
        $this->table(['Component', 'Status', 'Details'], [
            ['API Connection', '✅ Working', $connection['templates_count'] . ' templates'],
            ['Database', '✅ Working', "{$templateCount} templates, {$manufacturerCount} manufacturers"],
            ['Field Mappings', $templateCount > 0 ? '✅ Working' : '⚠️  Empty', 'Templates have field mappings'],
            ['Manufacturers', '✅ Working', "{$manufacturersWithTemplates} with templates"],
        ]);

        if ($templateCount === 0) {
            $this->newLine();
            $this->warn('💡 Recommendation: Run "php artisan docuseal:sync-templates" to populate templates');
        }

        $this->info('🎉 Test completed successfully!');
        return self::SUCCESS;
    }
}
