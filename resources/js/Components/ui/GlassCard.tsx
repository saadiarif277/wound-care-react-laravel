import React from 'react';
import clsx from 'clsx';
import { themes, cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface GlassCardProps extends React.HTMLAttributes<HTMLDivElement> {
  /**
   * Visual intent for the card.
   * Variant affects accent colours on borders / background overlays.
   */
  variant?: 'default' | 'danger' | 'success' | 'info' | 'warning' | 'primary' | 'error';
  /**
   * Enable enhanced frost effect for maximum readability
   */
  frost?: boolean;
}

/**
 * GlassCard – reusable glass-morphic container with proper frost effects.
 *
 * Usage:
 * ```tsx
 * <GlassCard className="p-6" variant="info">…</GlassCard>
 * <GlassCard frost className="p-8">Enhanced readability</GlassCard>
 * ```
 */
const GlassCard: React.FC<GlassCardProps> = ({
  variant = 'default',
  frost = false,
  className,
  children,
  ...rest
}) => {
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

  const baseStyles = frost ? t.glass.frost : t.glass.card;

  const variantStyles: Record<Required<GlassCardProps>['variant'], string> = {
    default: '',
    danger: theme === 'dark'
      ? 'bg-red-500/20 border-red-500/30'
      : 'bg-red-50 border-red-200',
    success: theme === 'dark'
      ? 'bg-emerald-500/20 border-emerald-500/30'
      : 'bg-emerald-50 border-emerald-200',
    info: theme === 'dark'
      ? 'bg-blue-500/20 border-blue-500/30'
      : 'bg-blue-50 border-blue-200',
    warning: theme === 'dark'
      ? 'bg-amber-500/20 border-amber-500/30'
      : 'bg-amber-50 border-amber-200',
    primary: theme === 'dark'
      ? 'bg-blue-500/20 border-blue-500/30'
      : 'bg-blue-50 border-blue-200',
    error: theme === 'dark'
      ? 'bg-red-500/20 border-red-500/30'
      : 'bg-red-50 border-red-200',
  };

  return (
    <div
      {...rest}
      className={cn(
        baseStyles,
        'rounded-2xl transition-all duration-300',
        variant !== 'default' && variantStyles[variant],
        frost && 'relative overflow-hidden',
        className
      )}
    >
      {frost && (
        <div className={cn(
          "absolute inset-0 bg-gradient-to-b pointer-events-none",
          theme === 'dark' ? 'from-white/[0.06] to-transparent' : 'from-white/20 to-transparent'
        )} />
      )}
      <div className={cn(frost ? 'relative z-10' : undefined, 'p-8')}>
        {children}
      </div>
    </div>
  );
};

export default GlassCard;
