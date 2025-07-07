<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Authenticate as a test user for the script
$user = \App\Models\User::first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
    echo "Authenticated as: " . $user->full_name . " (" . $user->email . ")\n";
}

echo "=== Testing Field Mapping Completeness for BioWound Solutions ===\n\n";

try {
    // Create a comprehensive test episode with all possible data
    $episode = new PatientManufacturerIVREpisode();
    $episode->id = 'test-complete-' . uniqid();
    $episode->patient_id = 'test-patient-123';
    $episode->manufacturer_id = 3;
    $episode->manufacturer_name = 'BioWound Solutions';
    $episode->created_by = 1;
    $episode->metadata = [
        'patient_data' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => '1980-01-01',
            'gender' => 'Male',
            'phone' => '555-123-4567',
            'email' => 'john.doe@example.com',
            'display_id' => 'TEST-123',
            'address' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75201'
        ],
        'provider_data' => [
            'name' => 'Dr. Jane Smith',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'npi' => '1234567890',
            'email' => 'dr.smith@hospital.com',
            'phone' => '555-987-6543',
            'specialty' => 'Wound Care Specialist',
            'credentials' => 'MD, CWSP',
            'license_number' => 'TX12345',
            'license_state' => 'TX',
            'dea_number' => 'BS1234567',
            'ptan' => 'A12345',
            'tax_id' => '98-7654321',
            'practice_name' => 'Dallas Wound Care Center'
        ],
        'facility_data' => [
            'name' => 'Dallas Medical Center',
            'address' => '456 Hospital Blvd',
            'address_line1' => '456 Hospital Blvd',
            'address_line2' => 'Suite 200',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip_code' => '75202',
            'phone' => '555-111-2222',
            'fax' => '555-111-2223',
            'email' => 'info@dallasmedical.com',
            'npi' => '9876543210',
            'group_npi' => '9876543211',
            'ptan' => 'B54321',
            'tax_id' => '12-3456789',
            'facility_type' => 'Hospital',
            'place_of_service' => '21'
        ],
        'organization_data' => [
            'name' => 'Dallas Healthcare System',
            'tax_id' => '11-1111111',
            'address' => '789 Corporate Dr',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip_code' => '75203',
            'phone' => '555-222-3333',
            'email' => 'admin@dallashealthcare.com',
            'type' => 'Healthcare System'
        ],
        'insurance_data' => [
            'primary_name' => 'Medicare',
            'primary_member_id' => 'M123456789A',
            'primary_group_number' => 'GRP001',
            'primary_phone' => '1-800-MEDICARE',
            'has_secondary_insurance' => true,
            'secondary_insurance_name' => 'AARP Supplement',
            'secondary_member_id' => 'AARP987654'
        ],
        'clinical_data' => [
            'wound_type' => 'Diabetic Foot Ulcer',
            'wound_location' => 'Left foot, plantar surface',
            'wound_length' => '3.5',
            'wound_width' => '2.8',
            'wound_depth' => '0.5',
            'wound_duration_weeks' => '8',
            'primary_diagnosis_code' => 'E11.621',
            'secondary_diagnosis_code' => 'L97.521',
            'icd10_code_1' => 'E11.621',
            'cpt_code_1' => '15275',
            'application_cpt_codes' => ['15275', '97597'],
            'failed_conservative_treatment' => true,
            'information_accurate' => true,
            'medical_necessity_established' => true,
            'maintain_documentation' => true,
            'global_period_status' => false,
            'procedure_date' => '2024-01-15',
            'previous_therapies' => 'Standard dressings, offloading, debridement',
            'comorbidities' => 'Type 2 Diabetes, Peripheral Neuropathy',
            'graft_size_requested' => '4x4'
        ],
        'order_details' => [
            'products' => [
                [
                    'id' => 12,
                    'product' => [
                        'code' => 'Q4239',
                        'name' => 'Amnio-Maxx',
                        'manufacturer' => 'BioWound Solutions'
                    ],
                    'quantity' => 1,
                    'size' => '4x4'
                ]
            ],
            'expected_service_date' => '2024-01-20',
            'place_of_service' => '11'
        ]
    ];
    
    // Get orchestrator data
    echo "1. Testing Orchestrator Data Preparation...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    $orchestratorData = $orchestrator->prepareDocusealData($episode);
    
    echo "   Fields prepared by orchestrator: " . count($orchestratorData) . "\n";
    
    // Debug: Check if specific fields are present
    echo "\n   Debug - Checking for key fields in orchestrator data:\n";
    $checkFields = ['contact_name', 'sales_rep', 'rep_email', 'territory', 'selected_products', 'order_date', 'product_description_1'];
    foreach ($checkFields as $field) {
        $exists = isset($orchestratorData[$field]);
        if ($exists) {
            $value = $orchestratorData[$field];
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $value = substr($value, 0, 50);
            echo "   - $field: ✓ '$value'\n";
        } else {
            echo "   - $field: ✗ NOT SET\n";
        }
    }
    
    // Test field mapping
    echo "\n2. Testing Field Mapping...\n";
    $mappingService = app(UnifiedFieldMappingService::class);
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $orchestratorData
    );
    
    echo "   Fields mapped: " . count($mappingResult['data']) . "\n";
    echo "   Validation: " . ($mappingResult['validation']['valid'] ? 'VALID' : 'INVALID') . "\n";
    echo "   Completeness: " . $mappingResult['completeness']['percentage'] . "%\n";
    
    // Get BioWound config to see all expected fields
    $config = $mappingService->getManufacturerConfig('BioWound Solutions');
    $requiredFields = [];
    $missingRequired = [];
    $missingOptional = [];
    
    foreach ($config['fields'] as $field => $fieldConfig) {
        if ($fieldConfig['required'] ?? false) {
            $requiredFields[] = $field;
            if (empty($mappingResult['data'][$field])) {
                $missingRequired[] = $field;
            }
        } else {
            if (empty($mappingResult['data'][$field])) {
                $missingOptional[] = $field;
            }
        }
    }
    
    echo "\n3. Field Analysis:\n";
    echo "   Total fields in config: " . count($config['fields']) . "\n";
    echo "   Required fields: " . count($requiredFields) . "\n";
    echo "   Missing required fields: " . count($missingRequired) . "\n";
    echo "   Missing optional fields: " . count($missingOptional) . "\n";
    
    if (count($missingRequired) > 0) {
        echo "\n   Missing REQUIRED fields:\n";
        foreach ($missingRequired as $field) {
            $fieldConfig = $config['fields'][$field];
            echo "   - $field (looking for: " . $fieldConfig['source'] . ")\n";
            
            // Check if any of the source fields exist in orchestrator data
            $sources = explode(' || ', $fieldConfig['source']);
            $foundSources = [];
            foreach ($sources as $source) {
                if (isset($orchestratorData[$source])) {
                    $foundSources[] = "$source = '" . $orchestratorData[$source] . "'";
                }
            }
            if (!empty($foundSources)) {
                echo "     Found in orchestrator data: " . implode(', ', $foundSources) . "\n";
            } else {
                echo "     NOT FOUND in orchestrator data\n";
            }
        }
    }
    
    // Show what fields ARE being mapped
    echo "\n4. Successfully Mapped Fields:\n";
    $mappedFields = array_filter($mappingResult['data']);
    foreach ($mappedFields as $field => $value) {
        echo "   - $field = " . (is_bool($value) ? ($value ? 'true' : 'false') : "'" . substr($value, 0, 50) . "'") . "\n";
    }
    
    // Check specific missing fields mentioned by user
    echo "\n5. Checking Key Fields:\n";
    $keyFields = [
        'contact_name' => 'Contact Name (for IVR form)',
        'contact_email' => 'Contact Email',
        'contact_phone' => 'Contact Phone',
        'sales_rep' => 'Sales Representative',
        'rep_email' => 'Rep Email',
        'territory' => 'Territory',
        'patient_address' => 'Patient Address',
        'city_state_zip' => 'City/State/Zip',
        'primary_icd10' => 'Primary ICD10',
        'wound_duration' => 'Wound Duration',
        'post_debridement_size' => 'Post Debridement Size',
        'date' => 'Form Date'
    ];
    
    foreach ($keyFields as $field => $description) {
        $isMapped = !empty($mappingResult['data'][$field]);
        $value = $mappingResult['data'][$field] ?? 'NOT SET';
        echo "   - $description ($field): " . ($isMapped ? "✓ '$value'" : "✗ MISSING") . "\n";
        
        if (!$isMapped && isset($config['fields'][$field])) {
            $fieldConfig = $config['fields'][$field];
            echo "     Looking for: " . $fieldConfig['source'] . "\n";
            
            // Special handling for computed fields
            if ($fieldConfig['source'] === 'computed') {
                echo "     Computation: " . $fieldConfig['computation'] . "\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";