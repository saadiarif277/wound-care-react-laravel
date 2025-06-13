import React from 'react';
import GlassCard from './GlassCard';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface StatCardProps {
  title: string;
  value: string | number;
  subtitle?: string;
  icon?: React.ReactNode;
  /** variant forwarded to GlassCard */
  variant?: 'default' | 'danger' | 'success' | 'info' | 'warning';
  /** Enable frost effect for enhanced readability */
  frost?: boolean;
  className?: string;
}

/**
 * Simple metric card built on top of `GlassCard` with proper glass theme styling.
 */
const StatCard: React.FC<StatCardProps> = ({
  title,
  value,
  subtitle,
  icon,
  variant = 'default',
  frost = false,
  className,
}) => {
  // Try to use theme if available, fallback to dark theme
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  // Get appropriate text color based on variant
  const textColors = {
    default: t.text.primary,
    danger: theme === 'dark' ? 'text-red-400' : 'text-red-700',
    success: theme === 'dark' ? 'text-emerald-400' : 'text-emerald-700',
    info: theme === 'dark' ? 'text-blue-400' : 'text-blue-700',
    warning: theme === 'dark' ? 'text-amber-400' : 'text-amber-700',
  };

  const iconColor = variant === 'default' ? (theme === 'dark' ? 'text-white/30' : 'text-gray-400') : textColors[variant];
  const glassTextShadow = theme === 'dark' ? 'drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]' : '';

  return (
    <GlassCard 
      variant={variant} 
      frost={frost}
      className={cn('p-6 group relative overflow-hidden', className)}
    >
      {icon && (
        <div className={cn(
          'absolute top-6 right-6 transition-all duration-300',
          iconColor,
          'group-hover:scale-110 group-hover:opacity-50'
        )}>
          {icon}
        </div>
      )}

      <h3 className={cn(
        'uppercase tracking-wide text-sm font-semibold',
        t.text.secondary,
        frost && glassTextShadow
      )}>
        {title}
      </h3>
      
      <p className={cn(
        'text-3xl font-bold mt-2',
        variant === 'default' ? t.text.primary : textColors[variant],
        frost && glassTextShadow
      )}>
        {value}
      </p>
      
      {subtitle && (
        <p className={cn(
          'text-xs mt-2',
          t.text.muted,
          frost && 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]'
        )}>
          {subtitle}
        </p>
      )}
    </GlassCard>
  );
};

export default StatCard;
