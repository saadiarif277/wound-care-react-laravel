import React from 'react';
import { cn } from '@/theme/glass-theme';

interface ResponsiveGridProps {
  children: React.ReactNode;
  variant?: 'dashboard' | 'episodes' | 'orders' | 'cards';
  gap?: 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
}

/**
 * ResponsiveGrid - 2025 Mobile-First Healthcare Grid System
 *
 * Breakpoint Strategy:
 * - Mobile: 320px (1 column)
 * - Tablet: 768px (2-3 columns)
 * - Desktop: 1024px (3-4 columns)
 * - Large: 1440px (4-6 columns)
 *
 * Healthcare-optimized layouts for different content types
 */
const ResponsiveGrid: React.FC<ResponsiveGridProps> = ({
  children,
  variant = 'dashboard',
  gap = 'md',
  className
}) => {

  const gridVariants = {
    // Dashboard Statistics Grid
    dashboard: cn(
      'grid w-full',
      'grid-cols-1',              // Mobile: 1 column
      'sm:grid-cols-2',           // Small: 2 columns
      'md:grid-cols-3',           // Medium: 3 columns
      'lg:grid-cols-4',           // Large: 4 columns
      'xl:grid-cols-5',           // XL: 5 columns
      '2xl:grid-cols-6'           // 2XL: 6 columns
    ),

    // Episode Cards Grid
    episodes: cn(
      'grid w-full',
      'grid-cols-1',              // Mobile: 1 column
      'md:grid-cols-2',           // Medium: 2 columns
      'lg:grid-cols-3',           // Large: 3 columns
      'xl:grid-cols-4'            // XL: 4 columns
    ),

    // Order Items Grid
    orders: cn(
      'grid w-full',
      'grid-cols-1',              // Mobile: 1 column
      'sm:grid-cols-1',           // Small: Still 1 column for order complexity
      'md:grid-cols-2',           // Medium: 2 columns
      'lg:grid-cols-3'            // Large: 3 columns
    ),

    // General Card Grid
    cards: cn(
      'grid w-full',
      'grid-cols-1',              // Mobile: 1 column
      'sm:grid-cols-2',           // Small: 2 columns
      'lg:grid-cols-3',           // Large: 3 columns
      'xl:grid-cols-4'            // XL: 4 columns
    )
  };

  const gapVariants = {
    sm: 'gap-2 sm:gap-3',
    md: 'gap-4 sm:gap-5 lg:gap-6',
    lg: 'gap-6 sm:gap-7 lg:gap-8',
    xl: 'gap-8 sm:gap-10 lg:gap-12'
  };

  return (
    <div className={cn(
      gridVariants[variant],
      gapVariants[gap],
      'auto-rows-fr',              // Equal height rows
      className
    )}>
      {children}
    </div>
  );
};

/**
 * ResponsiveContainer - Smart container with healthcare-optimized breakpoints
 */
interface ResponsiveContainerProps {
  children: React.ReactNode;
  variant?: 'full' | 'centered' | 'sidebar';
  className?: string;
}

const ResponsiveContainer: React.FC<ResponsiveContainerProps> = ({
  children,
  variant = 'centered',
  className
}) => {

  const containerVariants = {
    full: 'w-full',
    centered: cn(
      'w-full max-w-7xl mx-auto',
      'px-4 sm:px-6 lg:px-8'      // Progressive padding
    ),
    sidebar: cn(
      'w-full',
      'pl-0 md:pl-64',            // Account for sidebar on larger screens
      'px-4 sm:px-6 lg:px-8'
    )
  };

  return (
    <div className={cn(containerVariants[variant], className)}>
      {children}
    </div>
  );
};

/**
 * MobileOptimizedCard - Card component optimized for mobile interactions
 */
interface MobileOptimizedCardProps {
  children: React.ReactNode;
  title?: string;
  actions?: React.ReactNode;
  priority?: 'low' | 'medium' | 'high';
  className?: string;
  onClick?: () => void;
}

const MobileOptimizedCard: React.FC<MobileOptimizedCardProps> = ({
  children,
  title,
  actions,
  priority = 'medium',
  className,
  onClick
}) => {

  const priorityStyles = {
    low: 'border-gray-200 bg-white',
    medium: 'border-blue-200 bg-blue-50/50',
    high: 'border-red-200 bg-red-50/50'
  };

  return (
    <div
      className={cn(
        'rounded-xl border-2 p-4 sm:p-6 transition-all duration-200',
        'backdrop-blur-md',
        // Mobile-optimized touch targets
        'min-h-[120px] sm:min-h-[140px]',
        // Enhanced mobile tap areas
        onClick && 'cursor-pointer hover:shadow-lg active:scale-[0.98]',
        // Priority-based styling
        priorityStyles[priority],
        className
      )}
      onClick={onClick}
    >
      {/* Mobile-friendly header */}
      {(title || actions) && (
        <div className="flex items-start justify-between mb-4">
          {title && (
            <h3 className="text-lg sm:text-xl font-semibold text-gray-900 leading-tight">
              {title}
            </h3>
          )}
          {actions && (
            <div className="flex items-center space-x-2 ml-4">
              {actions}
            </div>
          )}
        </div>
      )}

      {/* Content area with mobile optimizations */}
      <div className="space-y-3 sm:space-y-4">
        {children}
      </div>
    </div>
  );
};

/**
 * TouchOptimizedButton - Button with enhanced mobile touch targets
 */
interface TouchOptimizedButtonProps {
  children: React.ReactNode;
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  fullWidth?: boolean;
  className?: string;
  onClick?: () => void;
  disabled?: boolean;
}

const TouchOptimizedButton: React.FC<TouchOptimizedButtonProps> = ({
  children,
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  className,
  onClick,
  disabled = false
}) => {

  const variantStyles = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800',
    secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 active:bg-gray-400',
    danger: 'bg-red-600 text-white hover:bg-red-700 active:bg-red-800',
    ghost: 'bg-transparent text-gray-700 hover:bg-gray-100 active:bg-gray-200'
  };

  const sizeStyles = {
    sm: 'px-4 py-2 text-sm min-h-[40px]',      // Minimum 40px touch target
    md: 'px-6 py-3 text-base min-h-[44px]',    // Minimum 44px touch target
    lg: 'px-8 py-4 text-lg min-h-[48px]'       // Minimum 48px touch target
  };

  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'font-medium rounded-lg transition-all duration-200',
        'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
        // Mobile-optimized touch feedback
        'active:scale-[0.97] active:transition-transform active:duration-75',
        variantStyles[variant],
        sizeStyles[size],
        fullWidth && 'w-full',
        disabled && 'opacity-50 cursor-not-allowed',
        className
      )}
    >
      {children}
    </button>
  );
};

export {
  ResponsiveGrid,
  ResponsiveContainer,
  MobileOptimizedCard,
  TouchOptimizedButton
};
