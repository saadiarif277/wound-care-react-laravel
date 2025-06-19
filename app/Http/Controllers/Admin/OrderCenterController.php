<?php
//app/Http/Controllers/Admin/OrderCenterController.php - Updated methods

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientIVRStatus;
use App\Models\Order\ProductRequest;
use App\Services\ManufacturerEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class OrderCenterController extends Controller
{
    /**
     * Display episode-based order center dashboard
     */
    public function index(Request $request)
    {
        // Get episodes with related product requests (not orders)
        $query = PatientIVRStatus::with(['manufacturer']);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('patient_display_id', 'like', "%{$search}%")
                  ->orWhere('patient_name', 'like', "%{$search}%")
                  ->orWhereHas('manufacturer', function($manufacturerQuery) use ($search) {
                      $manufacturerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('ivr_status') && $request->input('ivr_status')) {
            $query->where('ivr_status', $request->input('ivr_status'));
        }

        if ($request->has('action_required') && $request->input('action_required') === 'true') {
            $query->where('status', 'ready_for_review');
        }

        $episodes = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get status counts
        $statusCounts = PatientIVRStatus::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $ivrStatusCounts = PatientIVRStatus::select('ivr_status', DB::raw('count(*) as count'))
            ->groupBy('ivr_status')
            ->pluck('count', 'ivr_status')
            ->toArray();

        // Transform episodes for display
        $transformedEpisodes = $episodes->through(function ($episode) {
            // Get product requests for this episode
            $productRequests = ProductRequest::where('ivr_episode_id', $episode->id)
                ->with(['provider', 'facility'])
                ->get();

            return [
                'id' => $episode->id,
                'patient_id' => $episode->patient_id,
                'patient_name' => $episode->patient_name ?? $this->getPatientName($episode->patient_id),
                'patient_display_id' => $episode->patient_display_id,
                'manufacturer' => [
                    'id' => $episode->manufacturer_id,
                    'name' => $episode->manufacturer->name ?? 'Unknown Manufacturer',
                    'contact_email' => $episode->manufacturer->contact_email ?? null,
                ],
                'status' => $episode->status,
                'ivr_status' => $episode->ivr_status,
                'verification_date' => $episode->verification_date?->format('Y-m-d'),
                'expiration_date' => $episode->expiration_date?->format('Y-m-d'),
                'orders_count' => $productRequests->count(),
                'total_order_value' => (float) ($productRequests->sum('total_order_value') ?? 0),
                'latest_order_date' => $productRequests->max('created_at')?->toISOString() ?? $episode->created_at->toISOString(),
                'action_required' => $episode->status === 'ready_for_review',
                'orders' => $productRequests->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number ?? $order->request_number,
                        'order_status' => $order->order_status,
                        'expected_service_date' => $order->expected_service_date?->format('Y-m-d'),
                        'submitted_at' => $order->submitted_at?->toISOString() ?? $order->created_at->toISOString(),
                    ];
                }),
            ];
        });

        // Calculate stats for the enhanced dashboard
        $stats = [
            'total_episodes' => array_sum($statusCounts),
            'pending_review' => $statusCounts['ready_for_review'] ?? 0,
            'ivr_expiring_soon' => PatientIVRStatus::where('expiration_date', '<=', now()->addDays(30))
                ->where('expiration_date', '>', now())
                ->count(),
            'total_value' => $transformedEpisodes->sum('total_order_value'),
            'episodes_this_week' => PatientIVRStatus::where('created_at', '>=', now()->startOfWeek())->count(),
            'completion_rate' => array_sum($statusCounts) > 0
                ? round(($statusCounts['completed'] ?? 0) / array_sum($statusCounts) * 100, 1)
                : 0,
        ];

        // AI Insights (simulated for now)
        $aiInsights = [];
        if (($statusCounts['ready_for_review'] ?? 0) > 5) {
            $aiInsights[] = [
                'id' => 'high-pending',
                'type' => 'warning',
                'title' => 'High Pending Reviews',
                'description' => "{$statusCounts['ready_for_review']} episodes awaiting review - consider bulk processing",
                'action' => [
                    'label' => 'View Pending',
                    'route' => route('admin.orders.index', ['status' => 'ready_for_review']),
                ],
                'confidence' => 0.95,
            ];
        }

        // Recent activity
        $recentActivity = collect([
            ['id' => '1', 'type' => 'episode', 'description' => 'New episode created', 'timestamp' => now()->toISOString(), 'user' => 'System'],
        ]);

        // Performance data for charts
        $performanceData = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'episodesCompleted' => [5, 8, 12, 7, 10, 6, 9],
            'averageProcessingTime' => [2.5, 3.1, 2.8, 3.5, 2.9, 3.2, 3.0],
        ];

        return Inertia::render('Admin/OrderCenter/EnhancedDashboard', [
            'episodes' => $transformedEpisodes->items(), // Extract just the data array from paginated results
            'stats' => $stats,
            'aiInsights' => $aiInsights,
            'recentActivity' => $recentActivity,
            'performanceData' => $performanceData,
            'filters' => $request->only(['search', 'status', 'ivr_status', 'action_required']),
            'pagination' => [
                'total' => $transformedEpisodes->total(),
                'per_page' => $transformedEpisodes->perPage(),
                'current_page' => $transformedEpisodes->currentPage(),
                'last_page' => $transformedEpisodes->lastPage(),
            ],
        ]);
    }

    /**
     * Show episode details
     */
    public function showEpisode($episodeId)
    {
        $episode = PatientIVRStatus::with(['manufacturer'])
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
                'signed_documents' => [], // DocuSeal specific documents
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
        $episode = PatientIVRStatus::findOrFail($episodeId);

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

        } catch (\Exception $e) {
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
        $episode = PatientIVRStatus::findOrFail($episodeId);

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
                throw new \Exception($result['message']);
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

        } catch (\Exception $e) {
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

        $episode = PatientIVRStatus::findOrFail($episodeId);

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

        } catch (\Exception $e) {
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
        $episode = PatientIVRStatus::findOrFail($episodeId);

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

        } catch (\Exception $e) {
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
            $episode = PatientIVRStatus::findOrFail($episodeId);

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

        } catch (\Exception $e) {
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
            $episode = PatientIVRStatus::findOrFail($episodeId);

            if (!\Illuminate\Support\Facades\Storage::disk('private')->exists($documentPath)) {
                abort(404, 'Document not found');
            }

                        $file = \Illuminate\Support\Facades\Storage::disk('private')->get($documentPath);
            $fileName = basename($documentPath);

            return response($file, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        } catch (\Exception $e) {
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
            $episode = PatientIVRStatus::findOrFail($episodeId);

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

        } catch (\Exception $e) {
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
}
