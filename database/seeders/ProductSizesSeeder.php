<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\Product;
use Illuminate\Support\Facades\DB;

class ProductSizesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding product sizes...');

        // Common wound care product sizes based on industry standards
        $sizeOptions = [
            // Skin substitutes typically come in these sizes (in square cm)
            'skin_substitute' => [
                'size_options' => ['2x2cm', '3x3cm', '4x4cm', '5x5cm', '6x6cm', '8x8cm', '10x10cm'],
                'size_pricing' => [
                    '2x2cm' => 4,    // 4 square cm
                    '3x3cm' => 9,    // 9 square cm
                    '4x4cm' => 16,   // 16 square cm
                    '5x5cm' => 25,   // 25 square cm
                    '6x6cm' => 36,   // 36 square cm
                    '8x8cm' => 64,   // 64 square cm
                    '10x10cm' => 100 // 100 square cm
                ],
                'available_sizes' => [4, 9, 16, 25, 36, 64, 100] // For backward compatibility
            ],
            
            // Membrane wraps and hydrogels
            'membrane_wrap' => [
                'size_options' => ['3x4cm', '4x5cm', '5x7cm', '7x10cm', '10x12cm'],
                'size_pricing' => [
                    '3x4cm' => 12,   // 12 square cm
                    '4x5cm' => 20,   // 20 square cm
                    '5x7cm' => 35,   // 35 square cm
                    '7x10cm' => 70,  // 70 square cm
                    '10x12cm' => 120 // 120 square cm
                ],
                'available_sizes' => [12, 20, 35, 70, 120]
            ],
            
            // Collagen matrices
            'collagen_matrix' => [
                'size_options' => ['1x1cm', '2x2cm', '3x3cm', '4x4cm', '5x5cm', '6x8cm'],
                'size_pricing' => [
                    '1x1cm' => 1,    // 1 square cm
                    '2x2cm' => 4,    // 4 square cm
                    '3x3cm' => 9,    // 9 square cm
                    '4x4cm' => 16,   // 16 square cm
                    '5x5cm' => 25,   // 25 square cm
                    '6x8cm' => 48    // 48 square cm
                ],
                'available_sizes' => [1, 4, 9, 16, 25, 48]
            ],
            
            // Dermal regeneration templates
            'dermal_template' => [
                'size_options' => ['2x3cm', '4x5cm', '5x7cm', '8x10cm', '10x15cm'],
                'size_pricing' => [
                    '2x3cm' => 6,    // 6 square cm
                    '4x5cm' => 20,   // 20 square cm
                    '5x7cm' => 35,   // 35 square cm
                    '8x10cm' => 80,  // 80 square cm
                    '10x15cm' => 150 // 150 square cm
                ],
                'available_sizes' => [6, 20, 35, 80, 150]
            ]
        ];

        // Product categories mapping
        $productCategoryMapping = [
            'Ensano ACA' => 'skin_substitute',
            'Complete FT' => 'skin_substitute', 
            'Membrane Wrap' => 'membrane_wrap',
            'Membrane Wrap Hydro' => 'membrane_wrap',
            'Neostim TL' => 'dermal_template',
            'Amnio AMP' => 'skin_substitute',
            'Complete AA' => 'skin_substitute',
            'Complete FI' => 'skin_substitute',
            'Neostim' => 'dermal_template',
            'PalinGen Xplus' => 'collagen_matrix',
            'PalinGen Flow' => 'collagen_matrix',
            'MatriStem' => 'dermal_template',
            'SurgiMend' => 'dermal_template'
        ];

        $products = Product::all();
        $updated = 0;

        foreach ($products as $product) {
            // Determine category based on product name
            $category = 'skin_substitute'; // default
            foreach ($productCategoryMapping as $namePattern => $categoryType) {
                if (str_contains(strtolower($product->name), strtolower($namePattern))) {
                    $category = $categoryType;
                    break;
                }
            }

            // Get size data for this category
            $sizes = $sizeOptions[$category];

            // Update the product
            $product->update([
                'size_options' => $sizes['size_options'],
                'size_pricing' => $sizes['size_pricing'],
                'available_sizes' => $sizes['available_sizes'],
                'size_unit' => 'cm'
            ]);

            $this->command->line("Updated {$product->name} with {$category} sizes: " . 
                implode(', ', $sizes['size_options']));
            $updated++;
        }

        $this->command->info("Successfully updated {$updated} products with size data.");

        // Verify the data was seeded correctly
        $this->command->info("\nVerifying seeded data...");
        $sampleProduct = Product::first();
        if ($sampleProduct) {
            $this->command->line("Sample product: {$sampleProduct->name}");
            $this->command->line("Available sizes: " . json_encode($sampleProduct->available_sizes));
            $this->command->line("Size options: " . json_encode($sampleProduct->size_options));
            $this->command->line("Size pricing: " . json_encode($sampleProduct->size_pricing));
        }
    }
} 