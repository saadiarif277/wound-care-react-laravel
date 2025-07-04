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
            // Add user_email column after user_id
            $table->string('user_email')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phi_audit_logs', function (Blueprint $table) {
            $table->dropColumn('user_email');
        });
    }
};