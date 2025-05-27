// User Role System Types
export type UserRole =
  | 'provider'
  | 'office_manager'
  | 'msc_rep'
  | 'msc_subrep'
  | 'msc_admin'
  | 'super_admin'
  | 'superadmin';

export interface RoleDefinition {
  id: UserRole;
  name: string;
  description: string;
  category: 'healthcare' | 'sales' | 'admin';
  permissions: {
    canViewFinancials: boolean;
    canManageOrders: boolean;
    canViewCommissions: boolean;
    canManageUsers: boolean;
    canConfigureSystem: boolean;
    canViewReports: boolean;
    canManageTerritory: boolean;
    canViewFullPricing: boolean;
  };
  dashboardFeatures: string[];
  navigationItems: string[];
}

export interface UserWithRole {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  facility_id?: number;
  sales_rep_id?: number;
  territory?: string;
  owner: boolean; // Legacy field for backward compatibility
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

// Role-based Feature Flags
export interface FeatureFlags {
  showFinancials: boolean;
  showCommissions: boolean;
  showAdminTools: boolean;
  showSalesTools: boolean;
  showProviderTools: boolean;
  showReports: boolean;
}
