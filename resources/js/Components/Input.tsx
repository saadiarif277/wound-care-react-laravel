import { ComponentProps, ChangeEvent } from 'react';

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
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {required && <span className="text-red-500">*</span>}
            </label>
            <input
                value={value}
                onChange={onChange}
                disabled={disabled}
                className={`form-input w-full focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 border-gray-300 rounded ${
                    error ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''
                } ${disabled ? 'bg-gray-100' : ''} ${className}`}
                {...props}
            />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
