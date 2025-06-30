<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update MedLife templates with specific field mappings
        $medLifeManufacturer = DB::table('manufacturers')
            ->where('name', 'MEDLIFE SOLUTIONS')
            ->first();
            
        if ($medLifeManufacturer) {
            // Update IVR template
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $medLifeManufacturer->id)
                ->where('document_type', 'IVR')
                ->update([
                    'field_mappings' => json_encode([
                        // Product specific fields
                        'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
                        
                        // Patient fields
                        'patient_name' => ['source' => 'patient_name', 'type' => 'text'],
                        'patient_first_name' => ['source' => 'patient_first_name', 'type' => 'text'],
                        'patient_last_name' => ['source' => 'patient_last_name', 'type' => 'text'],
                        'patient_dob' => ['source' => 'patient_dob', 'type' => 'date'],
                        'patient_gender' => ['source' => 'patient_gender', 'type' => 'radio'],
                        
                        // Provider fields
                        'physician_name' => ['source' => 'physician_name', 'type' => 'text'],
                        'physician_npi' => ['source' => 'physician_npi', 'type' => 'text'],
                        'provider_name' => ['source' => 'provider_name', 'type' => 'text'],
                        'provider_npi' => ['source' => 'provider_npi', 'type' => 'text'],
                        
                        // Facility fields
                        'facility_name' => ['source' => 'facility_name', 'type' => 'text'],
                        'facility_address' => ['source' => 'facility_address', 'type' => 'text'],
                        'facility_city' => ['source' => 'facility_city', 'type' => 'text'],
                        'facility_state' => ['source' => 'facility_state', 'type' => 'text'],
                        'facility_zip' => ['source' => 'facility_zip', 'type' => 'text'],
                        
                        // Clinical fields
                        'wound_location' => ['source' => 'wound_location', 'type' => 'text'],
                        'wound_size' => ['source' => 'wound_size', 'type' => 'text'],
                        'wound_length' => ['source' => 'wound_length', 'type' => 'text'],
                        'wound_width' => ['source' => 'wound_width', 'type' => 'text'],
                        'wound_depth' => ['source' => 'wound_depth', 'type' => 'text'],
                        'diagnosis_code' => ['source' => 'diagnosis_code', 'type' => 'text'],
                        'icd10_code' => ['source' => 'icd10_code', 'type' => 'text'],
                        
                        // Insurance fields
                        'insurance_name' => ['source' => 'insurance_name', 'type' => 'text'],
                        'insurance_id' => ['source' => 'insurance_id', 'type' => 'text'],
                        'policy_number' => ['source' => 'policy_number', 'type' => 'text'],
                        'group_number' => ['source' => 'group_number', 'type' => 'text'],
                    ])
                ]);
                
            // Update Order Form template
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $medLifeManufacturer->id)
                ->where('document_type', 'OrderForm')
                ->update([
                    'field_mappings' => json_encode([
                        // Product fields
                        'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
                        'product_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
                        
                        // Order fields
                        'order_date' => ['source' => 'order_date', 'type' => 'date'],
                        'requested_delivery_date' => ['source' => 'requested_delivery_date', 'type' => 'date'],
                        'po_number' => ['source' => 'po_number', 'type' => 'text'],
                        
                        // Provider signature
                        'provider_signature' => ['source' => 'provider_signature', 'type' => 'signature'],
                        'signature_date' => ['source' => 'signature_date', 'type' => 'date'],
                    ])
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset field mappings to empty
        $medLifeManufacturer = DB::table('manufacturers')
            ->where('name', 'MEDLIFE SOLUTIONS')
            ->first();
            
        if ($medLifeManufacturer) {
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $medLifeManufacturer->id)
                ->update(['field_mappings' => json_encode([])]);
        }
    }
};