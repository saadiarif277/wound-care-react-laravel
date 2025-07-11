<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Permission;

class CheckPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if financial permissions were created properly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking financial permissions...');

        // Total permissions
        $totalPermissions = Permission::count();
        $this->info("Total permissions: {$totalPermissions}");

        // Financial permissions
        $this->info("\nFinancial permissions:");
        $financialPermissions = Permission::where('slug', 'like', '%financial%')->get();
        
        foreach ($financialPermissions as $permission) {
            $this->line("ID: {$permission->id} | Name: {$permission->name} | Slug: {$permission->slug}");
        }

        // Check for the specific new permissions we added
        $this->info("\nChecking 4-tier financial permissions:");
        $newPermissions = ['common-financial-data', 'my-financial-data', 'view-all-financial-data', 'no-financial-data'];
        
        foreach ($newPermissions as $slug) {
            $exists = Permission::where('slug', $slug)->exists();
            $status = $exists ? '<info>EXISTS</info>' : '<error>NOT FOUND</error>';
            $this->line("Permission '{$slug}': {$status}");
        }

        return Command::SUCCESS;
    }
}
