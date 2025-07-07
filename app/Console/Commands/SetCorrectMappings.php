<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetCorrectMappings extends Command
{
    protected $signature = 'docuseal:set-correct-mappings {manufacturer_id}';
    protected $description = 'Set correct field mappings for a manufacturer';

    public function handle()
    {
        $manufacturerId = $this->argument('manufacturer_id');
        
        $template = DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');
        if (!$template) {
            $this->error("No template found for manufacturer {$manufacturerId}");
            return 1;
        }

        // Based on the actual Docuseal template fields we saw, create correct mappings
        $correctMappings = [
            // Patient Information
            'patient_name' => 'Patient Name',
            'patient_dob' => 'Patient DOB', 
            'patient_phone' => 'Office Contact Name', // This might need adjustment
            'patient_gender' => 'Gender',
            'patient_address' => 'Patient Address',
            
            // Provider Information  
            'provider_name' => 'Physician Name',
            'provider_npi' => 'Physician NPI',
            'provider_phone' => 'Office Contact Name',
            'provider_email' => 'Office Contact Email',
            
            // Facility Information
            'facility_name' => 'Practice Name',
            'facility_address' => 'Facility Address',
            
            // Insurance Information
            'primary_insurance_name' => 'Primary Insurance',
            'primary_policy_number' => 'Member ID',
            
            // Medical Information
            'wound_type' => 'Wound location',
            'wound_location' => 'Wound location',
            'wound_size' => 'Size of Graft Requested',
            'wound_size_width' => 'W',
            'wound_size_length' => 'L',
            
            // Service Information
            'service_date' => 'Procedure Date',
            'diagnosis_code' => 'ICD-10',
            'procedure_code' => 'CPT',
            'units_requested' => 'Size of Graft Requested',
            
            // Organization Information
            'company_name' => 'Distributor/Company',
            'organization_name' => 'Distributor/Company',
            
            // Signatures
            'provider_signature' => 'Provider Signature',
            'provider_signature_date' => 'Signature Date'
        ];

        $this->info("Setting correct field mappings for {$template->template_name}");
        $this->table(['Form Field', 'Docuseal Field'], 
            collect($correctMappings)->map(fn($docuSealField, $formField) => [$formField, $docuSealField])->toArray()
        );

        if ($this->confirm('Apply these mappings?')) {
            $template->update(['field_mappings' => $correctMappings]);
            $this->info("✅ Updated field mappings successfully");
        }

        return 0;
    }
}
