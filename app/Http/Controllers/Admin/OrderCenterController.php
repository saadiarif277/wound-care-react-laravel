<?php
//app/Http/Controllers/Admin/OrderCenterController.php - Updated methods

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\ProductRequest;
use App\Services\ManufacturerEmailService;
use App\Services\EmailNotificationService;
use App\Services\StatusChangeService;
use App\Services\DocusealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class OrderCenterController extends Controller
{
    protected EmailNotificationService $emailService;
    protected StatusChangeService $statusService;
    protected DocusealService $docusealService;

    public function __construct(
        EmailNotificationService $emailService,
        StatusChangeService $statusService,
        DocusealService $docusealService
    ) {
        $this->emailService = $emailService;
        $this->statusService = $statusService;
        $this->docusealService = $docusealService;
    }

    /**
     * Display episode-based order center dashboard
     */
    public function index(Request $request)
    {
        // Get product requests (orders) instead of episodes for the Index view
        $query = ProductRequest::with(['provider', 'facility', 'products']);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('patient_display_id', 'like', "%{$search}%")
                  ->orWhereHas('provider', function($providerQuery) use ($search) {
                      $providerQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('facility', function($facilityQuery) use ($search) {
                      $facilityQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->input('status')) {
            $query->where('order_status', $request->input('status'));
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get status counts for orders
        $statusCounts = ProductRequest::select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        // Transform orders for display
        $transformedOrders = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->request_number,
                'patient_name' => $order->patient_display_id ?? 'Unknown Patient',
                'patient_display_id' => $order->patient_display_id,
                'provider_name' => $order->provider->name ?? 'Unknown Provider',
                'facility_name' => $order->facility->name ?? 'Unknown Facility',
                'manufacturer_name' => $order->manufacturer ?? 'Unknown Manufacturer',
                'product_name' => $order->product_name ?? 'Unknown Product',
                'order_status' => $order->order_status,
                'ivr_status' => $order->ivr_status ?? 'N/A',
                'order_form_status' => $order->order_form_status ?? 'Not Started',
                'total_order_value' => (float) ($order->total_order_value ?? 0),
                'created_at' => $order->created_at->toISOString(),
                'action_required' => $order->order_status === 'pending',
            ];
        });

        return Inertia::render('Admin/OrderCenter/Index', [
            'orders' => [
                'data' => $transformedOrders->items(),
                'current_page' => $transformedOrders->currentPage(),
                'last_page' => $transformedOrders->lastPage(),
                'total' => $transformedOrders->total(),
            ],
            'statusCounts' => $statusCounts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show individual order details
     */
    public function show($orderId)
    {
        $order = ProductRequest::with(['provider', 'facility', 'products', 'episode'])
            ->findOrFail($orderId);

        // Transform order data for display
        $orderData = [
            'id' => $order->id,
            'order_number' => $order->request_number,
            'patient_name' => $order->patient_display_id ?? 'Unknown Patient',
            'patient_display_id' => $order->patient_display_id,
            'provider_name' => $order->provider->name ?? 'Unknown Provider',
            'facility_name' => $order->facility->name ?? 'Unknown Facility',
            'manufacturer_name' => $order->manufacturer ?? 'Unknown Manufacturer',
            'product_name' => $order->product_name ?? 'Unknown Product',
            'order_status' => $order->order_status,
            'ivr_status' => $order->ivr_status ?? 'N/A',
            'order_form_status' => $order->order_form_status ?? 'Not Started',
            'total_order_value' => (float) ($order->total_order_value ?? 0),
            'created_at' => $order->created_at->toISOString(),
            'action_required' => $order->order_status === 'pending',
            'episode_id' => $order->ivr_episode_id,
            'docuseal_submission_id' => $order->episode->docuseal_submission_id ?? null,
        ];

        return Inertia::render('Admin/OrderCenter/OrderDetails', [
            'order' => $orderData,
            'can_update_status' => Auth::user()->can('update-order-status'),
            'can_view_ivr' => Auth::user()->can('view-ivr-documents'),
        ]);
    }

    /**
     * Show episode details
     */
    public function showEpisode($episodeId)
    {
        $episode = PatientManufacturerIVREpisode::with(['manufacturer'])
            ->findOrFail($episodeId);

        // Get product requests for this episode
        $productRequests = ProductRequest::where('ivr_episode_id', $episode->id)
            ->with(['provider', 'facility', 'products'])
            ->get();

        // Transform episode data for display
        $episodeData = [
            'id' => $episode->id,
            'patient_id' => $episode->patient_id,
            'patient_name' => $episode->patient_name ?? $this->getPatientName($episode->patient_id),
            'patient_display_id' => $episode->patient_display_id,
            'status' => $episode->status,
            'ivr_status' => $episode->ivr_status,
            'verification_date' => $episode->verification_date?->format('Y-m-d'),
            'expiration_date' => $episode->expiration_date?->format('Y-m-d'),
            'manufacturer' => [
                'id' => $episode->manufacturer_id,
                'name' => $episode->manufacturer->name ?? 'Unknown Manufacturer',
                'contact_email' => $episode->manufacturer->contact_email ?? null,
                'contact_phone' => $episode->manufacturer->contact_phone ?? null,
            ],
            'orders' => $productRequests->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?? $order->request_number,
                    'order_status' => $order->order_status,
                    'provider' => [
                        'id' => $order->provider->id ?? null,
                        'name' => $order->provider ? $order->provider->full_name : 'Unknown Provider',
                        'email' => $order->provider->email ?? null,
                        'npi_number' => $order->provider->npi_number ?? null,
                    ],
                    'facility' => [
                        'id' => $order->facility->id ?? null,
                        'name' => $order->facility->name ?? 'Unknown Facility',
                        'city' => $order->facility->city ?? null,
                        'state' => $order->facility->state ?? null,
                    ],
                    'expected_service_date' => $order->expected_service_date?->format('Y-m-d'),
                    'submitted_at' => $order->submitted_at?->toISOString() ?? $order->created_at->toISOString(),
                    'total_order_value' => (float) ($order->total_order_value ?? 0),
                    'action_required' => $order->order_status === 'ready_for_review',
                    'products' => $order->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku ?? $product->q_code,
                            'quantity' => $product->pivot->quantity ?? 1,
                            'unit_price' => (float) ($product->pivot->unit_price ?? 0),
                            'total_price' => (float) ($product->pivot->total_price ?? 0),
                        ];
                    }),
                ];
            }),
            'total_order_value' => (float) ($productRequests->sum('total_order_value') ?? 0),
            'orders_count' => $productRequests->count(),
            'action_required' => $episode->status === 'ready_for_review',
            'docuseal' => [
                'status' => $episode->ivr_status,
                'signed_documents' => [], // Docuseal specific documents
                'audit_log_url' => null, // TODO: Implement if needed
                'last_synced_at' => $episode->updated_at->toISOString(),
            ],
            'documents' => $episode->metadata['documents'] ?? [],
            'audit_log' => $episode->audit_log ?? [],
        ];

        return Inertia::render('Admin/OrderCenter/ShowEpisode', [
            'episode' => $episodeData,
            'can_review_episode' => Auth::user()->can('review-episodes'),
            'can_manage_episode' => Auth::user()->can('manage-episodes'),
            'can_send_to_manufacturer' => Auth::user()->can('send-to-manufacturer'),
        ]);
    }

    /**
     * ASHLEY'S REQUIREMENT: Review provider-generated IVR
     * Provider has already completed IVR during order submission
     */
    public function reviewEpisode(Request $request, $episodeId)
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        // Validate that episode is ready for review
        if ($episode->status !== 'ready_for_review') {
            return back()->with('error', 'Episode is not ready for review.');
        }

        // Validate that IVR was completed by provider
        if ($episode->ivr_status !== 'provider_completed' || !$episode->docuseal_submission_id) {
            return back()->with('error', 'Provider has not completed IVR for this episode.');
        }

        DB::beginTransaction();
        try {
            // Update episode status to indicate admin has reviewed provider IVR
            $episode->update([
                'status' => 'ivr_verified',
                'ivr_status' => 'admin_reviewed',
                'admin_reviewed_at' => now(),
                'admin_reviewed_by' => Auth::id(),
            ]);

            // Update all orders in this episode
            ProductRequest::where('ivr_episode_id', $episode->id)->update([
                'order_status' => 'ivr_verified',
                'admin_reviewed_at' => now(),
            ]);

            // Log the action
            $this->logEpisodeAction($episode, 'review_ivr', 'Admin reviewed and approved provider-generated IVR');

            DB::commit();

            return back()->with('success', 'IVR approved successfully. Episode ready to send to manufacturer.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to review episode', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to review episode: ' . $e->getMessage());
        }
    }

    /**
     * ASHLEY'S REQUIREMENT: Send episode to manufacturer
     */
    public function sendEpisodeToManufacturer(Request $request, $episodeId)
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        if ($episode->status !== 'ivr_verified') {
            return back()->with('error', 'Episode must be reviewed before sending to manufacturer.');
        }

        // Validate email recipients
        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'notes' => 'nullable|string',
            'include_ivr' => 'boolean',
            'include_clinical_notes' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            // Get all orders in the episode
            $orders = ProductRequest::where('ivr_episode_id', $episode->id)
                ->with(['provider', 'facility', 'products'])
                ->get();

            // Prepare email data
            $emailData = [
                'episode_id' => $episode->id,
                'manufacturer' => $episode->manufacturer,
                'orders' => $orders,
                'notes' => $request->input('notes'),
                'include_ivr' => $request->input('include_ivr', true),
                'include_clinical_notes' => $request->input('include_clinical_notes', true),
                'recipients' => $request->input('recipients'),
                'sent_by' => Auth::user()->name,
                'sent_at' => now()
            ];

            // Send email via ManufacturerEmailService
            $emailService = app(ManufacturerEmailService::class);
            $result = $emailService->sendEpisodeToManufacturer($episode, $emailData);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // Update episode status
            $episode->update([
                'status' => 'sent_to_manufacturer',
                'manufacturer_sent_at' => now(),
                'manufacturer_sent_by' => Auth::id(),
                'metadata' => array_merge($episode->metadata ?? [], [
                    'email_recipients' => $request->input('recipients'),
                    'email_notes' => $request->input('notes'),
                    'sent_by_name' => Auth::user()->name
                ])
            ]);

            // Update all orders in episode
            ProductRequest::where('ivr_episode_id', $episode->id)->update([
                'order_status' => 'sent_to_manufacturer',
                'manufacturer_sent_at' => now(),
                'manufacturer_recipients' => json_encode($request->input('recipients'))
            ]);

            // Log the action
            $this->logEpisodeAction($episode, 'send_to_manufacturer',
                'Episode sent to manufacturer with ' . count($request->input('recipients')) . ' recipients');

            DB::commit();

            return back()->with('success', 'Episode sent to manufacturer successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to send episode to manufacturer', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to send episode to manufacturer: ' . $e->getMessage());
        }
    }

    /**
     * Update episode tracking information
     */
    public function updateEpisodeTracking(Request $request, $episodeId)
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'carrier' => 'required|string',
            'estimated_delivery' => 'nullable|date',
        ]);

        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        DB::beginTransaction();
        try {
            $episode->update([
                'status' => 'tracking_added',
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'estimated_delivery' => $request->estimated_delivery,
                'tracking_updated_at' => now(),
                'tracking_updated_by' => Auth::id(),
            ]);

            // Update all orders in episode
            ProductRequest::where('ivr_episode_id', $episode->id)->update([
                'order_status' => 'tracking_added',
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'estimated_delivery' => $request->estimated_delivery,
            ]);

            // Log the action
            $this->logEpisodeAction($episode, 'add_tracking',
                "Added tracking: {$request->tracking_number} via {$request->carrier}");

            DB::commit();

            return back()->with('success', 'Tracking information updated successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update episode tracking', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to update tracking: ' . $e->getMessage());
        }
    }

    /**
     * Mark episode as completed
     */
    public function markEpisodeCompleted(Request $request, $episodeId)
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        DB::beginTransaction();
        try {
            $episode->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);

            // Update all orders in episode
            ProductRequest::where('ivr_episode_id', $episode->id)->update([
                'order_status' => 'completed',
                'completed_at' => now(),
            ]);

            // Log the action
            $this->logEpisodeAction($episode, 'mark_completed', 'Episode marked as completed');

            DB::commit();

            return back()->with('success', 'Episode marked as completed successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark episode as completed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to mark episode as completed: ' . $e->getMessage());
        }
    }

    /**
     * Get patient name from FHIR (cached for performance)
     */
    private function getPatientName(?string $patientFhirId): string
    {
        if (!$patientFhirId) {
            return 'Unknown Patient';
        }

        // TODO: Implement FHIR service to get patient name
        // For now, return a placeholder
        return 'Patient ' . substr($patientFhirId, -6);
    }

    /**
     * Log episode actions for audit trail
     */
    private function logEpisodeAction($episode, $action, $description)
    {
        // Add to episode audit log
        $auditLog = $episode->audit_log ?? [];
        $auditLog[] = [
            'id' => count($auditLog) + 1,
            'action' => $action,
            'actor' => Auth::user()->full_name,
            'actor_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
            'description' => $description,
        ];

        $episode->update(['audit_log' => $auditLog]);
    }

    /**
     * Upload documents for an episode
     */
    public function uploadEpisodeDocuments(Request $request, $episodeId)
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
            'document_type' => 'sometimes|string|in:clinical_notes,insurance_card,wound_photo,face_sheet,other'
        ]);

        try {
            $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

            $uploadedDocuments = [];

            foreach ($request->file('documents') as $index => $file) {
                // Store file securely
                $path = $file->store('episodes/' . $episodeId . '/documents', 'private');

                // Create document record
                $document = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'episode_id' => $episodeId,
                    'name' => $file->getClientOriginalName(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'document_type' => $request->input('document_type', 'other'),
                    'uploaded_by' => Auth::id(),
                    'uploaded_at' => now(),
                    'url' => route('admin.episodes.documents.download', ['episode' => $episodeId, 'document' => $path])
                ];

                $uploadedDocuments[] = $document;
            }

            // Update episode metadata with documents
            $currentMetadata = $episode->metadata ?? [];
            $currentMetadata['documents'] = array_merge($currentMetadata['documents'] ?? [], $uploadedDocuments);
            $episode->update(['metadata' => $currentMetadata]);

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully',
                'documents' => $uploadedDocuments
            ]);

        } catch (Exception $e) {
            Log::error('Episode document upload failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download episode document
     */
    public function downloadEpisodeDocument($episodeId, $documentPath)
    {
        try {
            $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

            if (!\Illuminate\Support\Facades\Storage::disk('private')->exists($documentPath)) {
                abort(404, 'Document not found');
            }

                        $file = \Illuminate\Support\Facades\Storage::disk('private')->get($documentPath);
            $fileName = basename($documentPath);

            return response($file, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        } catch (Exception $e) {
            Log::error('Episode document download failed', [
                'episode_id' => $episodeId,
                'document_path' => $documentPath,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            abort(404, 'Document not found');
        }
    }

    /**
     * Delete episode document
     */
    public function deleteEpisodeDocument(Request $request, $episodeId, $documentId)
    {
        try {
            $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

            $currentMetadata = $episode->metadata ?? [];
            $documents = $currentMetadata['documents'] ?? [];

            // Find and remove document
            $documents = array_filter($documents, function($doc) use ($documentId) {
                return $doc['id'] !== $documentId;
            });

            // Update metadata
            $currentMetadata['documents'] = array_values($documents);
            $episode->update(['metadata' => $currentMetadata]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Episode document deletion failed', [
                'episode_id' => $episodeId,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document'
            ], 500);
        }
    }

    /**
     * Get IVR document for viewing/downloading
     */
    public function getIvrDocument($episodeId)
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        // Check if IVR exists
        if (!$episode->docuseal_submission_id) {
            return response()->json(['error' => 'IVR document not found'], 404);
        }

        try {
            // Get IVR document from DocuSeal
            $documentData = $this->docusealService->getSubmissionDocument($episode->docuseal_submission_id);

            // Update view tracking
            $episode->update([
                'last_ivr_viewed_at' => now(),
                'ivr_download_count' => $episode->ivr_download_count + 1,
            ]);

            return response()->json([
                'success' => true,
                'document_url' => $documentData['url'] ?? null,
                'audit_log_url' => $documentData['audit_log_url'] ?? null,
                'download_count' => $episode->ivr_download_count,
                'last_viewed' => $episode->last_ivr_viewed_at?->toISOString(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get IVR document', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to retrieve IVR document'], 500);
        }
    }

    /**
     * Change order status with notifications
     */
    public function changeOrderStatus(Request $request, $orderId)
    {
        $order = ProductRequest::findOrFail($orderId);
        $newStatus = $request->input('status');
        $statusType = $request->input('status_type', 'order'); // 'ivr' or 'order'
        $notes = $request->input('notes');
        $rejectionReason = $request->input('rejection_reason');
        $cancellationReason = $request->input('cancellation_reason');
        $sendNotification = $request->input('send_notification', true);
        $carrier = $request->input('carrier');
        $trackingNumber = $request->input('tracking_number');

        // Validate status change based on type
        $validIVRStatuses = ['pending', 'sent', 'verified', 'rejected', 'n/a'];
        $validOrderStatuses = [
            'pending', 'submitted_to_manufacturer', 'confirmed_by_manufacturer',
            'rejected', 'canceled', 'shipped', 'delivered'
        ];

        $isValidStatus = false;
        if ($statusType === 'ivr' && in_array($newStatus, $validIVRStatuses)) {
            $isValidStatus = true;
        } elseif ($statusType === 'order' && in_array($newStatus, $validOrderStatuses)) {
            $isValidStatus = true;
        }

        if (!$isValidStatus) {
            return response()->json(['error' => 'Invalid status for type: ' . $statusType], 400);
        }

        try {
            // Prepare update data
            $updateData = [
                'notes' => $notes,
                'rejection_reason' => $rejectionReason,
                'cancellation_reason' => $cancellationReason,
            ];

            // Update appropriate status field based on type
            if ($statusType === 'ivr') {
                $updateData['ivr_status'] = $newStatus;
                $previousStatus = $order->ivr_status ?? 'none';
            } else {
                $updateData['order_status'] = $newStatus;
                $previousStatus = $order->order_status ?? 'none';

                // Save shipping info when submitted to manufacturer
                if ($newStatus === 'submitted_to_manufacturer') {
                    $updateData['carrier'] = $carrier;
                    $updateData['tracking_number'] = $trackingNumber;

                    // Save shipping info as JSON
                    if ($carrier || $trackingNumber) {
                        $updateData['shipping_info'] = [
                            'carrier' => $carrier,
                            'tracking_number' => $trackingNumber,
                            'submitted_at' => now()->toISOString(),
                            'submitted_by' => auth()->user()->name ?? 'Admin',
                        ];
                    }
                }
            }

            // Update order in database
            $order->update($updateData);

            // Log status change
            $success = $this->statusService->changeOrderStatus($order, $newStatus, $notes);

            // Send notification if requested
            $notificationSent = false;
            if ($sendNotification) {
                try {
                    $changedBy = auth()->user()->name ?? 'Admin';
                    $notificationSent = $this->emailService->sendStatusChangeNotification(
                        $order,
                        $previousStatus,
                        $newStatus,
                        $changedBy,
                        $notes
                    );
                } catch (Exception $e) {
                    Log::warning('Failed to send notification', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($success) {
                $message = ucfirst($statusType) . ' status updated successfully';
                if ($notificationSent) {
                    $message .= ' and email sent';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'new_status' => $newStatus,
                    'status_type' => $statusType,
                    'notification_sent' => $notificationSent,
                ]);
            } else {
                return response()->json(['error' => 'Failed to update ' . $statusType . ' status'], 500);
            }

        } catch (Exception $e) {
            Log::error('Failed to change ' . $statusType . ' status', [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update ' . $statusType . ' status'], 500);
        }
    }

    /**
     * Get order status history
     */
    public function getOrderStatusHistory($orderId)
    {
        $order = ProductRequest::findOrFail($orderId);
        $history = $this->statusService->getStatusHistory($order);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Get email notification statistics
     */
    public function getNotificationStats($orderId)
    {
        $order = ProductRequest::findOrFail($orderId);
        $stats = $this->emailService->getNotificationStats($order);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Send IVR notification
     */
    public function sendIvrNotification($episodeId)
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($episodeId);

        // Get the first order for this episode
        $order = ProductRequest::where('ivr_episode_id', $episodeId)->first();

        if (!$order) {
            return response()->json(['error' => 'No order found for this episode'], 404);
        }

        try {
            $success = $this->emailService->sendIvrSentNotification($order);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'IVR notification sent successfully' : 'Failed to send IVR notification',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send IVR notification', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to send IVR notification'], 500);
        }
    }

    /**
     * Get enhanced order details with IVR and notification data
     */
    public function getEnhancedOrderDetails($orderId)
    {
        $order = ProductRequest::with(['provider', 'facility', 'products', 'episode'])
            ->findOrFail($orderId);

        // Get status history
        $statusHistory = $this->statusService->getStatusHistory($order);

        // Get notification stats
        $notificationStats = $this->emailService->getNotificationStats($order);

        // Get IVR data if available
        $ivrData = null;
        if ($order->episode && $order->episode->docuseal_submission_id) {
            try {
                $ivrData = $this->docusealService->getSubmissionDocument($order->episode->docuseal_submission_id);
            } catch (Exception $e) {
                Log::warning('Failed to get IVR data for order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Get patient data from FHIR or request submission
        $patientData = null;
        if ($order->patient_fhir_id) {
            try {
                $fhirService = app(\App\Services\FhirService::class);
                $patient = $fhirService->searchPatients(['_id' => $order->patient_fhir_id]);
                if (!empty($patient['entry'][0]['resource'])) {
                    $resource = $patient['entry'][0]['resource'];
                    $patientData = [
                        'dob' => $resource['birthDate'] ?? null,
                        'gender' => $resource['gender'] ?? null,
                        'phone' => $resource['telecom'][0]['value'] ?? null,
                        'address' => isset($resource['address'][0]) ? 
                            implode(', ', array_filter([
                                $resource['address'][0]['line'][0] ?? '',
                                $resource['address'][0]['city'] ?? '',
                                $resource['address'][0]['state'] ?? '',
                                $resource['address'][0]['postalCode'] ?? ''
                            ])) : null,
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Failed to get patient FHIR data', ['error' => $e->getMessage()]);
            }
        }

        // Get product details
        $productData = null;
        if ($order->products->isNotEmpty()) {
            $product = $order->products->first();
            $productData = [
                'code' => $product->sku ?? $product->code ?? null,
                'quantity' => $product->pivot->quantity ?? 1,
                'size' => $product->size ?? null,
                'category' => $product->category ?? null,
                'shippingInfo' => [
                    'speed' => $order->shipping_speed ?? 'Standard',
                    'address' => $order->shipping_address ?? $order->facility->address ?? null,
                ],
            ];
        }

        // Get clinical summary data which contains all submitted form data
        $clinicalSummary = $order->clinical_summary ?? [];
        
        // Extract patient insurance data from clinical summary
        $insuranceData = null;
        if (isset($clinicalSummary['insurance'])) {
            $insuranceData = [
                'primary' => isset($clinicalSummary['insurance']['primaryName']) ? 
                    ($clinicalSummary['insurance']['primaryName'] ?? 'N/A') . ' - ' . ($clinicalSummary['insurance']['primaryMemberId'] ?? 'N/A') : 'N/A',
                'secondary' => isset($clinicalSummary['insurance']['hasSecondary']) && $clinicalSummary['insurance']['hasSecondary'] ? 
                    ($clinicalSummary['insurance']['secondaryName'] ?? 'N/A') . ' - ' . ($clinicalSummary['insurance']['secondaryMemberId'] ?? 'N/A') : 'N/A',
            ];
        }
        
        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'request_number' => $order->request_number,
                'patient_display_id' => $order->patient_display_id,
                'order_status' => $order->order_status,
                'provider' => $order->provider,
                'facility' => $order->facility,
                'products' => $order->products,
                'total_order_value' => $order->total_order_value,
                'created_at' => $order->created_at->toISOString(),
                'updated_at' => $order->updated_at->toISOString(),
            ],
            'patient' => $patientData ?? [
                'dob' => $clinicalSummary['patient']['dateOfBirth'] ?? null,
                'gender' => $clinicalSummary['patient']['gender'] ?? null,
                'phone' => $clinicalSummary['patient']['phone'] ?? null,
                'address' => isset($clinicalSummary['patient']['address']) ? 
                    implode(', ', array_filter([
                        $clinicalSummary['patient']['address']['street'] ?? '',
                        $clinicalSummary['patient']['address']['city'] ?? '',
                        $clinicalSummary['patient']['address']['state'] ?? '',
                        $clinicalSummary['patient']['address']['zipCode'] ?? ''
                    ])) : null,
                'insurance' => $insuranceData ?? [
                    'primary' => 'N/A',
                    'secondary' => 'N/A',
                ],
            ],
            'product' => $productData ?? [
                'code' => $product->sku ?? $product->code ?? null,
                'quantity' => $product->pivot->quantity ?? 1,
                'size' => $product->pivot->size ?? null,
                'category' => $product->category ?? null,
                'shippingInfo' => [
                    'speed' => $clinicalSummary['orderPreferences']['shippingSpeed'] ?? $order->shipping_speed ?? 'Standard',
                    'address' => $clinicalSummary['orderPreferences']['shippingAddress'] ?? $order->shipping_address ?? $order->facility->address ?? null,
                ],
            ],
            'clinical' => [
                'woundType' => $clinicalSummary['clinical']['woundType'] ?? $order->wound_type ?? null,
                'location' => $clinicalSummary['clinical']['woundLocation'] ?? null,
                'size' => isset($clinicalSummary['clinical']['woundSizeLength']) && isset($clinicalSummary['clinical']['woundSizeWidth']) ? 
                    $clinicalSummary['clinical']['woundSizeLength'] . ' x ' . $clinicalSummary['clinical']['woundSizeWidth'] . 'cm' : null,
                'cptCodes' => isset($clinicalSummary['clinical']['applicationCptCodes']) ? 
                    implode(', ', $clinicalSummary['clinical']['applicationCptCodes']) : null,
                'placeOfService' => $order->place_of_service ?? $clinicalSummary['orderPreferences']['placeOfService'] ?? null,
                'failedConservativeTreatment' => $clinicalSummary['clinical']['failedConservativeTreatment'] ?? false,
            ],
            'forms' => [
                'consent' => $clinicalSummary['attestations']['informationAccurate'] ?? false,
                'assignmentOfBenefits' => $clinicalSummary['attestations']['documentationMaintained'] ?? false,
                'medicalNecessity' => $clinicalSummary['attestations']['medicalNecessityEstablished'] ?? false,
            ],
            'provider' => [
                'npi' => $order->provider->npi_number ?? null,
            ],
            'submission' => [
                'informationAccurate' => $clinicalSummary['attestations']['informationAccurate'] ?? false,
                'documentationMaintained' => $clinicalSummary['attestations']['documentationMaintained'] ?? false,
                'authorizePriorAuth' => $clinicalSummary['attestations']['priorAuthPermission'] ?? false,
            ],
            'status_history' => $statusHistory,
            'notification_stats' => $notificationStats,
            'ivr_data' => $ivrData,
        ]);
    }

    /**
     * View IVR document in new tab
     */
    public function viewIvrDocument($orderId)
    {
        $order = ProductRequest::with(['episode'])->findOrFail($orderId);

        if (!$order->episode || !$order->episode->docuseal_submission_id) {
            return response()->json(['error' => 'IVR document not found'], 404);
        }

        try {
            $documentData = $this->docusealService->getSubmissionDocument($order->episode->docuseal_submission_id);

            // Redirect to DocuSeal viewing URL
            return redirect($documentData['url']);

        } catch (Exception $e) {
            Log::error('Failed to view IVR document', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to retrieve IVR document'], 500);
        }
    }

    /**
     * Download IVR document
     */
    public function downloadIvrDocument($orderId)
    {
        $order = ProductRequest::with(['episode'])->findOrFail($orderId);

        if (!$order->episode || !$order->episode->docuseal_submission_id) {
            return response()->json(['error' => 'IVR document not found'], 404);
        }

        try {
            $documentData = $this->docusealService->getSubmissionDocument($order->episode->docuseal_submission_id);

            // Download the document
            $response = Http::withHeaders([
                'Authorization' => 'API-Key ' . config('services.docuseal.api_key'),
            ])->get($documentData['url']);

            if (!$response->successful()) {
                throw new Exception('Failed to download document from DocuSeal');
            }

            // Return the file as a download
            return response($response->body())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="ivr-document-' . $orderId . '.pdf"');

        } catch (Exception $e) {
            Log::error('Failed to download IVR document', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to download IVR document'], 500);
        }
    }
}
