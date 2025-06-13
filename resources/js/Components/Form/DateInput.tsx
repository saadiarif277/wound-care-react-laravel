import { ComponentProps, ChangeEvent, ReactNode } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface DateInputProps extends Omit<ComponentProps<'input'>, 'type' | 'onChange'> {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    icon?: ReactNode;
    required?: boolean;
    disabled?: boolean;
}

export default function DateInput({
    label,
    value,
    onChange,
    error,
    icon,
    required = false,
    disabled = false,
    className = '',
    ...props
}: DateInputProps) {
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

    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        onChange(e.target.value);
    };

    return (
        <div>
            <label className={cn(
                'block text-sm font-medium mb-1',
                t.text.secondary
            )}>
                {label}
                {required && <span className={cn("ml-1", t.status.error.split(' ')[0])}>*</span>}
            </label>
            <div className="relative">
                {icon && (
                    <div className={cn(
                        'absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none',
                        t.text.muted
                    )}>
                        {icon}
                    </div>
                )}
                <input
                    type="date"
                    value={value}
                    onChange={handleChange}
                    disabled={disabled}
                    className={cn(
                        t.input.base,
                        t.input.focus,
                        icon ? 'pl-10' : '',
                        error ? t.input.error : '',
                        disabled ? 'opacity-50 cursor-not-allowed' : '',
                        '[color-scheme:dark]', // Ensures date picker uses dark theme
                        className
                    )}
                    {...props}
                />
            </div>
            {error && (
                <p className={cn('mt-1 text-sm', t.status.error.split(' ')[0])}>
                    {error}
                </p>
            )}
        </div>
    );
}
