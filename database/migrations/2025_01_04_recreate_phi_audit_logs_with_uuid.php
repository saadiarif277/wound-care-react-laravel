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
        // Create a new table with the correct structure
        Schema::create('phi_audit_logs_new', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            
            // Indexes
            $table->index('user_id');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
        
        // Copy any existing data (converting numeric IDs to UUIDs)
        $existingRecords = DB::table('phi_audit_logs')->get();
        foreach ($existingRecords as $record) {
            $data = (array) $record;
            // Generate a new UUID for the ID
            $data['id'] = \Illuminate\Support\Str::uuid()->toString();
            DB::table('phi_audit_logs_new')->insert($data);
        }
        
        // Drop the old table
        Schema::dropIfExists('phi_audit_logs');
        
        // Rename the new table
        Schema::rename('phi_audit_logs_new', 'phi_audit_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Create the old structure
        Schema::create('phi_audit_logs_old', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            
            // Indexes
            $table->index('user_id');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
        
        // Drop the UUID version
        Schema::dropIfExists('phi_audit_logs');
        
        // Rename back
        Schema::rename('phi_audit_logs_old', 'phi_audit_logs');
    }
};