<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExampleProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or get the organization
        $organization = DB::table('organizations')->updateOrInsert(
            ['name' => 'Example Organization'],
            [
                'name' => 'Example Organization',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $organizationId = DB::table('organizations')->where('name', 'Example Organization')->value('id');

        // 2. Create provider user
        $providerEmail = 'provider@example.com';
        $providerId = DB::table('users')->insertGetId([
            'name' => 'Example Provider',
            'email' => $providerEmail,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Fill out provider profile and mark as verified
        DB::table('provider_profiles')->insert([
            'user_id' => $providerId,
            'organization_id' => $organizationId,
            'npi' => '1234567890',
            'phone' => '555-123-4567',
            'address' => '123 Main St',
            'city' => 'Sample City',
            'state' => 'CA',
            'zip' => '90001',
            'verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Attach provider to organization (if needed, already done above)
        // 5. Create 5 products and attach to provider
        $productIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $productId = DB::table('msc_products')->insertGetId([
                'name' => 'Example Product ' . $i,
                'sku' => 'EX-PROD-' . $i,
                'manufacturer_id' => 1, // Assumes manufacturer with ID 1 exists
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productIds[] = $productId;
        }
        foreach ($productIds as $productId) {
            DB::table('provider_products')->insert([
                'provider_id' => $providerId,
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
