<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Set up Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\QuickRequestController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Authenticate as a test user
$user = User::where('email', 'provider@example.com')->first();
Auth::login($user);
echo "🔐 Authenticated as: {$user->email}\n";

// Set up the current organization for testing
$organization = \App\Models\Users\Organization\Organization::find(21); // Test Healthcare Network
if ($organization) {
    $currentOrgService = app(\App\Services\CurrentOrganization::class);
    $currentOrgService->setOrganization($organization);
    echo "🏢 Set current organization: {$organization->name}\n\n";
} else {
    echo "❌ Organization 21 not found\n\n";
}

try {
    echo "🧪 Testing Create Draft Episode API Endpoint\n";
    echo "===========================================\n\n";

    // Create a mock request
    $formData = [
        'provider_id' => 1,
        'facility_id' => 1, // Add facility_id
        'selected_products' => [
            [
                'product_id' => 1,
                'quantity' => 1,
                'size' => '2 x 2',
                'product' => [
                    'id' => 1,
                    'name' => 'Test Product',
                    'manufacturer' => 'MEDLIFE SOLUTIONS',
                    'manufacturer_id' => 5
                ]
            ]
        ],
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-01',
        'patient_gender' => 'male',
        'patient_phone' => '555-0123',
        'patient_email' => 'john.doe@example.com',
        'patient_address_line1' => '123 Test St',
        'patient_city' => 'Test City',
        'patient_state' => 'CA',
        'patient_zip' => '90210',
        'wound_type' => 'diabetic_foot_ulcer',
        'wound_location' => 'left_foot',
        'wound_size_length' => '2.5',
        'wound_size_width' => '1.8',
        'primary_insurance_name' => 'Medicare',
        'primary_member_id' => 'MED123456'
    ];

    $requestData = [
        'form_data' => $formData,
        'manufacturer_name' => 'MEDLIFE SOLUTIONS'
    ];

    // Create request object
    $request = new Request();
    $request->replace($requestData);

    // Get controller instance
    $controller = app(QuickRequestController::class);

    echo "1️⃣ Calling createDraftEpisode endpoint...\n";
    $response = $controller->createDraftEpisode($request);
    $responseData = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 200 && $responseData['success']) {
        echo "✅ Draft episode API endpoint successful!\n";
        echo "   Episode ID: {$responseData['episode_id']}\n";
        echo "   Status: {$responseData['status']}\n";
        echo "   Message: {$responseData['message']}\n\n";

        // Test the IVR submission endpoint with the created episode
        echo "2️⃣ Testing IVR submission with created episode...\n";
        
        $ivrRequest = new Request();
        $ivrRequest->replace([
            'episode_id' => $responseData['episode_id'],
            'manufacturer_name' => 'MEDLIFE SOLUTIONS'
        ]);

        $ivrResponse = $controller->createIvrSubmission($ivrRequest);
        $ivrResponseData = json_decode($ivrResponse->getContent(), true);

        if ($ivrResponse->getStatusCode() === 200 && $ivrResponseData['success']) {
            echo "✅ IVR submission API endpoint successful!\n";
            echo "   Submission ID: {$ivrResponseData['submission_id']}\n";
            echo "   Mapped fields count: {$ivrResponseData['mapped_fields_count']}\n";
            echo "   Completeness: {$ivrResponseData['completeness_percentage']}%\n";
            echo "   Message: {$ivrResponseData['message']}\n\n";
        } else {
            echo "❌ IVR submission API failed:\n";
            echo "   Status: {$ivrResponse->getStatusCode()}\n";
            echo "   Response: " . json_encode($ivrResponseData, JSON_PRETTY_PRINT) . "\n";
            
            // Debug the authorization issue
            if ($ivrResponse->getStatusCode() === 403) {
                $currentUserId = Auth::id();
                $episode = \App\Models\PatientManufacturerIVREpisode::find($responseData['episode_id']);
                $episodeCreatedBy = $episode->created_by;
                
                echo "   Debug: Current user ID: " . $currentUserId . " (type: " . gettype($currentUserId) . ")\n";
                echo "   Debug: Episode created_by: " . $episodeCreatedBy . " (type: " . gettype($episodeCreatedBy) . ")\n";
                echo "   Debug: Strict comparison: " . ($currentUserId === $episodeCreatedBy ? 'YES' : 'NO') . "\n";
                echo "   Debug: Loose comparison: " . ($currentUserId == $episodeCreatedBy ? 'YES' : 'NO') . "\n";
            }
        }

    } else {
        echo "❌ Draft episode API failed:\n";
        echo "   Status: {$response->getStatusCode()}\n";
        echo "   Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    }

    echo "🎉 API Endpoint Tests Completed!\n\n";
    echo "🔧 Ready for Frontend Integration:\n";
    echo "   • Step 5: Call /api/v1/quick-request/create-draft-episode\n";
    echo "   • Step 7: Use episode_id for Docuseal IVR generation\n";
    echo "   • Final: Submit with existing episode_id\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
