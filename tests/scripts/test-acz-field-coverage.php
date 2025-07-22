<?php

/**
 * Test script to verify 90%+ field coverage for ACZ & Associates DocuSeal forms
 * 
 * This script simulates a complete Quick Request with all required data
 * to ensure we achieve 90%+ field mapping coverage.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UnifiedFieldMappingService;
use App\Services\DataExtractionService;
use App\Services\DocusealService;
use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ANSI color codes
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$blue}=== ACZ & Associates Field Coverage Test ==={$reset}\n\n";

try {
    // Get services
    $mappingService = app(UnifiedFieldMappingService::class);
    $dataExtractionService = app(DataExtractionService::class);
    $docusealService = app(DocusealService::class);
    
    // Find ACZ manufacturer
    $manufacturer = Manufacturer::where('name', 'LIKE', '%ACZ%')->first();
    if (!$manufacturer) {
        throw new Exception("ACZ & Associates manufacturer not found");
    }
    
    echo "Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n";
    
    // Get manufacturer config
    $config = $mappingService->getManufacturerConfig($manufacturer->name, 'IVR');
    if (!$config) {
        throw new Exception("Manufacturer config not found");
    }
    
    $totalFields = count($config['docuseal_field_names'] ?? []);
    echo "Total DocuSeal fields configured: {$totalFields}\n\n";
    
    // Create comprehensive test data
    $testData = [
        // Basic Information
        'sales_rep_name' => 'John Sales Representative',
        'iso_if_applicable' => 'ISO123456',
        'additional_emails' => 'notifications@example.com',
        
        // Provider Information
        'provider_id' => 1,
        'physician_name' => 'Dr. Sarah Johnson',
        'physician_npi' => '1234567890',
        'physician_specialty' => 'Wound Care Specialist',
        'physician_tax_id' => '98-7654321',
        'physician_ptan' => 'PTAN123456',
        'physician_medicaid' => 'MED987654',
        'physician_phone' => '(555) 123-4567',
        'physician_fax' => '(555) 123-4568',
        'physician_organization' => 'Advanced Wound Care Center',
        
        // Facility Information
        'facility_id' => 1,
        'facility_npi' => '0987654321',
        'facility_tax_id' => '12-3456789',
        'facility_name' => 'Main Medical Center',
        'facility_ptan' => 'FPTAN789012',
        'facility_address' => '123 Healthcare Blvd',
        'facility_medicaid' => 'FMED123456',
        'facility_city_state_zip' => 'Houston, TX 77001',
        'facility_phone' => '(555) 987-6543',
        'facility_contact_name' => 'Mary Administrator',
        'facility_fax' => '(555) 987-6544',
        'facility_contact_info' => 'mary@mainmedical.com',
        'facility_organization' => 'Main Healthcare System',
        
        // Place of Service
        'place_of_service' => '11', // Office
        'pos_other_specify' => '',
        
        // Patient Information
        'patient_first_name' => 'Robert',
        'patient_last_name' => 'Thompson',
        'patient_name' => 'Robert Thompson',
        'patient_dob' => '1955-03-15',
        'patient_address' => '456 Oak Street',
        'patient_city' => 'Houston',
        'patient_state' => 'TX',
        'patient_zip' => '77002',
        'patient_city_state_zip' => 'Houston, TX 77002',
        'patient_phone' => '(555) 456-7890',
        'patient_email' => 'robert.thompson@email.com',
        'patient_caregiver_info' => 'Wife: Susan Thompson (555) 456-7891',
        
        // Insurance Information
        'primary_insurance_name' => 'Medicare',
        'secondary_insurance_name' => 'AARP Supplement',
        'primary_policy_number' => '1EG4-TE5-MK72',
        'secondary_policy_number' => 'AARP123456',
        'primary_payer_phone' => '(800) 633-4227',
        'secondary_payer_phone' => '(800) 523-5800',
        
        // Network Status
        'primary_physician_network_status' => 'in_network',
        'secondary_physician_network_status' => 'out_of_network',
        
        // Authorization Questions
        'prior_auth_permission' => true,
        'hospice_status' => false,
        'part_a_status' => false,
        'global_period_status' => false,
        
        // Clinical Information
        'wound_location_details' => 'Legs/Arms/Trunk < 100 SQ CM',
        'primary_diagnosis_code' => 'L97.511',
        'secondary_diagnosis_code' => 'E11.622',
        'wound_size_total' => '4.5 cm x 3.2 cm x 0.5 cm',
        'medical_history' => 'Type 2 diabetes, peripheral vascular disease, hypertension. Non-healing ulcer present for 8 weeks.',
        
        // Product Information
        'selected_products' => [
            [
                'product_id' => 1,
                'product' => [
                    'q_code' => 'Q4316',
                    'name' => 'ACZ Wound Care Product'
                ]
            ]
        ],
        
        // Context
        'manufacturer_id' => $manufacturer->id,
        'episode_id' => null // No episode for this test
    ];
    
    echo "{$yellow}=== Extracting Data ==={$reset}\n";
    
    // Extract data using DataExtractionService
    $extractedData = $dataExtractionService->extractData($testData);
    echo "Extracted fields: " . count($extractedData) . "\n";
    
    echo "\n{$yellow}=== Mapping Fields ==={$reset}\n";
    
    // Map fields using manufacturer config
    $mappingResult = $mappingService->mapEpisodeToDocuSeal(
        null, // No episode
        $manufacturer->name,
        $config['docuseal_template_id'] ?? '852440', // Template ID
        $testData, // Additional data
        'test@example.com', // Submitter email
        false // Don't use dynamic mapping for this test
    );
    
    $mappedData = $mappingResult['data'] ?? [];
    echo "Mapped fields: " . count($mappedData) . "\n";
    
    echo "\n{$yellow}=== Converting to DocuSeal Format ==={$reset}\n";
    
    // Convert to DocuSeal fields
    $docusealFields = $mappingService->convertToDocusealFields($mappedData, $config, 'IVR');
    echo "DocuSeal fields generated: " . count($docusealFields) . "\n";
    
    // Analyze field coverage
    echo "\n{$yellow}=== Field Coverage Analysis ==={$reset}\n";
    
    $mappedFieldNames = array_column($docusealFields, 'name');
    $expectedFields = $config['docuseal_field_names'];
    $mappedCount = 0;
    $missingFields = [];
    
    foreach ($expectedFields as $canonical => $docusealName) {
        if (in_array($docusealName, $mappedFieldNames)) {
            $mappedCount++;
            echo "{$green}✓{$reset} {$docusealName}\n";
        } else {
            $missingFields[] = $docusealName;
            echo "{$red}✗{$reset} {$docusealName} (canonical: {$canonical})\n";
        }
    }
    
    $coverage = ($mappedCount / $totalFields) * 100;
    
    echo "\n{$yellow}=== Results ==={$reset}\n";
    echo "Total fields: {$totalFields}\n";
    echo "Mapped fields: {$mappedCount}\n";
    echo "Missing fields: " . count($missingFields) . "\n";
    echo "Field coverage: " . number_format($coverage, 2) . "%\n";
    
    if ($coverage >= 90) {
        echo "{$green}✓ SUCCESS: Achieved {$coverage}% field coverage!{$reset}\n";
    } else {
        echo "{$red}✗ FAILED: Only achieved {$coverage}% field coverage (target: 90%){$reset}\n";
        echo "\nMissing fields:\n";
        foreach ($missingFields as $field) {
            echo "  - {$field}\n";
        }
    }
    
    // Test actual DocuSeal submission (optional)
    if (isset($argv[1]) && $argv[1] === '--test-submission') {
        echo "\n{$yellow}=== Testing DocuSeal Submission ==={$reset}\n";
        
        $templateId = $config['docuseal_template_id'] ?? null;
        if (!$templateId) {
            echo "{$red}No template ID configured{$reset}\n";
        } else {
            $result = $docusealService->createSubmissionForQuickRequest(
                $templateId,
                'test@example.com',
                'submitter@example.com',
                'Test Submitter',
                $mappedData,
                null // No episode
            );
            
            if ($result['success']) {
                echo "{$green}✓ DocuSeal submission created successfully!{$reset}\n";
                echo "Submission ID: " . ($result['data']['submission_id'] ?? 'N/A') . "\n";
                echo "Slug: " . ($result['data']['slug'] ?? 'N/A') . "\n";
            } else {
                echo "{$red}✗ DocuSeal submission failed: {$result['error']}{$reset}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "{$red}Error: {$e->getMessage()}{$reset}\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n{$blue}=== Test Complete ==={$reset}\n"; 