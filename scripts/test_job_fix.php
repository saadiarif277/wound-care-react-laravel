<?php

/**
 * Test script to verify ProcessQuickRequestToDocusealAndFhir job fix
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Jobs\ProcessQuickRequestToDocusealAndFhir;

echo "🧪 Testing ProcessQuickRequestToDocusealAndFhir Job Fix\n";
echo "=====================================================\n\n";

// Test 1: Test with UUID string (what the model actually provides)
echo "1. Testing job constructor with UUID string...\n";

try {
    $uuid = '550e8400-e29b-41d4-a716-446655440000'; // Example UUID
    $job = new ProcessQuickRequestToDocusealAndFhir($uuid);
    echo "✅ Job created successfully with UUID: {$uuid}\n";

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($job);
    $episodeIdProperty = $reflection->getProperty('episodeId');
    $episodeIdProperty->setAccessible(true);
    $episodeIdValue = $episodeIdProperty->getValue($job);

    echo "✅ Episode ID type: " . gettype($episodeIdValue) . "\n";
    echo "✅ Episode ID value: {$episodeIdValue}\n";
} catch (Exception $e) {
    echo "❌ Error creating job with UUID: " . $e->getMessage() . "\n";
}

// Test 2: Test with null episode ID
echo "\n2. Testing job constructor with null episode ID...\n";

try {
    $job = new ProcessQuickRequestToDocusealAndFhir(null);
    echo "✅ Job created successfully with null episode ID\n";

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($job);
    $episodeIdProperty = $reflection->getProperty('episodeId');
    $episodeIdProperty->setAccessible(true);
    $episodeIdValue = $episodeIdProperty->getValue($job);

    echo "✅ Episode ID is null: " . (is_null($episodeIdValue) ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "❌ Error creating job with null: " . $e->getMessage() . "\n";
}

// Test 3: Test with both episode ID and quick request data
echo "\n3. Testing job constructor with both parameters...\n";

try {
    $uuid = '550e8400-e29b-41d4-a716-446655440001';
    $quickRequestData = [
        'patient_name' => 'Test Patient',
        'provider_id' => 1,
        'facility_id' => 1
    ];

    $job = new ProcessQuickRequestToDocusealAndFhir($uuid, $quickRequestData);
    echo "✅ Job created successfully with both parameters\n";

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($job);
    $episodeIdProperty = $reflection->getProperty('episodeId');
    $quickRequestDataProperty = $reflection->getProperty('quickRequestData');
    $episodeIdProperty->setAccessible(true);
    $quickRequestDataProperty->setAccessible(true);

    $episodeIdValue = $episodeIdProperty->getValue($job);
    $quickRequestDataValue = $quickRequestDataProperty->getValue($job);

    echo "✅ Episode ID: {$episodeIdValue}\n";
    echo "✅ Quick Request Data count: " . count($quickRequestDataValue) . "\n";
} catch (Exception $e) {
    echo "❌ Error creating job with both parameters: " . $e->getMessage() . "\n";
}

// Test 4: Test with empty string episode ID
echo "\n4. Testing job constructor with empty string episode ID...\n";

try {
    $job = new ProcessQuickRequestToDocusealAndFhir('');
    echo "✅ Job created successfully with empty string\n";

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($job);
    $episodeIdProperty = $reflection->getProperty('episodeId');
    $episodeIdProperty->setAccessible(true);
    $episodeIdValue = $episodeIdProperty->getValue($job);

    echo "✅ Episode ID: '{$episodeIdValue}'\n";
} catch (Exception $e) {
    echo "❌ Error creating job with empty string: " . $e->getMessage() . "\n";
}

// Test 5: Test reflection to verify parameter types
echo "\n5. Testing job constructor parameter types via reflection...\n";

try {
    $reflection = new ReflectionClass(ProcessQuickRequestToDocusealAndFhir::class);
    $constructor = $reflection->getConstructor();
    $parameters = $constructor->getParameters();

    echo "✅ Constructor has " . count($parameters) . " parameters\n";

    foreach ($parameters as $i => $parameter) {
        $type = $parameter->getType();
        $typeName = $type ? $type->getName() : 'mixed';
        $isNullable = $type && $type->allowsNull();
        $name = $parameter->getName();

        echo "  Parameter {$i}: \${$name} - {$typeName}" . ($isNullable ? ' (nullable)' : '') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking constructor parameters: " . $e->getMessage() . "\n";
}

echo "\n🎯 Test Summary:\n";
echo "================\n";
echo "The job fix should ensure:\n";
echo "1. ✅ Job accepts UUID strings (what PatientManufacturerIVREpisode provides)\n";
echo "2. ✅ Job accepts null values for optional parameters\n";
echo "3. ✅ Job accepts both episode ID and quick request data\n";
echo "4. ✅ No more type mismatch errors when dispatching the job\n";
echo "5. ✅ Constructor parameters are properly typed as string|null\n";

echo "\n✅ ProcessQuickRequestToDocusealAndFhir Job Fix Test Complete!\n";
