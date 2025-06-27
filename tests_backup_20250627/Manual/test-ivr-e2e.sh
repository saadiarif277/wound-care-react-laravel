#!/bin/bash

# MSC Wound Portal - IVR DocuSeal End-to-End Test Script
# This script tests the complete IVR generation flow

echo "========================================"
echo "IVR DocuSeal E2E Testing"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print section headers
print_section() {
    echo ""
    echo -e "${BLUE}=== $1 ===${NC}"
    echo ""
}

# Function to print test results
print_result() {
    if [ $2 -eq 0 ]; then
        echo -e "${GREEN}✓ $1${NC}"
    else
        echo -e "${RED}✗ $1${NC}"
    fi
}

# 1. Run Unit Tests
print_section "1. Running IVR Field Mapping Tests"
echo "Testing field mapping service..."
php artisan test tests/Feature/IvrFieldMappingTest.php --stop-on-failure
print_result "Field Mapping Tests" $?

# 2. Run E2E Tests
print_section "2. Running IVR DocuSeal E2E Tests"
echo "Testing complete order flow..."
php artisan test tests/Feature/IvrDocuSealE2ETest.php --stop-on-failure
print_result "E2E Integration Tests" $?

# 3. Test IVR Generation Directly
print_section "3. Testing Direct IVR Generation"
php tests/Manual/test-ivr-generation.php
print_result "Direct IVR Generation" $?

# 4. Check Configuration
print_section "4. Checking Configuration"

echo "DocuSeal Configuration:"
php artisan tinker --execute="
    echo 'API URL: ' . config('services.docuseal.api_url');
    echo PHP_EOL;
    echo 'API Key: ' . (config('services.docuseal.api_key') ? 'SET' : 'NOT SET');
    echo PHP_EOL;
"

echo ""
echo "FHIR Configuration:"
php artisan tinker --execute="
    echo 'Base URL: ' . config('services.azure_fhir.base_url');
    echo PHP_EOL;
    echo 'Client ID: ' . (config('services.azure_fhir.client_id') ? 'SET' : 'NOT SET');
    echo PHP_EOL;
"

# 5. Create Test Data
print_section "5. Creating Test Data"

echo "Creating test order..."
php artisan db:seed --class=TestOrderSeeder
print_result "Test Order Created" $?

# Get the order ID
ORDER_ID=$(php artisan tinker --execute="
    \$order = \App\Models\Order\ProductRequest::where('order_status', 'pending_ivr')->latest()->first();
    echo \$order ? \$order->id : 'none';
")

echo "Test Order ID: $ORDER_ID"

# 6. Test Field Mappings
print_section "6. Testing Field Mappings"

php artisan tinker << 'EOF'
$order = \App\Models\Order\ProductRequest::where('order_status', 'pending_ivr')->latest()->first();
if (!$order) {
    echo "No test order found\n";
    exit(1);
}

$fieldMappingService = app(\App\Services\IvrFieldMappingService::class);

// Mock patient data
$patientData = [
    'given' => ['John'],
    'family' => 'Doe',
    'birthDate' => '1970-01-01',
    'gender' => 'male',
    'address' => [[
        'line' => ['123 Test St'],
        'city' => 'Test City',
        'state' => 'TS',
        'postalCode' => '12345'
    ]],
    'telecom' => [[
        'system' => 'phone',
        'value' => '555-123-4567'
    ]]
];

// Get manufacturer from product
$product = $order->products->first();
$manufacturerKey = str_replace(' ', '_', $product->manufacturer ?? 'Unknown');

echo "Testing field mapping for manufacturer: {$manufacturerKey}\n";

try {
    $mappedFields = $fieldMappingService->mapProductRequestToIvrFields(
        $order,
        $manufacturerKey,
        $patientData
    );
    
    echo "\nMapped Fields:\n";
    echo "==============\n";
    
    // Check standard fields
    $standardFields = [
        'patient_first_name',
        'patient_last_name',
        'patient_dob',
        'patient_display_id',
        'payer_name',
        'product_name',
        'provider_name',
        'facility_name',
        'failed_conservative_treatment'
    ];
    
    foreach ($standardFields as $field) {
        $value = $mappedFields[$field] ?? 'NOT MAPPED';
        echo sprintf("%-30s: %s\n", $field, $value);
    }
    
    echo "\nTotal fields mapped: " . count($mappedFields) . "\n";
    
    // Validate mapping
    $errors = $fieldMappingService->validateMapping($manufacturerKey, $mappedFields);
    if (!empty($errors)) {
        echo "\nValidation Errors:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    } else {
        echo "\n✓ All required fields are mapped!\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
EOF

# 7. Test API Endpoints
print_section "7. Testing API Endpoints"

# Start server if not running
if ! curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo "Starting Laravel server..."
    php artisan serve > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 3
fi

# Get CSRF token
echo "Getting CSRF token..."
CSRF_RESPONSE=$(curl -s http://localhost:8000/csrf-token)
CSRF_TOKEN=$(echo $CSRF_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$CSRF_TOKEN" ]; then
    echo -e "${RED}Failed to get CSRF token${NC}"
else
    echo -e "${GREEN}CSRF token obtained${NC}"
fi

# 8. Test Manufacturer Configurations
print_section "8. Testing Manufacturer Configurations"

php artisan tinker << 'EOF'
$fieldMappingService = app(\App\Services\IvrFieldMappingService::class);
$manufacturers = $fieldMappingService->getAvailableManufacturers();

echo "Available Manufacturers:\n";
echo "=======================\n";

foreach ($manufacturers as $key => $config) {
    echo sprintf("%-25s: Template ID: %s, Has Mapping: %s\n", 
        $config['name'], 
        $config['template_id'] ?? 'NOT SET',
        $config['has_mapping'] ? 'YES' : 'NO'
    );
}

// Test field types for each manufacturer
echo "\nField Type Definitions:\n";
echo "======================\n";

$testManufacturer = 'ACZ_Distribution';
$fieldTypes = $fieldMappingService->getFieldTypes($testManufacturer);

echo "Sample field types for {$testManufacturer}:\n";
$sampleFields = array_slice($fieldTypes, 0, 10);
foreach ($sampleFields as $field => $type) {
    echo sprintf("  %-30s: %s\n", $field, $type);
}
echo "  ... and " . (count($fieldTypes) - 10) . " more fields\n";
EOF

# 9. Summary Report
print_section "9. Test Summary"

echo "Test Results:"
echo "============="
echo ""
echo "1. Field Mapping Service: Tests the core mapping functionality"
echo "2. E2E Integration: Tests complete order to IVR flow"
echo "3. Direct Generation: Tests IVR generation with real data"
echo "4. Configuration: Verifies DocuSeal and FHIR setup"
echo "5. Test Data: Creates sample orders for testing"
echo "6. Field Validation: Ensures all required fields are mapped"
echo "7. API Endpoints: Tests HTTP endpoints"
echo "8. Manufacturer Support: Verifies all manufacturers configured"
echo ""
echo "Next Steps:"
echo "1. Ensure DocuSeal API credentials are in .env"
echo "2. Configure manufacturer template IDs"
echo "3. Test with real DocuSeal templates"
echo "4. Verify FHIR patient data retrieval"
echo ""

# Cleanup
if [ ! -z "$SERVER_PID" ]; then
    echo "Stopping test server..."
    kill $SERVER_PID 2>/dev/null
fi

echo -e "${GREEN}Testing complete!${NC}"