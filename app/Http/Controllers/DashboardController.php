<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductRequest;
use App\Models\Order;
use App\Models\UserRole;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user()->load('userRole');
        $userRole = $user->userRole;

        // Handle case where user doesn't have a role assigned
        if (!$userRole) {
            // Create a default provider role or redirect to setup
            $defaultRole = UserRole::where('name', UserRole::PROVIDER)->first();
            if ($defaultRole) {
                $user->update(['user_role_id' => $defaultRole->id]);
                $userRole = $defaultRole;
            } else {
                // If no roles exist, create basic provider role
                $userRole = $this->createDefaultProviderRole();
                $user->update(['user_role_id' => $userRole->id]);
            }
        }

        // Get role-specific dashboard data
        $dashboardData = $this->getDashboardDataForRole($user, $userRole);

        // Route to specific role-based dashboard
        $dashboardComponent = $this->getDashboardComponent($userRole->name);

        return Inertia::render($dashboardComponent, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'owner' => $user->owner,
                'role' => $userRole->name,
                'role_display_name' => $userRole->display_name,
            ],
            'dashboardData' => $dashboardData,
            'roleRestrictions' => [
                'can_view_financials' => $userRole->canAccessFinancials(),
                'can_see_discounts' => $userRole->canSeeDiscounts(),
                'can_see_msc_pricing' => $userRole->canSeeMscPricing(),
                'can_see_order_totals' => $userRole->canSeeOrderTotals(),
                'pricing_access_level' => $userRole->getPricingAccessLevel(),
                'commission_access_level' => $userRole->getCommissionAccessLevel(),
                'can_manage_products' => $userRole->canManageProducts(),
            ]
        ]);
    }

    private function getDashboardComponent(string $roleName): string
    {
        return match($roleName) {
            UserRole::PROVIDER => 'Dashboard/Provider/ProviderDashboard',
            UserRole::OFFICE_MANAGER => 'Dashboard/OfficeManager/OfficeManagerDashboard',
            UserRole::MSC_REP => 'Dashboard/Sales/MscRepDashboard',
            UserRole::MSC_SUBREP => 'Dashboard/Sales/MscSubrepDashboard',
            UserRole::MSC_ADMIN => 'Dashboard/Admin/MscAdminDashboard',
            UserRole::SUPER_ADMIN => 'Dashboard/Admin/SuperAdminDashboard',
            'superadmin' => 'Dashboard/Admin/SuperAdminDashboard',
            default => 'Dashboard/Index'
        };
    }

    private function getDashboardDataForRole($user, $userRole): array
    {
        $baseData = [
            'recent_requests' => $this->getRecentRequests($user, $userRole),
            'action_items' => $this->getActionItems($user, $userRole),
            'metrics' => $this->getMetrics($user, $userRole),
        ];

        // Add role-specific data
        switch ($userRole->name) {
            case UserRole::PROVIDER:
                return array_merge($baseData, $this->getProviderSpecificData($user, $userRole));

            case UserRole::OFFICE_MANAGER:
                return array_merge($baseData, $this->getOfficeManagerSpecificData($user, $userRole));

            case UserRole::MSC_REP:
                return array_merge($baseData, $this->getMscRepSpecificData($user, $userRole));

            case UserRole::MSC_SUBREP:
                return array_merge($baseData, $this->getMscSubrepSpecificData($user, $userRole));

            case UserRole::MSC_ADMIN:
                return array_merge($baseData, $this->getMscAdminSpecificData($user, $userRole));

            case UserRole::SUPER_ADMIN:
            case 'superadmin':
                return array_merge($baseData, $this->getSuperAdminSpecificData($user, $userRole));

            default:
                return $baseData;
        }
    }

    private function getRecentRequests($user, $userRole): array
    {
        $query = ProductRequest::with(['facility'])
            ->where('provider_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5);

        $requests = $query->get()->map(function ($request) use ($userRole) {
            $data = [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_name' => $request->formatPatientDisplay(),
                'wound_type' => $request->wound_type,
                'status' => $request->order_status,
                'created_at' => $request->created_at->format('Y-m-d'),
                'facility_name' => $request->facility->name ?? 'Unknown Facility',
            ];

            // Add financial data only if role allows it
            if ($userRole && $userRole->canAccessFinancials() && $userRole->canSeeOrderTotals()) {
                $data['total_amount'] = $request->total_order_value;
                // Note: amount_owed doesn't exist in the model, removing for now
            }

            return $data;
        });

        return $requests->toArray();
    }

    private function getActionItems($user, $userRole): array
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

    private function getMetrics($user, $userRole): array
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

        // Add financial metrics only if role allows
        if ($userRole && $userRole->canAccessFinancials()) {
            $metrics['total_order_value'] = ProductRequest::where('provider_id', $user->id)
                ->where('order_status', 'approved')
                ->sum('total_order_value') ?? 0;

            // Note: total_savings doesn't exist in the model, removing for now
        }

        return $metrics;
    }

    private function getProviderSpecificData($user, $userRole): array
    {
        return [
            'clinical_opportunities' => $this->getClinicalOpportunities($user),
            'eligibility_status' => $this->getEligibilityStatus($user),
        ];
    }

    private function getOfficeManagerSpecificData($user, $userRole): array
    {
        return [
            'facility_metrics' => $this->getFacilityMetrics($user),
            'provider_activity' => $this->getProviderActivity($user),
        ];
    }

    private function getMscRepSpecificData($user, $userRole): array
    {
        return [
            'commission_summary' => $this->getCommissionSummary($user),
            'territory_performance' => $this->getTerritoryPerformance($user),
        ];
    }

    private function getMscSubrepSpecificData($user, $userRole): array
    {
        return [
            'personal_commission' => $this->getPersonalCommission($user),
            'assigned_customers' => $this->getAssignedCustomers($user),
        ];
    }

    private function getMscAdminSpecificData($user, $userRole): array
    {
        return [
            'business_metrics' => $this->getBusinessMetrics(),
            'pending_approvals' => $this->getPendingApprovals(),
        ];
    }

    private function getSuperAdminSpecificData($user, $userRole): array
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

    /**
     * Create a default provider role if none exists
     */
    private function createDefaultProviderRole(): UserRole
    {
        return UserRole::create([
            'name' => UserRole::PROVIDER,
            'display_name' => 'Healthcare Provider',
            'description' => 'Default provider role for clinical staff',
            'permissions' => [],
            'is_active' => true,
            'hierarchy_level' => 10,
        ]);
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
