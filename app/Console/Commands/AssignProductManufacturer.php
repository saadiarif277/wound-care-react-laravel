<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;

class AssignProductManufacturer extends Command
{
    protected $signature = 'product:assign-manufacturer 
                            {product_code : The product Q-code}
                            {manufacturer_name : The manufacturer name to assign}';

    protected $description = 'Assign a manufacturer to a product by Q-code';

    public function handle()
    {
        $productCode = $this->argument('product_code');
        $manufacturerName = $this->argument('manufacturer_name');

        $this->info("🔍 Finding product with Q-code: {$productCode}");
        
        $product = Product::where('q_code', $productCode)->first();
        if (!$product) {
            $this->error("❌ Product not found with Q-code: {$productCode}");
            return 1;
        }

        $this->info("✅ Found product: {$product->name}");

        $this->info("🔍 Finding manufacturer: {$manufacturerName}");
        
        $manufacturer = Manufacturer::where('name', 'LIKE', "%{$manufacturerName}%")->first();
        if (!$manufacturer) {
            $this->error("❌ Manufacturer not found: {$manufacturerName}");
            $this->info("Available manufacturers:");
            Manufacturer::all()->each(function($m) {
                $this->line("  • {$m->name} (ID: {$m->id})");
            });
            return 1;
        }

        $this->info("✅ Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})");

        if ($product->manufacturer_id === $manufacturer->id) {
            $this->info("✅ Product already assigned to this manufacturer");
            return 0;
        }

        $this->info("🔄 Updating product manufacturer assignment...");
        
        $product->update(['manufacturer_id' => $manufacturer->id]);
        
        $this->info("✅ Successfully assigned {$product->name} to {$manufacturer->name}");
        
        // Test Docuseal integration
        $this->info("🧪 Testing Docuseal integration...");
        $this->call('docuseal:debug', ['--product' => $productCode]);
        
        return 0;
    }
}
