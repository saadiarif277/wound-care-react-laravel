import { ComponentProps } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface CheckboxInputProps extends ComponentProps<'input'> {
  label?: string;
  error?: string;
}

export default function CheckboxInput({
  label,
  name,
  error,
  className,
  ...props
}: CheckboxInputProps) {
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
    <div>
      <label className="flex items-center select-none cursor-pointer" htmlFor={name}>
        <input
          id={name}
          name={name}
          type="checkbox"
          className={cn(
            'mr-3 w-5 h-5 rounded transition-all duration-200 cursor-pointer',
            theme === 'dark'
              ? 'bg-white/[0.08] border-2 border-white/[0.15] checked:bg-gradient-to-r checked:from-[#1925c3] checked:to-[#c71719] checked:border-transparent focus:ring-2 focus:ring-[#1925c3]/50'
              : 'bg-gray-50 border-2 border-gray-300 checked:bg-gradient-to-r checked:from-blue-600 checked:to-blue-700 checked:border-transparent focus:ring-2 focus:ring-blue-500/50',
            error ? (theme === 'dark' ? 'border-red-400' : 'border-red-500') : '',
            className
          )}
          {...props}
        />
        {label && (
          <span className={cn('text-sm', t.text.primary)}>{label}</span>
        )}
      </label>
      {error && (
        <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>
          {error}
        </p>
      )}
    </div>
  );
}

// Also export as named export for backwards compatibility
export { CheckboxInput };
