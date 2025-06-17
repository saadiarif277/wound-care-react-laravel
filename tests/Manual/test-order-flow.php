<?php

/**
 * Manual Test Script for Order Flow End-to-End
 * Run this script to test the complete order flow from product request to IVR submission
 */

echo "========================================\n";
echo "MSC Wound Portal - Order Flow Test Script\n";
echo "========================================\n\n";

// Check PHP version
echo "1. Environment Check:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   Laravel Path: " . __DIR__ . "/../../\n\n";

// Test steps
$testSteps = [
    [
        'step' => 1,
        'name' => 'Database Connection',
        'command' => 'php artisan db:connection',
        'expected' => 'Database connection successful'
    ],
    [
        'step' => 2,
        'name' => 'Create Test Order',
        'command' => 'php artisan db:seed --class=TestOrderSeeder',
        'expected' => 'Test ProductRequest created successfully!'
    ],
    [
        'step' => 3,
        'name' => 'Check DocuSeal Configuration',
        'command' => 'php artisan tinker --execute="echo config(\'services.docuseal.api_key\') ? \'API Key Configured\' : \'API Key Missing\';"',
        'expected' => 'API Key Configured'
    ],
    [
        'step' => 4,
        'name' => 'Verify Order Status',
        'command' => 'php artisan tinker --execute="\\App\\Models\\Order\\ProductRequest::latest()->first()->order_status"',
        'expected' => 'pending_ivr'
    ],
];

echo "2. Running Automated Tests:\n";
echo "----------------------------\n";

foreach ($testSteps as $test) {
    echo "   Step {$test['step']}: {$test['name']}\n";
    echo "   Command: {$test['command']}\n";
    echo "   Expected: {$test['expected']}\n";
    echo "   Status: Run manually to verify\n\n";
}

echo "3. Manual Testing Checklist:\n";
echo "----------------------------\n";
echo "   [ ] Start Laravel server: php artisan serve\n";
echo "   [ ] Start Vite dev server: npm run dev\n";
echo "   [ ] Login as admin user\n";
echo "   [ ] Navigate to /admin/orders\n";
echo "   [ ] Find order with status 'pending_ivr'\n";
echo "   [ ] Click on order to view details\n";
echo "   [ ] Verify patient info displays correctly\n";
echo "   [ ] Click 'Generate IVR' button\n";
echo "   [ ] Verify IVR document generates\n";
echo "   [ ] Click 'Send to Manufacturer'\n";
echo "   [ ] Verify status changes to 'ivr_sent'\n\n";

echo "4. API Testing Commands:\n";
echo "------------------------\n";
echo "# Get CSRF token:\n";
echo "curl -c cookies.txt -X GET http://localhost:8000/csrf-token\n\n";

echo "# List orders (requires auth):\n";
echo "curl -b cookies.txt -X GET http://localhost:8000/admin/orders\n\n";

echo "# Generate IVR for order:\n";
echo "curl -b cookies.txt -X POST http://localhost:8000/admin/orders/{order_id}/generate-ivr \\\n";
echo "  -H 'X-CSRF-TOKEN: {csrf_token}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"ivr_required\": true}'\n\n";

echo "5. Troubleshooting:\n";
echo "-------------------\n";
echo "If you encounter issues:\n";
echo "- Check logs: tail -f storage/logs/laravel.log\n";
echo "- Verify database: php artisan tinker\n";
echo "  > \\App\\Models\\Order\\ProductRequest::count()\n";
echo "  > \\App\\Models\\User::whereHas('roles', function(\$q) { \$q->where('slug', 'msc-admin'); })->first()\n";
echo "- Check DocuSeal config: config/services.php\n";
echo "- Verify FHIR connection: .env (AZURE_FHIR_*)\n\n";

echo "========================================\n";
echo "End of test script\n";
echo "========================================\n";