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
                'name' => 'ACZ & Associates',
                'notes' => 'Distributor of Ensano ACA, Revoshield+ Amnio, and Dermabind FM products',
            ],
            [
                'name' => 'Advanced Solution',
                'notes' => 'Distributor of Complete FT, Membrane Wrap, and Complete AA products',
            ],
            [
                'name' => 'BioWound Solutions',
                'notes' => 'Distributor of Membrane Wrap Hydro, NeoStim product line (TL, DL, SL), Amnio-Maxx, and Derm-maxx products',
            ],
            [
                'name' => 'Extremity Care LLC',
                'notes' => 'Distributor of Restorigin and Coll-e-derm products',
            ],
            [
                'name' => 'MedLife Solutions',
                'notes' => 'Distributor of Amnio AMP products',
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
                'name' => 'EXTREMITY CARE',
                'notes' => 'Manufacturer of Complete FT, Barrera DL, carePatch, Restorigin, Procenta, and Coll-e-Derm',
            ],
            [
                'name' => 'BLS SALES AND MARKETING LLC',
                'notes' => 'Manufacturer of Membrane Wrap and Membrane Wrap - Hydro products',
            ],
            [
                'name' => 'DYNAMIC MEDICAL SERVICES, LLC',
                'notes' => 'Manufacturer of NeoStim product line (TL, DL, SL)',
            ],
            [
                'name' => 'HUMAN REGENERATIVE TECHNOLOGIES, LLC',
                'notes' => 'Manufacturer of WoundFix biologic products',
            ],
            [
                'name' => 'REVOGEN BIOLOGICS / MINDSIGHT MEDICAL',
                'notes' => 'Manufacturer of RevoShield + products',
            ],
            [
                'name' => 'STRATUS BIOSYSTEMS LLC',
                'notes' => 'Manufacturer of AmnioAMP-MP biologic products',
            ],
            [
                'name' => 'SAMARITAN BIOLOGICS, LLC',
                'notes' => 'Manufacturer of Complete AA and Complete SL products',
            ],
            [
                'name' => 'PRECISE BIOSCIENCE',
                'notes' => 'Manufacturer of Xcellerate and Xcell Amnio Matrix products',
            ],
            [
                'name' => 'MIMEDX GROUP, INC.',
                'website' => 'https://www.mimedx.com',
                'notes' => 'Leading manufacturer of EpiFix and EpiCord amniotic membrane products',
            ],
            [
                'name' => 'ROYAL BIOLOGICS',
                'notes' => 'Manufacturer of Amnio-Maxx and Derm-Maxx products',
            ],
            [
                'name' => 'STABILITY BIOLOGICS, LLC',
                'notes' => 'Manufacturer of AmnioCore product line (Pro, Pro+, Quad-Core, Tri-Core, AmnioCore)',
            ],
            [
                'name' => 'SURGENEX, LLC',
                'notes' => 'Manufacturer of SurGraft TL biologic products',
            ],
            [
                'name' => 'HEALTHTECH WOUND CARE, INC.',
                'notes' => 'Manufacturer of DermaBind FM products',
            ],
            [
                'name' => 'MUSCULOSKELETAL TRANSPLANT FOUNDATION',
                'website' => 'https://www.mtf.org',
                'notes' => 'Non-profit manufacturer of AmnioBand products',
            ],
            // Additional manufacturers for comprehensive coverage
            [
                'name' => 'ORGANOGENESIS',
                'website' => 'https://www.organogenesis.com',
                'notes' => 'Leading regenerative medicine company',
            ],
            [
                'name' => 'INTEGRA LIFESCIENCES',
                'website' => 'https://www.integralife.com',
                'notes' => 'Global medical device company specializing in wound care',
            ],
            [
                'name' => 'SMITH & NEPHEW',
                'website' => 'https://www.smith-nephew.com',
                'notes' => 'Global medical technology company with advanced wound care division',
            ],
            [
                'name' => 'ACELITY / KCI',
                'notes' => 'Manufacturer of negative pressure wound therapy and advanced wound care products',
            ],
            [
                'name' => 'MOLNLYCKE HEALTH CARE',
                'website' => 'https://www.molnlycke.com',
                'notes' => 'Global manufacturer of wound care and surgical products',
            ],
            [
                'name' => 'CONVATEC',
                'website' => 'https://www.convatec.com',
                'notes' => 'Global medical products and technologies company',
            ],
            [
                'name' => 'COLOPLAST',
                'website' => 'https://www.coloplast.com',
                'notes' => 'Danish multinational medical device company',
            ],
            [
                'name' => 'HOLLISTER',
                'website' => 'https://www.hollister.com',
                'notes' => 'Healthcare products company specializing in advanced wound care',
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
