<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding place of service field mappings...\n";

// Map the checkbox-style place of service fields to the form data
$placeOfServiceMappings = [
    // Office variations
    ['source' => 'Place of Service (Office)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Physician Office)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // Hospital Outpatient variations
    ['source' => 'Place of Service (Outpatient Hospital)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Hospital Outpatient)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // Ambulatory Surgical Center
    ['source' => 'Place of Service (Ambulatory Surgical Center)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Surgery Center)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // Home
    ['source' => 'Place of Service (Home)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // SNF/Nursing Facility
    ['source' => 'Place of Service (Nursing Care Facility)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Skilled Nursing Facility)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // Other
    ['source' => 'Place of Service (Other)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    
    // Also map to the encounter service provider for FHIR
    ['source' => 'Place of Service (Office)', 'target' => 'encounter.serviceProvider', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Physician Office)', 'target' => 'encounter.serviceProvider', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Outpatient Hospital)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Hospital Outpatient)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Ambulatory Surgical Center)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Surgery Center)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Home)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Nursing Care Facility)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
    ['source' => 'Place of Service (Other)', 'target' => 'encounter.class', 'match_type' => 'semantic'],
];

$created = 0;
$skipped = 0;

foreach ($placeOfServiceMappings as $mapping) {
    // Find all fields with this source name
    $fields = DB::table('ivr_template_fields')
        ->where('field_name', $mapping['source'])
        ->get();
    
    foreach ($fields as $field) {
        // Check if mapping already exists
        $exists = DB::table('ivr_field_mappings')
            ->where('manufacturer_id', $field->manufacturer_id)
            ->where('template_id', $field->template_name)
            ->where('source_field', $mapping['source'])
            ->where('target_field', $mapping['target'])
            ->exists();
            
        if (!$exists) {
            try {
                DB::table('ivr_field_mappings')->insert([
                    'manufacturer_id' => $field->manufacturer_id,
                    'template_id' => $field->template_name,
                    'source_field' => $mapping['source'],
                    'target_field' => $mapping['target'],
                    'confidence' => 0.90,
                    'match_type' => $mapping['match_type'],
                    'usage_count' => 0,
                    'created_by' => 'system',
                    'metadata' => json_encode([
                        'auto_generated' => true,
                        'field_type' => 'place_of_service',
                        'notes' => 'Maps checkbox-style place of service to form dropdown',
                        'created_at' => now()->toISOString()
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (Exception $e) {
                // Skip duplicates
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
}

echo "\nCreated {$created} place of service mappings\n";
echo "Skipped {$skipped} existing mappings\n";

// Add some additional missed mappings for fields in the form
$additionalFormMappings = [
    // Map form data fields that might not have FHIR equivalents
    ['source' => 'Anticipated Application Date', 'target' => 'formData.anticipated_application_date', 'match_type' => 'semantic'],
    ['source' => 'Anticipated Treatment Start Date', 'target' => 'formData.anticipated_application_date', 'match_type' => 'semantic'],
    ['source' => 'Number of Anticipated Applications', 'target' => 'formData.anticipated_applications', 'match_type' => 'exact'],
    ['source' => 'Treatment Setting (Provider\'s Office)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
    ['source' => 'Treatment Setting (Other)', 'target' => 'formData.place_of_service', 'match_type' => 'semantic'],
];

foreach ($additionalFormMappings as $mapping) {
    $fields = DB::table('ivr_template_fields')
        ->where('field_name', $mapping['source'])
        ->get();
    
    foreach ($fields as $field) {
        $exists = DB::table('ivr_field_mappings')
            ->where('manufacturer_id', $field->manufacturer_id)
            ->where('template_id', $field->template_name)
            ->where('source_field', $mapping['source'])
            ->where('target_field', $mapping['target'])
            ->exists();
            
        if (!$exists) {
            try {
                DB::table('ivr_field_mappings')->insert([
                    'manufacturer_id' => $field->manufacturer_id,
                    'template_id' => $field->template_name,
                    'source_field' => $mapping['source'],
                    'target_field' => $mapping['target'],
                    'confidence' => 0.90,
                    'match_type' => $mapping['match_type'],
                    'usage_count' => 0,
                    'created_by' => 'system',
                    'metadata' => json_encode([
                        'auto_generated' => true,
                        'generation_batch' => 'form_data_mappings',
                        'created_at' => now()->toISOString()
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (Exception $e) {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
}

echo "\nTotal created: {$created} mappings\n";
echo "Total skipped: {$skipped} mappings\n";

// Show updated summary
echo "\nUpdated Mapping Summary by Manufacturer:\n";
$summary = DB::table('ivr_field_mappings as m')
    ->join('manufacturers as mfr', 'm.manufacturer_id', '=', 'mfr.id')
    ->select('mfr.name', DB::raw('COUNT(*) as total_mappings'))
    ->groupBy('mfr.id', 'mfr.name')
    ->orderBy('mfr.name')
    ->get();

foreach ($summary as $s) {
    echo "  {$s->name}: {$s->total_mappings} mappings\n";
}

echo "\nDone!\n";