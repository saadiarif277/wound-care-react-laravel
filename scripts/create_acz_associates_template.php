<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Str;

echo "Creating ACZ & ASSOCIATES IVR template...\n\n";

// Find ACZ & ASSOCIATES manufacturer
$aczManufacturer = Manufacturer::find(1); // ID 1 is ACZ & ASSOCIATES

if (!$aczManufacturer) {
    echo "❌ ACZ & ASSOCIATES manufacturer not found!\n";
    exit;
}

echo "✓ Found manufacturer: {$aczManufacturer->name} (ID: {$aczManufacturer->id})\n";

// Check if ACZ already has an IVR template
$existingTemplate = DocusealTemplate::where('manufacturer_id', $aczManufacturer->id)
    ->where('document_type', 'InsuranceVerification')
    ->first();

if ($existingTemplate) {
    echo "✓ ACZ & ASSOCIATES already has an IVR template: {$existingTemplate->template_name}\n";
    exit;
}

// Create ACZ IVR template
$templateName = 'ACZ & ASSOCIATES IVR';
echo "\nCreating Docuseal template: {$templateName}\n";

$template = DocusealTemplate::create([
    'id' => Str::uuid()->toString(),
    'template_name' => $templateName,
    'manufacturer_id' => $aczManufacturer->id,
    'docuseal_template_id' => 'acz-associates-ivr-' . Str::random(10),
    'document_type' => 'InsuranceVerification',
    'field_mappings' => json_encode([]),
    'is_active' => true,
    'is_default' => false,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "✓ Created template record for {$templateName}\n";

// Check all ACZ products
echo "\n\nACZ & ASSOCIATES Products:\n";
$aczProducts = \App\Models\Order\Product::where('manufacturer_id', 1)->get();
foreach ($aczProducts as $product) {
    echo "  - {$product->name} (Q-Code: {$product->q_code})\n";
}

echo "\n✓ ACZ & ASSOCIATES now has an IVR template!\n";
echo "\nNote: The actual Docuseal template file needs to be uploaded to Docuseal API.\n";
echo "The template should be located at: docs/ivr-forms/ACZ & ASSOCIATES/insurance-verification.docx\n";