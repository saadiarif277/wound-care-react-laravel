import { ComponentProps, ChangeEvent } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface TextAreaInputProps extends Omit<ComponentProps<'textarea'>, 'onChange'> {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    rows?: number;
}

export default function TextAreaInput({
    label,
    value,
    onChange,
    error,
    required = false,
    disabled = false,
    rows = 3,
    className = '',
    ...props
}: TextAreaInputProps) {
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

    const handleChange = (e: ChangeEvent<HTMLTextAreaElement>) => {
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
            <textarea
                value={value}
                onChange={handleChange}
                rows={rows}
                disabled={disabled}
                className={cn(
                    t.input.base,
                    t.input.focus,
                    'resize-none',
                    error ? t.input.error : '',
                    disabled ? 'opacity-50 cursor-not-allowed' : '',
                    className
                )}
                {...props}
            />
            {error && (
                <p className={cn('mt-1 text-sm', t.status.error.split(' ')[0])}>
                    {error}
                </p>
            )}
        </div>
    );
}
