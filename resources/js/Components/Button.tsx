import { ComponentProps, ReactNode } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ButtonProps extends ComponentProps<'button'> {
    children: ReactNode;
    className?: string;
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
    size?: 'sm' | 'md' | 'lg';
    isLoading?: boolean;
}

export function Button({
    children,
    className = '',
    variant = 'primary',
    size = 'md',
    disabled = false,
    isLoading = false,
    type = 'button',
    ...props
}: ButtonProps) {
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

    const sizeStyles = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base'
    };

    const baseStyles = cn(
        'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200',
        'focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-offset-transparent',
        'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none',
        sizeStyles[size]
    );

    const variantStyles = {
        primary: cn(t.button.primary.base, 'focus:ring-[#1925c3]/50'),
        secondary: cn(t.button.secondary.base, 'focus:ring-white/20'),
        ghost: cn(t.button.ghost.base, 'focus:ring-white/20'),
        danger: cn(t.button.danger.base, 'focus:ring-red-500/50'),
        success: cn(t.button.approve.base, 'focus:ring-emerald-500/50')
    };

    const finalDisabled = disabled || isLoading;

    return (
        <button
            type={type}
            disabled={finalDisabled}
            className={cn(
                baseStyles,
                variantStyles[variant],
                className
            )}
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
                children
            )}
        </button>
    );
}
