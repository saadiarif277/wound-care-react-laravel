import React from 'react';
import { glassTheme, cn } from '@/theme/glass-theme';

interface PanelProps extends React.HTMLAttributes<HTMLDivElement> {
  /**
   * Enable hover effects
   */
  hover?: boolean;
  /**
   * Use frost effect for enhanced readability
   */
  frost?: boolean;
  /**
   * Visual variant of the panel
   */
  variant?: 'default' | 'primary' | 'secondary';
  /**
   * Padding preset
   */
  padding?: 'none' | 'compact' | 'normal' | 'spacious';
}

/**
 * Panel component using the glass theme for consistent styling.
 * Can be used as a section container or content wrapper.
 */
const Panel: React.FC<PanelProps> = ({ 
  hover = false, 
  frost = false,
  variant = 'default',
  padding = 'normal',
  className, 
  children,
  ...rest 
}) => {
  const paddingMap = {
    none: '',
    compact: glassTheme.spacing.compact,
    normal: glassTheme.spacing.card,
    spacious: glassTheme.spacing.section,
  };

  const variantStyles = {
    default: frost ? glassTheme.glass.frost : glassTheme.glass.base,
    primary: cn(
      glassTheme.glass.base,
      'border-[#1925c3]/30',
      glassTheme.shadows.glow
    ),
    secondary: cn(
      glassTheme.glass.base,
      'bg-white/[0.05]'
    ),
  };

  return (
    <div
      {...rest}
      className={cn(
        'rounded-2xl transition-all duration-300',
        variantStyles[variant],
        hover && glassTheme.glass.hover,
        paddingMap[padding],
        frost && 'relative overflow-hidden',
        className
      )}
    >
      {frost && (
        <div className="absolute inset-0 bg-gradient-to-b from-white/[0.06] to-transparent pointer-events-none" />
      )}
      <div className={frost ? 'relative z-10' : undefined}>
        {children}
      </div>
    </div>
  );
};

export default Panel;
