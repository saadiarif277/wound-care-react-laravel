import React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { glassTheme, cn, themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

const getButtonVariants = (theme: 'dark' | 'light') => {
  const t = themes[theme];
  
  return cva(
    'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-offset-transparent disabled:opacity-50 disabled:pointer-events-none disabled:transform-none',
    {
      variants: {
        variant: {
          primary: t.button.primary + ' focus:ring-[#1925c3]/50',
          secondary: t.button.secondary + (theme === 'dark' ? ' focus:ring-white/20' : ' focus:ring-gray-300'),
          danger: t.button.danger + ' focus:ring-red-500/50',
          ghost: t.button.ghost + (theme === 'dark' ? ' focus:ring-white/20' : ' focus:ring-gray-300'),
          success: theme === 'dark' 
            ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-emerald-700 hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] transform hover:scale-105 transition-all duration-200 shadow-lg focus:ring-emerald-500/50'
            : 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:from-emerald-600 hover:to-emerald-700 hover:shadow-md transform hover:scale-105 transition-all duration-200 shadow-md focus:ring-emerald-500/50',
          glass: theme === 'dark'
            ? 'backdrop-blur-md bg-white/[0.08] text-white/95 border border-white/[0.15] hover:bg-white/[0.12] hover:border-white/[0.20] focus:ring-white/30'
            : 'backdrop-blur-md bg-gray-100/80 text-gray-800 border border-gray-300 hover:bg-gray-200/80 hover:border-gray-400 focus:ring-gray-300',
        },
        size: {
          sm: 'text-xs px-3 py-1.5',
          md: 'text-sm px-4 py-2',
          lg: 'text-base px-6 py-3',
          xl: 'text-lg px-8 py-4',
        },
      },
      defaultVariants: {
        variant: 'primary',
        size: 'md',
      },
    }
  );
};

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<ReturnType<typeof getButtonVariants>> {
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

/**
 * Glassmorphic button component with loading states and icon support.
 */
const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      className,
      variant,
      size,
      isLoading,
      leftIcon,
      rightIcon,
      children,
      disabled,
      ...props
    },
    ref
  ) => {
    // Get theme context with fallback
    let theme: 'dark' | 'light' = 'dark';
    
    try {
      const themeContext = useTheme();
      theme = themeContext.theme;
    } catch (e) {
      // Fallback to dark theme if outside ThemeProvider
    }

    const buttonVariants = getButtonVariants(theme);

    return (
      <button
        ref={ref}
        disabled={disabled || isLoading}
        className={cn(buttonVariants({ variant, size }), className)}
        {...props}
      >
        {isLoading ? (
          <>
            <svg
              className="animate-spin -ml-1 mr-2 h-4 w-4"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            Loading...
          </>
        ) : (
          <>
            {leftIcon && <span className="mr-2 -ml-1">{leftIcon}</span>}
            {children}
            {rightIcon && <span className="ml-2 -mr-1">{rightIcon}</span>}
          </>
        )}
      </button>
    );
  }
);

Button.displayName = 'Button';

export default Button;
export { getButtonVariants };