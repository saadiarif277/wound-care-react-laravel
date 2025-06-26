<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Updating DocuSeal Template IDs...\n\n";

// Update ACZ & ASSOCIATES template with real DocuSeal ID
$acz = DocusealTemplate::where('manufacturer_id', 1)->where('template_name', 'Biowound IVR')->first();
if ($acz) {
    $acz->update(['docuseal_template_id' => '1254774']);
    echo "✅ Updated ACZ & ASSOCIATES template with DocuSeal ID: 1254774\n";
} else {
    echo "❌ ACZ & ASSOCIATES template not found\n";
}

// Update Advanced Solution template with real DocuSeal ID
$advanced = DocusealTemplate::where('manufacturer_id', 2)->where('template_name', 'Advanced Solution IVR')->first();
if ($advanced) {
    $advanced->update(['docuseal_template_id' => '1199885']);
    echo "✅ Updated Advanced Solution template with DocuSeal ID: 1199885\n";
} else {
    echo "❌ Advanced Solution template not found\n";
}

// Update IMBED template with real DocuSeal ID
$imbed = DocusealTemplate::where('manufacturer_id', 6)->where('template_name', 'Imbed Microlyte IVR')->first();
if ($imbed) {
    $imbed->update(['docuseal_template_id' => '1234272']);
    echo "✅ Updated IMBED template with DocuSeal ID: 1234272\n";
} else {
    echo "❌ IMBED template not found\n";
}

echo "\n🎉 Template IDs updated successfully!\n";
