<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\FhirService;

// Bootstrap Laravel application
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Enable debug mode and log init
config(['app.debug' => true]);
Log::info('Starting FHIR test...', [
    'config' => [
        'services.azure.fhir_endpoint' => config('services.azure.fhir_endpoint'),
        'services.azure.fhir.base_url' => config('services.azure.fhir.base_url'),
        'features.fhir.enabled' => config('features.fhir.enabled'),
        'features.fhir.service_enabled' => config('features.fhir.service_enabled')
    ],
    'env' => [
        'AZURE_FHIR_ENDPOINT' => env('AZURE_FHIR_ENDPOINT'),
        'AZURE_FHIR_BASE_URL' => env('AZURE_FHIR_BASE_URL')
    ]
]);

// Test data for a QuickRequest
$testData = [
    'order_details' => [
        'order_type' => 'initial',
        'delivery_preference' => 'ship_to_facility',
        'shipping_address' => [
            'address_line1' => '456 Clinic Ave',
            'city' => 'Test City',
            'state' => 'TX',
            'postalCode' => '12345'
        ],
        'product_list' => [
            [
                'product_id' => 1,
                'quantity' => 1,
                'duration_weeks' => 4
            ]
        ]
    ],
    'patient' => [
        'first_name' => 'John',
        'display_id' => 'TEST001',
        'last_name' => 'Doe',
        'middle_name' => 'Test',
        'dob' => '1990-01-01',
        'gender' => 'male',
        'ssn' => '123-45-6789',
        'address' => [
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TX',
            'postalCode' => '12345'
        ],
        'phone' => '555-123-4567',
        'email' => 'test@example.com'
    ],
    'provider' => [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'npi' => '1234567890',
        'email' => 'provider@example.com',
        'phone' => '555-987-6543'
    ],
    'facility' => [
        'name' => 'Test Clinic',
        'npi' => '0987654321',
        'address' => [
            'address_line1' => '456 Clinic Ave',
            'city' => 'Test City',
            'state' => 'TX',
            'postalCode' => '12345'
        ]
    ],
    'clinical' => [
        'diagnosis' => [
            'primary' => 'L89.004',
            'secondary' => ['E11.9']
        ],
        'woundLocation' => 'Sacrum',
        'woundMeasurements' => [
            'length' => 2.5,
            'width' => 3.0,
            'depth' => 1.0
        ],
        'woundDescription' => 'Stage 4 pressure ulcer'
    ],
    'insurance' => [
        'primary' => [
            'payer_name' => 'Medicare',
            'member_id' => 'MED123456',
            'group_number' => 'GRP789',
            'policy_type' => 'Medicare Part B',
            'effective_date' => '2025-01-01',
            'expiration_date' => '2025-12-31'
        ]
    ],
    'manufacturer_id' => 1, // Assuming this is a valid manufacturer ID in your database
];

try {
    echo "Starting QuickRequest FHIR test...\n";

    // First verify FHIR service configuration
    $fhirService = new FhirService();
    if (!$fhirService->isAzureConfigured()) {
        throw new RuntimeException("Azure FHIR is not properly configured!");
    }

    // Check feature flags
    $featureFlags = [
        'fhir.service_enabled',
        'fhir.patient_handler_enabled',
        'fhir.provider_handler_enabled',
        'fhir.insurance_handler_enabled',
        'fhir.clinical_handler_enabled',
        'fhir.order_handler_enabled'
    ];

    foreach ($featureFlags as $flag) {
        $enabled = config("features.{$flag}");
        echo "Feature flag {$flag}: " . ($enabled ? "Enabled" : "Disabled") . "\n";
        if (!$enabled) {
            echo "Warning: {$flag} is disabled, FHIR resources won't be created\n";
        }
    }

    // Get the orchestrator from the container
    $orchestrator = App::make(QuickRequestOrchestrator::class);

    echo "Creating new episode with test data...\n";
    
    // Start database transaction
    DB::beginTransaction();
    
    try {
        // Create the episode
        $episode = $orchestrator->startEpisode($testData);
        
        echo "Episode created successfully!\n";
        echo "Episode ID: " . $episode->id . "\n";
        echo "Patient FHIR ID: " . $episode->patient_fhir_id . "\n";
        
        // Get metadata to verify FHIR resources
        $metadata = $episode->metadata;
        echo "\nFHIR Resources created:\n";
        echo "- Practitioner: " . ($metadata['practitioner_fhir_id'] ?? 'Not created') . "\n";
        echo "- Organization: " . ($metadata['organization_fhir_id'] ?? 'Not created') . "\n";
        echo "- EpisodeOfCare: " . ($metadata['episode_of_care_fhir_id'] ?? 'Not created') . "\n";
        echo "- Coverage: " . ($metadata['coverage_fhir_id'] ?? 'Not created') . "\n";
        
        // Verify FHIR resources exist by trying to fetch them
        echo "\nVerifying FHIR resources...\n";
        
        $resourcesToCheck = [
            'Patient' => $episode->patient_fhir_id,
            'Practitioner' => $metadata['practitioner_fhir_id'] ?? null,
            'Organization' => $metadata['organization_fhir_id'] ?? null,
            'EpisodeOfCare' => $metadata['episode_of_care_fhir_id'] ?? null,
            'Coverage' => $metadata['coverage_fhir_id'] ?? null
        ];
        
        foreach ($resourcesToCheck as $type => $id) {
            if (!$id) {
                echo "- {$type}: No ID available to check\n";
                continue;
            }
            
            try {
                $resource = $fhirService->read($type, $id);
                echo "- {$type}: ✓ Found ({$id})\n";
            } catch (Exception $e) {
                echo "- {$type}: ✗ Not found ({$id})\n";
                echo "  Error: " . $e->getMessage() . "\n";
            }
        }
        
        // If we got here without exceptions, commit the transaction
        DB::commit();
        echo "\nTest completed successfully!\n";
        
    } catch (Exception $e) {
        // If anything fails, roll back the transaction
        DB::rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
