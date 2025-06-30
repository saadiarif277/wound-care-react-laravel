import React from 'react';
import { FiLock } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';

interface ProtectedFinancialInfoProps {
  userRole: string;
  label: string;
  value: string | number;
  children?: React.ReactNode;
  className?: string;
}

export const ProtectedFinancialInfo: React.FC<ProtectedFinancialInfoProps> = ({
  userRole,
  label,
  value,
  children,
  className
}) => {
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

  // Office Managers cannot see financial data
  if (userRole === 'OM') {
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

  // For providers and admins, show the actual value
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

// Helper hook for permission checks
export const useFinancialPermissions = (userRole: string) => {
  return {
    canViewPricing: userRole !== 'OM',
    canViewCommissions: userRole === 'Admin',
    canViewAllFinancials: userRole === 'Admin' || userRole === 'Provider',
    isOfficeManager: userRole === 'OM',
    isProvider: userRole === 'Provider',
    isAdmin: userRole === 'Admin'
  };
};