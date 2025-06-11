import React, { ReactNode } from 'react';

interface Props {
    show: boolean;
    onClose: () => void;
    children: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

export const Modal: React.FC<Props> = ({ show, onClose, children, maxWidth = 'md' }) => {
    if (!show) return null;

    const maxWidthClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                onClick={onClose}
            />

            {/* Modal Container */}
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className={`relative w-full ${maxWidthClasses[maxWidth]} transform overflow-hidden rounded-lg bg-white shadow-xl transition-all`}
                    onClick={(e) => e.stopPropagation()}
                >
                    {children}
                </div>
            </div>
        </div>
    );
};
