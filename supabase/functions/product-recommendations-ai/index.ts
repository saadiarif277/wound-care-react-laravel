import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from 'jsr:@supabase/supabase-js@2';

interface RecommendationContext {
  product_request_id: string;
  wound_type: string;
  wound_characteristics: any;
  clinical_data: any;
  patient_factors: any;
  payer_context: any;
  mac_validation_status: string;
  prior_treatments: any;
  facility_context: any;
}

interface RuleBasedRecommendation {
  q_code: string;
  rank: number;
  rule_id: number;
  rule_name: string;
  confidence_score: number;
  reasoning: string;
  suggested_size?: number;
  key_benefits: string[];
  clinical_evidence: any;
  contraindications: string[];
}

interface AIEnhancedRecommendation extends RuleBasedRecommendation {
  ai_reasoning?: string;
  ai_confidence_adjustment?: number;
  ai_size_recommendation?: number;
  ai_insights?: string[];
  risk_factors?: string[];
  alternative_products?: string[];
}

const AZURE_OPENAI_CONFIG = {
  endpoint: Deno.env.get('AZURE_OPENAI_ENDPOINT'),
  apiKey: Deno.env.get('AZURE_OPENAI_API_KEY'),
  deploymentName: Deno.env.get('AZURE_OPENAI_DEPLOYMENT_NAME') || 'gpt-4',
  apiVersion: '2024-02-15-preview'
};

/**
 * Enhanced Product Recommendation Engine using Azure OpenAI
 */
Deno.serve(async (req: Request) => {
  // CORS headers
  const corsHeaders = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
  };

  // Handle CORS preflight
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders });
  }

  // Request ID for tracing
  const requestId = crypto.randomUUID();
  const startTime = Date.now();

  try {
    // Validate request method
    if (req.method !== 'POST') {
      return new Response(
        JSON.stringify({ error: 'Method not allowed' }),
        {
          status: 405,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      );
    }

    // Validate Azure OpenAI configuration
    if (!AZURE_OPENAI_CONFIG.endpoint || !AZURE_OPENAI_CONFIG.apiKey) {
      console.error('Missing Azure OpenAI configuration');
      return new Response(
        JSON.stringify({
          error: 'Service configuration error',
          message: 'AI service is currently unavailable'
        }),
        {
          status: 503,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      );
    }

    // Parse and validate request body
    let requestBody;
    try {
      const rawBody = await req.text();
      if (!rawBody.trim()) {
        throw new Error('Empty request body');
      }
      requestBody = JSON.parse(rawBody);
    } catch (parseError) {
      console.error('Request body parse error:', parseError);
      return new Response(
        JSON.stringify({
          error: 'Invalid JSON in request body',
          message: 'Please check your request format'
        }),
        {
          status: 400,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      );
    }

    const { context, rule_based_recommendations } = requestBody as {
      context: RecommendationContext;
      rule_based_recommendations: RuleBasedRecommendation[];
    };

    // Enhanced validation
    const validationResult = validateRequestData(context, rule_based_recommendations);
    if (!validationResult.valid) {
      console.error('Request validation failed:', validationResult.errors);
      return new Response(
        JSON.stringify({
          error: 'Invalid request data',
          details: validationResult.errors,
          message: 'Please check your request parameters'
        }),
        {
          status: 400,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      );
    }

    // Initialize Supabase client with error handling
    let supabase;
    try {
      supabase = createClient(
        Deno.env.get('SUPABASE_URL') ?? '',
        Deno.env.get('SUPABASE_ANON_KEY') ?? ''
      );
    } catch (supabaseError) {
      console.error('Failed to initialize Supabase client:', supabaseError);
      return new Response(
        JSON.stringify({
          error: 'Database connection error',
          message: 'Unable to connect to product database'
        }),
        {
          status: 503,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      );
    }

    // Get product catalog data for AI context with timeout
    let products = [];
    try {
      const productQuery = supabase
        .from('msc_products')
        .select('q_code, name, category, manufacturer, description')
        .eq('is_active', true);

      const { data, error: productsError } = await Promise.race([
        productQuery,
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Product fetch timeout')), 10000)
        )
      ]) as any;

      if (productsError) {
        throw new Error(`Product fetch error: ${productsError.message}`);
      }

      products = data || [];
      console.log(`Fetched ${products.length} products for AI context`);

    } catch (productError) {
      console.error('Error fetching products:', productError);
      // Continue with empty products array - AI can still work with rule-based data
      console.warn('Continuing with empty product catalog for AI enhancement');
    }

    // Enhance recommendations with AI
    let enhancedRecommendations: AIEnhancedRecommendation[];

    try {
      console.log(`Starting AI enhancement for request ${requestId}`);
      enhancedRecommendations = await enhanceRecommendationsWithAI(
        context,
        rule_based_recommendations,
        products,
        requestId
      );
      console.log(`AI enhancement completed for request ${requestId}`);
    } catch (aiError) {
      console.error(`AI enhancement failed for request ${requestId}:`, aiError);

      // Fallback to rule-based recommendations with basic AI structure
      enhancedRecommendations = rule_based_recommendations.map(rec => ({
        ...rec,
        ai_reasoning: 'AI enhancement temporarily unavailable - using rule-based recommendation',
        ai_confidence_adjustment: 0,
        ai_insights: ['AI service temporarily unavailable'],
        risk_factors: [],
        alternative_products: []
      }));
    }

    // Log usage for analytics (non-blocking)
    try {
      await logAIUsage(supabase, context.product_request_id, enhancedRecommendations, requestId);
    } catch (logError) {
      console.error('Failed to log AI usage:', logError);
      // Continue execution, don't fail the request
    }

    const processingTime = Date.now() - startTime;
    console.log(`Request ${requestId} completed in ${processingTime}ms`);

    return new Response(
      JSON.stringify({
        success: true,
        recommendations: enhancedRecommendations,
        metadata: {
          request_id: requestId,
          processing_time_ms: processingTime,
          ai_enhanced: enhancedRecommendations.some(r =>
            r.ai_reasoning && !r.ai_reasoning.includes('temporarily unavailable')
          ),
          generated_at: new Date().toISOString()
        }
      }),
      {
        headers: { ...corsHeaders, 'Content-Type': 'application/json' }
      }
    );

  } catch (error) {
    const processingTime = Date.now() - startTime;
    console.error(`Edge function error for request ${requestId || 'unknown'} after ${processingTime}ms:`, error);

    // Determine appropriate error response
    let statusCode = 500;
    let errorMessage = 'Internal server error';

    if (error.name === 'TimeoutError' || error.message.includes('timeout')) {
      statusCode = 504;
      errorMessage = 'Request timeout - please try again';
    } else if (error.message.includes('configuration') || error.message.includes('API key')) {
      statusCode = 503;
      errorMessage = 'Service temporarily unavailable';
    }

    return new Response(
      JSON.stringify({
        success: false,
        error: errorMessage,
        details: error.message,
        request_id: requestId || 'unknown',
        processing_time_ms: processingTime
      }),
      {
        status: statusCode,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' }
      }
    );
  }
});

/**
 * Validate request data
 */
function validateRequestData(context: any, recommendations: any): { valid: boolean; errors: string[] } {
  const errors: string[] = [];

  // Validate context
  if (!context) {
    errors.push('Missing context object');
  } else {
    if (!context.product_request_id) {
      errors.push('Missing context.product_request_id');
    }
    if (!context.wound_type) {
      errors.push('Missing context.wound_type');
    }
  }

  // Validate recommendations
  if (!recommendations) {
    errors.push('Missing rule_based_recommendations array');
  } else if (!Array.isArray(recommendations)) {
    errors.push('rule_based_recommendations must be an array');
  } else if (recommendations.length === 0) {
    errors.push('rule_based_recommendations array is empty');
  } else {
    // Validate each recommendation
    recommendations.forEach((rec, index) => {
      if (!rec.q_code) {
        errors.push(`Missing q_code in recommendation ${index}`);
      }
      if (typeof rec.rank !== 'number') {
        errors.push(`Invalid or missing rank in recommendation ${index}`);
      }
      if (typeof rec.confidence_score !== 'number') {
        errors.push(`Invalid or missing confidence_score in recommendation ${index}`);
      }
    });
  }

  return {
    valid: errors.length === 0,
    errors
  };
}

/**
 * Enhance rule-based recommendations using Azure OpenAI
 */
async function enhanceRecommendationsWithAI(
  context: RecommendationContext,
  ruleRecommendations: RuleBasedRecommendation[],
  productCatalog: any[],
  requestId: string
): Promise<AIEnhancedRecommendation[]> {

  console.log(`Building AI prompt for request ${requestId} with ${ruleRecommendations.length} recommendations`);

  // Build AI prompt
  const prompt = buildAIPrompt(context, ruleRecommendations, productCatalog);

  // Call Azure OpenAI with timeout
  console.log(`Calling Azure OpenAI for request ${requestId}`);
  const aiResponse = await callAzureOpenAI(prompt, requestId);

  // Parse and merge AI insights with rule-based recommendations
  console.log(`Merging AI insights for request ${requestId}`);
  return mergeAIInsights(ruleRecommendations, aiResponse);
}

/**
 * Build comprehensive prompt for Azure OpenAI
 */
function buildAIPrompt(
  context: RecommendationContext,
  ruleRecommendations: RuleBasedRecommendation[],
  productCatalog: any[]
): string {
  const availableProducts = productCatalog
    .map(p => `${p.q_code}: ${p.name} (${p.category}, ${p.manufacturer})`)
    .join('\n');

  return `
You are an expert wound care specialist AI assistant helping to optimize product recommendations for wound treatment.

## CLINICAL CONTEXT:
- Wound Type: ${context.wound_type}
- Wound Characteristics: ${JSON.stringify(context.wound_characteristics, null, 2)}
- Clinical Data: ${JSON.stringify(context.clinical_data, null, 2)}
- Patient Factors: ${JSON.stringify(context.patient_factors, null, 2)}
- Prior Treatments: ${JSON.stringify(context.prior_treatments, null, 2)}
- Payer Context: ${JSON.stringify(context.payer_context, null, 2)}

## AVAILABLE PRODUCTS:
${availableProducts}

## RULE-BASED RECOMMENDATIONS:
${ruleRecommendations.map((rec, idx) =>
  `${idx + 1}. ${rec.q_code} (Rank: ${rec.rank}, Confidence: ${rec.confidence_score})
     Reasoning: ${rec.reasoning}
     Benefits: ${rec.key_benefits.join(', ')}`
).join('\n\n')}

## INSTRUCTIONS:
Analyze the clinical context and rule-based recommendations. For each recommended product, provide:

1. **AI_REASONING**: Enhanced clinical rationale considering all patient factors
2. **CONFIDENCE_ADJUSTMENT**: Adjustment to confidence score (-0.3 to +0.3) based on your analysis
3. **SIZE_RECOMMENDATION**: Optimal size in cm² based on wound characteristics
4. **AI_INSIGHTS**: Key clinical insights that support or modify the recommendation
5. **RISK_FACTORS**: Any contraindications or risk factors to consider
6. **ALTERNATIVE_PRODUCTS**: Alternative Q-codes if current recommendation isn't optimal

## OUTPUT FORMAT:
Return a JSON array with the following structure for each recommendation:
[
  {
    "q_code": "existing_q_code",
    "ai_reasoning": "detailed clinical reasoning",
    "ai_confidence_adjustment": 0.1,
    "ai_size_recommendation": 6.25,
    "ai_insights": ["insight 1", "insight 2"],
    "risk_factors": ["risk 1", "risk 2"],
    "alternative_products": ["Q4567", "Q8901"]
  }
]

Focus on evidence-based recommendations considering:
- Wound healing physiology
- Patient comorbidities and medications
- Cost-effectiveness for the payer
- Clinical outcomes data
- Risk-benefit analysis

Only recommend products that exist in the available products list.
`;
}

/**
 * Call Azure OpenAI API with timeout and enhanced error handling
 */
async function callAzureOpenAI(prompt: string, requestId: string): Promise<any> {
  const url = `${AZURE_OPENAI_CONFIG.endpoint}/openai/deployments/${AZURE_OPENAI_CONFIG.deploymentName}/chat/completions?api-version=${AZURE_OPENAI_CONFIG.apiVersion}`;

  const requestBody = {
    messages: [
      {
        role: 'system',
        content: 'You are an expert wound care specialist AI assistant. Provide evidence-based, clinically sound recommendations for wound care products. Always respond with valid JSON format.'
      },
      {
        role: 'user',
        content: prompt
      }
    ],
    max_tokens: 2500,
    temperature: 0.3, // Lower temperature for more consistent medical recommendations
    top_p: 0.9,
    frequency_penalty: 0.0,
    presence_penalty: 0.0
  };

  const timeoutMs = 25000; // 25 second timeout

  try {
    const response = await Promise.race([
      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'api-key': AZURE_OPENAI_CONFIG.apiKey!,
        },
        body: JSON.stringify(requestBody)
      }),
      new Promise<never>((_, reject) =>
        setTimeout(() => reject(new Error('Azure OpenAI request timeout')), timeoutMs)
      )
    ]);

    if (!response.ok) {
      const errorText = await response.text();
      console.error(`Azure OpenAI API error for request ${requestId}:`, response.status, errorText);

      if (response.status === 429) {
        throw new Error('Azure OpenAI rate limit exceeded - please try again in a moment');
      } else if (response.status === 401) {
        throw new Error('Azure OpenAI authentication failed - invalid API key');
      } else if (response.status >= 500) {
        throw new Error('Azure OpenAI service temporarily unavailable');
      } else {
        throw new Error(`Azure OpenAI API error: ${response.status} - ${errorText}`);
      }
    }

    const data = await response.json();

    // Extract and parse the AI response
    const aiContent = data.choices?.[0]?.message?.content;
    if (!aiContent) {
      console.error(`No content received from Azure OpenAI for request ${requestId}`);
      throw new Error('No content received from Azure OpenAI');
    }

    console.log(`Azure OpenAI response received for request ${requestId}, length: ${aiContent.length}`);

    // Enhanced JSON parsing with multiple fallback strategies
    try {
      // Strategy 1: Parse the entire content as JSON
      const parsed = JSON.parse(aiContent);
      if (Array.isArray(parsed)) {
        console.log(`Successfully parsed JSON array for request ${requestId}, items: ${parsed.length}`);
        return parsed;
      }

      // If parsed but not array, wrap in array if it's an object
      if (typeof parsed === 'object' && parsed !== null) {
        console.log(`Wrapped single object in array for request ${requestId}`);
        return [parsed];
      }

    } catch (parseError) {
      console.log(`Direct JSON parse failed for request ${requestId}, trying extraction strategies`);
    }

    // Strategy 2: Extract JSON array from markdown code blocks
    const codeBlockMatch = aiContent.match(/```(?:json)?\s*(\[[\s\S]*?\])\s*```/);
    if (codeBlockMatch) {
      try {
        const parsed = JSON.parse(codeBlockMatch[1]);
        console.log(`Successfully extracted JSON from code block for request ${requestId}`);
        return parsed;
      } catch (e) {
        console.warn(`Code block extraction failed for request ${requestId}:`, e);
      }
    }

    // Strategy 3: Find any JSON array in the text
    const jsonArrayMatch = aiContent.match(/\[[\s\S]*\]/);
    if (jsonArrayMatch) {
      try {
        const parsed = JSON.parse(jsonArrayMatch[0]);
        console.log(`Successfully extracted JSON array from text for request ${requestId}`);
        return parsed;
      } catch (e) {
        console.warn(`JSON array extraction failed for request ${requestId}:`, e);
      }
    }

    // Strategy 4: Try to find individual JSON objects and combine them
    const objectMatches = aiContent.match(/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/g);
    if (objectMatches && objectMatches.length > 0) {
      try {
        const objects = objectMatches.map(match => JSON.parse(match));
        console.log(`Successfully parsed ${objects.length} individual objects for request ${requestId}`);
        return objects;
      } catch (e) {
        console.warn(`Individual object parsing failed for request ${requestId}:`, e);
      }
    }

    // If all parsing strategies fail, log the response and return empty array
    console.error(`All JSON parsing strategies failed for request ${requestId}`);
    console.error(`Raw AI Response for request ${requestId}:`, aiContent);
    return [];

  } catch (error) {
    console.error(`Azure OpenAI call failed for request ${requestId}:`, error);
    throw error;
  }
}

/**
 * Merge AI insights with rule-based recommendations
 */
function mergeAIInsights(
  ruleRecommendations: RuleBasedRecommendation[],
  aiInsights: any[]
): AIEnhancedRecommendation[] {

  return ruleRecommendations.map(ruleRec => {
    // Find corresponding AI insight
    const aiInsight = aiInsights.find(ai => ai.q_code === ruleRec.q_code);

    if (!aiInsight) {
      // No AI enhancement available, return original with default values
      return {
        ...ruleRec,
        ai_reasoning: 'AI analysis not available',
        ai_confidence_adjustment: 0,
        ai_insights: [],
        risk_factors: [],
        alternative_products: []
      };
    }

    // Apply AI confidence adjustment
    const adjustedConfidence = Math.max(0, Math.min(1,
      ruleRec.confidence_score + (aiInsight.ai_confidence_adjustment || 0)
    ));

    return {
      ...ruleRec,
      confidence_score: adjustedConfidence,
      ai_reasoning: aiInsight.ai_reasoning || 'AI analysis completed',
      ai_confidence_adjustment: aiInsight.ai_confidence_adjustment || 0,
      ai_size_recommendation: aiInsight.ai_size_recommendation || ruleRec.suggested_size,
      ai_insights: aiInsight.ai_insights || [],
      risk_factors: aiInsight.risk_factors || [],
      alternative_products: aiInsight.alternative_products || []
    };
  });
}

/**
 * Log AI usage for analytics and monitoring
 */
async function logAIUsage(
  supabase: any,
  productRequestId: string,
  recommendations: AIEnhancedRecommendation[],
  requestId: string
): Promise<void> {
  try {
    const aiEnhancedCount = recommendations.filter(r =>
      r.ai_reasoning &&
      !r.ai_reasoning.includes('temporarily unavailable') &&
      r.ai_reasoning !== 'AI analysis not available'
    ).length;

    await supabase
      .from('ai_usage_logs')
      .insert({
        function_name: 'product-recommendations-ai',
        product_request_id: productRequestId,
        request_id: requestId,
        recommendations_count: recommendations.length,
        ai_enhanced_count: aiEnhancedCount,
        success: aiEnhancedCount > 0,
        created_at: new Date().toISOString()
      });

    console.log(`AI usage logged for request ${requestId}: ${aiEnhancedCount}/${recommendations.length} recommendations enhanced`);
  } catch (error) {
    console.error(`Failed to log AI usage for request ${requestId}:`, error);
    // Don't throw error here to avoid breaking the main function
  }
}
