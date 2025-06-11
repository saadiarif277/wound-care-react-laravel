import { usePage } from '@inertiajs/react';

/**
 * Hook to get current user's permissions
 */
export function usePermissions(): string[] {
  const { permissions = [] } = usePage().props as { permissions?: string[] };
  return permissions;
}

/**
 * Hook to check if user has a specific permission
 */
export function useHasPermission(permission: string): boolean {
  const permissions = usePermissions();
  return permissions.includes(permission);
}

/**
 * Hook to check if user has any of the specified permissions
 */
export function useHasAnyPermission(requiredPermissions: string[]): boolean {
  const permissions = usePermissions();
  return requiredPermissions.some(permission => permissions.includes(permission));
}

/**
 * Hook to check if user has all of the specified permissions
 */
export function useHasAllPermissions(requiredPermissions: string[]): boolean {
  const permissions = usePermissions();
  return requiredPermissions.every(permission => permissions.includes(permission));
}

/**
 * Component to conditionally render children based on permissions
 */
interface CanProps {
  permission?: string;
  permissions?: string[];
  requireAll?: boolean;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function Can({ permission, permissions, requireAll = false, children, fallback = null }: CanProps) {
  const userPermissions = usePermissions();
  
  let hasPermission = false;
  
  if (permission) {
    hasPermission = userPermissions.includes(permission);
  } else if (permissions) {
    if (requireAll) {
      hasPermission = permissions.every(p => userPermissions.includes(p));
    } else {
      hasPermission = permissions.some(p => userPermissions.includes(p));
    }
  } else {
    // If no permissions specified, allow access
    hasPermission = true;
  }
  
  return hasPermission ? <>{children}</> : <>{fallback}</>;
} 