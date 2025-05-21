import { ComponentProps, ReactNode } from 'react';

interface CardProps extends ComponentProps<'div'> {
    children: ReactNode;
    title?: string;
    icon?: ReactNode;
    footer?: ReactNode;
    className?: string;
}

export function Card({
    children,
    title,
    icon,
    footer,
    className = '',
    ...props
}: CardProps) {
    return (
        <div
            className={`bg-white overflow-hidden shadow-sm rounded-lg ${className}`}
            {...props}
        >
            {(title || icon) && (
                <div className="px-6 py-4 border-b border-gray-200">
                    <div className="flex items-center">
                        {icon && <div className="mr-3 text-gray-500">{icon}</div>}
                        {title && <h3 className="text-lg font-medium text-gray-900">{title}</h3>}
                    </div>
                </div>
            )}
            <div className="p-6">{children}</div>
            {footer && (
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    {footer}
                </div>
            )}
        </div>
    );
}
