import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ProgressProps {
    value: number;
    max?: number;
    className?: string;
    showValue?: boolean;
    'aria-label'?: string;
    'aria-labelledby'?: string;
}

export const Progress: React.FC<ProgressProps> = ({
    value,
    max = 100,
    className = '',
    showValue = false,
    'aria-label': ariaLabel,
    'aria-labelledby': ariaLabelledBy
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

    const percentage = Math.min(Math.max((value / max) * 100, 0), 100);

    return (
        <div className={`relative ${className}`}>
            <div
                className={cn(
                    "w-full rounded-full h-2",
                    theme === 'dark' ? 'bg-white/10' : 'bg-gray-200'
                )}
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-label={ariaLabel}
                aria-labelledby={ariaLabelledBy}
            >
                <div
                    className={cn(
                        "h-2 rounded-full transition-all duration-300 ease-in-out",
                        theme === 'dark'
                            ? 'bg-gradient-to-r from-blue-500 to-purple-600'
                            : 'bg-gradient-to-r from-blue-600 to-purple-700'
                    )}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {showValue && (
                <span
                    className={cn(
                        "absolute right-0 top-full text-xs mt-1 pl-1",
                        t.text.secondary
                    )}
                    aria-hidden="true"
                >
                    {Math.round(percentage)}%
                </span>
            )}
        </div>
    );
};
