import { ComponentProps, ChangeEvent } from 'react';

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
    const handleChange = (e: ChangeEvent<HTMLTextAreaElement>) => {
        onChange(e.target.value);
    };

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {required && <span className="text-red-500">*</span>}
            </label>
            <textarea
                value={value}
                onChange={handleChange}
                rows={rows}
                disabled={disabled}
                className={`form-textarea w-full focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 border-gray-300 rounded ${
                    error ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''
                } ${disabled ? 'bg-gray-100' : ''} ${className}`}
                {...props}
            />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
