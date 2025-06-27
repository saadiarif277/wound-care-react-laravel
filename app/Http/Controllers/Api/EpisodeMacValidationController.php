<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\MacValidationService;
use App\Services\CmsCoverageApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EpisodeMacValidationController extends Controller
{
    private MacValidationService $macValidationService;
    private CmsCoverageApiService $cmsApiService;

    public function __construct(
        MacValidationService $macValidationService,
        CmsCoverageApiService $cmsApiService
    ) {
        $this->macValidationService = $macValidationService;
        $this->cmsApiService = $cmsApiService;
    }

    /**
     * Get MAC validation data for an episode
     */
    public function show($episodeId)
    {
        try {
            $episode = PatientManufacturerIVREpisode::with([
                'orders.orderItems.product',
                'orders.facility',
                'manufacturer'
            ])->findOrFail($episodeId);

            // Cache key for this episode's MAC validation
            $cacheKey = "mac_validation_episode_{$episodeId}";
            
            // Check cache first (valid for 4 hours)
            $validationData = Cache::remember($cacheKey, 14400, function () use ($episode) {
                return $this->generateMacValidationData($episode);
            });

            return response()->json([
                'success' => true,
                'data' => $validationData
            ]);

        } catch (\Exception $e) {
            Log::error('Episode MAC validation error', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate MAC validation data'
            ], 500);
        }
    }

    /**
     * Generate comprehensive MAC validation data for an episode
     */
    private function generateMacValidationData($episode)
    {
        // Get the facility state from the first order
        $facilityState = $episode->orders->first()?->facility?->state ?? 'TX';
        
        // Get MAC contractor info
        $contractorInfo = $this->macValidationService->getMacContractorByState($facilityState);
        
        // Aggregate all products across orders
        $allProducts = $episode->orders->flatMap(function ($order) {
            return $order->orderItems->map(function ($item) {
                return [
                    'product' => $item->product,
                    'hcpcs_code' => $item->product->hcpcs_code ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price
                ];
            });
        });

        // Calculate risk factors
        $riskAnalysis = $this->analyzeEpisodeRisk($episode, $allProducts, $contractorInfo);
        
        // Check LCD compliance
        $lcdCompliance = $this->checkLcdCompliance($allProducts, $contractorInfo);
        
        // Calculate financial impact
        $financialImpact = $this->calculateFinancialImpact($episode, $riskAnalysis);
        
        // Generate recommendations
        $recommendations = $this->generateRecommendations($riskAnalysis, $lcdCompliance);

        return [
            'risk_score' => $riskAnalysis['score'],
            'risk_level' => $riskAnalysis['level'],
            'coverage_status' => $this->determineCoverageStatus($lcdCompliance, $allProducts),
            'contractor' => [
                'name' => $contractorInfo['contractor'],
                'jurisdiction' => $contractorInfo['jurisdiction']
            ],
            'lcd_compliance' => $lcdCompliance,
            'denial_prediction' => [
                'probability' => $riskAnalysis['denial_probability'],
                'top_risk_factors' => $riskAnalysis['top_factors']
            ],
            'financial_impact' => $financialImpact,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Analyze risk factors for the episode
     */
    private function analyzeEpisodeRisk($episode, $products, $contractorInfo)
    {
        $riskScore = 0;
        $riskFactors = [];

        // Check for high-risk products (skin substitutes, biologics)
        $highRiskProducts = $products->filter(function ($item) {
            $hcpcs = $item['hcpcs_code'] ?? '';
            return in_array(substr($hcpcs, 0, 3), ['Q41', '152', '157']); // Common wound care HCPCS prefixes
        });

        if ($highRiskProducts->isNotEmpty()) {
            $riskScore += 30;
            $riskFactors[] = [
                'factor' => 'High-risk product category',
                'impact' => 'high',
                'mitigation' => 'Ensure complete documentation and prior authorization if required'
            ];
        }

        // Check episode value (high-value episodes get more scrutiny)
        $totalValue = $episode->total_order_value ?? 0;
        if ($totalValue > 5000) {
            $riskScore += 20;
            $riskFactors[] = [
                'factor' => 'High episode value ($' . number_format($totalValue, 2) . ')',
                'impact' => 'medium',
                'mitigation' => 'Consider splitting into multiple smaller claims if clinically appropriate'
            ];
        }

        // Check for multiple orders in short timeframe
        $orderCount = $episode->orders->count();
        if ($orderCount > 3) {
            $riskScore += 15;
            $riskFactors[] = [
                'factor' => 'Multiple orders in episode (' . $orderCount . ' orders)',
                'impact' => 'medium',
                'mitigation' => 'Document medical necessity for frequency of treatment'
            ];
        }

        // Check contractor-specific risk factors
        if (in_array($contractorInfo['jurisdiction'], ['JL', 'JJ'])) {
            // Novitas and Palmetto are known for stricter reviews
            $riskScore += 10;
            $riskFactors[] = [
                'factor' => 'Strict MAC jurisdiction (' . $contractorInfo['jurisdiction'] . ')',
                'impact' => 'low',
                'mitigation' => 'Follow LCD guidelines precisely'
            ];
        }

        // Determine risk level
        $riskLevel = 'low';
        if ($riskScore >= 70) {
            $riskLevel = 'critical';
        } elseif ($riskScore >= 50) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 30) {
            $riskLevel = 'medium';
        }

        return [
            'score' => min($riskScore, 100),
            'level' => $riskLevel,
            'denial_probability' => $riskScore / 100,
            'top_factors' => array_slice($riskFactors, 0, 3)
        ];
    }

    /**
     * Check LCD compliance for products
     */
    private function checkLcdCompliance($products, $contractorInfo)
    {
        $status = 'compliant';
        $missingCriteria = [];
        $documentationRequired = [];

        // Check each product against LCD requirements
        foreach ($products as $item) {
            $hcpcs = $item['hcpcs_code'] ?? '';
            
            // Skin substitute products require specific documentation
            if (substr($hcpcs, 0, 3) === 'Q41') {
                $documentationRequired[] = 'Failed conservative treatment documentation';
                $documentationRequired[] = 'Wound measurements and photos';
                $status = 'partial';
            }
            
            // CTPs require prior authorization in many jurisdictions
            if (in_array($hcpcs, ['15271', '15272', '15273', '15274'])) {
                $documentationRequired[] = 'Prior authorization required';
                $missingCriteria[] = 'Prior auth not verified';
                $status = 'partial';
            }
        }

        return [
            'status' => empty($missingCriteria) && empty($documentationRequired) ? 'compliant' : $status,
            'missing_criteria' => array_unique($missingCriteria),
            'documentation_required' => array_unique($documentationRequired)
        ];
    }

    /**
     * Calculate financial impact
     */
    private function calculateFinancialImpact($episode, $riskAnalysis)
    {
        $totalValue = $episode->total_order_value ?? 0;
        $denialProbability = $riskAnalysis['denial_probability'];
        
        return [
            'potential_denial_amount' => round($totalValue * $denialProbability, 2),
            'approval_confidence' => round((1 - $denialProbability) * 100),
            'estimated_reimbursement' => round($totalValue * (1 - $denialProbability), 2)
        ];
    }

    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations($riskAnalysis, $lcdCompliance)
    {
        $recommendations = [];

        if ($riskAnalysis['level'] === 'critical' || $riskAnalysis['level'] === 'high') {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Consider prior authorization before submission',
                'impact' => 'Reduces denial risk by 40-60%'
            ];
        }

        if ($lcdCompliance['status'] !== 'compliant') {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Complete all required documentation: ' . implode(', ', $lcdCompliance['documentation_required']),
                'impact' => 'Required for claim approval'
            ];
        }

        if ($riskAnalysis['score'] > 30) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Review order against current LCD guidelines',
                'impact' => 'Ensures compliance with latest coverage policies'
            ];
        }

        // Always include a proactive recommendation
        $recommendations[] = [
            'priority' => 'low',
            'action' => 'Set up automated LCD monitoring for these products',
            'impact' => 'Stay ahead of coverage changes'
        ];

        return array_slice($recommendations, 0, 3); // Return top 3 recommendations
    }

    /**
     * Determine overall coverage status
     */
    private function determineCoverageStatus($lcdCompliance, $products)
    {
        // Check if any products require prior auth
        $requiresPriorAuth = $products->contains(function ($item) {
            $hcpcs = $item['hcpcs_code'] ?? '';
            return in_array($hcpcs, ['15271', '15272', '15273', '15274']);
        });

        if ($requiresPriorAuth) {
            return 'requires_prior_auth';
        }

        if ($lcdCompliance['status'] === 'compliant') {
            return 'covered';
        }

        if ($lcdCompliance['status'] === 'partial') {
            return 'conditional';
        }

        return 'not_covered';
    }
}
