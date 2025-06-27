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
        Schema::create('manufacturer_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->enum('type', [
                'primary',
                'approval_team',
                'technical',
                'billing',
                'general'
            ])->default('general');
            $table->enum('notification_types', [
                'all',
                'approvals_only',
                'technical_only',
                'none'
            ])->default('all');
            $table->boolean('is_active')->default(true);
            $table->json('preferences')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('manufacturer_id', 'idx_manufacturer_contacts_manufacturer');
            $table->index('email', 'idx_manufacturer_contacts_email');
            $table->index(['manufacturer_id', 'type'], 'idx_manufacturer_contacts_manufacturer_type');
            $table->index(['is_active', 'notification_types'], 'idx_manufacturer_contacts_active_notifications');
            
            // Unique constraint for email per manufacturer
            $table->unique(['manufacturer_id', 'email'], 'uniq_manufacturer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturer_contacts');
    }
};
