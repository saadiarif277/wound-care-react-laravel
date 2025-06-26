<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\IVRFieldMapping;

class IVRMappingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only check in production or when explicitly enabled
        if (config('app.env') === 'production' || config('ivr.auto_seed_mappings', false)) {
            $this->ensureIVRMappingsExist();
        }
    }

    /**
     * Ensure IVR field mappings exist, create them if they don't
     */
    private function ensureIVRMappingsExist(): void
    {
        try {
            // Check if IVR field mappings table exists and has data
            if (Schema::hasTable('ivr_field_mappings')) {
                $mappingCount = IVRFieldMapping::count();

                if ($mappingCount < 50) { // Minimum expected mappings
                    Log::info('IVR field mappings missing or insufficient, auto-creating...', [
                        'current_count' => $mappingCount,
                        'minimum_required' => 50
                    ]);

                    // Run the seeder in the background
                    Artisan::call('db:seed', [
                        '--class' => 'IVRFieldMappingSeeder',
                        '--force' => true
                    ]);

                    $newCount = IVRFieldMapping::count();
                    Log::info('IVR field mappings auto-seeded successfully', [
                        'previous_count' => $mappingCount,
                        'new_count' => $newCount
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Don't fail the app if this doesn't work
            Log::warning('Failed to auto-seed IVR field mappings', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
