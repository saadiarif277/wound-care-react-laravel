<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\ProductSize;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\DB;

class ManufacturerDocuSealTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding manufacturers with DocuSeal templates, products, and sizes...');

        DB::transaction(function () {
            $this->seedManufacturersWithTemplates();
        });

        $this->command->info('✅ Manufacturer DocuSeal template seeding completed!');
    }

    private function seedManufacturersWithTemplates(): void
    {
        $manufacturersData = [
            [
                'name' => 'ACZ & Associates',
                'folder_id' => null, // No folder provided
                'contact_email' => 'orders@aczassociates.com',
                'contact_phone' => null,
                'website' => 'https://aczassociates.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '852440'],
                    ['type' => 'OrderForm', 'template_id' => '852554'],
                ],
                'products' => [
                    // ACZ products - add when more specific info available
                ],
            ],
            [
                'name' => 'MEDLIFE SOLUTIONS',
                'folder_id' => '111417',
                'contact_email' => 'orders@medlifesolutions.com',
                'contact_phone' => null,
                'website' => 'https://medlifesolutions.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233913'],
                    ['type' => 'OrderForm', 'template_id' => '1234279'],
                ],
                'products' => [
                    [
                        'name' => 'Amnio AMP',
                        'sku' => 'MED-AMNIOAMP',
                        'q_code' => 'Q4250',
                        'national_asp' => 2901.65,
                        'price_per_sq_cm' => 145.08, // Estimated based on 20 sq cm standard
                        'description' => 'Amniotic membrane product for advanced wound care',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                            ['size_label' => '5x5cm', 'area_cm2' => 25, 'size_type' => 'rectangular'],
                        ]
                    ],
                ],
            ],
            [
                'name' => 'CENTURION THERAPEUTICS',
                'folder_id' => '111419',
                'contact_email' => 'orders@centuriontherapeutics.com',
                'contact_phone' => null,
                'website' => 'https://centuriontherapeutics.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233918'],
                ],
                'products' => [
                    // Centurion products - add when more specific info available
                ],
            ],
            [
                'name' => 'BIOWOUND SOLUTIONS',
                'folder_id' => '113461',
                'contact_email' => 'orders@biowoundsolutions.com',
                'contact_phone' => null,
                'website' => 'https://biowoundsolutions.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1254774'],
                    ['type' => 'OrderForm', 'template_id' => '1299495'],
                ],
                'products' => [
                    [
                        'name' => 'Neostim DL',
                        'sku' => 'BIO-NEOSTIMDL',
                        'q_code' => 'Q4267',
                        'national_asp' => 290.01,
                        'price_per_sq_cm' => 18.13,
                        'description' => 'Dual-layer wound matrix for enhanced healing outcomes',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Neostim SL',
                        'sku' => 'BIO-NEOSTIMSL',
                        'q_code' => 'Q4266',
                        'national_asp' => 560.57,
                        'price_per_sq_cm' => 35.04,
                        'description' => 'Single-layer wound matrix for routine wound care',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Neostim TL',
                        'sku' => 'BIO-NEOSTIMTL',
                        'q_code' => 'Q4265',
                        'national_asp' => 1704.02,
                        'price_per_sq_cm' => 106.50,
                        'description' => 'Triple-layer wound matrix for complex wound management',
                        'category' => 'SkinSubstitute',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Membrane Wrap Hydro',
                        'sku' => 'BIO-MEMBRANEWRAPHYDRO',
                        'q_code' => 'Q4290',
                        'national_asp' => 1864.70,
                        'price_per_sq_cm' => 116.54,
                        'description' => 'Hydrated membrane wrap with enhanced healing properties',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Amnio-Maxx',
                        'sku' => 'BIO-AMNIOMAXX',
                        'q_code' => 'Q4239',
                        'national_asp' => 2038.50,
                        'price_per_sq_cm' => 127.41,
                        'description' => 'Maximum strength amniotic membrane for challenging wounds',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
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
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                ],
            ],
            [
                'name' => 'EXTREMITY CARE',
                'folder_id' => '111449',
                'contact_email' => 'orders@extremitycare.com',
                'contact_phone' => null,
                'website' => 'https://extremitycare.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1234285', 'name' => 'Coll-e-Derm IVR'],
                    ['type' => 'IVR', 'template_id' => '1234284', 'name' => 'Restorigin IVR'],
                    ['type' => 'IVR', 'template_id' => '1234283', 'name' => 'Complete FT IVR'],
                ],
                'products' => [
                    [
                        'name' => 'Restorigin',
                        'sku' => 'EXT-RESTORIGIN',
                        'q_code' => 'Q4191',
                        'national_asp' => 730.43,
                        'price_per_sq_cm' => 45.65,
                        'description' => 'Regenerative biologic matrix for tissue restoration',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Coll-e-derm',
                        'sku' => 'EXT-COLLEDERM',
                        'q_code' => 'Q4193',
                        'national_asp' => 1713.18,
                        'price_per_sq_cm' => 107.07,
                        'description' => 'Collagen-based dermal matrix for enhanced wound healing',
                        'category' => 'CollageMatrix',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                ],
            ],
            [
                'name' => 'ADVANCED SOLUTION',
                'folder_id' => '108291',
                'contact_email' => 'orders@advancedsolution.com',
                'contact_phone' => null,
                'website' => 'https://advancedsolution.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1199885'],
                    ['type' => 'OrderForm', 'template_id' => '1299488'],
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
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
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
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                    [
                        'name' => 'Membrane Wrap',
                        'sku' => 'ADV-MEMBRANEWRAP',
                        'q_code' => 'Q4205',
                        'national_asp' => 1190.43,
                        'price_per_sq_cm' => 74.40,
                        'description' => 'Versatile membrane wrap for various wound types',
                        'category' => 'Biologic',
                        'sizes' => [
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                ],
            ],
            [
                'name' => 'CELULARITY',
                'folder_id' => '122416',
                'contact_email' => 'orders@celularity.com',
                'contact_phone' => null,
                'website' => 'https://celularity.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1330769'],
                    ['type' => 'OrderForm', 'template_id' => '1330771'],
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
                            ['size_label' => '2x2cm', 'area_cm2' => 4, 'size_type' => 'rectangular'],
                            ['size_label' => '4x4cm', 'area_cm2' => 16, 'size_type' => 'rectangular'],
                        ]
                    ],
                ],
            ],
            [
                'name' => 'IMBED',
                'folder_id' => '111448',
                'contact_email' => 'orders@imbed.com',
                'contact_phone' => null,
                'website' => 'https://imbed.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1234272'],
                    ['type' => 'OrderForm', 'template_id' => '1234276'],
                ],
                'products' => [
                    // IMBED products - add when more specific info available
                ],
            ],
            [
                'name' => 'SKYE BIOLOGICS',
                'folder_id' => '123397',
                'contact_email' => 'orders@skyebiologics.com',
                'contact_phone' => null,
                'website' => 'https://skyebiologics.com',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1340334'],
                ],
                'products' => [
                    // SKYE products - add when more specific info available
                ],
            ],
            [
                'name' => 'BioWerX',
                'folder_id' => null,
                'contact_email' => 'orders@biowerx.com',
                'contact_phone' => null,
                'website' => 'https://biowerx.com',
                'templates' => [],
                'products' => [],
            ],
            [
                'name' => 'Advanced Health',
                'folder_id' => null,
                'contact_email' => 'orders@advancedhealth.com',
                'contact_phone' => null,
                'website' => 'https://advancedhealth.com',
                'templates' => [],
                'products' => [],
            ],
            [
                'name' => 'Total Ancillary',
                'folder_id' => null,
                'contact_email' => 'orders@totalancillary.com',
                'contact_phone' => null,
                'website' => 'https://totalancillary.com',
                'templates' => [],
                'products' => [],
            ]
        ];

        foreach ($manufacturersData as $manufacturerData) {
            $this->createManufacturerWithTemplatesAndProducts($manufacturerData);
        }
    }

    private function createManufacturerWithTemplatesAndProducts(array $data): void
    {
        $this->command->info("Creating manufacturer: {$data['name']}");

        // Create or update manufacturer
        $manufacturer = Manufacturer::updateOrCreate(
            ['name' => $data['name']],
            [
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'website' => $data['website'] ?? null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create DocuSeal templates
        foreach ($data['templates'] as $templateData) {
            $templateName = $templateData['name'] ?? "{$manufacturer->name} - {$templateData['type']} Form";
            
            DocusealTemplate::updateOrCreate(
                [
                    'manufacturer_id' => $manufacturer->id,
                    'docuseal_template_id' => $templateData['template_id'],
                ],
                [
                    'template_name' => $templateName,
                    'document_type' => $templateData['type'],
                    'is_active' => true,
                    'is_default' => true,
                    'field_mappings' => [], // Will be populated via sync commands
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
                    'category' => $productData['category'] ?? 'wound_care',
                    'q_code' => $productData['q_code'] ?? null,
                    'national_asp' => $productData['national_asp'] ?? null,
                    'price_per_sq_cm' => $productData['price_per_sq_cm'] ?? null,
                    'description' => $productData['description'] ?? null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Create product sizes
            foreach ($productData['sizes'] ?? [] as $sizeData) {
                ProductSize::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'size_label' => $sizeData['size_label'],
                    ],
                    [
                        'size_type' => $sizeData['size_type'] ?? 'rectangular',
                        'area_cm2' => $sizeData['area_cm2'] ?? null,
                        'length_mm' => isset($sizeData['area_cm2']) ? sqrt($sizeData['area_cm2']) * 10 : null,
                        'width_mm' => isset($sizeData['area_cm2']) ? sqrt($sizeData['area_cm2']) * 10 : null,
                        'display_label' => $sizeData['display_label'] ?? $sizeData['size_label'],
                        'sort_order' => $sizeData['sort_order'] ?? ($sizeData['area_cm2'] ?? 0) * 10,
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
} 