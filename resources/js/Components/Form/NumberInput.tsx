import { ComponentProps, ChangeEvent } from 'react';

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
    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        onChange(e.target.value);
    };

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {required && <span className="text-red-500">*</span>}
            </label>
            <input
                type="number"
                value={value}
                onChange={handleChange}
                min={min}
                max={max}
                step={step}
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
