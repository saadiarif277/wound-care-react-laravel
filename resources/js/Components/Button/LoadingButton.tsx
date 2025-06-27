import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { Loader2 } from 'lucide-react';

interface LoadingButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  loading: boolean;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
}

export default function LoadingButton({
  loading,
  className,
  children,
  variant = 'primary',
  ...props
}: LoadingButtonProps) {
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

  const baseStyles = 'flex items-center justify-center px-4 py-2 rounded-xl font-medium transition-all duration-200';

  const variantStyles = {
    primary: cn(t.button.primary.base, t.button.primary.hover),
    secondary: cn(t.button.secondary.base, t.button.secondary.hover),
    ghost: cn(t.button.ghost?.base || '', t.button.ghost?.hover || ''),
    danger: cn(
      theme === 'dark'
        ? 'bg-red-600/80 text-white border border-red-500/30 hover:bg-red-600/90'
        : 'bg-red-600 text-white border border-red-600 hover:bg-red-700'
    ),
  };

  return (
    <button
      disabled={loading || props.disabled}
      className={cn(
        baseStyles,
        variantStyles[variant],
        loading && 'opacity-75 cursor-not-allowed',
        className
      )}
      {...props}
    >
      {loading && (
        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
      )}
      {children}
    </button>
  );
}
