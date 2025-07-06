<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Reques         ]
    ];

    echo "✅ Form data prepared with " . count($formData) . " fields\n\n";

    // 3. Get manufacturer ID first and fix the product relationship issue
    echo "🏭 Getting manufacturer information...\n";manufacturer ID first and fix the product relationship issue
    echo "🏭 Getting manufacturer information...\n";
    $manufacturer = Manufacturer::where('name', 'MEDLIFE SOLUTIONS')->first();
    if (!$manufacturer) {
        echo "❌ Manufacturer 'MEDLIFE SOLUTIONS' not found.\n";
        exit(1);
    }
    echo "✅ Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n\n";lluminate\Support\Facades\Auth;
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
        echo "❌ Test organization not found.\n";
        exit(1);
    }

    $currentOrganization = app(CurrentOrganization::class);
    $currentOrganization->setOrganization($organization);
    echo "✅ Set current organization: {$organization->name}\n\n";

    // 2. Create comprehensive form data with all required Docuseal fields
    echo "📋 Preparing comprehensive form data...\n";
    $formData = [
        // Patient Information (Required)
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-05-15',
        'patient_gender' => 'Male',
        'patient_phone' => '555-123-4567',
        'patient_email' => 'john.doe@example.com',
        'patient_address_line1' => '123 Main St',
        'patient_city' => 'Anytown',
        'patient_state' => 'CA',
        'patient_zip' => '90210',

        // Provider Information (Required)
        'provider_first_name' => 'Dr. Sarah',
        'provider_last_name' => 'Johnson',
        'provider_npi' => '1234567890',
        'provider_email' => 'provider@example.com',
        'provider_phone' => '555-987-6543',

        // Facility Information (Required)
        'facility_name' => 'Test Medical Center',
        'facility_address' => '456 Healthcare Blvd',
        'facility_city' => 'Medville',
        'facility_state' => 'CA',
        'facility_zip' => '90211',

        // Organization Information (Required)
        'organization_name' => 'Test Healthcare Network',

        // Insurance Information (Required)
        'primary_insurance_name' => 'Blue Cross Blue Shield',
        'primary_member_id' => 'BC123456789',
        'primary_plan_type' => 'PPO',

        // Clinical Information (Required)
        'wound_type' => 'Diabetic Foot Ulcer',
        'wound_location' => 'Left Foot',
        'wound_size_length' => '2.5',
        'wound_size_width' => '1.8',
        'wound_size_depth' => '0.5',
        'graft_size_requested' => '4.5', // calculated from length * width
        'primary_diagnosis_code' => 'E11.621',
        'icd10_code_1' => 'E11.621',
        'cpt_code_1' => '15275',
        'wound_duration_weeks' => '8',

        // Required boolean fields
        'failed_conservative_treatment' => true,
        'information_accurate' => true,
        'medical_necessity_established' => true,
        'maintain_documentation' => true,

        // Product Selection
        'selected_products' => [
            [
                'product_id' => 10,
                'quantity' => 1,
                'product' => [
                    'id' => 10,
                    'name' => 'Amnio AMP',
                    'code' => 'AMNIO-AMP-001',
                    'q_code' => 'Q4162',
                    'manufacturer' => 'MedLife Solutions',
                    'manufacturer_id' => 5,
                    'docuseal_template_id' => '1233913',
                    'docuseal_order_form_template_id' => '1234279'
                ]
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
    echo "✅ Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n";
    
    // Fix the product-manufacturer relationship for testing
    $selectedProduct = [
        'id' => 10,
        'name' => 'Amnio AMP',
        'code' => 'AMNIO-AMP-001',
        'q_code' => 'Q4162',
        'manufacturer' => $manufacturer->name, // Use the actual manufacturer name
        'manufacturer_id' => $manufacturer->id, // Add the manufacturer ID
        'manufacturer_object' => $manufacturer // Add the actual manufacturer object
    ];
    
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
            'display_id' => FriendlyTagHelper::generate($formData['patient_first_name'], $formData['patient_last_name'])
        ],
        'patient_data' => [  // This key matches what prepareDocusealData looks for
            'first_name' => $formData['patient_first_name'],
            'last_name' => $formData['patient_last_name'],
            'dob' => $formData['patient_dob'],
            'gender' => $formData['patient_gender'],
            'phone' => $formData['patient_phone'],
            'email' => $formData['patient_email']
        ],
        'clinical_data' => [  // This key matches what prepareDocusealData looks for  
            'wound_type' => $formData['wound_type'],
            'wound_location' => $formData['wound_location'],
            'wound_length' => $formData['wound_size_length'],
            'wound_width' => $formData['wound_size_width'],
            'wound_depth' => $formData['wound_size_depth'],
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
        'provider_data' => [  // This key matches what prepareDocusealData looks for
            'name' => $formData['provider_first_name'] . ' ' . $formData['provider_last_name'],
            'first_name' => $formData['provider_first_name'],
            'last_name' => $formData['provider_last_name'],
            'npi' => $formData['provider_npi'],
            'email' => $formData['provider_email'],
            'phone' => $formData['provider_phone']
        ],
        'facility_data' => [  // This key matches what prepareDocusealData looks for
            'name' => $formData['facility_name'],
            'address' => $formData['facility_address'],
            'city' => $formData['facility_city'],
            'state' => $formData['facility_state'],
            'zip_code' => $formData['facility_zip']
        ],
        'organization_data' => [  // This key matches what prepareDocusealData looks for
            'name' => $formData['organization_name']
        ],
        'insurance' => [  // This gets transformed by transformRequestDataForInsuranceHandler
            [
                'policy_type' => 'primary',
                'insurance_name' => $formData['primary_insurance_name'],
                'member_id' => $formData['primary_member_id'],
                'plan_type' => $formData['primary_plan_type']
            ]
        ],
        'order_details' => [
            'selected_products' => $formData['selected_products']
        ]
    ];

    try {
        $episode = $orchestrator->createDraftEpisode($episodeData);
        echo "✅ Draft episode created successfully!\n";
        echo "   Episode ID: {$episode->id}\n";
        echo "   Status: {$episode->status}\n\n";

        // 4. Test IVR submission using orchestrator data directly
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

        // Create Docuseal submission
        $result = $docusealService->createSubmissionFromOrchestratorData(
            $episode,
            $comprehensiveData,
            'MEDLIFE SOLUTIONS'
        );

        if (!$result['success']) {
            echo "❌ IVR submission failed:\n";
            echo "   Error: {$result['error']}\n\n";
        } else {
            echo "✅ IVR submission created successfully!\n";
            echo "   Submission ID: {$result['submission']['id']}\n";
            echo "   Slug: " . ($result['submission']['slug'] ?? 'Not provided') . "\n";
            echo "   Status: " . ($result['submission']['status'] ?? 'pending') . "\n";
            echo "   Mapped Fields: " . count($result['mapped_data']) . "\n\n";

            if (isset($result['submission']['slug'])) {
                echo "🌐 DocuSeal Embed URL: https://docuseal.com/s/{$result['submission']['slug']}\n\n";
            }
        }

        echo "🎯 Summary:\n";
        echo "   • Episode ID: {$episode->id}\n";
        echo "   • Form Data Fields: " . count($formData) . "\n";
        echo "   • Orchestrator Fields: " . count($comprehensiveData) . "\n";
        echo "   • IVR Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "   • Ready for Frontend: " . ($result['success'] ? 'Yes' : 'No') . "\n\n";

    } catch (Exception $e) {
        echo "❌ Episode creation failed:\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Test failed with exception:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "🎉 Complete DocuSeal Workflow Test Completed!\n";
