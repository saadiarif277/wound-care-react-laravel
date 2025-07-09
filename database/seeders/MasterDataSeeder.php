<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\Order\Category;
use App\Models\ProductSize;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏥 Seeding Master Data (Manufacturers, Products, Sizes, Templates)...');

        DB::transaction(function () {
            // 1. Create categories first
            $this->createCategories();
            
            // 2. Create all manufacturers, products, sizes, and templates together
            $this->createMasterData();
        });

        $this->command->info('✅ Master data seeding completed!');
        $this->displaySummary();
    }

    private function createCategories(): void
    {
        $categories = [
            [
                'name' => 'SkinSubstitute',
                'description' => 'Skin substitute products for wound healing including decellularized matrices and synthetic alternatives',
                'sort_order' => 1,
            ],
            [
                'name' => 'Biologic',
                'description' => 'Biologic products including amniotic membranes, collagen matrices, and growth factor products',
                'sort_order' => 2,
            ],
            [
                'name' => 'CollageMatrix',
                'description' => 'Collagen-based matrices and scaffolds for tissue regeneration',
                'sort_order' => 3,
            ],
            [
                'name' => 'Dressing',
                'description' => 'Advanced wound dressings and topical treatments',
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData + ['is_active' => true]
            );
        }
    }

    private function createMasterData(): void
    {
        $masterData = [
            [
                'manufacturer' => [
                    'name' => 'ACZ & Associates',
                    'contact_email' => 'orders@aczassociates.com',
                    'contact_phone' => '555-ACZ-ASSO',
                    'website' => 'https://aczassociates.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '852440', 'name' => 'ACZ IVR Form'],
                    ['type' => 'OrderForm', 'template_id' => '852554', 'name' => 'ACZ Order Form'],
                ],
                'products' => [
                    [
                        'name' => 'Ensano AC',
                        'sku' => 'ACZ-ENSANOAC',
                        'q_code' => 'Q4274',
                        'national_asp' => 1832.31,
                        'price_per_sq_cm' => 114.52,
                        'description' => 'Advanced cellular allograft for complex wound management',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '1×1 cm', 'area_cm2' => 1, 'length_mm' => 10, 'width_mm' => 10],
                            ['size_label' => '1×2 cm', 'area_cm2' => 2, 'length_mm' => 10, 'width_mm' => 20],
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×3 cm', 'area_cm2' => 6, 'length_mm' => 20, 'width_mm' => 30],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×6 cm', 'area_cm2' => 24, 'length_mm' => 40, 'width_mm' => 60],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                        ]
                    ],
                    [
                        'name' => 'Dermabind FM',
                        'sku' => 'ACZ-DERMABINDFM',
                        'q_code' => 'Q4313',
                        'national_asp' => 3520.68,
                        'price_per_sq_cm' => 220.04,
                        'description' => 'Advanced dermal matrix with enhanced binding properties',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '3×3 cm', 'area_cm2' => 9, 'length_mm' => 30, 'width_mm' => 30],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '6.5×6.5 cm', 'area_cm2' => 42.25, 'length_mm' => 65, 'width_mm' => 65],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'MEDLIFE SOLUTIONS',
                    'contact_email' => 'orders@medlifesolutions.com',
                    'contact_phone' => '555-MEDLIFE',
                    'website' => 'https://medlifesolutions.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233913', 'name' => 'MedLife IVR Form'],
                    ['type' => 'OrderForm', 'template_id' => '1234279', 'name' => 'MedLife Order Form'],
                ],
                'products' => [
                    [
                        'name' => 'Amnio AMP',
                        'sku' => 'MED-AMNIOAMP',
                        'q_code' => 'Q4250',
                        'national_asp' => 2901.65,
                        'price_per_sq_cm' => 145.08,
                        'description' => 'Amniotic membrane product for advanced wound care',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2×3 cm', 'area_cm2' => 6, 'length_mm' => 20, 'width_mm' => 30],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '2×6 cm', 'area_cm2' => 12, 'length_mm' => 20, 'width_mm' => 60],
                            ['size_label' => '3×8 cm', 'area_cm2' => 24, 'length_mm' => 30, 'width_mm' => 80],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '16mm disk', 'area_cm2' => 2.01, 'length_mm' => 16, 'width_mm' => 16],
                            ['size_label' => '12×21 cm', 'area_cm2' => 252, 'length_mm' => 120, 'width_mm' => 210],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'CENTURION THERAPEUTICS',
                    'contact_email' => 'orders@centuriontherapeutics.com',
                    'contact_phone' => '555-CENTURI',
                    'website' => 'https://centuriontherapeutics.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233918', 'name' => 'Centurion IVR Form'],
                ],
                'products' => [
                    [
                        'name' => 'AmnioBand',
                        'sku' => 'CEN-AMNIOBAND',
                        'q_code' => 'Q4151',
                        'national_asp' => 137.19,
                        'price_per_sq_cm' => 8.57,
                        'description' => 'Amniotic membrane band for wound healing',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '10mm Disk', 'area_cm2' => 0.785, 'length_mm' => 10, 'width_mm' => 10],
                            ['size_label' => '14mm Disk', 'area_cm2' => 1.539, 'length_mm' => 14, 'width_mm' => 14],
                            ['size_label' => '16mm Disk', 'area_cm2' => 2.011, 'length_mm' => 16, 'width_mm' => 16],
                            ['size_label' => '18mm Disk', 'area_cm2' => 2.545, 'length_mm' => 18, 'width_mm' => 18],
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×3 cm', 'area_cm2' => 6, 'length_mm' => 20, 'width_mm' => 30],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '3×4 cm', 'area_cm2' => 12, 'length_mm' => 30, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '3×8 cm', 'area_cm2' => 24, 'length_mm' => 30, 'width_mm' => 80],
                            ['size_label' => '4×6 cm', 'area_cm2' => 24, 'length_mm' => 40, 'width_mm' => 60],
                            ['size_label' => '5×6 cm', 'area_cm2' => 30, 'length_mm' => 50, 'width_mm' => 60],
                            ['size_label' => '7×7 cm', 'area_cm2' => 49, 'length_mm' => 70, 'width_mm' => 70],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'BIOWOUND SOLUTIONS',
                    'contact_email' => 'orders@biowoundsolutions.com',
                    'contact_phone' => '555-BIOWOUND',
                    'website' => 'https://biowoundsolutions.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1254774', 'name' => 'BioWound IVR Form'],
                    ['type' => 'OrderForm', 'template_id' => '1299495', 'name' => 'BioWound Order Form'],
                ],
                'products' => [
                    [
                        'name' => 'Amnio-Maxx',
                        'sku' => 'BIO-AMNIOMAXX',
                        'q_code' => 'Q4239',
                        'national_asp' => 2038.50,
                        'price_per_sq_cm' => 127.41,
                        'description' => 'Maximum strength amniotic membrane for challenging wounds',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                        ]
                    ],
                    [
                        'name' => 'Derm-maxx',
                        'sku' => 'BIO-DERMMAXX',
                        'q_code' => 'Q4238',
                        'national_asp' => 1725.04,
                        'price_per_sq_cm' => 107.82,
                        'description' => 'Maximum strength dermal matrix for advanced wound care',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×7 cm', 'area_cm2' => 28, 'length_mm' => 40, 'width_mm' => 70],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                            ['size_label' => '5×10 cm', 'area_cm2' => 50, 'length_mm' => 50, 'width_mm' => 100],
                            ['size_label' => '8×16 cm', 'area_cm2' => 128, 'length_mm' => 80, 'width_mm' => 160],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'EXTREMITY CARE',
                    'contact_email' => 'orders@extremitycare.com',
                    'contact_phone' => '555-EXTREMITY',
                    'website' => 'https://extremitycare.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1234285', 'name' => 'Coll-e-Derm IVR'],
                    ['type' => 'IVR', 'template_id' => '1234284', 'name' => 'Restorigin IVR'],
                    ['type' => 'IVR', 'template_id' => '1234283', 'name' => 'Complete FT IVR'],
                ],
                'products' => [
                    [
                        'name' => 'Coll-e-derm',
                        'sku' => 'EXT-COLLEDERM',
                        'q_code' => 'Q4193',
                        'national_asp' => 1713.18,
                        'price_per_sq_cm' => 107.07,
                        'description' => 'Collagen-based dermal matrix for enhanced wound healing',
                        'category' => 'CollageMatrix',
                        'sizes' => [
                            ['size_label' => '1×1 cm', 'area_cm2' => 1, 'length_mm' => 10, 'width_mm' => 10],
                            ['size_label' => '1×2 cm', 'area_cm2' => 2, 'length_mm' => 10, 'width_mm' => 20],
                            ['size_label' => '1×4 cm', 'area_cm2' => 4, 'length_mm' => 10, 'width_mm' => 40],
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '3×7 cm', 'area_cm2' => 21, 'length_mm' => 30, 'width_mm' => 70],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×7 cm', 'area_cm2' => 28, 'length_mm' => 40, 'width_mm' => 70],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                            ['size_label' => '4×12 cm', 'area_cm2' => 48, 'length_mm' => 40, 'width_mm' => 120],
                            ['size_label' => '4×16 cm', 'area_cm2' => 64, 'length_mm' => 40, 'width_mm' => 160],
                            ['size_label' => '5×4 cm', 'area_cm2' => 20, 'length_mm' => 50, 'width_mm' => 40],
                            ['size_label' => '5×10 cm', 'area_cm2' => 50, 'length_mm' => 50, 'width_mm' => 100],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'ADVANCED SOLUTION',
                    'contact_email' => 'orders@advancedsolution.com',
                    'contact_phone' => '555-ADVANCED',
                    'website' => 'https://advancedsolution.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1199885', 'name' => 'Advanced Solution IVR'],
                    ['type' => 'OrderForm', 'template_id' => '1299488', 'name' => 'Advanced Solution Order Form'],
                ],
                'products' => [
                    [
                        'name' => 'Complete AA',
                        'sku' => 'ADV-COMPLETEAA',
                        'q_code' => 'Q4303',
                        'national_asp' => 3368.00,
                        'price_per_sq_cm' => 210.50,
                        'description' => 'Advanced amniotic allograft for complex wound care',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                            ['size_label' => '15×20 cm', 'area_cm2' => 300, 'length_mm' => 150, 'width_mm' => 200],
                        ]
                    ],
                    [
                        'name' => 'Complete FT',
                        'sku' => 'ADV-COMPLETEFT',
                        'q_code' => 'Q4271',
                        'national_asp' => 1210.48,
                        'price_per_sq_cm' => 75.66,
                        'description' => 'Full-thickness skin substitute designed for deep wounds',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×3 cm', 'area_cm2' => 6, 'length_mm' => 20, 'width_mm' => 30],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '4×6 cm', 'area_cm2' => 24, 'length_mm' => 40, 'width_mm' => 60],
                            ['size_label' => '4×8 cm', 'area_cm2' => 32, 'length_mm' => 40, 'width_mm' => 80],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'CELULARITY',
                    'contact_email' => 'orders@celularity.com',
                    'contact_phone' => '555-CELULAR',
                    'website' => 'https://celularity.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1330769', 'name' => 'Celularity IVR Form'],
                    ['type' => 'OrderForm', 'template_id' => '1330771', 'name' => 'Celularity Order Form'],
                ],
                'products' => [
                    [
                        'name' => 'Biovance',
                        'sku' => 'CEL-BIOVANCE',
                        'q_code' => 'Q4154',
                        'national_asp' => 142.86,
                        'price_per_sq_cm' => 8.93,
                        'description' => 'Decellularized, dehydrated human amniotic membrane',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '1×2 cm', 'area_cm2' => 2, 'length_mm' => 10, 'width_mm' => 20],
                            ['size_label' => '2×2 cm', 'area_cm2' => 4, 'length_mm' => 20, 'width_mm' => 20],
                            ['size_label' => '2×3 cm', 'area_cm2' => 6, 'length_mm' => 20, 'width_mm' => 30],
                            ['size_label' => '2×4 cm', 'area_cm2' => 8, 'length_mm' => 20, 'width_mm' => 40],
                            ['size_label' => '3×3.5 cm', 'area_cm2' => 10.5, 'length_mm' => 30, 'width_mm' => 35],
                            ['size_label' => '4×4 cm', 'area_cm2' => 16, 'length_mm' => 40, 'width_mm' => 40],
                            ['size_label' => '5×5 cm', 'area_cm2' => 25, 'length_mm' => 50, 'width_mm' => 50],
                            ['size_label' => '6×6 cm', 'area_cm2' => 36, 'length_mm' => 60, 'width_mm' => 60],
                        ]
                    ],
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'IMBED',
                    'contact_email' => 'orders@imbed.com',
                    'contact_phone' => '555-IMBED',
                    'website' => 'https://imbed.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1234272', 'name' => 'Imbed IVR Form'],
                    ['type' => 'OrderForm', 'template_id' => '1234276', 'name' => 'Imbed Order Form'],
                ],
                'products' => [
                    // Add Imbed products when available
                ],
            ],
            [
                'manufacturer' => [
                    'name' => 'SKYE BIOLOGICS',
                    'contact_email' => 'orders@skyebiologics.com',
                    'contact_phone' => '555-SKYE',
                    'website' => 'https://skyebiologics.com',
                ],
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1340334', 'name' => 'Skye Biologics IVR Form'],
                ],
                'products' => [
                    // Add Skye products when available
                ],
            ],
        ];

        foreach ($masterData as $data) {
            $this->createManufacturerWithAllData($data);
        }
    }

    private function createManufacturerWithAllData(array $data): void
    {
        $manufacturerData = $data['manufacturer'];
        $this->command->info("Creating manufacturer: {$manufacturerData['name']}");

        // Create manufacturer
        $manufacturer = Manufacturer::updateOrCreate(
            ['name' => $manufacturerData['name']],
            [
                'contact_email' => $manufacturerData['contact_email'],
                'contact_phone' => $manufacturerData['contact_phone'],
                'website' => $manufacturerData['website'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create DocuSeal templates
        foreach ($data['templates'] as $templateData) {
            DocusealTemplate::updateOrCreate(
                [
                    'manufacturer_id' => $manufacturer->id,
                    'docuseal_template_id' => $templateData['template_id'],
                ],
                [
                    'template_name' => $templateData['name'],
                    'document_type' => $templateData['type'],
                    'is_active' => true,
                    'is_default' => true,
                    'field_mappings' => [],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Create products and their sizes
        foreach ($data['products'] as $productData) {
            $product = Product::updateOrCreate(
                ['sku' => $productData['sku']],
                [
                    'name' => $productData['name'],
                    'manufacturer_id' => $manufacturer->id,
                    'category' => $productData['category'],
                    'q_code' => $productData['q_code'],
                    'national_asp' => $productData['national_asp'],
                    'price_per_sq_cm' => $productData['price_per_sq_cm'],
                    'description' => $productData['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Create product sizes
            foreach ($productData['sizes'] as $sizeData) {
                ProductSize::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'size_label' => $sizeData['size_label'],
                    ],
                    [
                        'size_type' => 'rectangular',
                        'area_cm2' => $sizeData['area_cm2'],
                        'length_mm' => $sizeData['length_mm'],
                        'width_mm' => $sizeData['width_mm'],
                        'display_label' => $sizeData['size_label'],
                        'sort_order' => $sizeData['area_cm2'] * 10,
                        'is_active' => true,
                        'is_available' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $templateCount = count($data['templates']);
        $productCount = count($data['products']);
        $this->command->line("  ✅ Created {$templateCount} templates and {$productCount} products for {$manufacturer->name}");
    }

    private function displaySummary(): void
    {
        $this->command->info("\n📊 Summary:");
        $this->command->info("   Categories: " . Category::count());
        $this->command->info("   Manufacturers: " . Manufacturer::count());
        $this->command->info("   Products: " . Product::count());
        $this->command->info("   Product Sizes: " . ProductSize::count());
        $this->command->info("   DocuSeal Templates: " . DocusealTemplate::count());
    }
} 