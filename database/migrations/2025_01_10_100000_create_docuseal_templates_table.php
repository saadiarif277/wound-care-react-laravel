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
        Schema::create('docuseal_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('template_name');
            $table->string('docuseal_template_id')->unique();
            $table->uuid('manufacturer_id')->nullable();
            $table->enum('document_type', ['InsuranceVerification', 'OrderForm', 'OnboardingForm']);
            $table->boolean('is_default')->default(false);
            $table->json('field_mappings');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Foreign key constraints - commented out until manufacturers table exists
            // $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('set null');
            
            // Indexes
            $table->index(['document_type', 'is_active']);
            $table->index(['manufacturer_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docuseal_templates');
    }
}; 