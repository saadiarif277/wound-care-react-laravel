<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Services\Compliance\PhiAuditService;

// Mock the FHIR service to avoid Azure connection issues
$fhirService = Mockery::mock(FhirService::class);
$fhirService->shouldReceive('create')->andReturn(['id' => 'test-coverage-id']);

$logger = new PhiSafeLogger();
$auditService = new PhiAuditService($logger);

$insuranceHandler = new InsuranceHandler($fhirService, $logger, $auditService);

// Test data with the correct flat structure (from InsuranceData DTO)
$testData = [
    'primary_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'BCBS123456',
    'primary_plan_type' => 'PPO',
    'has_secondary' => true,
    'secondary_name' => 'Medicare',
    'secondary_member_id' => 'MED123456789',
    'secondary_plan_type' => 'Part B'
];

try {
    echo "Testing InsuranceHandler with flat InsuranceData structure...\n";
    $result = $insuranceHandler->createMultipleCoverages($testData, 'test-patient-id');
    echo "SUCCESS: InsuranceHandler worked with flat insurance data structure\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test with only primary insurance
$testDataPrimaryOnly = [
    'primary_name' => 'Aetna',
    'primary_member_id' => 'AET789012',
    'primary_plan_type' => 'HMO',
    'has_secondary' => false
];

try {
    echo "\nTesting InsuranceHandler with primary insurance only...\n";
    $result = $insuranceHandler->createMultipleCoverages($testDataPrimaryOnly, 'test-patient-id');
    echo "SUCCESS: InsuranceHandler worked with primary insurance only\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test with empty insurance data
$testDataEmpty = [];

try {
    echo "\nTesting InsuranceHandler with empty insurance data...\n";
    $result = $insuranceHandler->createMultipleCoverages($testDataEmpty, 'test-patient-id');
    echo "SUCCESS: InsuranceHandler worked with empty insurance data (created default)\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nInsurance fix test completed!\n";
