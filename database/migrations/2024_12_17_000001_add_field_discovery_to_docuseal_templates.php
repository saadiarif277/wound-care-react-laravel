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
            $table->json('extraction_metadata')->nullable()->after('field_mappings');
            $table->timestamp('last_extracted_at')->nullable()->after('extraction_metadata');
            $table->string('field_discovery_status', 50)->nullable()->after('last_extracted_at');
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