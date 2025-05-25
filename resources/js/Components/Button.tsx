import { ComponentProps, ReactNode } from 'react';

interface ButtonProps extends ComponentProps<'button'> {
    children: ReactNode;
    className?: string;
    variant?: 'primary' | 'secondary' | 'danger';
}

export function Button({
    children,
    className = '',
    variant = 'primary',
    disabled = false,
    type = 'button',
    ...props
}: ButtonProps) {
    const baseStyles = 'inline-flex items-center justify-center px-6 py-3 border rounded-lg font-semibold text-sm tracking-normal focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all duration-200 shadow-sm hover:shadow-md';

    const variantStyles = {
        primary: 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700 hover:border-blue-700 focus:ring-blue-500 active:bg-blue-800',
        secondary: 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 focus:ring-blue-500 active:bg-gray-100',
        danger: 'bg-red-600 border-red-600 text-white hover:bg-red-700 hover:border-red-700 focus:ring-red-500 active:bg-red-800'
    };

    const disabledStyles = disabled ? 'opacity-60 cursor-not-allowed hover:shadow-sm' : 'cursor-pointer';

    // Apply brand colors via inline styles for primary and danger variants
    const getBrandStyles = () => {
        if (disabled) return {};

        switch (variant) {
            case 'primary':
                return {
                    backgroundColor: '#1822cf',
                    borderColor: '#1822cf',
                };
            case 'danger':
                return {
                    backgroundColor: '#cb0909',
                    borderColor: '#cb0909',
                };
            default:
                return {};
        }
    };

    return (
        <button
            type={type}
            disabled={disabled}
            className={`${baseStyles} ${variantStyles[variant]} ${disabledStyles} ${className}`}
            style={getBrandStyles()}
            {...props}
        >
            {children}
        </button>
    );
}
