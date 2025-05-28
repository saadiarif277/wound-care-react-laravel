<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerManagementController extends Controller
{
    public function __construct()
    {
        // Ensure only MSC admins with customer management permissions can access
        $this->middleware(['auth', 'role:msc-admin', 'permission:manage-customers']);
    }

    /**
     * Display the customer management dashboard
     */
    public function dashboard()
    {
        // Get organizations with stats
        $organizations = Organization::with(['primaryContact', 'salesRep'])
            ->withCount(['facilities', 'users'])
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'type' => $org->type,
                    'status' => $org->status,
                    'created_at' => $org->created_at->toISOString(),
                    'onboarding_progress' => $org->onboarding_progress ?? 0,
                    'onboarding_status' => $org->onboarding_status ?? 'not_started',
                    'total_users' => $org->users_count ?? 0,
                    'total_orders' => $org->orders_count ?? 0,
                    'monthly_revenue' => $org->monthly_revenue ?? 0,
                    'primary_contact' => [
                        'name' => $org->primaryContact ? $org->primaryContact->first_name . ' ' . $org->primaryContact->last_name : 'N/A',
                        'email' => $org->primaryContact?->email ?? 'N/A',
                        'phone' => $org->primaryContact?->phone ?? 'N/A',
                    ],
                    'sales_rep' => [
                        'name' => $org->salesRep ? $org->salesRep->first_name . ' ' . $org->salesRep->last_name : 'N/A',
                        'email' => $org->salesRep?->email ?? 'N/A',
                    ],
                    'compliance_status' => $org->compliance_status ?? 'pending_documents',
                    'last_activity' => $org->updated_at->toISOString(),
                ];
            });

        $stats = [
            'total_organizations' => $organizations->count(),
            'active_organizations' => $organizations->where('status', 'active')->count(),
            'pending_onboarding' => $organizations->where('onboarding_status', 'in_progress')->count(),
            'total_revenue' => $organizations->sum('monthly_revenue'),
            'growth_rate' => 12.5, // Mock data
            'compliance_alerts' => $organizations->whereIn('compliance_status', ['expired', 'non_compliant'])->count(),
        ];

        $filters = [
            'status' => request('status', ''),
            'type' => request('type', ''),
            'onboarding_status' => request('onboarding_status', ''),
            'sales_rep' => request('sales_rep', ''),
        ];

        return Inertia::render('Admin/CustomerManagement/Dashboard', [
            'organizations' => $organizations,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the organization creation wizard
     */
    public function createOrganization()
    {
        return Inertia::render('Admin/CustomerManagement/OrganizationWizard');
    }

    /**
     * Show organization details
     */
    public function showOrganization(Organization $organization)
    {
        $organization->load(['facilities.providers', 'primaryContact', 'salesRep']);
        
        return Inertia::render('Admin/CustomerManagement/OrganizationDetail', [
            'organization' => $organization,
        ]);
    }

    /**
     * Show organization edit form
     */
    public function editOrganization(Organization $organization)
    {
        return Inertia::render('Admin/CustomerManagement/OrganizationEdit', [
            'organization' => $organization,
        ]);
    }
} 