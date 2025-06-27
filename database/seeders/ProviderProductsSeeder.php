<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Order\Product;
use Carbon\Carbon;

class ProviderProductsSeeder extends Seeder
{
    /**
     * Seed provider-product relationships to ensure providers have access to products
     */
    public function run(): void
    {
        $this->command->info('Seeding provider product relationships...');

        // Get all providers
        $providers = User::whereHas('roles', function ($query) {
            $query->where('slug', 'provider');
        })->get();

        if ($providers->isEmpty()) {
            $this->command->warn('No providers found. Run DatabaseSeeder first.');
            return;
        }

        // Get all active products
        $products = Product::where('is_active', true)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No active products found. Run ProductSeeder first.');
            return;
        }

        $this->command->info("Found {$providers->count()} providers and {$products->count()} products");

        // Different onboarding scenarios for testing
        $onboardingScenarios = [
            'fully_onboarded' => [
                'products' => $products->pluck('id')->toArray(), // All products
                'status' => 'active',
                'expiration' => null
            ],
            'medicare_only' => [
                'products' => $products->whereIn('q_code', ['Q4250', 'Q4290'])->pluck('id')->toArray(),
                'status' => 'active',
                'expiration' => null
            ],
            'medicaid_membrane' => [
                'products' => $products->whereIn('q_code', ['Q4290', 'Q4205'])->pluck('id')->toArray(),
                'status' => 'active',
                'expiration' => null
            ],
            'commercial_only' => [
                'products' => $products->where('q_code', 'Q4154')->pluck('id')->toArray(),
                'status' => 'active',
                'expiration' => null
            ],
            'limited_onboarding' => [
                'products' => $products->whereIn('q_code', ['Q4154', 'Q4271'])->pluck('id')->take(2)->toArray(),
                'status' => 'active',
                'expiration' => Carbon::now()->addMonths(6)
            ]
        ];

        // Assign different scenarios to providers
        foreach ($providers as $index => $provider) {
            // Determine which scenario to use
            $scenarioKey = match($index) {
                0 => 'fully_onboarded',      // First provider gets all products
                1 => 'medicare_only',        // Second provider gets Medicare products
                2 => 'medicaid_membrane',    // Third provider gets Medicaid products
                3 => 'commercial_only',      // Fourth provider gets commercial products
                default => 'limited_onboarding' // Others get limited products
            };

            $scenario = $onboardingScenarios[$scenarioKey];

            $this->command->info("Assigning {$scenarioKey} scenario to {$provider->name} ({$provider->email})");

            // Clear existing relationships
            DB::table('provider_products')->where('user_id', $provider->id)->delete();

            // Create new relationships
            foreach ($scenario['products'] as $productId) {
                $product = $products->find($productId);
                if (!$product) continue;

                DB::table('provider_products')->insert([
                    'user_id' => $provider->id,
                    'product_id' => $productId,
                    'onboarding_status' => $scenario['status'],
                    'onboarded_at' => Carbon::now()->subDays(rand(30, 180)),
                    'expiration_date' => $scenario['expiration'],
                    'notes' => "Onboarded with {$product->name} ({$product->q_code}) - {$scenarioKey} scenario",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $assignedCount = count($scenario['products']);
            $this->command->info("  - Assigned {$assignedCount} products");
        }

        // Special case: Ensure the test provider (provider@example.com) has a good variety
        $testProvider = User::where('email', 'provider@example.com')->first();
        if ($testProvider) {
            $this->command->info("\nEnsuring test provider has comprehensive product access...");
            
            // Give test provider access to key products from each category
            $keyProducts = [
                'Q4154', // BioVance (Commercial/PPO)
                'Q4250', // Amnio AMP (Medicare 0-250)
                'Q4290', // Membrane Wrap Hydro (Medicare all sizes)
                'Q4271', // Complete FT (Medicaid default)
                'Q4238', // Derm-maxx (Medicaid default)
                'Q4205', // Membrane Wrap (Medicaid specific states)
                'Q4191', // Restorigin (Medicaid specific states)
            ];

            DB::table('provider_products')->where('user_id', $testProvider->id)->delete();

            foreach ($keyProducts as $qCode) {
                $product = $products->where('q_code', $qCode)->first();
                if ($product) {
                    DB::table('provider_products')->insert([
                        'user_id' => $testProvider->id,
                        'product_id' => $product->id,
                        'onboarding_status' => 'active',
                        'onboarded_at' => Carbon::now()->subDays(90),
                        'expiration_date' => null,
                        'notes' => "Test provider comprehensive access - {$product->name}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            $this->command->info("Test provider now has access to " . count($keyProducts) . " key products");
        }

        $this->command->info("\nProvider product seeding completed!");
        
        // Display summary
        $this->displaySummary();
    }

    private function displaySummary(): void
    {
        $this->command->info("\n=== Provider Product Summary ===");
        
        $providers = User::whereHas('roles', function ($query) {
            $query->where('slug', 'provider');
        })->with('onboardedProducts')->get();

        foreach ($providers as $provider) {
            $activeProducts = $provider->onboardedProducts()
                ->wherePivot('onboarding_status', 'active')
                ->where(function ($q) {
                    $q->whereNull('provider_products.expiration_date')
                        ->orWhere('provider_products.expiration_date', '>=', now());
                })->get();

            $this->command->info("\n{$provider->name} ({$provider->email}):");
            if ($activeProducts->isEmpty()) {
                $this->command->warn("  - No active products");
            } else {
                foreach ($activeProducts as $product) {
                    $expiration = $product->pivot->expiration_date 
                        ? " (expires: {$product->pivot->expiration_date->format('Y-m-d')})" 
                        : "";
                    $this->command->info("  - {$product->name} (Q{$product->q_code}){$expiration}");
                }
            }
        }
    }
}