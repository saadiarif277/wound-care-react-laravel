import { usePage } from '@inertiajs/react';

interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role?: string;
  permissions?: string[];
}

interface FinancialPermissions {
  canViewCommonFinancial: boolean;
  canViewMyFinancial: boolean;
  canViewAllFinancial: boolean;
  hasNoFinancialAccess: boolean;
  getDisplayPrice: (asp?: number, mscPrice?: number, providerPrice?: number) => string | null;
  shouldShowPricing: boolean;
  accessLevel: 'common' | 'personal' | 'all' | 'none';
}

export function useFinancialPermissions(): FinancialPermissions {
  const { props } = usePage();
  const user = props.auth?.user as User;
  const permissions = props.permissions as string[] || [];

  // Check for the 4-tier permission system
  const canViewCommonFinancial = permissions.includes('common-financial-data');
  const canViewMyFinancial = permissions.includes('my-financial-data');
  const canViewAllFinancial = permissions.includes('view-all-financial-data');
  const hasNoFinancialAccess = permissions.includes('no-financial-data');

  // Determine access level
  let accessLevel: 'common' | 'personal' | 'all' | 'none' = 'none';
  if (canViewAllFinancial) {
    accessLevel = 'all';
  } else if (canViewMyFinancial) {
    accessLevel = 'personal';
  } else if (canViewCommonFinancial) {
    accessLevel = 'common';
  }

  // Should show any pricing at all
  const shouldShowPricing = !hasNoFinancialAccess;

  // Function to get appropriate price display
  const getDisplayPrice = (asp?: number, mscPrice?: number, providerPrice?: number): string | null => {
    if (hasNoFinancialAccess) {
      return null; // Hide completely
    }

    if (canViewAllFinancial) {
      // Admin can see all pricing
      return formatPrice(providerPrice || mscPrice || asp);
    }

    if (canViewMyFinancial) {
      // Provider can see their specific pricing
      return formatPrice(providerPrice || mscPrice);
    }

    if (canViewCommonFinancial) {
      // Can see national ASP and default MSC pricing (40% off ASP)
      const defaultMscPrice = asp ? asp * 0.6 : null;
      return formatPrice(defaultMscPrice || asp);
    }

    return null;
  };

  const formatPrice = (price?: number): string => {
    if (!price) return 'N/A';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  return {
    canViewCommonFinancial,
    canViewMyFinancial,
    canViewAllFinancial,
    hasNoFinancialAccess,
    getDisplayPrice,
    shouldShowPricing,
    accessLevel
  };
}

/**
 * Hook for backward compatibility with existing role restrictions
 * Maps new 4-tier system to old roleRestrictions interface
 */
export function useLegacyRoleRestrictions() {
  const financialPerms = useFinancialPermissions();
  
  return {
    can_view_financials: financialPerms.canViewCommonFinancial,
    can_see_discounts: financialPerms.canViewCommonFinancial, // Assuming 'common' tier has discounts
    can_see_msc_pricing: financialPerms.canViewCommonFinancial, // Assuming 'common' tier has MSC pricing
    can_see_order_totals: financialPerms.canViewCommonFinancial, // Assuming 'common' tier has order totals
    pricing_access_level: financialPerms.accessLevel === 'all' ? 'full' : 
                         financialPerms.accessLevel === 'personal' ? 'provider' :
                         financialPerms.accessLevel === 'common' ? 'limited' : 'none',
    commission_access_level: financialPerms.canViewMyFinancial ? 'full' : 'none' // Assuming 'my-financial-data' has commissions
  };
}

/**
 * Helper function to get pricing display text based on permissions
 */
export function getPricingLabel(financialPerms: FinancialPermissions): string {
  if (financialPerms.hasNoFinancialAccess) {
    return ''; // No label shown for office managers
  }
  
  if (financialPerms.canViewAllFinancial) {
    return 'All Pricing Data';
  }
  
  if (financialPerms.canViewMyFinancial) {
    return 'Provider Pricing';
  }
  
  if (financialPerms.canViewCommonFinancial) {
    return 'Market Pricing';
  }
  
  return '';
}

/**
 * Helper function to determine which price to show for a product
 */
export function getDisplayPrice(
  financialPerms: FinancialPermissions,
  nationalAsp: number,
  mscPrice?: number,
  providerSpecificPrice?: number
): number | null {
  if (financialPerms.hasNoFinancialAccess) {
    return null; // No price shown
  }
  
  if (financialPerms.canViewAllFinancial) {
    // Admins see the best available price
    return providerSpecificPrice || mscPrice || nationalAsp;
  }
  
  if (financialPerms.canViewMyFinancial) {
    // Providers see their specific pricing or MSC default
    return providerSpecificPrice || mscPrice || nationalAsp;
  }
  
  if (financialPerms.canViewCommonFinancial) {
    // Common tier sees national ASP and default MSC pricing (40% off ASP)
    return mscPrice || nationalAsp;
  }
  
  return null;
} 