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
        // Drop existing foreign keys if they exist (using raw SQL for reliability)
        $foreignKeys = [
            'product_requests_manufacturer_sent_by_foreign',
            'product_requests_ivr_bypassed_by_foreign'
        ];

        foreach ($foreignKeys as $fkName) {
            try {
                DB::statement("ALTER TABLE product_requests DROP FOREIGN KEY {$fkName}");
            } catch (\Exception $e) {
                // Foreign key doesn't exist, continue
            }
        }

        // Add foreign keys properly
        Schema::table('product_requests', function (Blueprint $table) {
            // Check and add manufacturer_sent_by foreign key
            if (Schema::hasColumn('product_requests', 'manufacturer_sent_by')) {
                $fkExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_requests' 
                    AND CONSTRAINT_NAME = 'product_requests_manufacturer_sent_by_foreign'
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ");
                
                if ($fkExists[0]->count == 0) {
                    $table->foreign('manufacturer_sent_by')
                          ->references('id')
                          ->on('users')
                          ->nullOnDelete();
                }
            }
            
            // Check and add ivr_bypassed_by foreign key
            if (Schema::hasColumn('product_requests', 'ivr_bypassed_by')) {
                $fkExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_requests' 
                    AND CONSTRAINT_NAME = 'product_requests_ivr_bypassed_by_foreign'
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ");
                
                if ($fkExists[0]->count == 0) {
                    $table->foreign('ivr_bypassed_by')
                          ->references('id')
                          ->on('users')
                          ->nullOnDelete();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            try {
                $table->dropForeign(['manufacturer_sent_by']);
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
            
            try {
                $table->dropForeign(['ivr_bypassed_by']);
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
        });
    }
};