<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class RunMigrationController extends Controller
{
    public function runMigration()
    {
        // Only allow admin users
        if (!Auth::check() || !Auth::user()->hasRole('msc-admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Run the specific migration
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2024_12_17_000001_add_field_discovery_to_docuseal_templates.php',
                '--force' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully',
                'output' => Artisan::output()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}