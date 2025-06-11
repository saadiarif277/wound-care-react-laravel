<?php

namespace App\Services\ClinicalOpportunityEngine;

use App\Models\ClinicalOpportunity;
use App\Services\SupabaseService;
use App\Services\ProductRecommendationEngine\MSCProductRecommendationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ClinicalOpportunityService
{
    protected $contextBuilder;
    protected $ruleEvaluator;
    protected $supabaseService;
    protected $productRecommendationService;

    public function __construct(
        ClinicalContextBuilderService $contextBuilder,
        ClinicalRuleEvaluatorService $ruleEvaluator,
        SupabaseService $supabaseService,
        MSCProductRecommendationService $productRecommendationService
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->ruleEvaluator = $ruleEvaluator;
        $this->supabaseService = $supabaseService;
        $this->productRecommendationService = $productRecommendationService;
    }

    /**
     * Identify clinical opportunities for a patient
     */
    public function identifyOpportunities(string $patientId, array $options = []): array
    {
        try {
            // Check cache first
            $cacheKey = "clinical_opportunities:{$patientId}";
            if (!($options['force_refresh'] ?? false)) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    return $cached;
                }
            }

            // 1. Build comprehensive patient context
            $context = $this->contextBuilder->buildPatientContext($patientId, $options);

            // 2. Evaluate rules to identify opportunities
            $ruleBasedOpportunities = $this->ruleEvaluator->evaluateRules($context);

            // 3. Enhance with AI if enabled
            $opportunities = $ruleBasedOpportunities;
            if ($options['use_ai'] ?? true) {
                $opportunities = $this->enhanceWithAI($context, $ruleBasedOpportunities);
            }

            // 4. Enrich opportunities with additional data
            $enrichedOpportunities = $this->enrichOpportunities($opportunities, $context);

            // 5. Apply business logic and prioritization
            $finalOpportunities = $this->prioritizeOpportunities($enrichedOpportunities, $options);

            // 6. Store opportunities for tracking
            $this->storeOpportunities($patientId, $finalOpportunities);

            // 7. Cache results
            $result = [
                'success' => true,
                'patient_id' => $patientId,
                'opportunities' => $finalOpportunities,
                'summary' => $this->generateSummary($finalOpportunities),
                'context_snapshot' => $this->createContextSnapshot($context),
                'generated_at' => now()->toISOString()
            ];

            Cache::put($cacheKey, $result, now()->addMinutes(30));

            // 8. Log for analytics
            $this->logOpportunityIdentification($patientId, $finalOpportunities);

            return $result;

        } catch (\Exception $e) {
            Log::error('Clinical opportunity identification failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to identify clinical opportunities',
                'message' => 'An error occurred while analyzing patient data. Please try again.',
                'patient_id' => $patientId
            ];
        }
    }

    /**
     * Get opportunity details with full context
     */
    public function getOpportunityDetails(string $opportunityId): array
    {
        try {
            $opportunity = ClinicalOpportunity::with(['patient', 'provider', 'actions'])
                ->findOrFail($opportunityId);

            // Rebuild context for current state
            $context = $this->contextBuilder->buildPatientContext($opportunity->patient_id);

            // Re-evaluate the specific rule
            $currentEvaluation = $this->ruleEvaluator->evaluateRules($context);
            $stillValid = collect($currentEvaluation)->contains('rule_id', $opportunity->rule_id);

            return [
                'success' => true,
                'opportunity' => $opportunity->toArray(),
                'current_status' => $stillValid ? 'active' : 'resolved',
                'context' => $context,
                'actions_taken' => $this->getActionsTaken($opportunityId),
                'outcomes' => $this->getOpportunityOutcomes($opportunityId)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get opportunity details', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve opportunity details'
            ];
        }
    }

    /**
     * Take action on an opportunity
     */
    public function takeAction(string $opportunityId, array $actionData): array
    {
        try {
            DB::beginTransaction();

            $opportunity = ClinicalOpportunity::findOrFail($opportunityId);

            // Validate action is appropriate
            $validAction = $this->validateAction($opportunity, $actionData);
            if (!$validAction['valid']) {
                return [
                    'success' => false,
                    'error' => $validAction['message']
                ];
            }

            // Execute the action
            $result = $this->executeAction($opportunity, $actionData);

            // Update opportunity status
            $opportunity->update([
                'status' => 'action_taken',
                'last_action_at' => now(),
                'action_count' => $opportunity->action_count + 1
            ]);

            // Record the action
            DB::table('clinical_opportunity_actions')->insert([
                'clinical_opportunity_id' => $opportunityId,
                'action_type' => $actionData['type'],
                'action_data' => json_encode($actionData),
                'result' => json_encode($result),
                'user_id' => $actionData['user_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Action completed successfully',
                'result' => $result
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to take action on opportunity', [
                'opportunity_id' => $opportunityId,
                'action' => $actionData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to complete action'
            ];
        }
    }

    /**
     * Enhance opportunities with AI
     */
    protected function enhanceWithAI(array $context, array $opportunities): array
    {
        try {
            // Call Supabase Edge Function for AI enhancement
            $enhanced = $this->callSupabaseEdgeFunction('clinical-opportunities-ai', [
                'context' => $context,
                'rule_based_opportunities' => $opportunities
            ]);

            return $this->mergeAIEnhancements($opportunities, $enhanced);

        } catch (\Exception $e) {
            Log::warning('AI enhancement failed, using rule-based opportunities only', [
                'error' => $e->getMessage()
            ]);

            return $opportunities;
        }
    }

    /**
     * Call Supabase Edge Function
     */
    protected function callSupabaseEdgeFunction(string $functionName, array $payload): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.supabase.anon_key'),
            'Content-Type' => 'application/json'
        ])->timeout(30)
        ->post(
            config('services.supabase.url') . "/functions/v1/{$functionName}",
            $payload
        );

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Supabase Edge Function call failed');
    }

    /**
     * Enrich opportunities with additional data
     */
    protected function enrichOpportunities(array $opportunities, array $context): array
    {
        return array_map(function($opportunity) use ($context) {
            // Add product recommendations if relevant
            if ($opportunity['type'] === 'non_healing_wound' || 
                in_array('order_product', array_column($opportunity['actions'], 'type'))) {
                
                $opportunity['product_recommendations'] = $this->getProductRecommendations($context);
            }

            // Add cost impact analysis
            $opportunity['cost_impact'] = $this->analyzeCostImpact($opportunity, $context);

            // Add clinical pathways
            $opportunity['clinical_pathways'] = $this->getRelevantPathways($opportunity['type']);

            // Add evidence links
            $opportunity['evidence_links'] = $this->getEvidenceLinks($opportunity);

            // Add risk mitigation strategies
            $opportunity['risk_mitigation'] = $this->getRiskMitigationStrategies($opportunity, $context);

            return $opportunity;
        }, $opportunities);
    }

    /**
     * Prioritize opportunities based on business rules
     */
    protected function prioritizeOpportunities(array $opportunities, array $options): array
    {
        // Apply filters
        if (isset($options['categories'])) {
            $opportunities = array_filter($opportunities, function($opp) use ($options) {
                return in_array($opp['category'], $options['categories']);
            });
        }

        if (isset($options['min_confidence'])) {
            $opportunities = array_filter($opportunities, function($opp) use ($options) {
                return $opp['confidence_score'] >= $options['min_confidence'];
            });
        }

        // Calculate composite scores
        $opportunities = array_map(function($opp) {
            $opp['composite_score'] = $this->calculateCompositeScore($opp);
            return $opp;
        }, $opportunities);

        // Sort by composite score
        usort($opportunities, function($a, $b) {
            return $b['composite_score'] <=> $a['composite_score'];
        });

        // Limit results if specified
        if (isset($options['limit'])) {
            $opportunities = array_slice($opportunities, 0, $options['limit']);
        }

        return array_values($opportunities);
    }

    /**
     * Calculate composite score for prioritization
     */
    protected function calculateCompositeScore(array $opportunity): float
    {
        $weights = [
            'priority' => 0.3,
            'confidence' => 0.2,
            'cost_impact' => 0.2,
            'clinical_impact' => 0.2,
            'ease_of_implementation' => 0.1
        ];

        $score = 0;
        
        // Priority score (normalized to 0-1)
        $score += ($opportunity['priority'] / 10) * $weights['priority'];
        
        // Confidence score
        $score += $opportunity['confidence_score'] * $weights['confidence'];
        
        // Cost impact score
        $costScore = $this->normalizeCostImpact($opportunity['cost_impact'] ?? []);
        $score += $costScore * $weights['cost_impact'];
        
        // Clinical impact score
        $clinicalScore = $this->calculateClinicalImpactScore($opportunity['potential_impact'] ?? []);
        $score += $clinicalScore * $weights['clinical_impact'];
        
        // Ease of implementation
        $easeScore = $this->calculateEaseOfImplementation($opportunity['actions']);
        $score += $easeScore * $weights['ease_of_implementation'];

        return round($score, 3);
    }

    /**
     * Store opportunities in database
     */
    protected function storeOpportunities(string $patientId, array $opportunities): void
    {
        foreach ($opportunities as $opportunity) {
            ClinicalOpportunity::updateOrCreate(
                [
                    'patient_id' => $patientId,
                    'rule_id' => $opportunity['rule_id']
                ],
                [
                    'type' => $opportunity['type'],
                    'category' => $opportunity['category'],
                    'priority' => $opportunity['priority'],
                    'title' => $opportunity['title'],
                    'description' => $opportunity['description'],
                    'confidence_score' => $opportunity['confidence_score'],
                    'composite_score' => $opportunity['composite_score'] ?? null,
                    'data' => json_encode($opportunity),
                    'status' => 'identified',
                    'identified_at' => now()
                ]
            );
        }
    }

    /**
     * Generate summary of opportunities
     */
    protected function generateSummary(array $opportunities): array
    {
        $summary = [
            'total_opportunities' => count($opportunities),
            'by_category' => [],
            'by_priority' => [],
            'urgent_actions' => 0,
            'potential_cost_savings' => 0,
            'top_recommendations' => []
        ];

        foreach ($opportunities as $opportunity) {
            // Count by category
            $category = $opportunity['category'];
            $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;

            // Count by priority
            $priority = $this->getPriorityLabel($opportunity['priority']);
            $summary['by_priority'][$priority] = ($summary['by_priority'][$priority] ?? 0) + 1;

            // Count urgent actions
            foreach ($opportunity['actions'] as $action) {
                if ($action['priority'] === 'urgent') {
                    $summary['urgent_actions']++;
                }
            }

            // Sum potential cost savings
            if (isset($opportunity['cost_impact']['potential_savings'])) {
                $summary['potential_cost_savings'] += $opportunity['cost_impact']['potential_savings'];
            }
        }

        // Get top 3 recommendations
        $summary['top_recommendations'] = array_slice($opportunities, 0, 3);

        return $summary;
    }

    /**
     * Create context snapshot for reference
     */
    protected function createContextSnapshot(array $context): array
    {
        return [
            'demographics' => $context['demographics'] ?? [],
            'active_conditions' => count($context['clinical_data']['conditions'] ?? []),
            'wound_count' => count($context['clinical_data']['wound_data'] ?? []),
            'risk_summary' => array_map(function($risk) {
                return $risk >= 0.7 ? 'high' : ($risk >= 0.4 ? 'moderate' : 'low');
            }, $context['risk_factors'] ?? []),
            'care_gaps_identified' => array_sum(array_map('count', $context['care_gaps'] ?? [])),
            'quality_metrics_summary' => $context['quality_metrics'] ?? []
        ];
    }

    /**
     * Log opportunity identification for analytics
     */
    protected function logOpportunityIdentification(string $patientId, array $opportunities): void
    {
        Log::info('Clinical opportunities identified', [
            'patient_id' => $patientId,
            'opportunity_count' => count($opportunities),
            'categories' => array_unique(array_column($opportunities, 'category')),
            'highest_priority' => !empty($opportunities) ? $opportunities[0]['priority'] : null,
            'urgent_actions' => count(array_filter($opportunities, function($opp) {
                return collect($opp['actions'])->contains('priority', 'urgent');
            }))
        ]);
    }

    // Helper methods
    protected function mergeAIEnhancements(array $original, array $enhanced): array
    {
        // Merge AI enhancements with original opportunities
        $merged = $original;
        
        foreach ($enhanced as $enhancement) {
            $found = false;
            foreach ($merged as &$opportunity) {
                if ($opportunity['rule_id'] === ($enhancement['rule_id'] ?? null)) {
                    // Enhance existing opportunity
                    $opportunity['ai_insights'] = $enhancement['insights'] ?? [];
                    $opportunity['confidence_score'] = max(
                        $opportunity['confidence_score'],
                        $enhancement['confidence'] ?? 0
                    );
                    $found = true;
                    break;
                }
            }
            
            if (!$found && isset($enhancement['type'])) {
                // Add new AI-discovered opportunity
                $enhancement['source'] = 'ai';
                $merged[] = $enhancement;
            }
        }
        
        return $merged;
    }

    protected function getProductRecommendations(array $context): array
    {
        // Get product recommendations based on context
        try {
            $productContext = [
                'wound_type' => $context['clinical_data']['wound_data'][0]['type'] ?? 'unknown',
                'wound_characteristics' => $context['clinical_data']['wound_data'][0] ?? [],
                'patient_factors' => [
                    'age' => $context['demographics']['age'] ?? null,
                    'conditions' => array_column($context['clinical_data']['conditions'], 'category')
                ]
            ];
            
            // This would call the product recommendation service
            return ['status' => 'pending_integration'];
            
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Unable to generate recommendations'];
        }
    }

    protected function analyzeCostImpact(array $opportunity, array $context): array
    {
        $impact = [
            'potential_savings' => 0,
            'intervention_cost' => 0,
            'roi_timeframe' => 'unknown'
        ];

        // Calculate based on opportunity type
        switch ($opportunity['type']) {
            case 'non_healing_wound':
                $impact['potential_savings'] = 3500; // Average savings from preventing complications
                $impact['intervention_cost'] = 800; // Advanced dressing costs
                $impact['roi_timeframe'] = '3-6 months';
                break;
                
            case 'readmission_prevention':
                $impact['potential_savings'] = 12000; // Average readmission cost
                $impact['intervention_cost'] = 2000; // Intensive monitoring
                $impact['roi_timeframe'] = '30 days';
                break;
        }

        return $impact;
    }

    protected function getRelevantPathways(string $opportunityType): array
    {
        // Return clinical pathways relevant to the opportunity
        $pathways = [
            'non_healing_wound' => [
                'name' => 'Chronic Wound Management Protocol',
                'steps' => ['Assessment', 'Debridement', 'Advanced Therapy', 'Monitoring']
            ],
            'diabetic_foot_risk' => [
                'name' => 'Diabetic Foot Prevention Protocol',
                'steps' => ['Risk Assessment', 'Education', 'Preventive Care', 'Regular Monitoring']
            ]
        ];

        return $pathways[$opportunityType] ?? [];
    }

    protected function getEvidenceLinks(array $opportunity): array
    {
        // Return relevant clinical evidence
        return [
            [
                'title' => 'Clinical Guidelines',
                'source' => 'CMS LCD',
                'url' => '#'
            ]
        ];
    }

    protected function getRiskMitigationStrategies(array $opportunity, array $context): array
    {
        // Return strategies to mitigate identified risks
        return [
            'primary' => 'Implement recommended actions immediately',
            'secondary' => 'Schedule follow-up assessment',
            'monitoring' => 'Track progress weekly'
        ];
    }

    protected function normalizeCostImpact(array $costImpact): float
    {
        if (empty($costImpact)) return 0;
        
        $savings = $costImpact['potential_savings'] ?? 0;
        $cost = $costImpact['intervention_cost'] ?? 1;
        
        // ROI-based score
        $roi = ($savings - $cost) / $cost;
        
        // Normalize to 0-1 scale
        return min(1, max(0, $roi / 10));
    }

    protected function calculateClinicalImpactScore(array $impact): float
    {
        if (empty($impact)) return 0.5;
        
        $score = 0;
        
        // Parse impact metrics
        if (isset($impact['healing_acceleration'])) {
            // Extract percentage
            preg_match('/(\d+)-(\d+)%/', $impact['healing_acceleration'], $matches);
            $avgPercent = isset($matches[1]) ? ($matches[1] + $matches[2]) / 2 : 50;
            $score += $avgPercent / 100;
        }
        
        return min(1, $score);
    }

    protected function calculateEaseOfImplementation(array $actions): float
    {
        if (empty($actions)) return 0;
        
        $easeScores = [
            'order_product' => 0.9,
            'schedule_assessment' => 0.8,
            'update_care_plan' => 0.7,
            'refer_specialist' => 0.6
        ];
        
        $totalScore = 0;
        foreach ($actions as $action) {
            $totalScore += $easeScores[$action['type']] ?? 0.5;
        }
        
        return $totalScore / count($actions);
    }

    protected function getPriorityLabel(int $priority): string
    {
        if ($priority >= 9) return 'critical';
        if ($priority >= 7) return 'high';
        if ($priority >= 5) return 'medium';
        return 'low';
    }

    protected function validateAction(ClinicalOpportunity $opportunity, array $actionData): array
    {
        // Validate the action is appropriate for the opportunity
        $validActions = json_decode($opportunity->data, true)['actions'] ?? [];
        $actionTypes = array_column($validActions, 'type');
        
        if (!in_array($actionData['type'], $actionTypes)) {
            return ['valid' => false, 'message' => 'Invalid action type for this opportunity'];
        }
        
        return ['valid' => true];
    }

    protected function executeAction(ClinicalOpportunity $opportunity, array $actionData): array
    {
        // Execute the specific action
        switch ($actionData['type']) {
            case 'order_product':
                return $this->executeProductOrder($opportunity, $actionData);
                
            case 'schedule_assessment':
                return $this->scheduleAssessment($opportunity, $actionData);
                
            case 'refer_specialist':
                return $this->createReferral($opportunity, $actionData);
                
            case 'update_care_plan':
                return $this->updateCarePlan($opportunity, $actionData);
                
            default:
                return ['status' => 'completed', 'message' => 'Action recorded'];
        }
    }

    protected function executeProductOrder($opportunity, $actionData): array
    {
        // This would integrate with the order system
        return ['status' => 'pending', 'message' => 'Product order initiated'];
    }

    protected function scheduleAssessment($opportunity, $actionData): array
    {
        // This would integrate with the scheduling system
        return ['status' => 'scheduled', 'message' => 'Assessment scheduled'];
    }

    protected function createReferral($opportunity, $actionData): array
    {
        // This would create a referral in the system
        return ['status' => 'created', 'message' => 'Referral created'];
    }

    protected function updateCarePlan($opportunity, $actionData): array
    {
        // This would update the patient's care plan
        return ['status' => 'updated', 'message' => 'Care plan updated'];
    }

    protected function getActionsTaken(string $opportunityId): array
    {
        return DB::table('clinical_opportunity_actions')
            ->where('clinical_opportunity_id', $opportunityId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($action) {
                return [
                    'type' => $action->action_type,
                    'data' => json_decode($action->action_data, true),
                    'result' => json_decode($action->result, true),
                    'user_id' => $action->user_id,
                    'created_at' => $action->created_at
                ];
            })
            ->toArray();
    }

    protected function getOpportunityOutcomes(string $opportunityId): array
    {
        // Track outcomes of the opportunity
        return [
            'status' => 'tracking',
            'metrics' => []
        ];
    }
}