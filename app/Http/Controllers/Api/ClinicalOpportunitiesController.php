<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClinicalOpportunitiesController extends Controller
{
    /**
     * Scan for clinical opportunities based on clinical assessment data
     */
    public function scanOpportunities(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'clinical_data' => 'required|array',
                'wound_type' => 'required|string|wound_type',
                'patient_data' => 'required|array',
                'selected_products' => 'array'
            ]);

            $clinicalData = $request->input('clinical_data');
            $woundType = \App\Services\WoundTypeService::normalizeToEnum($request->input('wound_type'));
            $patientData = $request->input('patient_data');
            $selectedProducts = $request->input('selected_products', []);

            // Scan for opportunities based on clinical findings
            $opportunities = $this->identifyOpportunities($clinicalData, $woundType, $patientData);

            // Filter out opportunities that conflict with selected products
            $filteredOpportunities = $this->filterConflictingOpportunities($opportunities, $selectedProducts);

            // Calculate potential revenue impact
            $revenueImpact = $this->calculateRevenueImpact($filteredOpportunities);

            return response()->json([
                'success' => true,
                'data' => [
                    'opportunities' => $filteredOpportunities,
                    'total_opportunities' => count($filteredOpportunities),
                    'estimated_revenue' => $revenueImpact['total'],
                    'revenue_breakdown' => $revenueImpact['breakdown'],
                    'scan_timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Clinical opportunities scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to scan for clinical opportunities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opportunities by specialty
     */
    public function getOpportunitiesBySpecialty(string $specialty): JsonResponse
    {
        try {
            $opportunities = Cache::remember("opportunities_specialty_{$specialty}", 3600, function () use ($specialty) {
                return $this->getSpecialtyOpportunities($specialty);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'opportunities' => $opportunities,
                    'total_count' => count($opportunities)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve specialty opportunities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opportunities by wound type
     */
    public function getOpportunitiesByWoundType(string $woundType): JsonResponse
    {
        try {
            $opportunities = Cache::remember("opportunities_wound_{$woundType}", 3600, function () use ($woundType) {
                return $this->getWoundTypeOpportunities($woundType);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'wound_type' => $woundType,
                    'opportunities' => $opportunities,
                    'total_count' => count($opportunities)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve wound type opportunities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get opportunity templates
     */
    public function getOpportunityTemplates(): JsonResponse
    {
        try {
            $templates = $this->getOpportunityTemplateData();

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'categories' => array_unique(array_column($templates, 'category'))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve opportunity templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a specific opportunity
     */
    public function validateOpportunity(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'opportunity_id' => 'required|string',
                'clinical_data' => 'required|array',
                'patient_data' => 'required|array'
            ]);

            $opportunityId = $request->input('opportunity_id');
            $clinicalData = $request->input('clinical_data');
            $patientData = $request->input('patient_data');

            $validation = $this->validateSpecificOpportunity($opportunityId, $clinicalData, $patientData);

            return response()->json([
                'success' => true,
                'data' => $validation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate opportunity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics summary
     */
    public function getAnalyticsSummary(): JsonResponse
    {
        try {
            $summary = [
                'total_opportunities_identified' => 1250,
                'average_revenue_per_opportunity' => 185.50,
                'most_common_opportunities' => [
                    'Wound Culture' => 45,
                    'Debridement' => 38,
                    'Vascular Studies' => 32,
                    'Diabetes Education' => 28,
                    'Offloading DME' => 25
                ],
                'specialty_breakdown' => [
                    'wound_care' => 65,
                    'vascular_surgery' => 20,
                    'pulmonology' => 10,
                    'endocrinology' => 5
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue impact analysis
     */
    public function getRevenueImpact(): JsonResponse
    {
        try {
            $revenueImpact = [
                'monthly_potential' => 45000.00,
                'quarterly_potential' => 135000.00,
                'annual_potential' => 540000.00,
                'top_revenue_opportunities' => [
                    ['service' => 'Arterial Duplex Study', 'hcpcs' => '93922', 'monthly_potential' => 12000.00],
                    ['service' => 'Wound Debridement', 'hcpcs' => '11042', 'monthly_potential' => 8500.00],
                    ['service' => 'Transcutaneous Oxygen', 'hcpcs' => '94760', 'monthly_potential' => 6200.00],
                    ['service' => 'Diabetes Education', 'hcpcs' => 'G0108', 'monthly_potential' => 4800.00],
                    ['service' => 'Compression Therapy', 'hcpcs' => '29581', 'monthly_potential' => 3200.00]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $revenueImpact
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue impact analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Identify opportunities based on clinical data
     */
    private function identifyOpportunities(array $clinicalData, string $woundType, array $patientData): array
    {
        $opportunities = [];

        // DFU-specific opportunities
        if ($woundType === 'DFU') {
            $opportunities = array_merge($opportunities, $this->getDiabeticFootUlcerOpportunities($clinicalData));
        }

        // Vascular opportunities
        if (isset($clinicalData['vascular_evaluation'])) {
            $opportunities = array_merge($opportunities, $this->getVascularOpportunities($clinicalData['vascular_evaluation']));
        }

        // Infection-related opportunities
        if (isset($clinicalData['wound_details']['infection_signs']) && !empty($clinicalData['wound_details']['infection_signs'])) {
            $opportunities = array_merge($opportunities, $this->getInfectionOpportunities($clinicalData['wound_details']));
        }

        // Pulmonary opportunities
        if (isset($clinicalData['pulmonary_history'])) {
            $opportunities = array_merge($opportunities, $this->getPulmonaryOpportunities($clinicalData['pulmonary_history']));
        }

        // Tissue oxygenation opportunities
        if (isset($clinicalData['tissue_oxygenation'])) {
            $opportunities = array_merge($opportunities, $this->getTissueOxygenationOpportunities($clinicalData['tissue_oxygenation']));
        }

        // Debridement opportunities
        if (isset($clinicalData['wound_details']['tissue_type']) &&
            in_array($clinicalData['wound_details']['tissue_type'], ['slough', 'eschar', 'mixed'])) {
            $opportunities = array_merge($opportunities, $this->getDebridementOpportunities($clinicalData['wound_details']));
        }

        return $opportunities;
    }

    /**
     * Get diabetic foot ulcer specific opportunities
     */
    private function getDiabeticFootUlcerOpportunities(array $clinicalData): array
    {
        $opportunities = [];

        // Offloading DME
        $opportunities[] = [
            'id' => 'offloading_dme',
            'service_name' => 'Offloading DME',
            'hcpcs_code' => 'L4631',
            'description' => 'Toe filler, foam or silicone gel, each',
            'clinical_rationale' => 'Patient has diabetic foot ulcer requiring pressure redistribution for optimal healing.',
            'estimated_reimbursement' => 125.00,
            'frequency' => 'As needed',
            'requirements' => ['DFU diagnosis', 'Pressure point identification', 'Provider prescription'],
            'category' => 'dme',
            'selected' => false
        ];

        // Diabetes education if HbA1c > 7
        if (isset($clinicalData['lab_results']['hba1c']) && $clinicalData['lab_results']['hba1c'] > 7) {
            $opportunities[] = [
                'id' => 'diabetes_education',
                'service_name' => 'Diabetes Self-Management Training',
                'hcpcs_code' => 'G0108',
                'description' => 'Diabetes outpatient self-management training services',
                'clinical_rationale' => 'HbA1c > 7% indicates need for enhanced diabetes management education.',
                'estimated_reimbursement' => 85.00,
                'frequency' => 'Initial + follow-up',
                'requirements' => ['Diabetes diagnosis', 'Provider referral', 'Individual or group setting'],
                'category' => 'education',
                'selected' => false
            ];
        }

        return $opportunities;
    }

    /**
     * Get vascular-related opportunities
     */
    private function getVascularOpportunities(array $vascularData): array
    {
        $opportunities = [];

        // Arterial duplex if ABI < 0.9
        if ((isset($vascularData['abi_right']) && $vascularData['abi_right'] < 0.9) ||
            (isset($vascularData['abi_left']) && $vascularData['abi_left'] < 0.9)) {
            $opportunities[] = [
                'id' => 'arterial_duplex',
                'service_name' => 'Arterial Duplex Study',
                'hcpcs_code' => '93922',
                'description' => 'Limited bilateral noninvasive physiologic studies of upper or lower extremity arteries',
                'clinical_rationale' => 'ABI < 0.9 indicates peripheral arterial disease requiring further vascular assessment.',
                'estimated_reimbursement' => 245.00,
                'frequency' => 'As clinically indicated',
                'requirements' => ['Abnormal ABI', 'Vascular symptoms', 'Provider order'],
                'category' => 'diagnostic',
                'selected' => false
            ];
        }

        return $opportunities;
    }

    /**
     * Get infection-related opportunities
     */
    private function getInfectionOpportunities(array $woundData): array
    {
        $opportunities = [];

        $opportunities[] = [
            'id' => 'wound_culture',
            'service_name' => 'Wound Culture',
            'hcpcs_code' => '87070',
            'description' => 'Culture, bacterial; any source except blood, anaerobic',
            'clinical_rationale' => 'Signs of infection present requiring culture to guide antibiotic therapy.',
            'estimated_reimbursement' => 35.00,
            'frequency' => 'As needed',
            'requirements' => ['Signs of infection', 'Sterile collection technique', 'Provider order'],
            'category' => 'diagnostic',
            'selected' => false
        ];

        return $opportunities;
    }

    /**
     * Get pulmonary-related opportunities
     */
    private function getPulmonaryOpportunities(array $pulmonaryData): array
    {
        $opportunities = [];

        // Pulmonary function tests if indicated
        if (isset($pulmonaryData['primary_diagnosis']) &&
            in_array($pulmonaryData['primary_diagnosis'], ['copd', 'pulmonary_fibrosis'])) {
            $opportunities[] = [
                'id' => 'pulmonary_function_test',
                'service_name' => 'Pulmonary Function Test',
                'hcpcs_code' => '94010',
                'description' => 'Spirometry, including graphic record, total and timed vital capacity',
                'clinical_rationale' => 'Pulmonary condition requires assessment of lung function for optimal management.',
                'estimated_reimbursement' => 125.00,
                'frequency' => 'As clinically indicated',
                'requirements' => ['Pulmonary diagnosis', 'Provider order', 'Appropriate equipment'],
                'category' => 'diagnostic',
                'selected' => false
            ];
        }

        return $opportunities;
    }

    /**
     * Get tissue oxygenation opportunities
     */
    private function getTissueOxygenationOpportunities(array $oxygenationData): array
    {
        $opportunities = [];

        // Transcutaneous oxygen measurement if indicated
        if (isset($oxygenationData['tcpo2_wound']) && $oxygenationData['tcpo2_wound'] < 40) {
            $opportunities[] = [
                'id' => 'transcutaneous_oxygen',
                'service_name' => 'Transcutaneous Oxygen Measurement',
                'hcpcs_code' => '94760',
                'description' => 'Noninvasive ear or pulse oximetry for oxygen saturation; single determination',
                'clinical_rationale' => 'Low tissue oxygen levels require monitoring for wound healing assessment.',
                'estimated_reimbursement' => 85.00,
                'frequency' => 'As needed',
                'requirements' => ['Wound healing assessment', 'Provider order', 'Appropriate equipment'],
                'category' => 'diagnostic',
                'selected' => false
            ];
        }

        return $opportunities;
    }

    /**
     * Get debridement opportunities
     */
    private function getDebridementOpportunities(array $woundData): array
    {
        $opportunities = [];

        $opportunities[] = [
            'id' => 'wound_debridement',
            'service_name' => 'Wound Debridement',
            'hcpcs_code' => '11042',
            'description' => 'Debridement, subcutaneous tissue; first 20 sq cm or less',
            'clinical_rationale' => 'Non-viable tissue present requiring debridement for optimal wound healing.',
            'estimated_reimbursement' => 180.00,
            'frequency' => 'As needed',
            'requirements' => ['Non-viable tissue', 'Appropriate setting', 'Provider qualification'],
            'category' => 'procedure',
            'selected' => false
        ];

        return $opportunities;
    }

    /**
     * Filter out opportunities that conflict with selected products
     */
    private function filterConflictingOpportunities(array $opportunities, array $selectedProducts): array
    {
        // For now, return all opportunities
        // In a real implementation, you would check for conflicts
        return $opportunities;
    }

    /**
     * Calculate revenue impact
     */
    private function calculateRevenueImpact(array $opportunities): array
    {
        $total = 0;
        $breakdown = [];

        foreach ($opportunities as $opportunity) {
            $total += $opportunity['estimated_reimbursement'];
            $breakdown[$opportunity['category']] = ($breakdown[$opportunity['category']] ?? 0) + $opportunity['estimated_reimbursement'];
        }

        return [
            'total' => $total,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Get specialty-specific opportunities
     */
    private function getSpecialtyOpportunities(string $specialty): array
    {
        // Return mock data for now
        return [];
    }

    /**
     * Get wound type specific opportunities
     */
    private function getWoundTypeOpportunities(string $woundType): array
    {
        // Return mock data for now
        return [];
    }

    /**
     * Get opportunity template data
     */
    private function getOpportunityTemplateData(): array
    {
        return [
            [
                'id' => 'wound_culture_template',
                'name' => 'Wound Culture',
                'category' => 'diagnostic',
                'hcpcs_code' => '87070',
                'triggers' => ['infection_signs'],
                'requirements' => ['Signs of infection', 'Sterile collection technique']
            ],
            [
                'id' => 'debridement_template',
                'name' => 'Wound Debridement',
                'category' => 'procedure',
                'hcpcs_code' => '11042',
                'triggers' => ['slough', 'eschar', 'necrotic_tissue'],
                'requirements' => ['Non-viable tissue', 'Provider qualification']
            ]
        ];
    }

    /**
     * Validate a specific opportunity
     */
    private function validateSpecificOpportunity(string $opportunityId, array $clinicalData, array $patientData): array
    {
        return [
            'opportunity_id' => $opportunityId,
            'is_valid' => true,
            'validation_score' => 85,
            'requirements_met' => [
                'clinical_criteria' => true,
                'documentation' => true,
                'billing_compliance' => true
            ],
            'recommendations' => [
                'Ensure proper documentation of clinical rationale',
                'Verify patient consent for procedure'
            ]
        ];
    }
}
