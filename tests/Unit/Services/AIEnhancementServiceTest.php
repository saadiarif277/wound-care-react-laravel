<?php

namespace Tests\Unit\Services;

use App\Services\AIEnhancementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AIEnhancementServiceTest extends TestCase
{
    private AIEnhancementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set to mock provider for testing
        config(['ai.provider' => 'mock']);

        $this->service = new AIEnhancementService();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_enhance_clinical_opportunities_with_mock_provider()
    {
        $context = [
            'patient_id' => 'test-123',
            'demographics' => ['age' => 65]
        ];

        $opportunities = [
            ['rule_id' => 'test_1', 'type' => 'clinical']
        ];

        $result = $this->service->enhanceClinicalOpportunities($context, $opportunities);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('mock_ai_1', $result[0]['rule_id']);
        $this->assertEquals('ai_enhanced', $result[0]['type']);
    }

    public function test_enhance_product_recommendations_with_mock_provider()
    {
        $context = [
            'product_request_id' => 'pr-123',
            'wound_type' => 'DFU'
        ];

        $recommendations = [
            ['q_code' => 'Q4100', 'rank' => 2]
        ];

        $result = $this->service->enhanceProductRecommendations($context, $recommendations);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('Q4101', $result[0]['q_code']);
        $this->assertEquals(0.9, $result[0]['confidence_score']);
    }

    public function test_caching_prevents_duplicate_api_calls()
    {
        $context = ['patient_id' => 'test-123'];
        $opportunities = [['rule_id' => 'test_1']];

        // First call
        $result1 = $this->service->enhanceClinicalOpportunities($context, $opportunities);

        // Second call with same parameters
        $result2 = $this->service->enhanceClinicalOpportunities($context, $opportunities);

        // Results should be identical (from cache)
        $this->assertEquals($result1, $result2);
    }

    public function test_fallback_to_original_on_error()
    {
        // Configure invalid provider to trigger error
        config(['ai.provider' => 'invalid_provider']);
        $service = new AIEnhancementService();

        $context = ['patient_id' => 'test-123'];
        $opportunities = [
            ['rule_id' => 'original_1', 'type' => 'test']
        ];

        $result = $service->enhanceClinicalOpportunities($context, $opportunities);

        // Should return original opportunities on error
        $this->assertEquals($opportunities, $result);
    }

    public function test_azure_openai_request_format()
    {
        // Mock HTTP response
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'opportunities' => [
                                    ['rule_id' => 'ai_1', 'type' => 'enhanced']
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        config(['ai.provider' => 'azure']);
        config(['ai.providers.azure' => [
            'endpoint' => 'https://test.openai.azure.com',
            'api_key' => 'test-key',
            'deployment' => 'test-deployment',
            'api_version' => '2023-12-01-preview'
        ]]);

        $service = new AIEnhancementService();

        $result = $service->enhanceClinicalOpportunities(
            ['patient_id' => 'test'],
            [['rule_id' => 'test']]
        );

        $this->assertEquals('ai_1', $result[0]['rule_id']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('api-key', 'test-key') &&
                   str_contains($request->url(), 'test.openai.azure.com');
        });
    }
}
