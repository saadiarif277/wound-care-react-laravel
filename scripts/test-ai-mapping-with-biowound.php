<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\IntelligentFieldMappingService;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Log;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing AI-enhanced mapping for BioWound Solutions...\n\n";

try {
    // Sample comprehensive data that would come from the orchestrator
    $comprehensiveData = [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_name' => 'John Doe',
        'patient_dob' => '1980-01-01',
        'patient_gender' => 'Male',
        'patient_phone' => '555-123-4567',
        'patient_email' => 'john.doe@example.com',
        'patient_display_id' => 'TEST001',
        'patient_address' => '123 Test St',
        'provider_name' => 'Dr. Jane Smith',
        'provider_first_name' => 'Jane',
        'provider_last_name' => 'Smith',
        'provider_npi' => '1234567890',
        'provider_email' => 'dr.smith@clinic.com',
        'provider_phone' => '555-987-6543',
        'provider_specialty' => 'Wound Care',
        'provider_credentials' => 'MD',
        'provider_ptan' => 'ABC123',
        'provider_tax_id' => '12-3456789',
        'physician_name' => 'Dr. Jane Smith', // Alias
        'physician_npi' => '1234567890', // Alias
        'facility_name' => 'Test Hospital',
        'facility_address' => '123 Medical Way',
        'facility_city' => 'Testville',
        'facility_state' => 'CA',
        'facility_zip' => '90210',
        'facility_phone' => '555-111-2222',
        'facility_npi' => '9876543210',
        'wound_type' => 'DFU',
        'wound_location' => 'Left foot',
        'wound_size_length' => '2.5',
        'wound_size_width' => '3.0',
        'wound_size_depth' => '0.5',
        'wound_duration' => '6 weeks',
        'primary_insurance_name' => 'Medicare',
        'primary_member_id' => 'M12345678',
        'insurance_name' => 'Medicare', // Alias
        'insurance_member_id' => 'M12345678', // Alias
        'primary_diagnosis_code' => 'L97.413',
        'primary_icd10' => 'L97.413', // Alias
        'product_code' => 'Q4239',
        'selected_product_codes' => ['Q4239'],
        'q4239' => true, // Product checkbox
        'amnio_maxx' => true,
        'expected_service_date' => date('Y-m-d', strtotime('+2 days')),
        'place_of_service' => '11',
        'distributor_company' => 'MSC Wound Care',
        'date' => date('m/d/Y'),
        'order_date' => date('m/d/Y'),
        'name' => 'John Smith', // Contact name
        'email' => 'john.smith@example.com',
        'phone' => '555-555-5555',
        'sales_rep' => 'MSC Sales Team',
        'rep_email' => 'sales@mscwoundcare.com',
        'contact_name' => 'Office Manager',
        'contact_email' => 'office@testhospital.com',
        'city_state_zip' => 'Testville, CA 90210',
        'post_debridement_size' => '2.5 x 3.0 x 0.5 cm'
    ];
    
    // Test 1: Standard mapping
    echo "Test 1: Standard Field Mapping\n";
    echo str_repeat('-', 50) . "\n";
    
    $standardMapping = app(UnifiedFieldMappingService::class);
    $standardResult = $standardMapping->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $comprehensiveData
    );
    
    echo "Standard mapping result:\n";
    echo "  - Valid: " . ($standardResult['validation']['valid'] ? 'Yes' : 'No') . "\n";
    echo "  - Completeness: " . $standardResult['completeness']['percentage'] . "%\n";
    echo "  - Manufacturer name: " . ($standardResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
    echo "  - Has docuseal_template_id: " . (isset($standardResult['manufacturer']['docuseal_template_id']) ? 'Yes' : 'No') . "\n";
    echo "  - Template ID: " . ($standardResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
    echo "  - Manufacturer keys: " . implode(', ', array_keys($standardResult['manufacturer'])) . "\n\n";
    
    // Test 2: AI-enhanced mapping
    echo "Test 2: AI-Enhanced Field Mapping\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $aiMapping = app(IntelligentFieldMappingService::class);
        $aiResult = $aiMapping->mapEpisodeWithAI(
            null,
            'BioWound Solutions',
            $comprehensiveData,
            ['use_cache' => false] // Force fresh mapping
        );
        
        echo "AI-enhanced mapping result:\n";
        echo "  - Valid: " . ($aiResult['validation']['valid'] ? 'Yes' : 'No') . "\n";
        echo "  - Completeness: " . ($aiResult['completeness']['percentage'] ?? 0) . "%\n";
        echo "  - Manufacturer name: " . ($aiResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
        echo "  - Has docuseal_template_id: " . (isset($aiResult['manufacturer']['docuseal_template_id']) ? 'Yes' : 'No') . "\n";
        echo "  - Template ID: " . ($aiResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
        echo "  - Manufacturer keys: " . implode(', ', array_keys($aiResult['manufacturer'])) . "\n";
        echo "  - AI Enhanced: " . ($aiResult['ai_enhanced'] ?? false ? 'Yes' : 'No') . "\n";
        echo "  - Enhancement Method: " . ($aiResult['enhancement_method'] ?? 'None') . "\n";
        
    } catch (\Exception $e) {
        echo "❌ AI mapping failed: " . $e->getMessage() . "\n";
        echo "   This is expected if AI services are not configured\n";
    }
    
    // Test 3: Simulate what DocuSeal service would see
    echo "\nTest 3: DocuSeal Service Simulation\n";
    echo str_repeat('-', 50) . "\n";
    
    // Use whichever result succeeded
    $testResult = $aiResult ?? $standardResult;
    
    $templateId = $testResult['manufacturer']['template_id'] ?? 
                 $testResult['manufacturer']['docuseal_template_id'] ?? 
                 null;
    
    if (!$templateId) {
        echo "❌ No template ID found in mapping result!\n";
        echo "  Available manufacturer keys: " . implode(', ', array_keys($testResult['manufacturer'] ?? [])) . "\n";
        echo "  This would cause the IVR submission to fail\n";
    } else {
        echo "✅ Template ID found: " . $templateId . "\n";
        echo "  IVR submission would proceed successfully\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";