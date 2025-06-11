<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Services\DocusealService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCenterController extends Controller
{
    protected $docusealService;

    public function __construct(DocusealService $docusealService)
    {
        $this->docusealService = $docusealService;
    }

    /**
     * Display the admin order center dashboard
     */
    public function index(Request $request)
    {
        // Log the start of the query
        Log::info('Starting order center query with DB::table');

        // First, let's log what's actually in the orders table
        $rawOrders = DB::table('orders')->get();
        Log::info('Raw orders from database', [
            'count' => $rawOrders->count(),
            'orders' => $rawOrders->toArray()
        ]);

        // Build base query using DB::table
        $query = DB::table('orders')
            ->leftJoin('users as providers', 'orders.provider_id', '=', 'providers.id')
            ->leftJoin('facilities', 'orders.facility_id', '=', 'facilities.id')
            ->leftJoin('manufacturers', 'orders.manufacturer_id', '=', 'manufacturers.id')
            ->select([
                'orders.*',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.email as provider_email',
                'providers.npi_number as provider_npi',
                'facilities.name as facility_name',
                'facilities.city as facility_city',
                'facilities.state as facility_state',
                'facilities.zip_code as facility_zip_code',
                'facilities.phone as facility_phone',
                'manufacturers.name as manufacturer_name',
                'manufacturers.contact_email as manufacturer_contact_email',
            ]);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('orders.order_number', 'like', "%{$search}%")
                  ->orWhere('providers.first_name', 'like', "%{$search}%")
                  ->orWhere('providers.last_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->input('status')) {
            $query->where('orders.order_status', $request->input('status'));
        }

        if ($request->has('action_required') && $request->input('action_required') === 'true') {
            $query->where('orders.action_required', true);
        }

        if ($request->has('manufacturer') && $request->input('manufacturer')) {
            $query->where('orders.manufacturer_id', $request->input('manufacturer'));
        }

        // Sort by creation date instead of submitted_at
        $query->orderBy('orders.created_at', 'desc');

        // Get the orders and log the count
        $orders = $query->paginate(20);

        // Log detailed query information
        Log::info('Orders query details', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'total' => $orders->total(),
            'count' => $orders->count(),
            'first_page' => $orders->items()
        ]);

        // Get status counts using order_status instead of status
        $statusCounts = DB::table('orders')
            ->select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->get()
            ->pluck('count', 'order_status')
            ->toArray();

        // Log the status counts
        Log::info('Order status counts from DB::table', ['counts' => $statusCounts]);

        // Ensure all statuses are present
        $allStatuses = ['pending_ivr', 'ivr_sent', 'ivr_confirmed', 'approved', 'sent_back', 'denied', 'submitted_to_manufacturer'];
        foreach ($allStatuses as $status) {
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
        }

        // Get manufacturers for filter
        $manufacturers = DB::table('manufacturers')
            ->select('id', 'name')
            ->get();

        // Transform orders for the frontend
        $transformedOrders = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'patient_display_id' => $order->patient_display_id ?? 'PAT-' . str_pad($order->id, 3, '0', STR_PAD_LEFT),
                'patient_fhir_id' => $order->patient_fhir_id,
                'order_status' => $order->order_status ?? 'pending_ivr',
                'provider' => [
                    'id' => $order->provider_id,
                    'name' => trim($order->provider_first_name . ' ' . $order->provider_last_name),
                    'email' => $order->provider_email,
                    'npi_number' => $order->provider_npi,
                ],
                'facility' => [
                    'id' => $order->facility_id,
                    'name' => $order->facility_name,
                    'city' => $order->facility_city,
                    'state' => $order->facility_state,
                ],
                'manufacturer' => [
                    'id' => $order->manufacturer_id,
                    'name' => $order->manufacturer_name,
                    'contact_email' => $order->manufacturer_contact_email,
                ],
                'expected_service_date' => $order->expected_service_date ?? $order->date_of_service,
                'submitted_at' => $order->submitted_at ?? $order->created_at,
                'total_order_value' => $order->total_amount ?? 0,
                'products_count' => 1, // Default to 1, can be enhanced later
                'action_required' => $order->action_required ?? false,
                'ivr_generation_status' => $order->ivr_generation_status,
                'docuseal_generation_status' => $order->docuseal_generation_status,
            ];
        });

        return Inertia::render('Admin/OrderCenter/Index', [
            'orders' => $transformedOrders,
            'filters' => $request->only(['search', 'status', 'action_required', 'manufacturer', 'date_range']),
            'statusCounts' => $statusCounts,
            'manufacturers' => $manufacturers,
        ]);
    }

    /**
     * Display order details
     */
    public function show(Order $order)
    {
        // Add debugging
        Log::info('OrderCenterController@show called', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status
        ]);

        $order->load([
            'provider',
            'facility',
            'manufacturer',
            'items.product',
        ]);

        // Transform order for frontend
        $orderDetail = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'patient_display_id' => $order->patient_display_id ?? 'PAT-' . str_pad($order->id, 3, '0', STR_PAD_LEFT),
            'patient_fhir_id' => $order->patient_fhir_id,
            'order_status' => $order->order_status ?? 'pending_ivr',
            'provider' => [
                'id' => $order->provider->id,
                'name' => $order->provider->first_name . ' ' . $order->provider->last_name,
                'email' => $order->provider->email,
                'npi_number' => $order->provider->npi_number ?? null,
            ],
            'facility' => [
                'id' => $order->facility->id,
                'name' => $order->facility->name,
                'address' => $order->facility->address ?? null,
                'city' => $order->facility->city,
                'state' => $order->facility->state,
                'zip' => $order->facility->zip_code ?? null,
                'phone' => $order->facility->phone ?? null,
            ],
            'manufacturer' => $order->manufacturer ? [
                'id' => $order->manufacturer->id,
                'name' => $order->manufacturer->name,
                'contact_email' => $order->manufacturer->contact_email ?? null,
                'contact_phone' => $order->manufacturer->contact_phone ?? null,
                'ivr_template_id' => $order->manufacturer->docuseal_template_id ?? null,
            ] : null,
            'patient_info' => [
                'dob' => null, // Retrieved from FHIR if needed
                'insurance_name' => $order->payer_name ?? null,
                'insurance_id' => null,
                'diagnosis_codes' => [],
            ],
            'order_details' => [
                'products' => $order->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'quantity' => $item->quantity,
                        'size' => $item->graph_size,
                        'unit_price' => $item->price,
                        'total_price' => $item->total_amount,
                    ];
                }),
                'wound_type' => null, // Retrieved from product request if linked
                'wound_location' => null,
                'wound_size' => null,
                'wound_duration' => null,
            ],
            'documents' => [], // TODO: Implement document retrieval
            'action_history' => $this->getOrderActionHistory($order),
            'expected_service_date' => $order->expected_service_date ?? $order->date_of_service,
            'submitted_at' => $order->submitted_at ?? $order->created_at,
            'total_order_value' => $order->total_amount,
            'ivr_generation_status' => $order->ivr_generation_status,
            'ivr_skip_reason' => $order->ivr_skip_reason,
            'docuseal_generation_status' => $order->docuseal_generation_status,
            'docuseal_submission_id' => $order->docuseal_submission_id ?? null,
        ];

        // Determine permissions
        $canGenerateIvr = $order->order_status === 'pending_ivr';
        $canApprove = $order->order_status === 'ivr_confirmed';
        $canSubmitToManufacturer = $order->order_status === 'approved';

        return Inertia::render('Admin/OrderCenter/Show', [
            'order' => $orderDetail,
            'can_generate_ivr' => $canGenerateIvr,
            'can_approve' => $canApprove,
            'can_submit_to_manufacturer' => $canSubmitToManufacturer,
        ]);
    }

    /**
     * Generate IVR for order
     */
    public function generateIvr(Request $request, Order $order)
    {
        $request->validate([
            'ivr_required' => 'required|boolean',
            'justification' => 'required_if:ivr_required,false|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            if ($request->input('ivr_required')) {
                // Generate IVR using DocuSeal
                $result = $this->docusealService->generateDocumentForOrder($order, 'ivr');

                if ($result['success']) {
                    $order->update([
                        'order_status' => 'ivr_sent',
                        'ivr_generation_status' => 'generated',
                        'ivr_generated_at' => now(),
                        'ivr_sent_at' => now(),
                        'action_required' => false,
                        'docuseal_submission_id' => $result['submission_id'] ?? null,
                    ]);

                    // Log action
                    $this->logOrderAction($order, 'generate_ivr', 'IVR generated and sent to manufacturer');
                } else {
                    throw new \Exception('Failed to generate IVR: ' . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                // Skip IVR generation
                $order->update([
                    'order_status' => 'ivr_confirmed',
                    'ivr_generation_status' => 'skipped',
                    'ivr_skip_reason' => $request->input('justification'),
                    'ivr_confirmed_at' => now(),
                    'action_required' => true,
                ]);

                // Log action
                $this->logOrderAction($order, 'skip_ivr', 'IVR skipped: ' . $request->input('justification'));
            }

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('success', $request->input('ivr_required') ? 'IVR generated and sent successfully' : 'IVR skipped, order ready for approval');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate IVR', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'Failed to generate IVR: ' . $e->getMessage());
        }
    }

    /**
     * Approve order
     */
    public function approve(Request $request, Order $order)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'notify_provider' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'order_status' => 'approved',
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
                'action_required' => true, // Ready to submit to manufacturer
            ]);

            // Log action
            $this->logOrderAction($order, 'approve', 'Order approved' . ($request->input('notes') ? ': ' . $request->input('notes') : ''));

            // TODO: Send notification to provider if requested

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order approved successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'Failed to approve order: ' . $e->getMessage());
        }
    }

    /**
     * Send order back to provider
     */
    public function sendBack(Request $request, Order $order)
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
            'notify_provider' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'order_status' => 'sent_back',
                'sent_back_at' => now(),
                'send_back_notes' => $request->input('notes'),
                'action_required' => false,
            ]);

            // Log action
            $this->logOrderAction($order, 'send_back', 'Order sent back: ' . $request->input('notes'));

            // TODO: Send notification to provider

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order sent back to provider');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send order back', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'Failed to send order back: ' . $e->getMessage());
        }
    }

    /**
     * Deny order
     */
    public function deny(Request $request, Order $order)
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
            'notify_provider' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'order_status' => 'denied',
                'denied_at' => now(),
                'denial_reason' => $request->input('notes'),
                'action_required' => false,
            ]);

            // Log action
            $this->logOrderAction($order, 'deny', 'Order denied: ' . $request->input('notes'));

            // TODO: Send notification to provider

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order denied');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to deny order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'Failed to deny order: ' . $e->getMessage());
        }
    }

    /**
     * Submit order to manufacturer
     */
    public function submitToManufacturer(Request $request, Order $order)
    {
        DB::beginTransaction();
        try {
            // Generate final order documents using DocuSeal
            $result = $this->docusealService->generateDocumentForOrder($order, 'order_form');

            if ($result['success']) {
                $order->update([
                    'order_status' => 'submitted_to_manufacturer',
                    'submitted_to_manufacturer_at' => now(),
                    'action_required' => false,
                    'docuseal_generation_status' => 'completed',
                ]);

                // Log action
                $this->logOrderAction($order, 'submit_to_manufacturer', 'Order submitted to manufacturer');

                // TODO: Send order to manufacturer via email/API
            } else {
                throw new \Exception('Failed to generate order documents');
            }

            DB::commit();

            return redirect()->route('admin.orders.show', $order)
                ->with('success', 'Order submitted to manufacturer successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit order to manufacturer', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.orders.show', $order)
                ->with('error', 'Failed to submit order to manufacturer: ' . $e->getMessage());
        }
    }

    /**
     * Create new order (admin-created)
     */
    public function create()
    {
        // TODO: Implement admin order creation form
        return Inertia::render('Admin/OrderCenter/Create');
    }

    /**
     * Store new order (admin-created)
     */
    public function store(Request $request)
    {
        // TODO: Implement admin order creation logic
        return redirect()->route('admin.orders.index')
            ->with('success', 'Order created successfully');
    }

    /**
     * Get order action history
     */
    private function getOrderActionHistory(Order $order)
    {
        $history = [];

        // Add creation
        $history[] = [
            'id' => 1,
            'action' => 'created order',
            'actor' => $order->provider ? $order->provider->first_name . ' ' . $order->provider->last_name : 'System',
            'timestamp' => $order->created_at,
            'notes' => null,
        ];

        // Add status changes based on timestamps
        if ($order->ivr_generated_at) {
            $history[] = [
                'id' => 2,
                'action' => 'generated IVR',
                'actor' => 'Admin',
                'timestamp' => $order->ivr_generated_at,
                'notes' => $order->ivr_skip_reason ? 'IVR skipped: ' . $order->ivr_skip_reason : null,
            ];
        }

        if ($order->ivr_sent_at) {
            $history[] = [
                'id' => 3,
                'action' => 'sent IVR to manufacturer',
                'actor' => 'System',
                'timestamp' => $order->ivr_sent_at,
                'notes' => null,
            ];
        }

        if ($order->ivr_confirmed_at) {
            $history[] = [
                'id' => 4,
                'action' => 'received IVR confirmation',
                'actor' => 'Admin',
                'timestamp' => $order->ivr_confirmed_at,
                'notes' => null,
            ];
        }

        if ($order->approved_at) {
            $history[] = [
                'id' => 5,
                'action' => 'approved order',
                'actor' => 'Admin',
                'timestamp' => $order->approved_at,
                'notes' => $order->approval_notes,
            ];
        }

        if ($order->sent_back_at) {
            $history[] = [
                'id' => 6,
                'action' => 'sent order back to provider',
                'actor' => 'Admin',
                'timestamp' => $order->sent_back_at,
                'notes' => $order->send_back_notes,
            ];
        }

        if ($order->denied_at) {
            $history[] = [
                'id' => 7,
                'action' => 'denied order',
                'actor' => 'Admin',
                'timestamp' => $order->denied_at,
                'notes' => $order->denial_reason,
            ];
        }

        if ($order->submitted_to_manufacturer_at) {
            $history[] = [
                'id' => 8,
                'action' => 'submitted to manufacturer',
                'actor' => 'System',
                'timestamp' => $order->submitted_to_manufacturer_at,
                'notes' => null,
            ];
        }

        // Sort by timestamp descending
        usort($history, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $history;
    }

    /**
     * Log order action (TODO: Implement proper audit logging)
     */
    private function logOrderAction(Order $order, string $action, string $description)
    {
        Log::info('Order action', [
            'order_id' => $order->id,
            'action' => $action,
            'description' => $description,
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ]);
    }
}
