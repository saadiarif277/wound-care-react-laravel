<?php

namespace Database\Seeders;

use App\Models\Order\Category;
use App\Models\Order\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesAndManufacturersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏥 Seeding Wound Care Categories and Manufacturers...');

        // Create Categories based on Wound Care Products Catalog
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
                'name' => 'Dressing',
                'description' => 'Advanced wound dressings and topical treatments',
                'sort_order' => 3,
            ],
            [
                'name' => 'CollageMatrix',
                'description' => 'Collagen-based matrices and scaffolds for tissue regeneration',
                'sort_order' => 4,
            ],
            [
                'name' => 'Antimicrobial',
                'description' => 'Antimicrobial dressings and treatments for infected wounds',
                'sort_order' => 5,
            ],
            [
                'name' => 'Foam',
                'description' => 'Foam dressings for exudate management',
                'sort_order' => 6,
            ],
            [
                'name' => 'Hydrogel',
                'description' => 'Hydrogel dressings for moisture management',
                'sort_order' => 7,
            ],
            [
                'name' => 'Alginate',
                'description' => 'Alginate dressings for highly exuding wounds',
                'sort_order' => 8,
            ],
            [
                'name' => 'Compression',
                'description' => 'Compression systems for venous wound management',
                'sort_order' => 9,
            ],
            [
                'name' => 'Offloading',
                'description' => 'Offloading devices for diabetic foot ulcers',
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name' => $categoryData['name']],
                [
                    'slug' => Str::slug($categoryData['name']),
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Categories created successfully!');

        // Create Manufacturers based on Wound Care Products Catalog and CMS Data
        $manufacturers = [
            // From CMS Data - Current Distributors
            [
                'name' => 'ACZ & ASSOCIATES',
                'notes' => 'Distributor of Ensano ACA, Revoshield+ Amnio, and Dermabind FM products',
            ],
            [
                'name' => 'ADVANCED SOLUTION',
                'notes' => 'Distributor of Complete FT, Membrane Wrap, and Complete AA products',
            ],
            [
                'name' => 'BIOWOUND SOLUTIONS',
                'notes' => 'Distributor of Membrane Wrap Hydro, NeoStim product line (TL, DL, SL), Amnio-Maxx, and Derm-maxx products',
            ],
            [
                'name' => 'Extremity Care LLC',
                'notes' => 'Distributor of Restorigin and Coll-e-derm products',
            ],
            [
                'name' => 'MEDLIFE SOLUTIONS',
                'notes' => 'Distributor of Amnio AMP products',
            ],
            [
                'name' => 'IMBED',
                'notes' => 'Distributor of Microlyte products',
            ],
            // Legacy Manufacturers for backward compatibility
            [
                'name' => 'CELULARITY',
                'website' => 'https://www.celularity.com',
                'notes' => 'Leading manufacturer of cellular therapy products including Biovance',
            ],
            [
                'name' => 'LEGACY MEDICAL CONSULTANTS',
                'notes' => 'Manufacturer of Impax, Zenith, Orion, and Complete ACA products',
            ],
            [
                'name' => 'ENCOLL CORP',
                'website' => 'https://www.encoll.com',
                'notes' => 'Manufacturer of Helicoll collagen-based products',
            ],
            [
                'name' => 'MIMEDX GROUP, INC.',
                'website' => 'https://www.mimedx.com',
                'notes' => 'Leading manufacturer of EpiFix and EpiCord amniotic membrane products',
            ],
            [
                'name' => 'CENTURION THERAPEUTICS',
                'notes' => 'Distributor of Amnio AMP products',
            ],
            [
                'name' => 'STABILITY BIOLOGICS, LLC',
                'notes' => 'Manufacturer of AmnioCore product line (Pro, Pro+, Quad-Core, Tri-Core, AmnioCore)',
            ],
        ];

        foreach ($manufacturers as $manufacturerData) {
            Manufacturer::firstOrCreate(
                ['name' => $manufacturerData['name']],
                [
                    'slug' => Str::slug($manufacturerData['name']),
                    'website' => $manufacturerData['website'] ?? null,
                    'notes' => $manufacturerData['notes'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Manufacturers created successfully!');
        $this->command->info('🎉 Wound Care Categories and Manufacturers seeding completed!');
        $this->command->line('');
        $this->command->info('📊 Summary:');
        $this->command->info('   Categories: ' . Category::count());
        $this->command->info('   Manufacturers: ' . Manufacturer::count());
    }
}
