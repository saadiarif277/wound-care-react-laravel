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
        $query = ProductRequest::with(['provider', 'facility', 'products.manufacturer']);

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
            // Get product information from clinical summary or products relationship
            $productName = 'Unknown Product';
            $manufacturerName = 'Unknown Manufacturer';

            // Try to get product info from clinical summary first
            if ($order->clinical_summary && isset($order->clinical_summary['product_selection']['selected_products'])) {
                $selectedProducts = $order->clinical_summary['product_selection']['selected_products'];
                if (!empty($selectedProducts)) {
                    $productId = $selectedProducts[0]['product_id'] ?? null;
                    if ($productId) {
                        $product = \App\Models\Order\Product::with('manufacturer')->find($productId);
                        if ($product) {
                            $productName = $product->name;
                            $manufacturerName = $product->manufacturer()->first()?->name ?? $product->manufacturer ?? 'Unknown Manufacturer';
                        }
                    }
                }
            }

            // Fallback to products relationship if clinical summary doesn't have product info
            if ($productName === 'Unknown Product') {
                $product = $order->products()->with('manufacturer')->first();
                if ($product) {
                    $productName = $product->name;
                    $manufacturerName = $product->manufacturer()->first()?->name ?? $product->manufacturer ?? 'Unknown Manufacturer';
                }
            }

            return [
                'id' => $order->id,
                'order_number' => $order->request_number,
                'patient_name' => $order->patient_display_id ?? 'Unknown Patient',
                'patient_display_id' => $order->patient_display_id,
                'provider_name' => $order->provider->name ?? 'Unknown Provider',
                'facility_name' => $order->facility->name ?? 'Unknown Facility',
                'manufacturer_name' => $manufacturerName,
                'product_name' => $productName,
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
    // public function show($orderId)
    // {
    //     $order = ProductRequest::with(['provider', 'facility', 'products', 'episode'])
    //         ->findOrFail($orderId);

    //     // Get clinical summary data which contains all submitted form data
    //     $clinicalSummary = $order->clinical_summary ?? [];



    //     // Get orchestrator data from episode metadata if available
    //     $orchestratorData = [];
    //     if ($order->episode && $order->episode->metadata) {
    //         $orchestratorData = $this->extractOrchestratorData($order->episode);
    //     }

    //     // Get patient data from FHIR if available
    //     $patientData = $this->getPatientDataFromFhir($order);

    //     // Get product information from clinical summary
    //     $productData = $this->getProductDataFromClinicalSummary($order, $clinicalSummary);

    //     // Transform order data for display
    //     $orderData = [
    //         'id' => $order->id,
    //         'order_number' => $order->request_number,
    //         'patient_name' => $this->formatPatientName($clinicalSummary) ?: $order->patient_display_id ?? 'Unknown Patient',
    //         'patient_display_id' => $order->patient_display_id,
    //         'provider_name' => $clinicalSummary['provider']['name'] ?: ($order->provider ? $order->provider->first_name . ' ' . $order->provider->last_name : 'Unknown Provider'),
    //         'facility_name' => $clinicalSummary['facility']['name'] ?: ($order->facility ? $order->facility->name : 'Unknown Facility'),
    //         'manufacturer_name' => $order->manufacturer ?? 'Unknown Manufacturer',
    //         'product_name' => $order->product_name ?? 'Unknown Product',
    //         'order_status' => $order->order_status,
    //         'ivr_status' => $order->ivr_status ?? 'N/A',
    //         'order_form_status' => $order->order_form_status ?? 'Not Started',
    //         'total_order_value' => (float) ($order->total_order_value ?? 0),
    //         'created_at' => $order->created_at->toISOString(),
    //         'action_required' => $order->order_status === 'pending',
    //         'episode_id' => $order->ivr_episode_id,
    //         'docuseal_submission_id' => $order->episode->docuseal_submission_id ?? null,
    //     ];

    //     // Extract detailed order data with enhanced mappings from clinical summary
    //     $detailedOrderData = [
    //         'patient' => [
    //             'name' => $this->formatPatientName($clinicalSummary),
    //             'firstName' => $clinicalSummary['patient']['first_name'] ?? null,
    //             'lastName' => $clinicalSummary['patient']['last_name'] ?? null,
    //             'dob' => $clinicalSummary['patient']['date_of_birth'] ?? null,
    //             'gender' => $clinicalSummary['patient']['gender'] ?? null,
    //             'phone' => $clinicalSummary['patient']['phone'] ?? null,
    //             'email' => $clinicalSummary['patient']['email'] ?? null,
    //             'address' => $this->formatPatientAddress($clinicalSummary),
    //             'memberId' => $clinicalSummary['patient']['member_id'] ?? null,
    //             'isSubscriber' => $clinicalSummary['patient']['is_subscriber'] ?? true,
    //             'insurance' => [
    //                 'primary' => $this->formatInsuranceInfo($clinicalSummary, 'primary'),
    //                 'secondary' => $this->formatInsuranceInfo($clinicalSummary, 'secondary'),
    //             ],
    //         ],
    //         'insurance' => [
    //             'primary' => $this->formatInsuranceInfo($clinicalSummary, 'primary'),
    //             'secondary' => $this->formatInsuranceInfo($clinicalSummary, 'secondary'),
    //             'primaryPlanType' => $clinicalSummary['insurance']['primary_plan_type'] ?? null,
    //             'secondaryPlanType' => $clinicalSummary['insurance']['secondary_plan_type'] ?? null,
    //         ],
    //         'product' => [
    //             'name' => $productData['name'] ?? 'Unknown Product',
    //             'code' => $productData['code'] ?? 'N/A',
    //             'quantity' => $productData['quantity'] ?? 1,
    //             'size' => $productData['size'] ?? 'N/A',
    //             'category' => $productData['category'] ?? 'N/A',
    //             'manufacturer' => $productData['manufacturer'] ?? 'Unknown Manufacturer',
    //             'manufacturerId' => $clinicalSummary['product_selection']['manufacturer_id'] ?? null,
    //             'shippingInfo' => [
    //                 'speed' => $clinicalSummary['order_preferences']['shipping_speed'] ?? 'Standard',
    //                 'address' => $clinicalSummary['order_preferences']['delivery_instructions'] ?? 'N/A',
    //             ],
    //         ],
    //         'forms' => [
    //             'ivrStatus' => $order->ivr_status ?? 'not_started',
    //             'docusealStatus' => $order->episode?->docuseal_submission_id ? 'completed' : 'not_started',
    //             'consent' => $clinicalSummary['attestations']['information_accurate'] ?? false,
    //             'assignmentOfBenefits' => $clinicalSummary['attestations']['maintain_documentation'] ?? false,
    //             'medicalNecessity' => $clinicalSummary['attestations']['medical_necessity_established'] ?? false,
    //         ],
    //         'clinical' => [
    //             'woundType' => $clinicalSummary['clinical']['wound_type'] ?? 'N/A',
    //             'woundLocation' => $clinicalSummary['clinical']['wound_location'] ?? 'N/A',
    //             'location' => $clinicalSummary['clinical']['wound_location'] ?? 'N/A',
    //             'size' => $this->formatWoundSize($clinicalSummary),
    //             'depth' => $clinicalSummary['clinical']['wound_size_depth'] ?? null,
    //             'cptCodes' => $this->formatCptCodes($clinicalSummary),
    //             'diagnosisCodes' => $clinicalSummary['clinical']['diagnosis_codes'] ?? [],
    //             'primaryDiagnosis' => isset($clinicalSummary['clinical']['diagnosis_codes'][0]) ? $clinicalSummary['clinical']['diagnosis_codes'][0] : 'N/A',
    //             'clinicalNotes' => $clinicalSummary['clinical']['clinical_notes'] ?? null,
    //             'placeOfService' => $clinicalSummary['order_preferences']['place_of_service'] ?? 'N/A',
    //             'serviceDate' => $clinicalSummary['order_preferences']['expected_service_date'] ?? null,
    //             'failedConservativeTreatment' => $clinicalSummary['attestations']['failed_conservative_treatment'] ?? false,
    //         ],
    //         'provider' => [
    //             'name' => $clinicalSummary['provider']['name'] ?: ($order->provider ? $order->provider->first_name . ' ' . $order->provider->last_name : 'Unknown Provider'),
    //             'npi' => $clinicalSummary['provider']['npi'] ?? ($order->provider ? $order->provider->npi_number : 'N/A'),
    //             'email' => $clinicalSummary['provider']['email'] ?? ($order->provider ? $order->provider->email : null),
    //             'facility' => $clinicalSummary['facility']['name'] ?: ($order->facility ? $order->facility->name : 'Unknown Facility'),
    //         ],
    //         'facility' => [
    //             'name' => $clinicalSummary['facility']['name'] ?: ($order->facility ? $order->facility->name : null),
    //             'address' => $this->formatFacilityAddress($clinicalSummary) ?: ($order->facility ? $this->formatFacilityAddressFromModel($order->facility) : null),
    //             'phone' => $clinicalSummary['facility']['phone'] ?? ($order->facility ? $order->facility->phone : null),
    //         ],
    //         'submission' => [
    //             'status' => $order->order_status,
    //             'submittedAt' => $order->submitted_at?->format('Y-m-d H:i:s'),
    //             'informationAccurate' => $clinicalSummary['attestations']['information_accurate'] ?? false,
    //             'documentationMaintained' => $clinicalSummary['attestations']['maintain_documentation'] ?? false,
    //             'authorizePriorAuth' => $clinicalSummary['attestations']['authorize_prior_auth'] ?? false,
    //         ],
    //         'orderPreferences' => [
    //             'expectedServiceDate' => $clinicalSummary['order_preferences']['expected_service_date'] ?? null,
    //             'shippingSpeed' => $clinicalSummary['order_preferences']['shipping_speed'] ?? 'standard',
    //             'placeOfService' => $clinicalSummary['order_preferences']['place_of_service'] ?? null,
    //             'deliveryInstructions' => $clinicalSummary['order_preferences']['delivery_instructions'] ?? null,
    //         ],
    //         'adminNote' => $clinicalSummary['admin_note'] ?? null,
    //         'adminNoteAddedAt' => $clinicalSummary['admin_note_added_at'] ?? null,
    //         'total_amount' => $order->total_order_value ?? 0,
    //         'documents' => $this->getOrderDocuments($order),
    //     ];

    //     // Get role restrictions and apply financial data filtering
    //     $user = Auth::user();
    //     $userRole = $user->getPrimaryRole()?->slug ?? 'admin';

    //     // Apply role-based financial data restrictions
    //     if ($userRole === 'office-manager') {
    //         // Hide financial data from Office Managers
    //         unset($orderData['total_order_value']);
    //         if (isset($detailedOrderData['product']['pricing'])) {
    //             unset($detailedOrderData['product']['pricing']);
    //         }
    //     }

    //     $roleRestrictions = [
    //         'can_view_financials' => $user->can('view-financials') && $userRole !== 'office-manager',
    //         'can_see_discounts' => $user->can('view-discounts') && $userRole !== 'office-manager',
    //         'can_see_msc_pricing' => $user->can('view-msc-pricing') && $userRole !== 'office-manager',
    //         'can_see_order_totals' => $user->can('view-order-totals') && $userRole !== 'office-manager',
    //         'can_see_commission' => $user->can('view-commission') && $userRole !== 'office-manager',
    //         'pricing_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
    //         'commission_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
    //     ];



    //     // Debug: Log what we're sending to frontend
    //     \Log::info('Backend Debug - Sending to Frontend', [
    //         'order_id' => $orderId,
    //         'orderData_keys' => array_keys($orderData),
    //         'detailedOrderData_keys' => array_keys($detailedOrderData),
    //         'patient_name' => $detailedOrderData['patient']['name'] ?? 'NOT SET',
    //         'provider_name' => $detailedOrderData['provider']['name'] ?? 'NOT SET',
    //         'product_name' => $detailedOrderData['product']['name'] ?? 'NOT SET',
    //     ]);

    //     return Inertia::render('Admin/OrderCenter/OrderDetails', [
    //         'order' => $orderData,
    //         'orderData' => $detailedOrderData,
    //         'can_update_status' => $user->can('update-order-status'),
    //         'can_view_ivr' => $user->can('view-ivr-documents'),
    //         'userRole' => $userRole === 'provider' ? 'Provider' :
    //                     ($userRole === 'office-manager' ? 'OM' : 'Admin'),
    //         'roleRestrictions' => $roleRestrictions,
    //     ]);
    // }

            public function show($orderId)
{
    // Create dedicated log file for debugging
    $logFile = storage_path('logs/order-details-debug.log');
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - OrderCenterController::show called for order_id: {$orderId}\n", FILE_APPEND);

    try {
        $order = ProductRequest::with(['provider', 'facility', 'products', 'episode'])
            ->findOrFail($orderId);

        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order found: {$order->request_number}\n", FILE_APPEND);

        // Get clinical summary data which contains all submitted form data
        $clinicalSummary = $order->clinical_summary ?? [];

        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Clinical summary: " . json_encode($clinicalSummary) . "\n", FILE_APPEND);

        // Log the clinical summary structure
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Clinical summary keys: " . implode(', ', array_keys($clinicalSummary)) . "\n", FILE_APPEND);

        // Get product information from clinical summary
        try {
            $productData = $this->getProductDataFromClinicalSummary($order, $clinicalSummary);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Product data retrieved: " . json_encode($productData) . "\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error getting product data: " . $e->getMessage() . "\n", FILE_APPEND);
            $productData = ['name' => 'Unknown Product', 'code' => 'N/A', 'quantity' => 1, 'size' => 'N/A', 'category' => 'N/A', 'manufacturer' => 'Unknown Manufacturer'];
        }

    // Transform order data for display with all details included
    $orderData = [
        'id' => $order->id,
        'order_number' => $order->request_number,
        'patient_name' => $this->formatPatientName($clinicalSummary) ?: $order->patient_display_id ?? 'Unknown Patient',
        'patient_display_id' => $order->patient_display_id,
        'provider_name' => $clinicalSummary['provider']['name'] ?? ($order->provider ? $order->provider->first_name . ' ' . $order->provider->last_name : 'Unknown Provider'),
        'facility_name' => $clinicalSummary['facility']['name'] ?? ($order->facility ? $order->facility->name : 'Unknown Facility'),
        'manufacturer_name' => $order->getManufacturer() ?? 'Unknown Manufacturer',
        'product_name' => $productData['name'] ?? 'Unknown Product',
        'order_status' => $order->order_status,
        'ivr_status' => $order->ivr_status ?? 'N/A',
        'order_form_status' => $order->order_form_status ?? 'Not Started',
        'total_order_value' => (float) ($order->total_order_value ?? 0),
        'created_at' => $order->created_at->toISOString(),
        'action_required' => $order->order_status === 'pending',
        'episode_id' => $order->ivr_episode_id,
        'docuseal_submission_id' => $order->episode->docuseal_submission_id ?? null,
        'place_of_service' => $order->place_of_service,
        'place_of_service_display' => $order->place_of_service_description,
        'wound_type' => $order->wound_type,
        'wound_type_display' => $order->getWoundTypeDescriptions()[$order->wound_type] ?? $order->wound_type,
        'expected_service_date' => $order->expected_service_date?->toISOString(),

        // Add all detailed data directly to order
        'patient' => [
            'name' => $this->formatPatientName($clinicalSummary),
            'firstName' => $clinicalSummary['patient']['first_name'] ?? null,
            'lastName' => $clinicalSummary['patient']['last_name'] ?? null,
            'dob' => $clinicalSummary['patient']['date_of_birth'] ?? null,
            'gender' => $clinicalSummary['patient']['gender'] ?? null,
            'phone' => $clinicalSummary['patient']['phone'] ?? null,
            'email' => $clinicalSummary['patient']['email'] ?? null,
            'address' => $this->formatPatientAddress($clinicalSummary),
            'memberId' => $clinicalSummary['patient']['member_id'] ?? null,
            'displayId' => $clinicalSummary['patient']['display_id'] ?? null,
            'isSubscriber' => $clinicalSummary['patient']['is_subscriber'] ?? true,
        ],
        'insurance' => [
            'primary' => [
                'name' => $clinicalSummary['insurance']['primary_name'] ?? $order->payer_name_submitted,
                'memberId' => $clinicalSummary['insurance']['primary_member_id'] ?? null,
                'planType' => $clinicalSummary['insurance']['primary_plan_type'] ?? null,
            ],
            'secondary' => [
                'name' => $clinicalSummary['insurance']['secondary_name'] ?? null,
                'memberId' => $clinicalSummary['insurance']['secondary_member_id'] ?? null,
                'planType' => $clinicalSummary['insurance']['secondary_plan_type'] ?? null,
            ],
            'hasSecondary' => $clinicalSummary['insurance']['has_secondary'] ?? false,
        ],
        'product' => [
            'name' => $productData['name'] ?? 'Unknown Product',
            'code' => $productData['code'] ?? 'N/A',
            'quantity' => $productData['quantity'] ?? 1,
            'size' => $productData['size'] ?? 'N/A',
            'category' => $productData['category'] ?? 'N/A',
            'manufacturer' => $productData['manufacturer'] ?? $order->getManufacturer() ?? 'Unknown Manufacturer',
            'manufacturerId' => $clinicalSummary['product_selection']['manufacturer_id'] ?? null,
            'selectedProducts' => $clinicalSummary['product_selection']['selected_products'] ?? [],
            'shippingInfo' => [
                'speed' => $clinicalSummary['order_preferences']['shipping_speed'] ?? 'Standard',
                'instructions' => $clinicalSummary['order_preferences']['delivery_instructions'] ?? null,
            ],
        ],
        'clinical' => [
            'woundType' => $clinicalSummary['clinical']['wound_type'] ?? $order->wound_type,
            'woundLocation' => $clinicalSummary['clinical']['wound_location'] ?? 'N/A',
            'size' => $this->formatWoundSize($clinicalSummary),
            'depth' => $clinicalSummary['clinical']['wound_size_depth'] ?? null,
            'diagnosisCodes' => $clinicalSummary['clinical']['diagnosis_codes'] ?? [],
            'primaryDiagnosis' => $clinicalSummary['clinical']['diagnosis_codes'][0] ?? 'N/A',
            'clinicalNotes' => $clinicalSummary['clinical']['clinical_notes'] ?? null,
            'failedConservativeTreatment' => $clinicalSummary['attestations']['failed_conservative_treatment'] ?? false,
        ],
        'provider' => [
            'id' => $clinicalSummary['provider']['id'] ?? ($order->provider ? $order->provider->id : null),
            'name' => $clinicalSummary['provider']['name'] ?? ($order->provider ? $order->provider->first_name . ' ' . $order->provider->last_name : 'Unknown Provider'),
            'npi' => $clinicalSummary['provider']['npi'] ?? ($order->provider ? $order->provider->npi_number : 'N/A'),
            'email' => $clinicalSummary['provider']['email'] ?? ($order->provider ? $order->provider->email : null),
        ],
        'facility' => [
            'id' => $clinicalSummary['facility']['id'] ?? ($order->facility ? $order->facility->id : null),
            'name' => $clinicalSummary['facility']['name'] ?? ($order->facility ? $order->facility->name : null),
            'address' => $this->formatFacilityAddress($clinicalSummary) ?: ($order->facility ? $this->formatFacilityAddressFromModel($order->facility) : null),
            'phone' => $clinicalSummary['facility']['phone'] ?? ($order->facility ? $order->facility->phone : null),
        ],
        'attestations' => [
            'failedConservativeTreatment' => $clinicalSummary['attestations']['failed_conservative_treatment'] ?? false,
            'informationAccurate' => $clinicalSummary['attestations']['information_accurate'] ?? false,
            'medicalNecessityEstablished' => $clinicalSummary['attestations']['medical_necessity_established'] ?? false,
            'maintainDocumentation' => $clinicalSummary['attestations']['maintain_documentation'] ?? false,
            'authorizePriorAuth' => $clinicalSummary['attestations']['authorize_prior_auth'] ?? false,
        ],
        'documents' => $this->getOrderDocuments($order),
    ];

                    // Log the order data structure
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order data keys: " . implode(', ', array_keys($orderData)) . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Patient name: " . ($orderData['patient']['name'] ?? 'NOT SET') . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Product name: " . ($orderData['product']['name'] ?? 'NOT SET') . "\n", FILE_APPEND);

    // Get role restrictions and apply financial data filtering
    $user = Auth::user();
    $userRole = $user->getPrimaryRole()?->slug ?? 'admin';

    // Apply role-based financial data restrictions
    if ($userRole === 'office-manager') {
        // Hide financial data from Office Managers
        unset($orderData['total_order_value']);
    }

    $roleRestrictions = [
        'can_view_financials' => $user->can('view-financials') && $userRole !== 'office-manager',
        'can_see_discounts' => $user->can('view-discounts') && $userRole !== 'office-manager',
        'can_see_msc_pricing' => $user->can('view-msc-pricing') && $userRole !== 'office-manager',
        'can_see_order_totals' => $user->can('view-order-totals') && $userRole !== 'office-manager',
        'can_see_commission' => $user->can('view-commission') && $userRole !== 'office-manager',
        'pricing_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
        'commission_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
    ];

    // Log what we're sending to frontend
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Sending to frontend - order keys: " . implode(', ', array_keys($orderData)) . "\n", FILE_APPEND);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Patient name being sent: " . ($orderData['patient']['name'] ?? 'NOT SET') . "\n", FILE_APPEND);

    return Inertia::render('Admin/OrderCenter/OrderDetails', [
        'order' => $orderData,
        'can_update_status' => $user->can('update-order-status'),
        'can_view_ivr' => $user->can('view-ivr-documents'),
        'userRole' => $userRole === 'provider' ? 'Provider' :
                    ($userRole === 'office-manager' ? 'OM' : 'Admin'),
        'roleRestrictions' => $roleRestrictions,
    ]);
    } catch (Exception $e) {
        \Log::error('Error in OrderCenterController::show', [
            'order_id' => $orderId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return Inertia::render('Admin/OrderCenter/OrderDetails', [
            'order' => ['id' => $orderId, 'order_number' => 'Error'],
            'orderData' => null,
            'can_update_status' => false,
            'can_view_ivr' => false,
            'userRole' => 'Admin',
            'roleRestrictions' => [],
            'error' => 'Failed to load order details: ' . $e->getMessage()
        ]);
    }
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

        // Handle notification documents
        $notificationDocuments = [];
        if ($sendNotification && $request->hasFile('notification_documents')) {
            $notificationDocuments = $request->file('notification_documents');
        }

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
                        $notes,
                        $notificationDocuments
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

    /**
     * Get patient data from FHIR or fallback sources
     */
    private function getPatientDataFromFhir($order): array
    {
        if (!$order->patient_fhir_id) {
            return [];
        }

        try {
            $fhirService = app(\App\Services\FhirService::class);
            $patient = $fhirService->getPatientById($order->patient_fhir_id);

            if ($patient) {
                return [
                    'dob' => $patient['birthDate'] ?? null,
                    'gender' => $patient['gender'] ?? null,
                    'phone' => isset($patient['telecom']) ?
                        collect($patient['telecom'])->where('system', 'phone')->first()['value'] ?? null : null,
                    'address' => isset($patient['address'][0]) ?
                        implode(', ', array_filter([
                            $patient['address'][0]['line'][0] ?? '',
                            $patient['address'][0]['city'] ?? '',
                            $patient['address'][0]['state'] ?? '',
                            $patient['address'][0]['postalCode'] ?? ''
                        ])) : null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get patient FHIR data', [
                'order_id' => $order->id,
                'patient_fhir_id' => $order->patient_fhir_id,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Get product information from clinical summary
     */
    private function getProductDataFromClinicalSummary($order, array $clinicalSummary): array
    {
        $productId = null;
        $quantity = 1;

        // Get product ID and quantity from selected_products array
        if (isset($clinicalSummary['product_selection']['selected_products']) &&
            is_array($clinicalSummary['product_selection']['selected_products']) &&
            !empty($clinicalSummary['product_selection']['selected_products'])) {

            $selectedProduct = $clinicalSummary['product_selection']['selected_products'][0];
            $productId = $selectedProduct['product_id'] ?? null;
            $quantity = $selectedProduct['quantity'] ?? 1;
        }

        $product = null;
        if ($productId) {
            $product = \App\Models\Order\Product::with('manufacturer')->find($productId);
        }

        return [
            'name' => $product ? $product->name : 'Unknown Product',
            'code' => $product ? ($product->q_code ?? $product->sku ?? $product->code) : 'N/A',
            'quantity' => $quantity,
            'size' => $product ? ($product->size ?? 'N/A') : 'N/A',
            'category' => $product ? ($product->category ?? 'N/A') : 'N/A',
            'manufacturer' => $product ? ($product->manufacturer()->first()?->name ?? $product->manufacturer ?? 'Unknown Manufacturer') : 'Unknown Manufacturer',
            'manufacturerId' => $product ? ($product->manufacturer()->first()?->id ?? null) : null,
            'selectedProducts' => $clinicalSummary['product_selection']['selected_products'] ?? [],
            'shippingInfo' => [
                'speed' => $clinicalSummary['order_preferences']['shipping_speed'] ?? 'standard',
                'instructions' => $clinicalSummary['order_preferences']['delivery_instructions'] ?? null,
            ],
        ];
    }

    /**
     * Format patient name from clinical summary
     */
    private function formatPatientName(array $clinicalSummary): string
    {
        return trim(($clinicalSummary['patient']['first_name'] ?? '') . ' ' . ($clinicalSummary['patient']['last_name'] ?? ''));
    }

    /**
     * Format patient address from clinical summary
     */
    private function formatPatientAddress(array $clinicalSummary): ?string
    {
        if (!isset($clinicalSummary['patient']['address_line1']) &&
            !isset($clinicalSummary['patient']['city'])) {
            return null;
        }

        return implode(', ', array_filter([
            $clinicalSummary['patient']['address_line1'] ?? '',
            $clinicalSummary['patient']['address_line2'] ?? '',
            $clinicalSummary['patient']['city'] ?? '',
            $clinicalSummary['patient']['state'] ?? '',
            $clinicalSummary['patient']['zip'] ?? ''
        ]));
    }

    /**
     * Format facility address from clinical summary
     */
    private function formatFacilityAddress(array $clinicalSummary): ?string
    {
        if (!isset($clinicalSummary['facility']['address'])) {
            return null;
        }

        return implode(', ', array_filter([
            $clinicalSummary['facility']['address'] ?? '',
            $clinicalSummary['facility']['city'] ?? '',
            $clinicalSummary['facility']['state'] ?? '',
            $clinicalSummary['facility']['zip'] ?? ''
        ]));
    }

    /**
     * Format facility address from facility model
     */
    private function formatFacilityAddressFromModel($facility): ?string
    {
        if (!$facility) {
            return null;
        }

        return implode(', ', array_filter([
            $facility->address ?? '',
            $facility->city ?? '',
            $facility->state ?? '',
            $facility->zip_code ?? ''
        ]));
    }

    /**
     * Format address from clinical summary
     */
    private function formatAddress(array $clinicalSummary): ?string
    {
        $address = null;

        // Try different address locations in clinical summary
        $addressSources = [
            $clinicalSummary['patient']['address'] ?? null,
            $clinicalSummary['patientInfo']['address'] ?? null,
            $clinicalSummary['patientInfo'] ?? null // Sometimes address fields are directly in patientInfo
        ];

        foreach ($addressSources as $source) {
            if (!$source) continue;

            if (is_array($source)) {
                // Handle structured address
                if (isset($source['street']) || isset($source['city'])) {
                    $parts = array_filter([
                        $source['street'] ?? $source['address'] ?? '',
                        $source['city'] ?? '',
                        $source['state'] ?? '',
                        $source['zipCode'] ?? $source['zip'] ?? ''
                    ]);
                    if (!empty($parts)) {
                        $address = implode(', ', $parts);
                        break;
                    }
                }

                // Handle flat address fields
                $flatParts = array_filter([
                    $source['address'] ?? '',
                    $source['city'] ?? '',
                    $source['state'] ?? '',
                    $source['zip'] ?? $source['zipCode'] ?? ''
                ]);
                if (!empty($flatParts)) {
                    $address = implode(', ', $flatParts);
                    break;
                }
            }
        }

        return $address;
    }

    /**
     * Format insurance information
     */
    private function formatInsuranceInfo(array $clinicalSummary, string $type): string
    {
        $insurance = $clinicalSummary['insurance'] ?? [];

        if ($type === 'primary') {
            $name = $insurance['primary_name'] ?? '';
            $memberId = $insurance['primary_member_id'] ?? '';

            if ($name && $memberId) {
                return "{$name} - {$memberId}";
            } elseif ($name) {
                return $name;
            }
        } elseif ($type === 'secondary') {
            $hasSecondary = $insurance['has_secondary'] ?? false;
            if (!$hasSecondary) {
                return 'N/A';
            }

            $name = $insurance['secondary_name'] ?? '';
            $memberId = $insurance['secondary_member_id'] ?? '';

            if ($name && $memberId) {
                return "{$name} - {$memberId}";
            } elseif ($name) {
                return $name;
            }
        }

        return 'N/A';
    }

    /**
     * Format wound size from clinical summary
     */
    private function formatWoundSize(array $clinicalSummary): string
    {
        $length = $clinicalSummary['clinical']['wound_size_length'] ?? null;
        $width = $clinicalSummary['clinical']['wound_size_width'] ?? null;
        $depth = $clinicalSummary['clinical']['wound_size_depth'] ?? null;

        if ($length && $width) {
            $size = "{$length} x {$width}";
            if ($depth) {
                $size .= " x {$depth}";
            }
            return $size . ' cm';
        }

        return 'N/A';
    }

    /**
     * Format CPT codes from clinical summary
     */
    private function formatCptCodes(array $clinicalSummary): string
    {
        $cptData = $clinicalSummary['clinical']['application_cpt_codes'] ?? null;

        if (!$cptData) {
            return 'N/A';
        }

        if (is_array($cptData)) {
            return implode(', ', array_filter($cptData));
        } elseif (is_string($cptData)) {
            return $cptData;
        }

        return 'N/A';
    }

    /**
     * Get order documents
     */
    private function getOrderDocuments($order): array
    {
        $documents = [];

        // Add episode documents if available
        if ($order->episode && isset($order->episode->metadata['documents'])) {
            $documents = array_merge($documents, $order->episode->metadata['documents'] ?? []);
        }

        // Add order-specific documents if any exist
        // This would be expanded based on your document storage system

        return $documents;
    }

    /**
     * Extract orchestrator data from episode metadata
     */
    private function extractOrchestratorData(PatientManufacturerIVREpisode $episode): array
    {
        $metadata = $episode->metadata ?? [];
        $orchestratorData = [];

        // Extract patient data
        if (isset($metadata['patient_data'])) {
            $patientData = $metadata['patient_data'];
            $orchestratorData['patient_first_name'] = $patientData['first_name'] ?? '';
            $orchestratorData['patient_last_name'] = $patientData['last_name'] ?? '';
            $orchestratorData['patient_name'] = trim(($patientData['first_name'] ?? '') . ' ' . ($patientData['last_name'] ?? ''));
            $orchestratorData['patient_dob'] = $patientData['dob'] ?? '';
            $orchestratorData['patient_gender'] = $patientData['gender'] ?? '';
            $orchestratorData['patient_phone'] = $patientData['phone'] ?? '';
            $orchestratorData['patient_email'] = $patientData['email'] ?? '';
            $orchestratorData['patient_address'] = $this->formatAddressFromMetadata($patientData);
        }

        // Extract provider data
        if (isset($metadata['provider_data'])) {
            $providerData = $metadata['provider_data'];
            $orchestratorData['provider_name'] = $providerData['name'] ?? '';
            $orchestratorData['provider_npi'] = $providerData['npi'] ?? '';
            $orchestratorData['provider_email'] = $providerData['email'] ?? '';
            $orchestratorData['provider_phone'] = $providerData['phone'] ?? '';
            $orchestratorData['provider_specialty'] = $providerData['specialty'] ?? '';
        }

        // Extract facility data
        if (isset($metadata['facility_data'])) {
            $facilityData = $metadata['facility_data'];
            $orchestratorData['facility_name'] = $facilityData['name'] ?? '';
            $orchestratorData['facility_npi'] = $facilityData['npi'] ?? '';
            $orchestratorData['facility_address'] = $this->formatAddressFromMetadata($facilityData);
        }

        // Extract organization data
        if (isset($metadata['organization_data'])) {
            $organizationData = $metadata['organization_data'];
            $orchestratorData['organization_name'] = $organizationData['name'] ?? '';
        }

        // Extract clinical data
        if (isset($metadata['clinical_data'])) {
            $clinicalData = $metadata['clinical_data'];
            $orchestratorData['wound_type'] = $clinicalData['wound_type'] ?? '';
            $orchestratorData['wound_location'] = $clinicalData['wound_location'] ?? '';
            $orchestratorData['wound_size_length'] = $clinicalData['wound_length'] ?? $clinicalData['wound_size_length'] ?? '';
            $orchestratorData['wound_size_width'] = $clinicalData['wound_width'] ?? $clinicalData['wound_size_width'] ?? '';
            $orchestratorData['wound_size_depth'] = $clinicalData['wound_depth'] ?? $clinicalData['wound_size_depth'] ?? '';
            $orchestratorData['wound_duration_weeks'] = $clinicalData['wound_duration_weeks'] ?? '';
            $orchestratorData['graft_size_requested'] = $clinicalData['graft_size_requested'] ?? '';
            $orchestratorData['primary_diagnosis_code'] = $clinicalData['icd10_code_1'] ?? $clinicalData['primary_diagnosis'] ?? '';
            $orchestratorData['icd10_codes'] = $this->extractDiagnosisCodes($clinicalData);
            $orchestratorData['cpt_codes'] = $this->extractCptCodes($clinicalData);
            $orchestratorData['failed_conservative_treatment'] = $clinicalData['failed_conservative_treatment'] ?? false;
        }

        // Extract insurance data
        if (isset($metadata['insurance_data'])) {
            $insuranceData = $metadata['insurance_data'];

            // Handle primary insurance
            if (isset($insuranceData['primary'])) {
                $primary = $insuranceData['primary'];
                $orchestratorData['primary_insurance_name'] = $primary['name'] ?? '';
                $orchestratorData['primary_member_id'] = $primary['memberId'] ?? '';
                $orchestratorData['primary_group_number'] = $primary['groupNumber'] ?? '';
                $orchestratorData['primary_plan_type'] = $primary['planType'] ?? '';
            }

            // Handle secondary insurance
            if (isset($insuranceData['secondary']) && !empty($insuranceData['secondary']['name'])) {
                $secondary = $insuranceData['secondary'];
                $orchestratorData['secondary_insurance_name'] = $secondary['name'] ?? '';
                $orchestratorData['secondary_member_id'] = $secondary['memberId'] ?? '';
                $orchestratorData['secondary_group_number'] = $secondary['groupNumber'] ?? '';
            }
        }

        // Extract order details
        if (isset($metadata['order_details'])) {
            $orderDetails = $metadata['order_details'];
            $orchestratorData['place_of_service'] = $orderDetails['place_of_service'] ?? '';
            $orchestratorData['shipping_speed'] = $orderDetails['shipping_speed'] ?? '';
            $orchestratorData['shipping_address'] = $orderDetails['shipping_address'] ?? '';
        }

        return $orchestratorData;
    }

    /**
     * Format address from metadata
     */
    private function formatAddressFromMetadata(array $data): ?string
    {
        $parts = array_filter([
            $data['address'] ?? $data['address_line1'] ?? '',
            $data['address_line2'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['zip_code'] ?? $data['zip'] ?? ''
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Extract diagnosis codes from clinical data
     */
    private function extractDiagnosisCodes(array $clinicalData): array
    {
        $codes = [];

        // Check for numbered ICD10 codes
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($clinicalData["icd10_code_{$i}"])) {
                $codes[] = $clinicalData["icd10_code_{$i}"];
            }
        }

        // Check for array format
        if (isset($clinicalData['icd10_codes']) && is_array($clinicalData['icd10_codes'])) {
            $codes = array_merge($codes, $clinicalData['icd10_codes']);
        }

        // Check for diagnosis codes
        if (isset($clinicalData['diagnosis_codes']) && is_array($clinicalData['diagnosis_codes'])) {
            $codes = array_merge($codes, $clinicalData['diagnosis_codes']);
        }

        return array_unique(array_filter($codes));
    }

    /**
     * Extract CPT codes from clinical data
     */
    private function extractCptCodes(array $clinicalData): array
    {
        $codes = [];

        // Check for numbered CPT codes
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($clinicalData["cpt_code_{$i}"])) {
                $codes[] = $clinicalData["cpt_code_{$i}"];
            }
        }

        // Check for array format
        if (isset($clinicalData['cpt_codes']) && is_array($clinicalData['cpt_codes'])) {
            $codes = array_merge($codes, $clinicalData['cpt_codes']);
        }

        // Check for application CPT codes
        if (isset($clinicalData['application_cpt_codes']) && is_array($clinicalData['application_cpt_codes'])) {
            $codes = array_merge($codes, $clinicalData['application_cpt_codes']);
        }

        return array_unique(array_filter($codes));
    }
}
