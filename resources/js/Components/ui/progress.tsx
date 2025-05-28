import React from 'react';

interface ProgressProps {
    value: number;
    max?: number;
    className?: string;
    showValue?: boolean;
}

export const Progress: React.FC<ProgressProps> = ({
    value,
    max = 100,
    className = '',
    showValue = false
}) => {
    const percentage = Math.min(Math.max((value / max) * 100, 0), 100);

    return (
        <div className={`relative ${className}`}>
            <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                    className="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-in-out"
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {showValue && (
                <span className="absolute right-0 top-0 text-xs text-gray-600 mt-1">
                    {Math.round(percentage)}%
                </span>
            )}
        </div>
    );
};
