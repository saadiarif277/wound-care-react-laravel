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
        Schema::create('patient_ivr_status', function (Blueprint $table) {
            $table->id();
            $table->string('patient_fhir_id');
            $table->foreignId('manufacturer_id')
                ->constrained('manufacturers')
                ->onDelete('cascade');
            $table->date('last_verified_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])
                ->default('quarterly');
            $table->enum('status', ['active', 'expired', 'pending'])
                ->default('pending');
            $table->string('latest_docuseal_submission_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint for patient + manufacturer
            $table->unique(['patient_fhir_id', 'manufacturer_id']);
            $table->index('expiration_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_ivr_status');
    }
};