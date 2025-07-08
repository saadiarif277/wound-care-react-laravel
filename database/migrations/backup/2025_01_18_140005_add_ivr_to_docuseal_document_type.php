<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add IVR to the document_type enum
        DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('InsuranceVerification', 'OrderForm', 'OnboardingForm', 'IVR')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove IVR from the document_type enum
        DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('InsuranceVerification', 'OrderForm', 'OnboardingForm')");
    }
};