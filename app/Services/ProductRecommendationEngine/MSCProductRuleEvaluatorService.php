<?php

namespace App\Services\ProductRecommendationEngine;

use App\Models\Order\MscProductRecommendationRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class MSCProductRuleEvaluatorService
{
    /**
     * Evaluate recommendation rules against context
     */
    public function evaluateRules(array $context): array
    {
        try {
            // Get applicable rules for the wound type
            $rules = $this->getApplicableRules($context);

            if ($rules->isEmpty()) {
                Log::warning('No applicable rules found', [
                    'wound_type' => $context['wound_type'] ?? 'unknown',
                    'context_keys' => array_keys($context)
                ]);

                return $this->getFallbackRecommendations($context);
            }

            // Evaluate each rule and collect recommendations
            $recommendations = [];

            foreach ($rules as $rule) {
                if ($this->ruleMatches($rule, $context)) {
                    $ruleRecommendations = $this->generateRecommendationsFromRule($rule, $context);
                    $recommendations = array_merge($recommendations, $ruleRecommendations);
                }
            }

            // Deduplicate and rank recommendations
            $finalRecommendations = $this->consolidateRecommendations($recommendations);

            Log::info('Rule evaluation completed', [
                'rules_evaluated' => $rules->count(),
                'recommendations_generated' => count($finalRecommendations),
                'wound_type' => $context['wound_type'] ?? 'unknown'
            ]);

            return $finalRecommendations;

        } catch (\Exception $e) {
            Log::error('Rule evaluation failed', [
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return $this->getFallbackRecommendations($context);
        }
    }

    /**
     * Get rules applicable to the current context
     */
    protected function getApplicableRules(array $context): Collection
    {
        $woundType = $context['wound_type'] ?? null;

        return MscProductRecommendationRule::active()
            ->current()
            ->forWoundType($woundType)
            ->orderByPriority()
            ->get();
    }

    /**
     * Check if a rule matches the current context
     */
    protected function ruleMatches(MscProductRecommendationRule $rule, array $context): bool
    {
        try {
            // Check if rule matches context conditions
            if (!$rule->matchesContext($context)) {
                return false;
            }

            // Check for contraindications
            if ($rule->hasContraindications($context)) {
                Log::info('Rule excluded due to contraindications', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::warning('Error evaluating rule match', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate recommendations from a matched rule
     */
    protected function generateRecommendationsFromRule(MscProductRecommendationRule $rule, array $context): array
    {
        $products = $rule->getRecommendedProducts();
        $recommendations = [];

        foreach ($products as $productRec) {
            $qCode = $productRec['q_code'] ?? null;

            if (!$qCode) {
                continue;
            }

            $recommendation = [
                'q_code' => $qCode,
                'rank' => $productRec['rank'] ?? 999,
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'confidence_score' => $productRec['confidence'] ?? 0.8,
                'reasoning' => $rule->generateReasoning($qCode, $context),
                'suggested_size' => $this->calculateSuggestedSize($rule, $context),
                'key_benefits' => $productRec['key_benefits'] ?? [],
                'clinical_evidence' => $rule->clinical_evidence,
                'contraindications' => $rule->contraindications ?? []
            ];

            $recommendations[] = $recommendation;
        }

        return $recommendations;
    }

    /**
     * Calculate suggested product size based on wound characteristics
     */
    protected function calculateSuggestedSize(MscProductRecommendationRule $rule, array $context): float
    {
        $sizeKey = $rule->default_size_suggestion_key ?? 'MATCH_WOUND_AREA';
        $woundCharacteristics = $context['wound_characteristics'] ?? [];

        switch ($sizeKey) {
            case 'MATCH_WOUND_AREA':
                return $this->calculateWoundAreaSize($woundCharacteristics);

            case 'WOUND_AREA_PLUS_MARGIN':
                return $this->calculateWoundAreaSize($woundCharacteristics) * 1.5;

            case 'STANDARD_2x2':
                return 4.0;

            case 'STANDARD_4x4':
                return 16.0;

            case 'LARGE_WOUND':
                return max(20.0, $this->calculateWoundAreaSize($woundCharacteristics));

            default:
                return $this->calculateWoundAreaSize($woundCharacteristics);
        }
    }

    /**
     * Calculate wound area from characteristics
     */
    protected function calculateWoundAreaSize(array $woundCharacteristics): float
    {
        // Try to get direct area measurement
        if (isset($woundCharacteristics['wound_size_cm2']) && $woundCharacteristics['wound_size_cm2'] > 0) {
            return (float) $woundCharacteristics['wound_size_cm2'];
        }

        // Calculate from length and width
        $length = $woundCharacteristics['wound_length_cm'] ?? null;
        $width = $woundCharacteristics['wound_width_cm'] ?? null;

        if ($length && $width && $length > 0 && $width > 0) {
            return (float) ($length * $width);
        }

        // Default size for unknown wounds
        return 4.0; // 2cm x 2cm default
    }

    /**
     * Consolidate and rank recommendations from multiple rules
     */
    protected function consolidateRecommendations(array $recommendations): array
    {
        if (empty($recommendations)) {
            return [];
        }

        // Group by Q-code
        $grouped = [];
        foreach ($recommendations as $rec) {
            $qCode = $rec['q_code'];

            if (!isset($grouped[$qCode])) {
                $grouped[$qCode] = $rec;
            } else {
                // Merge recommendations for the same product
                $existing = $grouped[$qCode];

                // Use the highest confidence score
                if ($rec['confidence_score'] > $existing['confidence_score']) {
                    $grouped[$qCode] = $rec;
                }
            }
        }

        // Convert back to array and sort by rank and confidence
        $consolidated = array_values($grouped);

        usort($consolidated, function ($a, $b) {
            // First sort by rank (lower is better)
            if ($a['rank'] !== $b['rank']) {
                return $a['rank'] <=> $b['rank'];
            }

            // Then by confidence score (higher is better)
            return $b['confidence_score'] <=> $a['confidence_score'];
        });

        // Limit to top 6 recommendations
        return array_slice($consolidated, 0, 6);
    }

    /**
     * Get fallback recommendations when no rules match
     */
    protected function getFallbackRecommendations(array $context): array
    {
        $woundType = $context['wound_type'] ?? 'OTHER';

        // Basic fallback recommendations by wound type
        $fallbackMap = [
            'DFU' => [
                ['q_code' => 'Q4158', 'rank' => 1, 'confidence_score' => 0.6],
                ['q_code' => 'Q4145', 'rank' => 2, 'confidence_score' => 0.5],
                ['q_code' => 'Q4100', 'rank' => 3, 'confidence_score' => 0.4]
            ],
            'VLU' => [
                ['q_code' => 'Q4158', 'rank' => 1, 'confidence_score' => 0.6],
                ['q_code' => 'Q4145', 'rank' => 2, 'confidence_score' => 0.5]
            ],
            'PU' => [
                ['q_code' => 'Q4145', 'rank' => 1, 'confidence_score' => 0.6],
                ['q_code' => 'Q4100', 'rank' => 2, 'confidence_score' => 0.5]
            ],
            'TW' => [
                ['q_code' => 'Q4100', 'rank' => 1, 'confidence_score' => 0.6],
                ['q_code' => 'Q4158', 'rank' => 2, 'confidence_score' => 0.5]
            ],
            'AU' => [
                ['q_code' => 'Q4158', 'rank' => 1, 'confidence_score' => 0.5],
                ['q_code' => 'Q4145', 'rank' => 2, 'confidence_score' => 0.4]
            ]
        ];

        $fallbacks = $fallbackMap[$woundType] ?? $fallbackMap['DFU'];

        return array_map(function ($fallback) use ($context) {
            return [
                'q_code' => $fallback['q_code'],
                'rank' => $fallback['rank'],
                'rule_id' => null,
                'rule_name' => 'Fallback Recommendation',
                'confidence_score' => $fallback['confidence_score'],
                'reasoning' => "Basic recommendation for {$context['wound_type']} wounds when specific clinical rules are not available.",
                'suggested_size' => $this->calculateWoundAreaSize($context['wound_characteristics'] ?? []),
                'key_benefits' => ['Standard care option', 'Clinically appropriate'],
                'clinical_evidence' => null,
                'contraindications' => []
            ];
        }, $fallbacks);
    }
}
