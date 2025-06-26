<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if table exists and get columns
if (Schema::hasTable('ivr_template_fields')) {
    echo "Table 'ivr_template_fields' exists.\n";
    $columns = Schema::getColumnListing('ivr_template_fields');
    echo "Columns: " . implode(', ', $columns) . "\n";
} else {
    echo "Table 'ivr_template_fields' does not exist.\n";
}

// Check migrations table
$migration = DB::table('migrations')
    ->where('migration', '2025_06_26_062829_create_ivr_template_fields_table')
    ->first();
    
if ($migration) {
    echo "\nMigration is marked as run.\n";
} else {
    echo "\nMigration is NOT marked as run. Marking it now...\n";
    DB::table('migrations')->insert([
        'migration' => '2025_06_26_062829_create_ivr_template_fields_table',
        'batch' => DB::table('migrations')->max('batch') + 1
    ]);
    echo "Migration marked as run.\n";
}
