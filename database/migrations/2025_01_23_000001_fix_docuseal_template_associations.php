<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Since the table structure has been updated, we work with the new structure
        // The manufacturer_name column already exists as a string, so we don't need to change it

        // Map manufacturer names to their actual IDs for reference
        $manufacturerMappings = [
            'ACZ_Distribution' => 'ACZ Distribution',
            'ACZ' => 'ACZ Distribution',
            'BioWound' => 'BioWound',
            'Integra' => 'Integra',
            'Kerecis' => 'Kerecis',
            'MiMedx' => 'MiMedx',
            'Organogenesis' => 'Organogenesis',
            'MTF Biologics' => 'MTF Biologics',
            'StimLabs' => 'StimLabs',
            'Sanara MedTech' => 'Sanara MedTech',
            'Skye Biologics' => 'Skye Biologics',
        ];

        // Update templates with proper document types based on their names
        DB::table('docuseal_templates')
            ->whereRaw('LOWER(template_name) LIKE ?', ['%ivr%'])
            ->whereNull('document_type')
            ->update(['document_type' => 'IVR']);

        DB::table('docuseal_templates')
            ->whereRaw('LOWER(template_name) LIKE ?', ['%order%'])
            ->whereNull('document_type')
            ->update(['document_type' => 'OrderForm']);

        DB::table('docuseal_templates')
            ->whereRaw('LOWER(template_name) LIKE ?', ['%insurance%'])
            ->whereNull('document_type')
            ->update(['document_type' => 'InsuranceVerification']);

        DB::table('docuseal_templates')
            ->whereRaw('LOWER(template_name) LIKE ?', ['%onboard%'])
            ->whereNull('document_type')
            ->update(['document_type' => 'OnboardingForm']);

        // Fix manufacturer associations - update manufacturer_id based on mappings
        $manufacturerIdMappings = [
            'ACZ_Distribution' => 1,
            'ACZ' => 1,
            'BioWound' => 2,
            'Integra' => 3,
            'Kerecis' => 4,
            'MiMedx' => 5,
            'Organogenesis' => 6,
            'MTF Biologics' => 7,
            'StimLabs' => 8,
            'Sanara MedTech' => 9,
            'Skye Biologics' => 10,
        ];

        foreach ($manufacturerIdMappings as $oldName => $manufacturerId) {
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $oldName)
                ->update(['manufacturer_id' => $manufacturerId]);
        }

        // Also check template names for manufacturer patterns and update manufacturer_id
        $templates = DB::table('docuseal_templates')->get();

        foreach ($templates as $template) {
            $templateNameLower = strtolower($template->template_name);

            // Check for manufacturer patterns in template name
            $patterns = [
                'acz' => 1,
                'biowound' => 2,
                'integra' => 3,
                'kerecis' => 4,
                'mimedx' => 5,
                'organogenesis' => 6,
                'mtf' => 7,
                'stimlabs' => 8,
                'sanara' => 9,
                'skye' => 10,
            ];

            foreach ($patterns as $pattern => $manufacturerId) {
                if (str_contains($templateNameLower, $pattern)) {
                    DB::table('docuseal_templates')
                        ->where('id', $template->id)
                        ->update(['manufacturer_id' => $manufacturerId]);
                    break; // Only update once per template
                }
            }
        }

        // Ensure all templates have a valid document_type
        DB::table('docuseal_templates')
            ->whereNull('document_type')
            ->update(['document_type' => 'IVR']); // Default to IVR if not set
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Since we no longer have foreign key constraints with the new structure,
        // we just need to reset the document types

        // Reset document types to a default value instead of null
        DB::table('docuseal_templates')
            ->whereIn('document_type', ['IVR', 'OrderForm', 'InsuranceVerification', 'OnboardingForm'])
            ->update(['document_type' => 'InsuranceVerification']); // Set to a valid default

        // Reset manufacturer names to original values (this is a best-effort reversal)
        $reverseMappings = [
            1 => 'ACZ',
            2 => 'BioWound',
            3 => 'Integra',
            4 => 'Kerecis',
            5 => 'MiMedx',
            6 => 'Organogenesis',
            7 => 'MTF Biologics',
            8 => 'StimLabs',
            9 => 'Sanara MedTech',
            10 => 'Skye Biologics',
        ];

        foreach ($reverseMappings as $manufacturerId => $oldName) {
            DB::table('docuseal_templates')
                ->where('manufacturer_id', $manufacturerId)
                ->update(['manufacturer_id' => $oldName]);
        }
    }
};