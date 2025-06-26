<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

echo "Backing up existing data...\n";
$existingData = DB::table('ivr_template_fields')->get();
echo "Found " . count($existingData) . " records to backup.\n";

echo "Dropping existing table...\n";
Schema::dropIfExists('ivr_template_fields');

echo "Creating new table with correct structure...\n";
Schema::create('ivr_template_fields', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('manufacturer_id')->constrained();
    $table->string('template_name');
    $table->string('field_name');
    $table->string('field_type'); // text, date, select, checkbox, etc.
    $table->boolean('is_required')->default(false);
    $table->json('validation_rules')->nullable();
    $table->json('field_metadata')->nullable(); // Options, formats, etc.
    $table->integer('field_order')->default(0);
    $table->string('section')->nullable();
    $table->text('description')->nullable();
    $table->timestamps();
    
    $table->unique(['manufacturer_id', 'template_name', 'field_name'], 'ivr_template_fields_unique');
    $table->index(['manufacturer_id', 'template_name']);
});

echo "Table recreated successfully.\n";

// Try to restore data if possible
if (count($existingData) > 0) {
    echo "Attempting to restore data...\n";
    foreach ($existingData as $row) {
        try {
            DB::table('ivr_template_fields')->insert([
                'id' => $row->id,
                'manufacturer_id' => $row->manufacturer_id,
                'template_name' => 'insurance-verification', // Default template name
                'field_name' => $row->field_name,
                'field_type' => $row->field_type,
                'is_required' => $row->is_required,
                'validation_rules' => $row->validation_rules,
                'field_metadata' => json_encode(['options' => $row->options ?? null]),
                'field_order' => $row->position ?? 0,
                'section' => $row->field_category ?? null,
                'description' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        } catch (Exception $e) {
            echo "Failed to restore record: " . $e->getMessage() . "\n";
        }
    }
    echo "Data restore complete.\n";
}

// Run the other migration
echo "\nRunning ivr_mapping_audit migration...\n";
if (!Schema::hasTable('ivr_mapping_audit')) {
    Schema::create('ivr_mapping_audit', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('manufacturer_id')->constrained();
        $table->string('template_name');
        $table->string('fhir_field');
        $table->string('ivr_field');
        $table->string('mapping_type'); // exact, fuzzy, semantic, pattern
        $table->float('confidence_score');
        $table->boolean('was_successful');
        $table->json('metadata')->nullable();
        $table->timestamps();
        
        $table->index(['manufacturer_id', 'template_name']);
        $table->index('was_successful');
        $table->index('created_at');
    });
    
    // Mark migration as run
    DB::table('migrations')->insert([
        'migration' => '2025_06_26_062842_create_ivr_mapping_audit_table',
        'batch' => DB::table('migrations')->max('batch') + 1
    ]);
    
    echo "ivr_mapping_audit table created.\n";
} else {
    echo "ivr_mapping_audit table already exists.\n";
}

echo "\nDone!\n";