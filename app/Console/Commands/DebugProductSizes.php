<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Product;
use App\Models\User;
use App\Services\ProductDataService;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebugProductSizes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:product-sizes {product_id?} {--all : Debug all products} {--user_id= : Use specific user for testing}';

    /**
     * The description of the console command.
     */
    protected $description = 'Debug product sizes data at database, model, and API levels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $productId = $this->argument('product_id');
        $debugAll = $this->option('all');
        $userId = $this->option('user_id');

        if (!$productId && !$debugAll) {
            $this->error('Please provide a product_id or use --all flag');
            return 1;
        }

        // Get test user
        $user = $userId ? User::find($userId) : User::where('email', 'provider@example.com')->first();
        if (!$user) {
            $user = User::first();
        }
        $user->load('roles');

        $this->info("=== Product Sizes Debug ===");
        $this->info("User: {$user->email} (ID: {$user->id})");
        $this->info("Roles: " . $user->roles->pluck('name')->join(', '));

        if ($debugAll) {
            $products = Product::limit(5)->get();
            foreach ($products as $product) {
                $this->debugProduct($product, $user);
                $this->line('');
            }
        } else {
            $product = Product::find($productId);
            if (!$product) {
                $this->error("Product with ID {$productId} not found");
                return 1;
            }
            $this->debugProduct($product, $user);
        }

        return 0;
    }

    private function debugProduct(Product $product, User $user)
    {
        $this->info("--- Product: {$product->name} (ID: {$product->id}) ---");
        
        // 1. Database Layer Debug
        $this->line("1. DATABASE LAYER:");
        $rawData = DB::table('msc_products')->where('id', $product->id)->first();
        
        $this->line("  available_sizes (raw): " . ($rawData->available_sizes ?? 'NULL'));
        $this->line("  size_options (raw): " . ($rawData->size_options ?? 'NULL'));
        $this->line("  size_pricing (raw): " . ($rawData->size_pricing ?? 'NULL'));
        
        // Try to decode JSON
        if ($rawData->available_sizes) {
            $decodedSizes = json_decode($rawData->available_sizes, true);
            $this->line("  available_sizes (decoded): " . json_encode($decodedSizes));
        }
        
        if ($rawData->size_options) {
            $decodedOptions = json_decode($rawData->size_options, true);
            $this->line("  size_options (decoded): " . json_encode($decodedOptions));
        }

        // 2. Model Layer Debug
        $this->line("\n2. MODEL LAYER:");
        
        // Test different ways to access the data
        $this->line("  \$product->available_sizes: " . json_encode($product->available_sizes));
        $this->line("  \$product->size_options: " . json_encode($product->size_options));
        $this->line("  \$product->size_pricing: " . json_encode($product->size_pricing));
        
        // Check attributes directly
        $this->line("  \$product->attributes['available_sizes']: " . json_encode($product->attributes['available_sizes'] ?? 'NULL'));
        $this->line("  \$product->attributes['size_options']: " . json_encode($product->attributes['size_options'] ?? 'NULL'));
        
        // Test the accessor method directly
        $accessorResult = $product->getAvailableSizesAttribute($product->attributes['available_sizes'] ?? null);
        $this->line("  getAvailableSizesAttribute() result: " . json_encode($accessorResult));

        // 3. Service Layer Debug
        $this->line("\n3. SERVICE LAYER:");
        $productDataService = app(ProductDataService::class);
        $transformedProduct = $productDataService->transformProduct($product, $user);
        $this->line("  Transformed available_sizes: " . json_encode($transformedProduct['available_sizes'] ?? 'NULL'));
        $this->line("  Transformed size_options: " . json_encode($transformedProduct['size_options'] ?? 'NULL'));

        // 4. Repository Layer Debug
        $this->line("\n4. REPOSITORY LAYER:");
        $productRepository = app(ProductRepository::class);
        $repoProducts = $productRepository->getAllProducts(['q' => $product->q_code]);
        $repoProduct = $repoProducts->first();
        if ($repoProduct) {
            $this->line("  Repository available_sizes: " . json_encode($repoProduct->available_sizes));
            $this->line("  Repository size_options: " . json_encode($repoProduct->size_options));
        }

        // 5. Fresh Model Test
        $this->line("\n5. FRESH MODEL TEST:");
        $freshProduct = Product::where('id', $product->id)->first();
        $this->line("  Fresh model available_sizes: " . json_encode($freshProduct->available_sizes));
        $this->line("  Fresh model size_options: " . json_encode($freshProduct->size_options));

        // 6. Direct Query Test
        $this->line("\n6. DIRECT QUERY TEST:");
        $directQuery = Product::select('id', 'name', 'available_sizes', 'size_options', 'size_pricing')
            ->where('id', $product->id)
            ->first();
        $this->line("  Direct query available_sizes: " . json_encode($directQuery->available_sizes));
        $this->line("  Direct query size_options: " . json_encode($directQuery->size_options));

        // 7. Cast Test
        $this->line("\n7. CAST TEST:");
        $casts = $product->getCasts();
        $this->line("  Model casts: " . json_encode($casts));
        $this->line("  available_sizes cast: " . ($casts['available_sizes'] ?? 'NOT SET'));
        $this->line("  size_options cast: " . ($casts['size_options'] ?? 'NOT SET'));

        // 8. Error Testing
        $this->line("\n8. ERROR TESTING:");
        try {
            $testArray = $product->available_sizes;
            if (is_array($testArray)) {
                $this->line("  ✓ available_sizes is array with " . count($testArray) . " items");
            } else {
                $this->line("  ✗ available_sizes is not array, type: " . gettype($testArray));
            }
        } catch (\Exception $e) {
            $this->line("  ✗ Error accessing available_sizes: " . $e->getMessage());
        }
    }
} 