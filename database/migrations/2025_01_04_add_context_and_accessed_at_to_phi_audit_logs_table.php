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
        Schema::table('phi_audit_logs', function (Blueprint $table) {
            // Add context column (similar to metadata but for specific context)
            $table->json('context')->nullable()->after('metadata');

            // Add accessed_at timestamp
            $table->timestamp('accessed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phi_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['context', 'accessed_at']);
        });
    }
};
