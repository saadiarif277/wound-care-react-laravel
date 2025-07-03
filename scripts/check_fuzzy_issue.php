<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check manufacturers
echo "\n=== MANUFACTURERS ===\n";
$manufacturers = DB::table('manufacturers')->select('id', 'name')->get();
foreach ($manufacturers as $m) {
    echo "ID: {$m->id}, Name: {$m->name}\n";
}

// Check docuseal templates
echo "\n=== DOCUSEAL TEMPLATES ===\n";
$templates = DB::table('docuseal_templates')
    ->select('id', 'template_name', 'manufacturer_id', 'document_type', 'is_default', 'is_active')
    ->get();
foreach ($templates as $t) {
    echo "Template: {$t->template_name}, Manufacturer: {$t->manufacturer_id}, Type: {$t->document_type}, Default: {$t->is_default}, Active: {$t->is_active}\n";
}

// Check if other manufacturers have templates
echo "\n=== MANUFACTURERS WITHOUT TEMPLATES ===\n";
$manufacturersWithoutTemplates = DB::table('manufacturers as m')
    ->leftJoin('docuseal_templates as dt', 'm.id', '=', 'dt.manufacturer_id')
    ->whereNull('dt.id')
    ->select('m.id', 'm.name')
    ->get();
foreach ($manufacturersWithoutTemplates as $m) {
    echo "ID: {$m->id}, Name: {$m->name}\n";
}

// Check products and their manufacturers
echo "\n=== SAMPLE PRODUCTS AND MANUFACTURERS ===\n";
$products = DB::table('products as p')
    ->join('manufacturers as m', 'p.manufacturer_id', '=', 'm.id')
    ->select('p.id', 'p.product_name', 'm.name as manufacturer_name')
    ->limit(10)
    ->get();
foreach ($products as $p) {
    echo "Product: {$p->product_name}, Manufacturer: {$p->manufacturer_name}\n";
}
