import React from 'react';
import { Check, CircleX, TriangleAlert } from 'lucide-react';
import CloseButton from '@/Components/Button/CloseButton';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Alert {
  message: string;
  icon?: React.ReactNode;
  action?: React.ReactNode;
  onClose?: () => void;
  variant?: 'success' | 'error' | 'warning';
}

export default function Alert({
  icon,
  action,
  message,
  variant,
  onClose
}: Alert) {
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

  const color = {
    success: 'green',
    error: 'red',
    warning: 'yellow'
  }[variant || 'success'];

  const glassStyles = {
    success: cn(
      t.status.success,
      'border border-green-500/20 text-green-400',
      'bg-green-500/10'
    ),
    error: cn(
      t.status.error,
      'border border-red-500/20 text-red-400',
      'bg-red-500/10'
    ),
    warning: cn(
      t.status.warning,
      'border border-yellow-500/20 text-yellow-400',
      'bg-yellow-500/10'
    )
  }[variant || 'success'];

  const iconComponent = {
    success: <Check size={20} />,
    error: <CircleX size={20} />,
    warning: <TriangleAlert size={20} />
  }[variant || 'success'];

  return (
    <div
      className={cn(
        glassStyles,
        'backdrop-blur-xl px-4 mb-8 flex items-center justify-between rounded-xl max-w-3xl transition-all duration-200',
        theme === 'dark' ? 'shadow-lg shadow-black/20' : 'shadow-lg shadow-gray-500/20'
      )}
    >
      <div className="flex items-center space-x-2">
        {icon || iconComponent}
        <div className="py-4 text-sm font-medium">{message}</div>
      </div>
      {action}
      {onClose && <CloseButton onClick={onClose} color={color} />}
    </div>
  );
}
