import { ComponentProps } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface TextInputProps extends ComponentProps<'input'> {
  label?: string;
  error?: string;
  required?: boolean;
}

export default function TextInput({
  label,
  name,
  className = '',
  error,
  required = false,
  ...props
}: TextInputProps) {
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

  const input = (
    <input
      id={name}
      name={name}
      {...props}
      className={cn(
        t.input.base,
        t.input.focus,
        error ? t.input.error : '',
        className
      )}
    />
  );

  // If no label, return just the input
  if (!label) {
    return input;
  }

  // Return labeled input
  return (
    <div>
      <label htmlFor={name} className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
        {label}
        {required && <span className={cn("ml-1", t.status.error.split(' ')[0])}>*</span>}
      </label>
      {input}
      {error && (
        <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>
          {error}
        </p>
      )}
    </div>
  );
}
