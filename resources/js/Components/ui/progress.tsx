import React from 'react';

interface ProgressProps {
    value: number;
    max?: number;
    className?: string;
    showValue?: boolean;
    'aria-label'?: string;
    'aria-labelledby'?: string;
}

export const Progress: React.FC<ProgressProps> = ({
    value,
    max = 100,
    className = '',
    showValue = false,
    'aria-label': ariaLabel,
    'aria-labelledby': ariaLabelledBy
}) => {
    const percentage = Math.min(Math.max((value / max) * 100, 0), 100);

    return (
        <div className={`relative ${className}`}>
            <div
                className="w-full bg-gray-200 rounded-full h-2"
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-label={ariaLabel}
                aria-labelledby={ariaLabelledBy}
            >
                <div
                    className="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-in-out"
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {showValue && (
                <span
                    className="absolute right-0 top-full text-xs text-gray-600 mt-1 pl-1"
                    aria-hidden="true"
                >
                    {Math.round(percentage)}%
                </span>
            )}
        </div>
    );
};
