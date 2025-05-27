<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductRequest;
use App\Models\Order;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user()->load('roles');
        $primaryRole = $user->getPrimaryRole();

        // Handle case where user doesn't have a role assigned
        if (!$primaryRole) {
            // Assign default provider role
            $defaultRole = \App\Models\Role::where('slug', 'provider')->first();
            if ($defaultRole) {
                $user->assignRole($defaultRole);
                $primaryRole = $defaultRole;
            }
        }

        $roleName = $primaryRole ? $primaryRole->slug : 'provider';

        // Get role-specific dashboard data
        $dashboardData = $this->getDashboardDataForRole($user, $roleName);

        // Route to specific role-based dashboard
        $dashboardComponent = $this->getDashboardComponent($roleName);

        return Inertia::render($dashboardComponent, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'owner' => $user->owner,
                'role' => $roleName,
                'role_display_name' => $primaryRole ? $primaryRole->name : 'Provider',
            ],
            'dashboardData' => $dashboardData,
            'roleRestrictions' => [
                'can_view_financials' => $user->hasPermission('view-financials'),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'pricing_access_level' => $this->getPricingAccessLevel($user),
                'commission_access_level' => $this->getCommissionAccessLevel($user),
                'can_manage_products' => $user->hasPermission('manage-products'),
            ]
        ]);
    }



        private function getDashboardDataForRole($user, $roleName): array
    {
        $baseData = [
            'recent_requests' => $this->getRecentRequests($user),
            'action_items' => $this->getActionItems($user),
            'metrics' => $this->getMetrics($user),
        ];

        // Add role-specific data with normalized role handling
        $normalizedRole = $this->normalizeRoleName($roleName);

        // Add role-specific data based on permissions
        $roleSpecificData = [];

        if ($user->hasRole('provider')) {
            $roleSpecificData = $this->getProviderSpecificData($user);
        } elseif ($user->hasRole('office-manager')) {
            $roleSpecificData = $this->getOfficeManagerSpecificData($user);
        } elseif ($user->hasRole('msc-rep')) {
            $roleSpecificData = $this->getMscRepSpecificData($user);
        } elseif ($user->hasRole('msc-subrep')) {
            $roleSpecificData = $this->getMscSubrepSpecificData($user);
        } elseif ($user->hasRole('msc-admin')) {
            $roleSpecificData = $this->getMscAdminSpecificData($user);
        } elseif ($user->isSuperAdmin()) {
            $roleSpecificData = $this->getSuperAdminSpecificData($user);
        }

        return array_merge($baseData, $roleSpecificData);
    }

        /**
     * Normalize role names to handle legacy inconsistencies (fixes super admin duplication)
     */
    private function normalizeRoleName(string $roleName): string
    {
        return match($roleName) {
            'superadmin' => 'super-admin',
            default => $roleName
        };
    }

    /**
     * Get dashboard component for role (eliminates duplication)
     */
    private function getDashboardComponent(string $roleName): string
    {
        $normalizedRole = $this->normalizeRoleName($roleName);

        return match($normalizedRole) {
            'provider' => 'Dashboard/Provider/ProviderDashboard',
            'office-manager' => 'Dashboard/OfficeManager/OfficeManagerDashboard',
            'msc-rep' => 'Dashboard/Sales/MscRepDashboard',
            'msc-subrep' => 'Dashboard/Sales/MscSubrepDashboard',
            'msc-admin' => 'Dashboard/Admin/MscAdminDashboard',
            'super-admin' => 'Dashboard/Admin/SuperAdminDashboard',
            default => 'Dashboard/Index'
        };
    }

    /**
     * Get pricing access level based on user permissions
     */
    private function getPricingAccessLevel($user): string
    {
        if ($user->hasPermission('view-full-pricing')) return 'full';
        if ($user->hasPermission('view-limited-pricing')) return 'limited';
        return 'national_asp_only';
    }

    /**
     * Get commission access level based on user permissions
     */
    private function getCommissionAccessLevel($user): string
    {
        if ($user->hasPermission('view-full-commission')) return 'full';
        if ($user->hasPermission('view-limited-commission')) return 'limited';
        return 'none';
    }

    private function getRecentRequests($user): array
    {
        $query = ProductRequest::with(['facility'])
            ->where('provider_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5);

        $requests = $query->get()->map(function ($request) use ($user) {
            $data = [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_name' => $request->formatPatientDisplay(),
                'wound_type' => $request->wound_type,
                'status' => $request->order_status,
                'created_at' => $request->created_at->format('Y-m-d'),
                'facility_name' => $request->facility->name ?? 'Unknown Facility',
            ];

            // Add financial data only if user has permission
            if ($user->hasPermission('view-financials') && $user->hasPermission('view-order-totals')) {
                $data['total_amount'] = $request->total_order_value;
            }

            return $data;
        });

        return $requests->toArray();
    }

    private function getActionItems($user): array
    {
        // Get requests that need action - using order_status values that indicate action needed
        $actionItems = ProductRequest::where('provider_id', $user->id)
            ->whereIn('order_status', ['draft', 'submitted', 'processing'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'type' => $this->getActionType($request->order_status),
                    'patient_name' => $request->formatPatientDisplay(),
                    'description' => $this->getActionDescription($request->order_status),
                    'priority' => $this->getActionPriority($request),
                    'due_date' => $request->expected_service_date,
                    'request_id' => $request->request_number,
                ];
            });

        return $actionItems->toArray();
    }

    private function getMetrics($user): array
    {
        $metrics = [
            'total_requests' => ProductRequest::where('provider_id', $user->id)->count(),
            'pending_requests' => ProductRequest::where('provider_id', $user->id)
                ->whereIn('order_status', ['draft', 'submitted', 'processing'])
                ->count(),
            'approved_requests' => ProductRequest::where('provider_id', $user->id)
                ->where('order_status', 'approved')
                ->count(),
        ];

        // Add financial metrics only if user has permission
        if ($user->hasPermission('view-financials')) {
            $metrics['total_order_value'] = ProductRequest::where('provider_id', $user->id)
                ->where('order_status', 'approved')
                ->sum('total_order_value') ?? 0;
        }

        return $metrics;
    }

    private function getProviderSpecificData($user): array
    {
        return [
            'clinical_opportunities' => $this->getClinicalOpportunities($user),
            'eligibility_status' => $this->getEligibilityStatus($user),
        ];
    }

    private function getOfficeManagerSpecificData($user): array
    {
        return [
            'facility_metrics' => $this->getFacilityMetrics($user),
            'provider_activity' => $this->getProviderActivity($user),
        ];
    }

    private function getMscRepSpecificData($user): array
    {
        return [
            'commission_summary' => $this->getCommissionSummary($user),
            'territory_performance' => $this->getTerritoryPerformance($user),
        ];
    }

    private function getMscSubrepSpecificData($user): array
    {
        return [
            'personal_commission' => $this->getPersonalCommission($user),
            'assigned_customers' => $this->getAssignedCustomers($user),
        ];
    }

    private function getMscAdminSpecificData($user): array
    {
        return [
            'business_metrics' => $this->getBusinessMetrics(),
            'pending_approvals' => $this->getPendingApprovals(),
        ];
    }

    private function getSuperAdminSpecificData($user): array
    {
        return [
            'system_metrics' => $this->getSystemMetrics(),
            'security_overview' => $this->getSecurityOverview(),
            'platform_health' => $this->getPlatformHealth(),
        ];
    }

    // Helper methods for specific data types
    private function getActionType($status): string
    {
        return match($status) {
            'draft' => 'complete_request',
            'submitted' => 'review_pending',
            'processing' => 'processing_review',
            'rejected' => 'address_rejection',
            default => 'review_required'
        };
    }

    private function getActionDescription($status): string
    {
        return match($status) {
            'draft' => 'Complete and submit request',
            'submitted' => 'Request submitted, awaiting review',
            'processing' => 'Request is being processed',
            'rejected' => 'Request rejected, needs attention',
            default => 'Review required'
        };
    }

    private function getActionPriority($request): string
    {
        // Determine priority based on request urgency and date
        $daysSinceCreated = $request->created_at->diffInDays(now());

        if ($daysSinceCreated > 7) return 'high';
        if ($daysSinceCreated > 3) return 'medium';
        return 'low';
    }

    // Placeholder methods for specific data - these would be implemented based on actual business logic
    private function getClinicalOpportunities($user): array
    {
        return []; // Implement based on clinical rules engine
    }

    private function getEligibilityStatus($user): array
    {
        return []; // Implement based on eligibility checks
    }

    private function getFacilityMetrics($user): array
    {
        return [
            'total_providers' => 12,
            'active_requests' => 28,
            'processing_time' => 2.3,
            'admin_tasks' => 5
        ];
    }

    private function getProviderActivity($user): array
    {
        return []; // Implement based on facility provider relationships
    }

    private function getCommissionSummary($user): array
    {
        return []; // Implement based on commission calculations
    }

    private function getTerritoryPerformance($user): array
    {
        return []; // Implement based on territory assignments
    }

    private function getPersonalCommission($user): array
    {
        return []; // Implement based on sub-rep commission calculations
    }

    private function getAssignedCustomers($user): array
    {
        return []; // Implement based on customer assignments
    }

    private function getBusinessMetrics(): array
    {
        return [
            'total_outstanding_commissions' => 47850.00,
            'monthly_revenue' => 285400.00,
            'monthly_target' => 320000.00,
            'pending_approval_amount' => 125600.00,
        ];
    }

    private function getPendingApprovals(): array
    {
        return []; // Implement based on approval workflows
    }



    private function getSystemMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'active_sessions' => 0, // Would be implemented with session tracking
            'system_load' => 'normal',
        ];
    }

    private function getSecurityOverview(): array
    {
        return [
            'failed_logins_24h' => 0, // Would be implemented with audit logging
            'security_alerts' => [],
            'last_security_scan' => now()->subDays(1)->toISOString(),
        ];
    }

    private function getPlatformHealth(): array
    {
        return [
            'database_status' => 'healthy',
            'api_status' => 'operational',
            'storage_usage' => '45%',
        ];
    }
}
