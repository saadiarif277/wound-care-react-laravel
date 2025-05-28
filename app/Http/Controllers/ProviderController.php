<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Facility;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
} 