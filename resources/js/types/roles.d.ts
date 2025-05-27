// Updated role types for the robust RBAC system

export type UserRole = 'provider' | 'office-manager' | 'msc-rep' | 'msc-subrep' | 'msc-admin' | 'super-admin' | 'superadmin';

// User interface with role information
export interface UserWithRole {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role?: UserRole;
  roles?: Array<{
    id: number;
    name: string;
    slug: UserRole;
  }>;
  owner?: boolean; // Legacy field for backward compatibility
}

// Role restrictions from backend (permission-based)
export interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  can_view_phi: boolean;
  is_super_admin: boolean;
  is_msc_admin: boolean;
  is_provider: boolean;
}

// Legacy interfaces kept for compatibility (but deprecated)
// These should be replaced with RoleRestrictions over time
export interface FeatureFlags {
  showFinancials: boolean;
  showCommissions: boolean;
  showAdminTools: boolean;
  showSalesTools: boolean;
  showProviderTools: boolean;
  showReports: boolean;
}

export interface RolePermissions {
  canViewFinancials: boolean;
  canManageOrders: boolean;
  canViewCommissions: boolean;
  canManageUsers: boolean;
  canConfigureSystem: boolean;
  canViewReports: boolean;
  canManageTerritory: boolean;
  canViewFullPricing: boolean;
}

export interface RoleDefinition {
  id: string;
  name: string;
  description: string;
  category: 'healthcare' | 'sales' | 'admin';
  permissions: RolePermissions;
  dashboardFeatures: string[];
  navigationItems: string[];
}

export interface DashboardData {
  actionItems: ActionItem[];
  recentActivity: RecentActivity[];
  metrics: DashboardMetrics;
  quickActions: QuickAction[];
}

export interface ActionItem {
  id: string;
  title: string;
  description: string;
  priority: 'high' | 'medium' | 'low';
  type: 'approval' | 'document' | 'validation' | 'review';
  url: string;
  dueDate?: string;
  count?: number;
}

export interface RecentActivity {
  id: string;
  title: string;
  description: string;
  timestamp: string;
  status: 'pending' | 'approved' | 'completed' | 'cancelled';
  type: 'order' | 'request' | 'eligibility' | 'pa';
  url?: string;
}

export interface DashboardMetrics {
  totalOrders?: number;
  pendingApprovals?: number;
  monthlyCommission?: number;
  activeRequests?: number;
  eligibilityChecks?: number;
  conversionRate?: number;
}

export interface QuickAction {
  id: string;
  title: string;
  description: string;
  icon: string;
  url: string;
  color: string;
  roles: UserRole[];
}

// Navigation Types
export interface NavigationItem {
  name: string;
  href: string;
  icon: string;
  current: boolean;
  badge?: number;
  roles: UserRole[];
  children?: NavigationItem[];
}
