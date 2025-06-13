import React from 'react';
import {
  Clock,
  Send,
  FileText,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Package,
  Truck,
} from 'lucide-react';
import { themes, cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

export type ProductRequestStatus = 
  | 'draft' 
  | 'submitted' 
  | 'processing' 
  | 'approved' 
  | 'rejected' 
  | 'shipped' 
  | 'delivered' 
  | 'cancelled';

interface ProductRequestStatusBadgeProps {
  status: ProductRequestStatus;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
  className?: string;
}

const getStatusConfig = (theme: 'dark' | 'light') => {
  return {
    draft: { 
      color: theme === 'dark' 
        ? 'bg-white/[0.08] text-white/80 border border-white/[0.12] backdrop-blur-xl'
        : 'bg-gray-100 text-gray-700 border border-gray-200',
      icon: Clock, 
      label: 'Draft',
      description: 'Request not yet submitted'
    },
    submitted: { 
      color: theme === 'dark'
        ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30 backdrop-blur-xl'
        : 'bg-blue-50 text-blue-700 border border-blue-200',
      icon: Send, 
      label: 'Submitted',
      description: 'Request submitted for processing'
    },
    processing: { 
      color: theme === 'dark'
        ? 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30 backdrop-blur-xl'
        : 'bg-yellow-50 text-yellow-700 border border-yellow-200',
      icon: Clock, 
      label: 'Processing',
      description: 'Request being processed'
    },
    approved: { 
      color: theme === 'dark'
        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-xl'
        : 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      icon: CheckCircle, 
      label: 'Approved',
      description: 'Request approved'
    },
    rejected: { 
      color: theme === 'dark'
        ? 'bg-red-500/20 text-red-300 border border-red-500/30 backdrop-blur-xl'
        : 'bg-red-50 text-red-700 border border-red-200',
      icon: XCircle, 
      label: 'Rejected',
      description: 'Request rejected'
    },
    shipped: { 
      color: theme === 'dark'
        ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30 backdrop-blur-xl'
        : 'bg-purple-50 text-purple-700 border border-purple-200',
      icon: Truck, 
      label: 'Shipped',
      description: 'Order shipped'
    },
    delivered: { 
      color: theme === 'dark'
        ? 'bg-emerald-500/30 text-emerald-200 border border-emerald-500/40 backdrop-blur-xl'
        : 'bg-emerald-100 text-emerald-800 border border-emerald-300',
      icon: Package, 
      label: 'Delivered',
      description: 'Order delivered'
    },
    cancelled: { 
      color: theme === 'dark'
        ? 'bg-red-500/20 text-red-300 border border-red-500/30 backdrop-blur-xl'
        : 'bg-red-50 text-red-700 border border-red-200',
      icon: XCircle, 
      label: 'Cancelled',
      description: 'Request cancelled'
    },
  };
};

const ProductRequestStatusBadge: React.FC<ProductRequestStatusBadgeProps> = ({
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

export default ProductRequestStatusBadge;