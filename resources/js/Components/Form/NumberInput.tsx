import { ComponentProps, ChangeEvent } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface NumberInputProps extends Omit<ComponentProps<'input'>, 'type' | 'onChange'> {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    min?: number;
    max?: number;
    step?: string | number;
    required?: boolean;
    disabled?: boolean;
}

export default function NumberInput({
    label,
    value,
    onChange,
    error,
    min,
    max,
    step = 1,
    required = false,
    disabled = false,
    className = '',
    ...props
}: NumberInputProps) {
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
            <input
                type="number"
                value={value}
                onChange={handleChange}
                min={min}
                max={max}
                step={step}
                disabled={disabled}
                className={cn(
                    t.input.base,
                    t.input.focus,
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
