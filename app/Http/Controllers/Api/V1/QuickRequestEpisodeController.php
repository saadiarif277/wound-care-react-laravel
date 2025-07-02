<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Services\QuickRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QuickRequestEpisodeController extends Controller
{
    private QuickRequestService $quickRequestService;

    public function __construct(QuickRequestService $quickRequestService)
    {
        $this->quickRequestService = $quickRequestService;
    }

    /**
     * Create new episode + initial order
     * POST /api/v1/quick-request/episodes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Patient data
            'patient' => 'required|array',
            'patient.first_name' => 'required|string|max:100',
            'patient.last_name' => 'required|string|max:100',
            'patient.date_of_birth' => 'required|date',
            'patient.gender' => 'required|in:male,female,other',
            'patient.phone' => 'required|string',
            'patient.email' => 'nullable|email',
            'patient.address_line1' => 'required|string',
            'patient.address_line2' => 'nullable|string',
            'patient.city' => 'required|string',
            'patient.state' => 'required|string|size:2',
            'patient.zip' => 'required|string',
            'patient.member_id' => 'required|string',

            // Provider data
            'provider' => 'required|array',
            'provider.name' => 'required|string',
            'provider.npi' => 'required|string|size:10',
            'provider.email' => 'required|email',
            'provider.credentials' => 'nullable|string',

            // Facility data
            'facility' => 'required|array',
            'facility.name' => 'required|string',
            'facility.address' => 'required|string',
            'facility.city' => 'required|string',
            'facility.state' => 'required|string|size:2',
            'facility.zip' => 'required|string',
            'facility.phone' => 'nullable|string',
            'facility.npi' => 'nullable|string',

            // Clinical data
            'clinical' => 'required|array',
            'clinical.diagnosis_code' => 'required|string',
            'clinical.diagnosis_description' => 'nullable|string',
            'clinical.wound_type' => 'required|string|wound_type',
            'clinical.wound_location' => 'required|string',
            'clinical.wound_length' => 'required|numeric|min:0',
            'clinical.wound_width' => 'required|numeric|min:0',
            'clinical.wound_depth' => 'required|numeric|min:0',
            'clinical.onset_date' => 'nullable|date',
            'clinical.clinical_notes' => 'nullable|string',

            // Insurance data
            'insurance' => 'required|array',
            'insurance.payer_name' => 'required|string',
            'insurance.member_id' => 'required|string',
            'insurance.type' => 'nullable|string',
            'insurance.group_number' => 'nullable|string',

            // Product data
            'product' => 'required|array',
            'product.id' => 'required|exists:msc_products,id',
            'product.code' => 'required|string',
            'product.name' => 'required|string',
            'product.quantity' => 'required|integer|min:1',
            'product.size' => 'nullable|string',

            // Other required fields
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'order_details' => 'required|array',
        ]);

        try {
            $episode = $this->quickRequestService->startEpisode($validated);

            return response()->json([
                'success' => true,
                'message' => 'Episode created successfully',
                'data' => [
                    'episode_id' => $episode->id,
                    'status' => $episode->status,
                    'orders' => $episode->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'type' => $order->type,
                            'details' => $order->details
                        ];
                    })
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create episode', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve episode and orders
     * GET /api/v1/quick-request/episodes/{episode}
     */
    public function show(Episode $episode): JsonResponse
    {
        try {
            $episode->load(['orders', 'manufacturer', 'docusealSubmission']);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => [
                        'id' => $episode->id,
                        'patient_display_id' => $episode->patient_display_id,
                        'status' => $episode->status,
                        'ivr_status' => $episode->ivr_status,
                        'manufacturer' => [
                            'id' => $episode->manufacturer->id,
                            'name' => $episode->manufacturer->name
                        ],
                        'created_at' => $episode->created_at,
                        'updated_at' => $episode->updated_at,
                        'metadata' => $episode->metadata
                    ],
                    'orders' => $episode->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'type' => $order->type,
                            'status' => $order->status,
                            'details' => $order->details,
                            'created_at' => $order->created_at
                        ];
                    }),
                    'docuseal' => $episode->docuseal
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Internal approval workflow (Task)
     * POST /api/v1/quick-request/episodes/{episode}/approve
     */
    public function approve(Episode $episode): JsonResponse
    {
        try {
            // Check if episode can be approved
            if (!in_array($episode->status, ['draft', 'pending_review'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode cannot be approved in current status: ' . $episode->status
                ], 400);
            }

            $this->quickRequestService->approve($episode);

            return response()->json([
                'success' => true,
                'message' => 'Episode approved and sent to manufacturer',
                'data' => [
                    'episode_id' => $episode->id,
                    'status' => $episode->fresh()->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to approve episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve episode',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
