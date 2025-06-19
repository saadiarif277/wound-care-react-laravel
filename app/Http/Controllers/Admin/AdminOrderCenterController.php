<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order\ProductRequest;
use App\Models\PatientIVRStatus;
use App\Services\IvrDocusealService;
use App\Services\FhirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Exception;

class AdminOrderCenterController extends Controller
{
    private IvrDocusealService $ivrService;
    private FhirService $fhirService;

    public function __construct(IvrDocusealService $ivrService, FhirService $fhirService)
    {
        $this->ivrService = $ivrService;
        $this->fhirService = $fhirService;
    }

    /**
     * Display the admin order center dashboard
     */
    public function index(Request $request)
    {
        $query = ProductRequest::with(['provider', 'facility', 'products'])
            ->whereNotIn('order_status', ['draft']); // Show all except drafts

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('patient_display_id', 'like', "%{$search}%")
                  ->orWhereHas('provider', function($providerQuery) use ($search) {
                      $providerQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->input('status')) {
            $query->where('order_status', $request->input('status'));
        }

        if ($request->has('action_required') && $request->input('action_required') === 'true') {
            $query->where('order_status', 'pending_ivr');
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get status counts
        $statusCounts = ProductRequest::whereNotIn('order_status', ['draft'])
            ->select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        // Ensure all statuses are present
        $allStatuses = ['pending_ivr', 'ivr_sent', 'ivr_confirmed', 'approved', 'sent_back', 'denied', 'submitted_to_manufacturer', 'shipped', 'delivered'];
        foreach ($allStatuses as $status) {
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
        }

        // Transform orders for display to match frontend expectations
        $transformedOrders = $orders->through(function ($order) {
            $provider = $order->provider;

            // Get patient name from FHIR (cached for performance)
            $patientName = $this->getPatientName($order->patient_fhir_id);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number ?? $order->request_number,
                'patient_display_id' => $order->patient_display_id,
                'patient_fhir_id' => $order->patient_fhir_id,
                'patient_name' => $patientName, // Ashley's #1 request
                'order_status' => $order->order_status,
                'provider' => [
                    'id' => $provider->id ?? null,
                    'name' => $provider ? ($provider->first_name . ' ' . $provider->last_name) : 'Unknown',
                    'email' => $provider->email ?? null,
                    'npi_number' => $provider->npi_number ?? null,
                ],
                'facility' => [
                    'id' => $order->facility->id ?? null,
                    'name' => $order->facility->name ?? 'Unknown Facility',
                    'city' => $order->facility->city ?? 'Unknown',
                    'state' => $order->facility->state ?? 'Unknown',
                ],
                'manufacturer' => [
                    'id' => 1, // Placeholder
                    'name' => $order->getManufacturer() ?? 'Unknown Manufacturer',
                    'contact_email' => null,
                ],
                'expected_service_date' => $order->expected_service_date?->format('Y-m-d') ?? null,
                'submitted_at' => $order->submitted_at?->toISOString() ?? $order->created_at->toISOString(),
                'total_order_value' => $order->total_order_value ?? 0,
                'products_count' => $order->products->count(),
                'action_required' => $order->requiresAdminAction(),
                'ivr_generation_status' => $order->ivr_sent_at ? 'completed' : 'pending',
                'docuseal_generation_status' => $order->docuseal_submission_id ? 'completed' : null,
            ];
        });

        return Inertia::render('Admin/OrderCenter/Index', [
            'orders' => $transformedOrders,
            'filters' => $request->only(['search', 'status', 'action_required', 'manufacturer', 'date_range']),
            'statusCounts' => $statusCounts,
            'manufacturers' => [], // TODO: Add manufacturers list
        ]);
    }

    /**
     * Show order details
     */
    public function show($id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        // Add debugging
        Log::info('AdminOrderCenterController@show called', [
            'product_request_id' => $productRequest->id,
            'request_number' => $productRequest->request_number,
            'order_status' => $productRequest->order_status
        ]);

        $productRequest->load(['provider', 'facility', 'products', 'preAuthorizations']);

        // Get action history (audit logs)
        $actionHistory = $this->getActionHistory($productRequest);

        // Get DocuSeal submissions
        $submissions = $productRequest->docusealSubmissions ?? [];

        // Get patient name from FHIR
        $patientName = $this->getPatientName($productRequest->patient_fhir_id);

        return Inertia::render('Admin/OrderCenter/Show', [
            'order' => [
                'id' => (string) $productRequest->id,
                'order_number' => $productRequest->order_number ?? $productRequest->request_number,
                'patient_display_id' => $productRequest->patient_display_id,
                'patient_fhir_id' => $productRequest->patient_fhir_id,
                'patient_name' => $patientName,
                'order_status' => $productRequest->order_status,
                'expected_service_date' => $productRequest->expected_service_date?->format('Y-m-d'),
                'submitted_at' => $productRequest->submitted_at?->toISOString(),
                'total_order_value' => $productRequest->total_order_value,

                // Provider info
                'provider' => [
                    'id' => $productRequest->provider->id,
                    'name' => $productRequest->provider->full_name,
                    'email' => $productRequest->provider->email,
                    'npi_number' => $productRequest->provider->npi_number,
                    'phone' => $productRequest->provider->phone,
                ],

                // Facility info
                'facility' => [
                    'id' => $productRequest->facility->id ?? null,
                    'name' => $productRequest->facility->name ?? 'Unknown Facility',
                    'address' => $productRequest->facility->address ?? null,
                    'city' => $productRequest->facility->city ?? 'Unknown',
                    'state' => $productRequest->facility->state ?? 'Unknown',
                    'zip' => $productRequest->facility->zip_code ?? null,
                    'phone' => $productRequest->facility->phone ?? null,
                ],

                // Patient info (de-identified)
                'patient_info' => [
                    'dob' => null, // We don't store DOB
                    'insurance_name' => $productRequest->payer_name_submitted,
                    'insurance_id' => $productRequest->payer_id,
                    'diagnosis_codes' => [], // Would come from clinical summary
                ],

                // Order details
                'order_details' => [
                    'products' => $productRequest->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'quantity' => $product->pivot->quantity,
                            'size' => $product->pivot->size,
                            'unit_price' => $product->pivot->unit_price,
                            'total_price' => $product->pivot->total_price,
                        ];
                    })->toArray(),
                    'wound_type' => $productRequest->wound_type,
                    'wound_location' => $productRequest->clinical_summary['woundDetails']['location'] ?? null,
                    'wound_size' => $productRequest->clinical_summary['woundDetails']['size'] ?? null,
                    'wound_duration' => $productRequest->clinical_summary['woundDetails']['duration'] ?? null,
                ],

                // IVR status
                'ivr' => [
                    'required' => $productRequest->ivr_required,
                    'bypass_reason' => $productRequest->ivr_bypass_reason,
                    'sent_at' => $productRequest->ivr_sent_at,
                    'signed_at' => $productRequest->ivr_signed_at,
                    'document_url' => $productRequest->ivr_document_url,
                ],

                // Manufacturer info
                'manufacturer' => [
                    'id' => 1, // Placeholder since we don't have manufacturer relationship yet
                    'name' => $productRequest->getManufacturer() ?? 'Unknown Manufacturer',
                    'contact_email' => null,
                    'contact_phone' => null,
                    'ivr_template_id' => null,
                ],

                // Manufacturer approval
                'manufacturer_approval' => [
                    'sent_at' => $productRequest->manufacturer_sent_at,
                    'approved' => $productRequest->manufacturer_approved,
                    'approved_at' => $productRequest->manufacturer_approved_at,
                    'reference' => $productRequest->manufacturer_approval_reference,
                    'notes' => $productRequest->manufacturer_notes,
                ],

                // Validations
                'validations' => [
                    'mac_status' => $productRequest->mac_validation_status,
                    'eligibility_status' => $productRequest->eligibility_status,
                    'pre_auth_required' => $productRequest->pre_auth_required_determination,
                ],

                // Documents
                'documents' => [], // TODO: Implement document storage

                // Action history
                'action_history' => $actionHistory,

                // Additional status fields
                'ivr_generation_status' => $productRequest->ivr_sent_at ? 'completed' : 'pending',
                'ivr_skip_reason' => $productRequest->ivr_bypass_reason,
                'docuseal_generation_status' => $productRequest->docuseal_submission_id ? 'completed' : null,
                'docuseal_submission_id' => $productRequest->docuseal_submission_id,
            ],

            // Action permissions (updated for provider-centered workflow)
            'can_review_episode' => $productRequest->order_status === 'ready_for_review',
            'can_approve' => $productRequest->order_status === 'ivr_verified',
            'can_submit_to_manufacturer' => $productRequest->order_status === 'approved',
        ]);
    }

    /**
     * Review episode (provider has already generated IVR)
     */
    public function reviewEpisode(Request $request, $episodeId)
    {
        $episode = PatientIVRStatus::findOrFail($episodeId);

        try {
            $episode->update([
                'status' => 'ivr_verified',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            return back()->with('success', 'Episode reviewed and approved successfully.');
        } catch (Exception $e) {
            Log::error('Failed to review episode', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to review episode: ' . $e->getMessage());
        }
    }

    /**
     * Send episode to manufacturer
     */
    public function sendEpisodeToManufacturer(Request $request, $episodeId)
    {
        $episode = PatientIVRStatus::findOrFail($episodeId);

        try {
            $episode->update([
                'status' => 'sent_to_manufacturer',
                'sent_to_manufacturer_at' => now(),
                'sent_by' => Auth::id(),
            ]);

            return back()->with('success', 'Episode sent to manufacturer successfully.');
        } catch (Exception $e) {
            Log::error('Failed to send episode to manufacturer', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to send episode to manufacturer: ' . $e->getMessage());
        }
    }

    /**
     * Update episode tracking
     */
    public function updateEpisodeTracking(Request $request, $episodeId)
    {
        $episode = PatientIVRStatus::findOrFail($episodeId);

        try {
            $request->validate([
                'tracking_number' => 'required|string',
                'carrier' => 'required|string',
                'estimated_delivery' => 'nullable|date',
            ]);

            $episode->update([
                'status' => 'tracking_added',
                'tracking_number' => $request->input('tracking_number'),
                'carrier' => $request->input('carrier'),
                'estimated_delivery' => $request->input('estimated_delivery'),
                'tracking_updated_at' => now(),
                'tracking_updated_by' => Auth::id(),
            ]);

            return back()->with('success', 'Episode tracking updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update episode tracking', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to update episode tracking: ' . $e->getMessage());
        }
    }

    /**
     * Mark episode as completed
     */
    public function markEpisodeCompleted(Request $request, $episodeId)
    {
        $episode = PatientIVRStatus::findOrFail($episodeId);

        try {
            $episode->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);

            return back()->with('success', 'Episode marked as completed successfully.');
        } catch (Exception $e) {
            Log::error('Failed to mark episode as completed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to mark episode as completed: ' . $e->getMessage());
        }
    }

    /**
     * Generate IVR document (LEGACY - providers now generate IVRs)
     */
    public function generateIvr(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            // Check if IVR is required
            if ($request->input('ivr_required') === false) {
                // Skip IVR with justification
                $request->validate([
                    'justification' => 'required|string|min:10',
                ]);

                $this->ivrService->skipIvr(
                    $productRequest,
                    $request->input('justification'),
                    Auth::id()
                );

                return back()->with('success', 'IVR requirement bypassed successfully');
            }

            // Generate IVR document
            $submission = $this->ivrService->generateIvr($productRequest);

            return back()->with('success', 'IVR document generated successfully. Please review and send to manufacturer.');
        } catch (Exception $e) {
            Log::error('Failed to generate IVR', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to generate IVR: ' . $e->getMessage());
        }
    }

    /**
     * Send IVR to manufacturer
     */
    public function sendIvrToManufacturer(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $this->ivrService->submitToManufacturer($productRequest, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'IVR sent to manufacturer successfully',
                'order_status' => 'ivr_sent',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send IVR to manufacturer', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send IVR to manufacturer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm manufacturer approval
     */
    public function confirmManufacturerApproval(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $request->validate([
                'approved' => 'required|boolean',
                'reference' => 'required_if:approved,true|string',
                'notes' => 'nullable|string',
            ]);

            $this->ivrService->confirmManufacturerApproval(
                $productRequest,
                $request->input('approved'),
                $request->input('reference'),
                $request->input('notes'),
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => $request->input('approved') ? 'Manufacturer approval confirmed' : 'Manufacturer approval denied',
                'order_status' => $request->input('approved') ? 'ivr_confirmed' : 'ivr_denied',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to confirm manufacturer approval', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm manufacturer approval: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve order
     */
    public function approve($id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $productRequest->order_status = 'approved';
            $productRequest->approved_at = now();
            $productRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Order approved successfully',
                'order_status' => 'approved',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to approve order', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send back to provider
     */
    public function sendBack(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $request->validate([
                'reason' => 'required|string|min:10',
            ]);

            $this->ivrService->sendBackToProvider(
                $productRequest,
                $request->input('reason'),
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Order sent back to provider',
                'order_status' => 'sent_back',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send back order', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send back order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deny order
     */
    public function deny(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $request->validate([
                'reason' => 'required|string|min:10',
            ]);

            $productRequest->order_status = 'denied';
            $productRequest->save();

            // Log or handle the reason as needed
            Log::info('Order denied', [
                'product_request_id' => $productRequest->id,
                'reason' => $request->input('reason'),
                'actor_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order denied',
                'order_status' => 'denied',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to deny order', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deny order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit to manufacturer
     */
    public function submitToManufacturer($id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $this->ivrService->submitToManufacturer($productRequest, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Order submitted to manufacturer successfully',
                'order_status' => 'submitted_to_manufacturer',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to submit to manufacturer', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit to manufacturer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get action history for an order
     */
    private function getActionHistory(ProductRequest $productRequest): array
    {
        // TODO: Implement actual audit log retrieval
        // For now, return mock data based on timestamps
        $history = [];

        if ($productRequest->created_at) {
            $history[] = [
                'action' => 'Order Created',
                'actor' => $productRequest->provider->full_name ?? 'Provider',
                'timestamp' => $productRequest->created_at,
                'notes' => 'Product request initiated',
            ];
        }

        if ($productRequest->submitted_at) {
            $history[] = [
                'action' => 'Order Submitted',
                'actor' => $productRequest->provider->full_name ?? 'Provider',
                'timestamp' => $productRequest->submitted_at,
                'notes' => 'Submitted for review',
            ];
        }

        if ($productRequest->ivr_sent_at) {
            $history[] = [
                'action' => 'IVR Sent',
                'actor' => 'System',
                'timestamp' => $productRequest->ivr_sent_at,
                'notes' => 'IVR document sent to provider for signature',
            ];
        }

        if ($productRequest->ivr_signed_at) {
            $history[] = [
                'action' => 'IVR Signed',
                'actor' => $productRequest->provider->full_name ?? 'Provider',
                'timestamp' => $productRequest->ivr_signed_at,
                'notes' => 'Provider signed IVR document',
            ];
        }

        if ($productRequest->manufacturer_approved_at) {
            $history[] = [
                'action' => 'Manufacturer Approved',
                'actor' => 'Admin',
                'timestamp' => $productRequest->manufacturer_approved_at,
                'notes' => 'Manufacturer approval confirmed',
            ];
        }

        if ($productRequest->approved_at) {
            $history[] = [
                'action' => 'Order Approved',
                'actor' => 'Admin',
                'timestamp' => $productRequest->approved_at,
                'notes' => 'Order approved for submission',
            ];
        }

        if ($productRequest->order_submitted_at) {
            $history[] = [
                'action' => 'Submitted to Manufacturer',
                'actor' => 'Admin',
                'timestamp' => $productRequest->order_submitted_at,
                'notes' => 'Order submitted to manufacturer for fulfillment',
            ];
        }

        return collect($history)->sortByDesc('timestamp')->values()->toArray();
    }

    /**
     * Send order to manufacturer with custom recipients
     */
    public function sendToManufacturer(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $request->validate([
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'required|email',
            ]);

            // Get manufacturer information
            $manufacturer = $productRequest->getManufacturer();
            if (!$manufacturer) {
                throw new Exception('No manufacturer associated with this order');
            }

            // Prepare order data for email
            $orderData = [
                'order_number' => $productRequest->request_number,
                'patient_display_id' => $productRequest->patient_display_id,
                'provider' => $productRequest->provider->full_name,
                'facility' => $productRequest->facility->name,
                'service_date' => $productRequest->date_of_service,
                'products' => $productRequest->products->map(function($product) {
                    return [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $product->pivot->quantity ?? 1,
                        'size' => $product->pivot->size ?? null,
                    ];
                }),
                'total_value' => $productRequest->getTotalValue(),
            ];

            // Get IVR document if available
            $ivrDocument = null;
            if ($productRequest->docuseal_submission_id) {
                // TODO: Fetch IVR document from DocuSeal
                // $ivrDocument = $this->ivrService->getDocument($productRequest->docuseal_submission_id);
            }

            // Send email to all recipients
            $recipients = $request->input('recipients');

            // TODO: Implement actual email sending
            // For now, we'll just update the status
            Log::info('Sending order to manufacturer', [
                'order_id' => $productRequest->id,
                'recipients' => $recipients,
                'manufacturer' => $manufacturer->name,
            ]);

            // Update order status
            $productRequest->order_status = 'submitted_to_manufacturer';
            $productRequest->order_submitted_at = now();
            $productRequest->manufacturer_recipients = json_encode($recipients);
            $productRequest->save();

            // Add to order history
            DB::table('order_action_history')->insert([
                'product_request_id' => $productRequest->id,
                'action' => 'sent_to_manufacturer',
                'actor_id' => Auth::id(),
                'actor_name' => Auth::user()->name,
                'notes' => 'Order sent to ' . count($recipients) . ' recipient(s)',
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order sent to manufacturer successfully',
                'order_status' => 'submitted_to_manufacturer',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send order to manufacturer', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send order to manufacturer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tracking information for an order
     */
    public function updateTracking(Request $request, $id)
    {
        $productRequest = ProductRequest::findOrFail($id);

        try {
            $request->validate([
                'tracking_number' => 'required|string',
                'carrier' => 'required|string|in:ups,fedex,usps,dhl,other',
            ]);

            // Update tracking information
            $productRequest->tracking_number = $request->input('tracking_number');
            $productRequest->tracking_carrier = $request->input('carrier');
            $productRequest->order_status = 'shipped';
            $productRequest->shipped_at = now();
            $productRequest->save();

            // Add to order history
            DB::table('order_action_history')->insert([
                'product_request_id' => $productRequest->id,
                'action' => 'tracking_added',
                'actor_id' => Auth::id(),
                'actor_name' => Auth::user()->name,
                'notes' => 'Tracking number: ' . $request->input('tracking_number') . ' (' . strtoupper($request->input('carrier')) . ')',
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tracking information updated successfully',
                'order_status' => 'shipped',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update tracking information', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update tracking information: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get patient name from FHIR
     */
    private function getPatientName(?string $patientFhirId): string
    {
        if (!$patientFhirId) {
            return 'Unknown Patient';
        }

        // Cache patient names for 1 hour to improve performance
        return Cache::remember("patient_name_{$patientFhirId}", 3600, function () use ($patientFhirId) {
            try {
                // Extract the FHIR ID from the reference (format: "Patient/123")
                $fhirId = str_replace('Patient/', '', $patientFhirId);

                // Fetch patient from FHIR
                $patient = $this->fhirService->getPatientById($fhirId);

                if (!$patient) {
                    Log::warning('Patient not found in FHIR', ['fhir_id' => $fhirId]);
                    return 'Patient Not Found';
                }

                // Extract name from FHIR resource
                if (isset($patient['name']) && is_array($patient['name']) && count($patient['name']) > 0) {
                    $name = $patient['name'][0];
                    $firstName = isset($name['given']) && is_array($name['given']) ? implode(' ', $name['given']) : '';
                    $lastName = $name['family'] ?? '';

                    $fullName = trim($firstName . ' ' . $lastName);

                    return $fullName ?: 'Unknown Patient';
                }

                return 'Unknown Patient';
            } catch (\Exception $e) {
                Log::error('Failed to fetch patient name from FHIR', [
                    'patient_fhir_id' => $patientFhirId,
                    'error' => $e->getMessage()
                ]);

                // Return a fallback name to avoid breaking the UI
                return 'Patient (Error)';
            }
        });
    }

    /**
     * Get expiring IVRs for dashboard warning
     */
    private function getExpiringIVRsForDashboard()
    {
        try {
            $expiringIVRs = PatientIVRStatus::getExpiringIVRs(30);

            return $expiringIVRs->map(function ($ivr) {
                $daysUntilExpiration = $ivr->expiration_date ?
                    ceil(($ivr->expiration_date->getTimestamp() - now()->getTimestamp()) / (60 * 60 * 24)) :
                    0;

                return [
                    'id' => $ivr->id,
                    'patient_fhir_id' => $ivr->patient_fhir_id,
                    'patient_name' => $this->getPatientName($ivr->patient_fhir_id),
                    'patient_display_id' => $this->getPatientDisplayId($ivr->patient_fhir_id),
                    'manufacturer_name' => $ivr->manufacturer->name,
                    'expiration_date' => $ivr->expiration_date->format('Y-m-d'),
                    'days_until_expiration' => max(0, $daysUntilExpiration),
                ];
            })->sortBy('days_until_expiration')->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get expiring IVRs for dashboard', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get patient display ID from cache or database
     */
    private function getPatientDisplayId($patientFhirId)
    {
        return Cache::remember("patient_display_id_{$patientFhirId}", 3600, function () use ($patientFhirId) {
            $productRequest = ProductRequest::where('patient_fhir_id', $patientFhirId)
                ->select('patient_display_id')
                ->first();

            return $productRequest ? $productRequest->patient_display_id : 'Unknown';
        });
    }
}
