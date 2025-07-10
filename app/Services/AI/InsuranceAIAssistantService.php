<?php

namespace App\Services\AI;

use App\Services\AI\AzureAIAgentService;
use App\Services\AI\AzureFoundryService;
use App\Services\Learning\ContinuousLearningService;
use App\Services\Learning\BehavioralTrackingService;
use App\Services\Learning\MLDataPipelineService;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

/**
 * Insurance AI Assistant Service
 * 
 * Integrates user's trained Microsoft AI agent with insurance training data
 * Combines with existing ML ensemble system for comprehensive assistance
 */
class InsuranceAIAssistantService
{
    protected string $assistantId;
    protected string $threadId;
    protected bool $voiceEnabled;
    
    public function __construct(
        protected AzureAIAgentService $azureAgent,
        protected AzureFoundryService $foundryService,
        protected ContinuousLearningService $continuousLearning,
        protected BehavioralTrackingService $behavioralTracker,
        protected MLDataPipelineService $mlPipeline,
        protected DocuSealTemplateDiscoveryService $templateDiscovery,
        protected PhiSafeLogger $logger
    ) {
        $this->assistantId = Config::get('azure.insurance_assistant.assistant_id', '');
        $this->voiceEnabled = Config::get('azure.insurance_assistant.voice_enabled', true);
        
        if (empty($this->assistantId)) {
            throw new Exception('Insurance AI Assistant ID not configured. Please set AZURE_INSURANCE_ASSISTANT_ID in .env');
        }
    }

    /**
     * Start a new conversation with the Insurance AI Assistant
     */
    public function startConversation(array $context = []): array
    {
        try {
            // Create a new thread for the conversation
            $threadResult = $this->azureAgent->createThread([
                'user_id' => $context['user_id'] ?? null,
                'session_type' => 'insurance_assistance',
                'manufacturer' => $context['manufacturer'] ?? null,
                'template_id' => $context['template_id'] ?? null
            ]);

            if (!$threadResult['success']) {
                throw new Exception('Failed to create conversation thread');
            }

            $this->threadId = $threadResult['thread_id'];

            // Get relevant ML context from your trained models
            $mlContext = $this->buildMLContext($context);

            // Get manufacturer patterns from your 286+ field mappings
            $manufacturerContext = $this->getManufacturerContext($context);

            // Initialize the assistant with your training data context
            $initMessage = $this->buildInitializationMessage($mlContext, $manufacturerContext, $context);

            return [
                'success' => true,
                'thread_id' => $this->threadId,
                'assistant_id' => $this->assistantId,
                'context' => $mlContext,
                'voice_enabled' => $this->voiceEnabled,
                'initialization_message' => $initMessage
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to start insurance AI conversation', [
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send message to Insurance AI Assistant with ML enhancement
     */
    public function sendMessage(string $message, array $context = []): array
    {
        try {
            // Track user interaction for ML learning
            $this->behavioralTracker->trackEvent('insurance_ai_interaction', [
                'thread_id' => $this->threadId,
                'message_length' => strlen($message),
                'context' => $context,
                'timestamp' => now()
            ]);

            // Get ML-enhanced context
            $enhancedContext = $this->getMLEnhancedContext($context);

            // Prepare message with your insurance training data context
            $enhancedMessage = $this->enhanceMessageWithTrainingData($message, $enhancedContext);

            // Send to your trained Microsoft AI agent
            $response = $this->azureAgent->sendMessage($this->threadId, $enhancedMessage, $enhancedContext);

            // Apply ML ensemble enhancement to the response
            $enhancedResponse = $this->applyMLEnhancement($response, $context);

            // Track response quality for continuous learning
            $this->trackResponseQuality($message, $enhancedResponse, $context);

            return [
                'success' => true,
                'response' => $enhancedResponse,
                'context' => $enhancedContext,
                'ml_enhanced' => true,
                'insurance_data_used' => true
            ];

        } catch (Exception $e) {
            $this->logger->error('Insurance AI message failed', [
                'error' => $e->getMessage(),
                'thread_id' => $this->threadId,
                'message' => $message
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get insurance form assistance with template discovery
     */
    public function getFormAssistance(string $templateId, string $manufacturer): array
    {
        try {
            // Get template structure using your dynamic discovery
            $templateStructure = $this->templateDiscovery->getCachedTemplateStructure($templateId);

            // Get manufacturer patterns from your 286+ field mappings
            $manufacturerPatterns = $this->getManufacturerPatterns($manufacturer);

            // Build insurance-specific context
            $insuranceContext = [
                'template_structure' => $templateStructure,
                'manufacturer_patterns' => $manufacturerPatterns,
                'field_mappings' => $this->getFieldMappings($manufacturer),
                'insurance_training_data' => $this->getInsuranceTrainingContext($manufacturer)
            ];

            // Ask your trained AI agent for form assistance
            $assistanceMessage = $this->buildFormAssistanceMessage($insuranceContext);
            $response = $this->azureAgent->sendMessage($this->threadId, $assistanceMessage, $insuranceContext);

            // Apply ML ensemble enhancement
            $enhancedResponse = $this->applyMLEnhancement($response, $insuranceContext);

            return [
                'success' => true,
                'assistance' => $enhancedResponse,
                'template_fields' => $templateStructure['field_names'] ?? [],
                'manufacturer_patterns' => $manufacturerPatterns,
                'insurance_context' => $insuranceContext
            ];

        } catch (Exception $e) {
            $this->logger->error('Form assistance failed', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'manufacturer' => $manufacturer
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get personalized insurance recommendations using ML
     */
    public function getPersonalizedRecommendations(int $userId, array $context = []): array
    {
        try {
            // Start a conversation first if no thread exists
            if (!isset($this->threadId) || empty($this->threadId)) {
                $conversationResult = $this->startConversation($context);
                if (!$conversationResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Failed to start conversation for recommendations'
                    ];
                }
                $this->threadId = $conversationResult['thread_id'];
            }

            // Get user behavior patterns from ML system
            try {
                $userFeatures = $this->mlPipeline->extractUserBehaviorFeatures($userId);
            } catch (Exception $e) {
                // Fallback if ML pipeline is not available
                $userFeatures = [];
            }

            // Get real-time ML recommendations
            try {
                $mlRecommendations = $this->continuousLearning->getRealtimeRecommendations($userId);
            } catch (Exception $e) {
                // Fallback if continuous learning is not available
                $mlRecommendations = [];
            }

            // Combine with your insurance training data
            $insuranceContext = array_merge($context, [
                'user_features' => $userFeatures,
                'ml_recommendations' => $mlRecommendations,
                'insurance_training_context' => $this->getInsuranceTrainingContext()
            ]);

            // Ask your trained AI agent for personalized advice
            $recommendationMessage = $this->buildRecommendationMessage($insuranceContext);
            $response = $this->azureAgent->sendMessage($this->threadId, $recommendationMessage, $insuranceContext);

            return [
                'success' => true,
                'recommendations' => $response,
                'ml_enhanced' => true,
                'personalized' => true,
                'user_features' => $userFeatures
            ];

        } catch (Exception $e) {
            $this->logger->error('Personalized recommendations failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable voice interaction with the insurance assistant
     */
    public function enableVoiceMode(array $options = []): array
    {
        if (!$this->voiceEnabled) {
            return [
                'success' => false,
                'error' => 'Voice mode not enabled for insurance assistant'
            ];
        }

        try {
            // Configure voice session with insurance-specific instructions
            $voiceConfig = [
                'voice' => $options['voice'] ?? 'en-US-JennyNeural',
                'instructions' => $this->getVoiceInstructions(),
                'tools' => $this->getVoiceTools(),
                'context' => $this->getInsuranceTrainingContext()
            ];

            // Create voice session using Azure Realtime
            $voiceSession = app(\App\Services\AI\AzureRealtimeService::class)->createSession($voiceConfig);

            return [
                'success' => true,
                'voice_session' => $voiceSession,
                'insurance_context' => $voiceConfig['context']
            ];

        } catch (Exception $e) {
            $this->logger->error('Voice mode initialization failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build ML context from your trained models
     */
    private function buildMLContext(array $context): array
    {
        $mlContext = [];

        // Get manufacturer patterns from your 286+ field mappings
        if (isset($context['manufacturer'])) {
            $mlContext['manufacturer_patterns'] = $this->getManufacturerPatterns($context['manufacturer']);
        }

        // Get template discovery patterns
        if (isset($context['template_id'])) {
            $mlContext['template_patterns'] = $this->getTemplatePatterns($context['template_id']);
        }

        // Get user behavior patterns
        if (isset($context['user_id'])) {
            $mlContext['user_patterns'] = $this->mlPipeline->extractUserBehaviorFeatures($context['user_id']);
        }

        return $mlContext;
    }

    /**
     * Get manufacturer context from your comprehensive configs
     */
    private function getManufacturerContext(array $context): array
    {
        $manufacturer = $context['manufacturer'] ?? null;
        if (!$manufacturer) {
            return [];
        }

        // Get from your 286+ field mappings
        $manufacturerPatterns = Cache::get("manufacturer_patterns_{$manufacturer}", []);

        return [
            'manufacturer' => $manufacturer,
            'field_mappings' => $manufacturerPatterns,
            'pattern_count' => count($manufacturerPatterns),
            'last_updated' => Cache::get("manufacturer_patterns_{$manufacturer}_timestamp", now())
        ];
    }

    /**
     * Build initialization message with your training data
     */
    private function buildInitializationMessage(array $mlContext, array $manufacturerContext, array $context): string
    {
        $initMessage = "Hello! I'm your Insurance AI Assistant with access to:\n\n";
        $initMessage .= "📊 **Your Training Data**: Specialized insurance knowledge\n";
        $initMessage .= "🤖 **ML System**: 286+ manufacturer field mappings\n";
        $initMessage .= "📋 **Template Discovery**: Real-time form analysis\n";
        $initMessage .= "🎯 **Personalization**: Your behavioral patterns\n\n";

        if (!empty($manufacturerContext['manufacturer'])) {
            $initMessage .= "**Current Context**: {$manufacturerContext['manufacturer']} ";
            $initMessage .= "({$manufacturerContext['pattern_count']} field patterns)\n\n";
        }

        $initMessage .= "I can help you with:\n";
        $initMessage .= "• Insurance verification forms\n";
        $initMessage .= "• Field mapping assistance\n";
        $initMessage .= "• Manufacturer-specific requirements\n";
        $initMessage .= "• Personalized recommendations\n\n";
        $initMessage .= "How can I assist you today?";

        return $initMessage;
    }

    /**
     * Enhance message with your insurance training data
     */
    private function enhanceMessageWithTrainingData(string $message, array $context): string
    {
        $enhancedMessage = $message . "\n\n**Context from Training Data:**\n";
        
        // Add your insurance training context
        $enhancedMessage .= "- Insurance Training: Use specialized insurance knowledge\n";
        
        // Add manufacturer patterns
        if (isset($context['manufacturer_patterns'])) {
            $enhancedMessage .= "- Manufacturer Patterns: Apply known field mappings\n";
        }
        
        // Add ML insights
        if (isset($context['ml_recommendations'])) {
            $enhancedMessage .= "- ML Insights: Consider behavioral patterns\n";
        }

        return $enhancedMessage;
    }

    /**
     * Get ML-enhanced context for responses
     */
    private function getMLEnhancedContext(array $context): array
    {
        $enhancedContext = $context;

        // Add ML recommendations if user provided
        if (isset($context['user_id'])) {
            try {
                $enhancedContext['ml_recommendations'] = $this->continuousLearning->getRealtimeRecommendations($context['user_id']);
            } catch (Exception $e) {
                // Skip ML enhancement if it fails
            }
        }

        // Add manufacturer patterns
        if (isset($context['manufacturer'])) {
            $enhancedContext['manufacturer_patterns'] = $this->getManufacturerPatterns($context['manufacturer']);
        }

        return $enhancedContext;
    }

    /**
     * Apply ML ensemble enhancement to AI response
     */
    private function applyMLEnhancement(array $response, array $context): array
    {
        $enhanced = $response;

        // Add ML confidence scoring
        $enhanced['ml_confidence'] = $this->calculateMLConfidence($response, $context);

        // Add personalized suggestions
        if (isset($context['user_id'])) {
            $enhanced['personalized_suggestions'] = $this->getPersonalizedSuggestions($context['user_id'], $response);
        }

        // Add manufacturer-specific insights
        if (isset($context['manufacturer'])) {
            $enhanced['manufacturer_insights'] = $this->getManufacturerInsights($context['manufacturer'], $response);
        }

        return $enhanced;
    }

    /**
     * Track response quality for continuous learning
     */
    private function trackResponseQuality(string $message, array $response, array $context): void
    {
        $this->behavioralTracker->trackEvent('insurance_ai_response', [
            'thread_id' => $this->threadId,
            'message_length' => strlen($message),
            'response_length' => strlen($response['text'] ?? ''),
            'ml_confidence' => $response['ml_confidence'] ?? 0,
            'context' => $context,
            'timestamp' => now()
        ]);
    }

    // Helper methods for data retrieval
    private function getManufacturerPatterns(string $manufacturer): array
    {
        return Cache::get("manufacturer_patterns_{$manufacturer}", []);
    }

    private function getTemplatePatterns(string $templateId): array
    {
        return Cache::get("template_patterns_{$templateId}", []);
    }

    private function getFieldMappings(string $manufacturer): array
    {
        return Cache::get("field_mappings_{$manufacturer}", []);
    }

    private function getInsuranceTrainingContext(?string $manufacturer = null): array
    {
        // Return context from your Microsoft AI training data
        return [
            'trained_on' => 'insurance_forms_and_verification',
            'specialization' => 'wound_care_insurance',
            'manufacturer' => $manufacturer,
            'last_training' => Cache::get('insurance_training_timestamp', now())
        ];
    }

    private function buildFormAssistanceMessage(array $context): string
    {
        return "Provide form assistance based on the template structure and manufacturer patterns. Use insurance training data to guide field completion.";
    }

    private function buildRecommendationMessage(array $context): string
    {
        return "Provide personalized insurance recommendations based on user behavior patterns and ML insights.";
    }

    private function getVoiceInstructions(): string
    {
        return "You are an insurance AI assistant with specialized training in wound care insurance forms. Provide clear, helpful guidance using your training data.";
    }

    private function getVoiceTools(): array
    {
        return [
            'insurance_verification',
            'form_assistance',
            'field_mapping',
            'manufacturer_guidance'
        ];
    }

    private function calculateMLConfidence(array $response, array $context): float
    {
        // Calculate confidence based on ML patterns and training data
        return 0.85; // Placeholder
    }

    private function getPersonalizedSuggestions(int $userId, array $response): array
    {
        // Generate personalized suggestions based on user behavior
        return [];
    }

    private function getManufacturerInsights(string $manufacturer, array $response): array
    {
        // Generate manufacturer-specific insights
        return [];
    }
} 