import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface FieldGroupProps {
  name?: string;
  label?: string;
  error?: string;
  required?: boolean;
  children: React.ReactNode;
}

export default function FieldGroup({
  label,
  name,
  error,
  required = false,
  children
}: FieldGroupProps) {
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

  return (
    <div className="space-y-2">
      {label && (
        <label
          className={cn(
            'block text-sm font-medium select-none',
            t.text.secondary
          )}
          htmlFor={name}
        >
          {label}
          {required && <span className={cn("ml-1", t.status.error.split(' ')[0])}>*</span>}
        </label>
      )}
      {children}
      {error && (
        <div className={cn('mt-2 text-sm', t.status.error.split(' ')[0])}>
          {error}
        </div>
      )}
    </div>
  );
}
