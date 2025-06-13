import { ComponentProps, ChangeEvent } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface InputProps extends Omit<ComponentProps<'input'>, 'onChange'> {
    label: string;
    value: string;
    onChange: (e: ChangeEvent<HTMLInputElement>) => void;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

export function Input({
    label,
    value,
    onChange,
    error,
    required = false,
    disabled = false,
    className = '',
    ...props
}: InputProps) {
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
    
    return (
        <div>
            <label className={cn(
                "block text-sm font-medium mb-2",
                t.text.secondary
            )}>
                {label}
                {required && <span className={theme === 'dark' ? 'text-red-400 ml-1' : 'text-red-600 ml-1'}>*</span>}
            </label>
            <input
                value={value}
                onChange={onChange}
                disabled={disabled}
                className={cn(
                    "block w-full px-4 py-3 rounded-lg transition-all duration-200",
                    "focus:outline-none border",
                    t.input.base,
                    !error && t.input.focus,
                    error && t.input.error,
                    disabled && "opacity-50 cursor-not-allowed",
                    className
                )}
                {...props}
            />
            {error && (
                <p className={cn(
                    "mt-2 text-sm flex items-center",
                    t.status.error.split(' ').find(c => c.startsWith('text-')) || 'text-red-400'
                )}>
                    <svg className="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    {error}
                </p>
            )}
        </div>
    );
}