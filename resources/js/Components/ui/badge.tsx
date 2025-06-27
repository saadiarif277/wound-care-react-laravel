import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface BadgeProps {
    children: React.ReactNode;
    variant?: 'default' | 'outline' | 'destructive' | 'secondary' | 'success' | 'warning';
    className?: string;
}

export const Badge: React.FC<BadgeProps> = ({
    children,
    variant = 'default',
    className = ''
}) => {
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

    const getVariantClasses = () => {
        switch (variant) {
            case 'outline':
                return theme === 'dark'
                    ? 'border border-white/20 text-white/80 bg-white/5'
                    : 'border border-gray-300 text-gray-700 bg-white';
            case 'destructive':
                return theme === 'dark'
                    ? 'bg-red-500/20 text-red-300 border border-red-500/30'
                    : 'bg-red-100 text-red-800 border border-red-200';
            case 'secondary':
                return theme === 'dark'
                    ? 'bg-white/10 text-white/70 border border-white/20'
                    : 'bg-gray-100 text-gray-800 border border-gray-200';
            case 'success':
                return theme === 'dark'
                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                    : 'bg-emerald-100 text-emerald-800 border border-emerald-200';
            case 'warning':
                return theme === 'dark'
                    ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                    : 'bg-amber-100 text-amber-800 border border-amber-200';
            default: // 'default' - primary blue variant
                return theme === 'dark'
                    ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                    : 'bg-blue-100 text-blue-800 border border-blue-200';
        }
    };

    return (
        <span className={cn(
            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors duration-200',
            getVariantClasses(),
            className
        )}>
            {children}
        </span>
    );
};
