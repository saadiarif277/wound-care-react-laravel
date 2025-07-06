<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order\Product;
use App\Models\ProductSize;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏥 Seeding Wound Care Products with CMS Data...');

        // CMS Product Data with actual sizes from the provided dataset
        $products = [
            [
                'sku' => 'ENS-Q4275',
                'name' => 'Ensano ACA',
                'description' => 'Advanced cellular allograft for complex wound management and tissue regeneration.',
                'manufacturer' => 'ACZ & Associates',
                'category' => 'Biologic',
                'q_code' => 'Q4275',
                'national_asp' => 2676.50,
                'price_per_sq_cm' => 2676.50,
                'mue' => null,
                'size_labels' => null, // No specific sizes provided
                'graph_type' => 'Cellular Allograft',
                'commission_rate' => 4.0,
                'is_active' => true,
            ],
            [
                'sku' => 'COM-Q4271',
                'name' => 'Complete FT',
                'description' => 'Full-thickness skin substitute designed for deeper wounds with exposed structures.',
                'manufacturer' => 'Advanced Solution',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4271',
                'national_asp' => 1399.12,
                'price_per_sq_cm' => 1399.12,
                'mue' => 300,
                'size_labels' => ['12mm disc', '2x4cm', '16mm disc', '4x4cm', '1.5x1.5cm', '4x6cm', '2x2cm', '5x5cm', '2x3cm', '4x8cm'],
                'graph_type' => 'Full Thickness',
                'commission_rate' => 4.5,
                'is_active' => true,
            ],
            [
                'sku' => 'MEM-Q4205',
                'name' => 'Membrane Wrap',
                'description' => 'Versatile membrane wrap for various wound types and applications.',
                'manufacturer' => 'Advanced Solution',
                'category' => 'Biologic',
                'q_code' => 'Q4205',
                'national_asp' => 1055.97,
                'price_per_sq_cm' => 1055.97,
                'mue' => 480,
                'size_labels' => ['2x2cm', '4x4cm', '2x3cm', '4x6cm', '2x4cm', '4x8cm'],
                'graph_type' => 'Membrane Wrap',
                'commission_rate' => 4.5,
                'is_active' => true,
            ],
            [
                'sku' => 'MEM-Q4290',
                'name' => 'Membrane Wrap Hydro',
                'description' => 'Hydrated membrane wrap with enhanced healing properties for complex wounds.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'Biologic',
                'q_code' => 'Q4290',
                'national_asp' => 1841.00,
                'price_per_sq_cm' => 1841.00,
                'mue' => 480,
                'size_labels' => ['2x2cm', '4x4cm', '2x3cm', '4x6cm', '2x4cm', '4x8cm'],
                'graph_type' => 'Hydrated Membrane',
                'commission_rate' => 3.5,
                'is_active' => true,
            ],
            [
                'sku' => 'NEO-Q4265',
                'name' => 'Neostim TL',
                'description' => 'Triple-layer wound matrix for complex wound management and enhanced healing.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4265',
                'national_asp' => 1750.26,
                'price_per_sq_cm' => 1750.26,
                'mue' => 180,
                'size_labels' => ['4x4cm', '5x5cm', '10x10cm'],
                'graph_type' => 'Triple Layer',
                'commission_rate' => 3.0,
                'is_active' => true,
            ],
            [
                'sku' => 'NEO-Q4267',
                'name' => 'Neostim DL',
                'description' => 'Dual-layer wound matrix for enhanced healing outcomes.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4267',
                'national_asp' => 274.60,
                'price_per_sq_cm' => 274.60,
                'mue' => 180,
                'size_labels' => ['4x4cm', '5x5cm', '10x10cm'],
                'graph_type' => 'Dual Layer',
                'commission_rate' => 7.0,
                'is_active' => true,
            ],
            [
                'sku' => 'NEO-Q4266',
                'name' => 'Neostim SL',
                'description' => 'Single-layer wound matrix for routine wound care applications.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4266',
                'national_asp' => 989.67,
                'price_per_sq_cm' => 989.67,
                'mue' => 180,
                'size_labels' => ['4x4cm', '5x5cm', '10x10cm'],
                'graph_type' => 'Single Layer',
                'commission_rate' => 4.5,
                'is_active' => true,
            ],
            [
                'sku' => 'RES-Q4191',
                'name' => 'Restorigin',
                'description' => 'Regenerative biologic matrix for tissue restoration and wound healing.',
                'manufacturer' => 'Extremity Care LLC',
                'category' => 'Biologic',
                'q_code' => 'Q4191',
                'national_asp' => 940.15,
                'price_per_sq_cm' => 940.15,
                'mue' => 120,
                'size_labels' => ['2x2cm', '2x3cm', '5x5cm', '16mm disc', '4x4cm', '2x4cm', '4x8cm', '4x6cm'],
                'graph_type' => 'Regenerative Matrix',
                'commission_rate' => 5.0,
                'is_active' => true,
            ],
            [
                'sku' => 'REV-Q4289',
                'name' => 'Revoshield+ Amnio',
                'description' => 'Advanced amniotic membrane with enhanced protective properties.',
                'manufacturer' => 'ACZ & Associates',
                'category' => 'Biologic',
                'q_code' => 'Q4289',
                'national_asp' => 1602.22,
                'price_per_sq_cm' => 1602.22,
                'mue' => 300,
                'size_labels' => null, // No specific sizes provided
                'graph_type' => 'Amniotic Membrane',
                'commission_rate' => 4.0,
                'is_active' => true,
            ],
            [
                'sku' => 'AMN-Q4250',
                'name' => 'Amnio AMP',
                'description' => 'Amniotic membrane product for advanced wound care and tissue regeneration.',
                'manufacturer' => 'MedLife Solutions',
                'category' => 'Biologic',
                'q_code' => 'Q4250',
                'national_asp' => 2863.13,
                'price_per_sq_cm' => 2863.13,
                'mue' => 250,
                'size_labels' => null, // No specific sizes provided
                'graph_type' => 'Amniotic Membrane',
                'commission_rate' => 3.5,
                'is_active' => true,
            ],
            [
                'sku' => 'COM-Q4303',
                'name' => 'Complete AA',
                'description' => 'Advanced amniotic allograft for complex wound management.',
                'manufacturer' => 'Advanced Solution',
                'category' => 'Biologic',
                'q_code' => 'Q4303',
                'national_asp' => 3397.40,
                'price_per_sq_cm' => 3397.40,
                'mue' => 300,
                'size_labels' => null, // No specific sizes provided
                'graph_type' => 'Amniotic Allograft',
                'commission_rate' => 3.0,
                'is_active' => true,
            ],
            [
                'sku' => 'AMN-Q4239',
                'name' => 'Amnio-Maxx',
                'description' => 'Maximum strength amniotic membrane for challenging wound cases.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'Biologic',
                'q_code' => 'Q4239',
                'national_asp' => 2349.92,
                'price_per_sq_cm' => 2349.92,
                'mue' => null,
                'size_labels' => ['4x4cm', '5x5cm', '10x10cm'],
                'graph_type' => 'Amniotic Membrane',
                'commission_rate' => 3.5,
                'is_active' => true,
            ],
            [
                'sku' => 'COL-Q4193',
                'name' => 'Coll-e-derm',
                'description' => 'Collagen-based dermal matrix for enhanced wound healing and tissue regeneration.',
                'manufacturer' => 'Extremity Care LLC',
                'category' => 'CollageMatrix',
                'q_code' => 'Q4193',
                'national_asp' => 1608.27,
                'price_per_sq_cm' => 1608.27,
                'mue' => 180,
                'size_labels' => ['2x2cm', '4x4cm', '2x3cm', '4x6cm', '2x4cm', '4x8cm'],
                'graph_type' => 'Collagen Matrix',
                'commission_rate' => 4.5,
                'is_active' => true,
            ],
            [
                'sku' => 'DER-Q4238',
                'name' => 'Derm-maxx',
                'description' => 'Maximum strength dermal matrix for advanced wound care applications.',
                'manufacturer' => 'BioWound Solutions',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4238',
                'national_asp' => 1644.99,
                'price_per_sq_cm' => 1644.99,
                'mue' => 128,
                'size_labels' => ['4x4cm', '5x5cm'],
                'graph_type' => 'Dermal Matrix',
                'commission_rate' => 4.0,
                'is_active' => true,
            ],
            [
                'sku' => 'DER-Q4313',
                'name' => 'Dermabind FM',
                'description' => 'Fibrin matrix dermal substitute for complex wound management.',
                'manufacturer' => 'ACZ & Associates',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4313',
                'national_asp' => 3337.23,
                'price_per_sq_cm' => 3337.23,
                'mue' => 99,
                'size_labels' => null, // No specific sizes provided
                'graph_type' => 'Fibrin Matrix',
                'commission_rate' => 3.0,
                'is_active' => true,
            ],

            // Legacy products for backward compatibility
            [
                'sku' => 'BIO-Q4154',
                'name' => 'Biovance',
                'description' => 'Decellularized, dehydrated human amniotic membrane that preserves the natural extracellular matrix components.',
                'manufacturer' => 'CELULARITY',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4154',
                'national_asp' => 550.64,
                'price_per_sq_cm' => 550.64,
                'mue' => 36,
                'size_labels' => ['2x2cm', '2x3cm', '2x4cm', '3x3cm', '3x4cm', '4x4cm', '5x5cm', '6x6cm'], // Legacy sizes
                'graph_type' => 'Amniotic Membrane',
                'commission_rate' => 5.0,
                'is_active' => true,
            ],
            [
                'sku' => 'IMP-Q4262',
                'name' => 'Impax Dual Layer Membrane',
                'description' => 'Advanced dual-layer membrane designed for complex wound management.',
                'manufacturer' => 'LEGACY MEDICAL CONSULTANTS',
                'category' => 'SkinSubstitute',
                'q_code' => 'Q4262',
                'national_asp' => 169.86,
                'price_per_sq_cm' => 169.86,
                'mue' => 300,
                'size_labels' => ['2x2cm', '2x3cm', '4x4cm', '4x6cm', '4x8cm'], // Legacy sizes
                'graph_type' => 'Dual Layer',
                'commission_rate' => 6.0,
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            // Extract size labels for separate handling
            $sizeLabels = $productData['size_labels'];
            unset($productData['size_labels']);

            // Get or create manufacturer and set manufacturer_id
            $manufacturerName = $productData['manufacturer'];
            $manufacturer = \App\Models\Order\Manufacturer::firstOrCreate(
                ['name' => $manufacturerName],
                [
                    'name' => $manufacturerName,
                    'is_active' => true,
                    'contact_email' => 'info@' . strtolower(str_replace([' ', '&', '.'], ['', 'and', ''], $manufacturerName)) . '.com',
                ]
            );

            // Set manufacturer_id instead of manufacturer string
            $productData['manufacturer_id'] = $manufacturer->id;
            unset($productData['manufacturer']); // Remove the string field

            // Create or update the product
            $product = Product::firstOrCreate(
                ['q_code' => $productData['q_code']],
                array_merge($productData, [
                    'cms_last_updated' => now(),
                    'available_sizes' => [], // Keep empty array for backward compatibility
                ])
            );

            if ($product->wasRecentlyCreated) {
                $this->command->info("✅ Created product: {$product->name} ({$product->q_code}) - Manufacturer: {$manufacturer->name}");

                // Create sizes if provided
                if ($sizeLabels && is_array($sizeLabels)) {
                    foreach ($sizeLabels as $index => $sizeLabel) {
                        $size = ProductSize::createFromLabel($product->id, $sizeLabel, $index + 1);
                        $this->command->info("   📏 Created size: {$size->size_label} ({$size->area_cm2} cm²)");
                    }
                }

                // Record initial pricing history
                $product->recordPricingChange(
                    'initial_load',
                    ['national_asp', 'mue'],
                    null,
                    null,
                    'Initial product seeding with CMS data'
                );
            } else {
                $this->command->info("⚠️  Product already exists: {$product->name} ({$product->q_code})");
            }
        }

        $this->command->info('🎉 Product seeding completed!');
        $this->command->info('📊 Total products in database: ' . Product::count());
        $this->command->info('🏭 Total manufacturers: ' . \App\Models\Order\Manufacturer::count());
        $this->command->info('💰 Products with CMS pricing: ' . Product::whereNotNull('national_asp')->count());
        $this->command->info('🔢 Products with MUE limits: ' . Product::whereNotNull('mue')->count());
        $this->command->info('📏 Product sizes created: ' . ProductSize::count());
        $this->command->info('🔗 Products with manufacturer relationships: ' . Product::whereNotNull('manufacturer_id')->count());
    }
}
