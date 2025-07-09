<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\Order\Category;
use App\Models\ProductSize;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\ReferenceData;
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
            
            // 2. Create reference data
            $this->createReferenceData();

            // 3. Create all manufacturers, products, sizes, and templates together
            $this->createMasterData();
        });

        $this->command->info('✅ Master data seeding completed!');
        $this->displaySummary();
    }

    private function createCategories(): void
    {
        $categories = [
            [
                'name' => 'CTP_SkinSubstitute',
                'description' => 'Cellular and/or tissue-based products (CTPs) including all Q-code skin substitutes for wound healing: amniotic, dermal, synthetic, and hybrid matrices.',
                'sort_order' => 1,
            ],
            [
                'name' => 'AdvancedDressing',
                'description' => 'Advanced wound dressings, including antimicrobial and bioactive dressings not classified as skin substitutes.',
                'sort_order' => 2,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData + ['is_active' => true]
            );
        }
    }

    private function createReferenceData(): void
    {
        $this->command->info('🧬 Seeding Reference Data...');

        $referenceData = [
            'carriers' => [
                ['key' => 'ups', 'label' => 'UPS', 'metadata' => ['trackingUrl' => 'https://www.ups.com/track?tracknum=']],
                ['key' => 'fedex', 'label' => 'FedEx', 'metadata' => ['trackingUrl' => 'https://www.fedex.com/fedextrack/?tracknumbers=']],
                ['key' => 'usps', 'label' => 'USPS', 'metadata' => ['trackingUrl' => 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=']],
                ['key' => 'dhl', 'label' => 'DHL', 'metadata' => ['trackingUrl' => 'https://www.dhl.com/en/express/tracking.html?AWB=']],
                ['key' => 'ontrac', 'label' => 'OnTrac', 'metadata' => ['trackingUrl' => 'https://www.ontrac.com/tracking?number=']],
                ['key' => 'other', 'label' => 'Other', 'metadata' => ['trackingUrl' => null]],
            ],
            'facility_types' => [
                ['key' => 'private_practice', 'label' => 'Private Practice'],
                ['key' => 'clinic', 'label' => 'Clinic'],
                ['key' => 'hospital', 'label' => 'Hospital'],
                ['key' => 'surgery_center', 'label' => 'Surgery Center'],
                ['key' => 'wound_care_center', 'label' => 'Wound Care Center'],
                ['key' => 'emergency_department', 'label' => 'Emergency Department'],
                ['key' => 'urgent_care', 'label' => 'Urgent Care'],
                ['key' => 'specialty_clinic', 'label' => 'Specialty Clinic'],
                ['key' => 'other', 'label' => 'Other'],
            ],
            'specialties' => [
                ['key' => 'wound_care', 'label' => 'Wound Care'],
                ['key' => 'family_medicine', 'label' => 'Family Medicine'],
                ['key' => 'internal_medicine', 'label' => 'Internal Medicine'],
                ['key' => 'emergency_medicine', 'label' => 'Emergency Medicine'],
                ['key' => 'surgery', 'label' => 'Surgery'],
                ['key' => 'dermatology', 'label' => 'Dermatology'],
                ['key' => 'podiatry', 'label' => 'Podiatry'],
                ['key' => 'nursing', 'label' => 'Nursing'],
                ['key' => 'other', 'label' => 'Other'],
            ],
            'place_of_service_codes' => [
                ['key' => '11', 'label' => '11 - Office'],
                ['key' => '12', 'label' => '12 - Home'],
                ['key' => '31', 'label' => '31 - Skilled Nursing Facility'],
                ['key' => '32', 'label' => '32 - Nursing Facility'],
            ],
            'user_roles' => [
                ['key' => 'provider', 'label' => 'Provider'],
                ['key' => 'office_manager', 'label' => 'Office Manager'],
                ['key' => 'msc_rep', 'label' => 'MSC Rep'],
                ['key' => 'msc_subrep', 'label' => 'MSC Sub-Rep'],
                ['key' => 'msc_admin', 'label' => 'MSC Admin'],
            ],
            'provider_titles' => [
                ['key' => 'md', 'label' => 'MD'],
                ['key' => 'do', 'label' => 'DO'],
                ['key' => 'np', 'label' => 'NP'],
                ['key' => 'pa', 'label' => 'PA'],
                ['key' => 'dpm', 'label' => 'DPM'],
                ['key' => 'rn', 'label' => 'RN'],
                ['key' => 'other', 'label' => 'Other'],
            ],
            'wound_locations' => [
                ['key' => 'abdomen', 'label' => 'Abdomen'],
                ['key' => 'back', 'label' => 'Back'],
                ['key' => 'buttock', 'label' => 'Buttock'],
                ['key' => 'chest', 'label' => 'Chest'],
                ['key' => 'foot', 'label' => 'Foot'],
                ['key' => 'hand', 'label' => 'Hand'],
                ['key' => 'head', 'label' => 'Head'],
                ['key' => 'lower_leg', 'label' => 'Lower Leg'],
                ['key' => 'neck', 'label' => 'Neck'],
                ['key' => 'thigh', 'label' => 'Thigh'],
                ['key' => 'upper_arm', 'label' => 'Upper Arm'],
                ['key' => 'other', 'label' => 'Other'],
            ],
            'organization_types' => [
                ['key' => 'solo_practice', 'label' => 'Solo Practice'],
                ['key' => 'group_practice', 'label' => 'Group Practice'],
                ['key' => 'hospital_system', 'label' => 'Hospital System'],
                ['key' => 'wound_care_center', 'label' => 'Wound Care Center'],
                ['key' => 'home_health_agency', 'label' => 'Home Health Agency'],
                ['key' => 'dme_company', 'label' => 'DME Company'],
                ['key' => 'other', 'label' => 'Other'],
            ],
            'wound_types' => [
                ['key' => 'pressure_ulcer', 'label' => 'Pressure Ulcer'],
                ['key' => 'diabetic_foot_ulcer', 'label' => 'Diabetic Foot Ulcer'],
                ['key' => 'venous_ulcer', 'label' => 'Venous Ulcer'],
                ['key' => 'surgical_wound', 'label' => 'Surgical Wound'],
                ['key' => 'chronic_ulcer', 'label' => 'Chronic Ulcer'],
                ['key' => 'arterial_ulcer', 'label' => 'Arterial Ulcer'],
                ['key' => 'traumatic_wound', 'label' => 'Traumatic Wound'],
                ['key' => 'other', 'label' => 'Other'],
            ],
        ];

        foreach ($referenceData as $type => $data) {
            foreach ($data as $index => $item) {
                ReferenceData::updateOrCreate(
                    [
                        'type' => $type,
                        'key' => $item['key'],
                    ],
                    [
                        'label' => $item['label'],
                        'metadata' => $item['metadata'] ?? null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                        'category' => 'CTP_SkinSubstitute',
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
                    'manufacturer' => $manufacturer->name, // Add manufacturer name
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
        $this->command->info("   Reference Data Entries: " . ReferenceData::count());
    }
} 