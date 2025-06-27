<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateProductSizesSeeder extends Seeder
{
    /**
     * Common wound care product sizes
     */
    private $commonSizes = [
        // Label => Square CM equivalent
        '1x1' => 6.45,      // 1 inch = 2.54cm, so 1x1 inch = 6.45 sq cm
        '2x2' => 25.81,
        '2x3' => 38.71,
        '2x4' => 51.61,
        '3x3' => 58.06,
        '3x4' => 77.42,
        '4x4' => 103.23,
        '4x5' => 129.03,
        '4x6' => 154.84,
        '5x5' => 161.29,
        '6x6' => 232.26,
        '6x8' => 309.68,
        '8x8' => 412.90,
        '8x10' => 516.13,
        '10x10' => 645.16,
        '10x12' => 774.19,
        '12x12' => 929.03,
    ];

    /**
     * Product-specific size configurations
     */
    private $productSizes = [
        // Standard small wound products
        'small' => ['1x1', '2x2', '2x3', '3x3'],

        // Standard medium wound products
        'medium' => ['2x2', '3x3', '4x4', '4x5'],

        // Standard large wound products
        'large' => ['4x4', '4x6', '6x6', '8x8'],

        // Extra large wound products
        'xlarge' => ['6x6', '8x8', '10x10', '12x12'],

        // Full range products
        'full' => ['2x2', '2x3', '3x3', '4x4', '4x5', '4x6', '6x6', '8x8', '10x10'],

        // Common sizes for most products
        'common' => ['2x2', '4x4', '6x6', '8x8'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = DB::table('msc_products')->get();

        foreach ($products as $product) {
            // Determine which size set to use based on product name/category
            $sizeSet = $this->determineSizeSet($product);

            // Get the sizes for this product
            $sizes = $this->productSizes[$sizeSet];

            // Build size_pricing object
            $sizePricing = [];
            foreach ($sizes as $size) {
                if (isset($this->commonSizes[$size])) {
                    $sizePricing[$size] = $this->commonSizes[$size];
                }
            }

            // Update the product
            DB::table('msc_products')
                ->where('id', $product->id)
                ->update([
                    'size_options' => json_encode($sizes),
                    'size_pricing' => json_encode($sizePricing),
                    'size_unit' => 'in', // Default to inches
                    'updated_at' => now(),
                ]);

            echo "Updated {$product->name} with " . count($sizes) . " size options\n";
        }

        echo "\nAll products have been updated with size options!\n";
    }

    /**
     * Determine which size set to use based on product characteristics
     */
    private function determineSizeSet($product): string
    {
        $name = strtolower($product->name);
        $category = strtolower($product->category ?? '');

        // Small wound products
        if (str_contains($name, 'small') || str_contains($name, 'minor')) {
            return 'small';
        }

        // Large wound products
        if (str_contains($name, 'large') || str_contains($name, 'maxx')) {
            return 'large';
        }

        // Extra large products
        if (str_contains($name, 'xl') || str_contains($name, 'extra')) {
            return 'xlarge';
        }

        // Premium products often have full range
        if (str_contains($name, 'complete') || str_contains($name, 'premium')) {
            return 'full';
        }

        // Specific product patterns
        if (str_contains($name, 'amnio') || str_contains($name, 'membrane')) {
            return 'full';
        }

        if (str_contains($name, 'collagen') || str_contains($name, 'derm')) {
            return 'medium';
        }

        // Default to common sizes
        return 'common';
    }
}
