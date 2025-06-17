import { ComponentProps } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface SelectInputProps extends ComponentProps<'select'> {
  label?: string;
  error?: string;
  required?: boolean;
  options?: { value: string; label: string }[];
}

export default function SelectInput({
  label,
  name,
  error,
  required = false,
  className = '',
  options = [],
  children,
  ...props
}: SelectInputProps) {
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const select = (
    <select
      id={name}
      name={name}
      {...props}
      className={cn(
        t.input.base,
        t.input.focus,
        'appearance-none cursor-pointer relative z-50',
        error ? t.input.error : '',
        className
      )}
      style={{ position: 'relative', zIndex: 50 }}
    >
      {/* Render children if provided, otherwise use options */}
      {children || options?.map(({ value, label }, index) => (
        <option
          key={index}
          value={value}
          className={cn(
            theme === 'dark'
              ? 'bg-gray-900 text-white'
              : 'bg-white text-gray-900'
          )}
          style={{
            backgroundColor: theme === 'dark' ? '#111827' : '#ffffff',
            color: theme === 'dark' ? '#ffffff' : '#111827',
          }}
        >
          {label}
        </option>
      ))}
    </select>
  );

  // If no label, return just the select
  if (!label) {
    return select;
  }

  // Return labeled select
  return (
    <div>
      <label htmlFor={name} className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
        {label}
        {required && <span className={cn("ml-1", t.status.error.split(' ')[0])}>*</span>}
      </label>
      {select}
      {error && (
        <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>
          {error}
        </p>
      )}
    </div>
  );
}
