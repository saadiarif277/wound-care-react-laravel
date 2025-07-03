<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Reassigning Docuseal templates to correct manufacturers...\n\n";

// Define correct template assignments based on template names
$templateAssignments = [
    'Biowound IVR' => 'BIOWOUND SOLUTIONS', // ID 3
    'ACZ Distribution IVR Form' => 'ACZ Distribution', // ID 13
    'Advanced Solution IVR' => 'ADVANCED SOLUTION', // ID 2
    'Extremity Care Coll-e-Derm IVR' => 'Extremity Care LLC', // ID 4
    'Imbed Microlyte IVR' => 'IMBED', // ID 6
    'Centurion Therapeutics AmnioBand/Allopatch IVR' => 'CENTURION THERAPEUTICS', // ID 11
];

// Get all manufacturers for lookup
$manufacturers = App\Models\Order\Manufacturer::pluck('id', 'name')->toArray();

echo "Available Manufacturers:\n";
foreach ($manufacturers as $name => $id) {
    echo "  ID {$id}: {$name}\n";
}

echo "\nReassigning templates...\n";

foreach ($templateAssignments as $templateName => $manufacturerName) {
    $template = App\Models\Docuseal\DocusealTemplate::where('template_name', $templateName)->first();
    
    if (!$template) {
        echo "⚠️ Template '{$templateName}' not found\n";
        continue;
    }
    
    if (!isset($manufacturers[$manufacturerName])) {
        // Try case-insensitive search
        $foundManufacturer = null;
        foreach ($manufacturers as $name => $id) {
            if (strcasecmp($name, $manufacturerName) === 0) {
                $foundManufacturer = $id;
                $manufacturerName = $name; // Use the actual case from DB
                break;
            }
        }
        
        if (!$foundManufacturer) {
            echo "⚠️ Manufacturer '{$manufacturerName}' not found for template '{$templateName}'\n";
            continue;
        }
        
        $manufacturerId = $foundManufacturer;
    } else {
        $manufacturerId = $manufacturers[$manufacturerName];
    }
    
    $oldManufacturerId = $template->manufacturer_id;
    $oldManufacturer = $oldManufacturerId ? App\Models\Order\Manufacturer::find($oldManufacturerId) : null;
    
    if ($template->manufacturer_id != $manufacturerId) {
        $template->manufacturer_id = $manufacturerId;
        $template->save();
        
        echo "✓ {$templateName}: ";
        if ($oldManufacturer) {
            echo "{$oldManufacturer->name} => ";
        } else {
            echo "Unassigned => ";
        }
        echo "{$manufacturerName}\n";
    } else {
        echo "✓ {$templateName}: Already correctly assigned to {$manufacturerName}\n";
    }
}

// Also check for ACZ & ASSOCIATES - they might need their own IVR template
echo "\nChecking if ACZ & ASSOCIATES needs an IVR template...\n";
$aczAssociates = App\Models\Order\Manufacturer::where('name', 'ACZ & ASSOCIATES')->first();
if ($aczAssociates) {
    $hasIvrTemplate = App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $aczAssociates->id)
        ->where('template_name', 'LIKE', '%IVR%')
        ->exists();
        
    if (!$hasIvrTemplate) {
        echo "⚠️ ACZ & ASSOCIATES (ID: {$aczAssociates->id}) does not have an IVR template assigned.\n";
        echo "   Consider creating or importing an IVR template for ACZ & ASSOCIATES.\n";
    }
}

// Display final template assignments
echo "\n\nFinal Template Assignments:\n";
$templates = App\Models\Docuseal\DocusealTemplate::with('manufacturer')
    ->whereNotNull('manufacturer_id')
    ->orderBy('manufacturer_id')
    ->get();

$byManufacturer = [];
foreach ($templates as $template) {
    $mfrName = $template->manufacturer ? $template->manufacturer->name : 'Unknown';
    if (!isset($byManufacturer[$mfrName])) {
        $byManufacturer[$mfrName] = [];
    }
    $byManufacturer[$mfrName][] = $template->template_name;
}

ksort($byManufacturer);
foreach ($byManufacturer as $mfrName => $templateNames) {
    echo "\n{$mfrName}:\n";
    foreach ($templateNames as $templateName) {
        echo "  - {$templateName}\n";
    }
}

echo "\nDone!\n";