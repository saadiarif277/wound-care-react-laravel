<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EligibilityEngine\EligibilityService;
use App\Models\Order;
use App\Models\PreAuthTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EligibilityController extends Controller
{
    private EligibilityService $eligibilityService;

    public function __construct(EligibilityService $eligibilityService)
    {
        $this->eligibilityService = $eligibilityService;
    }

    /**
     * Trigger eligibility check for an order
     *
     * POST /api/v1/orders/{order_id}/eligibility-check
     */
    public function checkEligibility(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);

            Log::info('Eligibility check requested', [
                'order_id' => $orderId,
                'user_id' => $request->user()?->id
            ]);

            $result = $this->eligibilityService->runEligibility($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'eligibility_status' => $order->fresh()->eligibility_status,
                    'eligibility_checked_at' => $order->fresh()->eligibility_checked_at,
                    'result' => $result,
                    'pre_auth_required' => $result['pre_auth_required'] ?? false
                ]
            ]);

        } catch (ValidationException $e) {
            Log::warning('Eligibility check validation failed', [
                'order_id' => $orderId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Eligibility check failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eligibility check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get eligibility status and results for an order
     *
     * GET /api/v1/orders/{order_id}/eligibility
     */
    public function getEligibility(int $orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'eligibility_status' => $order->eligibility_status,
                    'eligibility_checked_at' => $order->eligibility_checked_at,
                    'eligibility_result' => $order->eligibility_result,
                    'pre_auth_status' => $order->pre_auth_status,
                    'pre_auth_requested_at' => $order->pre_auth_requested_at,
                    'pre_auth_result' => $order->pre_auth_result
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve eligibility data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check general eligibility (not tied to a specific order)
     *
     * POST /api/v1/eligibility/check
     */
    public function checkGeneralEligibility(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'patient_data' => 'required|array',
                'patient_data.member_id' => 'required|string',
                'patient_data.first_name' => 'required|string',
                'patient_data.last_name' => 'required|string',
                'patient_data.dob' => 'required|date',
                'payer_name' => 'required|string',
                'service_date' => 'required|date',
                'procedure_codes' => 'required|array',
                'procedure_codes.*' => 'string'
            ]);

            $patientData = $request->input('patient_data');
            $payerName = $request->input('payer_name');
            $serviceDate = $request->input('service_date');
            $procedureCodes = $request->input('procedure_codes');

            Log::info('General eligibility check requested', [
                'member_id' => $patientData['member_id'],
                'payer_name' => $payerName,
                'service_date' => $serviceDate,
                'user_id' => $request->user()?->id
            ]);

            // Perform eligibility check
            $result = $this->eligibilityService->checkGeneralEligibility(
                $patientData,
                $payerName,
                $serviceDate,
                $procedureCodes
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (ValidationException $e) {
            Log::warning('General eligibility check validation failed', [
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('General eligibility check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eligibility check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request pre-authorization for an order
     *
     * POST /api/v1/orders/{order_id}/preauth
     */
    public function requestPreAuth(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);

            // Check if eligibility was checked first
            if ($order->eligibility_status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Eligibility must be checked before requesting pre-authorization'
                ], 400);
            }

            Log::info('Pre-auth requested', [
                'order_id' => $orderId,
                'user_id' => $request->user()?->id
            ]);

            $result = $this->eligibilityService->runPreAuth($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'pre_auth_status' => $order->fresh()->pre_auth_status,
                    'pre_auth_requested_at' => $order->fresh()->pre_auth_requested_at,
                    'result' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Pre-auth request failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pre-authorization request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pre-auth tasks for an order
     *
     * GET /api/v1/orders/{order_id}/preauth/tasks
     */
    public function getPreAuthTasks(int $orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);
            $tasks = PreAuthTask::where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'pre_auth_status' => $order->pre_auth_status,
                    'tasks' => $tasks->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'external_task_id' => $task->external_task_id,
                            'status' => $task->status,
                            'task_name' => $task->task_name,
                            'details' => $task->details,
                            'created_at' => $task->created_at,
                            'updated_at' => $task->updated_at
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pre-auth tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle callback from coverage discovery completion
     *
     * POST /api/v1/eligibility/preauth/callback
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'task_id' => 'required|string',
                'status' => 'required|string',
                'data' => 'sometimes|array'
            ]);

            $taskId = $request->input('task_id');
            $callbackData = [
                'status' => $request->input('status'),
                'callback_received_at' => now()->toISOString(),
                'data' => $request->input('data', [])
            ];

            Log::info('Pre-auth callback received', [
                'task_id' => $taskId,
                'status' => $callbackData['status']
            ]);

            $this->eligibilityService->handleCoverageDiscoveryCallback($taskId, $callbackData);

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Pre-auth callback processing failed', [
                'task_id' => $request->input('task_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get eligibility status summary for multiple orders
     *
     * GET /api/v1/eligibility/summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'sometimes|array',
                'order_ids.*' => 'integer',
                'facility_id' => 'sometimes|integer',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date'
            ]);

            $query = Order::query();

            if ($request->has('order_ids')) {
                $query->whereIn('id', $request->input('order_ids'));
            }

            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->input('facility_id'));
            }

            if ($request->has('date_from')) {
                $query->where('date_of_service', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->where('date_of_service', '<=', $request->input('date_to'));
            }

            $orders = $query->select([
                'id', 'order_number', 'eligibility_status', 'eligibility_checked_at',
                'pre_auth_status', 'pre_auth_requested_at', 'date_of_service'
            ])->get();

            // Count by status
            $statusCounts = [
                'eligibility' => $orders->countBy('eligibility_status'),
                'pre_auth' => $orders->countBy('pre_auth_status')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'summary' => [
                        'total_orders' => $orders->count(),
                        'status_counts' => $statusCounts
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve eligibility summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Optum API health status
     *
     * GET /api/v1/eligibility/health-check
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $result = $this->eligibilityService->healthCheck();

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Health check endpoint failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
