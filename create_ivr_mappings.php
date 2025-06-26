<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Creating common FHIR field mappings...\n";

$commonMappings = [
    // Patient mappings
    ['fhir_path' => 'patient.name', 'variations' => ['Patient Name', 'Patient', 'Name']],
    ['fhir_path' => 'patient.birthDate', 'variations' => ['DOB', 'Patient DOB', 'Date of Birth']],
    ['fhir_path' => 'patient.gender', 'variations' => ['Sex (Male/Female)', 'Gender']],
    ['fhir_path' => 'patient.address', 'variations' => ['Address', 'Patient Address']],
    ['fhir_path' => 'patient.address.city', 'variations' => ['City']],
    ['fhir_path' => 'patient.address.state', 'variations' => ['State']],
    ['fhir_path' => 'patient.address.postalCode', 'variations' => ['Zip']],
    ['fhir_path' => 'patient.telecom.phone', 'variations' => ['Patient Phone', 'Phone', 'Home Phone', 'Mobile']],
    ['fhir_path' => 'patient.telecom.fax', 'variations' => ['Patient Fax/Email', 'Patient Fax']],
    
    // Provider mappings
    ['fhir_path' => 'practitioner.name', 'variations' => ['Provider Name', 'Physician Name', 'Treating Physician', 'Physician Name (line entries)']],
    ['fhir_path' => 'practitioner.identifier.npi', 'variations' => ['Provider NPI', 'Physician NPI', 'Treating Physician NPI', 'NPI']],
    ['fhir_path' => 'practitioner.identifier.tin', 'variations' => ['Provider Tax ID#', 'Tax ID', 'Physician TIN', 'Treating Physician Tax ID', 'Provider Tax ID']],
    ['fhir_path' => 'practitioner.identifier.ptan', 'variations' => ['Provider Medicare Provider #', 'Physician PTAN', 'Treating Physician PTAN', 'Practice PTAN']],
    ['fhir_path' => 'practitioner.telecom.phone', 'variations' => ['Physician Phone', 'Provider Phone', 'Treating Physician Phone']],
    ['fhir_path' => 'practitioner.telecom.fax', 'variations' => ['Physician Fax', 'Provider Fax', 'Treating Physician Fax', 'Fax']],
    
    // Facility mappings
    ['fhir_path' => 'organization.name', 'variations' => ['Facility Name', 'Practice Name', 'Clinic Name', 'Facility Name (line entries)']],
    ['fhir_path' => 'organization.address', 'variations' => ['Facility Address', 'Practice Address']],
    ['fhir_path' => 'organization.address.city', 'variations' => ['Facility City']],
    ['fhir_path' => 'organization.address.state', 'variations' => ['Facility State']],
    ['fhir_path' => 'organization.address.postalCode', 'variations' => ['Facility Zip']],
    ['fhir_path' => 'organization.identifier.npi', 'variations' => ['Facility NPI', 'Practice NPI']],
    ['fhir_path' => 'organization.identifier.tin', 'variations' => ['Facility Tax ID#', 'Facility TIN', 'Facility Tax ID']],
    ['fhir_path' => 'organization.identifier.ptan', 'variations' => ['Facility PTAN']],
    ['fhir_path' => 'organization.contact.name', 'variations' => ['Facility Contact Name', 'Office Contact Name']],
    ['fhir_path' => 'organization.contact.phone', 'variations' => ['Facility Contact Phone', 'Office Contact Phone']],
    ['fhir_path' => 'organization.contact.fax', 'variations' => ['Facility Contact Fax', 'Office Contact Fax']],
    ['fhir_path' => 'organization.contact.email', 'variations' => ['Facility Contact Email', 'Office Contact Email']],
    
    // Insurance mappings
    ['fhir_path' => 'coverage.payor.display', 'variations' => ['Primary Insurance', 'Primary Insurance Payer Name', 'Insurance Name 1']],
    ['fhir_path' => 'coverage.identifier.value', 'variations' => ['Primary Policy Number', 'Policy Number 1', 'Policy Number', 'Member ID', 'Primary Insurance Policy Number']],
    ['fhir_path' => 'coverage.subscriber.display', 'variations' => ['Primary Subscriber Name', 'Subscriber Name 1', 'Primary Insurance Subscriber Name']],
    ['fhir_path' => 'coverage.subscriber.birthDate', 'variations' => ['Subscriber DOB 1', 'Primary Insurance Subscriber DOB']],
    ['fhir_path' => 'coverage.payor.phone', 'variations' => ['Primary Payer Phone', 'Payer Phone 1', 'Primary Insurance Phone']],
    
    // Secondary Insurance
    ['fhir_path' => 'coverage.secondary.payor.display', 'variations' => ['Secondary Insurance', 'Secondary Insurance Payer Name', 'Insurance Name 2']],
    ['fhir_path' => 'coverage.secondary.identifier.value', 'variations' => ['Secondary Policy Number', 'Policy Number 2', 'Member ID 2', 'Secondary Insurance Policy Number']],
    ['fhir_path' => 'coverage.secondary.subscriber.display', 'variations' => ['Secondary Subscriber Name', 'Subscriber Name 2', 'Secondary Insurance Subscriber Name']],
    ['fhir_path' => 'coverage.secondary.subscriber.birthDate', 'variations' => ['Subscriber DOB 2', 'Secondary Insurance Subscriber DOB']],
    ['fhir_path' => 'coverage.secondary.payor.phone', 'variations' => ['Secondary Payer Phone', 'Payer Phone 2', 'Secondary Insurance Phone']],
    
    // Clinical mappings
    ['fhir_path' => 'condition.code.coding.code', 'variations' => ['ICD-10 Codes', 'ICD-10 Code 1', 'Primary Diagnosis Code', 'Diabetic Ulcer Code 1', 'ICD-10 Diagnosis Code(s)']],
    ['fhir_path' => 'procedure.performedDateTime', 'variations' => ['Date of Procedure', 'Procedure Date', 'Anticipated Application Date', 'Anticipated Treatment Start Date']],
    ['fhir_path' => 'encounter.serviceProvider', 'variations' => ['Place of Service (Office)', 'Place of Service (Physician Office)', 'Treatment Setting (Provider\'s Office)']],
    
    // Wound specific
    ['fhir_path' => 'condition.bodySite', 'variations' => ['Wound Location', 'Wound Location (Legs/Arms/Trunk ≤100 sq cm)']],
    ['fhir_path' => 'observation.woundSize', 'variations' => ['Wound Size(s)', 'Wound Size (Total)', 'Total Wound Size / Medical History']],
    
    // Product mappings
    ['fhir_path' => 'deviceRequest.codeCodeableConcept.coding.code', 'variations' => ['HCPCS Code (Q4277)', 'HCPCS Code (Q4193)', 'HCPCS Code (Q4271)', 'Product Q4205 (Membrane Wrap)']],
];

$mappingsCreated = 0;
$errors = 0;

foreach ($commonMappings as $mapping) {
    foreach ($mapping['variations'] as $fieldVariation) {
        // Find all template fields with this name
        $templateFields = DB::table('ivr_template_fields')
            ->where('field_name', $fieldVariation)
            ->get();
        
        foreach ($templateFields as $field) {
            try {
                // Check if mapping already exists
                $exists = DB::table('ivr_field_mappings')
                    ->where('manufacturer_id', $field->manufacturer_id)
                    ->where('template_id', $field->template_name) // using template_name as template_id
                    ->where('source_field', $field->field_name)
                    ->where('target_field', $mapping['fhir_path'])
                    ->exists();
                    
                if (!$exists) {
                    DB::table('ivr_field_mappings')->insert([
                        'manufacturer_id' => $field->manufacturer_id,
                        'template_id' => $field->template_name,
                        'source_field' => $field->field_name,
                        'target_field' => $mapping['fhir_path'],
                        'confidence' => 0.95,
                        'match_type' => 'exact',
                        'usage_count' => 0,
                        'created_by' => 'system',
                        'metadata' => json_encode([
                            'auto_generated' => true,
                            'generation_date' => now()->toISOString()
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $mappingsCreated++;
                    
                    if ($mappingsCreated % 10 == 0) {
                        echo "  Created {$mappingsCreated} mappings...\n";
                    }
                }
            } catch (Exception $e) {
                $errors++;
                if ($errors < 5) { // Only show first few errors
                    echo "  ⚠️ Error creating mapping for {$fieldVariation}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

echo "\nCreated {$mappingsCreated} field mappings\n";
if ($errors > 0) {
    echo "Encountered {$errors} errors\n";
}

// Add some additional product-specific mappings
echo "\nAdding product-specific mappings...\n";

$productMappings = [
    // ACZ & ASSOCIATES products
    ['manufacturer_id' => 1, 'source' => 'Product Q4205 (Membrane Wrap)', 'target' => 'deviceRequest.Q4205'],
    ['manufacturer_id' => 1, 'source' => 'Product Q4289 (Revoshield)', 'target' => 'deviceRequest.Q4289'],
    ['manufacturer_id' => 1, 'source' => 'Product Q4313 (Dermabind)', 'target' => 'deviceRequest.Q4313'],
    ['manufacturer_id' => 1, 'source' => 'Product Q4275 (Esano aca)', 'target' => 'deviceRequest.Q4275'],
    
    // Advanced Solution wound types
    ['manufacturer_id' => 2, 'source' => 'Wound Type (Diabetic Foot Ulcer)', 'target' => 'condition.woundType.diabeticFootUlcer'],
    ['manufacturer_id' => 2, 'source' => 'Wound Type (Venous Leg Ulcer)', 'target' => 'condition.woundType.venousLegUlcer'],
    ['manufacturer_id' => 2, 'source' => 'Wound Type (Pressure Ulcer)', 'target' => 'condition.woundType.pressureUlcer'],
];

$productMappingsCreated = 0;

foreach ($productMappings as $pm) {
    $field = DB::table('ivr_template_fields')
        ->where('manufacturer_id', $pm['manufacturer_id'])
        ->where('field_name', $pm['source'])
        ->first();
        
    if ($field) {
        try {
            DB::table('ivr_field_mappings')->insert([
                'manufacturer_id' => $pm['manufacturer_id'],
                'template_id' => $field->template_name,
                'source_field' => $pm['source'],
                'target_field' => $pm['target'],
                'confidence' => 0.90,
                'match_type' => 'semantic',
                'usage_count' => 0,
                'created_by' => 'system',
                'metadata' => json_encode([
                    'auto_generated' => true,
                    'type' => 'product_specific'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productMappingsCreated++;
        } catch (Exception $e) {
            // Skip duplicates
        }
    }
}

echo "Created {$productMappingsCreated} product-specific mappings\n";

// Summary
echo "\nMapping Summary:\n";
$summary = DB::table('ivr_field_mappings')
    ->select('manufacturer_id', DB::raw('COUNT(*) as count'))
    ->groupBy('manufacturer_id')
    ->orderBy('manufacturer_id')
    ->get();

foreach ($summary as $s) {
    $manufacturer = \App\Models\Order\Manufacturer::find($s->manufacturer_id);
    echo "  {$manufacturer->name}: {$s->count} mappings\n";
}

echo "\nTotal mappings: " . DB::table('ivr_field_mappings')->count() . "\n";
echo "\nDone!\n";