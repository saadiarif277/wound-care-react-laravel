<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\EnhancedDocuSealIVRService;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced DocuSeal Controller for FHIR-integrated submissions
 */
class EnhancedDocuSealController extends Controller
{
    public function __construct(
        protected EnhancedDocuSealIVRService $enhancedDocuSealService
    ) {}

    /**
     * Create enhanced DocuSeal submission with FHIR integration
     */
    public function createSubmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_request_id' => 'required|uuid|exists:product_requests,id',
            'episode_id' => 'nullable|integer|exists:episodes,id',
            'form_data' => 'required|array',
        ]);

        try {
            $productRequest = ProductRequest::findOrFail($validated['product_request_id']);
            $episode = $validated['episode_id'] 
                ? Episode::find($validated['episode_id']) 
                : null;

            $result = $this->enhancedDocuSealService->createEnhancedIVRSubmission(
                $productRequest,
                $validated['form_data'],
                $episode
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Enhanced DocuSeal submission creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'product_request_id' => $validated['product_request_id'],
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize DocuSeal submission after completion
     */
    public function finalizeSubmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => 'required|string',
            'episode_id' => 'nullable|string',
            'form_data' => 'required|array',
            'completion_data' => 'nullable|array',
        ]);

        try {
            Log::info('🔄 Finalizing Enhanced DocuSeal submission', [
                'submission_id' => $validated['submission_id'],
                'episode_id' => $validated['episode_id'],
                'user_id' => Auth::id(),
            ]);

            DB::beginTransaction();

            // Find the episode or product request
            $episode = null;
            $productRequest = null;

            if ($validated['episode_id']) {
                $episode = Episode::find($validated['episode_id']);
                if ($episode) {
                    // Try to find associated product request
                    $productRequest = ProductRequest::where('patient_fhir_id', $episode->patient_fhir_id)->first();
                }
            }

            // If no episode, try to find by submission ID
            if (!$productRequest) {
                $productRequest = ProductRequest::where('docuseal_submission_id', $validated['submission_id'])->first();
            }

            if (!$productRequest) {
                throw new \Exception('Could not find associated order for this submission');
            }

            // Update product request status
            $productRequest->update([
                'docuseal_submission_id' => $validated['submission_id'],
                'ivr_completed_at' => now(),
                'order_status' => 'ivr_completed',
                'metadata' => array_merge($productRequest->metadata ?? [], [
                    'completion_data' => $validated['completion_data'],
                    'enhanced_submission' => true,
                    'finalized_at' => now()->toISOString(),
                ])
            ]);

            // Update episode if available
            if ($episode) {
                $episode->update([
                    'status' => 'ready_for_review',
                    'metadata' => array_merge($episode->metadata ?? [], [
                        'docuseal_submission_id' => $validated['submission_id'],
                        'ivr_completed_at' => now()->toISOString(),
                    ])
                ]);
            }

            // Create order summary data
            $orderSummary = $this->buildOrderSummary($productRequest, $episode);

            DB::commit();

            Log::info('✅ Enhanced DocuSeal submission finalized successfully', [
                'submission_id' => $validated['submission_id'],
                'order_id' => $productRequest->id,
                'episode_id' => $episode?->id,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $productRequest->id,
                'episode_id' => $episode?->id,
                'submission_id' => $validated['submission_id'],
                'order_status' => $productRequest->order_status,
                'template_name' => 'Enhanced IVR Form',
                'fhir_data_used' => $episode ? 15 : 5, // Estimate based on availability
                'order_summary' => $orderSummary,
                'redirect_url' => route('quick-request.order-summary', $productRequest->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Enhanced DocuSeal submission finalization failed', [
                'error' => $e->getMessage(),
                'submission_id' => $validated['submission_id'],
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submission status
     */
    public function getSubmissionStatus(string $submissionId): JsonResponse
    {
        try {
            // Find product request by submission ID
            $productRequest = ProductRequest::where('docuseal_submission_id', $submissionId)->first();

            if (!$productRequest) {
                return response()->json([
                    'success' => false,
                    'error' => 'Submission not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'submission_id' => $submissionId,
                'order_id' => $productRequest->id,
                'status' => $productRequest->order_status,
                'completed_at' => $productRequest->ivr_completed_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting submission status', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle DocuSeal webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            
            Log::info('Enhanced DocuSeal webhook received', [
                'payload' => $payload,
                'headers' => $request->headers->all(),
            ]);

            // Process webhook data
            if (isset($payload['submission_id'])) {
                $submissionId = $payload['submission_id'];
                $status = $payload['status'] ?? 'unknown';

                // Update any relevant records
                $productRequest = ProductRequest::where('docuseal_submission_id', $submissionId)->first();
                if ($productRequest && $status === 'completed') {
                    $productRequest->update([
                        'order_status' => 'ivr_completed',
                        'ivr_completed_at' => now(),
                    ]);
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Enhanced DocuSeal webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Build comprehensive order summary
     */
    protected function buildOrderSummary(ProductRequest $productRequest, ?Episode $episode): array
    {
        return [
            'order_id' => $productRequest->id,
            'request_number' => $productRequest->request_number,
            'status' => $productRequest->order_status,
            'patient' => [
                'name' => $productRequest->getValue('patient_name'),
                'fhir_id' => $productRequest->patient_fhir_id,
                'display_id' => $productRequest->getValue('patient_display_id'),
            ],
            'provider' => [
                'name' => $productRequest->getValue('provider_name'),
                'npi' => $productRequest->getValue('provider_npi'),
            ],
            'facility' => [
                'name' => $productRequest->getValue('facility_name'),
            ],
            'product' => [
                'name' => $productRequest->product_name,
                'code' => $productRequest->product_code,
                'manufacturer' => $productRequest->manufacturer,
                'size' => $productRequest->size,
                'quantity' => $productRequest->quantity,
            ],
            'insurance' => [
                'payer_name' => $productRequest->payer_name,
                'member_id' => $productRequest->payer_id,
                'plan_type' => $productRequest->insurance_type,
            ],
            'clinical' => [
                'wound_type' => $productRequest->wound_type,
                'place_of_service' => $productRequest->place_of_service,
                'expected_service_date' => $productRequest->expected_service_date?->format('Y-m-d'),
            ],
            'submission' => [
                'docuseal_submission_id' => $productRequest->docuseal_submission_id,
                'completed_at' => $productRequest->ivr_completed_at?->format('Y-m-d H:i:s'),
            ],
            'episode' => $episode ? [
                'id' => $episode->id,
                'status' => $episode->status,
                'episode_of_care_fhir_id' => $episode->episode_of_care_fhir_id,
            ] : null,
        ];
    }
}
