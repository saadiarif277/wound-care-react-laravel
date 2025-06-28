<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocuSealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;

class TestDocuSealFieldMapping extends Command
{
    protected $signature = 'docuseal:test-field-mapping {manufacturer_id=1}';
    protected $description = 'Test DocuSeal field mapping with sample data';

    public function handle()
    {
        $manufacturerId = $this->argument('manufacturer_id');
        
        // Get manufacturer
        $manufacturer = Manufacturer::find($manufacturerId);
        if (!$manufacturer) {
            $this->error("Manufacturer with ID {$manufacturerId} not found");
            return 1;
        }
        
        $this->info("Testing field mapping for manufacturer: {$manufacturer->name}");
        
        // Get IVR template
        $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('document_type', 'IVR')
            ->where('is_active', true)
            ->first();
            
        if (!$template) {
            $this->error("No active IVR template found for manufacturer");
            return 1;
        }
        
        $this->info("Using template: {$template->template_name} (ID: {$template->docuseal_template_id})");
        
        // Sample quick request data
        $sampleData = [
            // Patient Information
            'patient_name' => 'John Doe',
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1970-01-15',
            'patient_display_id' => 'JODO123',
            'patient_member_id' => 'MEM123456',
            'patient_phone' => '(555) 123-4567',
            'patient_email' => 'john.doe@example.com',
            'patient_address_line1' => '123 Main St',
            'patient_city' => 'Houston',
            'patient_state' => 'TX',
            'patient_zip' => '77001',
            
            // Insurance
            'primary_insurance_name' => 'Blue Cross Blue Shield',
            'primary_member_id' => 'BCBS123456',
            'group_number' => 'GRP789',
            
            // Clinical
            'wound_type' => 'Diabetic Foot Ulcer',
            'wound_location' => 'Right foot, plantar surface',
            'wound_size_length' => '3.5',
            'wound_size_width' => '2.0',
            'wound_size_depth' => '0.5',
            'total_wound_size' => '7.0 sq cm',
            'primary_diagnosis_code' => 'E11.621',
            'secondary_diagnosis_code' => 'L97.501',
            
            // Provider
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'provider_email' => 'dr.smith@clinic.com',
            
            // Facility
            'facility_name' => 'Houston Wound Care Center',
            
            // Product
            'product_name' => 'Membrane Wrap',
            'product_code' => 'MW001',
            'product_manufacturer' => $manufacturer->name,
        ];
        
        // Test mapping
        $docuSealService = new DocuSealService();
        $mappedFields = $docuSealService->mapFieldsFromArray($sampleData, $template);
        
        $this->info("\nField Mapping Results:");
        $this->info("====================");
        $this->info("Total fields in template: " . count($template->field_mappings ?? []));
        $this->info("Fields successfully mapped: " . count($mappedFields));
        
        if (count($mappedFields) > 0) {
            $this->info("\nMapped Fields:");
            $this->table(
                ['DocuSeal Field', 'Mapped Value'],
                collect($mappedFields)->map(function ($value, $field) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    return [$field, is_bool($value) ? ($value ? 'Yes' : 'No') : substr((string)$value, 0, 50)];
                })->toArray()
            );
        } else {
            $this->warn("No fields were mapped. This might indicate a problem with field mappings.");
        }
        
        // Show unmapped fields
        $templateFields = array_keys($template->field_mappings ?? []);
        $mappedFieldNames = array_keys($mappedFields);
        $unmappedFields = array_diff($templateFields, $mappedFieldNames);
        
        if (count($unmappedFields) > 0) {
            $this->warn("\nUnmapped fields (no data found):");
            foreach ($unmappedFields as $field) {
                $mapping = $template->field_mappings[$field] ?? [];
                $systemField = $mapping['system_field'] ?? 'unknown';
                $this->line("  - {$field} (looking for: {$systemField})");
            }
        }
        
        return 0;
    }
}