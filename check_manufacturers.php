<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;

echo "Existing Manufacturers:\n";
$manufacturers = Manufacturer::select('id', 'name')->orderBy('id')->get();
foreach ($manufacturers as $manufacturer) {
    echo "ID: {$manufacturer->id}, Name: {$manufacturer->name}\n";
}

echo "\nTotal: " . $manufacturers->count() . " manufacturers\n";
