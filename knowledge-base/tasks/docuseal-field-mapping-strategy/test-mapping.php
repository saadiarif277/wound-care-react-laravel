<?php

/**
 * Test script for ACZ & Associates IVR Field Mapping
 *
 * This script demonstrates the field mapping strategy using the provided form data
 * to achieve 100% form completion accuracy.
 */

require_once __DIR__ . '/field-mapping-service.php';

// Sample form data from the user's request
$formData = [
    "request_type" => "new_request",
    "provider_id" => 3,
    "facility_id" => 1,
    "organization_id" => 22,
    "organization_name" => "Test Healthcare Network",
    "patient_name" => "John Doe",
    "patient_first_name" => "John",
    "patient_last_name" => "Doe",
    "patient_dob" => "1965-03-15",
    "patient_gender" => "male",
    "patient_is_subscriber" => true,
    "primary_insurance_name" => "Cigna",
    "primary_member_id" => "MED123456789",
    "primary_plan_type" => "ffs",
    "has_secondary_insurance" => false,
    "prior_auth_permission" => true,
    "wound_type" => "diabetic_foot_ulcer",
    "wound_types" => ["diabetic_foot_ulcer"],
    "wound_location" => "right_foot",
    "wound_size_length" => "4",
    "wound_size_width" => "4",
    "wound_size_depth" => "0",
    "application_cpt_codes" => [""],
    "place_of_service" => "11",
    "shipping_speed" => "standard_next_day",
    "expected_service_date" => "2025-08-02",
    "order_items" => [],
    "failed_conservative_treatment" => true,
    "information_accurate" => true,
    "medical_necessity_established" => true,
    "maintain_documentation" => true,
    "authorize_prior_auth" => true,
    "provider_npi" => "12345",
    "selected_products" => [
        [
            "product_id" => 4,
            "quantity" => 1,
            "size" => "1.54",
            "product" => [
                "id" => 4,
                "code" => "Q4316",
                "name" => "Amchoplast",
                "manufacturer" => "ACZ & Associates",
                "manufacturer_id" => 1,
                "available_sizes" => ["1.54", "2.55", "4.00", "6.00", "8.00", "9.00", "12.00", "15.00", "16.00", "24.00", "25.00", "28.00", "32.00", "48.00", "49.00", "72.00", "100.00", "200.00", "400.00"],
                "size_options" => ["14 mm disc", "18 mm disc", "2×2 cm", "2×3 cm", "2×4 cm", "3×3 cm", "2×6 cm", "3×5 cm", "4×4 cm", "4×6 cm", "5×5 cm", "4×7 cm", "4×8 cm", "6×8 cm", "7×7 cm", "6×12 cm", "10×10 cm", "10×20 cm", "20×20 cm"],
                "size_pricing" => [
                    "14 mm disc" => "1.54",
                    "18 mm disc" => "2.55",
                    "2×2 cm" => "4.00",
                    "2×3 cm" => "6.00",
                    "2×4 cm" => "8.00",
                    "3×3 cm" => "9.00",
                    "2×6 cm" => "12.00",
                    "3×5 cm" => "15.00",
                    "4×4 cm" => "16.00",
                    "4×6 cm" => "24.00",
                    "5×5 cm" => "25.00",
                    "4×7 cm" => "28.00",
                    "4×8 cm" => "32.00",
                    "6×8 cm" => "48.00",
                    "7×7 cm" => "49.00",
                    "6×12 cm" => "72.00",
                    "10×10 cm" => "100.00",
                    "10×20 cm" => "200.00",
                    "20×20 cm" => "400.00"
                ],
                "size_unit" => "cm",
                "price_per_sq_cm" => "441.60",
                "msc_price" => 264.96,
                "commission_rate" => null,
                "docuseal_template_id" => null,
                "signature_required" => false
            ]
        ]
    ],
    "manufacturer_fields" => [],
    "docuseal_submission_id" => "",
    "delivery_date" => "2025-08-02",
    "patient_member_id" => "MED123456789",
    "patient_address_line1" => "123 Main Street",
    "patient_address_line2" => "Apt 4B",
    "patient_city" => "New York",
    "patient_state" => "NY",
    "patient_zip" => "10001",
    "patient_phone" => "(555) 123-4567",
    "patient_email" => "john.doe@email.com",
    "primary_payer_phone" => "",
    "wound_location_details" => "Plantar surface, first metatarsal head",
    "primary_diagnosis_code" => "E11.621",
    "secondary_diagnosis_code" => "L97.519",
    "wound_duration_weeks" => "6",
    "wound_duration_days" => "2",
    "prior_applications" => "0",
    "prior_application_product" => "Standard dressing",
    "prior_application_within_12_months" => false,
    "anticipated_applications" => "4",
    "medicare_part_b_authorized" => false,
    "hospice_status" => false,
    "part_a_status" => false,
    "global_period_status" => false,
    "primary_physician_network_status" => "in_network",
    "primary_payer_id" => "3"
];

// Additional data that would be available in a real scenario
$formData['provider_name'] = 'Dr. Jane Smith';
$formData['facility_name'] = 'Test Healthcare Network';
$formData['facility_address_line1'] = '456 Medical Center Blvd';
$formData['facility_city'] = 'New York';
$formData['facility_state'] = 'NY';
$formData['facility_zip'] = '10002';

echo "=== ACZ & Associates IVR Field Mapping Test ===\n\n";

// Initialize the field mapping service
$mappingService = new \App\Services\ACZIVRFieldMappingService();

// Test the field mapping
$result = $mappingService->mapFormDataToDocuseal($formData);

echo "📊 MAPPING RESULTS:\n";
echo "==================\n";
echo "Success: " . ($result['success'] ? '✅ YES' : '❌ NO') . "\n";
echo "Mapped Fields: " . count($result['fields']) . "\n";
echo "Errors: " . count($result['errors']) . "\n\n";

if (!empty($result['errors'])) {
    echo "❌ ERRORS:\n";
    echo "==========\n";
    foreach ($result['errors'] as $error) {
        echo "- {$error}\n";
    }
    echo "\n";
}

echo "✅ MAPPED FIELDS:\n";
echo "================\n";
foreach ($result['fields'] as $fieldName => $value) {
    echo "• {$fieldName}: {$value}\n";
}

echo "\n📈 STATISTICS:\n";
echo "==============\n";
$stats = $mappingService->getMappingStatistics($formData);
echo "Total Template Fields: {$stats['total_template_fields']}\n";
echo "Mapped Fields: {$stats['mapped_fields']}\n";
echo "Success Rate: " . number_format($stats['mapping_success_rate'], 1) . "%\n";
echo "Required Fields Mapped: {$stats['required_fields_mapped']}\n";
echo "Radio Fields Mapped: {$stats['radio_fields_mapped']}\n";
echo "Conditional Fields Mapped: {$stats['conditional_fields_mapped']}\n";

echo "\n🎯 FIELD CATEGORIES:\n";
echo "===================\n";
$report = $mappingService->getFieldMappingReport($formData);

foreach ($report['field_categories'] as $category => $fields) {
    echo "\n📋 {$category}:\n";
    foreach ($fields as $fieldName => $mapping) {
        $mapped = isset($result['fields'][$fieldName]) ? '✅' : '❌';
        $required = ($mapping['required'] ?? false) ? ' (Required)' : '';
        echo "  {$mapped} {$fieldName}{$required}\n";
    }
}

echo "\n🎉 MAPPING STRATEGY SUMMARY:\n";
echo "============================\n";
echo "• Product Selection: " . (isset($result['fields']['Product Q Code']) ? '✅ Mapped' : '❌ Missing') . "\n";
echo "• Patient Information: " . (isset($result['fields']['Patient Name']) && isset($result['fields']['Patient DOB']) ? '✅ Complete' : '⚠️ Partial') . "\n";
echo "• Insurance Information: " . (isset($result['fields']['Primary Insurance Name']) ? '✅ Mapped' : '❌ Missing') . "\n";
echo "• Clinical Data: " . (isset($result['fields']['Location of Wound']) ? '✅ Mapped' : '❌ Missing') . "\n";
echo "• Authorization Questions: " . (isset($result['fields']['Permission To Initiate And Follow Up On Prior Auth?']) ? '✅ Mapped' : '❌ Missing') . "\n";

echo "\n📝 RECOMMENDATIONS:\n";
echo "==================\n";
if ($result['success']) {
    echo "✅ Excellent! All required fields are mapped successfully.\n";
    echo "✅ The form can be filled with 100% accuracy.\n";
} else {
    echo "⚠️ Some fields are missing or have errors.\n";
    echo "📋 Review the errors above and ensure all required data is available.\n";
    echo "🔧 Consider adding fallback values for missing optional fields.\n";
}

echo "\n🚀 NEXT STEPS:\n";
echo "==============\n";
echo "1. Integrate this mapping service into the DocusealService\n";
echo "2. Update the manufacturer configuration file\n";
echo "3. Test with real form submissions\n";
echo "4. Monitor mapping success rates in production\n";
echo "5. Add additional data transformations as needed\n";

echo "\n=== END OF TEST ===\n";
