<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\QuickRequest\QuickRequestOrchestrator;

// Set up Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Authenticate as a test user
$user = User::where('email', 'admin@msc.com')->first();
if (!$user) {
    echo "❌ Test user not found. Please create a test user first.\n";
    exit(1);
}

Auth::login($user);
echo "🔐 Authenticated as: {$user->email}\n\n";

try {
    // Test the draft episode creation workflow
    echo "🧪 Testing Draft Episode Workflow\n";
    echo "================================\n\n";

    $orchestrator = app(QuickRequestOrchestrator::class);

    // Simulate form data that would come from the frontend
    $testData = [
        'patient' => [
            'id' => 'test-patient-123',
            'display_id' => 'P123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'phone' => '555-0123',
            'email' => 'john.doe@example.com',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'CA',
            'zip' => '90210'
        ],
        'provider' => [
            'npi' => '1234567890',
            'name' => 'Dr. Test Provider',
            'email' => 'provider@test.com',
            'facility_name' => 'Test Clinic'
        ],
        'facility' => [
            'name' => 'Test Clinic',
            'address' => '456 Clinic Ave',
            'city' => 'Test City',
            'state' => 'CA',
            'zip' => '90210'
        ],
        'organization' => [
            'name' => 'MedLife Solutions',
            'npi' => '1234567890'
        ],
        'clinical' => [
            'wound_type' => 'diabetic_foot_ulcer',
            'wound_location' => 'left_foot',
            'wound_size_length' => '2.5',
            'wound_size_width' => '1.8',
            'wound_size_depth' => '0.5'
        ],
        'insurance' => [
            'primary_insurance_name' => 'Medicare',
            'primary_member_id' => 'MED123456',
            'primary_plan_type' => 'medicare'
        ],
        'order_details' => [
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'size' => '2 x 2'
                ]
            ]
        ],
        'manufacturer_id' => 1
    ];

    // Step 1: Create draft episode (simulating Step 5 product selection)
    echo "1️⃣ Creating draft episode...\n";
    $draftEpisode = $orchestrator->createDraftEpisode($testData);
    
    echo "✅ Draft episode created successfully!\n";
    echo "   Episode ID: {$draftEpisode->id}\n";
    echo "   Status: {$draftEpisode->status}\n";
    echo "   Is Draft: " . ($draftEpisode->metadata['is_draft'] ? 'Yes' : 'No') . "\n";
    echo "   Patient FHIR ID: " . ($draftEpisode->patient_fhir_id ?? 'Not created yet') . "\n\n";

    // Step 2: Test IVR data preparation (simulating Step 7 IVR generation)
    echo "2️⃣ Testing IVR data preparation...\n";
    $ivrData = $orchestrator->prepareDocusealData($draftEpisode);
    
    echo "✅ IVR data prepared successfully!\n";
    echo "   Available fields: " . count($ivrData) . "\n";
    echo "   Sample fields:\n";
    foreach (array_slice($ivrData, 0, 5) as $key => $value) {
        $displayValue = is_array($value) ? json_encode($value) : (string)$value;
        echo "     - {$key}: " . substr($displayValue, 0, 50) . (strlen($displayValue) > 50 ? '...' : '') . "\n";
    }
    echo "\n";

    echo "🎉 Draft Episode Workflow Test Completed Successfully!\n\n";
    
    echo "📋 Workflow Summary:\n";
    echo "==================\n";
    echo "• Step 5 (Product Selection): Draft episode created with ID {$draftEpisode->id}\n";
    echo "• Step 7 (IVR Generation): Uses draft episode for field mapping (✅ {" . count($ivrData) . "} fields available)\n";
    echo "• Final Submission: Draft episode will be finalized with FHIR resources\n\n";
    
    echo "🔧 This solves the chicken-and-egg problem:\n";
    echo "   ✅ IVR can be generated BEFORE final submission\n";
    echo "   ✅ Episode ID is available for Docuseal integration\n";
    echo "   ✅ FHIR resources will be created only during final submission\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
