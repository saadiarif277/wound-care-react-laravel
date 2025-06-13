<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ClearAllSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:clear-all {--force : Force clear without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all user sessions from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will log out all users. Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            // Clear all sessions from the database
            $deleted = DB::table('sessions')->delete();

            $this->info("Successfully cleared {$deleted} sessions.");
            $this->info('All users have been logged out.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear sessions: ' . $e->getMessage());
            return 1;
        }
    }
}
