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
        // Update existing wound type text values to enum values
        DB::table('product_requests')
            ->where('wound_type', 'diabetic_foot_ulcer')
            ->update(['wound_type' => 'DFU']);

        DB::table('product_requests')
            ->where('wound_type', 'venous_leg_ulcer')
            ->update(['wound_type' => 'VLU']);

        DB::table('product_requests')
            ->where('wound_type', 'pressure_ulcer')
            ->update(['wound_type' => 'PU']);

        DB::table('product_requests')
            ->where('wound_type', 'traumatic_wound')
            ->update(['wound_type' => 'TW']);

        DB::table('product_requests')
            ->where('wound_type', 'arterial_ulcer')
            ->update(['wound_type' => 'AU']);

        DB::table('product_requests')
            ->where('wound_type', 'other')
            ->update(['wound_type' => 'OTHER']);

        // Also handle capitalized versions
        DB::table('product_requests')
            ->where('wound_type', 'Diabetic Foot Ulcer')
            ->update(['wound_type' => 'DFU']);

        DB::table('product_requests')
            ->where('wound_type', 'Venous Leg Ulcer')
            ->update(['wound_type' => 'VLU']);

        DB::table('product_requests')
            ->where('wound_type', 'Pressure Ulcer')
            ->update(['wound_type' => 'PU']);

        DB::table('product_requests')
            ->where('wound_type', 'Traumatic Wound')
            ->update(['wound_type' => 'TW']);

        DB::table('product_requests')
            ->where('wound_type', 'Arterial Ulcer')
            ->update(['wound_type' => 'AU']);

        DB::table('product_requests')
            ->where('wound_type', 'Other')
            ->update(['wound_type' => 'OTHER']);

        // Handle any other variations
        DB::table('product_requests')
            ->where('wound_type', 'Pressure Ulcer/Injury')
            ->update(['wound_type' => 'PU']);

        DB::table('product_requests')
            ->where('wound_type', 'SSI')
            ->update(['wound_type' => 'OTHER']);

        \Log::info('Wound type values updated in product_requests table', [
            'migration' => 'update_wound_type_values_in_product_requests',
            'timestamp' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes if needed
        DB::table('product_requests')
            ->where('wound_type', 'DFU')
            ->update(['wound_type' => 'diabetic_foot_ulcer']);

        DB::table('product_requests')
            ->where('wound_type', 'VLU')
            ->update(['wound_type' => 'venous_leg_ulcer']);

        DB::table('product_requests')
            ->where('wound_type', 'PU')
            ->update(['wound_type' => 'pressure_ulcer']);

        DB::table('product_requests')
            ->where('wound_type', 'TW')
            ->update(['wound_type' => 'traumatic_wound']);

        DB::table('product_requests')
            ->where('wound_type', 'AU')
            ->update(['wound_type' => 'arterial_ulcer']);

        DB::table('product_requests')
            ->where('wound_type', 'OTHER')
            ->update(['wound_type' => 'other']);

        \Log::info('Wound type values reverted in product_requests table', [
            'migration' => 'update_wound_type_values_in_product_requests',
            'timestamp' => now()
        ]);
    }
};
