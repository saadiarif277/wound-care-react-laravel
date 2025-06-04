<?php

namespace App\Http\Controllers;

use App\Models\Fhir\Facility;
use App\Models\Users\Organization\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FacilityController extends Controller
{
    /**
     * Display a listing of facilities for admin users.
     */
    public function index()
    {
        // Use a raw query (DB::table('facilities')->select(...)->get()) to load all records (including soft-deleted ones) from the facilities table.
        $allfacility_raw = DB::table('facilities')->select('id', 'name', 'address', 'organization_id', 'created_at', 'updated_at', 'deleted_at')->get();
        // Hydrate (using Facility::hydrate) so that the select (for Order requests) also loads all facilities.
        $allfacility = Facility::hydrate($allfacility_raw->toArray());
        $facilities = $allfacility_raw->map(function ($fac) {
            $org = DB::table('organizations')->where('id', $fac->organization_id)->first();
            $prov_count = DB::table('facility_user')->where('facility_id', $fac->id)->where('role', 'provider')->count();
            return (object) [
                'id' => $fac->id,
                'name' => $fac->name,
                'address' => $fac->address,
                'organization_id' => $fac->organization_id,
                'organization_name' => ($org) ? $org->name : null,
                'provider_count' => $prov_count,
                'created_at' => $fac->created_at,
                'updated_at' => $fac->updated_at,
            ];
         });

        $organizations = Organization::select('id', 'name')->get();

        return Inertia::render('Admin/Facilities/Index', [
            'facilities' => $facilities,
            'organizations' => $organizations,
        ]);
    }

    /**
     * Show the form for creating a new facility.
     */
    public function create()
    {
        $organizations = Organization::select('id', 'name')->get();
        $salesReps = User::withRole('msc-rep')
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->map(function ($rep) {
                return [
                    'id' => $rep->id,
                    'name' => $rep->first_name . ' ' . $rep->last_name,
                ];
            });

        return Inertia::render('Admin/Facilities/Form', [
            'organizations' => $organizations,
            'salesReps' => $salesReps,
            'isEdit' => false,
        ]);
    }

    /**
     * Show the form for editing the specified facility.
     */
    public function edit(Facility $facility)
    {
        $organizations = Organization::select('id', 'name')->get();
        $salesReps = User::withRole('msc-rep')
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->map(function ($rep) {
                return [
                    'id' => $rep->id,
                    'name' => $rep->first_name . ' ' . $rep->last_name,
                ];
            });

        return Inertia::render('Admin/Facilities/Form', [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'facility_type' => $facility->facility_type,
                'address' => $facility->address,
                'city' => $facility->city,
                'state' => $facility->state,
                'zip_code' => $facility->zip_code,
                'phone' => $facility->phone,
                'email' => $facility->email,
                'npi' => $facility->npi,
                'business_hours' => $facility->business_hours,
                'active' => $facility->active,
                'coordinating_sales_rep_id' => $facility->coordinating_sales_rep_id,
                'organization_id' => $facility->organization_id,
            ],
            'organizations' => $organizations,
            'salesReps' => $salesReps,
            'isEdit' => true,
        ]);
    }

    /**
     * Display a listing of facilities for provider users.
     */
    public function providerIndex()
    {
        $user = Auth::user();
        $facilities = $user->facilities()
            ->with('organization')
            ->select('facilities.id', 'facilities.name', 'facilities.address', 'facilities.organization_id', 'facilities.created_at', 'facilities.updated_at')
            ->get()
            ->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->address,
                    'organization_name' => $facility->organization->name,
                    'created_at' => $facility->created_at,
                    'updated_at' => $facility->updated_at,
                ];
            });

        return Inertia::render('Provider/Facilities/Index', [
            'facilities' => $facilities,
        ]);
    }

    /**
     * Display the specified facility for provider users.
     */
    public function providerShow(Facility $facility)
    {
        $user = Auth::user();

        // Check if provider has access to this facility
        if (!$user->facilities()->where('facilities.id', $facility->id)->exists()) {
            abort(403, 'You do not have access to this facility.');
        }

        $facility->load(['organization', 'providers' => function ($query) {
            $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->withPivot('role');
        }]);

        return Inertia::render('Provider/Facilities/Show', [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization' => [
                    'id' => $facility->organization->id,
                    'name' => $facility->organization->name,
                ],
                'providers' => $facility->providers->map(function ($provider) {
                    return [
                        'id' => $provider->id,
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'email' => $provider->email,
                        'role' => $provider->pivot->role,
                    ];
                }),
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ],
        ]);
    }

    /**
     * API: Get a listing of facilities for admin users.
     */
    public function apiIndex(Request $request)
    {
        $query = Facility::with('organization')
            ->select('id', 'name', 'address', 'organization_id', 'created_at', 'updated_at')
            ->withCount('providers');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('organization', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $facilities = $query->get()->map(function ($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization_id' => $facility->organization_id,
                'organization_name' => $facility->organization->name,
                'provider_count' => $facility->providers_count,
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ];
        });

        return response()->json(['facilities' => $facilities]);
    }

    /**
     * API: Store a newly created facility.
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $facility = Facility::create($validated);

        return response()->json([
            'message' => 'Facility created successfully',
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization_id' => $facility->organization_id,
            ],
        ], 201);
    }

    /**
     * API: Display the specified facility.
     */
    public function apiShow(Facility $facility)
    {
        $facility->load(['organization', 'providers' => function ($query) {
            $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->withPivot('role');
        }]);

        return response()->json([
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization' => [
                    'id' => $facility->organization->id,
                    'name' => $facility->organization->name,
                ],
                'providers' => $facility->providers->map(function ($provider) {
                    return [
                        'id' => $provider->id,
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'email' => $provider->email,
                        'role' => $provider->pivot->role,
                    ];
                }),
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ],
        ]);
    }

    /**
     * API: Update the specified facility.
     */
    public function apiUpdate(Request $request, Facility $facility)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $facility->update($validated);

        return response()->json([
            'message' => 'Facility updated successfully',
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization_id' => $facility->organization_id,
            ],
        ]);
    }

    /**
     * API: Remove the specified facility.
     */
    public function apiDestroy(Facility $facility)
    {
        $facility->delete();

        return response()->json([
            'message' => 'Facility deleted successfully'
        ]);
    }

    /**
     * API: Get a listing of facilities for provider users.
     */
    public function apiProviderIndex(Request $request)
    {
        $user = Auth::user();
        $query = $user->facilities()
            ->with('organization')
            ->select('facilities.id', 'facilities.name', 'facilities.address', 'facilities.organization_id', 'facilities.created_at', 'facilities.updated_at')
            ->withCount('providers');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('facilities.name', 'like', "%{$search}%")
                    ->orWhere('facilities.address', 'like', "%{$search}%")
                    ->orWhereHas('organization', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $facilities = $query->get()->map(function ($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization_name' => $facility->organization->name,
                'provider_count' => $facility->providers_count,
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ];
        });

        return response()->json(['facilities' => $facilities]);
    }

    /**
     * API: Display the specified facility for provider users.
     */
    public function apiProviderShow(Facility $facility)
    {
        $user = Auth::user();

        // Check if provider has access to this facility
        if (!$user->facilities()->where('facilities.id', $facility->id)->exists()) {
            return response()->json(['message' => 'You do not have access to this facility.'], 403);
        }

        $facility->load(['organization', 'providers' => function ($query) {
            $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->withPivot('role');
        }]);

        return response()->json([
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->address,
                'organization' => [
                    'id' => $facility->organization->id,
                    'name' => $facility->organization->name,
                ],
                'providers' => $facility->providers->map(function ($provider) {
                    return [
                        'id' => $provider->id,
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'email' => $provider->email,
                        'role' => $provider->pivot->role,
                    ];
                }),
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
            ],
        ]);
    }

    /**
     * Store a newly created facility.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'facility_type' => 'required|string|in:clinic,hospital_outpatient,wound_center,asc',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip_code' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'npi' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string',
            'active' => 'boolean',
            'coordinating_sales_rep_id' => 'nullable|exists:users,id',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $facility = Facility::create($validated);

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility created successfully');
    }

    /**
     * Update the specified facility.
     */
    public function update(Request $request, Facility $facility)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'facility_type' => 'required|string|in:clinic,hospital_outpatient,wound_center,asc',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip_code' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'npi' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string',
            'active' => 'boolean',
            'coordinating_sales_rep_id' => 'nullable|exists:users,id',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $facility->update($validated);

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility updated successfully');
    }

    /**
     * Remove the specified facility.
     */
    public function destroy(Facility $facility)
    {
        $facility->delete();

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility deleted successfully');
    }
}
