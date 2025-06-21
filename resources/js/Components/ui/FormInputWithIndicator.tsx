import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import ExtractedFieldIndicator from './ExtractedFieldIndicator';

interface FormInputWithIndicatorProps {
  label: string;
  value: string | number;
  onChange: (value: string) => void;
  type?: 'text' | 'email' | 'tel' | 'number' | 'date';
  placeholder?: string;
  required?: boolean;
  error?: string;
  isExtracted?: boolean;
  className?: string;
  disabled?: boolean;
}

const FormInputWithIndicator: React.FC<FormInputWithIndicatorProps> = ({
  label,
  value,
  onChange,
  type = 'text',
  placeholder,
  required = false,
  error,
  isExtracted = false,
  className = '',
  disabled = false
}) => {
  const { theme } = useTheme();
  const t = themes[theme];

  return (
    <div className={className}>
      {/* Label with Extraction Indicator */}
      <div className="flex items-center justify-between mb-2">
        <label className={cn("block text-sm font-medium", t.text.primary)}>
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
        <ExtractedFieldIndicator
          isExtracted={isExtracted}
          size="sm"
          showLabel={false}
        />
      </div>

      {/* Input Field */}
      <input
        type={type}
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        className={cn(
          "w-full p-3 rounded-lg border transition-all",
          theme === 'dark'
            ? 'bg-gray-800 border-gray-700 text-white focus:border-blue-500'
            : 'bg-white border-gray-300 text-gray-900 focus:border-blue-500',
          error && 'border-red-500',
          isExtracted && 'border-green-300 bg-green-50/50 dark:bg-green-900/20',
          disabled && 'opacity-50 cursor-not-allowed'
        )}
      />

      {/* Extraction Success Message */}
      {isExtracted && (
        <div className="mt-1 flex items-center space-x-1">
          <ExtractedFieldIndicator
            isExtracted={true}
            size="sm"
            showLabel={true}
            className="text-xs"
          />
        </div>
      )}

      {/* Error Message */}
      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}
    </div>
  );
};

export default FormInputWithIndicator;
