<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Docuseal\DocusealFolder;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Str;

class DocusealFolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create ACZ Distribution manufacturer
        $aczManufacturer = Manufacturer::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')->firstOrCreate(
            ['name' => 'ACZ Distribution'],
            [
                'slug' => 'acz-distribution',
                'address' => '1234 Medical Drive, Dallas, TX 75001',
                'website' => 'https://aczdistribution.com',
                'notes' => 'Primary IVR contact: ivr@aczdistribution.com',
            ]
        );

        // Create DocuSeal folder for ACZ Distribution
        DocusealFolder::create([
            'id' => Str::uuid(),
            'folder_name' => 'ACZ Distribution IVR Forms',
            'docuseal_folder_id' => '75423',
            'manufacturer_id' => $aczManufacturer->id,
            'is_active' => true,
        ]);

        // Create folders for other common manufacturers
        $manufacturers = [
            [
                'name' => 'Organogenesis',
                'folder_name' => 'Organogenesis IVR Forms',
                'docuseal_folder_id' => 'folder_org_001',
            ],
            [
                'name' => 'MiMedx',
                'folder_name' => 'MiMedx IVR Forms',
                'docuseal_folder_id' => 'folder_mimedx_001',
            ],
            [
                'name' => 'Integra LifeSciences',
                'folder_name' => 'Integra IVR Forms',
                'docuseal_folder_id' => 'folder_integra_001',
            ],
        ];

        foreach ($manufacturers as $mfgData) {
            $manufacturer = Manufacturer::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')->firstOrCreate(
                ['name' => $mfgData['name']],
                [
                    'slug' => Str::slug($mfgData['name']),
                ]
            );

            DocusealFolder::create([
                'id' => Str::uuid(),
                'folder_name' => $mfgData['folder_name'],
                'docuseal_folder_id' => $mfgData['docuseal_folder_id'],
                'manufacturer_id' => $manufacturer->id,
            ]);
        }

        $this->command->info('DocuSeal folders seeded successfully!');
    }
}