<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Models\Order\ProductRequest;
use App\Services\ProductRecommendationEngine\MSCProductRecommendationService;

// Test the Product Recommendations API
echo "Testing Product Recommendations API...\n\n";

try {
    // Create a mock product request for testing
    $productRequest = new ProductRequest([
        'id' => 1,
        'wound_type' => 'DFU',
        'patient_fhir_id' => 'test-patient-123',
        'facility_id' => 1,
        'payer_name_submitted' => 'Medicare',
        'expected_service_date' => '2024-01-15',
        'clinical_summary' => [
            'wound_size' => '4.5',
            'wound_depth' => 'partial_thickness',
            'infection_present' => false,
            'previous_treatments' => ['conservative_care'],
            'patient_age' => 65,
            'diabetes_type' => 'type_2',
            'hba1c' => '7.2'
        ]
    ]);

    echo "Mock Product Request Created:\n";
    echo "- ID: {$productRequest->id}\n";
    echo "- Wound Type: {$productRequest->wound_type}\n";
    echo "- Patient: {$productRequest->patient_fhir_id}\n";
    echo "- Payer: {$productRequest->payer_name_submitted}\n\n";

    // Test the recommendation service
    echo "Testing MSC Product Recommendation Service...\n";

    // Note: Using real Laravel services instead of mocks for proper testing

    // Create the recommendation service using Laravel's container
    $recommendationService = app(MSCProductRecommendationService::class);

    echo "Getting recommendations...\n";
    $result = $recommendationService->getRecommendations($productRequest, ['use_ai' => false]);

    if ($result['success']) {
        echo "✅ Recommendations generated successfully!\n\n";
        echo "Number of recommendations: " . count($result['recommendations']) . "\n";

        foreach ($result['recommendations'] as $index => $rec) {
            echo "\nRecommendation " . ($index + 1) . ":\n";
            echo "- Q-Code: {$rec['q_code']}\n";
            echo "- Product: {$rec['product_name']}\n";
            echo "- Rank: {$rec['rank']}\n";
            echo "- Confidence: " . round($rec['confidence_score'] * 100) . "%\n";
            echo "- Reasoning: {$rec['reasoning']}\n";
            if (isset($rec['estimated_cost'])) {
                echo "- MSC Price: $" . number_format($rec['estimated_cost']['msc_price'], 2) . "\n";
            }
        }

        echo "\nContext Summary:\n";
        foreach ($result['context_summary'] as $key => $value) {
            echo "- " . ucfirst(str_replace('_', ' ', $key)) . ": " .
                 (is_array($value) ? json_encode($value) : $value) . "\n";
        }

    } else {
        echo "❌ Failed to generate recommendations\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";

        if (isset($result['fallback_recommendations'])) {
            echo "\nFallback recommendations available: " . count($result['fallback_recommendations']) . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Test failed with exception:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed.\n";
