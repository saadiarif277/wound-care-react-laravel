<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\IVRTemplateField;
use App\Models\IVRFieldMapping;

// First, let's verify the table structure
try {
    $test = IVRTemplateField::first();
    echo "Table structure is OK\n";
} catch (\Exception $e) {
    echo "Error with table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test data for ACZ & ASSOCIATES
$aczFields = [
    "Treating Physician NPI",
    "Treating Physician Tax ID",
    "Treating Physician PTAN",
    "Treating Physician Medicaid #",
    "Treating Physician Phone",
    "Treating Physician Fax",
    "Patient Name",
    "Patient DOB",
    "Patient Address",
    "Patient Phone",
    "Insurance Name 1",
    "Policy Number 1",
    "ICD-10 Codes",
    "Wound Location (Legs/Arms/Trunk ≤100 sq cm)",
    "Product Q4205 (Membrane Wrap)",
];

echo "Importing fields for ACZ & ASSOCIATES...\n";

$manufacturerId = 1; // ACZ & ASSOCIATES
$imported = 0;
$order = 0;

foreach ($aczFields as $fieldName) {
    try {
        // Use raw query to check existence
        $exists = \DB::table('ivr_template_fields')
            ->where('manufacturer_id', $manufacturerId)
            ->where('template_name', 'insurance-verification')
            ->where('field_name', $fieldName)
            ->exists();
            
        if ($exists) {
            echo "  - Skipped (exists): {$fieldName}\n";
            continue;
        }
        
        // Create using raw insert
        \DB::table('ivr_template_fields')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'manufacturer_id' => $manufacturerId,
            'template_name' => 'insurance-verification',
            'field_name' => $fieldName,
            'field_type' => detectFieldType($fieldName),
            'is_required' => isRequired($fieldName),
            'field_order' => $order++,
            'section' => detectSection($fieldName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  ✓ Imported: {$fieldName}\n";
        $imported++;
        
    } catch (\Exception $e) {
        echo "  ✗ Error importing {$fieldName}: " . $e->getMessage() . "\n";
    }
}

echo "\nImported {$imported} fields for ACZ & ASSOCIATES\n";

// Helper functions
function detectFieldType($fieldName) {
    $fieldName = strtolower($fieldName);
    if (strpos($fieldName, 'date') !== false || strpos($fieldName, 'dob') !== false) return 'date';
    if (strpos($fieldName, 'phone') !== false || strpos($fieldName, 'fax') !== false) return 'phone';
    if (strpos($fieldName, 'email') !== false) return 'email';
    if (preg_match('/\(.*\)/', $fieldName)) return 'checkbox';
    return 'text';
}

function detectSection($fieldName) {
    $fieldName = strtolower($fieldName);
    if (strpos($fieldName, 'patient') !== false) return 'patient_information';
    if (strpos($fieldName, 'physician') !== false || strpos($fieldName, 'treating') !== false) return 'provider_information';
    if (strpos($fieldName, 'insurance') !== false || strpos($fieldName, 'policy') !== false) return 'insurance_information';
    if (strpos($fieldName, 'wound') !== false || strpos($fieldName, 'icd') !== false) return 'clinical_information';
    if (strpos($fieldName, 'product') !== false) return 'product_selection';
    return 'other';
}

function isRequired($fieldName) {
    $required = ['patient name', 'dob', 'npi', 'insurance', 'policy'];
    $fieldName = strtolower($fieldName);
    foreach ($required as $req) {
        if (strpos($fieldName, $req) !== false) return true;
    }
    return false;
}