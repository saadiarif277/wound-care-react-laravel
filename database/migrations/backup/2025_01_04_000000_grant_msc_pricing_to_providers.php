<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Grant providers access to MSC pricing for clinical decision making
     */
    public function up(): void
    {
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        $mscPricingPermission = DB::table('permissions')->where('slug', 'view-msc-pricing')->first();
        
        if ($providerRole && $mscPricingPermission) {
            // Check if permission already exists
            $exists = DB::table('role_permission')
                ->where('role_id', $providerRole->id)
                ->where('permission_id', $mscPricingPermission->id)
                ->exists();
                
            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $providerRole->id,
                    'permission_id' => $mscPricingPermission->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        $mscPricingPermission = DB::table('permissions')->where('slug', 'view-msc-pricing')->first();
        
        if ($providerRole && $mscPricingPermission) {
            DB::table('role_permission')
                ->where('role_id', $providerRole->id)
                ->where('permission_id', $mscPricingPermission->id)
                ->delete();
        }
    }
}; 