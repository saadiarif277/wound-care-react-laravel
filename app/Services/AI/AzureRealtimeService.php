<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Exception;

class AzureRealtimeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        // Use Azure OpenAI configuration
        $this->apiKey = config('azure.ai_foundry.api_key');
        $this->model = 'gpt-4o-mini-realtime-preview';
    }

    /**
     * Initialize a realtime voice session
     * Returns configuration for frontend to establish WebSocket connection
     */
    public function createSession(array $options = []): array
    {
        try {
            // Generate ephemeral token for frontend (you'll need to implement this)
            // For now, we'll provide configuration for direct connection
            
            $sessionId = uniqid('realtime_');
            
            // Store session configuration that frontend will use
            $sessionConfig = [
                'type' => 'session.update',
                'session' => [
                    'modalities' => ['text', 'audio'],
                    'instructions' => $this->getSystemInstructions(),
                    'voice' => $options['voice'] ?? 'alloy',
                    'input_audio_format' => 'webm-opus',
                    'output_audio_format' => 'mp3',
                    'input_audio_transcription' => [
                        'model' => 'whisper-1'
                    ],
                    'turn_detection' => [
                        'type' => 'server_vad',
                        'threshold' => 0.5,
                        'prefix_padding_ms' => 300,
                        'silence_duration_ms' => 200,
                    ],
                    'tools' => $this->getAvailableTools(),
                ]
            ];

            // Build Azure OpenAI Realtime WebSocket URL
            $azureEndpoint = config('azure.ai_foundry.endpoint');
            $deployment = config('azure.ai_foundry.realtime_deployment', 'gpt-4o-mini-realtime-preview');
            $apiVersion = config('azure.ai_foundry.realtime_api_version', '2024-10-01-preview');
            
            // Convert HTTPS endpoint to WSS
            $websocketUrl = str_replace('https://', 'wss://', rtrim($azureEndpoint, '/'));
            
            // For Azure WebSocket auth, we need to include the key in the URL
            // This is not ideal for production - consider using a proxy server
            $websocketUrl .= "/openai/realtime?api-version={$apiVersion}&deployment={$deployment}&api-key={$this->apiKey}";

            return [
                'success' => true,
                'session_id' => $sessionId,
                'websocket_url' => $websocketUrl,
                'model' => $this->model,
                'deployment' => $deployment,
                'session_config' => $sessionConfig,
                'warning' => 'API key included in URL for Azure WebSocket auth. Use proxy server in production.'
            ];

        } catch (Exception $e) {
            Log::error('Failed to create realtime session', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get session configuration for reconnection
     */
    public function getSessionConfig(string $sessionId): array
    {
        // In production, you'd retrieve this from cache/database
        return [
            'session_id' => $sessionId,
            'model' => $this->model,
            'websocket_url' => 'wss://api.openai.com/v1/realtime',
        ];
    }

    /**
     * Generate ephemeral token for secure frontend connection
     * This is a placeholder - implement proper token generation in production
     */
    public function generateEphemeralToken(string $sessionId): string
    {
        // In production:
        // 1. Generate a short-lived token
        // 2. Store it with the session ID
        // 3. Frontend uses this instead of the main API key
        
        // For now, returning a placeholder
        return 'ephemeral_' . $sessionId . '_' . time();
    }

    /**
     * Close/invalidate a session
     */
    public function closeSession(string $sessionId): void
    {
        // In production, invalidate any ephemeral tokens
        // and clean up session data
        
        Log::info('Closing realtime session', ['session_id' => $sessionId]);
    }

    /**
     * Get system instructions for the realtime assistant
     */
    private function getSystemInstructions(): string
    {
        return "You are a helpful AI assistant for MSC Wound Care Portal. You help healthcare providers with:

1. **Product Requests**: Guide users through submitting new product requests
2. **Clinical Documentation**: Help with recording clinical notes and assessments  
3. **Document Processing**: Process uploaded documents and extract information
4. **General Support**: Answer questions about wound care products and procedures

Important Guidelines:
- Be conversational and natural in voice interactions
- Ask clarifying questions when needed
- Maintain HIPAA compliance
- If users want to upload documents, suggest switching to text mode
- Be concise in voice responses but thorough when needed

Voice-specific instructions:
- Keep responses brief and conversational
- Use natural speech patterns
- Pause appropriately for user input
- Be ready to be interrupted";
    }

    /**
     * Define available tools for the realtime session
     */
    private function getAvailableTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'switch_to_text_mode',
                    'description' => 'Switch from voice to text mode for document uploads or detailed forms',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Why switching to text mode'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function', 
                'function' => [
                    'name' => 'create_product_request',
                    'description' => 'Start a new product request form',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'patient_name' => ['type' => 'string'],
                            'product_type' => ['type' => 'string']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for wound care products',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                            'category' => ['type' => 'string']
                        ],
                        'required' => ['query']
                    ]
                ]
            ]
        ];
    }
} 