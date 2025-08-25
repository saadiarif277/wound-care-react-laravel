<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Users\Organization\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\FhirService;
use Illuminate\Support\Facades\Log;

class OrganizationManagementController extends Controller
{
    /**
     * Display a listing of organizations
     */
    public function index(Request $request)
    {
        $query = Organization::query();

        // Add search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Add status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Add type filter
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Get organizations with counts
        $organizations = $query->withCount(['facilities', 'users'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Transform the data
        $organizations->through(function ($org) {
            // Get provider count through facilities
            $providerCount = DB::table('facility_user')
                ->join('facilities', 'facility_user.facility_id', '=', 'facilities.id')
                ->join('users', 'facility_user.user_id', '=', 'users.id')
                ->join('user_role', 'users.id', '=', 'user_role.user_id')
                ->join('roles', 'user_role.role_id', '=', 'roles.id')
                ->where('facilities.organization_id', $org->id)
                ->where('roles.slug', 'provider')
                ->distinct('users.id')
                ->count('users.id');

            return [
                'id' => $org->id,
                'name' => $org->name,
                'type' => $org->type ?? 'healthcare',
                'status' => $org->status ?? 'active',
                'contact_email' => $org->contact_email,
                'phone' => $org->phone,
                'address' => $org->address,
                'facilities_count' => $org->facilities_count ?? 0,
                'providers_count' => $providerCount,
                'users_count' => $org->users_count ?? 0,
                'created_at' => $org->created_at,
                'updated_at' => $org->updated_at,
            ];
        });

        // Get summary statistics
        $summary = [
            'total_organizations' => Organization::count(),
            'active_organizations' => Organization::where('status', 'active')->count(),
            'pending_organizations' => Organization::where('status', 'pending')->count(),
            'total_facilities' => DB::table('facilities')->count(),
            'total_providers' => User::whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })->count(),
        ];

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
            'filters' => $request->only(['search', 'status', 'type']),
            'summary' => $summary,
        ]);
    }

    /**
     * Show the form for creating a new organization
     */
    public function create()
    {
        return Inertia::render('Admin/Organizations/Create');
    }

    /**
     * Store a newly created organization
     */
    public function store(Request $request, FhirService $fhirService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:healthcare,clinic,hospital,other',
            'contact_email' => 'required|email|unique:organizations,contact_email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'billing_address' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:2',
            'billing_zip' => 'nullable|string|max:10',
            'ap_contact_name' => 'nullable|string|max:255',
            'ap_contact_phone' => 'nullable|string|max:20',
            'ap_contact_email' => 'nullable|email',
        ]);

        DB::transaction(function () use ($validated, $fhirService) {
            // Use account ID 1 as default
            $accountId = \App\Models\Account::first()?->id ?? 1;

            $organization = Organization::create([
                'account_id' => $accountId,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'status' => 'active',
                'contact_email' => $validated['contact_email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'city' => $validated['city'] ?? null,
                'region' => $validated['state'] ?? null,
                'postal_code' => $validated['zip_code'] ?? null,
                'billing_address' => $validated['billing_address'] ?? null,
                'billing_city' => $validated['billing_city'] ?? null,
                'billing_state' => $validated['billing_state'] ?? null,
                'billing_zip' => $validated['billing_zip'] ?? null,
                'ap_contact_name' => $validated['ap_contact_name'] ?? null,
                'ap_contact_phone' => $validated['ap_contact_phone'] ?? null,
                'ap_contact_email' => $validated['ap_contact_email'] ?? null,
            ]);

            // Create FHIR Organization resource
            try {
                $organizationData = [
                    'resourceType' => 'Organization',
                    'identifier' => [
                        [
                            'system' => 'https://mscwoundcare.com/organization-id',
                            'value' => $organization->id
                        ]
                    ],
                    'active' => true,
                    'type' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                                    'code' => $this->mapOrganizationType($validated['type']),
                                    'display' => ucfirst($validated['type'])
                                ]
                            ]
                        ]
                    ],
                    'name' => $validated['name'],
                    'telecom' => []
                ];

                // Add email if available
                if (!empty($validated['contact_email'])) {
                    $organizationData['telecom'][] = [
                        'system' => 'email',
                        'value' => $validated['contact_email'],
                        'use' => 'work'
                    ];
                }

                // Add phone if available
                if (!empty($validated['phone'])) {
                    $organizationData['telecom'][] = [
                        'system' => 'phone',
                        'value' => $validated['phone'],
                        'use' => 'work'
                    ];
                }

                // Add address if available
                if (!empty($validated['address'])) {
                    $address = [
                        'use' => 'work',
                        'type' => 'both',
                        'line' => [$validated['address']]
                    ];

                    if (!empty($validated['city'])) {
                        $address['city'] = $validated['city'];
                    }
                    if (!empty($validated['state'])) {
                        $address['state'] = $validated['state'];
                    }
                    if (!empty($validated['zip_code'])) {
                        $address['postalCode'] = $validated['zip_code'];
                    }

                    $organizationData['address'] = [$address];
                }

                // Create the FHIR resource
                $fhirOrganization = $fhirService->createOrganization($organizationData);

                if ($fhirOrganization && isset($fhirOrganization['id'])) {
                    // Update organization with FHIR organization ID
                    $organization->update(['fhir_organization_id' => $fhirOrganization['id']]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create FHIR Organization for organization ' . $organization->id . ': ' . $e->getMessage());
                // Continue with organization creation even if FHIR fails
            }
        });

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    /**
     * Map organization type to FHIR coding system
     */
    private function mapOrganizationType(string $type): string
    {
        return match ($type) {
            'healthcare' => 'prov',
            'hospital' => 'hosp',
            'clinic' => 'dept',
            'other' => 'other',
            default => 'other'
        };
    }

    /**
     * Display the specified organization
     */
    public function show(Organization $organization)
    {
        $organization->load(['facilities.users', 'users']);

        // Get detailed stats
        $stats = [
            'total_facilities' => $organization->facilities()->count(),
            'total_users' => $organization->users()->count(),
            'total_providers' => $organization->users()->whereHas('roles', function ($q) {
                $q->where('slug', 'provider');
            })->count(),
            'total_orders' => 0, // Would need to implement order relationship
            'total_revenue' => 0, // Would need to implement revenue calculation
        ];

        // Get recent activity
        $recentActivity = [];

        return Inertia::render('Admin/Organizations/Show', [
            'organization' => array_merge($organization->toArray(), [
                'created_at_formatted' => $organization->created_at->format('M d, Y'),
                'updated_at_formatted' => $organization->updated_at->format('M d, Y'),
            ]),
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Show the form for editing the specified organization
     */
    public function edit(Organization $organization)
    {
        return Inertia::render('Admin/Organizations/Edit', [
            'organization' => $organization,
        ]);
    }

    /**
     * Update the specified organization
     */
    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:healthcare,clinic,hospital,other',
            'status' => 'required|in:active,inactive,pending',
            'contact_email' => 'required|email|unique:organizations,contact_email,' . $organization->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'billing_address' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:2',
            'billing_zip' => 'nullable|string|max:10',
            'ap_contact_name' => 'nullable|string|max:255',
            'ap_contact_phone' => 'nullable|string|max:20',
            'ap_contact_email' => 'nullable|email|max:255',
        ]);

        $organization->update($validated);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', 'Organization updated successfully.');
    }

    /**
     * Remove the specified organization
     */
    public function destroy(Organization $organization)
    {
        // Check if organization has facilities
        if ($organization->facilities()->exists()) {
            return redirect()->route('admin.organizations.show', $organization)
                ->with('error', 'Cannot delete organization with active facilities.');
        }

        // Check if organization has users
        if ($organization->users()->exists()) {
            return redirect()->route('admin.organizations.show', $organization)
                ->with('error', 'Cannot delete organization with active users.');
        }

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    /**
     * Activate the specified organization
     */
    public function activate(Organization $organization)
    {
        $organization->update(['status' => 'active']);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', 'Organization activated successfully.');
    }

    /**
     * Deactivate the specified organization
     */
    public function deactivate(Organization $organization)
    {
        $organization->update(['status' => 'inactive']);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', 'Organization deactivated successfully.');
    }
}
