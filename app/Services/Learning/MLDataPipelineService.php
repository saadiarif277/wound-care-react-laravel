<?php

namespace App\Services\Learning;

use App\Models\Learning\BehavioralEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * ML Data Pipeline Service
 * 
 * Processes raw behavioral events into structured features for ML training:
 * - Feature extraction and engineering
 * - Sequence pattern recognition
 * - User behavior clustering
 * - Temporal pattern analysis
 * - A/B test result analysis
 */
class MLDataPipelineService
{
    /**
     * Extract features for user behavior prediction
     */
    public function extractUserBehaviorFeatures(int $userId, int $days = 30): array
    {
        $events = BehavioralEvent::forUser($userId)->recent($days)->get();
        
        if ($events->isEmpty()) {
            return $this->getDefaultFeatures();
        }

        return [
            // Basic activity metrics
            'total_events' => $events->count(),
            'active_days' => $events->groupBy(fn($e) => $e->created_at->format('Y-m-d'))->count(),
            'avg_session_length' => $this->calculateAverageSessionLength($events),
            'peak_activity_hour' => $this->findPeakActivityHour($events),
            
            // Form interaction patterns
            'form_completion_rate' => $this->calculateFormCompletionRate($events),
            'avg_form_fill_time' => $this->calculateAverageFormFillTime($events),
            'validation_error_rate' => $this->calculateValidationErrorRate($events),
            'field_abandon_rate' => $this->calculateFieldAbandonRate($events),
            
            // Workflow patterns
            'workflow_completion_rate' => $this->calculateWorkflowCompletionRate($events),
            'avg_workflow_steps' => $this->calculateAverageWorkflowSteps($events),
            'workflow_back_step_rate' => $this->calculateBackStepRate($events),
            'quick_request_success_rate' => $this->calculateQuickRequestSuccessRate($events),
            
            // Decision patterns
            'follows_recommendations' => $this->calculateRecommendationFollowRate($events),
            'decision_confidence' => $this->calculateDecisionConfidence($events),
            'product_selection_patterns' => $this->extractProductSelectionPatterns($events),
            'clinical_decision_patterns' => $this->extractClinicalDecisionPatterns($events),
            
            // Search and navigation
            'search_refinement_rate' => $this->calculateSearchRefinementRate($events),
            'navigation_efficiency' => $this->calculateNavigationEfficiency($events),
            'help_seeking_behavior' => $this->calculateHelpSeekingBehavior($events),
            
            // AI interaction patterns
            'ai_acceptance_rate' => $this->calculateAIAcceptanceRate($events),
            'ai_modification_rate' => $this->calculateAIModificationRate($events),
            'ai_satisfaction_score' => $this->calculateAISatisfactionScore($events),
            
            // Error and recovery patterns
            'error_recovery_success_rate' => $this->calculateErrorRecoveryRate($events),
            'frustration_indicators' => $this->calculateFrustrationIndicators($events),
            'support_contact_rate' => $this->calculateSupportContactRate($events),
            
            // Device and context patterns
            'device_preferences' => $this->extractDevicePreferences($events),
            'browser_performance' => $this->extractBrowserPerformance($events),
            'time_of_day_patterns' => $this->extractTimeOfDayPatterns($events),
        ];
    }

    /**
     * Extract sequence patterns for workflow optimization
     */
    public function extractSequencePatterns(array $userIds = [], int $days = 30): array
    {
        $query = BehavioralEvent::recent($days);
        
        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }
        
        $events = $query->orderBy('timestamp')->get();
        
        return [
            'common_sequences' => $this->findCommonSequences($events),
            'successful_sequences' => $this->findSuccessfulSequences($events),
            'abandoned_sequences' => $this->findAbandonedSequences($events),
            'optimal_step_order' => $this->findOptimalStepOrder($events),
            'bottleneck_points' => $this->findBottleneckPoints($events),
        ];
    }

    /**
     * Process events for real-time recommendations
     */
    public function processForRealtimeRecommendations(int $userId): array
    {
        $recentEvents = Cache::get("recent_events:{$userId}", []);
        
        if (empty($recentEvents)) {
            return $this->getDefaultRecommendations();
        }

        return [
            'next_likely_action' => $this->predictNextAction($recentEvents),
            'recommended_products' => $this->recommendProducts($recentEvents),
            'form_suggestions' => $this->suggestFormOptimizations($recentEvents),
            'workflow_shortcuts' => $this->suggestWorkflowShortcuts($recentEvents),
            'personalization_settings' => $this->suggestPersonalizationSettings($recentEvents),
        ];
    }

    /**
     * Extract features for A/B testing analysis
     */
    public function extractABTestFeatures(string $testName, int $days = 30): array
    {
        $events = BehavioralEvent::recent($days)
            ->whereJsonContains('context->ab_test_group', $testName)
            ->get();

        return [
            'test_name' => $testName,
            'total_participants' => $events->unique('user_id')->count(),
            'group_performance' => $this->analyzeGroupPerformance($events),
            'conversion_rates' => $this->calculateConversionRates($events),
            'engagement_metrics' => $this->calculateEngagementMetrics($events),
            'statistical_significance' => $this->calculateStatisticalSignificance($events),
        ];
    }

    /**
     * Build training dataset for ML models
     */
    public function buildTrainingDataset(array $options = []): array
    {
        $days = $options['days'] ?? 90;
        $userIds = $options['user_ids'] ?? [];
        $features = $options['features'] ?? ['all'];
        
        $trainingData = [];
        
        $query = BehavioralEvent::recent($days);
        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }
        
        $userGroups = $query->get()->groupBy('user_id');
        
        foreach ($userGroups as $userId => $userEvents) {
            $userFeatures = $this->extractUserBehaviorFeatures($userId, $days);
            
            // Add outcome labels for supervised learning
            $userFeatures['labels'] = [
                'high_engagement' => $this->isHighEngagementUser($userEvents),
                'successful_workflow_completion' => $this->hasSuccessfulWorkflows($userEvents),
                'product_selection_accuracy' => $this->hasAccurateProductSelections($userEvents),
                'form_completion_likelihood' => $this->getFormCompletionLikelihood($userEvents),
            ];
            
            $trainingData[] = $userFeatures;
        }
        
        return [
            'dataset' => $trainingData,
            'metadata' => [
                'total_users' => count($trainingData),
                'date_range' => [
                    'start' => now()->subDays($days)->toDateString(),
                    'end' => now()->toDateString()
                ],
                'features_extracted' => array_keys($trainingData[0] ?? []),
                'data_quality_score' => $this->calculateDataQualityScore($trainingData),
            ]
        ];
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function getDefaultFeatures(): array
    {
        return [
            'total_events' => 0,
            'active_days' => 0,
            'avg_session_length' => 0,
            'form_completion_rate' => 0,
            'workflow_completion_rate' => 0,
            'follows_recommendations' => 0,
            'ai_acceptance_rate' => 0,
            'error_recovery_success_rate' => 1.0, // Assume good until proven otherwise
        ];
    }

    private function calculateAverageSessionLength(Collection $events): float
    {
        $sessions = $events->groupBy('session_id');
        $sessionLengths = $sessions->map(function ($sessionEvents) {
            $first = $sessionEvents->min('timestamp');
            $last = $sessionEvents->max('timestamp');
            return strtotime($last) - strtotime($first);
        });
        
        return $sessionLengths->avg() ?: 0;
    }

    private function findPeakActivityHour(Collection $events): int
    {
        $hourCounts = $events->groupBy(function ($event) {
            return (int) date('H', strtotime($event->timestamp));
        })->map->count();
        
        return $hourCounts->keys()->sortByDesc(function ($hour) use ($hourCounts) {
            return $hourCounts[$hour];
        })->first() ?? 9; // Default to 9 AM
    }

    private function calculateFormCompletionRate(Collection $events): float
    {
        $formEvents = $events->where('event_type', 'form_interaction');
        if ($formEvents->isEmpty()) return 0;
        
        $formStarts = $formEvents->where('event_data.action', 'start')->count();
        $formCompletions = $formEvents->where('event_data.action', 'submit')->count();
        
        return $formStarts > 0 ? $formCompletions / $formStarts : 0;
    }

    private function calculateAverageFormFillTime(Collection $events): float
    {
        $formSessions = $events->where('event_type', 'form_interaction')
            ->groupBy('event_data.form_name');
        
        $fillTimes = [];
        foreach ($formSessions as $sessions) {
            $start = $sessions->where('event_data.action', 'start')->first();
            $end = $sessions->where('event_data.action', 'submit')->first();
            
            if ($start && $end) {
                $fillTimes[] = strtotime($end->timestamp) - strtotime($start->timestamp);
            }
        }
        
        return !empty($fillTimes) ? array_sum($fillTimes) / count($fillTimes) : 0;
    }

    private function calculateValidationErrorRate(Collection $events): float
    {
        $formEvents = $events->where('event_type', 'form_interaction');
        if ($formEvents->isEmpty()) return 0;
        
        $totalInteractions = $formEvents->count();
        $errorEvents = $formEvents->where('event_data.action', 'validate_error')->count();
        
        return $totalInteractions > 0 ? $errorEvents / $totalInteractions : 0;
    }

    private function calculateFieldAbandonRate(Collection $events): float
    {
        $fieldEvents = $events->where('event_type', 'form_interaction')
            ->where('event_data.action', 'focus');
        
        if ($fieldEvents->isEmpty()) return 0;
        
        $focusEvents = $fieldEvents->count();
        $blurEvents = $events->where('event_type', 'form_interaction')
            ->where('event_data.action', 'blur')->count();
        
        return $focusEvents > 0 ? ($focusEvents - $blurEvents) / $focusEvents : 0;
    }

    private function calculateWorkflowCompletionRate(Collection $events): float
    {
        $workflowEvents = $events->where('event_type', 'workflow_step');
        if ($workflowEvents->isEmpty()) return 0;
        
        $workflowStarts = $workflowEvents->where('event_data.action', 'start')->count();
        $workflowCompletions = $workflowEvents->where('event_data.action', 'complete')->count();
        
        return $workflowStarts > 0 ? $workflowCompletions / $workflowStarts : 0;
    }

    private function calculateAverageWorkflowSteps(Collection $events): float
    {
        $workflows = $events->where('event_type', 'workflow_step')
            ->groupBy('event_data.workflow_name');
        
        $stepCounts = $workflows->map->count();
        return $stepCounts->avg() ?: 0;
    }

    private function calculateBackStepRate(Collection $events): float
    {
        $workflowEvents = $events->where('event_type', 'workflow_step');
        if ($workflowEvents->isEmpty()) return 0;
        
        $totalSteps = $workflowEvents->count();
        $backSteps = $workflowEvents->where('event_data.action', 'back')->count();
        
        return $totalSteps > 0 ? $backSteps / $totalSteps : 0;
    }

    private function calculateQuickRequestSuccessRate(Collection $events): float
    {
        $quickRequests = $events->where('event_data.workflow_name', 'quick_request');
        if ($quickRequests->isEmpty()) return 0;
        
        $starts = $quickRequests->where('event_data.action', 'start')->count();
        $completions = $quickRequests->where('event_data.action', 'complete')->count();
        
        return $starts > 0 ? $completions / $starts : 0;
    }

    private function calculateRecommendationFollowRate(Collection $events): float
    {
        $recommendationEvents = $events->where('event_data.recommendation_shown', true);
        if ($recommendationEvents->isEmpty()) return 0;
        
        $shown = $recommendationEvents->count();
        $followed = $recommendationEvents->where('event_data.followed_recommendation', true)->count();
        
        return $shown > 0 ? $followed / $shown : 0;
    }

    private function calculateDecisionConfidence(Collection $events): float
    {
        $decisionEvents = $events->where('event_type', 'decision_made');
        if ($decisionEvents->isEmpty()) return 0;
        
        $decisionTimes = $decisionEvents->pluck('event_data.decision_time_ms')->filter();
        
        // Lower decision time suggests higher confidence
        $avgDecisionTime = $decisionTimes->avg();
        return $avgDecisionTime ? min(1.0, 30000 / $avgDecisionTime) : 0.5; // 30 seconds as baseline
    }

    private function extractProductSelectionPatterns(Collection $events): array
    {
        $productEvents = $events->where('event_data.decision_type', 'product_selection');
        
        return [
            'most_selected_products' => $productEvents->groupBy('event_data.selected_option')->map->count()->sortDesc()->take(5)->toArray(),
            'selection_position_bias' => $productEvents->avg('event_data.option_position') ?: 0,
            'selection_speed' => $productEvents->avg('event_data.decision_time_ms') ?: 0,
        ];
    }

    private function extractClinicalDecisionPatterns(Collection $events): array
    {
        $clinicalEvents = $events->where('event_data.decision_type', 'clinical_assessment');
        
        return [
            'common_assessments' => $clinicalEvents->groupBy('event_data.selected_option')->map->count()->sortDesc()->take(5)->toArray(),
            'assessment_confidence' => $clinicalEvents->avg('event_data.decision_time_ms') ?: 0,
        ];
    }

    private function calculateSearchRefinementRate(Collection $events): float
    {
        $searchEvents = $events->where('event_type', 'search_performed');
        if ($searchEvents->isEmpty()) return 0;
        
        $totalSearches = $searchEvents->count();
        $refinements = $searchEvents->sum('event_data.search_refinements');
        
        return $totalSearches > 0 ? $refinements / $totalSearches : 0;
    }

    private function calculateNavigationEfficiency(Collection $events): float
    {
        $navigationEvents = $events->whereIn('event_category', ['navigation', 'workflow']);
        if ($navigationEvents->isEmpty()) return 1.0;
        
        // Calculate based on direct paths vs actual paths taken
        $workflows = $navigationEvents->groupBy('event_data.workflow_name');
        $efficiencyScores = [];
        
        foreach ($workflows as $workflowEvents) {
            $actualSteps = $workflowEvents->count();
            $optimalSteps = $this->getOptimalStepsForWorkflow($workflowEvents->first()->event_data['workflow_name'] ?? 'unknown');
            
            if ($optimalSteps > 0) {
                $efficiencyScores[] = min(1.0, $optimalSteps / $actualSteps);
            }
        }
        
        return !empty($efficiencyScores) ? array_sum($efficiencyScores) / count($efficiencyScores) : 1.0;
    }

    private function calculateHelpSeekingBehavior(Collection $events): float
    {
        $totalEvents = $events->count();
        if ($totalEvents === 0) return 0;
        
        $helpEvents = $events->where('event_data.help_sought', true)->count();
        
        return $helpEvents / $totalEvents;
    }

    private function calculateAIAcceptanceRate(Collection $events): float
    {
        $aiEvents = $events->where('event_type', 'ai_interaction');
        if ($aiEvents->isEmpty()) return 0;
        
        $suggestions = $aiEvents->where('event_data.action', 'request')->count();
        $acceptances = $aiEvents->where('event_data.action', 'accept')->count();
        
        return $suggestions > 0 ? $acceptances / $suggestions : 0;
    }

    private function calculateAIModificationRate(Collection $events): float
    {
        $aiEvents = $events->where('event_type', 'ai_interaction');
        if ($aiEvents->isEmpty()) return 0;
        
        $suggestions = $aiEvents->where('event_data.action', 'request')->count();
        $modifications = $aiEvents->where('event_data.action', 'modify')->count();
        
        return $suggestions > 0 ? $modifications / $suggestions : 0;
    }

    private function calculateAISatisfactionScore(Collection $events): float
    {
        $satisfactionScores = $events->where('event_type', 'ai_interaction')
            ->pluck('event_data.user_satisfaction')
            ->filter();
        
        return $satisfactionScores->avg() ?: 3.0; // Default to neutral
    }

    private function calculateErrorRecoveryRate(Collection $events): float
    {
        $errorEvents = $events->where('event_type', 'error_encountered');
        if ($errorEvents->isEmpty()) return 1.0;
        
        $totalErrors = $errorEvents->count();
        $recoveredErrors = $errorEvents->where('event_data.recovery_successful', true)->count();
        
        return $totalErrors > 0 ? $recoveredErrors / $totalErrors : 1.0;
    }

    private function calculateFrustrationIndicators(Collection $events): float
    {
        $frustrationEvents = $events->whereNotNull('event_data.user_frustration_level');
        if ($frustrationEvents->isEmpty()) return 0;
        
        return $frustrationEvents->avg('event_data.user_frustration_level') ?: 0;
    }

    private function calculateSupportContactRate(Collection $events): float
    {
        $totalSessions = $events->unique('session_id')->count();
        if ($totalSessions === 0) return 0;
        
        $supportContactSessions = $events->where('event_data.support_contacted', true)
            ->unique('session_id')->count();
        
        return $supportContactSessions / $totalSessions;
    }

    private function extractDevicePreferences(Collection $events): array
    {
        $deviceCounts = $events->groupBy('browser_info.device_type')->map->count();
        
        return $deviceCounts->sortDesc()->toArray();
    }

    private function extractBrowserPerformance(Collection $events): array
    {
        return [
            'avg_load_time' => $events->avg('performance_metrics.execution_time') ?: 0,
            'memory_usage' => $events->avg('performance_metrics.memory_usage') ?: 0,
            'browser_distribution' => $events->groupBy('browser_info.browser_family')->map->count()->toArray(),
        ];
    }

    private function extractTimeOfDayPatterns(Collection $events): array
    {
        $hourCounts = $events->groupBy(function ($event) {
            return (int) date('H', strtotime($event->timestamp));
        })->map->count();
        
        return $hourCounts->toArray();
    }

    // Additional helper methods would be implemented here...
    
    private function getOptimalStepsForWorkflow(string $workflowName): int
    {
        // This would be configured based on your actual workflows
        $optimalSteps = [
            'quick_request' => 5,
            'order_creation' => 8,
            'patient_registration' => 6,
            'insurance_verification' => 4,
        ];
        
        return $optimalSteps[$workflowName] ?? 10; // Default
    }

    private function getDefaultRecommendations(): array
    {
        return [
            'next_likely_action' => 'form_fill',
            'recommended_products' => [],
            'form_suggestions' => [],
            'workflow_shortcuts' => [],
            'personalization_settings' => [],
        ];
    }

    // Placeholder methods for complex calculations that would be implemented based on specific business logic
    private function findCommonSequences(Collection $events): array { return []; }
    private function findSuccessfulSequences(Collection $events): array { return []; }
    private function findAbandonedSequences(Collection $events): array { return []; }
    private function findOptimalStepOrder(Collection $events): array { return []; }
    private function findBottleneckPoints(Collection $events): array { return []; }
    private function predictNextAction(array $recentEvents): string { return 'continue_workflow'; }
    private function recommendProducts(array $recentEvents): array { return []; }
    private function suggestFormOptimizations(array $recentEvents): array { return []; }
    private function suggestWorkflowShortcuts(array $recentEvents): array { return []; }
    private function suggestPersonalizationSettings(array $recentEvents): array { return []; }
    private function analyzeGroupPerformance(Collection $events): array { return []; }
    private function calculateConversionRates(Collection $events): array { return []; }
    private function calculateEngagementMetrics(Collection $events): array { return []; }
    private function calculateStatisticalSignificance(Collection $events): float { return 0.0; }
    private function isHighEngagementUser(Collection $events): bool { return $events->count() > 100; }
    private function hasSuccessfulWorkflows(Collection $events): bool { return $events->where('event_data.action', 'complete')->count() > 0; }
    private function hasAccurateProductSelections(Collection $events): bool { return true; }
    private function getFormCompletionLikelihood(Collection $events): float { return 0.5; }
    private function calculateDataQualityScore(array $trainingData): float { return 0.8; }
} 