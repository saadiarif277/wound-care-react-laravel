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
            'insurance_card_front' => 'required|file|mimes:jpg,jpeg,png|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        try {
            $frontImage = $request->file('insurance_card_front');
            $backImage = $request->file('insurance_card_back');

            Log::info('Processing insurance card', [
                'has_front' => true,
                'has_back' => $backImage !== null
            ]);

            // Analyze with Azure Document Intelligence
            $result = $this->azureService->analyzeInsuranceCard($frontImage, $backImage);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            // Transform the Azure response to match the field names expected by Step7DocusealIVR
            $transformedData = $this->transformInsuranceCardData($result);

            Log::info('Insurance card analysis completed', [
                'success' => true,
                'extracted_fields' => array_keys($transformedData['data'] ?? [])
            ]);

            return response()->json($transformedData);

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
     * Transform Azure Document Intelligence results to match expected field names
     */
    private function transformInsuranceCardData(array $azureResult): array
    {
        $data = [];
        
        // Map Azure fields to our expected field names
        $fieldMapping = [
            // Patient fields
            'member_name' => 'patient_name',
            'patient_name' => 'patient_name',
            'first_name' => 'patient_first_name',
            'last_name' => 'patient_last_name',
            'date_of_birth' => 'patient_dob',
            'dob' => 'patient_dob',
            'member_id' => 'patient_member_id',
            'subscriber_id' => 'patient_member_id',
            
            // Insurance fields
            'insurance_company' => 'payer_name',
            'payer_name' => 'payer_name',
            'insurance_name' => 'primary_insurance_name',
            'plan_name' => 'primary_insurance_name',
            'group_number' => 'group_number',
            'policy_number' => 'primary_policy_number',
            'member_number' => 'primary_policy_number',
            
            // Contact fields
            'customer_service' => 'primary_payer_phone',
            'phone' => 'primary_payer_phone',
            'payer_phone' => 'primary_payer_phone',
        ];
        
        // Extract from structured data if available
        if (isset($azureResult['structured_data'])) {
            foreach ($azureResult['structured_data'] as $key => $value) {
                $mappedKey = $fieldMapping[$key] ?? $key;
                if (!empty($value)) {
                    $data[$mappedKey] = $value;
                }
            }
        }
        
        // Also check form_fields from Azure response
        if (isset($azureResult['form_fields'])) {
            foreach ($azureResult['form_fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldValue = $field['value'] ?? '';
                
                $mappedKey = $fieldMapping[$fieldName] ?? $fieldName;
                if (!empty($fieldValue) && !isset($data[$mappedKey])) {
                    $data[$mappedKey] = $fieldValue;
                }
            }
        }
        
        // Ensure patient_name is computed if we have first and last names
        if (!isset($data['patient_name']) && isset($data['patient_first_name']) && isset($data['patient_last_name'])) {
            $data['patient_name'] = trim($data['patient_first_name'] . ' ' . $data['patient_last_name']);
        }
        
        // Ensure primary_insurance_name is set from payer_name if not already set
        if (!isset($data['primary_insurance_name']) && isset($data['payer_name'])) {
            $data['primary_insurance_name'] = $data['payer_name'];
        }
        
        return [
            'success' => true,
            'data' => $data,
            'confidence' => $azureResult['confidence'] ?? 0.85,
            'processing_method' => 'azure_document_intelligence'
        ];
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