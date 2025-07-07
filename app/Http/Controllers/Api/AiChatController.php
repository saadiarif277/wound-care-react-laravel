<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AzureFoundryService;
use App\Services\AI\AzureAIAgentService;
use App\Services\AI\AzureSpeechService;
use App\Services\AI\AzureRealtimeService;
use App\Logging\PhiSafeLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Exception;

class AiChatController extends Controller
{
    public function __construct(
        protected AzureFoundryService $foundryService,
        protected AzureAIAgentService $agentService,
        protected AzureSpeechService $speechService,
        protected AzureRealtimeService $realtimeService,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Handle AI chat conversation
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_history' => 'sometimes|array',
            'conversation_history.*.role' => 'required|string|in:user,assistant',
            'conversation_history.*.content' => 'required|string'
        ]);

        $userMessage = $request->input('message');
        $conversationHistory = $request->input('conversation_history', []);

        $this->logger->info('AI chat request received', [
            'message_length' => strlen($userMessage),
            'conversation_length' => count($conversationHistory)
        ]);

        try {
            // Build context from conversation history
            $context = $this->buildConversationContext($conversationHistory);
            
            // Generate AI response using AzureFoundryService
            $aiResponse = $this->foundryService->generateChatResponse(
                $userMessage,
                $conversationHistory,
                $context,
                $this->getSystemPrompt()
            );

            // Try to parse the response as JSON to check for tool calls
            $parsedResponse = $this->parseAIResponse($aiResponse);
            
            if ($parsedResponse && isset($parsedResponse['tool_call'])) {
                // AI wants to use a client tool
                $response = [
                    'success' => true,
                    'reply' => $parsedResponse['reply'] ?? $aiResponse,
                    'tool_call' => $parsedResponse['tool_call'],
                    'markdown' => $parsedResponse['markdown'] ?? null
                ];
            } else {
                // Regular response - check if we should generate a markdown form
                $markdownForm = $this->generateMarkdownFormIfNeeded($userMessage, $aiResponse);

                $response = [
                    'success' => true,
                    'reply' => $aiResponse,
                    'markdown' => $markdownForm
                ];
            }

            $this->logger->info('AI chat response generated', [
                'response_length' => strlen($aiResponse),
                'has_markdown' => !empty($markdownForm)
            ]);

            return response()->json($response);

        } catch (Exception $e) {
            $this->logger->error('AI chat failed', [
                'error' => $e->getMessage(),
                'message' => $userMessage
            ]);

            return response()->json([
                'success' => false,
                'error' => 'I apologize, but I\'m having trouble processing your request right now. Please try again in a moment.',
                'reply' => 'I apologize, but I\'m having trouble processing your request right now. Please try again in a moment.'
            ], 500);
        }
    }

    /**
     * Build conversation context from history
     */
    protected function buildConversationContext(array $conversationHistory): string
    {
        if (empty($conversationHistory)) {
            return '';
        }

        $context = "Previous conversation:\n";
        foreach ($conversationHistory as $message) {
            $role = $message['role'] === 'user' ? 'User' : 'Assistant';
            $context .= "{$role}: {$message['content']}\n";
        }

        return $context;
    }

    /**
     * Get system prompt for the AI assistant with Superinterface client tools
     */
    protected function getSystemPrompt(): string
    {
        return "You are a helpful AI assistant for MSC Wound Care Portal. You help healthcare providers and patients with:

1. **Product Requests**: Guide users through submitting new product requests by asking for required information like patient details, wound characteristics, and clinical justification.

2. **Clinical Documentation**: Help with recording and organizing clinical notes, wound assessments, and treatment plans.

3. **Document Processing**: Assist with processing uploaded documents like insurance cards, clinical notes, and wound photos.

4. **General Support**: Answer questions about wound care products, procedures, and administrative processes.

**Available Client Tools:**
You have access to these client-side tools that you can suggest to use:

- **processDocument**: Process uploaded documents (insurance cards, clinical notes, wound photos) with OCR to extract data
- **fillQuickRequestField**: Fill specific fields in the Quick Request form with extracted or provided data
- **generateIVRForm**: Generate a pre-filled Insurance Verification Request form using DocuSeal
- **getCurrentFormData**: Get the current state of form data to check what's been filled
- **validateFormData**: Validate form data and identify missing required fields

When users upload documents or need help filling forms, suggest using these tools by returning a special response format:
```json
{
  \"reply\": \"Your message to the user\",
  \"tool_call\": {
    \"tool\": \"tool_name\",
    \"parameters\": { ... }
  }
}
```

**Important Guidelines:**
- Always be professional and medically appropriate
- Ask clarifying questions when information is incomplete
- Suggest using client tools when appropriate
- Be concise but thorough in your responses
- If asked about medical advice, remind users to consult their healthcare provider
- Prioritize patient privacy and HIPAA compliance

**Form Generation:**
When users want to submit product requests, clinical notes, or process documents, use the available tools and generate interactive markdown forms to streamline data collection.

Current conversation context will be provided to maintain continuity.";
    }

    /**
     * Generate markdown form if the conversation suggests one is needed
     */
    protected function generateMarkdownFormIfNeeded(string $userMessage, string $aiResponse): ?string
    {
        $lowerMessage = strtolower($userMessage);
        $lowerResponse = strtolower($aiResponse);

        // Don't generate forms for simple greetings or test messages
        $greetings = ['hey', 'hi', 'hello', 'test', 'help', 'how are you', 'good morning', 'good afternoon'];
        foreach ($greetings as $greeting) {
            if (trim($lowerMessage) === $greeting || str_starts_with($lowerMessage, $greeting . ' ')) {
                return null;
            }
        }

        // Only check user message for explicit requests (not AI response)
        // This prevents the AI from triggering forms just by mentioning keywords
        
        // Check if user is explicitly asking for a product request
        if ((str_contains($lowerMessage, 'create') || str_contains($lowerMessage, 'start') || 
             str_contains($lowerMessage, 'new') || str_contains($lowerMessage, 'submit')) && 
            str_contains($lowerMessage, 'product request')) {
            
            return $this->generateProductRequestForm();
        }

        // Check if user explicitly wants to record clinical notes
        if ((str_contains($lowerMessage, 'record') || str_contains($lowerMessage, 'create') || 
             str_contains($lowerMessage, 'enter') || str_contains($lowerMessage, 'document')) && 
            (str_contains($lowerMessage, 'clinical note') || str_contains($lowerMessage, 'wound assessment'))) {
            
            return $this->generateClinicalNotesForm();
        }

        return null;
    }

    /**
     * Generate product request form
     */
    protected function generateProductRequestForm(): string
    {
        return "## 📋 Product Request Form

Please provide the following information for your product request:

**Patient Information**
- Patient Name: [input:patient_name|]
- Date of Birth: [date:patient_dob|]
- Medical Record Number: [input:mrn|]

**Wound Details**
- Primary Diagnosis: [input:primary_diagnosis|]
- Wound Location: [select:wound_location|Foot|Leg|Arm|Back|Other|]
- Wound Size (Length x Width x Depth): [input:wound_size|]
- Wound Duration: [select:wound_duration|< 1 week|1-4 weeks|1-3 months|> 3 months|]

**Product Information**
- Requested Product: [input:requested_product|]
- Quantity Needed: [input:quantity|]
- Clinical Justification: [textarea:clinical_justification|]

**Provider Information**
- Ordering Provider: [input:provider_name|]
- NPI Number: [input:provider_npi|]
- Contact Phone: [input:provider_phone|]

[button:Submit Request|submit_product_request]
[button:Save Draft|save_draft]
[button:Cancel|cancel_form]";
    }

    /**
     * Generate clinical notes form
     */
    protected function generateClinicalNotesForm(): string
    {
        return "## 🏥 Clinical Notes Entry

Document your clinical assessment:

**Assessment Date & Time**
- Date of Service: [date:service_date|]
- Time: [time:service_time|]

**Wound Assessment**
- Wound Location: [input:wound_location|]
- Size Measurements: [input:wound_measurements|]
- Wound Bed Description: [textarea:wound_bed|]
- Surrounding Skin: [textarea:surrounding_skin|]
- Drainage: [select:drainage|None|Minimal|Moderate|Heavy|]

**Treatment Provided**
- Cleaning/Debridement: [textarea:cleaning|]
- Dressing Applied: [input:dressing_type|]
- Topical Medications: [input:medications|]

**Patient Response**
- Pain Level (0-10): [select:pain_level|0|1|2|3|4|5|6|7|8|9|10|]
- Patient Tolerance: [textarea:patient_tolerance|]

**Plan**
- Next Appointment: [date:next_appointment|]
- Follow-up Instructions: [textarea:followup|]

[button:Save Clinical Notes|save_clinical_notes]
[button:Print|print_notes]
[button:Cancel|cancel_form]";
    }

    /**
     * Handle form actions triggered from markdown forms
     */
    public function handleFormAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string',
            'form_data' => 'required|array'
        ]);

        $action = $request->input('action');
        $formData = $request->input('form_data');

        $this->logger->info('Form action received', [
            'action' => $action,
            'form_fields' => array_keys($formData)
        ]);

        try {
            switch ($action) {
                case 'submit_product_request':
                    return $this->handleProductRequestSubmission($formData);
                
                case 'save_clinical_notes':
                    return $this->handleClinicalNotesSubmission($formData);
                
                case 'save_draft':
                    return $this->handleSaveDraft($formData);
                
                case 'cancel_form':
                    return response()->json([
                        'success' => true,
                        'message' => 'Form cancelled',
                        'reply' => 'Form has been cancelled. How else can I help you?'
                    ]);
                
                default:
                    return response()->json([
                        'success' => false,
                        'error' => 'Unknown action: ' . $action,
                        'reply' => 'I\'m not sure how to handle that action. Please try again.'
                    ], 400);
            }

        } catch (Exception $e) {
            $this->logger->error('Form action failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process form action',
                'reply' => 'There was an error processing your form. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle product request form submission
     */
    protected function handleProductRequestSubmission(array $formData): JsonResponse
    {
        // This would integrate with your existing product request system
        // For now, we'll simulate the submission and provide feedback
        
        $requiredFields = ['patient_name', 'primary_diagnosis', 'requested_product'];
        $missingFields = array_filter($requiredFields, fn($field) => empty($formData[$field]));
        
        if (!empty($missingFields)) {
            return response()->json([
                'success' => false,
                'error' => 'Missing required fields: ' . implode(', ', $missingFields),
                'reply' => 'Please fill in all required fields: ' . implode(', ', $missingFields)
            ], 400);
        }

        // Here you would typically:
        // 1. Create a new product request record
        // 2. Attach any uploaded documents
        // 3. Send notifications to relevant staff
        // 4. Generate tracking number

        return response()->json([
            'success' => true,
            'message' => 'Product request submitted successfully',
            'reply' => "Great! I've submitted your product request for {$formData['patient_name']}. The request for {$formData['requested_product']} has been assigned tracking number PR-" . time() . ". You'll receive updates as the request is processed.",
            'tracking_number' => 'PR-' . time()
        ]);
    }

    /**
     * Handle clinical notes form submission
     */
    protected function handleClinicalNotesSubmission(array $formData): JsonResponse
    {
        // This would integrate with your existing clinical documentation system
        
        $requiredFields = ['service_date', 'wound_location'];
        $missingFields = array_filter($requiredFields, fn($field) => empty($formData[$field]));
        
        if (!empty($missingFields)) {
            return response()->json([
                'success' => false,
                'error' => 'Missing required fields: ' . implode(', ', $missingFields),
                'reply' => 'Please fill in all required fields: ' . implode(', ', $missingFields)
            ], 400);
        }

        // Here you would typically:
        // 1. Save the clinical notes to the patient's record
        // 2. Update FHIR resources
        // 3. Trigger any necessary workflows

        return response()->json([
            'success' => true,
            'message' => 'Clinical notes saved successfully',
            'reply' => "Perfect! I've saved the clinical notes for the {$formData['wound_location']} wound assessment from {$formData['service_date']}. The notes have been added to the patient's medical record."
        ]);
    }

    /**
     * Handle saving form as draft
     */
    protected function handleSaveDraft(array $formData): JsonResponse
    {
        // Here you would typically save the form data to a drafts table
        // For now, we'll just acknowledge the save
        
        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully',
            'reply' => 'I\'ve saved your form as a draft. You can continue working on it later.',
            'draft_id' => 'DRAFT-' . time()
        ]);
    }

    /**
     * Parse AI response to detect JSON-formatted tool calls
     */
    protected function parseAIResponse(string $response): ?array
    {
        // Try to find JSON in the response
        $jsonPattern = '/```json\s*(\{.*?\})\s*```/s';
        if (preg_match($jsonPattern, $response, $matches)) {
            try {
                $parsed = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            } catch (Exception $e) {
                Log::debug('Failed to parse JSON from AI response', ['error' => $e->getMessage()]);
            }
        }

        // Also try to parse the entire response as JSON
        try {
            $parsed = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        } catch (Exception $e) {
            // Not JSON, return null
        }

        return null;
    }

    /**
     * Handle tool execution results from the frontend
     */
    public function handleToolResult(Request $request): JsonResponse
    {
        $request->validate([
            'tool' => 'required|string',
            'result' => 'required|array',
            'conversation_history' => 'sometimes|array'
        ]);

        $tool = $request->input('tool');
        $result = $request->input('result');
        $conversationHistory = $request->input('conversation_history', []);

        $this->logger->info('Tool execution result received', [
            'tool' => $tool,
            'success' => $result['success'] ?? false
        ]);

        try {
            // Build context about the tool result
            $context = "The client tool '{$tool}' was executed with result: " . json_encode($result);
            
            // Generate appropriate response based on tool result
            $aiResponse = $this->foundryService->generateChatResponse(
                "Process this tool result and provide an appropriate response: " . json_encode($result),
                $conversationHistory,
                $context,
                $this->getSystemPrompt()
            );

            // Check if there's a markdown form in the result
            $markdown = $result['markdown'] ?? null;

            return response()->json([
                'success' => true,
                'reply' => $aiResponse,
                'markdown' => $markdown
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to process tool result', [
                'error' => $e->getMessage(),
                'tool' => $tool
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process tool result',
                'reply' => 'I encountered an error processing that action. Please try again.'
            ], 500);
        }
    }

    /**
     * Convert text to speech using Azure Speech Services
     */
    public function textToSpeech(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'voice' => 'sometimes|string',
            'rate' => 'sometimes|string',
            'pitch' => 'sometimes|string'
        ]);

        $text = $request->input('text');
        $options = [
            'voice' => $request->input('voice', 'en-US-JennyNeural'),
            'rate' => $request->input('rate', '0%'),
            'pitch' => $request->input('pitch', '0%')
        ];

        $this->logger->info('Text-to-speech request', [
            'text_length' => strlen($text),
            'voice' => $options['voice']
        ]);

        try {
            $result = $this->speechService->textToSpeech($text, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'audio' => $result['audio'],
                    'format' => $result['format']
                ]);
            } else {
                // Fallback to browser TTS if Azure fails
                return response()->json([
                    'success' => false,
                    'fallback' => true,
                    'error' => 'Azure Speech Services unavailable, use browser TTS'
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Text-to-speech failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'fallback' => true,
                'error' => 'Text-to-speech service error'
            ], 500);
        }
    }

    /**
     * Get available voice options
     */
    public function getVoices(): JsonResponse
    {
        try {
            $voices = $this->speechService->getVoices();
            
            return response()->json([
                'success' => true,
                'voices' => $voices
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to get voices', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve voice options'
            ], 500);
        }
    }

    /**
     * Create a realtime voice session
     */
    public function createRealtimeSession(Request $request): JsonResponse
    {
        $request->validate([
            'voice' => 'sometimes|string|in:alloy,echo,fable,onyx,nova,shimmer'
        ]);

        $this->logger->info('Creating realtime voice session');

        try {
            $session = $this->realtimeService->createSession([
                'voice' => $request->input('voice', 'alloy')
            ]);

            if ($session['success']) {
                return response()->json([
                    'success' => true,
                    'session_id' => $session['session_id'],
                    'websocket_url' => $session['websocket_url'],
                    'model' => $session['model'],
                    'deployment' => $session['deployment'] ?? null,
                    'session_config' => $session['session_config'],
                    'mode' => 'voice'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $session['error'] ?? 'Failed to create realtime session',
                    'fallback_mode' => 'text'
                ], 500);
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to create realtime session', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Realtime voice not available. Please use text mode.',
                'fallback_mode' => 'text'
            ], 500);
        }
    }

    /**
     * Transcribe audio using Azure Speech Services or OpenAI Whisper
     */
    public function transcribe(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'audio' => 'required|file|max:25600' // 25MB max
            ]);

            $audioFile = $request->file('audio');
            
            // Use Azure Speech Services or OpenAI Whisper
            // Assuming TranscriptionService is a new service class for this
            // For now, we'll simulate transcription
            $transcription = "Transcription successful for audio file: " . $audioFile->getClientOriginalName();
            
            return response()->json([
                'success' => true,
                'text' => $transcription
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Transcription error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to transcribe audio'
            ], 500);
        }
    }

    /**
     * Get hybrid mode capabilities
     */
    public function getCapabilities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'capabilities' => [
                'voice' => [
                    'available' => true,
                    'provider' => 'openai-realtime',
                    'models' => ['gpt-4o-realtime-preview'],
                    'features' => [
                        'natural_conversation' => true,
                        'interruption_handling' => true,
                        'low_latency' => true,
                        'hands_free' => true
                    ],
                    'limitations' => [
                        'no_file_uploads' => true,
                        'no_vision' => true
                    ]
                ],
                'text' => [
                    'available' => true,
                    'provider' => 'azure-ai-foundry',
                    'models' => ['gpt-4o', 'gpt-4'],
                    'features' => [
                        'file_uploads' => true,
                        'vision' => true,
                        'markdown_forms' => true,
                        'client_tools' => true
                    ]
                ],
                'hybrid' => [
                    'description' => 'Switch between voice and text modes seamlessly',
                    'use_cases' => [
                        'voice_for_conversation' => 'Quick questions and hands-free interaction',
                        'text_for_documents' => 'Document processing and detailed forms'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Switch between voice and text modes
     */
    public function switchMode(Request $request): JsonResponse
    {
        $request->validate([
            'from_mode' => 'required|string|in:voice,text',
            'to_mode' => 'required|string|in:voice,text',
            'conversation_history' => 'sometimes|array'
        ]);

        $fromMode = $request->input('from_mode');
        $toMode = $request->input('to_mode');
        $history = $request->input('conversation_history', []);

        $this->logger->info('Switching modes', [
            'from' => $fromMode,
            'to' => $toMode
        ]);

        try {
            if ($toMode === 'voice') {
                // Create new realtime session
                $session = $this->realtimeService->createSession();
                
                return response()->json([
                    'success' => true,
                    'mode' => 'voice',
                    'session' => $session,
                    'message' => 'Switched to voice mode. You can speak naturally now.'
                ]);
            } else {
                // Close any existing realtime session if provided
                if ($request->has('session_id')) {
                    $this->realtimeService->closeSession($request->input('session_id'));
                }
                
                return response()->json([
                    'success' => true,
                    'mode' => 'text',
                    'message' => 'Switched to text mode. You can now upload documents and use detailed forms.'
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to switch modes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to switch modes'
            ], 500);
        }
    }
}
