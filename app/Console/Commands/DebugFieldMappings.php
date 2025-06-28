<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use App\Services\DocusealService;
use Illuminate\Support\Facades\Http;

class DebugFieldMappings extends Command
{
    protected $signature = 'docuseal:debug-fields 
                            {manufacturer_id : Manufacturer ID to debug}
                            {--show-template : Show actual DocuSeal template fields}
                            {--test-mapping : Test field mapping with sample data}';

    protected $description = 'Debug field mapping issues for DocuSeal templates';

    public function handle()
    {
        $manufacturerId = $this->argument('manufacturer_id');
        
        $this->info("🔍 Debugging Field Mappings for Manufacturer ID: {$manufacturerId}");
        $this->info("================================================================");

        // Get manufacturer and template
        $manufacturer = Manufacturer::find($manufacturerId);
        if (!$manufacturer) {
            $this->error("❌ Manufacturer not found");
            return 1;
        }

        $template = DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');
        if (!$template) {
            $this->error("❌ No default IVR template found");
            return 1;
        }

        $this->info("✅ Manufacturer: {$manufacturer->name}");
        $this->info("✅ Template: {$template->template_name} (DocuSeal ID: {$template->docuseal_template_id})");

        // Show current field mappings
        $this->showCurrentMappings($template);

        if ($this->option('show-template')) {
            $this->showDocuSealTemplateFields($template);
        }

        if ($this->option('test-mapping')) {
            $this->testFieldMapping($template);
        }

        return 0;
    }

    private function showCurrentMappings($template)
    {
        $this->info("\n📋 Current Field Mappings in Database:");
        $this->line("────────────────────────────────────");
        
        $mappings = $template->field_mappings;
        if (!is_array($mappings) || empty($mappings)) {
            $this->warn("⚠️ No field mappings found or invalid format");
            return;
        }

        $this->table(['Form Field', 'DocuSeal Field', 'Type'], 
            collect($mappings)->map(function($value, $key) {
                if (is_array($value)) {
                    return [
                        $key,
                        $value['field_label'] ?? 'Unknown',
                        'Complex Object'
                    ];
                } else {
                    return [
                        $key,
                        $value,
                        'Simple String'
                    ];
                }
            })->toArray()
        );

        $this->info("\n🔍 Detailed Mapping Analysis:");
        foreach ($mappings as $formField => $docuSealField) {
            $this->line("• {$formField}:");
            if (is_array($docuSealField)) {
                $this->line("  → DocuSeal Label: " . ($docuSealField['field_label'] ?? 'N/A'));
                $this->line("  → Field Type: " . ($docuSealField['field_type'] ?? 'N/A'));
                $this->line("  → Required: " . (($docuSealField['required'] ?? false) ? 'Yes' : 'No'));
                $this->line("  → System Field: " . ($docuSealField['system_field'] ?? 'N/A'));
            } else {
                $this->line("  → Direct mapping to: {$docuSealField}");
            }
        }
    }

    private function showDocuSealTemplateFields($template)
    {
        $this->info("\n🌐 Fetching Actual DocuSeal Template Fields:");
        $this->line("─────────────────────────────────────────");

        try {
            $apiKey = config('docuseal.api_key');
            $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$apiUrl}/templates/{$template->docuseal_template_id}");

            if ($response->successful()) {
                $templateData = $response->json();
                
                if (isset($templateData['fields'])) {
                    $this->info("✅ Found " . count($templateData['fields']) . " fields in DocuSeal template:");
                    
                    $tableData = [];
                    foreach ($templateData['fields'] as $field) {
                        $tableData[] = [
                            $field['name'] ?? 'N/A',
                            $field['type'] ?? 'N/A',
                            ($field['required'] ?? false) ? 'Yes' : 'No',
                            $field['placeholder'] ?? 'N/A'
                        ];
                    }
                    
                    $this->table(['Field Name', 'Type', 'Required', 'Placeholder'], $tableData);
                } else {
                    $this->warn("⚠️ No fields found in template response");
                    $this->line("Response keys: " . implode(', ', array_keys($templateData)));
                }
            } else {
                $this->error("❌ Failed to fetch template: " . $response->status());
                $this->error("Response: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
        }
    }

    private function testFieldMapping($template)
    {
        $this->info("\n🧪 Testing Field Mapping with Sample Data:");
        $this->line("──────────────────────────────────────");

        // Generate sample form data (same as in our test)
        $sampleData = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_email' => 'john.doe@example.com',
            'patient_phone' => '555-0123',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'provider_email' => 'dr.smith@clinic.com',
            'provider_phone' => '555-0456',
            'facility_name' => 'Test Medical Center',
            'facility_address' => '123 Medical Dr, City, ST 12345',
            'wound_type' => 'Diabetic Ulcer',
            'wound_location' => 'Lower Left Leg',
            'wound_size' => '5.2 cm²',
            'service_date' => now()->format('Y-m-d'),
            'diagnosis_code' => 'E11.621',
            'procedure_code' => 'Q4250',
            'units_requested' => '1',
            'medical_necessity' => 'Patient has chronic diabetic ulcer requiring advanced wound care treatment',
            'organization_name' => 'MSC Wound Care',
            'company_name' => 'MSC Wound Care Solutions'
        ];

        $this->info("📤 Sample form data to be mapped:");
        foreach ($sampleData as $key => $value) {
            $this->line("  {$key} = {$value}");
        }

        // Test the mapping using DocuSeal service
        try {
            $docuSealService = app(DocusealService::class);
            $mappedFields = $docuSealService->mapFieldsFromArray($sampleData, $template);

            $this->info("\n✅ Mapped fields result:");
            if (empty($mappedFields)) {
                $this->error("❌ No fields were mapped!");
            } else {
                foreach ($mappedFields as $docuSealField => $value) {
                    $this->line("  {$docuSealField} = {$value}");
                }
                $this->info("📊 Total mapped: " . count($mappedFields) . " out of " . count($sampleData) . " input fields");
            }

        } catch (\Exception $e) {
            $this->error("❌ Field mapping failed: " . $e->getMessage());
        }

        // Show what should be mapped vs what was mapped
        $this->info("\n🔍 Mapping Analysis:");
        $mappings = $template->field_mappings;
        
        foreach ($sampleData as $formField => $formValue) {
            if (isset($mappings[$formField])) {
                $this->info("✅ {$formField} → should map");
            } else {
                $this->warn("⚠️ {$formField} → no mapping defined");
            }
        }

        // Show reverse mapping
        $this->info("\n🔄 Reverse Analysis (Template → Form Data):");
        foreach ($mappings as $formField => $docuSealField) {
            if (isset($sampleData[$formField])) {
                $this->info("✅ {$formField} → has form data");
            } else {
                $this->warn("⚠️ {$formField} → missing in form data");
            }
        }
    }
}
