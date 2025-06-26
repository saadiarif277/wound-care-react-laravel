<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivr_template_fields');
    }
};
