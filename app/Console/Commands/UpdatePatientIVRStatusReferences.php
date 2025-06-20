<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdatePatientIVRStatusReferences extends Command
{
    protected $signature = 'update:ivr-references';
    protected $description = 'Update all PatientIVRStatus references to PatientManufacturerIVREpisode';

    public function handle()
    {
        $files = [
            'tests/Unit/PatientIVRStatusTest.php',
            'tests/Feature/EpisodeWorkflowTest.php',
            'database/seeders/SalesRepTestDataSeeder.php',
            'database/factories/PatientIVRStatusFactory.php',
            'app/Models/Order/Order.php',
            'app/Http/Controllers/QuickRequestEpisodeController.php',
            'app/Http/Controllers/Provider/DashboardController.php',
            'app/Http/Controllers/QuickRequestController.php',
            'app/Http/Controllers/DocuSealWebhookController.php',
            'app/Http/Controllers/Admin/PatientIVRController.php',
            'app/Http/Controllers/Admin/OrderCenterController.php',
            'app/Console/Commands/CreateTestEpisode.php',
            'app/Console/Commands/FixEpisodeProductRequestLinks.php',
        ];

        foreach ($files as $file) {
            $path = base_path($file);
            if (File::exists($path)) {
                $content = File::get($path);
                
                // Replace the use statement
                $content = str_replace(
                    'use App\Models\PatientIVRStatus;',
                    'use App\Models\PatientManufacturerIVREpisode;',
                    $content
                );
                
                // Replace class references
                $content = str_replace('PatientIVRStatus::', 'PatientManufacturerIVREpisode::', $content);
                $content = str_replace('new PatientIVRStatus', 'new PatientManufacturerIVREpisode', $content);
                $content = str_replace('PatientIVRStatus $', 'PatientManufacturerIVREpisode $', $content);
                
                File::put($path, $content);
                $this->info("Updated: $file");
            }
        }
        
        $this->info('All references updated successfully!');
    }
}
