<?php

namespace App\Services\AI;

use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\DocumentIntelligenceService;
use App\Services\AI\AzureFoundryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Optimizes AI form filling to ensure consistent and helpful assistance
 * throughout the entire form filling process
 */
class FormFillingOptimizer
{
    public function __construct(
        protected OptimizedMedicalAiService $medicalAiService,
        protected DocumentIntelligenceService $documentIntelligence,
        protected AzureFoundryService $foundryService
    ) {}

    /**
     * Enhance form data with AI assistance at key points
     */
    public function enhanceFormData(array $formData, string $stage = 'general'): array
    {
        Log::info('AI Form Enhancement requested', [
            'stage' => $stage,
            'fields_count' => count($formData)
        ]);

        try {
            switch ($stage) {
                case 'patient_info':
                    return $this->enhancePatientInfo($formData);
                
                case 'clinical_data':
                    return $this->enhanceClinicalData($formData);
                
                case 'insurance_data':
                    return $this->enhanceInsuranceData($formData);
                
                case 'product_selection':
                    return $this->enhanceProductSelection($formData);
                
                case 'docuseal_prefill':
                    return $this->enhanceDocusealPrefill($formData);
                
                default:
                    return $this->generalEnhancement($formData);
            }
        } catch (\Exception $e) {
            Log::error('AI form enhancement failed', [
                'stage' => $stage,
                'error' => $e->getMessage()
            ]);
            
            // Return original data on failure - don't break the form
            return $formData;
        }
    }

    /**
     * Enhance patient information with smart defaults and validation
     */
    protected function enhancePatientInfo(array $formData): array
    {
        // Auto-format phone numbers
        if (!empty($formData['patient_phone'])) {
            $formData['patient_phone'] = $this->formatPhoneNumber($formData['patient_phone']);
        }

        // Auto-format dates
        if (!empty($formData['patient_dob'])) {
            $formData['patient_dob'] = $this->formatDate($formData['patient_dob']);
        }

        // Suggest gender based on first name if not provided
        if (empty($formData['patient_gender']) && !empty($formData['patient_first_name'])) {
            $formData['patient_gender_suggestion'] = $this->suggestGender($formData['patient_first_name']);
        }

        // Auto-capitalize names
        foreach (['patient_first_name', 'patient_last_name'] as $field) {
            if (!empty($formData[$field])) {
                $formData[$field] = ucwords(strtolower($formData[$field]));
            }
        }

        return $formData;
    }

    /**
     * Enhance clinical data with medical terminology validation
     */
    protected function enhanceClinicalData(array $formData): array
    {
        // Validate and suggest ICD-10 codes
        if (!empty($formData['wound_type']) && empty($formData['diagnosis_code'])) {
            $suggestedCodes = $this->suggestDiagnosisCodes($formData['wound_type']);
            if (!empty($suggestedCodes)) {
                $formData['suggested_diagnosis_codes'] = $suggestedCodes;
            }
        }

        // Calculate wound area if dimensions provided
        if (!empty($formData['wound_size_length']) && !empty($formData['wound_size_width'])) {
            $formData['calculated_wound_area'] = 
                floatval($formData['wound_size_length']) * floatval($formData['wound_size_width']);
        }

        // Suggest CPT codes based on wound location and size
        if (!empty($formData['wound_location']) && !empty($formData['calculated_wound_area'])) {
            $formData['suggested_cpt_codes'] = $this->suggestCPTCodes(
                $formData['wound_location'],
                $formData['calculated_wound_area']
            );
        }

        return $formData;
    }

    /**
     * Enhance insurance data with payer validation
     */
    protected function enhanceInsuranceData(array $formData): array
    {
        // Validate insurance member ID format
        if (!empty($formData['primary_member_id']) && !empty($formData['primary_insurance_name'])) {
            $validation = $this->validateMemberIdFormat(
                $formData['primary_member_id'],
                $formData['primary_insurance_name']
            );
            
            if (!$validation['valid']) {
                $formData['member_id_warning'] = $validation['message'];
            }
        }

        // Auto-detect insurance type from payer name
        if (!empty($formData['primary_insurance_name']) && empty($formData['primary_plan_type'])) {
            $formData['primary_plan_type'] = $this->detectInsuranceType($formData['primary_insurance_name']);
        }

        return $formData;
    }

    /**
     * Enhance product selection with recommendations
     */
    protected function enhanceProductSelection(array $formData): array
    {
        // Suggest product size based on wound area
        if (!empty($formData['calculated_wound_area']) && !empty($formData['selected_products'])) {
            foreach ($formData['selected_products'] as &$product) {
                if (empty($product['size'])) {
                    $product['suggested_size'] = $this->suggestProductSize(
                        $formData['calculated_wound_area'],
                        $product['product_id'] ?? null
                    );
                }
            }
        }

        return $formData;
    }

    /**
     * Enhance DocuSeal prefill data
     */
    protected function enhanceDocusealPrefill(array $formData): array
    {
        // Ensure all required fields are formatted correctly for DocuSeal
        $enhancedData = $formData;

        // Format dates for DocuSeal
        $dateFields = ['patient_dob', 'expected_service_date', 'global_period_surgery_date'];
        foreach ($dateFields as $field) {
            if (!empty($enhancedData[$field])) {
                $enhancedData[$field] = date('m/d/Y', strtotime($enhancedData[$field]));
            }
        }

        // Format place of service for DocuSeal radio buttons
        if (!empty($enhancedData['place_of_service'])) {
            $enhancedData['place_of_service'] = $this->formatPlaceOfService($enhancedData['place_of_service']);
        }

        // Format yes/no fields
        $booleanFields = [
            'hospice_status',
            'hospice_family_consent',
            'hospice_clinically_necessary',
            'medicare_part_b_authorized',
            'part_a_status',
            'global_period_status'
        ];
        
        foreach ($booleanFields as $field) {
            if (isset($enhancedData[$field])) {
                $enhancedData[$field] = $enhancedData[$field] ? 'Yes' : 'No';
            }
        }

        return $enhancedData;
    }

    /**
     * General enhancement for any stage
     */
    protected function generalEnhancement(array $formData): array
    {
        // Remove empty strings and convert to null
        foreach ($formData as $key => $value) {
            if ($value === '') {
                $formData[$key] = null;
            }
        }

        // Add metadata about AI processing
        $formData['_ai_enhanced'] = true;
        $formData['_ai_enhanced_at'] = now()->toISOString();

        return $formData;
    }

    /**
     * Smart field mapping with caching
     */
    public function mapFieldsIntelligently(array $sourceData, array $targetTemplate, string $context = ''): array
    {
        $cacheKey = 'ai_field_mapping_' . md5(json_encode($sourceData) . json_encode($targetTemplate) . $context);
        
        return Cache::remember($cacheKey, 300, function () use ($sourceData, $targetTemplate, $context) {
            try {
                $result = $this->foundryService->translateFormData(
                    $sourceData,
                    $targetTemplate,
                    "Source form data",
                    "Target template fields" . ($context ? " for {$context}" : ""),
                    ['use_cache' => true]
                );

                if ($result['success']) {
                    return $result['mappings'] ?? [];
                }
            } catch (\Exception $e) {
                Log::error('Intelligent field mapping failed', ['error' => $e->getMessage()]);
            }

            // Fallback to basic mapping
            return $this->basicFieldMapping($sourceData, $targetTemplate);
        });
    }

    /**
     * Format phone number to standard format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }
        
        return $phone;
    }

    /**
     * Format date to standard format
     */
    protected function formatDate(string $date): string
    {
        try {
            return date('Y-m-d', strtotime($date));
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Suggest gender based on first name
     */
    protected function suggestGender(string $firstName): ?string
    {
        // This would ideally use a name database or AI service
        // For now, return null to avoid assumptions
        return null;
    }

    /**
     * Suggest diagnosis codes based on wound type
     */
    protected function suggestDiagnosisCodes(string $woundType): array
    {
        $suggestions = [
            'diabetic_foot_ulcer' => ['E11.621', 'E11.622', 'L97.411', 'L97.412'],
            'pressure_ulcer' => ['L89.90', 'L89.91', 'L89.92', 'L89.93'],
            'venous_ulcer' => ['I83.01', 'I83.02', 'I87.01', 'I87.31'],
            'surgical_wound' => ['T81.31XA', 'T81.32XA', 'T81.33XA'],
            'burn' => ['T31.0', 'T31.10', 'T31.20', 'T31.30']
        ];

        return $suggestions[$woundType] ?? [];
    }

    /**
     * Suggest CPT codes based on wound location and area
     */
    protected function suggestCPTCodes(string $location, float $area): array
    {
        $isExtremity = str_contains(strtolower($location), 'hand') || 
                      str_contains(strtolower($location), 'feet') ||
                      str_contains(strtolower($location), 'head');

        if ($isExtremity) {
            if ($area <= 25) return ['15275'];
            if ($area <= 100) return ['15275', '15276'];
            return ['15277', '15278'];
        } else {
            if ($area <= 25) return ['15271'];
            if ($area <= 100) return ['15271', '15272'];
            return ['15273', '15274'];
        }
    }

    /**
     * Validate member ID format for known payers
     */
    protected function validateMemberIdFormat(string $memberId, string $payerName): array
    {
        $payerPatterns = [
            'medicare' => '/^[0-9]{9}[A-Z]?$/',
            'medicaid' => '/^[0-9]{8,12}$/',
            'bcbs' => '/^[A-Z]{3}[0-9]{9}$/',
            'aetna' => '/^W[0-9]{9}$/',
            'united' => '/^[0-9]{9}$/'
        ];

        foreach ($payerPatterns as $payer => $pattern) {
            if (stripos($payerName, $payer) !== false) {
                if (!preg_match($pattern, $memberId)) {
                    return [
                        'valid' => false,
                        'message' => "Member ID format may be incorrect for {$payer}"
                    ];
                }
                break;
            }
        }

        return ['valid' => true];
    }

    /**
     * Detect insurance type from payer name
     */
    protected function detectInsuranceType(string $payerName): string
    {
        $lowerName = strtolower($payerName);

        if (str_contains($lowerName, 'medicare')) return 'Medicare';
        if (str_contains($lowerName, 'medicaid')) return 'Medicaid';
        if (str_contains($lowerName, 'hmo')) return 'HMO';
        if (str_contains($lowerName, 'ppo')) return 'PPO';
        if (str_contains($lowerName, 'tricare')) return 'Military';
        if (str_contains($lowerName, 'va')) return 'VA';

        return 'Commercial';
    }

    /**
     * Suggest product size based on wound area
     */
    protected function suggestProductSize(float $woundArea, ?int $productId): string
    {
        // Size thresholds in sq cm
        if ($woundArea <= 25) return 'Small (5x5 cm)';
        if ($woundArea <= 100) return 'Medium (10x10 cm)';
        if ($woundArea <= 225) return 'Large (15x15 cm)';
        
        return 'Extra Large (20x20 cm)';
    }

    /**
     * Format place of service for DocuSeal
     */
    protected function formatPlaceOfService(string $pos): string
    {
        $posMap = [
            '11' => 'POS 11',
            '12' => 'POS 12',
            '22' => 'POS 22',
            '24' => 'POS 24',
            '31' => 'POS 31',
            '32' => 'POS 32',
            '34' => 'POS 34'
        ];

        return $posMap[$pos] ?? 'Other';
    }

    /**
     * Basic field mapping fallback
     */
    protected function basicFieldMapping(array $sourceData, array $targetTemplate): array
    {
        $mapped = [];
        
        foreach ($targetTemplate as $targetField => $fieldConfig) {
            // Try exact match
            if (isset($sourceData[$targetField])) {
                $mapped[$targetField] = $sourceData[$targetField];
                continue;
            }

            // Try common variations
            $variations = [
                str_replace('_', '', $targetField),
                str_replace('_', '-', $targetField),
                Str::camel($targetField),
                Str::snake($targetField)
            ];

            foreach ($variations as $variation) {
                if (isset($sourceData[$variation])) {
                    $mapped[$targetField] = $sourceData[$variation];
                    break;
                }
            }
        }

        return $mapped;
    }
} 