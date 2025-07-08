<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\DB;

class ManufacturerDocuSealTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding manufacturers with DocuSeal templates...');

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
                'folder_id' => null, // Not provided in the data
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '852440'],
                    ['type' => 'Order', 'template_id' => '852554'],
                ],
            ],
            [
                'name' => 'MEDLIFE SOLUTIONS',
                'folder_id' => '111417',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233913'],
                    ['type' => 'Order', 'template_id' => '1234279'],
                ],
            ],
            [
                'name' => 'CENTURION THERAPEUTICS',
                'folder_id' => '111419',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1233918'],
                ],
            ],
            [
                'name' => 'BIOWOUND SOLUTIONS',
                'folder_id' => '113461',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1254774'],
                    ['type' => 'Order', 'template_id' => '1299495'],
                ],
            ],
            [
                'name' => 'EXTREMITY CARE',
                'folder_id' => '111449',
                'templates' => [
                    ['type' => 'Coll-e-Derm IVR', 'template_id' => '1234285'],
                    ['type' => 'Restorigin IVR', 'template_id' => '1234284'],
                    ['type' => 'Complete FT IVR', 'template_id' => '1234283'],
                ],
            ],
            [
                'name' => 'ADVANCED SOLUTION',
                'folder_id' => '108291',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1199885'],
                    ['type' => 'Order', 'template_id' => '1299488'],
                ],
            ],
            [
                'name' => 'CELULARITY',
                'folder_id' => '122416',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1330769'],
                    ['type' => 'Order', 'template_id' => '1330771'],
                ],
            ],
            [
                'name' => 'IMBED',
                'folder_id' => '111448',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1234272'],
                    ['type' => 'Order', 'template_id' => '1234276'],
                ],
            ],
            [
                'name' => 'SKYE BIOLOGICS',
                'folder_id' => '123397',
                'templates' => [
                    ['type' => 'IVR', 'template_id' => '1340334'],
                ],
            ],
            [
                'name' => 'BioWerX',
                'folder_id' => null,
                'templates' => [],
            ],
            [
                'name' => 'Advanced Health',
                'folder_id' => null,
                'templates' => [],
            ],
            [
                'name' => 'Total Ancillary',
                'folder_id' => null,
                'templates' => [],
            ]
        ];

        foreach ($manufacturersData as $manufacturerData) {
            $this->createManufacturerWithTemplates($manufacturerData);
        }
    }

    private function createManufacturerWithTemplates(array $data): void
    {
        $this->command->info("Creating manufacturer: {$data['name']}");

        // Create or update manufacturer
        $manufacturer = Manufacturer::updateOrCreate(
            ['name' => $data['name']],
            [
                'folder_id' => $data['folder_id'],
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
                    'document_type' => $templateData['type'],
                    'template_name' => "{$manufacturer->name} - {$templateData['type']} Form",
                    'is_active' => true,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $templateCount = count($data['templates']);
        $this->command->line("  ✅ Created {$templateCount} templates for {$manufacturer->name}");
    }
} 