<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AzureDocumentIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InsuranceCardController extends Controller
{
    private AzureDocumentIntelligenceService $azureService;

    public function __construct(AzureDocumentIntelligenceService $azureService)
    {
        $this->azureService = $azureService;
    }

    /**
     * Analyze insurance card images and extract data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'insurance_card_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        try {
            // Analyze insurance card
            $extractedData = $this->azureService->analyzeInsuranceCard(
                $request->file('insurance_card_front'),
                $request->file('insurance_card_back')
            );

            // Map to form fields
            $formData = $this->azureService->mapToPatientForm($extractedData);

            return response()->json([
                'success' => true,
                'data' => $formData,
                'extracted_data' => $extractedData,
                'message' => 'Insurance card analyzed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Insurance card analysis failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze insurance card. Please try again or enter information manually.',
                'error' => config('app.debug') ? $e->getMessage() : null,
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