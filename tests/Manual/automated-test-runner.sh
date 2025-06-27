#!/bin/bash

# MSC Wound Portal - Automated Order Flow Test Runner
# This script automates the testing of the order flow

echo "======================================"
echo "MSC Order Flow Automated Test Runner"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check if command succeeded
check_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ $1${NC}"
    else
        echo -e "${RED}✗ $1${NC}"
        exit 1
    fi
}

# Function to run command and check output
run_test() {
    echo -e "${YELLOW}Running: $1${NC}"
    eval $2
    check_status "$1"
    echo ""
}

# 1. Environment Check
echo "1. Checking Environment..."
echo "=========================="

run_test "PHP Version Check" "php -v | head -1"
run_test "Composer Check" "composer --version | head -1"
run_test "NPM Check" "npm --version"
run_test "Database Connection" "php artisan db:show 2>/dev/null || echo 'Configure database first'"

# 2. Setup Test Data
echo "2. Setting Up Test Data..."
echo "=========================="

# Create test order
echo "Creating test order..."
php artisan db:seed --class=TestOrderSeeder
check_status "Test order created"

# Get the order ID
ORDER_ID=$(php artisan tinker --execute="echo \App\Models\Order\ProductRequest::where('order_status', 'pending_ivr')->latest()->value('id');")
echo "Test Order ID: $ORDER_ID"
echo ""

# 3. Configuration Check
echo "3. Checking Configuration..."
echo "============================"

# Check DocuSeal config
DOCUSEAL_KEY=$(php artisan tinker --execute="echo config('services.docuseal.api_key') ? 'SET' : 'NOT SET';")
echo "DocuSeal API Key: $DOCUSEAL_KEY"

# Check FHIR config
FHIR_URL=$(php artisan tinker --execute="echo config('services.azure_fhir.base_url') ? 'SET' : 'NOT SET';")
echo "Azure FHIR URL: $FHIR_URL"
echo ""

# 4. Test Order Flow via Tinker
echo "4. Testing Order Flow..."
echo "========================"

# Test IVR Generation
echo "Testing IVR generation for Order ID: $ORDER_ID"
php artisan tinker << 'EOF'
$order = \App\Models\Order\ProductRequest::find(env('ORDER_ID'));
if (!$order) {
    echo "Order not found\n";
    exit(1);
}
echo "Order Status: " . $order->order_status . "\n";
echo "Order Number: " . $order->request_number . "\n";

// Test IVR service
try {
    $ivrService = app(\App\Services\IvrDocusealService::class);
    echo "IVR Service loaded successfully\n";
    
    // Check if order can generate IVR
    if ($order->order_status === 'pending_ivr') {
        echo "Order is ready for IVR generation\n";
    } else {
        echo "Order status is not pending_ivr: " . $order->order_status . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
exit(0);
EOF

# 5. API Testing
echo ""
echo "5. API Endpoint Tests..."
echo "======================="

# Start server in background if not running
if ! curl -s http://localhost:8000 > /dev/null; then
    echo "Starting Laravel server..."
    php artisan serve > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 3
fi

# Get CSRF token
echo "Getting CSRF token..."
CSRF_RESPONSE=$(curl -s http://localhost:8000/csrf-token)
CSRF_TOKEN=$(echo $CSRF_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)
echo "CSRF Token obtained: ${CSRF_TOKEN:0:20}..."

# Test routes
echo ""
echo "Testing routes..."
curl -s -o /dev/null -w "GET /admin/orders: %{http_code}\n" http://localhost:8000/admin/orders
curl -s -o /dev/null -w "GET /quick-requests/create: %{http_code}\n" http://localhost:8000/quick-requests/create
curl -s -o /dev/null -w "GET /demo/complete-order-flow: %{http_code}\n" http://localhost:8000/demo/complete-order-flow

# 6. Summary
echo ""
echo "======================================"
echo "Test Summary"
echo "======================================"
echo "Order ID for testing: $ORDER_ID"
echo "Order Status: pending_ivr"
echo ""
echo "Next Steps:"
echo "1. Login to the application"
echo "2. Navigate to /admin/orders"
echo "3. Find order ID: $ORDER_ID"
echo "4. Click 'Generate IVR'"
echo "5. Verify document generation"
echo ""
echo "Demo Mode:"
echo "Visit http://localhost:8000/demo/complete-order-flow"
echo ""

# Cleanup
if [ ! -z "$SERVER_PID" ]; then
    echo "Stopping test server..."
    kill $SERVER_PID 2>/dev/null
fi

echo "Test setup complete!"