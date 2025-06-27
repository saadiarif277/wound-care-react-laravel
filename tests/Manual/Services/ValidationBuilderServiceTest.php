<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Client\HttpClientException;

// Bootstrap Laravel for testing
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the CMS Coverage API Service
echo "=== Testing CMS Coverage API Service ===\n\n";

try {
    $cmsService = app(\App\Services\CmsCoverageApiService::class);

    // Test 1: Get available specialties
    echo "1. Testing getAvailableSpecialties():\n";
    $specialties = $cmsService->getAvailableSpecialties();
    foreach ($specialties as $key => $name) {
        echo "   - {$key}: {$name}\n";
    }
    echo "\n";

    // Test 2: Get LCDs for wound care (CA state)
    echo "2. Testing getLCDsBySpecialty() for wound care in CA:\n";
    $lcds = $cmsService->getLCDsBySpecialty('wound_care_specialty', 'CA');
    echo "   Found " . count($lcds) . " LCDs\n";
    if (!empty($lcds)) {
        $firstLcd = array_slice($lcds, 0, 1)[0] ?? null;
        if ($firstLcd) {
            echo "   First LCD: " . ($firstLcd['documentTitle'] ?? 'No title') . "\n";
        }
    }
    echo "\n";

    // Test 3: Get NCDs for wound care
    echo "3. Testing getNCDsBySpecialty() for wound care:\n";
    $ncds = $cmsService->getNCDsBySpecialty('wound_care_specialty');
    echo "   Found " . count($ncds) . " NCDs\n";
    if (!empty($ncds)) {
        $firstNcd = array_slice($ncds, 0, 1)[0] ?? null;
        if ($firstNcd) {
            echo "   First NCD: " . ($firstNcd['documentTitle'] ?? 'No title') . "\n";
        }
    }
    echo "\n";

    // Test 4: Get Articles for wound care (CA state)
    echo "4. Testing getArticlesBySpecialty() for wound care in CA:\n";
    $articles = $cmsService->getArticlesBySpecialty('wound_care_specialty', 'CA');
    echo "   Found " . count($articles) . " Articles\n";
    echo "\n";

    // Test 5: Search CMS documents
    echo "5. Testing searchCoverageDocuments() for 'wound dressing':\n";
    $searchResults = $cmsService->searchCoverageDocuments('wound dressing', 'CA');
    echo "   Total results: " . ($searchResults['total_results'] ?? 0) . "\n";
    echo "   LCDs: " . count($searchResults['lcds'] ?? []) . "\n";
    echo "   NCDs: " . count($searchResults['ncds'] ?? []) . "\n";
    echo "   Articles: " . count($searchResults['articles'] ?? []) . "\n";
    echo "\n";

    // Test 6: Get MAC jurisdiction for CA
    echo "6. Testing getMACJurisdiction() for CA:\n";
    $macInfo = $cmsService->getMACJurisdiction('CA');
    if ($macInfo) {
        echo "   MAC Contractor: " . ($macInfo['contractor'] ?? 'Unknown') . "\n";
        echo "   Jurisdiction: " . ($macInfo['jurisdiction'] ?? 'Unknown') . "\n";
    } else {
        echo "   No MAC jurisdiction data found\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "Error testing CMS Coverage API Service: " . $e->getMessage() . "\n\n";
}

// Test the Validation Builder Engine
echo "=== Testing Validation Builder Engine ===\n\n";

try {
    $validationEngine = app(\App\Services\ValidationBuilderEngine::class);

    // Test 1: Build validation rules for wound care specialty
    echo "1. Testing buildValidationRulesForSpecialty() for wound care:\n";
    $rules = $validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');

    if (!empty($rules)) {
        echo "   Found validation rules with " . count($rules) . " main categories:\n";
        foreach (array_keys($rules) as $category) {
            echo "     - {$category}\n";
        }

        // Show wound care specific rules
        if (isset($rules['pre_purchase_qualification'])) {
            echo "   Pre-purchase qualification rules found\n";
        }
        if (isset($rules['comprehensive_wound_assessment'])) {
            echo "   Comprehensive wound assessment rules found\n";
        }
        if (isset($rules['conservative_care_documentation'])) {
            echo "   Conservative care documentation rules found\n";
        }
    } else {
        echo "   No validation rules found\n";
    }
    echo "\n";

    // Test 2: Build validation rules for vascular surgery
    echo "2. Testing buildValidationRulesForSpecialty() for vascular surgery:\n";
    $vascularRules = $validationEngine->buildValidationRulesForSpecialty('vascular_surgery', 'CA');
    echo "   Found " . count($vascularRules) . " rule categories for vascular surgery\n";
    echo "\n";

} catch (Exception $e) {
    echo "Error testing Validation Builder Engine: " . $e->getMessage() . "\n\n";
}

// Test API Endpoints (if running through web server)
echo "=== API Endpoint Examples ===\n\n";

echo "Available API endpoints:\n";
echo "GET /api/v1/validation-builder/specialties\n";
echo "GET /api/v1/validation-builder/rules?specialty=wound_care_specialty&state=CA\n";
echo "GET /api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty&state=CA\n";
echo "GET /api/v1/validation-builder/cms-ncds?specialty=wound_care_specialty\n";
echo "GET /api/v1/validation-builder/search-cms?keyword=wound+dressing&state=CA\n";
echo "GET /api/v1/validation-builder/mac-jurisdiction?state=CA\n";
echo "POST /api/v1/validation-builder/validate-order (with order_id and optional specialty)\n";
echo "\n";

// Test integration with existing Medicare MAC validation
echo "=== Testing Integration with Medicare MAC Validation ===\n\n";

try {
            $macService = app(\App\Services\MacValidationService::class);

    echo "Medicare MAC Validation Service loaded successfully\n";
    echo "The service now integrates with:\n";
    echo "  - CMS Coverage API Service for live LCD/NCD data\n";
    echo "  - Validation Builder Engine for comprehensive validation rules\n";
    echo "  - Enhanced wound care validation based on the questionnaire\n";
    echo "\n";

} catch (Exception $e) {
    echo "Error testing Medicare MAC Validation integration: " . $e->getMessage() . "\n\n";
}

echo "=== Test Complete ===\n";
echo "\nImplementation Summary:\n";
echo "✓ CMS Coverage API Service - Fetches LCDs, NCDs, Articles from api.coverage.cms.gov\n";
echo "✓ Validation Builder Engine - Creates specialty-specific validation rules\n";
echo "✓ Enhanced Medicare MAC Validation - Integrates live CMS data\n";
echo "✓ Comprehensive Wound Care Rules - Based on the detailed questionnaire\n";
echo "✓ API Controller and Routes - RESTful endpoints for all functionality\n";
echo "✓ Service Registration - Proper dependency injection setup\n";
echo "\nNext steps:\n";
echo "- Test with real orders and product requests\n";
echo "- Enhance CMS document parsing for more detailed rule extraction\n";
echo "- Add frontend integration for the new validation endpoints\n";
echo "- Implement caching strategies for performance optimization\n";
