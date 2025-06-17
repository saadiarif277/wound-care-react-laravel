import React, { ReactNode } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Props {
    show: boolean;
    onClose: () => void;
    children: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

export const Modal: React.FC<Props> = ({ show, onClose, children, maxWidth = 'md' }) => {
    if (!show) return null;
    
    // Theme setup
    let theme: 'dark' | 'light' = 'dark';
    let t = themes.dark;
    
    try {
        const themeContext = useTheme();
        theme = themeContext.theme;
        t = themes[theme];
    } catch (e) {
        // If not in ThemeProvider, use dark theme
    }

    const maxWidthClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Glass Backdrop */}
            <div
                className={cn("fixed inset-0 transition-opacity", t.modal.backdrop)}
                onClick={onClose}
            />

            {/* Modal Container */}
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className={cn(
                        "relative w-full transform transition-all",
                        maxWidthClasses[maxWidth],
                        t.modal.container,
                        "overflow-visible" // Allow dropdown to overflow
                    )}
                    onClick={(e) => e.stopPropagation()}
                    style={{ isolation: 'isolate' }} // Create new stacking context
                >
                    {children}
                </div>
            </div>
        </div>
    );
};
