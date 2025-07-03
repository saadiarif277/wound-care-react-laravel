<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Docuseal\DocusealTemplate;

class FixFieldMappings extends Command
{
    protected $signature = 'docuseal:fix-field-mappings 
                            {--manufacturer= : Fix mappings for specific manufacturer ID}
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Fix field mapping structure for Docuseal templates';

    public function handle()
    {
        $manufacturerId = $this->option('manufacturer');
        $isDryRun = $this->option('dry-run');

        $this->info("🔧 Fixing Docuseal Field Mappings");
        $this->info("================================");
        $this->info("Mode: " . ($isDryRun ? "DRY RUN" : "LIVE FIX"));

        // Get templates to fix
        $query = DocusealTemplate::where('document_type', 'IVR');
        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }
        $templates = $query->get();

        $this->info("Found " . $templates->count() . " IVR templates to process");

        foreach ($templates as $template) {
            $this->processTemplate($template, $isDryRun);
        }

        return 0;
    }

    private function processTemplate($template, $isDryRun)
    {
        $manufacturerName = $template->manufacturer->name ?? 'Unknown';
        $this->info("\n🔍 Processing: {$template->template_name} ({$manufacturerName})");

        $currentMappings = $template->field_mappings;
        if (!is_array($currentMappings) || empty($currentMappings)) {
            $this->warn("  ⚠️ No mappings to fix");
            return;
        }

        // The issue: Current mappings are backwards
        // They're stored as: docuseal_field => form_field
        // But the service expects: form_field => docuseal_field

        $fixedMappings = [];
        
        foreach ($currentMappings as $key => $value) {
            if (is_array($value)) {
                // Complex mapping object - extract the actual field name
                $docuSealFieldName = $value['field_label'] ?? $key;
                $systemField = $value['system_field'] ?? $key;
                
                // Convert to correct format: form_field => docuseal_field
                $formFieldName = $this->convertToFormFieldName($systemField);
                // Also handle the original key name as form field name
                $keyAsFormField = $this->convertToFormFieldName($key);
                $fixedMappings[$formFieldName] = $docuSealFieldName;
                if ($formFieldName !== $keyAsFormField) {
                    $fixedMappings[$keyAsFormField] = $docuSealFieldName;
                }
                
                $this->line("  📝 {$key} -> {$formFieldName} => {$docuSealFieldName}");
            } else {
                // Simple string mapping
                $docuSealFieldName = $value;
                $formFieldName = $this->convertToFormFieldName($key);
                $fixedMappings[$formFieldName] = $docuSealFieldName;
                
                $this->line("  📝 {$key} -> {$formFieldName} => {$docuSealFieldName}");
            }
        }

        // Add common fields that might be missing
        $commonMappings = [
            'company_name' => 'Distributor/Company',
            'organization_name' => 'Distributor/Company',
            'patient_gender' => 'Gender',
            'diagnosis_code' => 'ICD-10',
            'procedure_code' => 'CPT',
            'wound_size' => 'Total Size',
            'units_requested' => 'Size of Graft Requested'
        ];

        foreach ($commonMappings as $formField => $docuSealField) {
            if (!isset($fixedMappings[$formField])) {
                $fixedMappings[$formField] = $docuSealField;
                $this->line("  ➕ Added: {$formField} => {$docuSealField}");
            }
        }

        $this->info("  📊 Original mappings: " . count($currentMappings));
        $this->info("  📊 Fixed mappings: " . count($fixedMappings));

        if (!$isDryRun) {
            $template->update(['field_mappings' => $fixedMappings]);
            $this->info("  ✅ Updated template mappings");
        } else {
            $this->info("  🔍 DRY RUN: Would update template mappings");
        }
    }

    private function convertToFormFieldName($systemField)
    {
        // Convert system field names to our form field names
        $mapping = [
            'Patient Full Name' => 'patient_name',
            'Patient Name' => 'patient_name',
            'Patient DOB' => 'patient_dob',
            'Patient Phone' => 'patient_phone',
            'Provider Name' => 'provider_name',
            'Physician Name' => 'provider_name',
            'Provider NPI' => 'provider_npi',
            'Physician NPI' => 'provider_npi',
            'Practice NPI' => 'provider_npi',
            'Provider Phone' => 'provider_phone',
            'Facility Name' => 'facility_name',
            'Practice Name' => 'facility_name',
            'Facility Address' => 'facility_address',
            'Wound Type' => 'wound_type',
            'Wound Location' => 'wound_location',
            'Wound Width' => 'wound_size_width',
            'Wound Length' => 'wound_size_length',
            'Date of Service' => 'service_date',
            'Surgery Date' => 'service_date',
            'Procedure Date' => 'service_date',
            'Primary Insurance' => 'primary_insurance_name',
            'Policy Number' => 'primary_policy_number',
            'Diagnosis Code' => 'primary_diagnosis_code',
            'Gender' => 'patient_gender',
            'Patient Address' => 'patient_address',
            'Provider Signature' => 'provider_signature',
            'Signature Date' => 'provider_signature_date'
        ];

        // If we have a direct mapping, use it
        if (isset($mapping[$systemField])) {
            return $mapping[$systemField];
        }

        // Otherwise, convert the field name to snake_case
        $converted = strtolower(preg_replace('/\s+/', '_', trim($systemField)));
        
        // Remove common prefixes/suffixes
        $converted = preg_replace('/^(patient_|provider_|facility_)/', '', $converted);
        
        return $converted;
    }
}
