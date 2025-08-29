<?php

use Illuminate\Support\Facades\Route;
use App\Services\Medical\OptimizedMedicalAiService;
use App\Models\PatientManufacturerIVREpisode;

Route::get('/debug/ai-service-status', function () {
    $optimizedService = app(OptimizedMedicalAiService::class);
    $status = $optimizedService->getStatus();
    
    return response()->json([
        'service_status' => $status,
        'timestamp' => now()->toISOString()
    ]);
});

Route::get('/debug/test-ai-enhancement/{episodeId}', function ($episodeId) {
    $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);
    $optimizedService = app(OptimizedMedicalAiService::class);
    
    $baseData = [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-01',
        'patient_phone' => '555-123-4567'
    ];
    
    $templateId = 'test-template';
    
    try {
        $enhancedData = $optimizedService->enhanceDocusealFieldMapping($baseData, $templateId, $episode);
        
        return response()->json([
            'success' => true,
            'episode_id' => $episodeId,
            'base_data' => $baseData,
            'enhanced_data' => $enhancedData,
            'ai_confidence' => $enhancedData['_ai_confidence'] ?? 0,
            'ai_method' => $enhancedData['_ai_method'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'episode_id' => $episodeId
        ], 500);
    }
});

Route::get('/debug/test-basic-ai-call', function () {
    $optimizedService = app(OptimizedMedicalAiService::class);
    
    $testData = [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'dob' => '1975-05-15',
        'phone' => '555-987-6543'
    ];
    
    try {
        // Use the health check method since OptimizedMedicalAiService doesn't have mapFields
        $healthStatus = $optimizedService->healthCheck();
        
        return response()->json([
            'success' => true,
            'input_data' => $testData,
            'service_health' => $healthStatus,
            'note' => 'Using health check instead of field mapping for debug'
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'input_data' => $testData
        ], 500);
    }
});
