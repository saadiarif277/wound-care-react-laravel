<?php

require_once 'vendor/autoload.php';

use App\Services\PatientService;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing PatientService Sequential Display ID Generation\n";
echo "======================================================\n\n";

$service = new PatientService();

// Test 1: Create first patient
echo "Test 1: Creating first patient (John Smith)\n";
$result1 = $service->createPatientRecord([
    'first_name' => 'John',
    'last_name' => 'Smith',
    'dob' => '1980-01-01',
    'gender' => 'male',
    'member_id' => '123456'
], 1);

echo "Result: " . json_encode($result1, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Create second patient with same initials
echo "Test 2: Creating second patient with same initials (Jane Smith)\n";
$result2 = $service->createPatientRecord([
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'dob' => '1985-05-15',
    'gender' => 'female',
    'member_id' => '789012'
], 1);

echo "Result: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Create patient with different initials
echo "Test 3: Creating patient with different initials (Bob Johnson)\n";
$result3 = $service->createPatientRecord([
    'first_name' => 'Bob',
    'last_name' => 'Johnson',
    'dob' => '1975-12-25',
    'gender' => 'male',
    'member_id' => '345678'
], 1);

echo "Result: " . json_encode($result3, JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Search functionality
echo "Test 4: Testing search functionality\n";
$searchResults = $service->searchPatientsByDisplayId('JoSm', 1);
echo "Search results for 'JoSm': " . json_encode($searchResults, JSON_PRETTY_PRINT) . "\n\n";

echo "Testing completed successfully!\n";
