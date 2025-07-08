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
        Schema::table('docuseal_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('docuseal_templates', 'extraction_metadata')) {
                $table->json('extraction_metadata')->nullable();
            }
            if (!Schema::hasColumn('docuseal_templates', 'last_extracted_at')) {
                $table->timestamp('last_extracted_at')->nullable();
            }
            if (!Schema::hasColumn('docuseal_templates', 'field_discovery_status')) {
                $table->string('field_discovery_status', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docuseal_templates', function (Blueprint $table) {
            $table->dropColumn(['extraction_metadata', 'last_extracted_at', 'field_discovery_status']);
        });
    }
};
