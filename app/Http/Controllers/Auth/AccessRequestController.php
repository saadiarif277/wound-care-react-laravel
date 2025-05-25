<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    /**
     * Display the access request form
     */
    public function create(): Response
    {
        return Inertia::render('Auth/RequestAccess', [
            'roles' => AccessRequest::ROLES,
        ]);
    }

    /**
     * Store a new access request
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:access_requests,email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'requested_role' => 'required|in:' . implode(',', array_keys(AccessRequest::ROLES)),
            'request_notes' => 'nullable|string|max:1000',

            // Provider fields
            'npi_number' => 'required_if:requested_role,provider|nullable|string|max:255',
            'medical_license' => 'required_if:requested_role,provider|nullable|string|max:255',
            'license_state' => 'required_if:requested_role,provider|nullable|string|max:2',
            'specialization' => 'nullable|string|max:255',
            'facility_name' => 'required_if:requested_role,provider,office_manager|nullable|string|max:255',
            'facility_address' => 'required_if:requested_role,provider,office_manager|nullable|string|max:500',

            // Office Manager fields
            'manager_name' => 'required_if:requested_role,office_manager|nullable|string|max:255',
            'manager_email' => 'required_if:requested_role,office_manager|nullable|email|max:255',

            // MSC Rep fields
            'territory' => 'required_if:requested_role,msc_rep,msc_subrep|nullable|string|max:255',
            'manager_contact' => 'required_if:requested_role,msc_rep|nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0|max:50',

            // MSC SubRep fields
            'main_rep_name' => 'required_if:requested_role,msc_subrep|nullable|string|max:255',
            'main_rep_email' => 'required_if:requested_role,msc_subrep|nullable|email|max:255',

            // MSC Admin fields
            'department' => 'required_if:requested_role,msc_admin|nullable|string|max:255',
            'supervisor_name' => 'required_if:requested_role,msc_admin|nullable|string|max:255',
            'supervisor_email' => 'required_if:requested_role,msc_admin|nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            AccessRequest::create($validator->validated());

            return redirect()->route('login')->with('success',
                'Access request submitted successfully! You will receive an email notification within 24 hours regarding the status of your request.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while submitting your request. Please try again.');
        }
    }

    /**
     * Display access requests for admin management
     */
    public function index(Request $request): Response
    {
        $query = AccessRequest::query()->with('reviewedBy');

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'denied'])) {
            $query->where('status', $request->status);
        } else {
            // Default to pending requests
            $query->where('status', 'pending');
        }

        // Filter by role
        if ($request->has('role') && array_key_exists($request->role, AccessRequest::ROLES)) {
            $query->where('requested_role', $request->role);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('facility_name', 'like', "%{$search}%");
            });
        }

        $accessRequests = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('AccessRequests/Index', [
            'accessRequests' => $accessRequests,
            'filters' => $request->only(['status', 'role', 'search']),
            'roles' => AccessRequest::ROLES,
        ]);
    }

    /**
     * Show a specific access request
     */
    public function show(AccessRequest $accessRequest): Response
    {
        $accessRequest->load('reviewedBy');

        return Inertia::render('AccessRequests/Show', [
            'accessRequest' => $accessRequest,
            'roleSpecificFields' => $accessRequest->getRoleSpecificFields(),
        ]);
    }

    /**
     * Approve an access request
     */
    public function approve(AccessRequest $accessRequest, Request $request): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $accessRequest->approve(Auth::user(), $request->admin_notes);

            // TODO: Send approval email notification
            // TODO: Create user account

            return back()->with('success', 'Access request approved successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while approving the request.');
        }
    }

    /**
     * Deny an access request
     */
    public function deny(AccessRequest $accessRequest, Request $request): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        try {
            $accessRequest->deny(Auth::user(), $request->admin_notes);

            // TODO: Send denial email notification

            return back()->with('success', 'Access request denied.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while denying the request.');
        }
    }

    /**
     * Get role-specific field requirements for the frontend
     */
    public function getRoleFields(Request $request)
    {
        $role = $request->get('role');

        if (!array_key_exists($role, AccessRequest::ROLES)) {
            return response()->json(['error' => 'Invalid role'], 400);
        }

        $fields = [];

        switch ($role) {
            case 'provider':
                $fields = [
                    'npi_number' => ['label' => 'NPI Number', 'required' => true, 'type' => 'text'],
                    'medical_license' => ['label' => 'Medical License Number', 'required' => true, 'type' => 'text'],
                    'license_state' => ['label' => 'License State', 'required' => true, 'type' => 'select'],
                    'specialization' => ['label' => 'Specialization', 'required' => false, 'type' => 'text'],
                    'facility_name' => ['label' => 'Facility/Practice Name', 'required' => true, 'type' => 'text'],
                    'facility_address' => ['label' => 'Facility Address', 'required' => true, 'type' => 'textarea'],
                ];
                break;

            case 'office_manager':
                $fields = [
                    'facility_name' => ['label' => 'Facility/Practice Name', 'required' => true, 'type' => 'text'],
                    'facility_address' => ['label' => 'Facility Address', 'required' => true, 'type' => 'textarea'],
                    'manager_name' => ['label' => 'Practice Manager Name', 'required' => true, 'type' => 'text'],
                    'manager_email' => ['label' => 'Practice Manager Email', 'required' => true, 'type' => 'email'],
                ];
                break;

            case 'msc_rep':
                $fields = [
                    'territory' => ['label' => 'Territory/Region', 'required' => true, 'type' => 'text'],
                    'manager_contact' => ['label' => 'Manager Contact', 'required' => true, 'type' => 'text'],
                    'experience_years' => ['label' => 'Years of Sales Experience', 'required' => false, 'type' => 'number'],
                ];
                break;

            case 'msc_subrep':
                $fields = [
                    'territory' => ['label' => 'Territory/Region', 'required' => true, 'type' => 'text'],
                    'main_rep_name' => ['label' => 'Main Representative Name', 'required' => true, 'type' => 'text'],
                    'main_rep_email' => ['label' => 'Main Representative Email', 'required' => true, 'type' => 'email'],
                ];
                break;

            case 'msc_admin':
                $fields = [
                    'department' => ['label' => 'Department', 'required' => true, 'type' => 'text'],
                    'supervisor_name' => ['label' => 'Supervisor Name', 'required' => true, 'type' => 'text'],
                    'supervisor_email' => ['label' => 'Supervisor Email', 'required' => true, 'type' => 'email'],
                ];
                break;
        }

        return response()->json(['fields' => $fields]);
    }
}
