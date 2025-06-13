<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Users\Provider\ProviderProfile;
use App\Models\Users\Organization\Organization;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Carbon\Carbon;

class ProviderManagementController extends Controller
{
    /**
     * Display a listing of providers with financial summary
     */
    public function index(Request $request)
    {
        $query = User::whereHas('roles', function ($query) {
                $query->where('slug', 'provider');
            });

        // Add financial summary subquery
        $query->addSelect([
            'total_outstanding' => ProductRequest::selectRaw('COALESCE(SUM(total_order_value), 0)')
                ->whereColumn('provider_id', 'users.id')
                ->where('order_status', '!=', 'approved'),
            'past_due_amount' => ProductRequest::selectRaw('COALESCE(SUM(total_order_value), 0)')
                ->whereColumn('provider_id', 'users.id')
                ->where('order_status', '!=', 'approved')
                ->where('created_at', '<', now()->subDays(60)),
            'days_past_due' => ProductRequest::selectRaw('COALESCE(MAX(DATEDIFF(NOW(), created_at)), 0)')
                ->whereColumn('provider_id', 'users.id')
                ->where('order_status', '!=', 'approved')
                ->where('created_at', '<', now()->subDays(60)),
            'last_payment_date' => DB::table('payments')
                ->selectRaw('MAX(payment_date)')
                ->whereColumn('provider_id', 'users.id')
                ->where('status', 'posted'),
            'last_payment_amount' => DB::table('payments')
                ->selectRaw('amount')
                ->whereColumn('provider_id', 'users.id')
                ->where('status', 'posted')
                ->orderBy('payment_date', 'desc')
                ->limit(1)
        ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('npi_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('organization')) {
            $query->where('current_organization_id', $request->input('organization'));
        }

        if ($request->filled('verification_status')) {
            $query->whereHas('providerProfile', function ($q) use ($request) {
                $q->where('verification_status', $request->input('verification_status'));
            });
        }

        if ($request->boolean('has_past_due')) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_requests')
                    ->whereColumn('provider_id', 'users.id')
                    ->where('order_status', '!=', 'approved')
                    ->where('created_at', '<', now()->subDays(60));
            });
        }

        $providers = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        // Transform the data for the frontend
        $providers->through(function ($provider) {
            // Get counts manually since relationships might not exist
            $facilitiesCount = DB::table('facility_user')
                ->where('user_id', $provider->id)
                ->count();

            $activeProductsCount = DB::table('provider_products')
                ->where('user_id', $provider->id)
                ->where('onboarding_status', 'active')
                ->count();

            $totalOrdersCount = ProductRequest::where('provider_id', $provider->id)->count();
            $pendingOrdersCount = ProductRequest::where('provider_id', $provider->id)
                ->whereIn('order_status', ['pending_ivr', 'ivr_sent', 'ivr_confirmed'])
                ->count();

            // Get provider profile if table exists
            $providerProfile = null;
            if (Schema::hasTable('provider_profiles')) {
                $providerProfile = ProviderProfile::where('provider_id', $provider->id)->first();
            }

            // Get organization - either from current_organization_id or through facilities
            $organization = null;
            if ($provider->current_organization_id) {
                $organization = Organization::find($provider->current_organization_id);
            } else {
                // Try to get organization through facilities
                $facilityOrg = DB::table('facility_user')
                    ->join('facilities', 'facility_user.facility_id', '=', 'facilities.id')
                    ->join('organizations', 'facilities.organization_id', '=', 'organizations.id')
                    ->where('facility_user.user_id', $provider->id)
                    ->select('organizations.*')
                    ->first();
                if ($facilityOrg) {
                    $organization = Organization::hydrate([$facilityOrg])->first();
                }
            }

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'email' => $provider->email,
                'npi_number' => $provider->npi_number,
                'phone' => $provider->phone,
                'profile' => [
                    'verification_status' => $providerProfile->verification_status ?? ($provider->npi_number ? 'verified' : 'pending'),
                    'profile_completion_percentage' => $this->calculateProfileCompletion($provider, $providerProfile),
                ],
                'current_organization' => $organization,
                'facilities_count' => $facilitiesCount,
                'active_products_count' => $activeProductsCount,
                'total_orders_count' => $totalOrdersCount,
                'pending_orders_count' => $pendingOrdersCount,
                'financial_summary' => [
                    'total_outstanding' => (float) ($provider->total_outstanding ?? 0),
                    'past_due_amount' => (float) ($provider->past_due_amount ?? 0),
                    'days_past_due' => (int) ($provider->days_past_due ?? 0),
                    'last_payment_date' => $provider->last_payment_date,
                    'last_payment_amount' => (float) ($provider->last_payment_amount ?? 0),
                ],
                'created_at' => $provider->created_at,
                'last_activity_at' => $provider->updated_at,
            ];
        });

        // Get summary statistics
        $summary = [
            'total_providers' => User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })->count(),
            'verified_providers' => Schema::hasTable('provider_profiles')
                ? ProviderProfile::where('verification_status', 'verified')->count()
                : 0,
            'providers_with_past_due' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('product_requests')
                        ->whereColumn('provider_id', 'users.id')
                        ->where('order_status', '!=', 'approved')
                        ->where('created_at', '<', now()->subDays(60));
                })->count(),
            'total_outstanding' => ProductRequest::where('order_status', '!=', 'approved')
                ->sum('total_order_value'),
            'total_past_due' => ProductRequest::where('order_status', '!=', 'approved')
                ->where('created_at', '<', now()->subDays(60))
                ->sum('total_order_value'),
        ];

        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Providers/Index', [
            'providers' => $providers,
            'filters' => $request->only(['search', 'organization', 'verification_status', 'has_past_due']),
            'organizations' => $organizations,
            'summary' => $summary,
        ]);
    }

    /**
     * Display the specified provider's detailed profile
     */
    public function show(Request $request, User $provider)
    {
        // Ensure the user is a provider
        if (!$provider->isProvider()) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Selected user is not a provider.');
        }

        // Get organization - either from current_organization_id or through facilities
        $organization = null;
        if ($provider->current_organization_id) {
            $provider->load(['currentOrganization']);
            $organization = $provider->currentOrganization;
        } else {
            // Try to get organization through facilities
            $facilityOrg = DB::table('facility_user')
                ->join('facilities', 'facility_user.facility_id', '=', 'facilities.id')
                ->join('organizations', 'facilities.organization_id', '=', 'organizations.id')
                ->where('facility_user.user_id', $provider->id)
                ->select('organizations.*')
                ->first();
            if ($facilityOrg) {
                $organization = Organization::hydrate([$facilityOrg])->first();
            }
        }

        // Get provider profile if table exists
        $providerProfile = null;
        if (Schema::hasTable('provider_profiles')) {
            $providerProfile = ProviderProfile::where('provider_id', $provider->id)->first();
        }

        // Get credentials if table exists
        $credentials = collect();
        if (Schema::hasTable('provider_credentials')) {
            $credentials = DB::table('provider_credentials')
                ->where('provider_id', $provider->id)
                ->orderBy('expiration_date')
                ->get();
        }

        // Add NPI and DEA from User model as credentials
        $userCredentials = collect();

        // Add NPI if present
        if ($provider->npi_number) {
            $userCredentials->push((object)[
                'id' => 'npi-' . $provider->id,
                'credential_type' => 'npi_number',
                'credential_name' => 'NPI Number',
                'credential_number' => $provider->npi_number,
                'expiration_date' => null,
                'verification_status' => 'verified',
                'is_primary' => true,
            ]);
        }

        // Add DEA if present
        if ($provider->dea_number) {
            $userCredentials->push((object)[
                'id' => 'dea-' . $provider->id,
                'credential_type' => 'dea_registration',
                'credential_name' => 'DEA Registration',
                'credential_number' => $provider->dea_number,
                'expiration_date' => null,
                'verification_status' => 'verified',
                'is_primary' => true,
            ]);
        }

        // Add Medical License if present
        if ($provider->license_number) {
            $userCredentials->push((object)[
                'id' => 'license-' . $provider->id,
                'credential_type' => 'medical_license',
                'credential_name' => 'Medical License',
                'credential_number' => $provider->license_number,
                'expiration_date' => $provider->license_expiry,
                'verification_status' => 'verified',
                'is_primary' => true,
                'issuing_state' => $provider->license_state,
            ]);
        }

        // Merge credentials from both sources
        $allCredentials = $userCredentials->merge($credentials);

        // Get facilities
        $facilities = DB::table('facilities')
            ->join('facility_user', 'facilities.id', '=', 'facility_user.facility_id')
            ->where('facility_user.user_id', $provider->id)
            ->select('facilities.*')
            ->get();

        // Get products
        $products = DB::table('msc_products')
            ->join('provider_products', 'msc_products.id', '=', 'provider_products.product_id')
            ->where('provider_products.user_id', $provider->id)
            ->select('msc_products.*',
                'provider_products.onboarded_at',
                'provider_products.onboarding_status',
                'provider_products.expiration_date as product_expiration_date',
                'provider_products.notes as product_notes')
            ->orderBy('msc_products.name')
            ->get();

        // Get financial summary with aging report
        $financialSummary = $this->getProviderFinancialSummary($provider->id);

        // Get recent orders
        $recentOrders = ProductRequest::where('provider_id', $provider->id)
            ->with(['products'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($order) {
                $daysOutstanding = null;
                if ($order->order_status !== 'approved') {
                    $daysOutstanding = Carbon::parse($order->created_at)->diffInDays(now());
                }

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?? $order->request_number,
                    'date' => $order->created_at,
                    'status' => $order->order_status,
                    'total_amount' => $order->total_order_value ?? 0,
                    'items_count' => $order->products->count(),
                    'payment_status' => $order->order_status === 'approved' ? 'paid' : 'pending',
                    'days_outstanding' => $daysOutstanding,
                ];
            });

        // Get payment history
        $paymentHistory = DB::table('payments')
            ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
            ->leftJoin('product_requests', 'payments.order_id', '=', 'product_requests.id')
            ->where('payments.provider_id', $provider->id)
            ->where('payments.status', 'posted')
            ->select('payments.*',
                DB::raw('COALESCE(orders.order_number, product_requests.request_number) as order_number'))
            ->orderBy('payments.payment_date', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'date' => $payment->payment_date,
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method,
                    'reference' => $payment->reference_number,
                    'order_number' => $payment->order_number,
                    'paid_to' => $payment->paid_to ?? 'msc',
                    'posted_by' => User::find($payment->posted_by_user_id)->name ?? 'System',
                ];
            });

        // Get activity log if table exists
        $activityLog = collect();
        if (Schema::hasTable('activity_logs')) {
            $activityLog = DB::table('activity_logs')
                ->where('subject_type', User::class)
                ->where('subject_id', $provider->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action' => $activity->action,
                        'description' => $activity->description,
                        'user' => User::find($activity->causer_id)->name ?? 'System',
                        'timestamp' => $activity->created_at,
                    ];
                });
        }

        // Get provider statistics
        $stats = [
            'total_orders' => ProductRequest::where('provider_id', $provider->id)->count(),
            'total_revenue' => ProductRequest::where('provider_id', $provider->id)->sum('total_order_value'),
            'avg_order_value' => ProductRequest::where('provider_id', $provider->id)->avg('total_order_value') ?? 0,
            'payment_performance_score' => $this->calculatePaymentPerformanceScore($provider->id),
        ];

        // Transform provider data
        $providerData = [
            'id' => $provider->id,
            'name' => $provider->name,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'npi_number' => $provider->npi_number,
            'address' => $provider->address,
            'profile' => [
                'verification_status' => $providerProfile->verification_status ?? ($provider->npi_number ? 'verified' : 'pending'),
                'profile_completion_percentage' => $this->calculateProfileCompletion($provider, $providerProfile),
                'professional_bio' => $providerProfile->professional_bio ?? null,
                'specializations' => $providerProfile->specializations ?? [],
                'languages_spoken' => $providerProfile->languages_spoken ?? [],
                'last_profile_update' => $providerProfile->last_profile_update ?? null,
            ],
            'current_organization' => $organization,
            'credentials' => $allCredentials->map(function ($credential) {
                return [
                    'id' => $credential->id,
                    'type' => $credential->credential_type ?? 'unknown',
                    'name' => $credential->credential_display_name ?? $credential->credential_name ?? $this->getCredentialDisplayName($credential->credential_type ?? 'unknown'),
                    'number' => $credential->credential_number ?? '',
                    'issuing_state' => $credential->issuing_state ?? null,
                    'expiration_date' => $credential->expiration_date,
                    'verification_status' => $credential->verification_status ?? 'pending',
                    'is_expired' => $credential->expiration_date ? Carbon::parse($credential->expiration_date)->isPast() : false,
                    'expires_soon' => $credential->expiration_date ? Carbon::parse($credential->expiration_date)->isBetween(now(), now()->addDays(30)) : false,
                ];
            }),
            'facilities' => $facilities->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'type' => $facility->facility_type ?? 'clinic',
                    'address' => $facility->address ? "{$facility->address}, {$facility->city}, {$facility->state} {$facility->zip_code}" : 'Address not available',
                    'phone' => $facility->phone ?? '',
                    'email' => $facility->email ?? '',
                ];
            }),
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'manufacturer' => $product->manufacturer,
                    'category' => $product->category,
                    'onboarded_at' => $product->onboarded_at,
                    'onboarding_status' => $product->onboarding_status,
                    'expiration_date' => $product->product_expiration_date,
                ];
            }),
            'financial_summary' => $financialSummary,
            'recent_orders' => $recentOrders,
            'payment_history' => $paymentHistory,
            'activity_log' => $activityLog,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ];

        // Get available facilities for the Add Facility modal
        $availableFacilities = DB::table('facilities')
            ->leftJoin('facility_user', function ($join) use ($provider) {
                $join->on('facilities.id', '=', 'facility_user.facility_id')
                     ->where('facility_user.user_id', '=', $provider->id);
            })
            ->whereNull('facility_user.facility_id')
            ->select('facilities.id', 'facilities.name', 'facilities.address')
            ->get();

        return Inertia::render('Admin/Providers/Show', [
            'provider' => $providerData,
            'stats' => $stats,
            'availableFacilities' => $availableFacilities,
        ]);
    }

    /**
     * Calculate provider's financial summary with aging
     */
    private function getProviderFinancialSummary($providerId)
    {
        $outstandingOrders = ProductRequest::where('provider_id', $providerId)
            ->where('order_status', '!=', 'approved')
            ->get();

        $agingBuckets = [
            'current' => 0,
            '30_days' => 0,
            '60_days' => 0,
            '90_days' => 0,
            'over_90' => 0,
        ];

        $totalOutstanding = 0;
        $pastDueAmount = 0;
        $maxDaysPastDue = 0;

        foreach ($outstandingOrders as $order) {
            $outstandingAmount = $order->total_order_value ?? 0;
            $totalOutstanding += $outstandingAmount;

            $daysOld = Carbon::parse($order->created_at)->diffInDays(now());

            if ($daysOld <= 30) {
                $agingBuckets['current'] += $outstandingAmount;
            } elseif ($daysOld <= 60) {
                $agingBuckets['30_days'] += $outstandingAmount;
            } elseif ($daysOld <= 90) {
                $agingBuckets['60_days'] += $outstandingAmount;
                $pastDueAmount += $outstandingAmount;
                $maxDaysPastDue = max($maxDaysPastDue, $daysOld);
            } elseif ($daysOld <= 120) {
                $agingBuckets['90_days'] += $outstandingAmount;
                $pastDueAmount += $outstandingAmount;
                $maxDaysPastDue = max($maxDaysPastDue, $daysOld);
            } else {
                $agingBuckets['over_90'] += $outstandingAmount;
                $pastDueAmount += $outstandingAmount;
                $maxDaysPastDue = max($maxDaysPastDue, $daysOld);
            }
        }

        // Get last payment info
        $lastPayment = DB::table('payments')
            ->where('provider_id', $providerId)
            ->where('status', 'posted')
            ->orderBy('payment_date', 'desc')
            ->first();

        return [
            'total_outstanding' => $totalOutstanding,
            'current_balance' => $totalOutstanding,
            'past_due_amount' => $pastDueAmount,
            'days_past_due' => $maxDaysPastDue,
            'payment_terms' => 'NET 60',
            'last_payment' => $lastPayment ? [
                'date' => $lastPayment->payment_date,
                'amount' => $lastPayment->amount,
                'reference' => $lastPayment->reference_number,
            ] : null,
            'aging_buckets' => $agingBuckets,
        ];
    }

    /**
     * Calculate payment performance score
     */
    private function calculatePaymentPerformanceScore($providerId)
    {
        // Get all approved orders (equivalent to paid orders in the new system)
        $approvedOrders = ProductRequest::where('provider_id', $providerId)
            ->where('order_status', 'approved')
            ->get();

        if ($approvedOrders->count() === 0) {
            return 100; // New provider, give them benefit of doubt
        }

        $onTimeApprovals = 0;
        $totalOrders = $approvedOrders->count();

        foreach ($approvedOrders as $order) {
            $daysToApproval = Carbon::parse($order->created_at)->diffInDays($order->updated_at);
            if ($daysToApproval <= 60) { // NET 60 terms equivalent
                $onTimeApprovals++;
            }
        }

        return round(($onTimeApprovals / $totalOrders) * 100);
    }

    /**
     * Show the form for creating a new provider
     */
    public function create()
    {
        $organizations = Organization::orderBy('name')->get(['id', 'name']);
        $states = $this->getUSStates();

        return Inertia::render('Admin/Providers/Create', [
            'organizations' => $organizations,
            'states' => $states,
        ]);
    }

    /**
     * Store a newly created provider
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'npi_number' => 'nullable|string|max:20',
            'dea_number' => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
            'license_state' => 'nullable|string|max:2',
            'license_expiry' => 'nullable|date',
            'current_organization_id' => 'nullable|exists:organizations,id',
            'is_verified' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Create the user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'npi_number' => $validated['npi_number'] ?? null,
                'dea_number' => $validated['dea_number'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'license_state' => $validated['license_state'] ?? null,
                'license_expiry' => $validated['license_expiry'] ?? null,
                'current_organization_id' => $validated['current_organization_id'] ?? null,
                'is_verified' => $validated['is_verified'] ?? false,
                'account_id' => 1, // Default account
            ]);

            // Assign provider role
            $providerRole = DB::table('roles')->where('slug', 'provider')->first();
            if ($providerRole) {
                DB::table('user_role')->insert([
                    'user_id' => $user->id,
                    'role_id' => $providerRole->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create provider profile if table exists
            if (Schema::hasTable('provider_profiles')) {
                ProviderProfile::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'provider_id' => $user->id,
                    'verification_status' => $user->npi_number ? 'pending' : 'unverified',
                    'last_profile_update' => now(),
                ]);
            }
        });

        return redirect()->route('admin.providers.index')
            ->with('success', 'Provider created successfully.');
    }

    /**
     * Show the form for editing a provider
     */
    public function edit(User $provider)
    {
        // Ensure the user is a provider
        if (!$provider->isProvider()) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Selected user is not a provider.');
        }

        $organizations = Organization::orderBy('name')->get(['id', 'name']);
        $states = $this->getUSStates();

        return Inertia::render('Admin/Providers/Edit', [
            'provider' => [
                'id' => $provider->id,
                'first_name' => $provider->first_name,
                'last_name' => $provider->last_name,
                'email' => $provider->email,
                'npi_number' => $provider->npi_number,
                'dea_number' => $provider->dea_number,
                'license_number' => $provider->license_number,
                'license_state' => $provider->license_state,
                'license_expiry' => $provider->license_expiry,
                'current_organization_id' => $provider->current_organization_id,
                'is_verified' => $provider->is_verified ?? false,
                'name' => $provider->name,
            ],
            'organizations' => $organizations,
            'states' => $states,
        ]);
    }

    /**
     * Update the specified provider
     */
    public function update(Request $request, User $provider)
    {
        // Ensure the user is a provider
        if (!$provider->isProvider()) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Selected user is not a provider.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $provider->id,
            'npi_number' => 'nullable|string|max:20',
            'dea_number' => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:50',
            'license_state' => 'nullable|string|max:2',
            'license_expiry' => 'nullable|date',
            'current_organization_id' => 'nullable|exists:organizations,id',
            'is_verified' => 'boolean',
        ]);

        $provider->update($validated);

        return redirect()->route('admin.providers.show', $provider)
            ->with('success', 'Provider updated successfully.');
    }

    /**
     * Update provider's product associations
     */
    public function updateProducts(Request $request, User $provider)
    {
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:msc_products,id',
            'products.*.onboarding_status' => 'required|in:active,suspended,expired',
            'products.*.notes' => 'nullable|string|max:500',
            'products.*.expiration_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($provider, $validated) {
            // Get current product IDs
            $currentProductIds = $provider->products()->pluck('msc_products.id')->toArray();
            $newProductIds = collect($validated['products'])->pluck('product_id')->toArray();

            // Detach removed products
            $toDetach = array_diff($currentProductIds, $newProductIds);
            if (!empty($toDetach)) {
                $provider->products()->detach($toDetach);
            }

            // Update or attach products
            foreach ($validated['products'] as $productData) {
                $provider->products()->syncWithoutDetaching([
                    $productData['product_id'] => [
                        'onboarding_status' => $productData['onboarding_status'],
                        'notes' => $productData['notes'] ?? null,
                        'expiration_date' => $productData['expiration_date'] ?? null,
                        'onboarded_at' => $provider->products()->where('msc_products.id', $productData['product_id'])->exists()
                            ? $provider->products()->where('msc_products.id', $productData['product_id'])->first()->pivot->onboarded_at
                            : now(),
                    ]
                ]);
            }
        });

        return redirect()->route('admin.providers.show', ['provider' => $provider->id, 'tab' => 'products'])
            ->with('success', 'Provider products updated successfully.');
    }

    /**
     * Add a facility to a provider
     */
    public function addFacility(Request $request, User $provider)
    {
        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'is_primary' => 'boolean',
        ]);

        // Attach the facility to the provider
        $provider->facilities()->attach($validated['facility_id'], [
            'is_primary' => $validated['is_primary'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Facility added successfully',
        ]);
    }

    /**
     * Remove a facility from a provider
     */
    public function removeFacility(User $provider, $facilityId)
    {
        $provider->facilities()->detach($facilityId);

        return response()->json([
            'success' => true,
            'message' => 'Facility removed successfully',
        ]);
    }

    /**
     * Associate a product with a provider (API)
     */
    public function addProduct(Request $request, $providerId)
    {
        $provider = \App\Models\User::findOrFail($providerId);
        $validated = $request->validate([
            'product_id' => 'required|exists:msc_products,id',
            'onboarding_status' => 'required|in:active,pending,suspended,expired',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);
        $provider->products()->syncWithoutDetaching([
            $validated['product_id'] => [
                'onboarding_status' => $validated['onboarding_status'],
                'expiration_date' => $validated['expiration_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'onboarded_at' => now(),
            ]
        ]);

        return redirect()->route('admin.providers.show', ['provider' => $providerId, 'tab' => 'products'])
            ->with('success', 'Product added successfully.');
    }

    /**
     * Remove a product from a provider
     */
    public function removeProduct(User $provider, $productId)
    {
        $provider->products()->detach($productId);

        return response()->json([
            'success' => true,
            'message' => 'Product removed successfully',
        ]);
    }

    /**
     * Update a provider's product association
     */
    public function updateProduct(Request $request, User $provider, $productId)
    {
        $validated = $request->validate([
            'onboarding_status' => 'required|in:active,pending,expired,suspended',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $provider->products()->updateExistingPivot($productId, [
            'onboarding_status' => $validated['onboarding_status'],
            'expiration_date' => $validated['expiration_date'],
            'notes' => $validated['notes'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion($provider, $providerProfile)
    {
        $completed = 0;
        $total = 10; // Total number of profile fields to check

        // Basic info
        if ($provider->name) $completed++;
        if ($provider->email) $completed++;
        if ($provider->phone) $completed++;
        if ($provider->npi_number) $completed++;

        // Organization/Facility association
        if ($provider->current_organization_id || $provider->facilities()->exists()) $completed++;

        // Profile specific fields if profile exists
        if ($providerProfile) {
            if ($providerProfile->professional_bio) $completed++;
            if ($providerProfile->specializations && count($providerProfile->specializations) > 0) $completed++;
            if ($providerProfile->languages_spoken && count($providerProfile->languages_spoken) > 0) $completed++;
        }

        // Credentials
        if (Schema::hasTable('provider_credentials') && DB::table('provider_credentials')->where('provider_id', $provider->id)->exists()) {
            $completed++;
        }

        // Products
        if ($provider->products()->exists()) $completed++;

        return round(($completed / $total) * 100);
    }

    /**
     * Remove the specified provider from storage
     */
    public function destroy(User $provider)
    {
        // Ensure the user is a provider
        if (!$provider->isProvider()) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Selected user is not a provider.');
        }

        // Check if provider has active orders
        $activeOrdersCount = ProductRequest::where('provider_id', $provider->id)
            ->whereIn('order_status', ['pending_ivr', 'ivr_sent', 'ivr_confirmed', 'approved'])
            ->count();

        if ($activeOrdersCount > 0) {
            return redirect()->route('admin.providers.show', $provider)
                ->with('error', 'Cannot delete provider with active orders. Please complete or cancel all active orders first.');
        }

        // Check if provider has outstanding balance
        if ($provider->financial_summary && $provider->financial_summary['total_outstanding'] > 0) {
            return redirect()->route('admin.providers.show', $provider)
                ->with('error', 'Cannot delete provider with outstanding balance. Please settle all payments first.');
        }

        DB::transaction(function () use ($provider) {
            // Remove provider from all facilities
            $provider->facilities()->detach();

            // Remove provider from all products
            $provider->products()->detach();

            // Remove provider profile if exists
            if (Schema::hasTable('provider_profiles')) {
                ProviderProfile::where('provider_id', $provider->id)->delete();
            }

            // Remove provider credentials if exists
            if (Schema::hasTable('provider_credentials')) {
                DB::table('provider_credentials')->where('provider_id', $provider->id)->delete();
            }

            // Soft delete the provider (preserves historical data)
            $provider->delete();
        });

        return redirect()->route('admin.providers.index')
            ->with('success', 'Provider has been deactivated successfully.');
    }

    /**
     * Get US states list
     */
    private function getUSStates()
    {
        return [
            ['code' => 'AL', 'name' => 'Alabama'],
            ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'],
            ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'],
            ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'],
            ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'FL', 'name' => 'Florida'],
            ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'],
            ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'],
            ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'],
            ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'],
            ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'],
            ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'],
            ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'],
            ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'],
            ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'],
            ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'],
            ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'],
            ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'],
            ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'],
            ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'],
            ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'RI', 'name' => 'Rhode Island'],
            ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'],
            ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'],
            ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'],
            ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'],
            ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'],
            ['code' => 'WY', 'name' => 'Wyoming'],
            ['code' => 'DC', 'name' => 'District of Columbia']
        ];
    }

    /**
     * Get credential type display name
     */
    private function getCredentialDisplayName($type)
    {
        return match ($type) {
            'npi_number' => 'NPI Number',
            'dea_registration' => 'DEA Registration',
            'medical_license' => 'Medical License',
            'board_certification' => 'Board Certification',
            'malpractice_insurance' => 'Malpractice Insurance',
            'hospital_privileges' => 'Hospital Privileges',
            'continuing_education' => 'Continuing Education',
            'state_license' => 'State License',
            'specialty_certification' => 'Specialty Certification',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

}
