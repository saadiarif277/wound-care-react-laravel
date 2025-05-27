// Simple role utilities that work with the robust RBAC system
// Role data now comes from the backend, not hard-coded definitions

export type UserRole = 'provider' | 'office-manager' | 'msc-rep' | 'msc-subrep' | 'msc-admin' | 'super-admin' | 'superadmin';

// Simple role display name mapping (the only frontend-specific data we need)
const ROLE_DISPLAY_NAMES: Record<UserRole, string> = {
  'provider': 'Healthcare Provider',
  'office-manager': 'Office Manager',
  'msc-rep': 'MSC Sales Representative',
  'msc-subrep': 'MSC Sub-Representative',
  'msc-admin': 'MSC Administrator',
  'super-admin': 'Super Administrator',
  'superadmin': 'Super Administrator',
};

// Dashboard titles for different roles
const DASHBOARD_TITLES: Record<UserRole, string> = {
  'provider': 'Provider Dashboard',
  'office-manager': 'Office Manager Dashboard',
  'msc-rep': 'MSC Sales Representative Dashboard',
  'msc-subrep': 'MSC Sub-Representative Dashboard',
  'msc-admin': 'MSC Administrator Dashboard',
  'super-admin': 'Super Administrator Dashboard',
  'superadmin': 'Super Administrator Dashboard',
};

// Simple utility functions
export function getRoleDisplayName(role: UserRole | string): string {
  if (!role) return 'User';

  // Handle both string and UserRole types
  const roleKey = role as UserRole;
  return ROLE_DISPLAY_NAMES[roleKey] || 'User';
}

export function getDashboardTitle(role: UserRole | string): string {
  if (!role) return 'Dashboard';

  const roleKey = role as UserRole;
  return DASHBOARD_TITLES[roleKey] || 'Dashboard';
}

export function getDashboardDescription(role: UserRole | string): string {
  // Simple descriptions - could be moved to backend if needed
  const descriptions: Record<UserRole, string> = {
    'provider': 'Clinical wound care workflows, patient management, product requests',
    'office-manager': 'Provider workflow access with facility-level management',
    'msc-rep': 'Customer relationship management, sales activities, commission tracking',
    'msc-subrep': 'Limited sales activities, territory support under main rep supervision',
    'msc-admin': 'Platform administration, system configuration, operational oversight',
    'super-admin': 'Full system access, critical system management, security oversight',
    'superadmin': 'Full system access, critical system management, security oversight',
  };

  const roleKey = role as UserRole;
  return descriptions[roleKey] || 'User dashboard';
}

// Legacy compatibility functions (these should use backend permission data)
export function hasPermission(role: UserRole | string, permission: string): boolean {
  // This should now check roleRestrictions from backend instead
  console.warn('hasPermission is deprecated - use roleRestrictions from backend props instead');
  return false;
}

export function getFeatureFlags(role: UserRole | string): any {
  // This should now use roleRestrictions from backend instead
  console.warn('getFeatureFlags is deprecated - use roleRestrictions from backend props instead');
  return {};
}

// Helper for navigation filtering (if still needed)
export function filterNavigationByRole(navigation: any[], role: UserRole | string): any[] {
  // Simple implementation - could be enhanced based on actual needs
  return navigation.filter(item => {
    if (!item.roles) return true;
    return item.roles.includes(role);
  });
}
