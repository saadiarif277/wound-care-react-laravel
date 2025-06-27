<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing IVR field mapping data
        $this->migrateIvrFieldMappings();
        
        // Migrate field mapping patterns from logs if available
        $this->migrateFieldMappingPatterns();
    }

    /**
     * Migrate existing IVR field mappings to new structure
     */
    private function migrateIvrFieldMappings(): void
    {
        // Get all existing IVR episodes
        $ivrEpisodes = DB::table('patient_manufacturer_ivr_episodes')
            ->whereNotNull('manufacturer_fields')
            ->get();

        foreach ($ivrEpisodes as $episode) {
            try {
                // Calculate field completeness from existing data
                $manufacturerFields = json_decode($episode->manufacturer_fields, true) ?? [];
                $totalFields = count($manufacturerFields);
                $filledFields = 0;
                $fieldStatus = [];

                foreach ($manufacturerFields as $field => $value) {
                    $isFilled = !empty($value) || $value === '0' || $value === 0;
                    if ($isFilled) {
                        $filledFields++;
                    }
                    $fieldStatus[$field] = [
                        'filled' => $isFilled,
                        'value' => $value
                    ];
                }

                $completeness = $totalFields > 0 ? round(($filledFields / $totalFields) * 100, 2) : 0;

                // Create field mapping log entry
                DB::table('field_mapping_logs')->insert([
                    'episode_id' => $episode->episode_id,
                    'manufacturer_name' => $episode->manufacturer_name,
                    'manufacturer_id' => $episode->manufacturer_id,
                    'mapping_type' => 'docuseal',
                    'completeness_percentage' => $completeness,
                    'required_completeness_percentage' => $completeness, // Estimate since we don't have required field info
                    'fields_mapped' => $filledFields,
                    'fields_total' => $totalFields,
                    'required_fields_mapped' => $filledFields, // Estimate
                    'required_fields_total' => $totalFields, // Estimate
                    'field_status' => json_encode($fieldStatus),
                    'validation_errors' => null,
                    'validation_warnings' => null,
                    'mapping_duration_ms' => 0, // Unknown for historical data
                    'source_service' => 'legacy_migration',
                    'created_by' => null,
                    'created_at' => $episode->created_at,
                    'updated_at' => $episode->updated_at,
                ]);

                // Update IVR episode with new fields
                DB::table('patient_manufacturer_ivr_episodes')
                    ->where('id', $episode->id)
                    ->update([
                        'field_mapping_completeness' => $completeness,
                        'required_fields_completeness' => $completeness,
                        'mapped_fields' => $episode->manufacturer_fields,
                        'validation_warnings' => json_encode([]),
                        'updated_at' => now(),
                    ]);

            } catch (\Exception $e) {
                Log::error('Failed to migrate IVR episode', [
                    'episode_id' => $episode->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Migrate field mapping patterns from existing usage
     */
    private function migrateFieldMappingPatterns(): void
    {
        // Analyze existing mappings to identify common patterns
        $manufacturers = ['ACZ', 'Acell', 'Advanced Health', 'AVITA', 'LifeSciences', 'LifeNet', 'MTF Biologics', 'Organogenesis'];
        
        foreach ($manufacturers as $manufacturer) {
            // Get all episodes for this manufacturer
            $episodes = DB::table('patient_manufacturer_ivr_episodes')
                ->where('manufacturer_name', $manufacturer)
                ->whereNotNull('manufacturer_fields')
                ->get();

            $fieldUsage = [];
            
            foreach ($episodes as $episode) {
                $fields = json_decode($episode->manufacturer_fields, true) ?? [];
                
                foreach ($fields as $field => $value) {
                    if (!empty($value)) {
                        if (!isset($fieldUsage[$field])) {
                            $fieldUsage[$field] = 0;
                        }
                        $fieldUsage[$field]++;
                    }
                }
            }

            // Create analytics entries for frequently used fields
            foreach ($fieldUsage as $field => $count) {
                if ($count >= 5) { // Only track fields used 5+ times
                    DB::table('field_mapping_analytics')->insert([
                        'manufacturer_name' => $manufacturer,
                        'field_name' => $field,
                        'match_type' => 'exact', // Assume exact match for historical data
                        'source_field' => $field,
                        'match_score' => 1.0,
                        'usage_count' => $count,
                        'successful' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear migrated data
        DB::table('field_mapping_logs')->where('source_service', 'legacy_migration')->delete();
        DB::table('field_mapping_analytics')->truncate();
        
        // Reset IVR episode fields
        DB::table('patient_manufacturer_ivr_episodes')->update([
            'field_mapping_completeness' => null,
            'required_fields_completeness' => null,
            'mapped_fields' => null,
            'validation_warnings' => null,
        ]);
    }
};