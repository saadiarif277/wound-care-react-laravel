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
        Schema::table('pdf_field_mappings', function (Blueprint $table) {
            // AI-related fields
            $table->boolean('ai_suggested')->default(false)->after('options')
                ->comment('Whether this mapping was suggested by AI');
            
            $table->decimal('ai_confidence', 3, 2)->nullable()->after('ai_suggested')
                ->comment('AI confidence score (0.00 to 1.00)');
            
            $table->json('ai_suggestion_metadata')->nullable()->after('ai_confidence')
                ->comment('Additional metadata about the AI suggestion');
            
            // Index for filtering AI-suggested mappings
            $table->index('ai_suggested');
            $table->index(['ai_suggested', 'ai_confidence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_field_mappings', function (Blueprint $table) {
            $table->dropIndex(['ai_suggested', 'ai_confidence']);
            $table->dropIndex(['ai_suggested']);
            
            $table->dropColumn([
                'ai_suggested',
                'ai_confidence',
                'ai_suggestion_metadata'
            ]);
        });
    }
};