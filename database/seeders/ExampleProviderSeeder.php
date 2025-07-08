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
        // 1. Create or get an account first
        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Example Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create or get the organization with account_id
        $organization = DB::table('organizations')->updateOrInsert(
            ['name' => 'Example Organization'],
            [
                'name' => 'Example Organization',
                'account_id' => $accountId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $organizationId = DB::table('organizations')->where('name', 'Example Organization')->value('id');

        // 3. Create or get provider user with account_id
        $providerEmail = 'provider@example.com';
        $existingUser = DB::table('users')->where('email', $providerEmail)->first();

        if ($existingUser) {
            $providerId = $existingUser->id;
            $this->command->info('Provider user already exists, using existing user ID: ' . $providerId);
        } else {
            $providerId = DB::table('users')->insertGetId([
                'account_id' => $accountId,
                'first_name' => 'Example',
                'last_name' => 'Provider',
                'email' => $providerEmail,
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Create or update provider profile
        $existingProfile = DB::table('provider_profiles')->where('provider_id', $providerId)->first();
        if (!$existingProfile) {
            DB::table('provider_profiles')->insert([
                'provider_id' => $providerId,
                'npi' => '1234567890',
                'tax_id' => '12-3456789',
                'ptan' => 'PTAN123456',
                'specialty' => 'Wound Care',
                'verification_status' => 'verified',
                'profile_completion_percentage' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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

        // 6. Attach products to provider (only if not already attached)
        foreach ($productIds as $productId) {
            $existingProduct = DB::table('provider_products')
                ->where('user_id', $providerId)
                ->where('product_id', $productId)
                ->first();

            if (!$existingProduct) {
                DB::table('provider_products')->insert([
                    'user_id' => $providerId,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Example provider seeded successfully!');
        $this->command->info('Email: provider@example.com');
        $this->command->info('Password: password123');
    }
}
