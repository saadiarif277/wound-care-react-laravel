import { ComponentProps, ReactNode } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface CardProps extends Omit<ComponentProps<'div'>, 'title'> {
    children: ReactNode;
    title?: string | React.ReactNode;
    icon?: ReactNode;
    footer?: ReactNode;
    /**
     * Use frost effect for enhanced readability
     */
    frost?: boolean;
    /**
     * Visual variant of the card
     */
    variant?: 'default' | 'primary' | 'secondary';
    className?: string;
}

export function Card({
    children,
    title,
    icon,
    footer,
    frost = false,
    variant = 'default',
    className = '',
    ...props
}: CardProps) {
    // Theme setup with fallback
    let theme: 'dark' | 'light' = 'dark';
    let t = themes.dark;

    try {
        const themeContext = useTheme();
        theme = themeContext.theme;
        t = themes[theme];
    } catch (e) {
        // If not in ThemeProvider, use dark theme
    }

    const baseStyles = frost ? t.glass.frost : t.glass.card;

    const variantStyles = {
        default: '',
        primary: cn(
            theme === 'dark'
                ? 'border-[#1925c3]/30 shadow-lg shadow-blue-500/20'
                : 'border-blue-500/30 shadow-lg shadow-blue-500/20'
        ),
        secondary: cn(
            theme === 'dark'
                ? 'bg-white/[0.05]'
                : 'bg-gray-50/80'
        ),
    };

    return (
        <div
            className={cn(
                baseStyles,
                'rounded-2xl overflow-hidden transition-all duration-300',
                variant !== 'default' && variantStyles[variant],
                frost && 'relative',
                className
            )}
            {...props}
        >
            {frost && (
                <div className={cn(
                    "absolute inset-0 pointer-events-none",
                    theme === 'dark'
                        ? "bg-gradient-to-b from-white/[0.06] to-transparent"
                        : "bg-gradient-to-b from-white/40 to-transparent"
                )} />
            )}

            <div className={frost ? 'relative z-10' : undefined}>
                {(title || icon) && (
                    <div className={cn(
                        'px-6 py-4',
                        theme === 'dark'
                            ? 'border-b border-white/[0.12] bg-white/[0.03]'
                            : 'border-b border-gray-200/60 bg-gray-50/30'
                    )}>
                        <div className="flex items-center">
                            {icon && (
                                <div className={cn('mr-3', t.text.secondary)}>
                                    {icon}
                                </div>
                            )}
                            {title && (
                                <h3 className={cn(
                                    'text-lg font-medium',
                                    t.text.primary,
                                    frost && (theme === 'dark' ? 'text-shadow-sm' : '')
                                )}>
                                    {title}
                                </h3>
                            )}
                        </div>
                    </div>
                )}

                <div className="p-6">
                    {children}
                </div>

                {footer && (
                    <div className={cn(
                        'px-6 py-4',
                        theme === 'dark'
                            ? 'border-t border-white/[0.12] bg-white/[0.03]'
                            : 'border-t border-gray-200/60 bg-gray-50/30'
                    )}>
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}
