import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

/**
 * Custom hook for accessing theme-aware classes
 * Provides easy access to current theme styles
 */
export const useThemeClasses = () => {
  const { theme } = useTheme();
  const t = themes[theme];
  
  return {
    theme,
    t,
    cn,
    
    // Convenience methods
    glass: (variant: keyof typeof t.glass = 'base') => t.glass[variant],
    text: (variant: keyof typeof t.text = 'primary') => t.text[variant],
    button: (variant: keyof typeof t.button = 'primary') => t.button[variant],
    status: (variant: keyof typeof t.status) => t.status[variant],
    
    // Common combinations
    cardClass: (frost?: boolean) => cn(
      frost ? t.glass.frost : t.glass.card,
      t.shadows.glass
    ),
    
    inputClass: (error?: boolean) => cn(
      t.input.base,
      error ? t.input.error : t.input.focus
    ),
    
    tableClass: {
      container: t.table.container,
      header: t.table.header,
      headerText: t.table.headerText,
      row: t.table.row,
      rowHover: t.table.rowHover,
      cell: t.table.cell,
      evenRow: t.table.evenRow,
    },
  };
};