<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:view-providers']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Build base query for providers
        $query = User::with(['facilities', 'roles'])
            ->whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })
            ->withCount(['productRequests', 'productRequests as pending_requests_count' => function ($q) {
                $q->whereIn('order_status', ['submitted', 'processing', 'pending_approval']);
            }]);

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

        if ($request->filled('facility')) {
            $query->whereHas('facilities', function ($q) use ($request) {
                $q->where('facilities.id', $request->input('facility'));
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('active', true);
            } elseif ($status === 'inactive') {
                $query->where('active', false);
            }
        }

        // Get paginated results
        $providers = $query->orderBy('last_name')->paginate(20)->withQueryString();

        // Transform for frontend
        $providers->getCollection()->transform(function ($provider) {
            return [
                'id' => $provider->id,
                'name' => $provider->first_name . ' ' . $provider->last_name,
                'email' => $provider->email,
                'npi_number' => $provider->npi_number,
                'active' => $provider->active,
                'created_at' => $provider->created_at?->format('M j, Y'),
                'last_login' => $provider->last_login_at?->format('M j, Y H:i'),
                'facilities' => $provider->facilities->map(fn ($facility) => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'city' => $facility->city ?? '',
                    'state' => $facility->state ?? '',
                ]),
                'total_requests' => $provider->product_requests_count,
                'pending_requests' => $provider->pending_requests_count,
            ];
        });

        // Get facilities for filter dropdown
        $facilities = Facility::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Providers/Index', [
            'providers' => $providers,
            'filters' => $request->only(['search', 'facility', 'status']),
            'facilities' => $facilities,
        ]);
    }

    /**
     * Get providers for API (JSON response)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $query = User::with(['facilities', 'roles'])
                ->whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })
                ->withCount(['productRequests', 'productRequests as pending_requests_count' => function ($q) {
                    $q->whereIn('order_status', ['submitted', 'processing', 'pending_approval']);
                }]);

            // Apply search filter if provided
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('npi_number', 'like', "%{$search}%");
                });
            }

            // Apply facility filter if provided
            if ($request->has('facility_id') && $request->facility_id) {
                $query->whereHas('facilities', function ($q) use ($request) {
                    $q->where('facilities.id', $request->facility_id);
                });
            }

            // Apply status filter if provided
            if ($request->has('status') && $request->status) {
                if ($request->status === 'active') {
                    $query->where('active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('active', false);
                }
            }

            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $providers = $query->orderBy('last_name')
                              ->paginate($perPage);

            // Transform the data for API response
            $transformedData = $providers->getCollection()->map(function ($provider) {
                return [
                    'id' => $provider->id,
                    'first_name' => $provider->first_name,
                    'last_name' => $provider->last_name,
                    'full_name' => $provider->first_name . ' ' . $provider->last_name,
                    'email' => $provider->email,
                    'npi_number' => $provider->npi_number,
                    'phone' => $provider->phone,
                    'active' => $provider->active,
                    'email_verified_at' => $provider->email_verified_at,
                    'last_login_at' => $provider->last_login_at,
                    'facilities' => $provider->facilities->map(fn ($facility) => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'city' => $facility->city ?? '',
                        'state' => $facility->state ?? '',
                    ]),
                    'total_requests' => $provider->product_requests_count,
                    'pending_requests' => $provider->pending_requests_count,
                    'created_at' => $provider->created_at,
                    'updated_at' => $provider->updated_at,
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'meta' => [
                    'current_page' => $providers->currentPage(),
                    'last_page' => $providers->lastPage(),
                    'per_page' => $providers->perPage(),
                    'total' => $providers->total(),
                ],
                'links' => [
                    'first' => $providers->url(1),
                    'last' => $providers->url($providers->lastPage()),
                    'prev' => $providers->previousPageUrl(),
                    'next' => $providers->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching providers.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(User $provider)
    {
        // Load relationships
        $provider->load([
            'facilities',
            'roles',
            'productRequests' => function ($query) {
                $query->with(['facility:id,name', 'products'])
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            }
        ]);

        // Get request statistics
        $requestStats = [
            'total' => $provider->productRequests()->count(),
            'submitted' => $provider->productRequests()->where('order_status', 'submitted')->count(),
            'approved' => $provider->productRequests()->where('order_status', 'approved')->count(),
            'rejected' => $provider->productRequests()->where('order_status', 'rejected')->count(),
            'average_processing_time' => $this->getAverageProcessingTime($provider),
        ];

        return Inertia::render('Providers/Show', [
            'provider' => [
                'id' => $provider->id,
                'first_name' => $provider->first_name,
                'last_name' => $provider->last_name,
                'email' => $provider->email,
                'npi_number' => $provider->npi_number,
                'phone' => $provider->phone,
                'active' => $provider->active,
                'created_at' => $provider->created_at?->format('M j, Y'),
                'last_login' => $provider->last_login_at?->format('M j, Y H:i'),
                'email_verified_at' => $provider->email_verified_at?->format('M j, Y'),
                'facilities' => $provider->facilities,
                'recent_requests' => $provider->productRequests->map(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'order_status' => $request->order_status,
                    'wound_type' => $request->wound_type,
                    'facility_name' => $request->facility->name ?? '',
                    'total_value' => $request->total_order_value,
                    'created_at' => $request->created_at?->format('M j, Y'),
                    'products_count' => $request->products->count(),
                ]),
            ],
            'requestStats' => $requestStats,
        ]);
    }

    /**
     * Get a specific provider for API
     */
    public function apiShow(string $id): JsonResponse
    {
        try {
            $provider = User::with(['facilities', 'roles', 'productRequests' => function ($query) {
                    $query->with(['facility:id,name', 'products'])
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                }])
                ->whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })
                ->findOrFail($id);

            // Get request statistics
            $requestStats = [
                'total' => $provider->productRequests()->count(),
                'submitted' => $provider->productRequests()->where('order_status', 'submitted')->count(),
                'approved' => $provider->productRequests()->where('order_status', 'approved')->count(),
                'rejected' => $provider->productRequests()->where('order_status', 'rejected')->count(),
                'average_processing_time' => $this->getAverageProcessingTime($provider),
            ];

            $providerData = [
                'id' => $provider->id,
                'first_name' => $provider->first_name,
                'last_name' => $provider->last_name,
                'full_name' => $provider->first_name . ' ' . $provider->last_name,
                'email' => $provider->email,
                'npi_number' => $provider->npi_number,
                'phone' => $provider->phone,
                'active' => $provider->active,
                'email_verified_at' => $provider->email_verified_at,
                'last_login_at' => $provider->last_login_at,
                'facilities' => $provider->facilities,
                'roles' => $provider->roles,
                'recent_requests' => $provider->productRequests->map(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'order_status' => $request->order_status,
                    'wound_type' => $request->wound_type,
                    'facility_name' => $request->facility->name ?? '',
                    'total_value' => $request->total_order_value,
                    'created_at' => $request->created_at,
                    'products_count' => $request->products->count(),
                ]),
                'request_stats' => $requestStats,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ];

            return response()->json(['data' => $providerData]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Provider not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Store a new provider via API
     */
    public function apiStore(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'npi_number' => 'nullable|string|max:20|unique:users,npi_number',
            'phone' => 'nullable|string|max:20',
            'active' => 'boolean',
            'password' => 'required|string|min:8|confirmed',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'exists:facilities,id',
        ]);

        try {
            // Create the user
            $userData = $request->only(['first_name', 'last_name', 'email', 'npi_number', 'phone', 'active']);
            $userData['password'] = bcrypt($request->password);

            $provider = User::create($userData);

            // Assign provider role
            $provider->assignRole(\App\Models\Role::where('slug', 'provider')->first());

            // Attach facilities if provided
            if ($request->has('facility_ids') && is_array($request->facility_ids)) {
                $provider->facilities()->attach($request->facility_ids, [
                    'relationship_type' => 'provider',
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'message' => 'Provider created successfully.',
                'data' => $provider->load(['facilities', 'roles'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating provider.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a provider via API
     */
    public function apiUpdate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'npi_number' => 'nullable|string|max:20|unique:users,npi_number,' . $id,
            'phone' => 'nullable|string|max:20',
            'active' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'exists:facilities,id',
        ]);

        try {
            $provider = User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })->findOrFail($id);

            // Update user data
            $userData = $request->only(['first_name', 'last_name', 'email', 'npi_number', 'phone', 'active']);

            if ($request->has('password') && $request->password) {
                $userData['password'] = bcrypt($request->password);
            }

            $provider->update($userData);

            // Update facilities if provided
            if ($request->has('facility_ids')) {
                $provider->facilities()->detach();
                if (is_array($request->facility_ids) && !empty($request->facility_ids)) {
                    $provider->facilities()->attach($request->facility_ids, [
                        'relationship_type' => 'provider',
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Provider updated successfully.',
                'data' => $provider->load(['facilities', 'roles'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating provider.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a provider via API
     */
    public function apiDestroy(string $id): JsonResponse
    {
        try {
            $provider = User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })->findOrFail($id);

            // Detach facilities
            $provider->facilities()->detach();

            // Remove roles
            $provider->removeRole(\App\Models\Role::where('slug', 'provider')->first());

            // Soft delete the user
            $provider->delete();

            return response()->json([
                'message' => 'Provider deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting provider.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get provider stats for API
     */
    public function apiStats(): JsonResponse
    {
        try {
            $stats = [
                'total' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->count(),
                'active' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->where('active', true)->count(),
                'inactive' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->where('active', false)->count(),
                'verified' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->whereNotNull('email_verified_at')->count(),
                'recent' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'provider');
                })->where('created_at', '>=', now()->subDays(30))->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching provider stats.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getAverageProcessingTime(User $provider): ?float
    {
        $completedRequests = $provider->productRequests()
            ->whereIn('order_status', ['approved', 'rejected'])
            ->whereNotNull('submitted_at')
            ->whereNotNull('updated_at')
            ->get();

        if ($completedRequests->isEmpty()) {
            return null;
        }

        $totalDays = $completedRequests->sum(function ($request) {
            return $request->submitted_at->diffInDays($request->updated_at);
        });

        return round($totalDays / $completedRequests->count(), 1);
    }

    /**
     * Show credential management page
     */
    public function credentials(Request $request)
    {
        $providerId = $request->query('provider_id');
        
        if (!$providerId) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Provider ID is required');
        }

        $provider = User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })
            ->findOrFail($providerId);

        // Get credentials if table exists
        $credentials = [];
        if (Schema::hasTable('provider_credentials')) {
            $credentials = DB::table('provider_credentials')
                ->where('provider_id', $provider->id)
                ->orderBy('expiration_date')
                ->get()
                ->map(function ($credential) {
                    return [
                        'id' => $credential->id,
                        'type' => $credential->credential_type ?? $credential->type ?? 'license',
                        'name' => $credential->credential_name ?? $credential->name ?? 'Medical License',
                        'number' => $credential->credential_number ?? $credential->number ?? '',
                        'issuing_state' => $credential->issuing_state ?? null,
                        'issuing_organization' => $credential->issuing_organization ?? '',
                        'issue_date' => $credential->issue_date ?? now()->subYear()->format('Y-m-d'),
                        'expiration_date' => $credential->expiration_date,
                        'status' => $credential->status ?? 'active',
                        'verification_status' => $credential->verification_status ?? 'pending',
                        'document_url' => $credential->document_url ?? null,
                        'notes' => $credential->notes ?? null,
                        'last_verified' => $credential->last_verified ?? $credential->updated_at ?? now()->format('Y-m-d'),
                    ];
                })
                ->toArray();
        }

        return Inertia::render('Providers/CredentialManagement', [
            'credentials' => $credentials,
            'user' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'email' => $provider->email,
                'verification_status' => $provider->is_verified ? 'verified' : 'pending',
            ],
        ]);
    }

    /**
     * Store a new credential
     */
    public function storeCredential(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'name' => 'required|string',
            'number' => 'required|string',
            'issuing_state' => 'nullable|string',
            'issuing_organization' => 'required|string',
            'issue_date' => 'required|date',
            'expiration_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if (!Schema::hasTable('provider_credentials')) {
            return response()->json(['error' => 'Credentials table not found'], 500);
        }

        $credentialId = DB::table('provider_credentials')->insertGetId([
            'provider_id' => $validated['provider_id'],
            'credential_type' => $validated['type'],
            'credential_name' => $validated['name'],
            'credential_number' => $validated['number'],
            'issuing_state' => $validated['issuing_state'],
            'issuing_organization' => $validated['issuing_organization'],
            'issue_date' => $validated['issue_date'],
            'expiration_date' => $validated['expiration_date'],
            'verification_status' => 'pending',
            'status' => 'active',
            'notes' => $validated['notes'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $credentialId]);
    }

    /**
     * Update a credential
     */
    public function updateCredential(Request $request, $credentialId)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'name' => 'required|string',
            'number' => 'required|string',
            'issuing_state' => 'nullable|string',
            'issuing_organization' => 'required|string',
            'issue_date' => 'required|date',
            'expiration_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if (!Schema::hasTable('provider_credentials')) {
            return response()->json(['error' => 'Credentials table not found'], 500);
        }

        DB::table('provider_credentials')
            ->where('id', $credentialId)
            ->update([
                'credential_type' => $validated['type'],
                'credential_name' => $validated['name'],
                'credential_number' => $validated['number'],
                'issuing_state' => $validated['issuing_state'],
                'issuing_organization' => $validated['issuing_organization'],
                'issue_date' => $validated['issue_date'],
                'expiration_date' => $validated['expiration_date'],
                'notes' => $validated['notes'],
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
