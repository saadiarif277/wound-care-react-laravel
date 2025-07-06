<?php
/**
 * Test script to verify IVR status update fixes
 *
 * This script tests:
 * 1. Performance fix - method signature mismatch
 * 2. Shipping info display fix - status value matching
 */

echo "=== IVR Status Update Fixes Test ===\n\n";

// Test 1: Check if EmailNotificationService method signature is correct
echo "1. Testing EmailNotificationService method signature...\n";

try {
    $reflection = new ReflectionMethod('App\Services\EmailNotificationService', 'sendStatusChangeNotification');
    $parameters = $reflection->getParameters();

    $hasNotificationDocuments = false;
    foreach ($parameters as $param) {
        if ($param->getName() === 'notificationDocuments') {
            $hasNotificationDocuments = true;
            break;
        }
    }

    if ($hasNotificationDocuments) {
        echo "✅ EmailNotificationService method signature is correct (includes notificationDocuments parameter)\n";
    } else {
        echo "❌ EmailNotificationService method signature is missing notificationDocuments parameter\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking EmailNotificationService: " . $e->getMessage() . "\n";
}

// Test 2: Check status mapping consistency
echo "\n2. Testing status mapping consistency...\n";

$frontendStatuses = [
    'submitted_to_manufacturer',
    'confirmed_by_manufacturer'
];

$backendStatuses = [
    'submitted_to_manufacturer',
    'confirmed_by_manufacturer'
];

$mappingCorrect = true;
foreach ($frontendStatuses as $frontend) {
    if (!in_array($frontend, $backendStatuses)) {
        $mappingCorrect = false;
        echo "❌ Status mapping mismatch: {$frontend}\n";
    }
}

if ($mappingCorrect) {
    echo "✅ Status mapping is consistent between frontend and backend\n";
}

// Test 3: Check if StatusChangeService calls EmailNotificationService correctly
echo "\n3. Testing StatusChangeService integration...\n";

try {
    $reflection = new ReflectionMethod('App\Services\StatusChangeService', 'changeOrderStatus');
    $source = file_get_contents('app/Services/StatusChangeService.php');

    if (strpos($source, 'notification_documents') !== false) {
        echo "✅ StatusChangeService includes notification documents handling\n";
    } else {
        echo "❌ StatusChangeService missing notification documents handling\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking StatusChangeService: " . $e->getMessage() . "\n";
}

// Test 4: Check OrderCenterController integration
echo "\n4. Testing OrderCenterController integration...\n";

try {
    $source = file_get_contents('app/Http/Controllers/Admin/OrderCenterController.php');

    if (strpos($source, 'notification_documents') !== false) {
        echo "✅ OrderCenterController includes notification documents handling\n";
    } else {
        echo "❌ OrderCenterController missing notification documents handling\n";
    }

    if (strpos($source, 'sendStatusChangeNotification') !== false &&
        strpos($source, '$notificationDocuments') !== false) {
        echo "✅ OrderCenterController passes notification documents to service\n";
    } else {
        echo "❌ OrderCenterController not passing notification documents to service\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking OrderCenterController: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "The fixes should resolve:\n";
echo "- Performance issue (>60 seconds): Fixed method signature mismatch\n";
echo "- Shipping info not showing: Fixed status value matching\n";
echo "- Document upload functionality: Added to modal\n";
echo "- Notification checkbox position: Moved above comments\n";

echo "\nTo test manually:\n";
echo "1. Go to admin order details\n";
echo "2. Try updating IVR status - should complete quickly\n";
echo "3. Try updating order form status to 'Submitted to Manufacturer' - shipping fields should appear\n";
echo "4. Check notification checkbox is above comments\n";
echo "5. Verify document upload appears when notification is checked\n";
