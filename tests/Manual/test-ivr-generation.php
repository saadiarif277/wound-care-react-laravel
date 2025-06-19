<?php

/**
 * Test IVR Generation Directly
 * Run: php tests/Manual/test-ivr-generation.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Services\IvrDocusealService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

echo "=================================\n";
echo "Direct IVR Generation Test\n";
echo "=================================\n\n";

try {
    // 1. Find a pending order
    echo "1. Finding pending order...\n";
    $order = ProductRequest::where('order_status', 'pending_ivr')->latest()->first();
    
    if (!$order) {
        echo "   No pending orders found. Creating one...\n";
        // Run seeder
        Artisan::call('db:seed', ['--class' => 'TestOrderSeeder']);
        $order = ProductRequest::where('order_status', 'pending_ivr')->latest()->first();
    }
    
    if (!$order) {
        throw new Exception("Could not create test order");
    }
    
    echo "   ✓ Found order: {$order->request_number}\n";
    echo "   Status: {$order->order_status}\n";
    echo "   Products: " . $order->products->count() . "\n\n";
    
    // 2. Login as admin user for auth context
    echo "2. Setting auth context...\n";
    $admin = User::whereHas('roles', function($q) {
        $q->where('slug', 'msc-admin');
    })->first();
    
    if ($admin) {
        Auth::login($admin);
        echo "   ✓ Logged in as: {$admin->email}\n\n";
    } else {
        echo "   ⚠ No admin user found, continuing without auth\n\n";
    }
    
    // 3. Test IVR Service
    echo "3. Testing IVR Service...\n";
    $ivrService = app(IvrDocusealService::class);
    echo "   ✓ IVR Service loaded\n\n";
    
    // 4. Check DocuSeal configuration
    echo "4. Checking DocuSeal configuration...\n";
    $apiKey = config('services.docuseal.api_key');
    $apiUrl = config('services.docuseal.api_url');
    
    echo "   API URL: " . ($apiUrl ?: 'NOT SET') . "\n";
    echo "   API Key: " . ($apiKey ? 'SET (' . substr($apiKey, 0, 10) . '...)' : 'NOT SET') . "\n\n";
    
    if (!$apiKey) {
        echo "   ⚠ Warning: DocuSeal API key not configured in .env\n";
        echo "   Add DOCUSEAL_API_KEY to your .env file\n\n";
    }
    
    // 5. Check manufacturer configuration
    echo "5. Checking manufacturer setup...\n";
    $product = $order->products->first();
    if ($product) {
        echo "   Product: {$product->name}\n";
        echo "   Manufacturer: {$product->manufacturer}\n";
        
        // Check if manufacturer exists in database
        $manufacturer = \App\Models\Order\Manufacturer::where('name', $product->manufacturer)->first();
        if ($manufacturer) {
            echo "   ✓ Manufacturer found in database\n";
        } else {
            echo "   ⚠ Manufacturer not in database, will be created\n";
        }
    }
    echo "\n";
    
    // 6. Test field mapping
    echo "6. Testing field mapping...\n";
    $fieldMappingService = app(\App\Services\IvrFieldMappingService::class);
    
    // Get manufacturer key
    $manufacturerKey = strtolower(str_replace(' ', '_', $product->manufacturer ?? 'unknown'));
    echo "   Manufacturer key: {$manufacturerKey}\n";
    
    // Get DocuSeal config
    $docusealConfig = $fieldMappingService->getDocuSealConfig($manufacturerKey);
    echo "   Template ID: " . ($docusealConfig['template_id'] ?? 'NOT CONFIGURED') . "\n";
    echo "   Folder ID: " . ($docusealConfig['folder_id'] ?? 'NOT CONFIGURED') . "\n\n";
    
    // 7. Attempt IVR generation (dry run)
    echo "7. Attempting IVR generation (dry run)...\n";
    
    if (!$apiKey || !$docusealConfig['template_id']) {
        echo "   ⚠ Cannot proceed without DocuSeal configuration\n";
        echo "   Required:\n";
        echo "   - DOCUSEAL_API_KEY in .env\n";
        echo "   - Template configuration for manufacturer\n";
    } else {
        echo "   Ready to generate IVR!\n";
        echo "   Run the following to generate:\n\n";
        echo "   php artisan tinker\n";
        echo "   >>> \$order = \\App\\Models\\Order\\ProductRequest::find('{$order->id}');\n";
        echo "   >>> \$ivrService = app(\\App\\Services\\IvrDocusealService::class);\n";
        echo "   >>> \$submission = \$ivrService->generateIvr(\$order);\n";
        echo "   >>> echo \"IVR generated with submission ID: \" . \$submission->id;\n";
    }
    
    echo "\n=================================\n";
    echo "Test complete!\n";
    echo "=================================\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}