<?php

/**
 * Test script to verify 100% field completion for Advanced Solution IVR template
 * Based on actual DocuSeal template structure (ID: 1199885)
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DocusealService;

echo "=== Advanced Solution IVR 100% Completion Test ===\n\n";

// Complete test data for all fields in the Advanced Solution IVR template
$testData = [
    // Basic Information
    'sales_rep_name' => 'John Smith',
    'place_of_service' => 'other', // Changed to 'other' to trigger conditional field
    'place_of_service_other' => 'Home Health Agency',
    'medicare_mac' => 'NOVITAS',

    // Facility Information
    'facility_name' => 'Advanced Wound Care Center',
    'facility_address_line1' => '123 Medical Plaza Dr, Suite 100',
    'facility_npi' => '1234567890',
    'facility_contact_name' => 'Dr. Sarah Johnson',
    'facility_tin' => '12-3456789',
    'facility_phone' => '555-123-4567',
    'facility_ptan' => '123456789',
    'facility_fax' => '555-123-4568',

    // Physician Information
    'provider_name' => 'Dr. Michael Chen',
    'provider_fax' => '555-987-6543',
    'provider_address_line1' => '456 Healthcare Blvd, Suite 200',
    'provider_npi' => '9876543210',
    'provider_phone' => '555-987-6542',
    'provider_tin' => '98-7654321',

    // Patient Information
    'patient_first_name' => 'Robert',
    'patient_last_name' => 'Williams',
    'patient_phone' => '555-555-1234',
    'patient_address_line1' => '789 Oak Street, Apt 5B',
    'ok_to_contact_patient' => true,
    'patient_dob' => '1985-03-15',

    // Primary Insurance Information
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_subscriber_name' => 'Robert Williams',
    'primary_policy_number' => 'BCBS123456789',
    'primary_subscriber_dob' => '1985-03-15',
    'primary_plan_type' => 'other', // Changed to 'other' to trigger conditional field
    'primary_plan_type_other' => 'EPO',
    'primary_insurance_phone' => '1-800-555-1234',
    'physician_status_primary' => 'in_network',
    'primary_in_network_not_sure' => 'Not sure about network status',

    // Secondary Insurance Information
    'secondary_insurance_name' => 'Medicare',
    'secondary_subscriber_name' => 'Robert Williams',
    'secondary_policy_number' => '1AB2C3D4E5',
    'secondary_subscriber_dob' => '1985-03-15',
    'secondary_plan_type' => 'other', // Changed to 'other' to trigger conditional field
    'secondary_plan_type_other' => 'POS',
    'secondary_insurance_phone' => '1-800-MEDICARE',
    'physician_status_secondary' => 'in_network',
    'secondary_in_network_not_sure' => 'Need to verify',

    // Wound Information
    'wound_type' => 'other', // Changed to 'other' to trigger conditional field
    'wound_type_other' => 'Surgical Site Infection',
    'wound_size_cm2' => '25.5',
    'cpt_codes' => '97597, 97602',
    'date_of_service' => '2025-01-15',
    'icd10_diagnosis_codes' => 'E11.621, L97.509',

    // Product Information
    'selected_products' => ['complete_aa', 'membrane_wrap_hydro', 'other'], // Added 'other' to trigger conditional field
    'product_other' => 'Custom Dressing Kit',
    'is_patient_curer' => true,

    // Additional Clinical Information
    'patient_in_snf' => false,
    'patient_under_global' => false,
    'prior_auth_required' => true,
    'specialty_site_name' => 'Advanced Wound Care Center',

    // Signature and Documentation
    'physician_signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    'insurance_card_file' => 'sample_insurance_card.pdf',
];

echo "Test Data Summary:\n";
echo "==================\n";
echo "Total test data fields: " . count($testData) . "\n";
echo "Data categories: Basic Info, Facility, Physician, Patient, Insurance, Wound, Product, Clinical, Documentation\n\n";

// Test the Advanced Solution transformation
echo "=== Testing Advanced Solution IVR Transformation ===\n";
$docusealService = app(DocusealService::class);

try {
    $mappedFields = $docusealService->debugAdvancedSolutionIVRMapping($testData);

    echo "✅ Transformation completed successfully!\n";
    echo "Mapped fields count: " . count($mappedFields) . "\n\n";

    // Get the expected field names from the template
    $expectedFields = [
        // Basic Information
        'Sales Rep', 'Office', 'Outpatient Hospital', 'Ambulatory Surgical Center', 'Other', 'POS Other', 'MAC',

        // Facility Information
        'Facility Name', 'Facility Address', 'Facility NPI', 'Factility Contact Name', 'Facility TIN',
        'Facility Phone Number', 'Facility PTAN', 'Facility Fax Number',

        // Physician Information
        'Physician Name', 'Physician Fax', 'Physician Address', 'Physician NPI', 'Physician Phone', 'Physician TIN',

        // Patient Information
        'Patient Name', 'Patient Phone', 'Patient Address', 'Ok to Contact Patient Yes', 'OK to Contact Patient No', 'Patient DOB',

        // Primary Insurance Information
        'Primary Insurance Name', 'Primary Subscriber Name', 'Primary Policy Number', 'Primary Subscriber DOB',
        'Primary Type of Plan HMO', 'Primary Type of Plan PPO', 'Primary Type of Plan Other', 'Primary Type of Plan Other (String)',
        'Primary Insurance Phone Number', 'Physician Status With Primary: In-Network', 'Physician Status With Primary: Out-of-Network',
        'Primary In-Network Not Sure',

        // Secondary Insurance Information
        'Secondary Insurance', 'Secondary Subscriber Name', 'Secondary Policy Number', 'Secondary Subscriber DOB',
        'Secondary Type of Plan HMO', 'Secondary Type of Plan PPO', 'Secondary Type of Plan Other', 'Secondary Type of Plan Other (String)',
        'Secondary Insurance Phone Number', 'Physician Status With Secondary: In-Network', 'Physician Status With Secondary: Out-of-Network',
        'Secondary In-Network Not Sure',

        // Wound Information
        'Diabetic Foot Ulcer', 'Venous Leg Ulcer', 'Pressure Ulcer', 'Traumatic Burns', 'Radiation Burns', 'Necrotizing Facilitis',
        'Dehisced Surgical Wound', 'Other Wound', 'Type of Wound Other', 'Wound Size', 'CPT Codes', 'Date of Service', 'ICD-10 Diagnosis Codes',

        // Product Information
        'Complete AA', 'Membrane Wrap Hydro', 'Membrane Wrap', 'WoundPlus', 'CompleteFT', 'Other Product', 'Product Other', 'Is Patient Curer',

        // Additional Clinical Information
        'Patient in SNF No', 'Patient Under Global Yes', 'Patient Under Global No', 'Prior Auth', 'Specialty Site Name',

        // Signature and Documentation
        'Physician or Authorized Signature', 'Date Signed', 'Insurance Card'
    ];

    echo "Expected fields count: " . count($expectedFields) . "\n";
    echo "Actual mapped fields count: " . count($mappedFields) . "\n\n";

    // Check for missing fields
    $missingFields = [];
    $foundFields = [];

    foreach ($expectedFields as $expectedField) {
        if (isset($mappedFields[$expectedField])) {
            $foundFields[] = $expectedField;
        } else {
            $missingFields[] = $expectedField;
        }
    }

    echo "=== Field Completion Analysis ===\n";
    echo "Found fields: " . count($foundFields) . "/" . count($expectedFields) . "\n";
    echo "Missing fields: " . count($missingFields) . "\n";
    echo "Completion rate: " . round((count($foundFields) / count($expectedFields)) * 100, 1) . "%\n\n";

    if (count($missingFields) > 0) {
        echo "❌ Missing Fields:\n";
        foreach ($missingFields as $field) {
            echo "  - {$field}\n";
        }
        echo "\n";
    } else {
        echo "✅ All expected fields are present!\n\n";
    }

    // Show sample of mapped fields
    echo "=== Sample Mapped Fields ===\n";
    $sampleCount = 0;
    foreach ($mappedFields as $fieldName => $value) {
        if ($sampleCount >= 20) break;
        echo sprintf("  %-40s: %s\n", $fieldName, $value);
        $sampleCount++;
    }
    if (count($mappedFields) > 20) {
        echo "  ... and " . (count($mappedFields) - 20) . " more fields\n";
    }
    echo "\n";

    // Check for any "Patient Full Name" references (should be fixed)
    echo "=== Patient Full Name Check ===\n";
    $fullNameFound = false;
    foreach ($mappedFields as $fieldName => $value) {
        if (strpos($fieldName, 'Patient Full Name') !== false) {
            echo "❌ Found 'Patient Full Name' reference: {$fieldName} = {$value}\n";
            $fullNameFound = true;
        }
    }

    if (!$fullNameFound) {
        echo "✅ No 'Patient Full Name' references found - Issue is fixed!\n";
    }
    echo "\n";

    // Generate DocuSeal API request format
    echo "=== DocuSeal API Request Format ===\n";
    $apiRequest = [
        'template_id' => 1199885,
        'send_email' => true,
        'submitters' => [
            [
                'role' => 'First Party',
                'email' => 'test@example.com',
                'fields' => []
            ]
        ]
    ];

    foreach ($mappedFields as $fieldName => $value) {
        $apiRequest['submitters'][0]['fields'][] = [
            'name' => $fieldName,
            'default_value' => $value,
            'readonly' => false
        ];
    }

    echo "API request prepared with " . count($apiRequest['submitters'][0]['fields']) . " fields\n";
    echo "Template ID: 1199885\n";
    echo "Submitter role: First Party\n\n";

    // Final status
    echo "=== Final Status ===\n";
    if (count($missingFields) === 0) {
        echo "🎉 SUCCESS: Advanced Solution IVR template is 100% complete!\n";
        echo "✅ All " . count($expectedFields) . " fields are properly mapped\n";
        echo "✅ No 'Patient Full Name' errors\n";
        echo "✅ Ready for DocuSeal API submission\n";
    } else {
        echo "⚠️  PARTIAL: " . count($missingFields) . " fields are missing\n";
        echo "📊 Completion rate: " . round((count($foundFields) / count($expectedFields)) * 100, 1) . "%\n";
        echo "🔧 Additional configuration may be needed\n";
    }

} catch (Exception $e) {
    echo "❌ Transformation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
