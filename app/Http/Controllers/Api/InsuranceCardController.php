<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InsuranceCardController extends Controller
{
    private DocumentIntelligenceService $azureService;

    public function __construct(DocumentIntelligenceService $azureService)
    {
        $this->azureService = $azureService;
    }

    /**
     * Analyze insurance card images and extract data
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'front' => 'required|file|mimes:jpg,jpeg,png|max:10240',
            'back' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        try {
            $frontImage = $request->file('front');
            $backImage = $request->file('back');

            Log::info('Processing insurance card', [
                'has_front' => true,
                'has_back' => $backImage !== null
            ]);

            $result = $this->azureService->analyzeInsuranceCard($frontImage, $backImage);

            Log::info('Insurance card analysis completed', [
                'success' => $result['success'] ?? false,
                'has_form_fields' => !empty($result['form_fields'])
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Insurance card analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the status of Azure Document Intelligence service
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $configured = !empty(config('services.azure_di.endpoint')) && 
                     !empty(config('services.azure_di.key'));

        return response()->json([
            'configured' => $configured,
            'service' => 'Azure Document Intelligence',
            'api_version' => config('services.azure_di.api_version', '2024-02-29-preview'),
        ]);
    }
}