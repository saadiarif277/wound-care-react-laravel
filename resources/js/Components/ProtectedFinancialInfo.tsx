import React from 'react';
import { FiLock } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { usePermissions } from '@/hooks/usePermissions';

interface ProtectedFinancialInfoProps {
  permission: string;
  label: string;
  value: string | number;
  children?: React.ReactNode;
  className?: string;
}

export const ProtectedFinancialInfo: React.FC<ProtectedFinancialInfoProps> = ({
  permission,
  label,
  value,
  children,
  className
}) => {
  const { can } = usePermissions();
  
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Check permission instead of role - office managers have no financial permissions
  if (!can(permission)) {
    return (
      <div className={cn("flex items-center justify-between py-2", className)}>
        <span className={cn("text-sm font-medium", t.text.secondary)}>{label}:</span>
        <span className={cn("text-sm flex items-center", t.text.tertiary)}>
          <FiLock className="h-3 w-3 mr-1" />
          <span className="italic">Hidden</span>
        </span>
      </div>
    );
  }

  // For users with permission, show the actual value
  if (children) {
    return <>{children}</>;
  }

  return (
    <div className={cn("flex items-center justify-between py-2", className)}>
      <span className={cn("text-sm font-medium", t.text.secondary)}>{label}:</span>
      <span className={cn("text-sm", t.text.primary)}>{value}</span>
    </div>
  );
};

// Helper hook for financial permission checks - now uses actual permissions
export const useFinancialPermissions = () => {
  const { can, getFinancialAccess } = usePermissions();
  const financialAccess = getFinancialAccess();
  
  return {
    canViewPricing: can('view-national-asp') || can('view-msc-pricing'),
    canViewCommissions: can('view-commission'),
    canViewAllFinancials: can('view-financials') || can('manage-financials'),
    canViewMscPricing: can('view-msc-pricing'),
    canViewNationalAsp: can('view-national-asp'),
    canViewDiscounts: can('view-discounts'),
    financialAccess
  };
};