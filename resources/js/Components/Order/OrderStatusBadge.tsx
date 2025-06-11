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

const statusConfig = {
  pending_ivr: { 
    color: 'bg-gray-100 text-gray-800 border-gray-300', 
    icon: Clock, 
    label: 'Pending IVR',
    description: 'Awaiting IVR generation'
  },
  ivr_sent: { 
    color: 'bg-blue-100 text-blue-800 border-blue-300', 
    icon: Send, 
    label: 'IVR Sent',
    description: 'IVR sent to manufacturer'
  },
  ivr_confirmed: { 
    color: 'bg-purple-100 text-purple-800 border-purple-300', 
    icon: FileText, 
    label: 'IVR Confirmed',
    description: 'Manufacturer confirmed IVR'
  },
  approved: { 
    color: 'bg-green-100 text-green-800 border-green-300', 
    icon: CheckCircle, 
    label: 'Approved',
    description: 'Ready to submit to manufacturer'
  },
  sent_back: { 
    color: 'bg-orange-100 text-orange-800 border-orange-300', 
    icon: AlertTriangle, 
    label: 'Sent Back',
    description: 'Returned to provider for changes'
  },
  denied: { 
    color: 'bg-red-100 text-red-800 border-red-300', 
    icon: XCircle, 
    label: 'Denied',
    description: 'Order rejected'
  },
  submitted_to_manufacturer: { 
    color: 'bg-green-900 text-white border-green-900', 
    icon: Package, 
    label: 'Submitted',
    description: 'Sent to manufacturer'
  },
};

const OrderStatusBadge: React.FC<OrderStatusBadgeProps> = ({
  status,
  size = 'md',
  showIcon = true,
  className = '',
}) => {
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
      className={`inline-flex items-center rounded-full font-medium border ${config.color} ${sizeClasses[size]} ${className}`}
      title={config.description}
    >
      {showIcon && <Icon className={`${iconSizes[size]} ${config.label ? 'mr-1.5' : ''}`} />}
      {config.label}
    </span>
  );
};

export default OrderStatusBadge;

// Export status config for use in other components
export { statusConfig };