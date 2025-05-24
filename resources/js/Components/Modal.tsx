import React, { ReactNode } from 'react';

interface Props {
    show: boolean;
    onClose: () => void;
    children: ReactNode;
}

export const Modal: React.FC<Props> = ({ show, onClose, children }) => {
    if (!show) return null;

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
                    className="relative w-full max-w-md transform overflow-hidden rounded-lg bg-white shadow-xl transition-all"
                    onClick={(e) => e.stopPropagation()}
                >
                    {children}
                </div>
            </div>
        </div>
    );
};
