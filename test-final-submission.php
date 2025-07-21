<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use App\Models\Order\Manufacturer;

// Colors
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$yellow}=== FINAL DOCUSEAL SUBMISSION TEST ==={$reset}\n\n";

try {
    $docusealService = app(DocusealService::class);
    $mappingService = app(UnifiedFieldMappingService::class);
    
    $manufacturer = Manufacturer::where('name', 'LIKE', '%ACZ%')->first();
    $config = config("manufacturers.acz-&-associates");
    $templateId = $config['docuseal_template_id'] ?? null;
    
    // Complete test data with fixes
    $testData = [
        // Patient data - use both formats to ensure it works
        'patient_first_name' => 'John',
        'patient_last_name' => 'Smith',
        'patient_name' => 'John Smith', // Direct fallback
        'patient_dob' => '1980-01-15',
        'patient_phone' => '(555) 123-4567',
        'patient_email' => 'john.smith@example.com',
        'patient_address' => '123 Healthcare Blvd',
        'patient_city' => 'Medical City',
        'patient_state' => 'TX',
        'patient_zip' => '12345',
        
        // Provider data
        'provider_name' => 'Dr. Jane Doe',
        'provider_npi' => '1234567890',
        'provider_phone' => '(555) 234-5678',
        
        // Facility data
        'facility_name' => 'Main Medical Center',
        'facility_address' => '123 Healthcare Blvd',
        'facility_phone' => '(555) 345-6789',
        
        // Insurance
        'insurance_name' => 'Medicare',
        'insurance_id' => '1234567890A',
        
        // Place of Service - use just the number to avoid double prefix
        'place_of_service' => '11',  // Will be converted to "POS 11"
        
        // Radio buttons
        'primary_physician_network_status' => 'in_network',
        'prior_auth_permission' => true,
        'hospice_status' => false,
        
        // Product codes
        'selected_products' => [
            ['product_id' => 1, 'product' => ['q_code' => 'Q4205']]
        ],
        
        // Integration email
        'integration_email' => 'limitless@mscwoundcare.com',
    ];
    
    echo "{$blue}Creating submission...{$reset}\n";
    
    // Map fields
    $mappingResult = $mappingService->mapEpisodeToDocuSeal(
        null,
        $manufacturer->name,
        $templateId,
        $testData,
        'test@example.com',
        false
    );
    
    $mappedFields = $mappingResult['data'] ?? [];
    
    // Check critical fields
    echo "\n{$yellow}Critical Field Values:{$reset}\n";
    $criticalFields = ['patient_name', 'place_of_service', 'physician_status_primary'];
    foreach ($criticalFields as $field) {
        $value = $mappedFields[$field] ?? 'NOT MAPPED';
        $color = ($value === 'NOT MAPPED') ? $red : $green;
        echo "  {$field}: {$color}{$value}{$reset}\n";
    }
    
    // Create submission
    $result = $docusealService->createSubmissionForQuickRequest(
        $templateId,
        'limitless@mscwoundcare.com',
        'test@example.com',
        $mappedFields['patient_name'] ?? 'Test Patient',
        $mappedFields,
        null
    );
    
    if (isset($result['success']) && $result['success']) {
        echo "\n{$green}✓ SUCCESS!{$reset}\n";
        echo "Form URL: {$blue}{$result['data']['embed_url']}{$reset}\n";
        echo "Fields mapped: {$result['fields_mapped']}\n";
        
        // Provide direct link
        echo "\n{$yellow}Open this URL in your browser to verify:{$reset}\n";
        echo "{$blue}{$result['data']['embed_url']}{$reset}\n";
        
        // Check webhook
        echo "\n{$yellow}You can monitor the webhook at:{$reset}\n";
        echo "ssh -R gfj6ttsgjncgfh:80:localhost:8000 docuseal.dev\n";
        echo "Then check: http://localhost:8000/api/docuseal-webhooks\n";
    } else {
        echo "\n{$red}✗ FAILED{$reset}\n";
        print_r($result);
    }
    
} catch (Exception $e) {
    echo "{$red}✗ ERROR:{$reset} " . $e->getMessage() . "\n";
}

echo "\n{$yellow}=== TEST COMPLETE ==={$reset}\n"; 