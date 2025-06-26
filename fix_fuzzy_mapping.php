<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Docuseal\DocusealTemplate;
use App\Services\FuzzyMapping\TemplateSelectionService;

echo "\n=== FUZZY MAPPING DEBUG ===\n\n";

// 1. Fix the is_default issue
echo "1. Fixing is_default issue...\n";
DB::table('docuseal_templates')->update(['is_default' => 0]);

// Set one template per manufacturer as default
$manufacturers = DB::table('docuseal_templates')
    ->select('manufacturer_id')
    ->distinct()
    ->get();

foreach ($manufacturers as $m) {
    // Get the most recent template for this manufacturer
    $template = DB::table('docuseal_templates')
        ->where('manufacturer_id', $m->manufacturer_id)
        ->where('is_active', 1)
        ->orderBy('updated_at', 'desc')
        ->first();
    
    if ($template) {
        DB::table('docuseal_templates')
            ->where('id', $template->id)
            ->update(['is_default' => 1]);
        
        echo "   Set default for manufacturer {$m->manufacturer_id}: {$template->template_name}\n";
    }
}

// 2. Test template selection
echo "\n2. Testing template selection...\n";

$testCases = [
    ['manufacturer_id' => 1, 'name' => 'ACZ & ASSOCIATES'],
    ['manufacturer_id' => 2, 'name' => 'Advanced Solution'],
    ['manufacturer_id' => 3, 'name' => 'BIOWOUND SOLUTIONS'],
    ['manufacturer_id' => 4, 'name' => 'Extremity Care LLC'],
    ['manufacturer_id' => 16, 'name' => 'CENTURION THERAPEUTICS'],
];

$service = new TemplateSelectionService();

foreach ($testCases as $test) {
    $context = ['manufacturer_id' => $test['manufacturer_id']];
    $template = $service->selectTemplate($context);
    
    if ($template) {
        echo "   {$test['name']} -> {$template->template_name} ✓\n";
    } else {
        echo "   {$test['name']} -> NO TEMPLATE FOUND ✗\n";
    }
}

// 3. Show current template distribution
echo "\n3. Current template distribution:\n";
$templates = DB::table('docuseal_templates as dt')
    ->join('manufacturers as m', 'dt.manufacturer_id', '=', 'm.id')
    ->select('m.name as manufacturer', 'dt.template_name', 'dt.is_default', 'dt.is_active')
    ->orderBy('m.name')
    ->get();

foreach ($templates as $t) {
    $default = $t->is_default ? '[DEFAULT]' : '';
    $active = $t->is_active ? 'Active' : 'Inactive';
    echo "   {$t->manufacturer}: {$t->template_name} - {$active} {$default}\n";
}

// 4. Identify missing manufacturer associations
echo "\n4. Manufacturers without templates:\n";
$missingManufacturers = DB::table('manufacturers as m')
    ->leftJoin('docuseal_templates as dt', 'm.id', '=', 'dt.manufacturer_id')
    ->whereNull('dt.id')
    ->select('m.id', 'm.name')
    ->get();

foreach ($missingManufacturers as $m) {
    echo "   ID: {$m->id}, Name: {$m->name}\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Run the migration to create products table: php artisan migrate\n";
echo "2. Import your product catalog with manufacturer associations\n";
echo "3. Run 'php artisan docuseal:sync-templates' to sync all templates\n";
echo "4. Consider creating a generic fallback template for unmapped manufacturers\n";

echo "\n=== DONE ===\n";
