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
    const baseStyles = 'inline-flex items-center px-4 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150';

    const variantStyles = {
        primary: 'bg-indigo-600 border-transparent text-white hover:bg-indigo-700 focus:ring-indigo-500 active:bg-indigo-900',
        secondary: 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-indigo-500 active:bg-gray-100',
        danger: 'bg-red-600 border-transparent text-white hover:bg-red-700 focus:ring-red-500 active:bg-red-900'
    };

    const disabledStyles = disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer';

    return (
        <button
            type={type}
            disabled={disabled}
            className={`${baseStyles} ${variantStyles[variant]} ${disabledStyles} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
