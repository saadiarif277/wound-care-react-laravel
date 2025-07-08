<?php

namespace App\Services\Learning;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessBehavioralEventJob;
use App\Models\Learning\BehavioralEvent;
use Carbon\Carbon;

/**
 * Comprehensive Behavioral Tracking Service
 * 
 * Captures ALL user interactions for continuous learning:
 * - Form interactions and completions
 * - Navigation patterns and workflow decisions
 * - Product selections and clinical choices
 * - Search queries and filter usage
 * - Time spent on tasks and completion rates
 * 
 * PHI-Safe: All PII/PHI data is filtered out before storage
 */
class BehavioralTrackingService
{
    private const PHI_FIELDS = [
        'patient_name', 'patient_first_name', 'patient_last_name',
        'patient_dob', 'patient_phone', 'patient_email', 'patient_address',
        'patient_ssn', 'member_id', 'policy_number', 'subscriber_name',
        'insurance_member_id', 'medicare_number', 'medicaid_number'
    ];

    private const SENSITIVE_FIELDS = [
        'api_key', 'password', 'token', 'secret', 'auth', 'signature'
    ];

    /**
     * Track a user interaction event
     */
    public function trackEvent(string $eventType, array $eventData = [], array $context = []): void
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return; // Don't track anonymous interactions
            }

            // Build comprehensive event data
            $event = [
                'event_id' => uniqid('evt_', true),
                'user_id' => $user->id,
                'user_role' => $user->role,
                'facility_id' => $user->facility_id ?? null,
                'organization_id' => $user->organization_id ?? null,
                'event_type' => $eventType,
                'event_category' => $this->categorizeEvent($eventType),
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
                'ip_hash' => hash('sha256', request()->ip()), // Anonymized IP
                'user_agent_hash' => hash('sha256', request()->userAgent()), // Anonymized UA
                'url_path' => request()->path(),
                'http_method' => request()->method(),
                'event_data' => $this->sanitizeEventData($eventData),
                'context' => $this->sanitizeContextData($context),
                'browser_info' => $this->getBrowserInfo(),
                'performance_metrics' => $this->getPerformanceMetrics(),
            ];

            // Queue for async processing to avoid blocking user requests
            Queue::push(new ProcessBehavioralEventJob($event));

            // Store in cache for immediate access by real-time systems
            $this->cacheRecentEvent($event);

        } catch (\Exception $e) {
            Log::warning('Behavioral tracking failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Track form interaction (field focus, typing, completion)
     */
    public function trackFormInteraction(string $formName, string $fieldName, string $action, $value = null): void
    {
        $this->trackEvent('form_interaction', [
            'form_name' => $formName,
            'field_name' => $fieldName,
            'action' => $action, // focus, blur, change, submit, validate_error
            'field_type' => $this->getFieldType($fieldName),
            'has_value' => !empty($value),
            'value_length' => is_string($value) ? strlen($value) : null,
            'is_required_field' => $this->isRequiredField($formName, $fieldName),
        ]);
    }

    /**
     * Track workflow progression
     */
    public function trackWorkflowStep(string $workflowName, string $stepName, string $action, array $data = []): void
    {
        $this->trackEvent('workflow_step', [
            'workflow_name' => $workflowName,
            'step_name' => $stepName,
            'action' => $action, // start, complete, skip, back, abandon
            'step_duration_ms' => $data['duration_ms'] ?? null,
            'validation_errors' => $data['validation_errors'] ?? [],
            'completion_percentage' => $data['completion_percentage'] ?? null,
            'retry_count' => $data['retry_count'] ?? 0,
        ]);
    }

    /**
     * Track product/clinical decisions
     */
    public function trackDecision(string $decisionType, string $selectedOption, array $availableOptions = [], array $context = []): void
    {
        $this->trackEvent('decision_made', [
            'decision_type' => $decisionType, // product_selection, clinical_assessment, insurance_choice
            'selected_option' => $selectedOption,
            'available_options' => $availableOptions,
            'option_position' => array_search($selectedOption, $availableOptions),
            'total_options' => count($availableOptions),
            'decision_time_ms' => $context['decision_time_ms'] ?? null,
            'recommendation_shown' => $context['recommendation_shown'] ?? false,
            'followed_recommendation' => $context['followed_recommendation'] ?? null,
        ]);
    }

    /**
     * Track search and filter usage
     */
    public function trackSearch(string $searchType, string $query, array $filters = [], array $results = []): void
    {
        $this->trackEvent('search_performed', [
            'search_type' => $searchType, // patient_search, product_search, order_search
            'query_length' => strlen($query),
            'query_words' => str_word_count($query),
            'filters_used' => array_keys($filters),
            'filter_count' => count($filters),
            'results_count' => count($results),
            'results_clicked' => false, // Will be updated when user clicks
            'search_refinements' => 0, // Track if user refines search
        ]);
    }

    /**
     * Track AI assistance usage
     */
    public function trackAIInteraction(string $aiFeature, string $action, array $data = []): void
    {
        $this->trackEvent('ai_interaction', [
            'ai_feature' => $aiFeature, // field_mapping, product_recommendation, clinical_opportunity
            'action' => $action, // request, accept, reject, modify
            'confidence_score' => $data['confidence_score'] ?? null,
            'processing_time_ms' => $data['processing_time_ms'] ?? null,
            'suggestions_count' => $data['suggestions_count'] ?? null,
            'user_satisfaction' => $data['user_satisfaction'] ?? null, // 1-5 rating
            'improvement_suggested' => $data['improvement_suggested'] ?? null,
        ]);
    }

    /**
     * Track error and recovery patterns
     */
    public function trackError(string $errorType, string $errorMessage, array $context = []): void
    {
        $this->trackEvent('error_encountered', [
            'error_type' => $errorType, // validation, network, system, user
            'error_category' => $this->categorizeError($errorMessage),
            'recovery_action' => $context['recovery_action'] ?? null,
            'recovery_successful' => $context['recovery_successful'] ?? null,
            'user_frustration_level' => $context['frustration_level'] ?? null,
            'support_contacted' => $context['support_contacted'] ?? false,
        ]);
    }

    /**
     * Sanitize event data to remove PHI and sensitive information
     */
    private function sanitizeEventData(array $data): array
    {
        return $this->recursiveFieldFilter($data, function($key, $value) {
            // Remove PHI fields
            if (in_array(strtolower($key), array_map('strtolower', self::PHI_FIELDS))) {
                return null;
            }

            // Remove sensitive fields
            if (in_array(strtolower($key), array_map('strtolower', self::SENSITIVE_FIELDS))) {
                return null;
            }

            // Hash long strings that might contain sensitive data
            if (is_string($value) && strlen($value) > 100) {
                return 'HASHED_' . hash('sha256', $value);
            }

            // Keep the value but check for patterns that look like PHI
            if (is_string($value)) {
                // Check for SSN pattern
                if (preg_match('/\d{3}-?\d{2}-?\d{4}/', $value)) {
                    return 'SSN_DETECTED';
                }
                
                // Check for phone pattern
                if (preg_match('/\(\d{3}\)\s?\d{3}-?\d{4}/', $value)) {
                    return 'PHONE_DETECTED';
                }
                
                // Check for date pattern that might be DOB
                if (preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $value)) {
                    return 'DATE_DETECTED';
                }
            }

            return $value;
        });
    }

    /**
     * Sanitize context data
     */
    private function sanitizeContextData(array $context): array
    {
        // Keep only non-sensitive context
        $allowedContextKeys = [
            'page_load_time', 'form_step', 'workflow_position', 'recommendation_shown',
            'user_preferences', 'feature_flags', 'ab_test_group', 'screen_resolution',
            'device_type', 'connection_speed', 'previous_action', 'time_since_last_action'
        ];

        return array_intersect_key($context, array_flip($allowedContextKeys));
    }

    /**
     * Recursively filter array fields
     */
    private function recursiveFieldFilter(array $data, callable $filter): array
    {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = $this->recursiveFieldFilter($value, $filter);
            } else {
                $filteredValue = $filter($key, $value);
                if ($filteredValue !== null) {
                    $filtered[$key] = $filteredValue;
                }
            }
        }
        
        return $filtered;
    }

    /**
     * Categorize events for ML feature engineering
     */
    private function categorizeEvent(string $eventType): string
    {
        $categories = [
            'navigation' => ['page_view', 'link_click', 'menu_click'],
            'form' => ['form_interaction', 'form_submit', 'form_validation'],
            'workflow' => ['workflow_step', 'workflow_complete', 'workflow_abandon'],
            'decision' => ['decision_made', 'product_selection', 'clinical_choice'],
            'search' => ['search_performed', 'filter_applied', 'results_viewed'],
            'ai' => ['ai_interaction', 'recommendation_shown', 'suggestion_accepted'],
            'error' => ['error_encountered', 'validation_failed', 'system_error'],
            'performance' => ['slow_load', 'timeout', 'retry_needed']
        ];

        foreach ($categories as $category => $types) {
            if (in_array($eventType, $types)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Get browser and device information for context
     */
    private function getBrowserInfo(): array
    {
        $userAgent = request()->userAgent();
        
        return [
            'is_mobile' => request()->isMobile(),
            'is_tablet' => request()->isTablet(),
            'is_desktop' => request()->isDesktop(),
            'browser_family' => $this->getBrowserFamily($userAgent),
            'os_family' => $this->getOSFamily($userAgent),
            'screen_size_category' => $this->getScreenSizeCategory(),
        ];
    }

    /**
     * Get performance metrics if available
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
        ];
    }

    /**
     * Cache recent events for immediate ML feedback
     */
    private function cacheRecentEvent(array $event): void
    {
        $cacheKey = "recent_events:{$event['user_id']}";
        $recentEvents = Cache::get($cacheKey, []);
        
        // Keep only last 50 events per user
        array_unshift($recentEvents, $event);
        $recentEvents = array_slice($recentEvents, 0, 50);
        
        Cache::put($cacheKey, $recentEvents, now()->addHours(24));
    }

    /**
     * Get aggregated user behavior patterns
     */
    public function getUserBehaviorPattern(int $userId, int $days = 30): array
    {
        return BehavioralEvent::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select([
                'event_category',
                \DB::raw('COUNT(*) as event_count'),
                \DB::raw('AVG(processing_time_ms) as avg_processing_time'),
                \DB::raw('COUNT(DISTINCT DATE(created_at)) as active_days')
            ])
            ->groupBy('event_category')
            ->get()
            ->toArray();
    }

    /**
     * Helper methods for classification
     */
    private function getFieldType(string $fieldName): string
    {
        // Classify field type for ML features
        if (str_contains($fieldName, 'phone')) return 'phone';
        if (str_contains($fieldName, 'email')) return 'email';
        if (str_contains($fieldName, 'date')) return 'date';
        if (str_contains($fieldName, 'npi')) return 'npi';
        if (str_contains($fieldName, 'address')) return 'address';
        return 'text';
    }

    private function isRequiredField(string $formName, string $fieldName): bool
    {
        // Check manufacturer configs or form definitions
        // This would integrate with your existing field mapping configs
        return false; // Simplified for now
    }

    private function categorizeError(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'validation')) return 'validation';
        if (str_contains($errorMessage, 'network')) return 'network';
        if (str_contains($errorMessage, 'timeout')) return 'timeout';
        if (str_contains($errorMessage, 'authorization')) return 'auth';
        return 'unknown';
    }

    private function getBrowserFamily(string $userAgent): string
    {
        if (str_contains($userAgent, 'Chrome')) return 'chrome';
        if (str_contains($userAgent, 'Firefox')) return 'firefox';
        if (str_contains($userAgent, 'Safari')) return 'safari';
        if (str_contains($userAgent, 'Edge')) return 'edge';
        return 'other';
    }

    private function getOSFamily(string $userAgent): string
    {
        if (str_contains($userAgent, 'Windows')) return 'windows';
        if (str_contains($userAgent, 'Mac')) return 'mac';
        if (str_contains($userAgent, 'Linux')) return 'linux';
        if (str_contains($userAgent, 'Android')) return 'android';
        if (str_contains($userAgent, 'iOS')) return 'ios';
        return 'other';
    }

    private function getScreenSizeCategory(): string
    {
        // This would need JavaScript to get actual screen size
        // For now, use user agent hints
        return 'unknown';
    }
} 