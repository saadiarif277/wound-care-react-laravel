<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrganizationCollection;
use App\Models\Users\Organization\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadeRequest;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-organizations')->only(['index', 'show', 'apiIndex', 'apiShow']);
        $this->middleware('permission:create-organizations')->only(['create', 'store', 'apiStore']);
        $this->middleware('permission:edit-organizations')->only(['edit', 'update', 'apiUpdate']);
        $this->middleware('permission:delete-organizations')->only(['destroy', 'apiDestroy']);
    }

    public function index(): Response
    {
        return Inertia::render('Organizations/Index', [
            'filters' => FacadeRequest::only(['search', 'trashed']),
            'organizations' => new OrganizationCollection(
                Organization::query()
                    ->orderBy('name')
                    ->filter(FacadeRequest::only(['search', 'trashed']))
                    ->paginate()
                    ->appends(FacadeRequest::all())
            ),
        ]);
    }

    /**
     * Get organizations for API (JSON response)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $query = Organization::query();

            // Apply search filter if provided
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
            }

            // Apply status filter if provided
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Apply trashed filter if provided
            if ($request->has('trashed')) {
                if ($request->trashed === 'with') {
                    $query->withTrashed();
                } elseif ($request->trashed === 'only') {
                    $query->onlyTrashed();
                }
            }

            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $organizations = $query->orderBy('name')
                                  ->with(['salesRep', 'facilities'])
                                  ->paginate($perPage);

            return response()->json([
                'data' => $organizations->items(),
                'meta' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                ],
                'links' => [
                    'first' => $organizations->url(1),
                    'last' => $organizations->url($organizations->lastPage()),
                    'prev' => $organizations->previousPageUrl(),
                    'next' => $organizations->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching organizations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific organization for API
     */
    public function apiShow(string $id): JsonResponse
    {
        try {
            $organization = Organization::with(['salesRep', 'facilities', 'onboardingRecord'])
                                       ->findOrFail($id);
            return response()->json(['data' => $organization]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Organization not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Store a new organization via API
     */
    public function apiStore(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,pending,inactive',
            'sales_rep_id' => 'nullable|exists:users,id',
            'email' => 'required|email|unique:organizations,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'fhir_id' => 'nullable|string|max:255',
        ]);

        try {
            $organization = Organization::create($request->all());
            return response()->json([
                'message' => 'Organization created successfully.',
                'data' => $organization->load(['salesRep', 'facilities'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating organization.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an organization via API
     */
    public function apiUpdate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,pending,inactive',
            'sales_rep_id' => 'nullable|exists:users,id',
            'email' => 'sometimes|required|email|unique:organizations,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'fhir_id' => 'nullable|string|max:255',
        ]);

        try {
            $organization = Organization::findOrFail($id);
            $organization->update($request->all());
            return response()->json([
                'message' => 'Organization updated successfully.',
                'data' => $organization->load(['salesRep', 'facilities'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating organization.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an organization via API
     */
    public function apiDestroy(string $id): JsonResponse
    {
        try {
            $organization = Organization::findOrFail($id);
            $organization->delete();
            return response()->json([
                'message' => 'Organization deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting organization.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organization stats for API
     */
    public function apiStats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Organization::count(),
                'active' => Organization::where('status', 'active')->count(),
                'inactive' => Organization::where('status', 'inactive')->count(),
                'recent' => Organization::where('created_at', '>=', now()->subDays(30))->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching organization stats.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
