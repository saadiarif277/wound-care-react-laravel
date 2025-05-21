import { ComponentProps, ChangeEvent, ReactNode } from 'react';

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
    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        onChange(e.target.value);
    };

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {required && <span className="text-red-500">*</span>}
            </label>
            <div className="relative">
                {icon && (
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        {icon}
                    </div>
                )}
                <input
                    type="date"
                    value={value}
                    onChange={handleChange}
                    disabled={disabled}
                    className={`form-input w-full focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 border-gray-300 rounded ${
                        icon ? 'pl-10' : ''
                    } ${
                        error ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''
                    } ${disabled ? 'bg-gray-100' : ''} ${className}`}
                    {...props}
                />
            </div>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
