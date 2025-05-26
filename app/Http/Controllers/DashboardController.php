<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductRequest;
use App\Models\Order;
use App\Models\UserRole;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user()->load('userRole');
        $userRole = $user->userRole;

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

            default:
                return $baseData;
        }
    }

    private function getRecentRequests($user, $userRole): array
    {
        $query = ProductRequest::with(['facility'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5);

        $requests = $query->get()->map(function ($request) use ($userRole) {
            $data = [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_name' => $request->patient_first_name . ' ' . $request->patient_last_name,
                'wound_type' => $request->wound_type,
                'status' => $request->status,
                'created_at' => $request->created_at->format('Y-m-d'),
                'facility_name' => $request->facility->name ?? 'Unknown Facility',
            ];

            // Add financial data only if role allows it
            if ($userRole->canAccessFinancials() && $userRole->canSeeOrderTotals()) {
                $data['total_amount'] = $request->total_amount;
                $data['amount_owed'] = $request->amount_owed;
            }

            return $data;
        });

        return $requests->toArray();
    }

    private function getActionItems($user, $userRole): array
    {
        // Get requests that need action
        $actionItems = ProductRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending_documentation', 'pending_eligibility', 'pending_pa'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'type' => $this->getActionType($request->status),
                    'patient_name' => $request->patient_first_name . ' ' . $request->patient_last_name,
                    'description' => $this->getActionDescription($request->status),
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
            'total_requests' => ProductRequest::where('user_id', $user->id)->count(),
            'pending_requests' => ProductRequest::where('user_id', $user->id)
                ->whereIn('status', ['draft', 'submitted', 'processing'])
                ->count(),
            'approved_requests' => ProductRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->count(),
        ];

        // Add financial metrics only if role allows
        if ($userRole->canAccessFinancials()) {
            $metrics['total_amount_owed'] = ProductRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('amount_owed') ?? 0;

            $metrics['total_savings'] = ProductRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('total_savings') ?? 0;
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

    // Helper methods for specific data types
    private function getActionType($status): string
    {
        return match($status) {
            'pending_documentation' => 'documentation_required',
            'pending_eligibility' => 'eligibility_check',
            'pending_pa' => 'pa_approval',
            default => 'review_required'
        };
    }

    private function getActionDescription($status): string
    {
        return match($status) {
            'pending_documentation' => 'Additional documentation required',
            'pending_eligibility' => 'Insurance eligibility verification needed',
            'pending_pa' => 'Prior authorization required',
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
}
