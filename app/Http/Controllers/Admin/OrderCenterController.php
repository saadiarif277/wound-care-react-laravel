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
                'total_order_value' => $productRequests->sum('total_order_value') ?? 0,
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

        return Inertia::render('Admin/OrderCenter/Index', [
            'episodes' => $transformedEpisodes,
            'filters' => $request->only(['search', 'status', 'ivr_status', 'action_required']),
            'statusCounts' => $statusCounts,
            'ivrStatusCounts' => $ivrStatusCounts,
            'manufacturers' => [], // TODO: Add manufacturers list
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
                    'total_order_value' => $order->total_order_value ?? 0,
                    'action_required' => $order->order_status === 'ready_for_review',
                    'products' => $order->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku ?? $product->q_code,
                            'quantity' => $product->pivot->quantity ?? 1,
                            'unit_price' => $product->pivot->unit_price ?? 0,
                            'total_price' => $product->pivot->total_price ?? 0,
                        ];
                    }),
                ];
            }),
            'total_order_value' => $productRequests->sum('total_order_value') ?? 0,
            'orders_count' => $productRequests->count(),
            'action_required' => $episode->status === 'ready_for_review',
            'docuseal' => [
                'status' => $episode->ivr_status,
                'signed_documents' => $episode->metadata['documents'] ?? [],
                'audit_log_url' => null, // TODO: Implement if needed
                'last_synced_at' => $episode->updated_at->toISOString(),
            ],
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
     */
    public function reviewEpisode(Request $request, $episodeId)
    {
        $episode = PatientIVRStatus::findOrFail($episodeId);

        // Validate that episode is ready for review
        if ($episode->status !== 'ready_for_review') {
            return back()->with('error', 'Episode is not ready for review.');
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

            return back()->with('success', 'Episode IVR reviewed and approved successfully. Ready to send to manufacturer.');

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

        DB::beginTransaction();
        try {
            // Update episode status
            $episode->update([
                'status' => 'sent_to_manufacturer',
                'manufacturer_sent_at' => now(),
                'manufacturer_sent_by' => Auth::id(),
            ]);

            // Update all orders in episode
            ProductRequest::where('ivr_episode_id', $episode->id)->update([
                'order_status' => 'sent_to_manufacturer',
                'manufacturer_sent_at' => now(),
            ]);

            // Log the action
            $this->logEpisodeAction($episode, 'send_to_manufacturer', 'Episode sent to manufacturer');

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
}
