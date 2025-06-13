import React from 'react';
import {
  Clock,
  Send,
  FileText,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Package,
} from 'lucide-react';
import { themes, cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

export type OrderStatus =
  | 'pending_ivr'
  | 'ivr_sent'
  | 'ivr_confirmed'
  | 'approved'
  | 'sent_back'
  | 'denied'
  | 'submitted_to_manufacturer';

interface OrderStatusBadgeProps {
  status: OrderStatus;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
  className?: string;
}

const getStatusConfig = (theme: 'dark' | 'light') => {
  const t = themes[theme];

  return {
    pending_ivr: {
      color: theme === 'dark'
        ? 'bg-white/[0.08] text-white/80 border border-white/[0.12] backdrop-blur-xl'
        : 'bg-gray-100 text-gray-700 border border-gray-200',
      icon: Clock,
      label: 'Pending IVR',
      description: 'Awaiting IVR generation'
    },
    ivr_sent: {
      color: theme === 'dark'
        ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30 backdrop-blur-xl'
        : 'bg-blue-50 text-blue-700 border border-blue-200',
      icon: Send,
      label: 'IVR Sent',
      description: 'IVR sent to manufacturer'
    },
    ivr_confirmed: {
      color: theme === 'dark'
        ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30 backdrop-blur-xl'
        : 'bg-purple-50 text-purple-700 border border-purple-200',
      icon: FileText,
      label: 'IVR Confirmed',
      description: 'Manufacturer confirmed IVR'
    },
    approved: {
      color: theme === 'dark'
        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-xl'
        : 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      icon: CheckCircle,
      label: 'Approved',
      description: 'Ready to submit to manufacturer'
    },
    sent_back: {
      color: theme === 'dark'
        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30 backdrop-blur-xl'
        : 'bg-amber-50 text-amber-700 border border-amber-200',
      icon: AlertTriangle,
      label: 'Sent Back',
      description: 'Returned to provider for changes'
    },
    denied: {
      color: theme === 'dark'
        ? 'bg-red-500/20 text-red-300 border border-red-500/30 backdrop-blur-xl'
        : 'bg-red-50 text-red-700 border border-red-200',
      icon: XCircle,
      label: 'Denied',
      description: 'Order rejected'
    },
    submitted_to_manufacturer: {
      color: theme === 'dark'
        ? 'bg-emerald-500/30 text-emerald-200 border border-emerald-500/40 backdrop-blur-xl'
        : 'bg-emerald-100 text-emerald-800 border border-emerald-300',
      icon: Package,
      label: 'Submitted',
      description: 'Sent to manufacturer'
    },
  };
};

// Export static config for components that need it
export const statusConfig = getStatusConfig('dark');

const OrderStatusBadge: React.FC<OrderStatusBadgeProps> = ({
  status,
  size = 'md',
  showIcon = true,
  className = '',
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const statusConfig = getStatusConfig(theme);
  const config = statusConfig[status];
  if (!config) return null;

  const Icon = config.icon;

  const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
    lg: 'px-3 py-1.5 text-base',
  };

  const iconSizes = {
    sm: 'w-3 h-3',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full font-medium transition-all duration-200',
        config.color,
        sizeClasses[size],
        className
      )}
      title={config.description}
    >
      {showIcon && <Icon className={cn(iconSizes[size], config.label && 'mr-1.5')} />}
      {config.label}
    </span>
  );
};

export default OrderStatusBadge;