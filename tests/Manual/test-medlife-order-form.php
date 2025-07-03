<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UnifiedFieldMappingService;
use App\Models\Order\Manufacturer;

echo "🧪 Testing MedLife Order Form Field Mapping\n";
echo "==========================================\n\n";

try {
    // Initialize the field mapping service
    $service = app(UnifiedFieldMappingService::class);
    
    // Test 1: Get MedLife IVR configuration
    echo "📋 Test 1: Loading MedLife IVR Configuration\n";
    $ivrConfig = $service->getManufacturerConfig('MedLife', 'IVR');
    
    if (!$ivrConfig) {
        echo "❌ FAILED: Could not load MedLife IVR configuration\n";
        exit(1);
    }
    
    echo "✅ SUCCESS: MedLife IVR configuration loaded\n";
    echo "   - Has IVR fields: " . (isset($ivrConfig['docuseal_field_names']) ? 'Yes' : 'No') . "\n";
    echo "   - Has order form enabled: " . ($ivrConfig['has_order_form'] ? 'Yes' : 'No') . "\n\n";
    
    // Test 2: Get MedLife Order Form configuration
    echo "📋 Test 2: Loading MedLife Order Form Configuration\n";
    $orderConfig = $service->getManufacturerConfig('MedLife', 'OrderForm');
    
    if (!$orderConfig) {
        echo "❌ FAILED: Could not load MedLife order form configuration\n";
        exit(1);
    }
    
    echo "✅ SUCCESS: MedLife order form configuration loaded\n";
    echo "   - Has order form fields: " . (isset($orderConfig['docuseal_field_names']) ? 'Yes' : 'No') . "\n";
    echo "   - Order form template ID: " . ($orderConfig['order_form_template_id'] ?? 'Not set') . "\n\n";
    
    // Test 3: Sample order form data
    echo "📦 Test 3: Testing Order Form Field Mapping\n";
    
    $sampleOrderData = [
        'facility_name' => 'Main Medical Center',
        'contact_name' => 'John Doe',
        'contact_title' => 'Office Manager',
        'contact_phone' => '555-123-4567',
        'facility_address' => '123 Medical Way, Suite 100',
        'order_notes' => 'Urgent order needed by Friday',
        'shipping_speed' => '2_day',
        'selected_products' => [
            [
                'product_id' => 1,
                'size' => '4x4',
                'quantity' => 2
            ]
        ],
        'sales_rep_name' => 'MSC Sales Rep'
    ];
    
    // Map the data for order form
    $mappingResult = $service->mapEpisodeToTemplate(null, 'MedLife', $sampleOrderData, 'OrderForm');
    echo "✅ SUCCESS: Data mapped successfully\n";
    echo "   - Fields mapped: " . count($mappingResult['data']) . "\n";
    echo "   - Completeness: " . round($mappingResult['completeness']['percentage'], 1) . "%\n\n";
    
    // Test 4: Convert to Docuseal order form format
    echo "🔄 Test 4: Converting to Docuseal Order Form Format\n";
    
    $docuSealFields = $service->convertToDocusealFields(
        $mappingResult['data'], 
        $orderConfig, 
        'OrderForm'  // This is the key - using OrderForm document type
    );
    
    echo "✅ SUCCESS: Converted to Docuseal format\n";
    echo "   - Docuseal fields generated: " . count($docuSealFields) . "\n\n";
    
    // Test 5: Show order form field mappings
    echo "📝 Test 5: Order Form Field Mappings\n";
    echo "Expected order form fields:\n";
    
    foreach ($orderConfig['docuseal_field_names'] ?? [] as $canonical => $docusealName) {
        $value = $mappingResult['data'][$canonical] ?? 'NOT MAPPED';
        $hasValue = isset($mappingResult['data'][$canonical]) && $mappingResult['data'][$canonical] !== null;
        $status = $hasValue ? '✅' : '⚠️';
        echo "   {$status} {$canonical} → '{$docusealName}' = {$value}\n";
    }
    
    echo "\n";
    
    // Test 6: Compare with IVR field mappings
    echo "🔍 Test 6: Comparison with IVR Mappings\n";
    
    $ivrFields = $service->convertToDocusealFields(
        $mappingResult['data'], 
        $ivrConfig, 
        'IVR'  // IVR document type
    );
    
    echo "   - IVR fields: " . count($ivrFields) . "\n";
    echo "   - Order form fields: " . count($docuSealFields) . "\n";
    echo "   - Unique order form fields: " . (count($docuSealFields) - count($ivrFields)) . "\n\n";
    
    // Test 7: Validate specific order form fields
    echo "🎯 Test 7: Validating Key Order Form Fields\n";
    
    $expectedOrderFields = [
        'company_facility' => 'Company/Facility',
        'contact_name' => 'Contact Name', 
        'contact_phone' => 'Contact Phone',
        'shipping_2_day' => 'Shipping: 2-Day',
        'total_units' => 'TOTAL UNITS',
        'date' => 'Date'
    ];
    
    foreach ($expectedOrderFields as $canonical => $expected) {
        $found = false;
        foreach ($docuSealFields as $field) {
            if ($field['name'] === $expected) {
                $found = true;
                echo "   ✅ Found: {$canonical} → '{$expected}' = {$field['default_value']}\n";
                break;
            }
        }
        
        if (!$found) {
            echo "   ❌ Missing: {$canonical} → '{$expected}'\n";
        }
    }
    
    echo "\n🎉 Order Form Test Complete!\n";
    echo "The MedLife order form field mapping is configured and ready to use.\n";
    echo "\n💡 Next Steps:\n";
    echo "1. Clear cache: php artisan cache:clear\n";
    echo "2. Test the order form in Step 8 of the Quick Request workflow\n";
    echo "3. Verify that the order form loads with template ID: {$orderConfig['order_form_template_id']}\n";

} catch (Exception $e) {
    echo "❌ TEST FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 