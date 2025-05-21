import { ComponentProps, ChangeEvent, ReactNode } from 'react';

interface SelectProps extends Omit<ComponentProps<'select'>, 'onChange'> {
    label: string;
    value: string;
    onChange: (e: ChangeEvent<HTMLSelectElement>) => void;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    children: ReactNode;
}

export function Select({
    label,
    value,
    onChange,
    error,
    required = false,
    disabled = false,
    className = '',
    children,
    ...props
}: SelectProps) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {required && <span className="text-red-500">*</span>}
            </label>
            <select
                value={value}
                onChange={onChange}
                disabled={disabled}
                className={`form-select w-full focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 border-gray-300 rounded ${
                    error ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''
                } ${disabled ? 'bg-gray-100' : ''} ${className}`}
                {...props}
            >
                {children}
            </select>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
