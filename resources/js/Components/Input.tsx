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
            <label className="block text-sm font-semibold text-gray-900 mb-2">
                {label}
                {required && <span className="text-red-600 ml-1">*</span>}
            </label>
            <input
                value={value}
                onChange={onChange}
                disabled={disabled}
                className={`block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-20 focus:border-blue-500 ${
                    error ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''
                } ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white hover:border-gray-400'} ${className}`}
                style={{
                    ...(error ? {} : {
                        '--tw-ring-color': '#1822cf33' // brand blue with opacity
                    })
                }}
                {...props}
            />
            {error && <p className="mt-2 text-sm text-red-600 flex items-center">
                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
                {error}
            </p>}
        </div>
    );
}
