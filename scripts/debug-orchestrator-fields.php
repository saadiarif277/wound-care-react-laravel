<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Debugging Orchestrator Field Mapping\n";
echo "======================================\n\n";

// Find the episode
$episode = \App\Models\PatientManufacturerIVREpisode::where('id', '9f532e12-155c-431a-972d-21405f6055ba')->first();

if (!$episode) {
    echo "❌ Episode not found\n";
    exit(1);
}

// Get the orchestrator data
$orchestrator = app(\App\Services\QuickRequest\QuickRequestOrchestrator::class);
$data = $orchestrator->prepareDocusealData($episode);

echo "📊 Field Analysis:\n";
echo "Patient fields:\n";
foreach (['patient_name', 'patient_first_name', 'patient_last_name', 'patient_dob'] as $field) {
    $value = $data[$field] ?? 'MISSING';
    echo "   {$field}: {$value}\n";
}

echo "\nInsurance fields:\n";
foreach (['primary_insurance_name', 'primary_member_id', 'insurance_name', 'member_id'] as $field) {
    $value = $data[$field] ?? 'MISSING';
    echo "   {$field}: {$value}\n";
}

echo "\nProvider fields:\n";
foreach (['physician_npi', 'provider_npi', 'physician_name', 'provider_name'] as $field) {
    $value = $data[$field] ?? 'MISSING';
    echo "   {$field}: {$value}\n";
}

echo "\nAll available fields (first 30):\n";
$i = 0;
foreach ($data as $key => $value) {
    if ($i++ >= 30) break;
    echo "   {$key}: {$value}\n";
}
