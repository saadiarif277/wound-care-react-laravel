import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';

interface MetricCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  trend?: number;
  status?: 'default' | 'info' | 'success' | 'warning' | 'danger';
  subtitle?: string;
  className?: string;
  size?: 'sm' | 'md' | 'lg';
}

const getStatusStyles = (status: string, theme: 'dark' | 'light') => {
  const styles = {
    danger: {
      gradient: theme === 'dark' ? 'from-red-500/30 to-red-600/20' : 'from-red-500/20 to-red-600/10',
      iconBg: theme === 'dark' ? 'from-red-500/40 to-red-600/30' : 'from-red-500/30 to-red-600/20',
      iconColor: theme === 'dark' ? 'text-red-300' : 'text-red-600',
      glow: 'hover:shadow-[0_0_40px_rgba(239,68,68,0.4)]',
      border: theme === 'dark' ? 'border-red-500/20' : 'border-red-200',
      hoverBorder: theme === 'dark' ? 'hover:border-red-500/40' : 'hover:border-red-300',
      overlayGradient: 'from-red-500/10 via-transparent to-transparent'
    },
    success: {
      gradient: theme === 'dark' ? 'from-emerald-500/30 to-emerald-600/20' : 'from-emerald-500/20 to-emerald-600/10',
      iconBg: theme === 'dark' ? 'from-emerald-500/40 to-emerald-600/30' : 'from-emerald-500/30 to-emerald-600/20',
      iconColor: theme === 'dark' ? 'text-emerald-300' : 'text-emerald-600',
      glow: 'hover:shadow-[0_0_40px_rgba(16,185,129,0.4)]',
      border: theme === 'dark' ? 'border-emerald-500/20' : 'border-emerald-200',
      hoverBorder: theme === 'dark' ? 'hover:border-emerald-500/40' : 'hover:border-emerald-300',
      overlayGradient: 'from-emerald-500/10 via-transparent to-transparent'
    },
    warning: {
      gradient: theme === 'dark' ? 'from-amber-500/30 to-amber-600/20' : 'from-amber-500/20 to-amber-600/10',
      iconBg: theme === 'dark' ? 'from-amber-500/40 to-amber-600/30' : 'from-amber-500/30 to-amber-600/20',
      iconColor: theme === 'dark' ? 'text-amber-300' : 'text-amber-600',
      glow: 'hover:shadow-[0_0_40px_rgba(245,158,11,0.4)]',
      border: theme === 'dark' ? 'border-amber-500/20' : 'border-amber-200',
      hoverBorder: theme === 'dark' ? 'hover:border-amber-500/40' : 'hover:border-amber-300',
      overlayGradient: 'from-amber-500/10 via-transparent to-transparent'
    },
    info: {
      gradient: theme === 'dark' ? 'from-blue-500/30 to-blue-600/20' : 'from-blue-500/20 to-blue-600/10',
      iconBg: theme === 'dark' ? 'from-blue-500/40 to-blue-600/30' : 'from-blue-500/30 to-blue-600/20',
      iconColor: theme === 'dark' ? 'text-blue-300' : 'text-blue-600',
      glow: 'hover:shadow-[0_0_40px_rgba(59,130,246,0.4)]',
      border: theme === 'dark' ? 'border-blue-500/20' : 'border-blue-200',
      hoverBorder: theme === 'dark' ? 'hover:border-blue-500/40' : 'hover:border-blue-300',
      overlayGradient: 'from-blue-500/10 via-transparent to-transparent'
    },
    default: {
      gradient: theme === 'dark' ? 'from-[#1925c3]/30 to-[#c71719]/20' : 'from-[#1925c3]/20 to-[#c71719]/10',
      iconBg: theme === 'dark' ? 'from-[#1925c3]/40 to-[#c71719]/30' : 'from-[#1925c3]/30 to-[#c71719]/20',
      iconColor: theme === 'dark' ? 'text-white' : 'text-[#1925c3]',
      glow: 'hover:shadow-[0_0_40px_rgba(25,37,195,0.4)]',
      border: theme === 'dark' ? 'border-white/10' : 'border-gray-200',
      hoverBorder: theme === 'dark' ? 'hover:border-white/20' : 'hover:border-gray-300',
      overlayGradient: 'from-[#1925c3]/10 via-transparent to-transparent'
    }
  };

  return styles[status as keyof typeof styles] || styles.default;
};

const ProgressIndicator: React.FC<{ value: number; theme: 'dark' | 'light' }> = ({ value, theme }) => {
  const isPositive = value >= 0;
  const percentage = Math.min(Math.abs(value), 100);

  return (
    <div className="mt-4">
      <div className="flex justify-between items-center mb-2">
        <span className={cn(
          'text-xs font-semibold uppercase tracking-wider',
          theme === 'dark' ? 'text-white/70' : 'text-gray-600'
        )}>
          Trend
        </span>
        <div className="flex items-center gap-1">
          <div className={cn(
            'w-0 h-0 border-l-[4px] border-l-transparent border-r-[4px] border-r-transparent',
            isPositive
              ? 'border-b-[6px] border-b-emerald-400'
              : 'border-t-[6px] border-t-red-400'
          )} />
          <span className={cn(
            'text-sm font-bold',
            isPositive
              ? theme === 'dark' ? 'text-emerald-400' : 'text-emerald-600'
              : theme === 'dark' ? 'text-red-400' : 'text-red-600'
          )}>
            {isPositive ? '+' : ''}{value}%
          </span>
        </div>
      </div>
      <div className={cn(
        'relative w-full h-2 rounded-full overflow-hidden',
        theme === 'dark' ? 'bg-white/10' : 'bg-gray-200'
      )}>
        {/* Glow effect for progress bar */}
        <div className={cn(
          'absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500',
          isPositive ? 'bg-emerald-500/20' : 'bg-red-500/20',
          'blur-sm'
        )} />

        <div
          className={cn(
            'relative h-full rounded-full transition-all duration-1000 ease-out',
            isPositive
              ? 'bg-gradient-to-r from-emerald-500 to-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.5)]'
              : 'bg-gradient-to-r from-red-500 to-red-400 shadow-[0_0_10px_rgba(239,68,68,0.5)]'
          )}
          style={{ width: `${percentage}%` }}
        >
          {/* Animated shine effect */}
          <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer" />
        </div>
      </div>
    </div>
  );
};

const MetricCard: React.FC<MetricCardProps> = ({
  title,
  value,
  icon,
  trend,
  status = 'default',
  subtitle,
  className,
  size = 'lg'
}) => {
  // Try to use theme if available, fallback to dark theme
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const statusStyles = getStatusStyles(status, theme);

  // Size configurations
  const sizeConfig = {
    sm: {
      padding: 'p-4',
      iconSize: 'w-12 h-12',
      iconClass: 'w-5 h-5',
      titleSize: 'text-xs',
      valueSize: 'text-2xl',
      subtitleSize: 'text-xs',
      gap: 'pr-3',
      titleMargin: 'mb-1',
      valueMargin: 'mb-1',
    },
    md: {
      padding: 'p-6',
      iconSize: 'w-14 h-14',
      iconClass: 'w-6 h-6',
      titleSize: 'text-sm',
      valueSize: 'text-3xl',
      subtitleSize: 'text-sm',
      gap: 'pr-4',
      titleMargin: 'mb-2',
      valueMargin: 'mb-1',
    },
    lg: {
      padding: 'p-8',
      iconSize: 'w-16 h-16',
      iconClass: 'w-8 h-8',
      titleSize: 'text-sm',
      valueSize: 'text-4xl',
      subtitleSize: 'text-sm',
      gap: 'pr-4',
      titleMargin: 'mb-3',
      valueMargin: 'mb-2',
    }
  };

  const config = sizeConfig[size];

  return (
    <div className={cn(
      'relative overflow-hidden rounded-2xl transition-all duration-500 group',
      'hover:scale-[1.02] hover:-translate-y-1',
      theme === 'dark'
        ? 'bg-white/[0.15] backdrop-blur-xl backdrop-saturate-150 border-2 shadow-2xl'
        : 'bg-white/95 backdrop-blur-sm border-2 shadow-lg',
      statusStyles.border,
      statusStyles.hoverBorder,
      statusStyles.glow,
      theme === 'dark' ? 'shadow-black/40' : 'shadow-gray-200/50',
      className
    )}>
      {/* Gradient overlay for status indication */}
      <div className={cn(
        'absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-500',
        statusStyles.overlayGradient
      )} />

      {/* Noise texture overlay for glass effect */}
      <div className="absolute inset-0 opacity-[0.02] mix-blend-overlay">
        <div className="absolute inset-0 bg-[url('/noise.png')]" />
      </div>

      {/* Content wrapper */}
      <div className={cn("relative z-10 flex justify-between items-start", config.padding)}>
        {/* Content */}
        <div className={cn("flex-1", config.gap)}>
          <p className={cn(
            config.titleSize,
            'font-semibold tracking-wide uppercase',
            config.titleMargin,
            theme === 'dark' ? 'text-white/80' : 'text-gray-700'
          )}>
            {title}
          </p>
          <p className={cn(
            config.valueSize,
            'font-bold tracking-tight',
            config.valueMargin,
            theme === 'dark' ? 'text-white' : 'text-gray-900',
            'drop-shadow-lg'
          )}>
            {value}
          </p>
          {subtitle && (
            <p className={cn(
              config.subtitleSize,
              theme === 'dark' ? 'text-white/60' : 'text-gray-600'
            )}>
              {subtitle}
            </p>
          )}
        </div>

        {/* Enhanced Icon Container */}
        <div className="relative">
          {/* Icon glow effect */}
          <div className={cn(
            'absolute inset-0 rounded-2xl blur-xl opacity-0 group-hover:opacity-60 transition-opacity duration-500',
            'bg-gradient-to-br',
            statusStyles.gradient
          )} />

          {/* Icon background */}
          <div className={cn(
            'relative flex-shrink-0 rounded-2xl',
            config.iconSize,
            'bg-gradient-to-br backdrop-blur-md',
            statusStyles.iconBg,
            'flex items-center justify-center',
            'transform group-hover:rotate-3 transition-transform duration-500',
            theme === 'dark' ? 'shadow-lg shadow-black/20' : 'shadow-md'
          )}>
            <div className={cn(
              statusStyles.iconColor,
              'transform group-hover:scale-110 transition-transform duration-500'
            )}>
              {React.cloneElement(icon as React.ReactElement, { className: cn(config.iconClass, 'drop-shadow-md') })}
            </div>
          </div>
        </div>
      </div>

      {/* Enhanced Progress bar */}
      {trend !== undefined && (
        <div className={cn("relative -mt-2", config.padding, "pt-0")}>
          <ProgressIndicator value={trend} theme={theme} />
        </div>
      )}

      {/* Bottom gradient fade for depth */}
      <div className={cn(
        'absolute bottom-0 left-0 right-0 h-1/3 bg-gradient-to-t pointer-events-none',
        theme === 'dark' ? 'from-black/10 to-transparent' : 'from-gray-100/20 to-transparent'
      )} />
    </div>
  );
};

export default MetricCard;