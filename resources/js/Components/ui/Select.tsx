import React from 'react';
import { FiChevronDown } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { cn, themes } from '@/theme/glass-theme';

interface SelectOption {
  value: string | number;
  label: string;
  disabled?: boolean;
  hidden?: boolean;
}

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  options: SelectOption[];
  placeholder?: string;
}

const Select: React.FC<SelectProps> = ({ label, error, options, placeholder, ...props }) => {
  const { theme } = useTheme();
  const t = themes[theme];

  // Ensure we have a placeholder option if no value is selected and placeholder is provided
  const selectOptions = [...options];
  if (placeholder && !selectOptions.some(opt => opt.value === '' || opt.value === undefined)) {
    selectOptions.unshift({
      value: '',
      label: placeholder,
      disabled: true,
      hidden: false
    });
  }

  return (
    <div>
      {label && (
        <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
          {label}
        </label>
      )}
      <div className="relative">
        <select
          {...props}
          className={cn(
            "w-full p-2 pr-8 border rounded appearance-none",
            "focus:outline-none focus:ring-2 focus:ring-blue-500",
            t.glass.input,
            error ? "border-red-500" : t.glass.border
          )}
        >
          {selectOptions.map(option => (
            <option
              key={option.value}
              value={option.value}
              disabled={option.disabled}
              hidden={option.hidden}
            >
              {option.label}
            </option>
          ))}
        </select>
        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700 dark:text-gray-300">
          <FiChevronDown />
        </div>
      </div>
      {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
    </div>
  );
};

export default Select;
