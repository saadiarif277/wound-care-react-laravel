<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Order\Order;

class SalesRepAnalyticsController extends Controller
{
    /**
     * Get analytics data for sales representatives
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get date range from request or default to last 30 days
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        
        // Base query for analytics based on role
        $analyticsData = $this->getAnalyticsForUser($user, $startDate, $endDate);
        
        return response()->json([
            'data' => $analyticsData,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }
    
    /**
     * Get summary analytics
     */
    public function summary(Request $request)
    {
        $user = Auth::user();
        
        $summary = [
            'total_sales' => $this->getTotalSales($user),
            'monthly_sales' => $this->getMonthlySales($user),
            'total_customers' => $this->getTotalCustomers($user),
            'active_customers' => $this->getActiveCustomers($user),
            'conversion_rate' => $this->getConversionRate($user),
            'average_order_value' => $this->getAverageOrderValue($user)
        ];
        
        return response()->json(['data' => $summary]);
    }
    
    /**
     * Get performance metrics
     */
    public function performance(Request $request)
    {
        $user = Auth::user();
        $period = $request->input('period', 'monthly'); // monthly, quarterly, yearly
        
        $performance = [
            'sales_trend' => $this->getSalesTrend($user, $period),
            'top_products' => $this->getTopProducts($user),
            'customer_growth' => $this->getCustomerGrowth($user, $period),
            'commission_earned' => $this->getCommissionEarned($user, $period)
        ];
        
        return response()->json(['data' => $performance]);
    }
    
    /**
     * Get territory analytics
     */
    public function territories(Request $request)
    {
        $user = Auth::user();
        
        $territories = [
            'assigned_territories' => $this->getAssignedTerritories($user),
            'territory_performance' => $this->getTerritoryPerformance($user),
            'territory_coverage' => $this->getTerritoryCoverage($user),
            'opportunity_areas' => $this->getOpportunityAreas($user)
        ];
        
        return response()->json(['data' => $territories]);
    }
    
    /**
     * Private helper methods
     */
    private function getAnalyticsForUser($user, $startDate, $endDate)
    {
        // Basic analytics structure
        return [
            'orders_count' => Order::where('sales_rep_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'revenue' => Order::where('sales_rep_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount'),
            'new_customers' => DB::table('organization_users')
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'pending_orders' => Order::where('sales_rep_id', $user->id)
                ->where('status', 'pending')
                ->count()
        ];
    }
    
    private function getTotalSales($user)
    {
        return Order::where('sales_rep_id', $user->id)->sum('total_amount') ?? 0;
    }
    
    private function getMonthlySales($user)
    {
        return Order::where('sales_rep_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount') ?? 0;
    }
    
    private function getTotalCustomers($user)
    {
        return DB::table('organization_users')
            ->where('user_id', $user->id)
            ->distinct('organization_id')
            ->count();
    }
    
    private function getActiveCustomers($user)
    {
        return DB::table('orders')
            ->join('organization_users', 'orders.organization_id', '=', 'organization_users.organization_id')
            ->where('organization_users.user_id', $user->id)
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->distinct('orders.organization_id')
            ->count('orders.organization_id');
    }
    
    private function getConversionRate($user)
    {
        // Placeholder calculation
        return 0.65; // 65% conversion rate
    }
    
    private function getAverageOrderValue($user)
    {
        $avg = Order::where('sales_rep_id', $user->id)->avg('total_amount');
        return round($avg ?? 0, 2);
    }
    
    private function getSalesTrend($user, $period)
    {
        // Placeholder data
        return [
            ['month' => 'Jan', 'sales' => 45000],
            ['month' => 'Feb', 'sales' => 52000],
            ['month' => 'Mar', 'sales' => 48000],
            ['month' => 'Apr', 'sales' => 61000],
            ['month' => 'May', 'sales' => 58000],
            ['month' => 'Jun', 'sales' => 67000]
        ];
    }
    
    private function getTopProducts($user)
    {
        // Placeholder data
        return [
            ['product' => 'Wound Care Kit A', 'units_sold' => 150, 'revenue' => 15000],
            ['product' => 'Surgical Dressing B', 'units_sold' => 120, 'revenue' => 12000],
            ['product' => 'Compression Bandage C', 'units_sold' => 90, 'revenue' => 9000]
        ];
    }
    
    private function getCustomerGrowth($user, $period)
    {
        // Placeholder data
        return [
            'new_customers' => 12,
            'retained_customers' => 45,
            'churned_customers' => 3,
            'growth_rate' => 0.15 // 15% growth
        ];
    }
    
    private function getCommissionEarned($user, $period)
    {
        // Placeholder data
        return [
            'total_commission' => 8500,
            'pending_commission' => 1200,
            'paid_commission' => 7300
        ];
    }
    
    private function getAssignedTerritories($user)
    {
        // Placeholder data
        return [
            ['territory' => 'Northeast Region', 'zip_codes' => ['10001', '10002', '10003']],
            ['territory' => 'Mid-Atlantic', 'zip_codes' => ['20001', '20002']]
        ];
    }
    
    private function getTerritoryPerformance($user)
    {
        // Placeholder data
        return [
            ['territory' => 'Northeast Region', 'revenue' => 125000, 'customers' => 25],
            ['territory' => 'Mid-Atlantic', 'revenue' => 95000, 'customers' => 18]
        ];
    }
    
    private function getTerritoryCoverage($user)
    {
        // Placeholder data
        return [
            'total_facilities' => 150,
            'covered_facilities' => 95,
            'coverage_percentage' => 0.63 // 63% coverage
        ];
    }
    
    private function getOpportunityAreas($user)
    {
        // Placeholder data
        return [
            ['area' => 'Uncovered ZIP 10004', 'potential_customers' => 8, 'estimated_revenue' => 25000],
            ['area' => 'Low penetration in 20003', 'potential_customers' => 5, 'estimated_revenue' => 15000]
        ];
    }
}