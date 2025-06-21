import React from 'react';
import { Sparkles, CheckCircle } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';

interface ExtractedFieldIndicatorProps {
  isExtracted?: boolean;
  className?: string;
  showLabel?: boolean;
  size?: 'sm' | 'md' | 'lg';
}

const ExtractedFieldIndicator: React.FC<ExtractedFieldIndicatorProps> = ({
  isExtracted = false,
  className = '',
  showLabel = true,
  size = 'sm'
}) => {
  const { theme } = useTheme();
  const t = themes[theme];

  if (!isExtracted) return null;

  const sizeClasses = {
    sm: 'w-4 h-4 text-xs',
    md: 'w-5 h-5 text-sm',
    lg: 'w-6 h-6 text-base'
  };

  return (
    <div className={`inline-flex items-center space-x-1 ${className}`}>
      <div className="relative">
        <CheckCircle className={`${sizeClasses[size]} text-green-500`} />
        <Sparkles className="w-2 h-2 text-yellow-400 absolute -top-0.5 -right-0.5 animate-pulse" />
      </div>
      {showLabel && (
        <span className={`${sizeClasses[size]} font-medium text-green-600 dark:text-green-400`}>
          Auto-filled
        </span>
      )}
    </div>
  );
};

export default ExtractedFieldIndicator;
