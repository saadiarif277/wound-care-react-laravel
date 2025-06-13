// Real Data Enhanced Status Cards - No Mock Data

import React from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import GlassCard from '@/Components/ui/GlassCard';
import { 
  TrendingUp, 
  TrendingDown, 
  Clock, 
  CheckCircle,
  Package,
  Truck,
  FileText,
  XCircle 
} from 'lucide-react';

interface RealStatusCardProps {
  title: string;
  count: number;
  trend?: number; // real percentage change from last week
  onClick?: () => void;
  isActive?: boolean;
  status: 'draft' | 'submitted' | 'processing' | 'approved' | 'rejected' | 'shipped' | 'delivered' | 'cancelled';
}

const RealStatusCard: React.FC<RealStatusCardProps> = ({
  title,
  count,
  trend,
  onClick,
  isActive,
  status
}) => {
  const { theme } = useTheme();
  const t = themes[theme];

  const getStatusConfig = (status: string) => {
    const configs = {
      draft: {
        icon: <FileText className="h-5 w-5" />,
        color: 'from-blue-500 to-blue-400',
        bgGlow: 'shadow-[0_0_20px_rgba(59,130,246,0.3)]',
        textColor: 'text-blue-400'
      },
      submitted: {
        icon: <Package className="h-5 w-5" />,
        color: 'from-yellow-500 to-amber-400',
        bgGlow: 'shadow-[0_0_20px_rgba(245,158,11,0.3)]',
        textColor: 'text-yellow-400'
      },
      processing: {
        icon: <Clock className="h-5 w-5 animate-pulse" />,
        color: 'from-purple-500 to-purple-400',
        bgGlow: 'shadow-[0_0_20px_rgba(168,85,247,0.3)]',
        textColor: 'text-purple-400'
      },
      approved: {
        icon: <CheckCircle className="h-5 w-5" />,
        color: 'from-green-500 to-emerald-400',
        bgGlow: 'shadow-[0_0_20px_rgba(34,197,94,0.3)]',
        textColor: 'text-green-400'
      },
      shipped: {
        icon: <Truck className="h-5 w-5" />,
        color: 'from-indigo-500 to-indigo-400',
        bgGlow: 'shadow-[0_0_20px_rgba(99,102,241,0.3)]',
        textColor: 'text-indigo-400'
      },
      delivered: {
        icon: <CheckCircle className="h-5 w-5" />,
        color: 'from-green-600 to-green-500',
        bgGlow: 'shadow-[0_0_20px_rgba(22,163,74,0.4)]',
        textColor: 'text-green-500'
      },
      rejected: {
        icon: <XCircle className="h-5 w-5" />,
        color: 'from-red-500 to-red-400',
        bgGlow: 'shadow-[0_0_20px_rgba(239,68,68,0.3)]',
        textColor: 'text-red-400'
      },
      cancelled: {
        icon: <XCircle className="h-5 w-5" />,
        color: 'from-gray-500 to-gray-400',
        bgGlow: 'shadow-[0_0_20px_rgba(107,114,128,0.3)]',
        textColor: 'text-gray-400'
      }
    };
    return configs[status] || configs.draft;
  };

  const config = getStatusConfig(status);

  return (
    <GlassCard
      variant={isActive ? 'info' : 'default'}
      className={`cursor-pointer transition-all duration-300 hover:scale-105 hover:${config.bgGlow} group relative overflow-hidden ${
        isActive ? 'ring-2 ring-blue-500/50' : ''
      }`}
      onClick={onClick}
    >
      {/* Animated background gradient */}
      <div className={`absolute inset-0 bg-gradient-to-br ${config.color} opacity-5 group-hover:opacity-10 transition-opacity duration-300`} />
      
      <div className="relative p-5">
        {/* Header with icon and trend */}
        <div className="flex items-center justify-between mb-3">
          <div className={`p-2 rounded-xl bg-gradient-to-br ${config.color} bg-opacity-20 ${config.textColor}`}>
            {config.icon}
          </div>
          
          {/* Real trend indicator (only show if we have real data) */}
          {trend !== undefined && trend !== 0 && (
            <div className={`flex items-center space-x-1 ${
              trend > 0 ? 'text-green-400' : 'text-red-400'
            }`}>
              {trend > 0 ? (
                <TrendingUp className="h-4 w-4" />
              ) : (
                <TrendingDown className="h-4 w-4" />
              )}
              <span className="text-xs font-medium">
                {Math.abs(trend)}%
              </span>
            </div>
          )}
        </div>

        {/* Main content */}
        <div className="space-y-2">
          <p className={`text-sm font-medium ${t.text.secondary}`}>{title}</p>
          <p className={`text-3xl font-bold ${t.text.primary} group-hover:${config.textColor} transition-colors duration-300`}>
            {count}
          </p>
          
          {/* Show trend description if we have real data */}
          {trend !== undefined && trend !== 0 && (
            <p className={`text-xs ${trend > 0 ? 'text-green-400' : 'text-red-400'}`}>
              {trend > 0 ? 'Up' : 'Down'} from last week
            </p>
          )}
        </div>

        {/* Progress bar showing relative volume */}
        <div className="mt-4 w-full bg-white/5 rounded-full h-1.5 overflow-hidden">
          <div 
            className={`h-full bg-gradient-to-r ${config.color} transition-all duration-1000 ease-out`}
            style={{ 
              width: `${Math.min((count / Math.max(count, 10)) * 100, 100)}%` // Responsive to actual data
            }}
          />
        </div>

        {/* Hover effect overlay */}
        <div className="absolute inset-0 bg-gradient-to-br from-white/0 to-white/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none" />
      </div>
    </GlassCard>
  );
};

// Usage component with real data calculations
export const RealDataStatusDashboard: React.FC<{ 
  statusOptions: Array<{ value: string; label: string; count: number; trend?: number }>;
  onStatusFilter: (status: string) => void;
  activeFilter?: string;
  totalRequests?: number;
}> = ({ statusOptions, onStatusFilter, activeFilter, totalRequests }) => {
  
  // Calculate max count for progress bars
  const maxCount = Math.max(...statusOptions.map(s => s.count), 1);

  return (
    <div className="mb-8">
      {/* Header with live indicator */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-3">
          <h2 className="text-xl font-semibold bg-gradient-to-r from-[#1925c3] to-[#c71719] bg-clip-text text-transparent">
            Request Status Overview
          </h2>
          <div className="flex items-center space-x-2 text-xs text-green-400">
            <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
            <span>Live</span>
          </div>
        </div>
        <p className="text-sm text-gray-400">
          Updated: {new Date().toLocaleTimeString()}
        </p>
      </div>

      {/* Enhanced status cards grid */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-4 xl:grid-cols-8">
        {statusOptions.map((item) => (
          <RealStatusCard
            key={item.value}
            title={item.label}
            count={item.count}
            trend={item.trend} // Real trend data from backend
            status={item.value as any}
            isActive={activeFilter === item.value}
            onClick={() => onStatusFilter(item.value)}
          />
        ))}
      </div>

      {/* Simple real stats summary */}
      {totalRequests && totalRequests > 0 && (
        <div className="mt-6 p-4 bg-white/5 backdrop-blur-xl rounded-xl border border-white/10">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            <div>
              <p className="text-2xl font-bold text-blue-400">{totalRequests}</p>
              <p className="text-sm text-gray-400">Total Requests</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-green-400">
                {statusOptions.find(s => s.value === 'approved')?.count || 0}
              </p>
              <p className="text-sm text-gray-400">Approved</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-purple-400">
                {statusOptions.find(s => s.value === 'processing')?.count || 0}
              </p>
              <p className="text-sm text-gray-400">In Progress</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
