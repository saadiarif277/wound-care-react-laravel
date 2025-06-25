<?php

namespace App\Services\ProductRecommendationEngine;

use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\Order\MscProductRecommendationRule;
use App\Services\AIEnhancementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MSCProductRecommendationService
{
    protected $contextBuilder;
    protected $ruleEvaluator;
    protected $aiEnhancementService;

    public function __construct(
        MSCProductContextBuilderService $contextBuilder,
        MSCProductRuleEvaluatorService $ruleEvaluator,
        AIEnhancementService $aiEnhancementService
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->ruleEvaluator = $ruleEvaluator;
        $this->aiEnhancementService = $aiEnhancementService;
    }

    /**
     * Get product recommendations for a product request
     */
    public function getRecommendations(ProductRequest $productRequest, array $options = []): array
    {
        try {
            // 1. Build comprehensive context
            $context = $this->contextBuilder->buildProductContext($productRequest);

            // 2. Merge user options into context for pricing visibility
            $context = array_merge($context, [
                'user_role' => $options['user_role'] ?? 'provider',
                'show_msc_pricing' => $options['show_msc_pricing'] ?? true
            ]);

            // 3. Get rule-based recommendations
            $ruleBasedRecommendations = $this->ruleEvaluator->evaluateRules($context);

            // 4. Enhance with AI if enabled
            $recommendations = $ruleBasedRecommendations;
            if ($options['use_ai'] ?? true) {
                $recommendations = $this->enhanceWithAI($context, $ruleBasedRecommendations);
            }

            // 5. Apply business logic and formatting
            $formattedRecommendations = $this->formatRecommendations($recommendations, $context);

            // 5. Log for analytics
            $this->logRecommendationUsage($productRequest, $formattedRecommendations);

            return [
                'success' => true,
                'recommendations' => $formattedRecommendations,
                'context_summary' => $this->buildContextSummary($context),
                'generated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Product recommendation failed', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate recommendations',
                'fallback_recommendations' => $this->getFallbackRecommendations($productRequest)
            ];
        }
    }

    /**
     * Enhance recommendations using AI
     */
    protected function enhanceWithAI(array $context, array $ruleBasedRecommendations): array
    {
        try {
            // Use AI enhancement service
            $enhancedRecommendations = $this->aiEnhancementService->enhanceProductRecommendations($context, $ruleBasedRecommendations);

            return $enhancedRecommendations;
        } catch (\Exception $e) {
            Log::warning('AI enhancement failed, falling back to rule-based only', [
                'error' => $e->getMessage(),
                'context' => $context['product_request_id'] ?? 'unknown'
            ]);

            // Return rule-based recommendations as fallback
            return $ruleBasedRecommendations;
        }
    }

    /**
     * Format recommendations for frontend consumption
     */
    protected function formatRecommendations(array $recommendations, array $context): array
    {
        $showMscPricing = $context['show_msc_pricing'] ?? true;

        return array_map(function ($rec) use ($context, $showMscPricing) {
            // Try to find product by q_code, fallback to MSC products table
            $product = Product::where('q_code', $rec['q_code'])->first();

            if (!$product) {
                // Check MSC products table as fallback
                $product = DB::table('msc_products')
                    ->where('q_code', $rec['q_code'])
                    ->where('is_active', true)
                    ->first();

                if ($product) {
                    // Convert to object with expected properties
                    $product = (object) [
                        'id' => $product->id,
                        'name' => $product->name,
                        'q_code' => $product->q_code,
                        'manufacturer' => $product->manufacturer,
                        'category' => $product->category,
                        'msc_price' => $product->price_per_sq_cm ?? 0,
                        'national_asp' => $product->national_asp ?? 0,
                        'available_sizes' => json_decode($product->available_sizes ?? '[]', true),
                        'image_url' => $product->image_url,
                        'document_urls' => json_decode($product->document_urls ?? '[]', true)
                    ];
                }
            }

            if (!$product) {
                return null;
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'q_code' => $product->q_code,
                'manufacturer' => $product->manufacturer,
                'category' => $product->category,
                'rank' => $rec['rank'] ?? 999,
                'confidence_score' => $rec['confidence_score'] ?? 0.8,
                'reasoning' => $rec['reasoning'] ?? 'Recommended based on clinical criteria',
                'suggested_size' => $rec['suggested_size'] ?? null,
                'estimated_cost' => $this->calculateEstimatedCost($product, $rec['suggested_size'] ?? 4, $showMscPricing),
                'key_benefits' => $rec['key_benefits'] ?? [],
                'clinical_evidence' => $rec['clinical_evidence'] ?? null,
                'contraindications' => $rec['contraindications'] ?? [],
                'product_details' => array_merge([
                    'national_asp' => $product->national_asp,
                    'available_sizes' => $product->available_sizes,
                    'image_url' => $product->image_url,
                    'document_urls' => $product->document_urls
                ], $showMscPricing ? ['msc_price' => $product->msc_price] : [])
            ];
        }, $recommendations);
    }

    /**
     * Calculate estimated cost for a product and size
     */
    protected function calculateEstimatedCost(Product $product, float $size, bool $showMscPricing = true): array
    {
        $nationalAsp = $product->national_asp * $size;

        $result = [
            'national_asp' => round($nationalAsp, 2)
        ];

        if ($showMscPricing) {
            $mscPrice = $product->msc_price * $size;
            $result['msc_price'] = round($mscPrice, 2);
            $result['savings'] = round($nationalAsp - $mscPrice, 2);
            $result['savings_percentage'] = $nationalAsp > 0 ? round((($nationalAsp - $mscPrice) / $nationalAsp) * 100, 1) : 0;
        }

        return $result;
    }

    /**
     * Build context summary for UI display
     */
    protected function buildContextSummary(array $context): array
    {
        return [
            'wound_type' => $context['wound_type'] ?? 'Unknown',
            'wound_characteristics' => $context['wound_characteristics'] ?? [],
            'patient_factors' => $context['patient_factors'] ?? [],
            'payer_context' => $context['payer_context'] ?? [],
            'mac_validation_status' => $context['mac_validation_status'] ?? 'not_checked'
        ];
    }

    /**
     * Get fallback recommendations when AI/rules fail
     */
    protected function getFallbackRecommendations(ProductRequest $productRequest): array
    {
        $products = Product::active()
            ->when($productRequest->wound_type === 'DFU', function ($query) {
                return $query->whereIn('category', ['SkinSubstitute', 'Biologic']);
            })
            ->when($productRequest->wound_type === 'VLU', function ($query) {
                return $query->where('category', 'SkinSubstitute');
            })
            ->orderBy('price_per_sq_cm', 'asc')
            ->limit(3)
            ->get();

        return $products->map(function ($product, $index) {
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'q_code' => $product->q_code,
                'rank' => $index + 1,
                'reasoning' => 'Basic recommendation based on wound type',
                'confidence_score' => 0.6
            ];
        })->toArray();
    }

    /**
     * Log recommendation usage for analytics
     */
    protected function logRecommendationUsage(ProductRequest $productRequest, array $recommendations): void
    {
        Log::info('Product recommendations generated', [
            'product_request_id' => $productRequest->id,
            'provider_id' => $productRequest->provider_id,
            'wound_type' => $productRequest->wound_type,
            'recommendation_count' => count($recommendations),
            'top_recommendation' => $recommendations[0]['q_code'] ?? null
        ]);
    }
}
