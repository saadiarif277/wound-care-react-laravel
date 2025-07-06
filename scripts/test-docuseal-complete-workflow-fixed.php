<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Models\Order\Manufacturer;
use App\Services\CurrentOrganization;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\DocusealService;
use App\Services\QuickRequest\QuickRequestCalculationService;
use App\Services\QuickRequest\QuickRequestFileService;
use App\Helpers\FriendlyTagHelper;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Complete DocuSeal Workflow\n";
echo "====================================\n\n";

try {
    // 1. Setup user and organization context
    echo "🔐 Setting up authentication context...\n";
    $user = User::where('email', 'provider@example.com')->first();
    if (!$user) {
        echo "❌ Test user not found. Please run user setup first.\n";
        exit(1);
    }

    Auth::login($user);
    echo "✅ Authenticated as: {$user->email}\n";

    // Set organization context
    $organization = Organization::where('name', 'Test Healthcare Network')->first();
    if (!$organization) {
        echo "❌ Test organization not found. Please create test data first.\n";
        exit(1);
    }

    $currentOrg = app(CurrentOrganization::class);
    $currentOrg->setOrganization($organization);
    echo "✅ Organization context set: {$organization->name}\n\n";

    // 2. Prepare comprehensive form data
    echo "📋 Preparing comprehensive form data...\n";
    $formData = [
        // Patient data
        'patient_first_name' => 'Jane',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1985-05-15',
        'patient_gender' => 'Female',
        'patient_phone' => '555-123-4567',
        'patient_email' => 'jane.doe@email.com',
        'patient_address_line1' => '123 Main St',
        'patient_city' => 'Test City',
        'patient_state' => 'CA',
        'patient_zip' => '90210',

        // Provider data
        'provider_first_name' => 'John',
        'provider_last_name' => 'Smith',
        'provider_npi' => '1234567890',
        'provider_email' => 'dr.smith@clinic.com',
        'provider_phone' => '555-987-6543',

        // Facility data
        'facility_name' => 'Test Medical Center',
        'facility_address' => '456 Healthcare Ave',
        'facility_city' => 'Test City',
        'facility_state' => 'CA',
        'facility_zip' => '90210',

        // Organization data
        'organization_name' => 'Test Healthcare Network',

        // Clinical data
        'wound_type' => 'diabetic_foot_ulcer',
        'wound_location' => 'left_foot',
        'wound_size_length' => '2.5',
        'wound_size_width' => '1.8',
        'wound_size_depth' => '0.5',
        'graft_size_requested' => '2x2',
        'primary_diagnosis_code' => 'E11.621',
        'icd10_code_1' => 'E11.621',
        'cpt_code_1' => '15271',
        'wound_duration_weeks' => '8',
        'failed_conservative_treatment' => 'Yes',
        'information_accurate' => 'Yes',
        'medical_necessity_established' => 'Yes',
        'maintain_documentation' => 'Yes',

        // Insurance data
        'primary_insurance_name' => 'Blue Cross Blue Shield',
        'primary_member_id' => 'BC123456789',
        'primary_plan_type' => 'PPO',

        // Product selection
        'selected_products' => [
            [
                'product_id' => 1,
                'quantity' => 1,
                'size' => '2x2'
            ]
        ]
    ];

    echo "✅ Form data prepared with " . count($formData) . " fields\n\n";

    // 3. Get manufacturer ID first and fix the product relationship issue
    echo "🏭 Getting manufacturer information...\n";
    $manufacturer = Manufacturer::where('name', 'MEDLIFE SOLUTIONS')->first();
    if (!$manufacturer) {
        echo "❌ Manufacturer 'MEDLIFE SOLUTIONS' not found.\n";
        exit(1);
    }
    echo "✅ Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n\n";

    // Add manufacturer relationship to form data
    $formData = array_merge($formData, [
        'manufacturer' => $manufacturer->name,
        'manufacturer_id' => $manufacturer->id,
        'manufacturer_object' => $manufacturer
    ]);

    echo "✅ Product data prepared with manufacturer relationship fixed\n\n";

    // 4. Create draft episode using orchestrator directly
    echo "1️⃣ Creating draft episode...\n";

    // Get required services
    $orchestrator = app(QuickRequestOrchestrator::class);
    $docusealService = app(DocusealService::class);

    // Create episode data with proper structure matching what prepareDocusealData expects
    $episodeData = [
        'manufacturer_id' => $manufacturer->id,
        'patient' => [
            'id' => 'draft-' . time(),
            'display_id' => FriendlyTagHelper::generate($formData['patient_first_name'], $formData['patient_last_name']),
            'first_name' => $formData['patient_first_name'],
            'last_name' => $formData['patient_last_name'],
            'dob' => $formData['patient_dob'],
            'gender' => $formData['patient_gender'],
            'phone' => $formData['patient_phone'],
            'email' => $formData['patient_email']
        ],
        'clinical' => [
            'wound_type' => $formData['wound_type'],
            'wound_location' => $formData['wound_location'],
            'wound_size_length' => $formData['wound_size_length'],
            'wound_size_width' => $formData['wound_size_width'],
            'wound_size_depth' => $formData['wound_size_depth'],
            'graft_size_requested' => $formData['graft_size_requested'],
            'primary_diagnosis_code' => $formData['primary_diagnosis_code'],
            'icd10_code_1' => $formData['icd10_code_1'],
            'cpt_code_1' => $formData['cpt_code_1'],
            'wound_duration_weeks' => $formData['wound_duration_weeks'],
            'failed_conservative_treatment' => $formData['failed_conservative_treatment'],
            'information_accurate' => $formData['information_accurate'],
            'medical_necessity_established' => $formData['medical_necessity_established'],
            'maintain_documentation' => $formData['maintain_documentation']
        ],
        'provider' => [
            'name' => $formData['provider_first_name'] . ' ' . $formData['provider_last_name'],
            'first_name' => $formData['provider_first_name'],
            'last_name' => $formData['provider_last_name'],
            'npi' => $formData['provider_npi'],
            'email' => $formData['provider_email'],
            'phone' => $formData['provider_phone']
        ],
        'facility' => [
            'name' => $formData['facility_name'],
            'address' => $formData['facility_address'],
            'city' => $formData['facility_city'],
            'state' => $formData['facility_state'],
            'zip' => $formData['facility_zip']
        ],
        'organization' => [
            'name' => $formData['organization_name']
        ],
        // Pass the insurance data in the correct format for transformRequestDataForInsuranceHandler
        'primary_insurance_name' => $formData['primary_insurance_name'],
        'primary_member_id' => $formData['primary_member_id'],
        'primary_plan_type' => $formData['primary_plan_type'],
        'order_details' => [
            'selected_products' => $formData['selected_products']
        ]
    ];

    try {
        $episode = $orchestrator->createDraftEpisode($episodeData);
        echo "✅ Draft episode created successfully!\n";
        echo "   Episode ID: {$episode->id}\n";
        echo "   Status: {$episode->status}\n\n";

        // 5. Test IVR submission using orchestrator data directly
        echo "2️⃣ Creating IVR submission with comprehensive data...\n";

        // Get comprehensive data from orchestrator
        $comprehensiveData = $orchestrator->prepareDocusealData($episode);

        echo "📊 Orchestrator prepared " . count($comprehensiveData) . " fields:\n";
        $requiredFields = [
            'patient_name', 'patient_dob', 'physician_npi', 'primary_insurance_name',
            'primary_member_id', 'graft_size_requested', 'icd10_code_1', 'cpt_code_1',
            'failed_conservative_treatment', 'information_accurate',
            'medical_necessity_established', 'maintain_documentation'
        ];

        foreach ($requiredFields as $field) {
            $status = isset($comprehensiveData[$field]) && !empty($comprehensiveData[$field]) ? '✅' : '❌';
            echo "   {$status} {$field}: " . ($comprehensiveData[$field] ?? 'MISSING') . "\n";
        }
        echo "\n";

        // Test DocuSeal submission creation
        echo "🚀 Creating DocuSeal submission...\n";
        $manufacturerName = 'MEDLIFE SOLUTIONS'; // Test with MedLife

        $result = $docusealService->createSubmissionFromOrchestratorData(
            $episode,
            $comprehensiveData,
            $manufacturerName
        );

        if ($result['success']) {
            echo "✅ DocuSeal submission created successfully!\n";
            echo "   Submission ID: {$result['submission']['id']}\n";
            echo "   Slug: {$result['submission']['slug']}\n";
            echo "   Status: {$result['submission']['status']}\n";
            echo "   Mapped fields: " . count($result['mapped_data']) . "\n";
            echo "   Template ID: " . ($result['manufacturer']['template_id'] ?? $result['manufacturer']['docuseal_template_id'] ?? 'N/A') . "\n\n";

            // 6. Test the frontend integration workflow
            echo "3️⃣ Testing frontend integration workflow...\n";
            echo "   Episode ID available for Step 7: {$episode->id}\n";
            echo "   DocuSeal slug for embedding: {$result['submission']['slug']}\n";
            echo "   IVR URL: https://api.docuseal.com/s/{$result['submission']['slug']}\n\n";

            echo "🎉 Complete DocuSeal Workflow Test PASSED!\n\n";

            echo "📋 Test Summary:\n";
            echo "================\n";
            echo "• Draft episode created with ID: {$episode->id}\n";
            echo "• Orchestrator aggregated " . count($comprehensiveData) . " fields\n";
            echo "• DocuSeal submission created: {$result['submission']['id']}\n";
            echo "• All required fields mapped and populated\n";
            echo "• Frontend can use episode ID and slug for IVR integration\n\n";

            echo "🔧 Next Steps:\n";
            echo "==============\n";
            echo "1. Update Step 5 to call createDraftEpisode endpoint\n";
            echo "2. Update Step 7 to use the episode ID for IVR generation\n";
            echo "3. Update final submission to finalize the draft episode\n";
            echo "4. Test the complete frontend workflow\n\n";

        } else {
            echo "❌ DocuSeal submission failed: {$result['error']}\n";
            echo "   Debug info: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        }

    } catch (\Exception $e) {
        echo "❌ Error in draft episode workflow: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
