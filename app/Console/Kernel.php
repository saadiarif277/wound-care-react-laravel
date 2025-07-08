<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // DocuSeal Template Synchronization - Daily at 2 AM
        $schedule->command('docuseal:sync-templates')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'))
                 ->appendOutputTo(storage_path('logs/docuseal-sync.log'));

        // DocuSeal Configuration Validation - Weekly on Sundays at 3 AM  
        $schedule->command('docuseal:validate-configs')
                 ->weeklyOn(0, '03:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'))
                 ->appendOutputTo(storage_path('logs/docuseal-validation.log'));

        // Example of other scheduled tasks
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 