import { UserRole, RoleDefinition, FeatureFlags, UserWithRole } from '@/types/roles';

// Role Definitions
export const ROLE_DEFINITIONS: Record<UserRole, RoleDefinition> = {
  provider: {
    id: 'provider',
    name: 'Healthcare Provider',
    description: 'Clinical wound care workflows, patient management, product requests',
    category: 'healthcare',
    permissions: {
      canViewFinancials: true, // Can see amounts owed on orders
      canManageOrders: true,
      canViewCommissions: false,
      canManageUsers: false,
      canConfigureSystem: false,
      canViewReports: true,
      canManageTerritory: false,
      canViewFullPricing: true, // Can see full pricing including discounts
    },
    dashboardFeatures: [
      'clinical_opportunities',
      'recent_requests',
      'action_required',
      'quick_actions_clinical',
      'eligibility_status',
      'order_tracking'
    ],
    navigationItems: [
      'dashboard',
      'orders',
      'products',
      'eligibility',
      'reports'
    ]
  },
  office_manager: {
    id: 'office_manager',
    name: 'Office Manager',
    description: 'Provider workflow access with financial restrictions, facility-level management',
    category: 'healthcare',
    permissions: {
      canViewFinancials: false, // Cannot see amounts owed, discounts, or commission data
      canManageOrders: true,
      canViewCommissions: false,
      canManageUsers: false,
      canConfigureSystem: false,
      canViewReports: true,
      canManageTerritory: false,
      canViewFullPricing: false, // Only sees National ASP pricing
    },
    dashboardFeatures: [
      'provider_coordination',
      'facility_management',
      'recent_requests',
      'action_required',
      'quick_actions_admin',
      'operational_metrics'
    ],
    navigationItems: [
      'dashboard',
      'orders',
      'products',
      'eligibility',
      'contacts',
      'reports'
    ]
  },
  msc_rep: {
    id: 'msc_rep',
    name: 'MSC Sales Representative',
    description: 'Customer relationship management, sales activities, commission tracking',
    category: 'sales',
    permissions: {
      canViewFinancials: true,
      canManageOrders: true,
      canViewCommissions: true,
      canManageUsers: false,
      canConfigureSystem: false,
      canViewReports: true,
      canManageTerritory: true,
      canViewFullPricing: true,
    },
    dashboardFeatures: [
      'commission_tracking',
      'customer_management',
      'territory_analytics',
      'sales_performance',
      'subrep_management',
      'recent_activity'
    ],
    navigationItems: [
      'dashboard',
      'commission',
      'customers',
      'territory',
      'orders',
      'products',
      'reports'
    ]
  },
  msc_subrep: {
    id: 'msc_subrep',
    name: 'MSC Sub-Representative',
    description: 'Limited sales activities, territory support under main rep supervision',
    category: 'sales',
    permissions: {
      canViewFinancials: false,
      canManageOrders: true,
      canViewCommissions: true, // Limited commission access
      canManageUsers: false,
      canConfigureSystem: false,
      canViewReports: false,
      canManageTerritory: false,
      canViewFullPricing: true,
    },
    dashboardFeatures: [
      'limited_commission_access',
      'customer_support',
      'subrep_activities',
      'territory_support',
      'recent_activity'
    ],
    navigationItems: [
      'dashboard',
      'commission',
      'customers',
      'orders',
      'products'
    ]
  },
  msc_admin: {
    id: 'msc_admin',
    name: 'MSC Administrator',
    description: 'Platform administration, system configuration, operational oversight',
    category: 'admin',
    permissions: {
      canViewFinancials: true,
      canManageOrders: true,
      canViewCommissions: true,
      canManageUsers: true,
      canConfigureSystem: true,
      canViewReports: true,
      canManageTerritory: false,
      canViewFullPricing: true,
    },
    dashboardFeatures: [
      'system_administration',
      'user_management',
      'operational_metrics',
      'pending_approvals',
      'system_health',
      'configuration_tools'
    ],
    navigationItems: [
      'dashboard',
      'orders',
      'users',
      'organizations',
      'commission',
      'products',
      'reports',
      'system_config'
    ]
  },
  superadmin: {
    id: 'superadmin',
    name: 'Super Administrator',
    description: 'Full system access, critical system management, security oversight',
    category: 'admin',
    permissions: {
      canViewFinancials: true,
      canManageOrders: true,
      canViewCommissions: true,
      canManageUsers: true,
      canConfigureSystem: true,
      canViewReports: true,
      canManageTerritory: true,
      canViewFullPricing: true,
    },
    dashboardFeatures: [
      'system_wide_control',
      'security_monitoring',
      'critical_operations',
      'audit_oversight',
      'all_metrics',
      'advanced_configuration'
    ],
    navigationItems: [
      'dashboard',
      'orders',
      'users',
      'organizations',
      'commission',
      'products',
      'reports',
      'system_config',
      'security',
      'audit'
    ]
  }
};

// Utility Functions
export function getRoleDefinition(role: UserRole): RoleDefinition {
  return ROLE_DEFINITIONS[role];
}

export function hasPermission(role: UserRole, permission: keyof RoleDefinition['permissions']): boolean {
  return ROLE_DEFINITIONS[role].permissions[permission];
}

export function getFeatureFlags(role: UserRole): FeatureFlags {
  const roleDef = getRoleDefinition(role);

  return {
    showFinancials: roleDef.permissions.canViewFinancials,
    showCommissions: roleDef.permissions.canViewCommissions,
    showAdminTools: roleDef.permissions.canManageUsers || roleDef.permissions.canConfigureSystem,
    showSalesTools: roleDef.category === 'sales',
    showProviderTools: roleDef.category === 'healthcare',
    showReports: roleDef.permissions.canViewReports,
  };
}

export function getUserRole(user: UserWithRole): UserRole {
  // For backward compatibility with existing owner field
  if (user.role) {
    return user.role;
  }

  // Legacy fallback
  return user.owner ? 'msc_admin' : 'provider';
}

export function canAccessRoute(role: UserRole, routeName: string): boolean {
  const roleDef = getRoleDefinition(role);
  return roleDef.navigationItems.includes(routeName);
}

export function getRoleDisplayName(role: UserRole): string {
  return ROLE_DEFINITIONS[role].name;
}

export function getRolesByCategory(category: 'healthcare' | 'sales' | 'admin'): UserRole[] {
  return Object.keys(ROLE_DEFINITIONS).filter(
    role => ROLE_DEFINITIONS[role as UserRole].category === category
  ) as UserRole[];
}

export function isHigherRole(role1: UserRole, role2: UserRole): boolean {
  const hierarchy = ['provider', 'office_manager', 'msc_subrep', 'msc_rep', 'msc_admin', 'superadmin'];
  return hierarchy.indexOf(role1) > hierarchy.indexOf(role2);
}

// Dashboard Content Helpers
export function getDashboardTitle(role: UserRole): string {
  const titles = {
    provider: 'Provider Dashboard',
    office_manager: 'Office Manager Dashboard',
    msc_rep: 'MSC Sales Representative Dashboard',
    msc_subrep: 'MSC Sub-Representative Dashboard',
    msc_admin: 'MSC Administrator Dashboard',
    superadmin: 'Super Administrator Dashboard'
  };

  return titles[role];
}

export function getDashboardDescription(role: UserRole): string {
  return ROLE_DEFINITIONS[role].description;
}

// Filter functions for role-based content
export function filterNavigationByRole(navigation: any[], role: UserRole): any[] {
  const allowedItems = ROLE_DEFINITIONS[role].navigationItems;

  return navigation.filter(item => {
    // Check if item is allowed for this role
    if (item.roles && !item.roles.includes(role)) {
      return false;
    }

    // Check if item name is in allowed navigation items
    const itemKey = item.href?.replace('/', '') || item.name?.toLowerCase();
    return allowedItems.includes(itemKey);
  });
}

export function filterQuickActionsByRole(actions: any[], role: UserRole): any[] {
  return actions.filter(action =>
    !action.roles || action.roles.includes(role)
  );
}
