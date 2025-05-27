<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRole extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
        'is_active',
        'hierarchy_level',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Role constants for easy reference
     */
    public const PROVIDER = 'provider';
    public const OFFICE_MANAGER = 'office_manager';
    public const MSC_REP = 'msc_rep';
    public const MSC_SUBREP = 'msc_subrep';
    public const MSC_ADMIN = 'msc_admin';
    public const SUPER_ADMIN = 'super_admin';

    /**
     * Get active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get roles by hierarchy level (lower number = higher privilege)
     */
    public function scopeByHierarchy($query, $maxLevel = null)
    {
        $query = $query->orderBy('hierarchy_level');

        if ($maxLevel !== null) {
            $query->where('hierarchy_level', '<=', $maxLevel);
        }

        return $query;
    }

    /**
     * Check if this role has higher privilege than another role
     */
    public function hasHigherPrivilegeThan(UserRole $otherRole): bool
    {
        return $this->hierarchy_level < $otherRole->hierarchy_level;
    }

    /**
     * Get role-specific dashboard configuration
     */
    public function getDashboardConfig(): array
    {
        $configs = [
            self::PROVIDER => [
                'widgets' => ['clinical_opportunities', 'recent_requests', 'action_required', 'eligibility_status'],
                'quick_actions' => ['new_request', 'eligibility_check', 'view_catalog'],
                'menu_items' => ['dashboard', 'product-requests', 'eligibility', 'products'],
                'financial_access' => true,
                'pricing_access' => 'full', // Can see full pricing including discounts
                'commission_access' => 'none', // CRITICAL: Provider should NOT see commission data
            ],
            self::OFFICE_MANAGER => [
                'widgets' => ['provider_coordination', 'facility_management', 'recent_requests', 'operational_metrics'],
                'quick_actions' => ['new_request', 'manage_providers', 'facility_requests'],
                'menu_items' => ['dashboard', 'product-requests', 'eligibility', 'products', 'providers'],
                'financial_access' => false, // CRITICAL: NO financial data visible
                'pricing_access' => 'national_asp_only', // CRITICAL: Only National ASP pricing
                'restrictions' => [
                    'no_financial_totals',
                    'no_amounts_owed',
                    'no_discounts',
                    'no_msc_pricing',
                    'no_special_rates'
                ]
            ],
            self::MSC_REP => [
                'widgets' => ['commission_tracking', 'customer_management', 'sales_performance', 'team_management'],
                'quick_actions' => ['view_commissions', 'manage_customers', 'team_invite'],
                'menu_items' => ['dashboard', 'orders', 'commission', 'customers'],
                'financial_access' => true,
                'pricing_access' => 'full',
                'commission_access' => 'full', // MSC Rep can see full commission data
                'customer_data_restrictions' => ['no_phi'] // Can see product & commission, NO PHI
            ],
            self::MSC_SUBREP => [
                'widgets' => ['limited_commission_access', 'customer_support', 'recent_activity'],
                'quick_actions' => ['view_commission', 'customer_support'],
                'menu_items' => ['dashboard', 'orders', 'commission'],
                'financial_access' => false,
                'pricing_access' => 'limited',
                'commission_access' => 'limited', // Limited commission access only
                'customer_data_restrictions' => ['no_phi', 'view_only'] // View only, NO PHI
            ],
            self::MSC_ADMIN => [
                'widgets' => ['system_administration', 'user_management', 'pending_approvals', 'operational_metrics'],
                'quick_actions' => ['manage_requests', 'create_orders', 'manage_users'],
                'menu_items' => ['dashboard', 'requests', 'orders', 'management', 'settings'],
                'financial_access' => true, // FULL financial visibility
                'pricing_access' => 'full',
                'commission_access' => 'full', // MSC Admin can see full commission data
                'admin_capabilities' => [
                    'create_manual_orders',
                    'manage_all_orders',
                    'product_management',
                    'clinical_rules',
                    'recommendation_rules',
                    'commission_management',
                    'user_approval',
                    'subrep_approval'
                ]
            ],
            self::SUPER_ADMIN => [
                'widgets' => ['system_wide_control', 'security_monitoring', 'audit_oversight', 'all_metrics'],
                'quick_actions' => ['system_config', 'rbac_config', 'audit_review'],
                'menu_items' => ['dashboard', 'requests', 'orders', 'commission', 'management', 'system-admin'],
                'financial_access' => true, // Complete financial access
                'pricing_access' => 'full',
                'commission_access' => 'full', // Super Admin can see full commission data
                'admin_capabilities' => [
                    'rbac_configuration',
                    'system_access_control',
                    'role_management',
                    'platform_configuration',
                    'integration_settings',
                    'api_management',
                    'audit_logs'
                ]
            ],
        ];

        // Handle both 'super_admin' and 'superadmin' role names
        $roleName = $this->name;
        if ($roleName === 'superadmin') {
            $roleName = self::SUPER_ADMIN;
        }

        return $configs[$roleName] ?? $configs[self::PROVIDER];
    }

    /**
     * Check if role can access financial information
     */
    public function canAccessFinancials(): bool
    {
        $config = $this->getDashboardConfig();
        return $config['financial_access'] ?? false;
    }

    /**
     * Check if role can see discounted pricing
     */
    public function canSeeDiscounts(): bool
    {
        return !in_array($this->name, [self::OFFICE_MANAGER, self::MSC_SUBREP]);
    }

    /**
     * Check if role can see MSC pricing
     */
    public function canSeeMscPricing(): bool
    {
        return !in_array($this->name, [self::OFFICE_MANAGER]);
    }

    /**
     * Check if role can see order totals and amounts owed
     */
    public function canSeeOrderTotals(): bool
    {
        $config = $this->getDashboardConfig();
        $restrictions = $config['restrictions'] ?? [];
        return !in_array('no_financial_totals', $restrictions);
    }

    /**
     * Get pricing access level for this role
     */
    public function getPricingAccessLevel(): string
    {
        $config = $this->getDashboardConfig();
        return $config['pricing_access'] ?? 'limited';
    }

    /**
     * Check if role has customer data restrictions
     */
    public function hasCustomerDataRestrictions(): array
    {
        $config = $this->getDashboardConfig();
        return $config['customer_data_restrictions'] ?? [];
    }

    /**
     * Check if role can view PHI (Protected Health Information)
     */
    public function canViewPhi(): bool
    {
        $restrictions = $this->hasCustomerDataRestrictions();
        return !in_array('no_phi', $restrictions);
    }

    /**
     * Check if role can manage orders (vs view only)
     */
    public function canManageOrders(): bool
    {
        $restrictions = $this->hasCustomerDataRestrictions();
        return !in_array('view_only', $restrictions) &&
               !in_array($this->name, [self::MSC_SUBREP]);
    }

    /**
     * Get commission access level
     */
    public function getCommissionAccessLevel(): string
    {
        $config = $this->getDashboardConfig();
        return $config['commission_access'] ?? 'none';
    }

    /**
     * Check if role has admin capabilities
     */
    public function hasAdminCapability(string $capability): bool
    {
        $config = $this->getDashboardConfig();
        $capabilities = $config['admin_capabilities'] ?? [];
        return in_array($capability, $capabilities);
    }

    /**
     * Check if role can manage products (create/edit/delete)
     */
    public function canManageProducts(): bool
    {
        return $this->hasAdminCapability('product_management') ||
               in_array($this->name, [self::MSC_ADMIN, self::SUPER_ADMIN, 'superadmin']);
    }
}
