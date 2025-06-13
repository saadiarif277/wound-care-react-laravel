import { ComponentProps } from 'react';
import { X } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface CloseButtonProps extends ComponentProps<'button'> {
  color?: string | 'red' | 'green' | 'yellow';
}

export default function CloseButton({ color, onClick, className, ...props }: CloseButtonProps) {
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

  const iconColor = {
    'red': theme === 'dark' ? 'text-red-400' : 'text-red-600',
    'green': theme === 'dark' ? 'text-green-400' : 'text-green-600',
    'yellow': theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600',
  }[color || 'red'] || t.text.muted;

  return (
    <button
      onClick={onClick}
      type="button"
      className={cn(
        'p-2 rounded-lg transition-all duration-200',
        theme === 'dark'
          ? 'hover:bg-white/10 focus:ring-white/20'
          : 'hover:bg-black/10 focus:ring-black/20',
        'focus:outline-none focus:ring-2',
        className
      )}
      {...props}
    >
      <X size={16} className={cn('block', iconColor)} />
    </button>
  );
}
