import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { colors } from '@/theme/colors.config';

interface ActionButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant: 'approve' | 'danger' | 'primary' | 'secondary' | 'warning' | 'info';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

const ActionButton: React.FC<ActionButtonProps> = ({
  variant,
  size = 'md',
  isLoading = false,
  leftIcon,
  rightIcon,
  children,
  className,
  disabled,
  ...props
}) => {
  // Try to use theme if available, fallback to dark theme
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const variants = {
    approve: {
      dark: `${t.button.approve.base} ${t.button.approve.hover}`,
      light: `${t.button.approve.base} ${t.button.approve.hover}`
    },
    danger: {
      dark: `${t.button.danger.base} ${t.button.danger.hover}`,
      light: `${t.button.danger.base} ${t.button.danger.hover}`
    },
    primary: {
      dark: `${t.button.primary.base} ${t.button.primary.hover}`,
      light: `${t.button.primary.base} ${t.button.primary.hover}`
    },
    secondary: {
      dark: `${t.button.secondary.base} ${t.button.secondary.hover}`,
      light: `${t.button.secondary.base} ${t.button.secondary.hover}`
    },
    warning: {
      dark: `${t.button.warning.base} ${t.button.warning.hover}`,
      light: `${t.button.warning.base} ${t.button.warning.hover}`
    },
    info: {
      dark: `${t.button.ghost.base} ${t.button.ghost.hover}`, // Using ghost for info
      light: `${t.button.ghost.base} ${t.button.ghost.hover}`
    }
  };

  const sizes = {
    sm: 'px-3 py-2 text-sm',
    md: 'px-5 py-2.5 text-base',
    lg: 'px-7 py-3.5 text-lg'
  };

  const currentVariant = variants[variant][theme];

  return (
    <button
      className={cn(
        'relative inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200',
        'focus:outline-none focus:ring-2 focus:ring-offset-2',
        theme === 'dark' ? 'focus:ring-offset-gray-900' : 'focus:ring-offset-white',
        variant === 'primary' && `focus:ring-[${colors.msc.blue.DEFAULT}]/50`,
        variant === 'approve' && 'focus:ring-emerald-500/50',
        variant === 'danger' && 'focus:ring-red-500/50',
        variant === 'warning' && 'focus:ring-amber-500/50',
        variant === 'info' && 'focus:ring-blue-500/50',
        variant === 'secondary' && theme === 'dark' ? 'focus:ring-white/30' : 'focus:ring-gray-500/50',
        currentVariant,
        sizes[size],
        (disabled || isLoading) && 'opacity-50 cursor-not-allowed',
        className
      )}
      disabled={disabled || isLoading}
      {...props}
    >
      {isLoading && (
        <svg
          className="animate-spin -ml-1 mr-3 h-5 w-5"
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
      )}
      {!isLoading && leftIcon && <span className="mr-2">{leftIcon}</span>}
      {children}
      {!isLoading && rightIcon && <span className="ml-2">{rightIcon}</span>}
    </button>
  );
};

export default ActionButton;
