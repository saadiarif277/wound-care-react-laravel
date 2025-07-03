<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fixing Docuseal template assignments...\n\n";

// Find templates without manufacturer assignments
$unassignedTemplates = App\Models\Docuseal\DocusealTemplate::whereNull('manufacturer_id')->get();

echo "Found " . $unassignedTemplates->count() . " templates without manufacturer assignments:\n";

foreach ($unassignedTemplates as $template) {
    echo "\nTemplate: {$template->template_name}\n";
    
    // Try to match based on template name
    if (str_contains(strtolower($template->template_name), 'acz distribution')) {
        // ACZ Distribution is ID 13
        $manufacturer = App\Models\Order\Manufacturer::find(13);
        if ($manufacturer) {
            $template->manufacturer_id = 13;
            $template->save();
            echo "  ✓ Assigned to: {$manufacturer->name} (ID: 13)\n";
        }
    } elseif (str_contains(strtolower($template->template_name), 'provider onboarding')) {
        echo "  ℹ️ Skipping - General form, not manufacturer-specific\n";
    } elseif (str_contains(strtolower($template->template_name), 'insurance verification')) {
        echo "  ℹ️ Skipping - General form, not manufacturer-specific\n";
    } elseif (str_contains(strtolower($template->template_name), 'standard order')) {
        echo "  ℹ️ Skipping - General form, not manufacturer-specific\n";
    } else {
        echo "  ⚠️ Could not determine manufacturer for this template\n";
    }
}

// Also check if ACZ & ASSOCIATES needs an IVR template
$aczAssociates = App\Models\Order\Manufacturer::find(1); // ACZ & ASSOCIATES
if ($aczAssociates) {
    $hasTemplate = App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', 1)
        ->where('template_name', 'LIKE', '%IVR%')
        ->exists();
    
    if (!$hasTemplate) {
        echo "\n⚠️ ACZ & ASSOCIATES (ID: 1) doesn't have an IVR template.\n";
        
        // Check if Biowound IVR is incorrectly assigned
        $biowoundTemplate = App\Models\Docuseal\DocusealTemplate::where('template_name', 'Biowound IVR')
            ->where('manufacturer_id', 1)
            ->first();
            
        if ($biowoundTemplate) {
            echo "  Found 'Biowound IVR' assigned to ACZ & ASSOCIATES.\n";
            
            // Check if BIOWOUND SOLUTIONS exists
            $biowound = App\Models\Order\Manufacturer::find(3); // BIOWOUND SOLUTIONS
            if ($biowound) {
                $biowoundTemplate->manufacturer_id = 3;
                $biowoundTemplate->save();
                echo "  ✓ Reassigned 'Biowound IVR' to BIOWOUND SOLUTIONS (ID: 3)\n";
            }
        }
    }
}

// Display updated template assignments
echo "\n\nUpdated Template Assignments:\n";
$templates = App\Models\Docuseal\DocusealTemplate::with('manufacturer')
    ->whereNotNull('manufacturer_id')
    ->orderBy('manufacturer_id')
    ->get();

foreach ($templates as $template) {
    echo "  {$template->template_name} => " . ($template->manufacturer ? $template->manufacturer->name : 'None') . "\n";
}

echo "\nDone!\n";