<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Services\QuickRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QuickRequestOrderController extends Controller
{
    private QuickRequestService $quickRequestService;

    public function __construct(QuickRequestService $quickRequestService)
    {
        $this->quickRequestService = $quickRequestService;
    }

    /**
     * List orders for an episode
     * GET /api/v1/quick-request/episodes/{episode}/orders
     */
    public function index(Episode $episode): JsonResponse
    {
        try {
            $orders = $episode->orders()
                ->with(['product', 'parentOrder'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'total_orders' => $orders->count(),
                    'orders' => $orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'type' => $order->type,
                            'status' => $order->status,
                            'parent_order_id' => $order->parent_order_id,
                            'details' => $order->details,
                            'total_amount' => $order->total_amount,
                            'created_at' => $order->created_at,
                            'product' => $order->product ? [
                                'id' => $order->product->id,
                                'name' => $order->product->name,
                                'code' => $order->product->q_code
                            ] : null
                        ];
                    })
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to list orders', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to list orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add initial or follow-up order
     * POST /api/v1/quick-request/episodes/{episode}/orders
     */
    public function store(Request $request, Episode $episode): JsonResponse
    {
        $validated = $request->validate([
            'parent_order_id' => 'nullable|uuid|exists:orders,id',
            'order_details' => 'required|array',
            'order_details.product_id' => 'required|exists:msc_products,id',
            'order_details.quantity' => 'required|integer|min:1',
            'order_details.size' => 'nullable|string',
            'order_details.notes' => 'nullable|string',
            'order_details.urgency' => 'nullable|in:routine,urgent,stat',
            'order_details.expected_delivery_date' => 'nullable|date',
        ]);

        try {
            // Determine if this is initial or follow-up order
            $isFollowUp = !empty($validated['parent_order_id']);
            
            if ($isFollowUp) {
                // Verify parent order belongs to this episode
                $parentOrder = Order::where('id', $validated['parent_order_id'])
                    ->where('episode_id', $episode->id)
                    ->first();
                    
                if (!$parentOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent order not found in this episode'
                    ], 400);
                }
                
                $order = $this->quickRequestService->addFollowUp($episode, $validated);
            } else {
                // Create initial order (if episode doesn't have one already)
                $existingInitialOrder = $episode->orders()
                    ->where('type', 'initial')
                    ->exists();
                    
                if ($existingInitialOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Episode already has an initial order. Use parent_order_id for follow-up orders.'
                    ], 400);
                }
                
                // Create the order directly since episode already exists
                $order = Order::create([
                    'episode_id' => $episode->id,
                    'type' => 'initial',
                    'status' => 'pending',
                    'product_id' => $validated['order_details']['product_id'],
                    'quantity' => $validated['order_details']['quantity'],
                    'details' => $validated['order_details'],
                    'created_by' => auth()->id()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => ($isFollowUp ? 'Follow-up' : 'Initial') . ' order created successfully',
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'type' => $order->type,
                        'status' => $order->status,
                        'parent_order_id' => $order->parent_order_id,
                        'details' => $order->details,
                        'created_at' => $order->created_at
                    ],
                    'episode_id' => $episode->id
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to create order', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
                'data' => $validated
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order details
     * GET /api/v1/quick-request/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $order->load(['episode', 'product', 'parentOrder', 'childOrders']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'type' => $order->type,
                        'status' => $order->status,
                        'parent_order_id' => $order->parent_order_id,
                        'details' => $order->details,
                        'total_amount' => $order->total_amount,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at
                    ],
                    'episode' => [
                        'id' => $order->episode->id,
                        'status' => $order->episode->status,
                        'patient_display_id' => $order->episode->patient_display_id
                    ],
                    'product' => $order->product ? [
                        'id' => $order->product->id,
                        'name' => $order->product->name,
                        'code' => $order->product->q_code,
                        'manufacturer' => $order->product->manufacturer
                    ] : null,
                    'follow_up_orders' => $order->childOrders->map(function ($childOrder) {
                        return [
                            'id' => $childOrder->id,
                            'status' => $childOrder->status,
                            'created_at' => $childOrder->created_at
                        ];
                    })
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     * PATCH /api/v1/quick-request/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'notes' => 'nullable|string',
            'tracking_number' => 'nullable|string',
            'carrier' => 'nullable|string'
        ]);

        try {
            $order->update([
                'status' => $validated['status'],
                'details' => array_merge($order->details ?? [], [
                    'status_notes' => $validated['notes'] ?? null,
                    'tracking_number' => $validated['tracking_number'] ?? null,
                    'carrier' => $validated['carrier'] ?? null,
                    'status_updated_at' => now()->toIso8601String(),
                    'status_updated_by' => auth()->id()
                ])
            ]);
            
            // Update episode status if all orders are completed
            if ($validated['status'] === 'delivered') {
                $allOrdersDelivered = !$order->episode->orders()
                    ->where('status', '!=', 'delivered')
                    ->where('status', '!=', 'cancelled')
                    ->exists();
                    
                if ($allOrdersDelivered) {
                    $order->episode->markAsCompleted();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'episode_status' => $order->episode->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}