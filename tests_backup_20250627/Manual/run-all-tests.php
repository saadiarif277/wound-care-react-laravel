<?php

/**
 * Manual Test Runner for MSC Wound Care Portal
 *
 * This script runs all manual tests in the proper order and provides
 * comprehensive output and error handling.
 */

// Bootstrap Laravel if autoloader exists
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';

    // Bootstrap Laravel application if available
    if (file_exists(__DIR__ . '/../../bootstrap/app.php')) {
        $app = require_once __DIR__ . '/../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    }
}

echo "MSC Wound Care Portal - Manual Test Suite\n";
echo str_repeat("=", 50) . "\n\n";

// Test configuration
$testSuites = [
    'Integration Tests' => [
        'description' => 'Database and external service connectivity',
        'tests' => [
            'Integration/SupabaseConnectionTest.php' => 'Supabase Database Connection'
        ]
    ],
    'Security Tests' => [
        'description' => 'Role-based access control and financial restrictions',
        'tests' => [
            'Security/ProductCatalogRestrictionsTest.php' => 'Product Catalog Role Restrictions',
            'Security/OfficeManagerPricingRestrictionsTest.php' => 'Office Manager Financial Restrictions'
        ]
    ],
    'Service Tests' => [
        'description' => 'Business logic and service layer functionality',
        'tests' => [
            'Services/ValidationBuilderServiceTest.php' => 'Validation Builder Service',
            'Services/PatientServiceTest.php' => 'Patient Service Operations'
        ]
    ],
    'API Tests' => [
        'description' => 'REST API endpoints and external integrations',
        'tests' => [
            'Api/ProductRecommendationsApiTest.php' => 'Product Recommendations API',
            'Api/FhirApiTest.php' => 'FHIR API Integration'
        ]
    ]
];

// Test execution tracking
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$skippedTests = 0;
$results = [];

// Helper functions
function formatTestName($name, $maxLength = 50) {
    return str_pad($name, $maxLength, '.');
}

function runTest($testFile, $testName) {
    global $totalTests, $passedTests, $failedTests, $skippedTests, $results;

    $totalTests++;
    $testPath = __DIR__ . '/' . $testFile;

    echo formatTestName($testName, 45) . " ";

    if (!file_exists($testPath)) {
        echo "SKIP (File not found)\n";
        $skippedTests++;
        $results[] = ['test' => $testName, 'status' => 'SKIP', 'message' => 'File not found'];
        return;
    }

    // Capture output and errors
    ob_start();
    $errorOutput = '';

    try {
        // Set error handler to capture errors
        set_error_handler(function($severity, $message, $file, $line) use (&$errorOutput) {
            $errorOutput .= "Error: $message in $file on line $line\n";
        });

        // Execute the test
        $startTime = microtime(true);
        include $testPath;
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        restore_error_handler();

        $output = ob_get_clean();

        // Check for errors in output
        if (!empty($errorOutput) || strpos($output, 'Fatal error') !== false || strpos($output, 'Exception') !== false) {
            echo "FAIL\n";
            $failedTests++;
            $results[] = [
                'test' => $testName,
                'status' => 'FAIL',
                'message' => $errorOutput ?: 'Test execution failed',
                'output' => $output,
                'time' => $executionTime
            ];
        } else {
            echo "PASS ({$executionTime}ms)\n";
            $passedTests++;
            $results[] = [
                'test' => $testName,
                'status' => 'PASS',
                'message' => 'Test completed successfully',
                'time' => $executionTime
            ];
        }

    } catch (Exception $e) {
        restore_error_handler();
        ob_end_clean();

        echo "FAIL\n";
        $failedTests++;
        $results[] = [
            'test' => $testName,
            'status' => 'FAIL',
            'message' => $e->getMessage(),
            'time' => 0
        ];
    } catch (Error $e) {
        restore_error_handler();
        ob_end_clean();

        echo "FAIL\n";
        $failedTests++;
        $results[] = [
            'test' => $testName,
            'status' => 'FAIL',
            'message' => $e->getMessage(),
            'time' => 0
        ];
    }
}

function checkPrerequisites() {
    echo "Checking Prerequisites...\n";
    echo str_repeat("-", 30) . "\n";

    $checks = [
        'PHP Version >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'Composer Autoloader' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
        '.env File' => file_exists(__DIR__ . '/../../.env'),
        'Laravel Framework' => class_exists('Illuminate\Foundation\Application'),
    ];

    $allPassed = true;
    foreach ($checks as $check => $passed) {
        echo formatTestName($check, 25) . " " . ($passed ? "✅ PASS" : "❌ FAIL") . "\n";
        if (!$passed) $allPassed = false;
    }

    echo "\n";

    if (!$allPassed) {
        echo "❌ Prerequisites check failed. Please resolve the issues above before running tests.\n\n";
        return false;
    }

    echo "✅ All prerequisites met.\n\n";
    return true;
}

// Main execution
$startTime = microtime(true);

// Check prerequisites
if (!checkPrerequisites()) {
    exit(1);
}

// Run test suites
foreach ($testSuites as $suiteName => $suiteConfig) {
    echo "{$suiteName}\n";
    echo str_repeat("-", strlen($suiteName)) . "\n";
    echo "Description: {$suiteConfig['description']}\n\n";

    foreach ($suiteConfig['tests'] as $testFile => $testName) {
        runTest($testFile, $testName);
    }

    echo "\n";
}

// Calculate total execution time
$totalTime = round((microtime(true) - $startTime) * 1000, 2);

// Display summary
echo str_repeat("=", 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total Tests: {$totalTests}\n";
echo "Passed: {$passedTests} ✅\n";
echo "Failed: {$failedTests} ❌\n";
echo "Skipped: {$skippedTests} ⏭️\n";
echo "Total Time: {$totalTime}ms\n\n";

// Display detailed results for failed tests
if ($failedTests > 0) {
    echo "FAILED TEST DETAILS\n";
    echo str_repeat("-", 20) . "\n";

    foreach ($results as $result) {
        if ($result['status'] === 'FAIL') {
            echo "❌ {$result['test']}\n";
            echo "   Error: {$result['message']}\n";
            if (isset($result['output']) && !empty($result['output'])) {
                echo "   Output: " . substr($result['output'], 0, 200) . "...\n";
            }
            echo "\n";
        }
    }
}

// Display performance summary
if ($passedTests > 0) {
    echo "PERFORMANCE SUMMARY\n";
    echo str_repeat("-", 20) . "\n";

    $totalTestTime = 0;
    $fastestTest = null;
    $slowestTest = null;

    foreach ($results as $result) {
        if ($result['status'] === 'PASS' && isset($result['time'])) {
            $totalTestTime += $result['time'];

            if ($fastestTest === null || $result['time'] < $fastestTest['time']) {
                $fastestTest = $result;
            }

            if ($slowestTest === null || $result['time'] > $slowestTest['time']) {
                $slowestTest = $result;
            }
        }
    }

    if ($passedTests > 0) {
        $avgTime = round($totalTestTime / $passedTests, 2);
        echo "Average Test Time: {$avgTime}ms\n";

        if ($fastestTest) {
            echo "Fastest Test: {$fastestTest['test']} ({$fastestTest['time']}ms)\n";
        }

        if ($slowestTest) {
            echo "Slowest Test: {$slowestTest['test']} ({$slowestTest['time']}ms)\n";
        }
    }

    echo "\n";
}

// Exit with appropriate code
$exitCode = $failedTests > 0 ? 1 : 0;

if ($exitCode === 0) {
    echo "🎉 All tests completed successfully!\n";
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
}

echo "\nFor detailed test documentation, see: tests/Manual/README.md\n";

exit($exitCode);
