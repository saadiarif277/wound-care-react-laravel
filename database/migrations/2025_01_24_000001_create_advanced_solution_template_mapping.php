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
        // First, ensure manufacturer ID 2 exists and is 'Advanced Solution' or similar
        $manufacturer = DB::table('manufacturers')->where('id', 2)->first();

        if (!$manufacturer) {
            // Create the manufacturer if it doesn't exist
            DB::table('manufacturers')->insert([
                'id' => 2,
                'name' => 'Advanced Solution',
                'slug' => 'advanced-solution',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Update the name to match the Docuseal folder name
            DB::table('manufacturers')->where('id', 2)->update([
                'name' => 'Advanced Solution',
                'slug' => 'advanced-solution',
                'updated_at' => now(),
            ]);
        }

        // Create or update the Docuseal template mapping
        // Note: The actual docuseal_template_id should be fetched from the API
        // For now, we'll create a placeholder that the sync command can update
        DB::table('docuseal_templates')->updateOrInsert(
            [
                'template_name' => 'Advanced Solution IVR',
                'manufacturer_id' => 2,
            ],
            [
                'id' => Str::uuid(),
                'docuseal_template_id' => 'advanced_solution_ivr_temp', // Will be updated by sync
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('docuseal_templates')
            ->where('template_name', 'Advanced Solution IVR')
            ->where('manufacturer_id', 2)
            ->delete();
    }
};
