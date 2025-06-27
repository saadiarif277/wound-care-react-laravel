import React from 'react';
import { useRoleAccess } from '@/Hooks/useRoleAccess';
import { router } from '@inertiajs/react';

export const withRoleAccess = (WrappedComponent, allowedRoles) => {
    return function WithRoleAccessComponent(props) {
        const { hasRole } = useRoleAccess();

        React.useEffect(() => {
            if (!hasRole(allowedRoles)) {
                router.visit('/unauthorized');
            }
        }, [hasRole]);

        if (!hasRole(allowedRoles)) {
            return null;
        }

        return <WrappedComponent {...props} />;
    };
};
