<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\EnhancedDocuSealMappingService;
use App\Services\DocuSealService;
use App\Services\DocuSealFieldMapper;
use App\Services\DocuSealFieldValidator;
use App\Models\Docuseal\DocusealTemplate;

class TestEnhancedDocuSealMapping extends Command
{
    protected $signature = 'test:enhanced-docuseal-mapping {manufacturer=MedLife}';
    protected $description = 'Test the enhanced DocuSeal mapping with full context';

    public function handle()
    {
        $manufacturerName = $this->argument('manufacturer');
        
        $this->info("Testing Enhanced DocuSeal Mapping for: $manufacturerName");
        $this->info("=" . str_repeat("=", 50));
        
        // Test input data (anonymized for testing purposes)
        $inputData = [
            'patient_name' => 'Test Patient',
            'patient_dob' => '1990-01-01',
            'patient_gender' => 'Male',
            'patient_phone' => '5555551234',
            'patient_member_id' => 'TEST123456',
            'provider_name' => 'Test Provider',
            'provider_npi' => '1234567890',
            'provider_tax_id' => '12-3456789',
            'facility_name' => 'Test Clinic',
            'facility_address' => '123 Test St',
            'facility_city' => 'Test City',
            'facility_state' => 'FL',
            'facility_zip' => '12345',
            'primary_insurance_name' => 'Medicare',
            'member_id' => 'TEST123456',
            'wound_location' => 'Lower extremity',
            'wound_size_length' => '5',
            'wound_size_width' => '3',
            'diagnosis_code' => 'L97.123',
            'procedure_date' => now()->format('Y-m-d'),
            'sales_rep_name' => 'Test Rep',
            'office_contact_name' => 'Test Contact',
            'office_contact_email' => 'test@example.com'
        ];
        
        // Add manufacturer-specific fields
        if ($manufacturerName === 'MEDLIFE SOLUTIONS') {
            $inputData['amnio_amp_size'] = '4x4';
        }
        
        // Get template
        $template = DocusealTemplate::whereHas('manufacturer', function($q) use ($manufacturerName) {
            $q->where('name', $manufacturerName);
        })->where('document_type', 'IVR')->first();
        
        if (!$template) {
            $this->error("No IVR template found for manufacturer: $manufacturerName");
            return 1;
        }
        
        $this->info("\nUsing template: {$template->template_name} (ID: {$template->docuseal_template_id})");
        
        // Get template fields
        $docuSealService = app(DocuSealService::class);
        $templateFields = [];
        
        try {
            $templateFields = $docuSealService->getTemplateFieldsFromAPI($template->docuseal_template_id);
            $this->info("Template has " . count($templateFields) . " fields");
        } catch (\Exception $e) {
            $this->error("Failed to get template fields: " . $e->getMessage());
        }
        
        // Test 1: Static Mapping
        $this->info("\n1. Testing Static Mapping:");
        $this->info("-" . str_repeat("-", 30));
        
        $formId = DocuSealFieldMapper::getFormIdForManufacturer($manufacturerName);
        $staticMapped = DocuSealFieldMapper::mapFieldsForManufacturer($inputData, $manufacturerName);
        
        $this->info("Form ID: $formId");
        $this->info("Mapped " . count($staticMapped) . " fields");
        
        // Show sample mappings
        $this->table(['Field Name', 'Value'], array_map(function($field) {
            return [$field['name'], substr($field['default_value'], 0, 50)];
        }, array_slice($staticMapped, 0, 5)));
        
        // Test 2: Enhanced AI Mapping (if available)
        if (config('ai.enabled') && config('azure.ai_foundry.enabled')) {
            $this->info("\n2. Testing Enhanced AI Mapping:");
            $this->info("-" . str_repeat("-", 30));
            
            try {
                $enhancedMapper = app(EnhancedDocuSealMappingService::class);
                $aiMapped = $enhancedMapper->mapFieldsWithEnhancedContext(
                    $inputData,
                    $templateFields,
                    $manufacturerName,
                    $formId
                );
                
                $this->info("AI mapped " . count($aiMapped) . " fields");
                
                $this->table(['Field Name', 'Value'], array_map(function($field) {
                    return [$field['name'], substr($field['default_value'], 0, 50)];
                }, array_slice($aiMapped, 0, 5)));
                
            } catch (\Exception $e) {
                $this->error("Enhanced AI mapping failed: " . $e->getMessage());
            }
        } else {
            $this->warn("\n2. AI mapping is disabled");
        }
        
        // Test 3: Field Validation
        $this->info("\n3. Testing Field Validation:");
        $this->info("-" . str_repeat("-", 30));
        
        if (!empty($templateFields)) {
            // Test with invalid fields
            $testFields = array_merge($staticMapped, [
                ['name' => 'invalid_field', 'default_value' => 'test'],
                ['name' => 'provider_npi', 'default_value' => '1234567890'], // Should be transformed
                ['name' => 'facility_name', 'default_value' => 'Test Clinic'] // Should be transformed
            ]);
            
            $validatedFields = DocuSealFieldValidator::validateAndCleanFields($testFields, $templateFields);
            
            $this->info("Input fields: " . count($testFields));
            $this->info("Validated fields: " . count($validatedFields));
            
            // Check for transformations
            $transformations = [];
            foreach ($validatedFields as $field) {
                if ($field['name'] === 'Physician NPI' && in_array('provider_npi', array_column($testFields, 'name'))) {
                    $transformations[] = "provider_npi → Physician NPI";
                }
                if ($field['name'] === 'Practice Name' && in_array('facility_name', array_column($testFields, 'name'))) {
                    $transformations[] = "facility_name → Practice Name";
                }
            }
            
            if (!empty($transformations)) {
                $this->info("\nField transformations applied:");
                foreach ($transformations as $transformation) {
                    $this->line("  - $transformation");
                }
            }
        }
        
        // Test 4: Check for common issues
        $this->info("\n4. Checking for Common Issues:");
        $this->info("-" . str_repeat("-", 30));
        
        $issues = [];
        
        // Check if critical fields are mapped
        $criticalFields = ['Patient Name', 'Physician Name', 'Physician NPI'];
        foreach ($criticalFields as $critical) {
            $found = false;
            foreach ($staticMapped as $field) {
                if ($field['name'] === $critical) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $issues[] = "Missing critical field: $critical";
            }
        }
        
        // Check for manufacturer-specific fields
        if ($manufacturerName === 'MEDLIFE SOLUTIONS' && isset($inputData['amnio_amp_size'])) {
            $found = false;
            foreach ($staticMapped as $field) {
                if ($field['name'] === 'amnio_amp_size') {
                    $found = true;
                    $this->info("✅ MEDLIFE SOLUTIONS specific field 'amnio_amp_size' is mapped");
                    break;
                }
            }
            if (!$found) {
                $issues[] = "Missing MEDLIFE SOLUTIONS specific field: amnio_amp_size";
            }
        }
        
        if (empty($issues)) {
            $this->info("✅ No common issues found");
        } else {
            $this->warn("Found " . count($issues) . " issues:");
            foreach ($issues as $issue) {
                $this->warn("  - $issue");
            }
        }
        
        return 0;
    }
}