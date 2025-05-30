<?php

namespace App\Http\Controllers;

use App\Models\Fhir\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadeRequest;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-facilities')->only(['index', 'show', 'apiIndex', 'apiShow']);
        $this->middleware('permission:create-facilities')->only(['create', 'store', 'apiStore']);
        $this->middleware('permission:edit-facilities')->only(['edit', 'update', 'apiUpdate']);
        $this->middleware('permission:delete-facilities')->only(['destroy', 'apiDestroy']);
    }

    public function index(): Response
    {
        return Inertia::render('Facilities/Index', [
            'filters' => FacadeRequest::only(['search', 'trashed']),
            'facilities' => Facility::with(['organization', 'users'])
                ->orderBy('name')
                ->paginate()
                ->appends(FacadeRequest::all()),
        ]);
    }

    /**
     * Get facilities for API (JSON response)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $query = Facility::with(['organization', 'users']);

            // Apply search filter if provided
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%')
                      ->orWhere('phone', 'like', '%' . $request->search . '%');
            }

            // Apply status filter if provided
            if ($request->has('status') && $request->status) {
                if ($request->status === 'active') {
                    $query->where('active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('active', false);
                }
            }

            // Apply organization filter if provided
            if ($request->has('organization_id') && $request->organization_id) {
                $query->where('organization_id', $request->organization_id);
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
            $facilities = $query->orderBy('name')
                                ->paginate($perPage);

            // Transform the data for API response
            $transformedData = $facilities->getCollection()->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'facility_type' => $facility->facility_type,
                    'group_npi' => $facility->group_npi,
                    'status' => $facility->status,
                    'active' => $facility->active,
                    'email' => $facility->email,
                    'phone' => $facility->formatted_phone,
                    'address' => $facility->full_address,
                    'city' => $facility->city,
                    'state' => $facility->state,
                    'zip_code' => $facility->zip_code,
                    'organization' => $facility->organization ? [
                        'id' => $facility->organization->id,
                        'name' => $facility->organization->name,
                    ] : null,
                    'providers_count' => $facility->providers()->count(),
                    'users_count' => $facility->users()->count(),
                    'orders_count' => $facility->orders()->count(),
                    'created_at' => $facility->created_at,
                    'updated_at' => $facility->updated_at,
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'meta' => [
                    'current_page' => $facilities->currentPage(),
                    'last_page' => $facilities->lastPage(),
                    'per_page' => $facilities->perPage(),
                    'total' => $facilities->total(),
                ],
                'links' => [
                    'first' => $facilities->url(1),
                    'last' => $facilities->url($facilities->lastPage()),
                    'prev' => $facilities->previousPageUrl(),
                    'next' => $facilities->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching facilities.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific facility for API
     */
    public function apiShow(string $id): JsonResponse
    {
        try {
            $facility = Facility::with(['organization', 'users', 'providers', 'officeManagers', 'orders'])
                               ->findOrFail($id);

            $facilityData = [
                'id' => $facility->id,
                'name' => $facility->name,
                'facility_type' => $facility->facility_type,
                'group_npi' => $facility->group_npi,
                'status' => $facility->status,
                'active' => $facility->active,
                'email' => $facility->email,
                'phone' => $facility->phone,
                'formatted_phone' => $facility->formatted_phone,
                'address' => $facility->address,
                'city' => $facility->city,
                'state' => $facility->state,
                'zip_code' => $facility->zip_code,
                'full_address' => $facility->full_address,
                'business_hours' => $facility->business_hours,
                'organization' => $facility->organization,
                'providers' => $facility->providers,
                'office_managers' => $facility->officeManagers,
                'users_count' => $facility->users()->count(),
                'orders_count' => $facility->orders()->count(),
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ];

            return response()->json(['data' => $facilityData]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facility not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Store a new facility via API
     */
    public function apiStore(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
            'facility_type' => 'nullable|string|max:100',
            'group_npi' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,pending,inactive',
            'email' => 'required|email|unique:facilities,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'business_hours' => 'nullable|array',
            'active' => 'boolean',
        ]);

        try {
            $facility = Facility::create($request->all());
            return response()->json([
                'message' => 'Facility created successfully.',
                'data' => $facility->load(['organization'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating facility.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a facility via API
     */
    public function apiUpdate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'organization_id' => 'sometimes|required|exists:organizations,id',
            'facility_type' => 'nullable|string|max:100',
            'group_npi' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,pending,inactive',
            'email' => 'sometimes|required|email|unique:facilities,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'business_hours' => 'nullable|array',
            'active' => 'boolean',
        ]);

        try {
            $facility = Facility::findOrFail($id);
            $facility->update($request->all());
            return response()->json([
                'message' => 'Facility updated successfully.',
                'data' => $facility->load(['organization'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating facility.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a facility via API
     */
    public function apiDestroy(string $id): JsonResponse
    {
        try {
            $facility = Facility::findOrFail($id);
            $facility->delete();
            return response()->json([
                'message' => 'Facility deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting facility.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get facility stats for API
     */
    public function apiStats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Facility::count(),
                'active' => Facility::where('active', true)->count(),
                'inactive' => Facility::where('active', false)->count(),
                'recent' => Facility::where('created_at', '>=', now()->subDays(30))->count(),
                'by_type' => Facility::selectRaw('facility_type, count(*) as count')
                                   ->groupBy('facility_type')
                                   ->pluck('count', 'facility_type'),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching facility stats.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
