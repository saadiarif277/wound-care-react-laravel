<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Scopes\OrganizationScope;

try {
    echo "=== Checking Facility ID 2 ===\n\n";
    
    // Check if facility exists in database
    $facilityRaw = \Illuminate\Support\Facades\DB::table('facilities')->where('id', 2)->first();
    
    if ($facilityRaw) {
        echo "✅ Facility found in database:\n";
        echo "   ID: {$facilityRaw->id}\n";
        echo "   Name: {$facilityRaw->name}\n";
        echo "   Deleted at: " . ($facilityRaw->deleted_at ?? 'Not deleted') . "\n";
        echo "   Organization ID: {$facilityRaw->organization_id}\n\n";
        
        // Try to load via Eloquent
        echo "Loading via Eloquent:\n";
        
        // Without scope
        $facilityWithoutScope = App\Models\Fhir\Facility::withoutGlobalScope(OrganizationScope::class)->find(2);
        echo "   Without scope: " . ($facilityWithoutScope ? 'Found' : 'Not found') . "\n";
        
        // With trashed
        $facilityWithTrashed = App\Models\Fhir\Facility::withoutGlobalScope(OrganizationScope::class)->withTrashed()->find(2);
        echo "   With trashed: " . ($facilityWithTrashed ? 'Found' : 'Not found') . "\n";
        
        // Check provider access
        echo "\nChecking provider access:\n";
        $user = App\Models\User::where('email', 'provider@example.com')->first();
        if ($user) {
            $hasAccess = $user->facilities()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('facilities.id', 2)
                ->exists();
            echo "   Provider has access: " . ($hasAccess ? 'YES ✅' : 'NO ❌') . "\n";
        }
        
    } else {
        echo "❌ Facility ID 2 not found in database!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 