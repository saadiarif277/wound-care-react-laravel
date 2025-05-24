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
                'widgets' => ['quick_actions', 'recent_requests', 'pending_orders', 'clinical_opportunities'],
                'quick_actions' => ['new_request', 'view_orders', 'eligibility_check'],
                'menu_items' => ['dashboard', 'requests', 'products', 'orders', 'profile'],
            ],
            self::OFFICE_MANAGER => [
                'widgets' => ['facility_overview', 'provider_activity', 'pending_orders', 'notifications'],
                'quick_actions' => ['new_request', 'manage_providers', 'view_orders'],
                'menu_items' => ['dashboard', 'requests', 'products', 'orders', 'providers', 'facility'],
            ],
            self::MSC_REP => [
                'widgets' => ['sales_performance', 'commission_tracking', 'territory_overview', 'opportunities'],
                'quick_actions' => ['view_commissions', 'territory_analysis', 'customer_outreach'],
                'menu_items' => ['dashboard', 'commissions', 'territory', 'customers', 'reports'],
            ],
            self::MSC_SUBREP => [
                'widgets' => ['limited_commission', 'assigned_accounts', 'rep_coordination'],
                'quick_actions' => ['view_assignments', 'contact_rep', 'customer_support'],
                'menu_items' => ['dashboard', 'assignments', 'customers', 'reports'],
            ],
            self::MSC_ADMIN => [
                'widgets' => ['system_overview', 'user_management', 'platform_health', 'analytics'],
                'quick_actions' => ['manage_users', 'system_config', 'view_analytics'],
                'menu_items' => ['dashboard', 'users', 'system', 'analytics', 'reports', 'settings'],
            ],
            self::SUPER_ADMIN => [
                'widgets' => ['complete_system_access', 'security_monitoring', 'audit_logs'],
                'quick_actions' => ['system_admin', 'security_review', 'audit_access'],
                'menu_items' => ['dashboard', 'users', 'system', 'security', 'audit', 'settings'],
            ],
        ];

        return $configs[$this->name] ?? $configs[self::PROVIDER];
    }

    /**
     * Check if role can access financial information
     */
    public function canAccessFinancials(): bool
    {
        return !in_array($this->name, [self::OFFICE_MANAGER]);
    }

    /**
     * Check if role can see discounted pricing
     */
    public function canSeeDiscounts(): bool
    {
        return !in_array($this->name, [self::OFFICE_MANAGER]);
    }
}
