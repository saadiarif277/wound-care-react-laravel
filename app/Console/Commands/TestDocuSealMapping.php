<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;

class TestDocuSealMapping extends Command
{
    protected $signature = 'docuseal:test-mapping {manufacturer_id} {--ai : Use AI mapping}';
    protected $description = 'Test DocuSeal field mapping for a manufacturer';

    public function handle()
    {
        $manufacturerId = $this->argument('manufacturer_id');
        $useAI = $this->option('ai');
        
        // Get manufacturer
        $manufacturer = Manufacturer::find($manufacturerId);
        if (!$manufacturer) {
            $this->error("Manufacturer not found: {$manufacturerId}");
            return 1;
        }
        
        $this->info("Testing DocuSeal mapping for: {$manufacturer->name}");
        
        // Get template
        $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('is_active', true)
            ->first();
            
        if (!$template) {
            $this->error("No active template found for manufacturer");
            return 1;
        }
        
        $this->info("Using template: {$template->template_name} (ID: {$template->docuseal_template_id})");
        
        // Sample data based on the manufacturer
        $sampleData = $this->getSampleData($manufacturer->name);
        
        $this->info("\nInput Data:");
        $this->table(['Field', 'Value'], collect($sampleData)->map(fn($v, $k) => [$k, $v])->toArray());
        
        // Get DocuSeal service
        $docuSealService = app(DocuSealService::class);
        
        // Test field discovery
        $this->info("\n🔍 Fetching template fields from DocuSeal API...");
        $templateFields = $docuSealService->getTemplateFieldsFromAPI($template->docuseal_template_id);
        
        if (!empty($templateFields)) {
            $this->info("Found " . count($templateFields) . " fields in template:");
            $this->table(
                ['Field Name', 'Field ID', 'Type', 'Required'],
                collect($templateFields)->map(fn($field, $name) => [
                    $name,
                    $field['id'] ?? 'N/A',
                    $field['type'] ?? 'text',
                    $field['required'] ?? false ? 'Yes' : 'No'
                ])->toArray()
            );
        } else {
            $this->warn("No fields retrieved from DocuSeal API");
        }
        
        // Test mapping
        $this->info("\n🗺️ Testing field mapping...");
        
        if ($useAI) {
            $this->info("Using AI-powered mapping");
            $mappedFields = $docuSealService->mapFieldsWithAI($sampleData, $template);
        } else {
            $this->info("Using static mapping");
            $mappedFields = $docuSealService->mapFieldsFromArray($sampleData, $template);
        }
        
        $this->info("\nMapping Results:");
        if (empty($mappedFields)) {
            $this->error("No fields were mapped!");
        } else {
            $this->info("Mapped " . count($mappedFields) . " fields:");
            $this->table(['Field', 'Value'], collect($mappedFields)->map(fn($v, $k) => [$k, $v])->toArray());
        }
        
        // Check AI configuration
        $this->info("\n🤖 AI Configuration:");
        $this->table(['Setting', 'Value'], [
            ['AI Enabled', config('ai.enabled') ? 'Yes' : 'No'],
            ['AI Provider', config('ai.provider')],
            ['Azure AI Foundry Enabled', config('azure.ai_foundry.enabled') ? 'Yes' : 'No'],
            ['Azure Endpoint Set', !empty(config('azure.ai_foundry.endpoint')) ? 'Yes' : 'No'],
            ['Azure API Key Set', !empty(config('azure.ai_foundry.api_key')) ? 'Yes' : 'No'],
        ]);
        
        return 0;
    }
    
    private function getSampleData(string $manufacturerName): array
    {
        // MedLife specific data
        if (stripos($manufacturerName, 'MedLife') !== false) {
            return [
                'amnio_amp_size' => '4x4',
                'patient_name' => 'John Doe',
                'patient_dob' => '1980-01-15',
                'physician_name' => 'Dr. Jane Smith',
                'physician_npi' => '1234567890',
                'facility_name' => 'City Medical Center',
                'wound_location' => 'Left foot',
                'wound_size' => '4cm x 4cm',
                'diagnosis_code' => 'L97.512'
            ];
        }
        
        // Default sample data
        return [
            'patient_name' => 'Test Patient',
            'patient_dob' => '1990-01-01',
            'provider_name' => 'Dr. Test Provider',
            'provider_npi' => '1234567890',
            'facility_name' => 'Test Facility',
            'diagnosis' => 'Test Diagnosis'
        ];
    }
}