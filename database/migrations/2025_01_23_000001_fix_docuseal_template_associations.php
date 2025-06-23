<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's fix the manufacturer_id column to accept both string and uuid temporarily
        Schema::table('docuseal_templates', function (Blueprint $table) {
            $table->string('manufacturer_id')->nullable()->change();
        });

        // Map manufacturer names to their actual IDs
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

        // Fix manufacturer associations
        foreach ($manufacturerMappings as $stringId => $manufacturerName) {
            $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
            
            if ($manufacturer) {
                // Update templates that have the string manufacturer_id
                DB::table('docuseal_templates')
                    ->where('manufacturer_id', $stringId)
                    ->update(['manufacturer_id' => $manufacturer->id]);
            }
        }

        // Also check template names for manufacturer patterns
        $templates = DocusealTemplate::whereNull('manufacturer_id')->get();
        
        foreach ($templates as $template) {
            $templateNameLower = strtolower($template->template_name);
            
            // Check for manufacturer patterns in template name
            $patterns = [
                'acz' => 'ACZ Distribution',
                'biowound' => 'BioWound',
                'integra' => 'Integra',
                'kerecis' => 'Kerecis',
                'mimedx' => 'MiMedx',
                'organogenesis' => 'Organogenesis',
                'mtf' => 'MTF Biologics',
                'stimlabs' => 'StimLabs',
                'sanara' => 'Sanara MedTech',
                'skye' => 'Skye Biologics',
            ];
            
            foreach ($patterns as $pattern => $manufacturerName) {
                if (str_contains($templateNameLower, $pattern)) {
                    $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
                    if ($manufacturer) {
                        $template->manufacturer_id = $manufacturer->id;
                        $template->save();
                        break;
                    }
                }
            }
        }

        // Finally, change the column back to UUID type
        Schema::table('docuseal_templates', function (Blueprint $table) {
            $table->uuid('manufacturer_id')->nullable()->change();
        });

        // Add the foreign key constraint if it doesn't exist
        if (!Schema::hasColumn('docuseal_templates', 'manufacturer_id')) {
            Schema::table('docuseal_templates', function (Blueprint $table) {
                $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key if it exists
        Schema::table('docuseal_templates', function (Blueprint $table) {
            $table->dropForeign(['manufacturer_id']);
        });

        // Reset document types
        DB::table('docuseal_templates')
            ->whereIn('document_type', ['IVR', 'OrderForm', 'InsuranceVerification', 'OnboardingForm'])
            ->update(['document_type' => null]);

        // Reset manufacturer IDs
        DB::table('docuseal_templates')
            ->whereNotNull('manufacturer_id')
            ->update(['manufacturer_id' => null]);
    }
};