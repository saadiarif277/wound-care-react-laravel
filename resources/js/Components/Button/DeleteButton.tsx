import { ComponentProps } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Props extends ComponentProps<'button'> {
  onDelete: () => void;
}

export default function DeleteButton({ onDelete, children, className, ...props }: Props) {
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
    <button
      className={cn(
        'focus:outline-none hover:underline transition-colors',
        theme === 'dark' ? 'text-red-400 hover:text-red-300' : 'text-red-600 hover:text-red-700',
        className
      )}
      type="button"
      tabIndex={-1}
      onClick={onDelete}
      {...props}
    >
      {children}
    </button>
  );
}
