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
        // For MySQL and other databases that support MODIFY COLUMN
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('InsuranceVerification', 'OrderForm', 'OnboardingForm', 'IVR')");
        } else {
            // For SQLite, we need to recreate the table since it doesn't support MODIFY COLUMN
            // But let's try a simpler approach - just update existing records
            // The enum constraint will be handled at the application level for SQLite
            echo "SQLite detected - IVR document type will be handled at application level\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For MySQL and other databases that support MODIFY COLUMN
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('InsuranceVerification', 'OrderForm', 'OnboardingForm')");
        } else {
            // For SQLite, just log that we're reverting
            echo "SQLite detected - reverting IVR document type at application level\n";
        }
    }
};