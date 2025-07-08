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

        // Get account ID
        $accountId = DB::table('accounts')->first()->id ?? 1;

        // 2. Create provider user with proper fields
        $providerEmail = 'provider@example.com';
        
        // Check if user already exists
        $existingUser = DB::table('users')->where('email', $providerEmail)->first();
        if ($existingUser) {
            $providerId = $existingUser->id;
            $this->command->info("Provider user already exists: {$providerEmail}");
        } else {
            $providerId = DB::table('users')->insertGetId([
                'account_id' => $accountId,
                'first_name' => 'Example',
                'last_name' => 'Provider',
                'name' => 'Example Provider',
                'email' => $providerEmail,
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'current_organization_id' => $organizationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("Created provider user: {$providerEmail}");
        }

        // 3. Assign provider role if not already assigned
        $providerRole = DB::table('roles')->where('slug', 'provider')->first();
        if ($providerRole) {
            $existingRoleAssignment = DB::table('user_role')
                ->where('user_id', $providerId)
                ->where('role_id', $providerRole->id)
                ->exists();
                
            if (!$existingRoleAssignment) {
                DB::table('user_role')->insert([
                    'user_id' => $providerId,
                    'role_id' => $providerRole->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Assigned provider role to user");
            }
        }

        // 4. Fill out provider profile and mark as verified
        $existingProfile = DB::table('provider_profiles')->where('user_id', $providerId)->first();
        if (!$existingProfile) {
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
            $this->command->info("Created provider profile");
        }

        // 5. Create 5 products and attach to provider only if they don't exist
        $existingProducts = DB::table('msc_products')->where('sku', 'like', 'EX-PROD-%')->count();
        
        if ($existingProducts < 5) {
            // Get a manufacturer to use
            $manufacturer = DB::table('manufacturers')->first();
            if (!$manufacturer) {
                $this->command->warn("No manufacturers found. Creating a default one.");
                $manufacturerId = DB::table('manufacturers')->insertGetId([
                    'name' => 'Default Manufacturer',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $manufacturerId = $manufacturer->id;
            }

            $productIds = [];
            for ($i = 1; $i <= 5; $i++) {
                $existingProduct = DB::table('msc_products')->where('sku', 'EX-PROD-' . $i)->first();
                if (!$existingProduct) {
                    $productId = DB::table('msc_products')->insertGetId([
                        'name' => 'Example Product ' . $i,
                        'sku' => 'EX-PROD-' . $i,
                        'manufacturer_id' => $manufacturerId,
                        'category' => 'Example',
                        'description' => 'Example product for demonstration purposes',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $productIds[] = $productId;
                    $this->command->info("Created product: Example Product {$i}");
                } else {
                    $productIds[] = $existingProduct->id;
                }
            }
            
            // Associate products with provider
            foreach ($productIds as $productId) {
                $existingAssociation = DB::table('provider_products')
                    ->where('provider_id', $providerId)
                    ->where('product_id', $productId)
                    ->exists();
                    
                if (!$existingAssociation) {
                    DB::table('provider_products')->insert([
                        'provider_id' => $providerId,
                        'product_id' => $productId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info("Associated products with provider");
        } else {
            $this->command->info("Example products already exist");
        }
    }
}
