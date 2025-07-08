<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        echo "🔧 Fixing unmapped Docuseal templates...\n";

        // 1. Fix ACZ & ASSOCIATES (ID: 1)
        $this->fixAczAssociates();

        // 2. Fix IMBED templates (find or create manufacturer)
        $this->fixImbedTemplates();

        // 3. Verify Advanced Solution (ID: 2) - should already be done
        $this->verifyAdvancedSolution();

        echo "✅ All unmapped templates have been properly mapped!\n";
    }

    private function fixAczAssociates(): void
    {
        // Ensure manufacturer ID 1 exists and is 'ACZ & ASSOCIATES'
        $manufacturer = DB::table('manufacturers')->where('id', 1)->first();

        if (!$manufacturer) {
            DB::table('manufacturers')->insert([
                'id' => 1,
                'name' => 'ACZ & ASSOCIATES',
                'slug' => 'acz-associates',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "   ✅ Created manufacturer: ACZ & ASSOCIATES (ID: 1)\n";
        } else {
            DB::table('manufacturers')->where('id', 1)->update([
                'name' => 'ACZ & ASSOCIATES',
                'slug' => 'acz-associates',
                'updated_at' => now(),
            ]);
            echo "   ✅ Updated manufacturer: ACZ & ASSOCIATES (ID: 1)\n";
        }

        // Map Biowound IVR template
        DB::table('docuseal_templates')->updateOrInsert(
            [
                'template_name' => 'Biowound IVR',
                'manufacturer_id' => 1,
            ],
            [
                'id' => Str::uuid(),
                'docuseal_template_id' => 'acz_biowound_ivr_temp',
                'document_type' => 'IVR',
                'is_default' => true,
                'is_active' => true,
                'field_mappings' => json_encode([
                    'patient_name' => 'Patient Name',
                    'patient_dob' => 'Date of Birth',
                    'provider_name' => 'Provider Name',
                    'facility_name' => 'Facility Name',
                    'wound_type' => 'Wound Type',
                    'wound_location' => 'Wound Location',
                    'primary_insurance_name' => 'Insurance Name',
                    'primary_member_id' => 'Member ID',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        echo "   ✅ Mapped: Biowound IVR → ACZ & ASSOCIATES\n";
    }

    private function fixImbedTemplates(): void
    {
        // Find or create IMBED manufacturer
        $imbedManufacturer = DB::table('manufacturers')->where('name', 'IMBED')->first();

        if (!$imbedManufacturer) {
            // Find the highest manufacturer ID and add 1
            $maxId = DB::table('manufacturers')->max('id') ?? 0;
            $imbedId = $maxId + 1;

            DB::table('manufacturers')->insert([
                'id' => $imbedId,
                'name' => 'IMBED',
                'slug' => 'imbed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "   ✅ Created manufacturer: IMBED (ID: {$imbedId})\n";
            $imbedManufacturerId = $imbedId;
        } else {
            $imbedManufacturerId = $imbedManufacturer->id;
            echo "   ✅ Found existing manufacturer: IMBED (ID: {$imbedManufacturerId})\n";
        }

        // Map Imbed Microlyte IVR template
        DB::table('docuseal_templates')->updateOrInsert(
            [
                'template_name' => 'Imbed Microlyte IVR',
                'manufacturer_id' => $imbedManufacturerId,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'docuseal_template_id' => 'imbed_microlyte_ivr_temp',
                'document_type' => 'IVR',
                'is_default' => true,
                'is_active' => true,
                'field_mappings' => json_encode([
                    'patient_name' => 'Patient Name',
                    'patient_dob' => 'Date of Birth',
                    'provider_name' => 'Provider Name',
                    'facility_name' => 'Facility Name',
                    'wound_type' => 'Wound Type',
                    'wound_location' => 'Wound Location',
                    'primary_insurance_name' => 'Insurance Name',
                    'primary_member_id' => 'Member ID',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        echo "   ✅ Mapped: Imbed Microlyte IVR → IMBED\n";

        // Map Imbed Microlyte Order Form template
        DB::table('docuseal_templates')->updateOrInsert(
            [
                'template_name' => 'Imbed Microlyte Order Form',
                'manufacturer_id' => $imbedManufacturerId,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'docuseal_template_id' => 'imbed_microlyte_order_temp',
                'document_type' => 'OrderForm',
                'is_default' => true,
                'is_active' => true,
                'field_mappings' => json_encode([
                    'patient_name' => 'Patient Name',
                    'patient_dob' => 'Date of Birth',
                    'provider_name' => 'Provider Name',
                    'facility_name' => 'Facility Name',
                    'wound_type' => 'Wound Type',
                    'wound_location' => 'Wound Location',
                    'primary_insurance_name' => 'Insurance Name',
                    'primary_member_id' => 'Member ID',
                    'quantity' => 'Quantity',
                    'product_code' => 'Product Code',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        echo "   ✅ Mapped: Imbed Microlyte Order Form → IMBED\n";
    }

    private function verifyAdvancedSolution(): void
    {
        $advancedSolution = DB::table('manufacturers')->where('id', 2)->first();
        if ($advancedSolution && $advancedSolution->name === 'Advanced Solution') {
            $templateExists = DB::table('docuseal_templates')
                ->where('manufacturer_id', 2)
                ->where('template_name', 'Advanced Solution IVR')
                ->exists();

            if ($templateExists) {
                echo "   ✅ Verified: Advanced Solution IVR → Advanced Solution (already mapped)\n";
            } else {
                echo "   ⚠️  Advanced Solution template mapping missing - may need manual sync\n";
            }
        } else {
            echo "   ⚠️  Advanced Solution manufacturer not properly configured\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all template mappings created by this migration
        DB::table('docuseal_templates')
            ->where('manufacturer_id', 1)
            ->whereIn('template_name', ['Biowound IVR', 'ACZ & ASSOCIATES - Biowound IVR'])
            ->delete();

        // Remove IMBED templates (find IMBED manufacturer first)
        $imbedManufacturer = DB::table('manufacturers')->where('name', 'IMBED')->first();
        if ($imbedManufacturer) {
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $imbedManufacturer->id)
                ->whereIn('template_name', ['Imbed Microlyte IVR', 'Imbed Microlyte Order Form'])
                ->delete();
        }

        echo "🗑️ Removed template mappings\n";
    }
};
