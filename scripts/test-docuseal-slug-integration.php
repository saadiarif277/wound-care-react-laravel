<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Log;

// Laravel Bootstrap
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing DocuSeal Integration with Slug Generation\n";
echo "==================================================\n\n";

try {
    // Get services
    $docusealService = app(DocusealService::class);
    $orchestrator = app(QuickRequestOrchestrator::class);
    
    // Test with a real episode ID (replace with actual episode ID from database)
    $episodeId = '01941cbc-6c7a-7b8b-9fd4-c8e1e31c2c64'; // UUID from previous tests
    
    // Load the episode
    $episode = PatientManufacturerIVREpisode::find($episodeId);
    
    if (!$episode) {
        echo "❌ Episode not found: $episodeId\n";
        exit(1);
    }
    
    echo "✅ Episode found: {$episode->id}\n";
    echo "   Created by: {$episode->created_by}\n";
    echo "   Episode type: {$episode->episode_type}\n";
    echo "   Status: {$episode->status}\n\n";
    
    // Get comprehensive data from orchestrator
    echo "📋 Getting comprehensive data from orchestrator...\n";
    $comprehensiveData = $orchestrator->prepareDocusealData($episode);
    
    echo "✅ Data prepared successfully!\n";
    echo "   Field count: " . count($comprehensiveData) . "\n";
    echo "   Has patient name: " . (isset($comprehensiveData['patient_name']) ? '✅' : '❌') . "\n";
    echo "   Has provider name: " . (isset($comprehensiveData['provider_name']) ? '✅' : '❌') . "\n";
    echo "   Has facility name: " . (isset($comprehensiveData['facility_name']) ? '✅' : '❌') . "\n\n";
    
    // Sample a few key fields
    $keyFields = ['patient_name', 'patient_dob', 'provider_name', 'provider_npi', 'facility_name', 'organization_name'];
    echo "🔍 Key field values:\n";
    foreach ($keyFields as $field) {
        $value = $comprehensiveData[$field] ?? 'NOT SET';
        echo "   $field: $value\n";
    }
    echo "\n";
    
    // Test DocuSeal submission creation
    echo "🚀 Creating DocuSeal submission...\n";
    $manufacturerName = 'MEDLIFE SOLUTIONS'; // Test with MedLife
    
    $result = $docusealService->createSubmissionFromOrchestratorData(
        $episode,
        $comprehensiveData,
        $manufacturerName
    );
    
    if ($result['success']) {
        echo "✅ DocuSeal submission created successfully!\n";
        echo "   Submission ID: {$result['submission']['id']}\n";
        echo "   Slug: " . ($result['submission']['slug'] ?? 'NOT PROVIDED') . "\n";
        echo "   Template ID: " . ($result['manufacturer']['template_id'] ?? 'NOT PROVIDED') . "\n";
        echo "   Mapped fields: " . count($result['mapped_data']) . "\n";
        
        // Show slug for embedding
        if (isset($result['submission']['slug'])) {
            $slug = $result['submission']['slug'];
            echo "\n🌐 Embed URL: https://docuseal.com/s/$slug\n";
        } else {
            echo "\n❌ No slug returned from DocuSeal API\n";
        }
        
    } else {
        echo "❌ DocuSeal submission failed: {$result['error']}\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "💥 Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Test completed!\n";
