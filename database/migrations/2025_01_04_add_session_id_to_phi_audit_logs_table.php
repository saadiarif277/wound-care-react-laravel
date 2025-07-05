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
        // Only run if the table exists
        if (Schema::hasTable('phi_audit_logs')) {
            Schema::table('phi_audit_logs', function (Blueprint $table) {
                // Add session_id column after user_agent
                if (!Schema::hasColumn('phi_audit_logs', 'session_id')) {
                    $table->string('session_id')->nullable()->after('user_agent');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phi_audit_logs', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }
};