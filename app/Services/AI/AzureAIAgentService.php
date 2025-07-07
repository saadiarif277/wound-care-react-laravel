<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Azure AI Foundry Agent Service
 * 
 * Integrates with Azure AI Foundry Agent Service for advanced AI capabilities including:
 * - Function calling for dynamic tool use
 * - File search for knowledge base queries
 * - Code interpreter for data analysis
 * - Azure AI Search integration
 */
class AzureAIAgentService
{
    private string $endpoint;
    private string $apiKey;
    private string $assistantId;
    private string $apiVersion = '2024-02-15-preview';

    public function __construct()
    {
        $this->endpoint = config('azure.ai_agent.endpoint', '');
        $this->apiKey = config('azure.ai_agent.api_key', '');
        $this->assistantId = config('azure.ai_agent.assistant_id', '');

        if (empty($this->endpoint) || empty($this->apiKey)) {
            Log::warning('Azure AI Agent Service not configured. Falling back to OpenAI service.');
        }
    }

    /**
     * Create a new conversation thread
     */
    public function createThread(array $metadata = []): array
    {
        try {
            $response = $this->makeRequest('POST', '/threads', [
                'metadata' => $metadata
            ]);

            Log::info('Created AI Agent thread', ['thread_id' => $response['id'] ?? null]);

            return [
                'success' => true,
                'thread_id' => $response['id'],
                'created_at' => $response['created_at'] ?? time()
            ];

        } catch (Exception $e) {
            Log::error('Failed to create AI Agent thread', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send a message to the AI Agent and get a response
     */
    public function sendMessage(string $threadId, string $message, array $context = []): array
    {
        try {
            // Add message to thread
            $messageResponse = $this->makeRequest('POST', "/threads/{$threadId}/messages", [
                'role' => 'user',
                'content' => $message,
                'metadata' => $context
            ]);

            // Create a run with function calling enabled
            $runResponse = $this->makeRequest('POST', "/threads/{$threadId}/runs", [
                'assistant_id' => $this->assistantId,
                'tools' => $this->getAvailableTools(),
                'instructions' => $this->getAgentInstructions($context)
            ]);

            $runId = $runResponse['id'];

            // Wait for completion
            $result = $this->waitForCompletion($threadId, $runId);

            // Get the assistant's response
            $messages = $this->getThreadMessages($threadId);
            $assistantMessage = $this->extractLatestAssistantMessage($messages);

            return [
                'success' => true,
                'reply' => $assistantMessage['content'] ?? '',
                'tool_calls' => $assistantMessage['tool_calls'] ?? [],
                'metadata' => $assistantMessage['metadata'] ?? []
            ];

        } catch (Exception $e) {
            Log::error('Failed to send message to AI Agent', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Define available tools for the AI Agent
     */
    protected function getAvailableTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_product_request',
                    'description' => 'Create a new product request for wound care supplies',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'patient_name' => ['type' => 'string', 'description' => 'Patient full name'],
                            'patient_dob' => ['type' => 'string', 'description' => 'Patient date of birth (YYYY-MM-DD)'],
                            'primary_diagnosis' => ['type' => 'string', 'description' => 'Primary ICD-10 diagnosis code'],
                            'product_name' => ['type' => 'string', 'description' => 'Name of the requested product'],
                            'quantity' => ['type' => 'integer', 'description' => 'Quantity needed'],
                            'clinical_justification' => ['type' => 'string', 'description' => 'Clinical reason for the product']
                        ],
                        'required' => ['patient_name', 'patient_dob', 'primary_diagnosis', 'product_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for wound care products by criteria',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query for products'],
                            'category' => ['type' => 'string', 'description' => 'Product category filter'],
                            'manufacturer' => ['type' => 'string', 'description' => 'Manufacturer name filter']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'validate_insurance',
                    'description' => 'Validate patient insurance eligibility',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'payer_name' => ['type' => 'string', 'description' => 'Insurance payer name'],
                            'member_id' => ['type' => 'string', 'description' => 'Member ID'],
                            'group_number' => ['type' => 'string', 'description' => 'Group number'],
                            'patient_dob' => ['type' => 'string', 'description' => 'Patient date of birth']
                        ],
                        'required' => ['payer_name', 'member_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'process_clinical_document',
                    'description' => 'Process and extract information from clinical documents',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'document_type' => ['type' => 'string', 'enum' => ['clinical_note', 'wound_photo', 'insurance_card']],
                            'document_content' => ['type' => 'string', 'description' => 'Base64 encoded document or text content']
                        ],
                        'required' => ['document_type', 'document_content']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_medicare_coverage',
                    'description' => 'Check Medicare coverage for a specific product and diagnosis',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_code' => ['type' => 'string', 'description' => 'HCPCS product code'],
                            'diagnosis_code' => ['type' => 'string', 'description' => 'ICD-10 diagnosis code'],
                            'state' => ['type' => 'string', 'description' => 'Two-letter state code']
                        ],
                        'required' => ['product_code', 'diagnosis_code', 'state']
                    ]
                ]
            ],
            [
                'type' => 'file_search',
                'file_search' => [
                    'max_num_results' => 10
                ]
            ],
            [
                'type' => 'code_interpreter'
            ]
        ];
    }

    /**
     * Get context-specific instructions for the agent
     */
    protected function getAgentInstructions(array $context): string
    {
        $baseInstructions = "You are a helpful AI assistant for MSC Wound Care Portal. You help healthcare providers with:
- Creating product requests for wound care supplies
- Processing clinical documentation and insurance information
- Checking Medicare coverage and eligibility
- Answering questions about wound care products and procedures

Always be professional, accurate, and HIPAA-compliant. When handling PHI, ensure privacy and security.";

        if (isset($context['user_role'])) {
            $baseInstructions .= "\n\nThe user is a {$context['user_role']}.";
        }

        if (isset($context['current_task'])) {
            $baseInstructions .= "\n\nCurrent task: {$context['current_task']}";
        }

        return $baseInstructions;
    }

    /**
     * Wait for a run to complete
     */
    protected function waitForCompletion(string $threadId, string $runId, int $maxAttempts = 30): array
    {
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $run = $this->makeRequest('GET', "/threads/{$threadId}/runs/{$runId}");
            
            switch ($run['status']) {
                case 'completed':
                    return $run;
                    
                case 'requires_action':
                    // Handle function calls
                    $this->handleRequiredActions($threadId, $runId, $run);
                    break;
                    
                case 'failed':
                case 'cancelled':
                case 'expired':
                    throw new Exception("Run failed with status: {$run['status']}");
                    
                default:
                    // Still processing
                    sleep(1);
            }
            
            $attempt++;
        }
        
        throw new Exception('Run timed out');
    }

    /**
     * Handle required actions (function calls)
     */
    protected function handleRequiredActions(string $threadId, string $runId, array $run): void
    {
        $toolOutputs = [];
        
        foreach ($run['required_action']['submit_tool_outputs']['tool_calls'] as $toolCall) {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);
            
            $result = $this->executeTool($functionName, $arguments);
            
            $toolOutputs[] = [
                'tool_call_id' => $toolCall['id'],
                'output' => json_encode($result)
            ];
        }
        
        // Submit tool outputs
        $this->makeRequest('POST', "/threads/{$threadId}/runs/{$runId}/submit_tool_outputs", [
            'tool_outputs' => $toolOutputs
        ]);
    }

    /**
     * Execute a tool function
     */
    protected function executeTool(string $functionName, array $arguments): array
    {
        Log::info('Executing AI Agent tool', [
            'function' => $functionName,
            'arguments' => $arguments
        ]);

        try {
            switch ($functionName) {
                case 'create_product_request':
                    return $this->toolCreateProductRequest($arguments);
                    
                case 'search_products':
                    return $this->toolSearchProducts($arguments);
                    
                case 'validate_insurance':
                    return $this->toolValidateInsurance($arguments);
                    
                case 'process_clinical_document':
                    return $this->toolProcessClinicalDocument($arguments);
                    
                case 'check_medicare_coverage':
                    return $this->toolCheckMedicareCoverage($arguments);
                    
                default:
                    return ['error' => "Unknown function: {$functionName}"];
            }
        } catch (Exception $e) {
            Log::error('Tool execution failed', [
                'function' => $functionName,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Tool: Create Product Request
     */
    protected function toolCreateProductRequest(array $args): array
    {
        // This would integrate with your existing product request system
        return [
            'success' => true,
            'request_id' => 'PR-' . time(),
            'message' => "Product request created for {$args['patient_name']}"
        ];
    }

    /**
     * Tool: Search Products
     */
    protected function toolSearchProducts(array $args): array
    {
        // This would search your product database
        return [
            'success' => true,
            'products' => [
                [
                    'name' => 'Mepilex Border Sacrum',
                    'manufacturer' => 'Molnlycke',
                    'code' => 'A6212',
                    'description' => 'Foam dressing with border'
                ]
            ]
        ];
    }

    /**
     * Tool: Validate Insurance
     */
    protected function toolValidateInsurance(array $args): array
    {
        // This would check with your eligibility service
        return [
            'success' => true,
            'eligible' => true,
            'coverage_details' => [
                'deductible_met' => true,
                'copay' => 20
            ]
        ];
    }

    /**
     * Tool: Process Clinical Document
     */
    protected function toolProcessClinicalDocument(array $args): array
    {
        // This would use your document processing service
        return [
            'success' => true,
            'extracted_data' => [
                'diagnosis' => 'L97.522',
                'wound_location' => 'left foot'
            ]
        ];
    }

    /**
     * Tool: Check Medicare Coverage
     */
    protected function toolCheckMedicareCoverage(array $args): array
    {
        // This would use your Medicare validation service
        return [
            'success' => true,
            'covered' => true,
            'lcd_number' => 'L33831',
            'requirements' => ['Documentation of wound measurements required']
        ];
    }

    /**
     * Get messages from a thread
     */
    protected function getThreadMessages(string $threadId): array
    {
        $response = $this->makeRequest('GET', "/threads/{$threadId}/messages");
        return $response['data'] ?? [];
    }

    /**
     * Extract the latest assistant message
     */
    protected function extractLatestAssistantMessage(array $messages): array
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'assistant') {
                return $message;
            }
        }
        
        return ['content' => 'No response received'];
    }

    /**
     * Make HTTP request to Azure AI Agent Service
     */
    protected function makeRequest(string $method, string $path, array $data = []): array
    {
        $url = "{$this->endpoint}/openai{$path}?api-version={$this->apiVersion}";
        
        $request = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
        ->timeout(60)
        ->retry(3, 1000);
        
        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new Exception("Unsupported HTTP method: {$method}")
        };
        
        if (!$response->successful()) {
            Log::error('Azure AI Agent API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception("API request failed: " . $response->body());
        }
        
        return $response->json();
    }

    /**
     * Check if agent service is configured and available
     */
    public function isConfigured(): bool
    {
        return !empty($this->endpoint) && !empty($this->apiKey) && !empty($this->assistantId);
    }
} 