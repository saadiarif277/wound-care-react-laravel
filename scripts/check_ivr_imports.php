<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Current IVR Template Fields by Manufacturer:\n";
$results = DB::table('ivr_template_fields')
    ->select('manufacturer_id', DB::raw('COUNT(*) as count'))
    ->groupBy('manufacturer_id')
    ->orderBy('manufacturer_id')
    ->get();

foreach ($results as $result) {
    echo "Manufacturer ID {$result->manufacturer_id}: {$result->count} fields\n";
}

echo "\nTotal fields: " . DB::table('ivr_template_fields')->count() . "\n";

echo "\nCurrent IVR Field Mappings by Manufacturer:\n";
$mappings = DB::table('ivr_field_mappings')
    ->select('manufacturer_id', DB::raw('COUNT(*) as count'))
    ->groupBy('manufacturer_id')
    ->orderBy('manufacturer_id')
    ->get();

foreach ($mappings as $mapping) {
    echo "Manufacturer ID {$mapping->manufacturer_id}: {$mapping->count} mappings\n";
}

echo "\nTotal mappings: " . DB::table('ivr_field_mappings')->count() . "\n";
