import { usePage } from '@inertiajs/react';

/**
 * Custom hook for checking user permissions
 * Works with the existing RBAC system and Inertia.js props
 */
export function usePermissions() {
    const { props } = usePage();
    const permissions = (props.permissions as string[]) || [];

    return {
        /**
         * Check if user has a specific permission
         */
        can: (permission: string): boolean => {
            return permissions.includes(permission);
        },

        /**
         * Check if user has any of the provided permissions
         */
        canAny: (permissionList: string[]): boolean => {
            return permissionList.some(permission => permissions.includes(permission));
        },

        /**
         * Check if user has all of the provided permissions
         */
        canAll: (permissionList: string[]): boolean => {
            return permissionList.every(permission => permissions.includes(permission));
        },

        /**
         * Get all user permissions
         */
        getPermissions: (): string[] => {
            return permissions;
        },

        /**
         * Check financial access levels based on permissions
         * Office managers have NO financial access
         */
        getFinancialAccess: () => {
            const canViewFinancials = permissions.includes('view-financials');
            const canSeeDiscounts = permissions.includes('view-discounts');
            const canSeeMscPricing = permissions.includes('view-msc-pricing');
            const canSeeOrderTotals = permissions.includes('view-order-totals');
            const canViewNationalAsp = permissions.includes('view-national-asp');

            return {
                canViewFinancials,
                canSeeDiscounts,
                canSeeMscPricing,
                canSeeOrderTotals,
                pricingAccessLevel: canSeeMscPricing && canSeeDiscounts ? 'full' : 
                                 canViewNationalAsp ? 'asp' : 
                                 'none'
            };
        },

        /**
         * Check commission access levels based on permissions
         */
        getCommissionAccess: () => {
            const canViewCommission = permissions.includes('view-commission');
            const canViewCommissionLimited = permissions.includes('view-commission-limited');
            const canViewCommissionFull = permissions.includes('view-commission-full');

            if (canViewCommissionFull) return 'full';
            if (canViewCommissionLimited) return 'limited';
            if (canViewCommission) return 'basic';
            return 'none';
        }
    };
} 