<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Str;

echo "Importing MedLife Solutions IVR template...\n\n";

// Find MedLife Solutions manufacturer
$medLifeManufacturer = Manufacturer::where('name', 'LIKE', '%MedLife%')
    ->orWhere('name', 'LIKE', '%MEDLIFE%')
    ->first();

if (!$medLifeManufacturer) {
    echo "❌ MedLife Solutions manufacturer not found!\n";
    exit;
}

echo "✓ Found manufacturer: {$medLifeManufacturer->name} (ID: {$medLifeManufacturer->id})\n";

// Check if MedLife already has an IVR template
$existingTemplate = DocusealTemplate::where('manufacturer_id', $medLifeManufacturer->id)
    ->where('template_name', 'LIKE', '%IVR%')
    ->first();

if ($existingTemplate) {
    echo "✓ MedLife Solutions already has an IVR template: {$existingTemplate->template_name}\n";
    $templateId = 'medlife-ivr-template'; // Use consistent template ID
    $template = $existingTemplate;
} else {

// Create MedLife IVR template
$templateName = 'MedLife Amnio IVR';
echo "\nCreating DocuSeal template: {$templateName}\n";

$template = DocusealTemplate::create([
    'id' => Str::uuid()->toString(),
    'template_name' => $templateName,
    'manufacturer_id' => $medLifeManufacturer->id,
    'docuseal_template_id' => 'medlife-ivr-' . Str::random(10), // Temporary ID until synced with DocuSeal
    'document_type' => 'InsuranceVerification',
    'field_mappings' => json_encode([]),
    'is_active' => true,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "✓ Created template record for {$templateName}\n";
}

// Import MedLife IVR field definitions from the JSON data
$medLifeFields = [
    "Patient Information_First Name",
    "Patient Information_Middle Name",
    "Patient Information_Last Name",
    "Patient Information_Date of Birth",
    "Patient Information_Primary Phone",
    "Patient Information_Alternate Phone",
    "Patient Information_Address",
    "Patient Information_City",
    "Patient Information_State",
    "Patient Information_ZIP",
    "Patient Information_Gender",
    "IVR Contact_First Name",
    "IVR Contact_Last Name",
    "IVR Contact_Primary Phone",
    "IVR Contact_Alternate Phone",
    "Primary Insurance_Insurance Name",
    "Primary Insurance_Member ID",
    "Primary Insurance_Group ID",
    "Primary Insurance_Payer ID",
    "Primary Insurance_Eligibility Summary",
    "Secondary Insurance_Insurance Name",
    "Secondary Insurance_Member ID",
    "Secondary Insurance_Group ID",
    "Secondary Insurance_Payer ID",
    "Secondary Insurance_Eligibility Summary",
    "Facility Information_Facility Name",
    "Facility Information_City",
    "Facility Information_State",
    "Facility Information_Zip",
    "Facility Information_Phone",
    "Prescribing Provider (MD/DO/DPM)_First Name",
    "Prescribing Provider (MD/DO/DPM)_Last Name",
    "Prescribing Provider (MD/DO/DPM)_NPI",
    "Prescribing Provider (MD/DO/DPM)_Practice Name",
    "Prescribing Provider (MD/DO/DPM)_Office Phone",
    "Treating Clinician (if different from above)_First Name",
    "Treating Clinician (if different from above)_Last Name",
    "Treating Clinician (if different from above)_NPI",
    "Wound Assessment_Wound Location",
    "Wound Assessment_Length (cm)",
    "Wound Assessment_Width (cm)",
    "Wound Assessment_Depth (cm)",
    "Wound Assessment_Drainage",
    "Wound Assessment_Appearance of Wound Base",
    "Other_Is patient ambulatory?",
    "Other_Is patient diabetic?",
    "Other_Primary Diagnosis",
    "Other_ICD Code",
    "Product Ordering_Product Requested",
    "Product Ordering_Qty",
    "Product Ordering_Size",
    "Product Ordering_Frequency of Dressing Changes",
    "Primary Care MD Information_First Name",
    "Primary Care MD Information_Last Name",
    "Primary Care MD Information_Primary Phone"
];

$templateId = 'medlife-ivr-template';
echo "\nImporting " . count($medLifeFields) . " IVR field definitions...\n";

$imported = 0;
$position = 0;
foreach ($medLifeFields as $field) {
    try {
        // Determine field category
        $category = 'Other';
        if (str_contains($field, 'Patient Information')) {
            $category = 'Patient';
        } elseif (str_contains($field, 'Insurance')) {
            $category = 'Insurance';
        } elseif (str_contains($field, 'Provider') || str_contains($field, 'Clinician') || str_contains($field, 'MD Information')) {
            $category = 'Provider';
        } elseif (str_contains($field, 'Facility')) {
            $category = 'Facility';
        } elseif (str_contains($field, 'Wound')) {
            $category = 'Clinical';
        } elseif (str_contains($field, 'Product')) {
            $category = 'Product';
        }
        
        // Check if it's a checkbox field
        $isCheckbox = str_contains($field, '?') || str_contains($field, 'Is patient');
        
        DB::table('ivr_template_fields')->insertGetId([
            'manufacturer_id' => $medLifeManufacturer->id,
            'template_name' => 'insurance-verification',
            'field_name' => $field,
            'field_type' => $isCheckbox ? 'checkbox' : 'text',
            'is_required' => false,
            'validation_rules' => null,
            'field_metadata' => json_encode([
                'category' => $category,
                'is_checkbox' => $isCheckbox
            ]),
            'field_order' => $position++,
            'section' => $category,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $imported++;
    } catch (\Exception $e) {
        if (!str_contains($e->getMessage(), 'Duplicate entry')) {
            echo "Error importing field '{$field}': " . $e->getMessage() . "\n";
        }
    }
}

echo "✓ Imported {$imported} field definitions\n";

// Create field mappings for MedLife
echo "\nCreating field mappings for MedLife IVR fields...\n";

$mappings = [
    // Patient Information
    ['source' => 'Patient Information_First Name', 'target' => 'patient.name[0].given[0]', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Middle Name', 'target' => 'patient.name[0].given[1]', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Last Name', 'target' => 'patient.name[0].family', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Date of Birth', 'target' => 'patient.birthDate', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Primary Phone', 'target' => 'patient.telecom[0].value', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Address', 'target' => 'patient.address[0].line[0]', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_City', 'target' => 'patient.address[0].city', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_State', 'target' => 'patient.address[0].state', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_ZIP', 'target' => 'patient.address[0].postalCode', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Patient Information_Gender', 'target' => 'patient.gender', 'type' => 'exact', 'confidence' => 1.0],
    
    // Insurance
    ['source' => 'Primary Insurance_Insurance Name', 'target' => 'coverage.primary.payor', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Primary Insurance_Member ID', 'target' => 'coverage.primary.memberId', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Primary Insurance_Group ID', 'target' => 'coverage.primary.groupId', 'type' => 'exact', 'confidence' => 1.0],
    
    // Provider
    ['source' => 'Prescribing Provider (MD/DO/DPM)_First Name', 'target' => 'practitioner.name[0].given[0]', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Prescribing Provider (MD/DO/DPM)_Last Name', 'target' => 'practitioner.name[0].family', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Prescribing Provider (MD/DO/DPM)_NPI', 'target' => 'practitioner.identifier[0].value', 'type' => 'exact', 'confidence' => 1.0],
    
    // Clinical
    ['source' => 'Wound Assessment_Length (cm)', 'target' => 'woundDetails.dimensions.length', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Wound Assessment_Width (cm)', 'target' => 'woundDetails.dimensions.width', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Wound Assessment_Depth (cm)', 'target' => 'woundDetails.dimensions.depth', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Other_Primary Diagnosis', 'target' => 'diagnosis.primary.description', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Other_ICD Code', 'target' => 'diagnosis.primary.code', 'type' => 'exact', 'confidence' => 1.0],
    
    // Product
    ['source' => 'Product Ordering_Product Requested', 'target' => 'quickRequestData.productName', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Product Ordering_Size', 'target' => 'quickRequestData.selectedSize', 'type' => 'exact', 'confidence' => 1.0],
    ['source' => 'Product Ordering_Qty', 'target' => 'quickRequestData.quantity', 'type' => 'exact', 'confidence' => 1.0],
];

$mappingCount = 0;
foreach ($mappings as $mapping) {
    try {
        DB::table('ivr_field_mappings')->insert([
            'manufacturer_id' => $medLifeManufacturer->id,
            'template_id' => $templateId,
            'source_field' => $mapping['source'],
            'target_field' => $mapping['target'],
            'confidence' => $mapping['confidence'],
            'match_type' => $mapping['type'],
            'usage_count' => 0,
            'success_rate' => null,
            'last_used_at' => null,
            'created_by' => 'system',
            'approved_by' => 'system',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $mappingCount++;
    } catch (\Exception $e) {
        if (!str_contains($e->getMessage(), 'Duplicate entry')) {
            echo "Error creating mapping: " . $e->getMessage() . "\n";
        }
    }
}

echo "✓ Created {$mappingCount} field mappings\n";

// Verify Amnio AMP now has proper template access
echo "\n\nVerifying Amnio AMP configuration...\n";
$amnioAmp = \App\Models\Order\Product::where('name', 'LIKE', '%Amnio%AMP%')->first();
if ($amnioAmp) {
    echo "Product: {$amnioAmp->name}\n";
    echo "Manufacturer ID: {$amnioAmp->manufacturer_id}\n";
    echo "Manufacturer: " . ($amnioAmp->manufacturer_id == $medLifeManufacturer->id ? $medLifeManufacturer->name : 'Mismatch!') . "\n";
    
    if ($amnioAmp->manufacturer_id == $medLifeManufacturer->id) {
        echo "✓ Amnio AMP is correctly assigned to MedLife Solutions\n";
        echo "✓ MedLife Solutions now has IVR template: {$templateName}\n";
    }
}

echo "\nDone! MedLife Solutions IVR template has been created.\n";
echo "\nNote: The actual DocuSeal template file needs to be uploaded to DocuSeal API.\n";
echo "The template should be located at: docs/ivr-forms/MedLife Solutions/insurance-verification.docx\n";