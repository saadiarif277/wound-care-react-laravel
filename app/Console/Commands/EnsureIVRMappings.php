<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\IVRFieldMapping;

class EnsureIVRMappings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr:ensure-mappings {--force : Force recreation of mappings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure IVR field mappings exist and are up to date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Checking IVR field mappings...');

        try {
            $mappingCount = IVRFieldMapping::count();
            $minimumRequired = 50; // Minimum expected mappings

            if ($this->option('force') || $mappingCount < $minimumRequired) {
                $this->warn("Current mappings: {$mappingCount}, Required: {$minimumRequired}");

                if ($this->option('force')) {
                    $this->info('Force flag detected, recreating mappings...');
                } else {
                    $this->info('Insufficient mappings detected, creating...');
                }

                // Run the IVR field mapping seeder
                $this->call('db:seed', [
                    '--class' => 'IVRFieldMappingSeeder',
                    '--force' => true
                ]);

                $newCount = IVRFieldMapping::count();
                $this->info("✅ IVR field mappings updated: {$mappingCount} → {$newCount}");

                return Command::SUCCESS;
            } else {
                $this->info("✅ IVR field mappings are sufficient ({$mappingCount} mappings)");
                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to ensure IVR mappings: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
