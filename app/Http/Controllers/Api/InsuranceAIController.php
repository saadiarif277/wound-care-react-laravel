<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\InsuranceAIAssistantService;
use App\Logging\PhiSafeLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

class InsuranceAIController extends Controller
{
    public function __construct(
        protected InsuranceAIAssistantService $assistantService,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Start a new insurance AI conversation
     */
    public function startConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'template_id' => 'nullable|string',
                'manufacturer' => 'nullable|string',
                'user_id' => 'nullable|integer',
                'context' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $context = array_merge($request->input('context', []), [
                'template_id' => $request->input('template_id'),
                'manufacturer' => $request->input('manufacturer'),
                'user_id' => $request->input('user_id', Auth::id())
            ]);

            $result = $this->assistantService->startConversation($context);

            return response()->json($result);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI conversation start failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start conversation'
            ], 500);
        }
    }

    /**
     * Send a message to the insurance AI assistant
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'thread_id' => 'required|string',
                'message' => 'required|string|max:2000',
                'context' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $context = array_merge($request->input('context', []), [
                'user_id' => Auth::id(),
                'thread_id' => $request->input('thread_id')
            ]);

            $result = $this->assistantService->sendMessage(
                $request->input('message'),
                $context
            );

            return response()->json($result);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI message failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'thread_id' => $request->input('thread_id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Get form assistance from the insurance AI
     */
    public function getFormAssistance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'template_id' => 'required|string',
                'manufacturer' => 'required|string',
                'thread_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->assistantService->getFormAssistance(
                $request->input('template_id'),
                $request->input('manufacturer')
            );

            return response()->json($result);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI form assistance failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'template_id' => $request->input('template_id'),
                'manufacturer' => $request->input('manufacturer')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get form assistance'
            ], 500);
        }
    }

    /**
     * Get personalized recommendations from the insurance AI
     */
    public function getPersonalizedRecommendations(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'thread_id' => 'required|string',
                'context' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $context = array_merge($request->input('context', []), [
                'user_id' => $request->input('user_id'),
                'thread_id' => $request->input('thread_id')
            ]);

            $result = $this->assistantService->getPersonalizedRecommendations(
                $request->input('user_id'),
                $context
            );

            return response()->json($result);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI recommendations failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id'),
                'thread_id' => $request->input('thread_id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get recommendations'
            ], 500);
        }
    }

    /**
     * Enable voice mode for the insurance AI
     */
    public function enableVoiceMode(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'thread_id' => 'required|string',
                'voice_options' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->assistantService->enableVoiceMode(
                $request->input('voice_options', [])
            );

            return response()->json($result);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI voice mode failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'thread_id' => $request->input('thread_id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to enable voice mode'
            ], 500);
        }
    }

    /**
     * Get insurance AI assistant status
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'features' => [
                    'text_chat' => true,
                    'voice_input' => true,
                    'form_assistance' => true,
                    'personalized_recommendations' => true,
                    'ml_enhanced' => true,
                    'insurance_training_data' => true
                ],
                'integrations' => [
                    'azure_ai_agent' => true,
                    'ml_ensemble' => true,
                    'manufacturer_configs' => true,
                    'template_discovery' => true,
                    'behavioral_tracking' => true
                ]
            ]);

        } catch (Exception $e) {
            $this->logger->error('Insurance AI status check failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Status check failed'
            ], 500);
        }
    }
} 