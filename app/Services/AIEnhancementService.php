<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class AIEnhancementService
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'azure');
        $this->config = config('ai.providers.' . $this->provider, []);
    }

    /**
     * Enhance clinical opportunities with AI
     */
    public function enhanceClinicalOpportunities(array $context, array $ruleBasedOpportunities): array
    {
        try {
            $cacheKey = 'ai_clinical_opp:' . md5(json_encode([$context['patient_id'] ?? '', $ruleBasedOpportunities]));

            // Check cache first
            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }

            // Prepare prompt for AI
            $prompt = $this->buildClinicalOpportunitiesPrompt($context, $ruleBasedOpportunities);

            // Call AI provider
            $response = $this->callAIProvider('clinical-opportunities', $prompt);

            // Parse and validate response
            $enhanced = $this->parseClinicalOpportunitiesResponse($response);

            // Cache results
            Cache::put($cacheKey, $enhanced, now()->addHours(1));

            return $enhanced;

        } catch (Exception $e) {
            Log::warning('AI enhancement failed for clinical opportunities', [
                'error' => $e->getMessage(),
                'patient_id' => $context['patient_id'] ?? 'unknown'
            ]);

            // Return original opportunities if AI fails
            return $ruleBasedOpportunities;
        }
    }

    /**
     * Enhance product recommendations with AI
     */
    public function enhanceProductRecommendations(array $context, array $ruleBasedRecommendations): array
    {
        try {
            $cacheKey = 'ai_product_rec:' . md5(json_encode([$context['product_request_id'] ?? '', $ruleBasedRecommendations]));

            // Check cache first
            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }

            // Prepare prompt for AI
            $prompt = $this->buildProductRecommendationsPrompt($context, $ruleBasedRecommendations);

            // Call AI provider
            $response = $this->callAIProvider('product-recommendations', $prompt);

            // Parse and validate response
            $enhanced = $this->parseProductRecommendationsResponse($response);

            // Cache results
            Cache::put($cacheKey, $enhanced, now()->addHours(1));

            return $enhanced;

        } catch (Exception $e) {
            Log::warning('AI enhancement failed for product recommendations', [
                'error' => $e->getMessage(),
                'product_request_id' => $context['product_request_id'] ?? 'unknown'
            ]);

            // Return original recommendations if AI fails
            return $ruleBasedRecommendations;
        }
    }

    /**
     * Call the configured AI provider
     */
    private function callAIProvider(string $useCase, string $prompt): array
    {
        switch ($this->provider) {
            case 'azure':
                return $this->callAzureOpenAI($useCase, $prompt);
            case 'openai':
                return $this->callOpenAI($useCase, $prompt);
            case 'mock':
                return $this->getMockResponse($useCase);
            default:
                throw new Exception("Unsupported AI provider: {$this->provider}");
        }
    }

    /**
     * Call Azure OpenAI
     */
    private function callAzureOpenAI(string $useCase, string $prompt): array
    {
        $response = Http::withHeaders([
            'api-key' => $this->config['api_key'] ?? '',
            'Content-Type' => 'application/json'
        ])->timeout(30)
        ->post($this->config['endpoint'] . '/openai/deployments/' . $this->config['deployment'] . '/chat/completions?api-version=' . $this->config['api_version'], [
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt($useCase)],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ]);

        if (!$response->successful()) {
            throw new Exception('Azure OpenAI API call failed: ' . $response->status());
        }

        $result = $response->json();
        return json_decode($result['choices'][0]['message']['content'], true);
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $useCase, string $prompt): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'] ?? '',
            'Content-Type' => 'application/json'
        ])->timeout(30)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->config['model'] ?? 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt($useCase)],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ]);

        if (!$response->successful()) {
            throw new Exception('OpenAI API call failed: ' . $response->status());
        }

        $result = $response->json();
        return json_decode($result['choices'][0]['message']['content'], true);
    }

    /**
     * Get system prompt for specific use case
     */
    private function getSystemPrompt(string $useCase): string
    {
        $prompts = [
            'clinical-opportunities' => 'You are a clinical decision support AI specializing in wound care. Analyze patient data and enhance clinical opportunities with evidence-based insights. Return JSON with enhanced opportunities array.',
            'product-recommendations' => 'You are a wound care product recommendation AI. Analyze wound characteristics and recommend appropriate products. Return JSON with enhanced recommendations array including confidence scores and clinical reasoning.'
        ];

        return $prompts[$useCase] ?? 'You are a healthcare AI assistant. Provide helpful, accurate, and evidence-based recommendations.';
    }

    /**
     * Build prompt for clinical opportunities
     */
    private function buildClinicalOpportunitiesPrompt(array $context, array $opportunities): string
    {
        return json_encode([
            'patient_context' => [
                'demographics' => $context['demographics'] ?? [],
                'conditions' => $context['clinical_data']['conditions'] ?? [],
                'wound_data' => $context['clinical_data']['wound_data'] ?? [],
                'risk_factors' => $context['risk_factors'] ?? []
            ],
            'rule_based_opportunities' => $opportunities,
            'request' => 'Enhance these clinical opportunities with additional insights, validate priority scores, and identify any missed opportunities.'
        ]);
    }

    /**
     * Build prompt for product recommendations
     */
    private function buildProductRecommendationsPrompt(array $context, array $recommendations): string
    {
        return json_encode([
            'wound_context' => [
                'type' => $context['wound_type'] ?? 'unknown',
                'characteristics' => $context['wound_characteristics'] ?? [],
                'duration' => $context['wound_duration'] ?? null
            ],
            'patient_factors' => $context['patient_factors'] ?? [],
            'payer_context' => $context['payer_context'] ?? [],
            'rule_based_recommendations' => $recommendations,
            'request' => 'Enhance product recommendations with clinical reasoning, validate rankings, and provide evidence-based justifications.'
        ]);
    }

    /**
     * Parse clinical opportunities response
     */
    private function parseClinicalOpportunitiesResponse(array $response): array
    {
        if (!isset($response['opportunities']) || !is_array($response['opportunities'])) {
            throw new Exception('Invalid AI response format for clinical opportunities');
        }

        return $response['opportunities'];
    }

    /**
     * Parse product recommendations response
     */
    private function parseProductRecommendationsResponse(array $response): array
    {
        if (!isset($response['recommendations']) || !is_array($response['recommendations'])) {
            throw new Exception('Invalid AI response format for product recommendations');
        }

        return $response['recommendations'];
    }

    /**
     * Get mock response for testing
     */
    private function getMockResponse(string $useCase): array
    {
        switch ($useCase) {
            case 'clinical-opportunities':
                return [
                    'opportunities' => [
                        [
                            'rule_id' => 'mock_ai_1',
                            'type' => 'ai_enhanced',
                            'category' => 'clinical',
                            'priority' => 8,
                            'title' => 'AI-Enhanced Clinical Insight',
                            'confidence_score' => 0.85,
                            'insights' => ['AI-generated insight for testing']
                        ]
                    ]
                ];

            case 'product-recommendations':
                return [
                    'recommendations' => [
                        [
                            'q_code' => 'Q4101',
                            'rank' => 1,
                            'confidence_score' => 0.9,
                            'reasoning' => 'AI-enhanced recommendation based on wound characteristics',
                            'key_benefits' => ['Optimal healing', 'Cost-effective']
                        ]
                    ]
                ];

            default:
                return [];
        }
    }
}
