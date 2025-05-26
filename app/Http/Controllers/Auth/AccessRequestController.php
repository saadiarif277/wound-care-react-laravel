<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessRequestStoreRequest;
use App\Http\Requests\AccessRequestApprovalRequest;
use App\Http\Requests\AccessRequestDenialRequest;
use App\Models\AccessRequest;
use App\Models\RbacAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['create', 'store', 'getRoleFields']);
        $this->middleware('permission:view-access-requests')->only(['index', 'show']);
        $this->middleware('permission:approve-access-requests')->only(['approve', 'deny']);
    }

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
    public function store(AccessRequestStoreRequest $request): RedirectResponse
    {
        try {
            $accessRequest = AccessRequest::create($request->validated());

            // Log the access request creation
            Log::info('Access request submitted', [
                'request_id' => $accessRequest->id,
                'email' => $accessRequest->email,
                'requested_role' => $accessRequest->requested_role,
                'ip_address' => request()->ip(),
            ]);

            return redirect()->route('login')->with('success',
                'Access request submitted successfully! You will receive an email notification within 24 hours regarding the status of your request.');

        } catch (\Exception $e) {
            Log::error('Failed to create access request', [
                'error' => $e->getMessage(),
                'email' => $request->validated()['email'],
                'requested_role' => $request->validated()['requested_role'],
                'ip_address' => request()->ip(),
            ]);

            return back()->with('error', 'An error occurred while submitting your request. Please try again.')
                ->withInput();
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
    public function approve(AccessRequest $accessRequest, AccessRequestApprovalRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $oldStatus = $accessRequest->status;

            $accessRequest->approve($user, $request->validated()['admin_notes'] ?? null);

            // Log the approval in audit trail
            RbacAuditLog::logEvent(
                eventType: 'access_request_approved',
                entityType: 'access_request',
                entityId: $accessRequest->id,
                entityName: $accessRequest->full_name,
                targetUserEmail: $accessRequest->email,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'approved'],
                changes: ['status_change' => 'approved'],
                reason: $request->validated()['admin_notes'] ?? 'Access request approved',
                metadata: [
                    'requested_role' => $accessRequest->requested_role,
                    'request_id' => $accessRequest->id,
                ]
            );

            Log::info('Access request approved', [
                'request_id' => $accessRequest->id,
                'approved_by' => $user->id,
                'approved_by_email' => $user->email,
                'applicant_email' => $accessRequest->email,
                'requested_role' => $accessRequest->requested_role,
            ]);

            // TODO: Send approval email notification
            // TODO: Create user account

            return back()->with('success', 'Access request approved successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to approve access request', [
                'request_id' => $accessRequest->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()->with('error', 'An error occurred while approving the request. Please try again.');
        }
    }

    /**
     * Deny an access request
     */
    public function deny(AccessRequest $accessRequest, AccessRequestDenialRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $oldStatus = $accessRequest->status;

            $accessRequest->deny($user, $request->validated()['admin_notes']);

            // Log the denial in audit trail
            RbacAuditLog::logEvent(
                eventType: 'access_request_denied',
                entityType: 'access_request',
                entityId: $accessRequest->id,
                entityName: $accessRequest->full_name,
                targetUserEmail: $accessRequest->email,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'denied'],
                changes: ['status_change' => 'denied'],
                reason: $request->validated()['admin_notes'],
                metadata: [
                    'requested_role' => $accessRequest->requested_role,
                    'request_id' => $accessRequest->id,
                ]
            );

            Log::info('Access request denied', [
                'request_id' => $accessRequest->id,
                'denied_by' => $user->id,
                'denied_by_email' => $user->email,
                'applicant_email' => $accessRequest->email,
                'requested_role' => $accessRequest->requested_role,
                'reason' => $request->validated()['admin_notes'],
            ]);

            // TODO: Send denial email notification

            return back()->with('success', 'Access request denied.');

        } catch (\Exception $e) {
            Log::error('Failed to deny access request', [
                'request_id' => $accessRequest->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()->with('error', 'An error occurred while denying the request. Please try again.');
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
