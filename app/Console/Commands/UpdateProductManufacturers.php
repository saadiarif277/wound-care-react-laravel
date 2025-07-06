<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;

class UpdateProductManufacturers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:update-manufacturers {--force : Force update even if manufacturer_id is already set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing products to have proper manufacturer relationships';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Updating product manufacturer relationships...');

        $force = $this->option('force');
        $query = Product::query();

        if (!$force) {
            $query->whereNull('manufacturer_id');
        }

        $products = $query->get();
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($products as $product) {
            $manufacturerName = $product->manufacturer;

            if (empty($manufacturerName)) {
                $this->warn("⚠️  Product {$product->name} ({$product->q_code}) has no manufacturer name");
                $skippedCount++;
                continue;
            }

            // Find or create manufacturer
            $manufacturer = Manufacturer::firstOrCreate(
                ['name' => $manufacturerName],
                [
                    'name' => $manufacturerName,
                    'is_active' => true,
                    'contact_email' => 'info@' . strtolower(str_replace([' ', '&', '.'], ['', 'and', ''], $manufacturerName)) . '.com',
                ]
            );

            // Update product with manufacturer_id
            $product->manufacturer_id = $manufacturer->id;
            $product->save();

            $this->info("✅ Updated {$product->name} ({$product->q_code}) -> {$manufacturer->name}");
            $updatedCount++;
        }

        $this->info("\n🎉 Update completed!");
        $this->info("📊 Products updated: {$updatedCount}");
        $this->info("⏭️  Products skipped: {$skippedCount}");
        $this->info("🏭 Total manufacturers: " . Manufacturer::count());
        $this->info("🔗 Products with manufacturer relationships: " . Product::whereNotNull('manufacturer_id')->count());
    }
}
