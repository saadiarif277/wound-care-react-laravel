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
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Add missing columns that are being referenced in the controller
            
            // Add medicaid_number column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'medicaid_number')) {
                $table->string('medicaid_number')->nullable()->after('ptan');
            }
            
            // Add phone column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'phone')) {
                $table->string('phone')->nullable()->after('medicaid_number');
            }
            
            // Add fax column if it doesn't exist  
            if (!Schema::hasColumn('provider_profiles', 'fax')) {
                $table->string('fax')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Drop the added columns
            $columnsToCheck = [
                'medicaid_number',
                'phone',
                'fax'
            ];

            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('provider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
