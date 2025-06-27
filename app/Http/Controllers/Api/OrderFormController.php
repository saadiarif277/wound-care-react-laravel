<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderFormController extends Controller
{
    /**
     * Process order form completion (optional step)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processCompletion(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|string',
                'episode_id' => 'nullable|string',
                'ivr_submission_id' => 'nullable|string',
                'form_data' => 'array',
                'completion_data' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request data',
                    'validation_errors' => $validator->errors()
                ], 422);
            }

            $submissionId = $request->input('submission_id');
            $episodeId = $request->input('episode_id');
            $ivrSubmissionId = $request->input('ivr_submission_id');
            $formData = $request->input('form_data', []);
            $completionData = $request->input('completion_data', []);

            // Log the order form completion for audit purposes
            Log::info('Order form completed', [
                'submission_id' => $submissionId,
                'episode_id' => $episodeId,
                'ivr_submission_id' => $ivrSubmissionId,
                'has_form_data' => !empty($formData),
                'has_completion_data' => !empty($completionData),
                'user_id' => auth()->id(),
                'timestamp' => now()->toISOString()
            ]);

            // Optional: Store order form completion in database
            // This is where you could save to a custom table if needed
            // For now, we'll just process and return success

            // Optional: Trigger any business logic for order form completion
            // This could include:
            // - Updating episode status
            // - Notifying admin users
            // - Preparing for final review
            // - Integrating with external systems

            // Prepare response data
            $responseData = [
                'submission_id' => $submissionId,
                'episode_id' => $episodeId,
                'ivr_submission_id' => $ivrSubmissionId,
                'processed_at' => now()->toISOString(),
                'status' => 'completed',
                'next_step' => 'final_review'
            ];

            // Optional: Add any processing metadata
            if (!empty($formData['selected_products'])) {
                $product = $formData['selected_products'][0] ?? null;
                if ($product) {
                    $responseData['product_info'] = [
                        'product_id' => $product['product_id'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'size' => $product['size'] ?? null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Order form completion processed successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            // Log the error but don't fail the workflow
            Log::error('Order form completion processing failed (non-critical)', [
                'error' => $e->getMessage(),
                'submission_id' => $request->input('submission_id'),
                'episode_id' => $request->input('episode_id'),
                'trace' => $e->getTraceAsString()
            ]);

            // Return success even if processing fails to not block the workflow
            return response()->json([
                'success' => true,
                'message' => 'Order form completed successfully',
                'warning' => 'Optional processing encountered an issue but workflow continues',
                'data' => [
                    'submission_id' => $request->input('submission_id'),
                    'status' => 'completed_with_warnings',
                    'processed_at' => now()->toISOString()
                ]
            ]);
        }
    }

    /**
     * Get order form status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $submissionId = $request->input('submission_id');
            $episodeId = $request->input('episode_id');

            if (!$submissionId && !$episodeId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Either submission_id or episode_id is required'
                ], 422);
            }

            // This would normally query your database for order form status
            // For now, return a basic response
            return response()->json([
                'success' => true,
                'data' => [
                    'submission_id' => $submissionId,
                    'episode_id' => $episodeId,
                    'status' => 'unknown',
                    'message' => 'Status tracking not yet implemented'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get order form status', [
                'error' => $e->getMessage(),
                'submission_id' => $request->input('submission_id'),
                'episode_id' => $request->input('episode_id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve status'
            ], 500);
        }
    }
}
