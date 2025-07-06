<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\PatientManufacturerIVREpisode;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Debug Episode Metadata\n";
echo "========================\n\n";

// Get the latest episode
$episode = PatientManufacturerIVREpisode::latest()->first();

if (!$episode) {
    echo "❌ No episodes found in database.\n";
    exit(1);
}

echo "📋 Episode ID: {$episode->id}\n";
echo "📋 Status: {$episode->status}\n";
echo "📋 IVR Status: {$episode->ivr_status}\n";
echo "📋 Patient ID: {$episode->patient_id}\n";
echo "📋 Patient Display ID: {$episode->patient_display_id}\n";
echo "📋 Manufacturer ID: {$episode->manufacturer_id}\n\n";

echo "🗂️ Metadata Structure:\n";
echo "======================\n";

$metadata = $episode->metadata ?? [];

if (empty($metadata)) {
    echo "❌ No metadata found.\n";
    exit(1);
}

echo "📊 Top-level keys: " . implode(', ', array_keys($metadata)) . "\n\n";

foreach ($metadata as $key => $value) {
    echo "🔹 {$key}:\n";
    if (is_array($value)) {
        if (empty($value)) {
            echo "   (empty array)\n";
        } else {
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    echo "   {$subKey}: (array with " . count($subValue) . " items)\n";
                    foreach ($subValue as $subSubKey => $subSubValue) {
                        $displayValue = is_scalar($subSubValue) ? $subSubValue : gettype($subSubValue);
                        echo "      {$subSubKey}: {$displayValue}\n";
                    }
                } else {
                    $displayValue = is_scalar($subValue) ? $subValue : gettype($subValue);
                    echo "   {$subKey}: {$displayValue}\n";
                }
            }
        }
    } else {
        $displayValue = is_scalar($value) ? $value : gettype($value);
        echo "   {$displayValue}\n";
    }
    echo "\n";
}

echo "🎯 Summary:\n";
echo "==========\n";
echo "Has patient_data: " . (isset($metadata['patient_data']) ? 'Yes' : 'No') . "\n";
echo "Has provider_data: " . (isset($metadata['provider_data']) ? 'Yes' : 'No') . "\n";
echo "Has facility_data: " . (isset($metadata['facility_data']) ? 'Yes' : 'No') . "\n";
echo "Has organization_data: " . (isset($metadata['organization_data']) ? 'Yes' : 'No') . "\n";
echo "Has clinical_data: " . (isset($metadata['clinical_data']) ? 'Yes' : 'No') . "\n";
echo "Has insurance_data: " . (isset($metadata['insurance_data']) ? 'Yes' : 'No') . "\n";

echo "\n✅ Debug completed!\n";
