import { usePage } from '@inertiajs/react';

export const useRoleAccess = () => {
    const { auth } = usePage().props;
    const userRole = auth.user?.role?.name;

    const hasRole = (roles) => {
        if (!userRole) return false;
        if (Array.isArray(roles)) {
            return roles.includes(userRole);
        }
        return roles === userRole;
    };

    const hasAnyRole = (roles) => {
        return hasRole(roles);
    };

    const hasAllRoles = (roles) => {
        if (!userRole) return false;
        return roles.every(role => role === userRole);
    };

    return {
        userRole,
        hasRole,
        hasAnyRole,
        hasAllRoles,
    };
};
