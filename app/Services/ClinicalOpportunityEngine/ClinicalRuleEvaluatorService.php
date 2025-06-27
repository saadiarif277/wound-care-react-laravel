<?php

namespace App\Services\ClinicalOpportunityEngine;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClinicalRuleEvaluatorService
{
    protected $rules;
    
    public function __construct()
    {
        $this->rules = $this->loadClinicalOpportunityRules();
    }

    /**
     * Evaluate all rules against patient context
     */
    public function evaluateRules(array $context): array
    {
        $opportunities = [];
        
        // Evaluate each rule category
        foreach ($this->rules as $category => $categoryRules) {
            foreach ($categoryRules as $rule) {
                $evaluation = $this->evaluateRule($rule, $context);
                
                if ($evaluation['triggered']) {
                    $opportunities[] = [
                        'category' => $category,
                        'rule_id' => $rule['id'],
                        'type' => $rule['type'],
                        'priority' => $rule['priority'],
                        'title' => $rule['title'],
                        'description' => $evaluation['description'],
                        'actions' => $evaluation['actions'],
                        'confidence_score' => $evaluation['confidence'],
                        'evidence' => $evaluation['evidence'],
                        'potential_impact' => $rule['potential_impact'] ?? []
                    ];
                }
            }
        }
        
        // Sort opportunities by priority and confidence
        usort($opportunities, function($a, $b) {
            // First sort by priority (higher first)
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority'];
            }
            // Then by confidence score
            return $b['confidence_score'] <=> $a['confidence_score'];
        });
        
        return $opportunities;
    }

    /**
     * Evaluate a single rule
     */
    protected function evaluateRule(array $rule, array $context): array
    {
        $result = [
            'triggered' => false,
            'confidence' => 0.0,
            'description' => '',
            'actions' => [],
            'evidence' => []
        ];
        
        // Evaluate conditions
        $conditionsMet = $this->evaluateConditions($rule['conditions'], $context);
        
        if ($conditionsMet['met']) {
            $result['triggered'] = true;
            $result['confidence'] = $conditionsMet['confidence'];
            $result['evidence'] = $conditionsMet['evidence'];
            
            // Generate personalized description
            $result['description'] = $this->generateDescription($rule, $context, $conditionsMet);
            
            // Generate recommended actions
            $result['actions'] = $this->generateActions($rule, $context);
        }
        
        return $result;
    }

    /**
     * Evaluate rule conditions
     */
    protected function evaluateConditions(array $conditions, array $context): array
    {
        $evidence = [];
        $confidenceScores = [];
        
        foreach ($conditions as $condition) {
            $evaluation = $this->evaluateCondition($condition, $context);
            
            if (!$evaluation['met']) {
                return ['met' => false, 'confidence' => 0.0, 'evidence' => []];
            }
            
            $evidence[] = $evaluation['evidence'];
            $confidenceScores[] = $evaluation['confidence'];
        }
        
        return [
            'met' => true,
            'confidence' => !empty($confidenceScores) ? array_sum($confidenceScores) / count($confidenceScores) : 1.0,
            'evidence' => $evidence
        ];
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $context): array
    {
        $type = $condition['type'];
        
        switch ($type) {
            case 'diagnosis':
                return $this->evaluateDiagnosisCondition($condition, $context);
                
            case 'wound_characteristic':
                return $this->evaluateWoundCondition($condition, $context);
                
            case 'risk_factor':
                return $this->evaluateRiskFactorCondition($condition, $context);
                
            case 'care_gap':
                return $this->evaluateCareGapCondition($condition, $context);
                
            case 'utilization':
                return $this->evaluateUtilizationCondition($condition, $context);
                
            case 'quality_metric':
                return $this->evaluateQualityMetricCondition($condition, $context);
                
            case 'payer':
                return $this->evaluatePayerCondition($condition, $context);
                
            default:
                return ['met' => false, 'confidence' => 0.0, 'evidence' => "Unknown condition type: {$type}"];
        }
    }

    /**
     * Evaluate diagnosis-based conditions
     */
    protected function evaluateDiagnosisCondition(array $condition, array $context): array
    {
        $conditions = $context['clinical_data']['conditions'] ?? [];
        $targetCodes = $condition['icd10_codes'] ?? [];
        $targetCategories = $condition['categories'] ?? [];
        
        foreach ($conditions as $patientCondition) {
            // Check ICD-10 codes
            if (!empty($targetCodes) && in_array($patientCondition['code'], $targetCodes)) {
                return [
                    'met' => true,
                    'confidence' => 1.0,
                    'evidence' => "Patient has diagnosis: {$patientCondition['display']} ({$patientCondition['code']})"
                ];
            }
            
            // Check categories
            if (!empty($targetCategories) && in_array($patientCondition['category'], $targetCategories)) {
                return [
                    'met' => true,
                    'confidence' => 0.9,
                    'evidence' => "Patient has {$patientCondition['category']} diagnosis: {$patientCondition['display']}"
                ];
            }
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    /**
     * Evaluate wound-specific conditions
     */
    protected function evaluateWoundCondition(array $condition, array $context): array
    {
        $wounds = $context['clinical_data']['wound_data'] ?? [];
        
        if (empty($wounds)) {
            return ['met' => false, 'confidence' => 0.0, 'evidence' => 'No wound data available'];
        }
        
        foreach ($wounds as $wound) {
            $met = true;
            $evidenceParts = [];
            
            // Check wound type
            if (isset($condition['wound_type']) && $wound['type'] !== $condition['wound_type']) {
                $met = false;
                continue;
            }
            
            // Check wound size
            if (isset($condition['min_size'])) {
                $size = $wound['size']['length'] * $wound['size']['width'] ?? 0;
                if ($size < $condition['min_size']) {
                    $met = false;
                    continue;
                }
                $evidenceParts[] = "wound size {$size}cm²";
            }
            
            // Check wound duration
            if (isset($condition['min_duration_weeks']) && $wound['duration'] < ($condition['min_duration_weeks'] * 7)) {
                $met = false;
                continue;
            }
            
            // Check healing progress
            if (isset($condition['healing_status']) && $wound['healing_progress'] !== $condition['healing_status']) {
                $met = false;
                continue;
            }
            
            if ($met) {
                $evidence = "Wound meets criteria: " . implode(', ', $evidenceParts);
                return ['met' => true, 'confidence' => 0.95, 'evidence' => $evidence];
            }
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    /**
     * Evaluate risk factor conditions
     */
    protected function evaluateRiskFactorCondition(array $condition, array $context): array
    {
        $riskFactors = $context['risk_factors'] ?? [];
        $riskType = $condition['risk_type'];
        $threshold = $condition['threshold'] ?? 0.5;
        
        if (!isset($riskFactors[$riskType])) {
            return ['met' => false, 'confidence' => 0.0, 'evidence' => "Risk factor {$riskType} not assessed"];
        }
        
        $riskScore = $riskFactors[$riskType];
        
        if ($riskScore >= $threshold) {
            $riskLevel = $riskScore >= 0.8 ? 'high' : ($riskScore >= 0.5 ? 'moderate' : 'low');
            return [
                'met' => true,
                'confidence' => min($riskScore + 0.2, 1.0),
                'evidence' => "Patient has {$riskLevel} {$riskType} (score: " . round($riskScore * 100) . "%)"
            ];
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    /**
     * Evaluate care gap conditions
     */
    protected function evaluateCareGapCondition(array $condition, array $context): array
    {
        $careGaps = $context['care_gaps'] ?? [];
        $gapType = $condition['gap_type'];
        
        $relevantGaps = [];
        
        // Check for specific gap type
        if (isset($careGaps[$gapType]) && !empty($careGaps[$gapType])) {
            $relevantGaps = $careGaps[$gapType];
        }
        
        if (!empty($relevantGaps)) {
            $gapCount = count($relevantGaps);
            $gapDescription = is_array($relevantGaps[0]) ? $relevantGaps[0]['description'] ?? 'Care gap identified' : $relevantGaps[0];
            
            return [
                'met' => true,
                'confidence' => min(0.7 + ($gapCount * 0.1), 1.0),
                'evidence' => "{$gapCount} {$gapType} identified: {$gapDescription}"
            ];
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    /**
     * Generate description for opportunity
     */
    protected function generateDescription(array $rule, array $context, array $evaluation): string
    {
        $template = $rule['description_template'] ?? $rule['title'];
        
        // Replace placeholders with context data
        $replacements = [
            '{patient_age}' => $context['demographics']['age'] ?? 'unknown',
            '{wound_type}' => $context['clinical_data']['wound_data'][0]['type'] ?? 'wound',
            '{risk_level}' => $this->getRiskLevel($context),
            '{payer}' => $context['payer_context']['primary_payer']['name'] ?? 'insurance'
        ];
        
        $description = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        return $description;
    }

    /**
     * Generate recommended actions
     */
    protected function generateActions(array $rule, array $context): array
    {
        $actions = [];
        
        foreach ($rule['actions'] as $actionTemplate) {
            $action = [
                'type' => $actionTemplate['type'],
                'priority' => $actionTemplate['priority'] ?? 'medium',
                'description' => $actionTemplate['description'],
                'details' => []
            ];
            
            // Add context-specific details
            switch ($actionTemplate['type']) {
                case 'order_product':
                    $action['details']['recommended_products'] = $this->getRecommendedProducts($context);
                    break;
                    
                case 'schedule_assessment':
                    $action['details']['assessment_type'] = $actionTemplate['assessment_type'] ?? 'wound_assessment';
                    $action['details']['urgency'] = $this->determineUrgency($context);
                    break;
                    
                case 'refer_specialist':
                    $action['details']['specialty'] = $actionTemplate['specialty'] ?? 'wound_care';
                    $action['details']['reason'] = $actionTemplate['reason'] ?? 'Complex wound requiring specialist evaluation';
                    break;
                    
                case 'update_care_plan':
                    $action['details']['recommendations'] = $this->generateCareRecommendations($context);
                    break;
            }
            
            $actions[] = $action;
        }
        
        return $actions;
    }

    /**
     * Load clinical opportunity rules
     */
    protected function loadClinicalOpportunityRules(): array
    {
        // In a real implementation, these would come from the database
        return [
            'wound_care' => [
                [
                    'id' => 'wc_001',
                    'type' => 'non_healing_wound',
                    'priority' => 9,
                    'title' => 'Non-Healing Wound Requiring Advanced Treatment',
                    'description_template' => 'Patient has a non-healing {wound_type} that may benefit from advanced wound care products',
                    'conditions' => [
                        [
                            'type' => 'wound_characteristic',
                            'min_duration_weeks' => 4,
                            'healing_status' => 'stalled'
                        ],
                        [
                            'type' => 'care_gap',
                            'gap_type' => 'wound_treatments'
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'order_product',
                            'priority' => 'high',
                            'description' => 'Consider advanced wound care products'
                        ],
                        [
                            'type' => 'schedule_assessment',
                            'priority' => 'high',
                            'description' => 'Schedule comprehensive wound assessment',
                            'assessment_type' => 'wound_assessment'
                        ]
                    ],
                    'potential_impact' => [
                        'healing_acceleration' => '40-60%',
                        'cost_savings' => '$2,000-5,000',
                        'quality_improvement' => 'Reduced healing time by 4-6 weeks'
                    ]
                ],
                [
                    'id' => 'wc_002',
                    'type' => 'infection_risk',
                    'priority' => 10,
                    'title' => 'High Infection Risk Requiring Intervention',
                    'description_template' => 'Patient shows high infection risk requiring immediate attention',
                    'conditions' => [
                        [
                            'type' => 'risk_factor',
                            'risk_type' => 'infection_risk',
                            'threshold' => 0.7
                        ],
                        [
                            'type' => 'wound_characteristic',
                            'min_size' => 10
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'order_product',
                            'priority' => 'urgent',
                            'description' => 'Order antimicrobial dressings'
                        ],
                        [
                            'type' => 'refer_specialist',
                            'priority' => 'high',
                            'description' => 'Refer to infectious disease specialist',
                            'specialty' => 'infectious_disease'
                        ]
                    ]
                ]
            ],
            'diabetes_management' => [
                [
                    'id' => 'dm_001',
                    'type' => 'diabetic_foot_risk',
                    'priority' => 8,
                    'title' => 'Diabetic Foot Ulcer Prevention Opportunity',
                    'description_template' => 'Diabetic patient at high risk for foot ulcers',
                    'conditions' => [
                        [
                            'type' => 'diagnosis',
                            'categories' => ['diabetes']
                        ],
                        [
                            'type' => 'risk_factor',
                            'risk_type' => 'diabetes_risk',
                            'threshold' => 0.6
                        ],
                        [
                            'type' => 'care_gap',
                            'gap_type' => 'preventive_care_gaps'
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'schedule_assessment',
                            'priority' => 'high',
                            'description' => 'Schedule diabetic foot screening',
                            'assessment_type' => 'diabetic_foot_exam'
                        ],
                        [
                            'type' => 'update_care_plan',
                            'priority' => 'medium',
                            'description' => 'Add preventive foot care to treatment plan'
                        ]
                    ]
                ]
            ],
            'quality_improvement' => [
                [
                    'id' => 'qi_001',
                    'type' => 'readmission_prevention',
                    'priority' => 7,
                    'title' => 'High Readmission Risk - Intervention Needed',
                    'description_template' => 'Patient at high risk for readmission within 30 days',
                    'conditions' => [
                        [
                            'type' => 'risk_factor',
                            'risk_type' => 'readmission_risk',
                            'threshold' => 0.7
                        ],
                        [
                            'type' => 'utilization',
                            'metric' => 'er_visits_90_days',
                            'threshold' => 2
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'update_care_plan',
                            'priority' => 'high',
                            'description' => 'Implement intensive outpatient monitoring'
                        ],
                        [
                            'type' => 'schedule_assessment',
                            'priority' => 'high',
                            'description' => 'Schedule home health evaluation',
                            'assessment_type' => 'home_health'
                        ]
                    ]
                ]
            ]
        ];
    }

    // Helper methods
    protected function getRiskLevel(array $context): string
    {
        $riskFactors = $context['risk_factors'] ?? [];
        $avgRisk = !empty($riskFactors) ? array_sum($riskFactors) / count($riskFactors) : 0;
        
        if ($avgRisk >= 0.8) return 'high';
        if ($avgRisk >= 0.5) return 'moderate';
        return 'low';
    }

    protected function getRecommendedProducts(array $context): array
    {
        // This would integrate with the product recommendation engine
        return ['Advanced wound dressing', 'Collagen matrix', 'Growth factor therapy'];
    }

    protected function determineUrgency(array $context): string
    {
        $riskLevel = $this->getRiskLevel($context);
        return $riskLevel === 'high' ? 'urgent' : 'routine';
    }

    protected function generateCareRecommendations(array $context): array
    {
        // Generate specific care recommendations based on context
        return [
            'Increase wound assessment frequency',
            'Consider nutritional optimization',
            'Evaluate offloading strategies'
        ];
    }

    protected function evaluateUtilizationCondition(array $condition, array $context): array
    {
        $utilization = $context['care_history']['utilization_metrics'] ?? [];
        $metric = $condition['metric'];
        $threshold = $condition['threshold'];
        
        if (isset($utilization[$metric]) && $utilization[$metric] >= $threshold) {
            return [
                'met' => true,
                'confidence' => 0.9,
                'evidence' => "High utilization: {$metric} = {$utilization[$metric]}"
            ];
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    protected function evaluateQualityMetricCondition(array $condition, array $context): array
    {
        $metrics = $context['quality_metrics'] ?? [];
        $metric = $condition['metric'];
        $operator = $condition['operator'] ?? '<=';
        $threshold = $condition['threshold'];
        
        if (!isset($metrics[$metric])) {
            return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
        }
        
        $value = $metrics[$metric];
        $met = false;
        
        switch ($operator) {
            case '<=':
                $met = $value <= $threshold;
                break;
            case '>=':
                $met = $value >= $threshold;
                break;
            case '<':
                $met = $value < $threshold;
                break;
            case '>':
                $met = $value > $threshold;
                break;
        }
        
        if ($met) {
            return [
                'met' => true,
                'confidence' => 0.85,
                'evidence' => "Quality metric {$metric} is {$value} ({$operator} {$threshold})"
            ];
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }

    protected function evaluatePayerCondition(array $condition, array $context): array
    {
        $payerContext = $context['payer_context'] ?? [];
        
        if (isset($condition['payer_type'])) {
            $primaryPayer = $payerContext['primary_payer']['type'] ?? '';
            if ($primaryPayer === $condition['payer_type']) {
                return [
                    'met' => true,
                    'confidence' => 1.0,
                    'evidence' => "Patient has {$condition['payer_type']} coverage"
                ];
            }
        }
        
        if (isset($condition['requires_prior_auth']) && $condition['requires_prior_auth']) {
            $priorAuth = $payerContext['prior_auth_requirements'] ?? [];
            if (!empty($priorAuth)) {
                return [
                    'met' => true,
                    'confidence' => 0.9,
                    'evidence' => 'Prior authorization requirements identified'
                ];
            }
        }
        
        return ['met' => false, 'confidence' => 0.0, 'evidence' => ''];
    }
}