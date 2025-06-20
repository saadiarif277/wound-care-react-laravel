// Clean, Beautiful Status Cards - Demo Ready

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

interface CleanStatusCardProps {
  title: string;
  count: number;
  trend?: number;
  onClick?: () => void;
  isActive?: boolean;
  status: string;
}

const CleanStatusCard: React.FC<CleanStatusCardProps> = ({
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
        textColor: 'text-blue-400',
        bgColor: 'bg-blue-500/10'
      },
      submitted: {
        icon: <Package className="h-5 w-5" />,
        color: 'from-yellow-500 to-amber-400',
        textColor: 'text-yellow-400',
        bgColor: 'bg-yellow-500/10'
      },
      processing: {
        icon: <Clock className="h-5 w-5" />,
        color: 'from-purple-500 to-purple-400',
        textColor: 'text-purple-400',
        bgColor: 'bg-purple-500/10'
      },
      approved: {
        icon: <CheckCircle className="h-5 w-5" />,
        color: 'from-green-500 to-emerald-400',
        textColor: 'text-green-400',
        bgColor: 'bg-green-500/10'
      },
      shipped: {
        icon: <Truck className="h-5 w-5" />,
        color: 'from-indigo-500 to-indigo-400',
        textColor: 'text-indigo-400',
        bgColor: 'bg-indigo-500/10'
      },
      delivered: {
        icon: <CheckCircle className="h-5 w-5" />,
        color: 'from-green-600 to-green-500',
        textColor: 'text-green-500',
        bgColor: 'bg-green-600/10'
      },
      rejected: {
        icon: <XCircle className="h-5 w-5" />,
        color: 'from-red-500 to-red-400',
        textColor: 'text-red-400',
        bgColor: 'bg-red-500/10'
      },
      cancelled: {
        icon: <XCircle className="h-5 w-5" />,
        color: 'from-gray-500 to-gray-400',
        textColor: 'text-gray-400',
        bgColor: 'bg-gray-500/10'
      }
    };
    return configs[status as keyof typeof configs] || configs.draft;
  };

  const config = getStatusConfig(status);

  return (
    <GlassCard
      variant={isActive ? 'info' : 'default'}
      className={`cursor-pointer transition-all duration-300 hover:scale-105 group ${
        isActive ? 'ring-2 ring-blue-500/50' : ''
      }`}
      onClick={onClick}
    >
      <div className="p-4">
        {/* Icon and trend in header */}
        <div className="flex items-center justify-between mb-3">
          <div className={`p-2 rounded-lg ${config.bgColor} ${config.textColor}`}>
            {config.icon}
          </div>

          {/* Theme-aware trend indicator - only show if meaningful */}
          {trend !== undefined && trend !== 0 && Math.abs(trend) < 200 && (
            <div className={`flex items-center space-x-1 text-xs ${
              trend > 0
                ? (theme === 'dark' ? 'text-green-400' : 'text-green-600')
                : (theme === 'dark' ? 'text-red-400' : 'text-red-600')
            }`}>
              {trend > 0 ? (
                <TrendingUp className="h-3 w-3" />
              ) : (
                <TrendingDown className="h-3 w-3" />
              )}
              <span>{Math.abs(trend)}%</span>
            </div>
          )}
        </div>

        {/* Title and count */}
        <div className="space-y-1">
          <p className={`text-sm font-medium ${t.text.secondary}`}>{title}</p>
          <p className={`text-2xl font-bold ${t.text.primary} group-hover:${config.textColor} transition-colors duration-300`}>
            {count}
          </p>
        </div>

        {/* Theme-aware progress indicator */}
        <div className={`mt-3 w-full rounded-full h-1 ${
          theme === 'dark' ? 'bg-white/5' : 'bg-gray-200'
        }`}>
          <div
            className={`h-full bg-gradient-to-r ${config.color} rounded-full transition-all duration-500`}
            style={{
              width: count > 0 ? '100%' : '0%'
            }}
          />
        </div>
      </div>
    </GlassCard>
  );
};

// Clean dashboard component
export const CleanStatusDashboard: React.FC<{
  statusOptions: Array<{ value: string; label: string; count: number; trend?: number }>;
  onStatusFilter: (status: string) => void;
  activeFilter?: string;
  totalRequests?: number;
}> = ({ statusOptions, onStatusFilter, activeFilter, totalRequests }) => {

  const { theme } = useTheme();
  const t = themes[theme];

  return (
    <div className="mb-8">
      {/* Clean header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-3">
          <h2 className="text-xl font-semibold bg-gradient-to-r from-[#1925c3] to-[#c71719] bg-clip-text text-transparent">
            Request Status Overview
          </h2>
          <div className={`flex items-center space-x-2 text-xs ${
            theme === 'dark' ? 'text-green-400' : 'text-green-600'
          }`}>
            <div className={`w-2 h-2 rounded-full animate-pulse ${
              theme === 'dark' ? 'bg-green-400' : 'bg-green-600'
            }`} />
            <span>Live</span>
          </div>
        </div>
        <p className={`text-sm ${t.text.muted}`}>
          Updated: {new Date().toLocaleTimeString()}
        </p>
      </div>

      {/* Clean grid - optimized for 5 cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {statusOptions.map((item) => (
          <CleanStatusCard
            key={item.value}
            title={item.label}
            count={item.count}
            trend={item.trend}
            status={item.value}
            isActive={activeFilter === item.value}
            onClick={() => onStatusFilter(item.value)}
          />
        ))}
      </div>

      {/* Clean summary - only show if we have data */}
      {totalRequests && totalRequests > 0 && (
        <div className={`mt-6 p-4 backdrop-blur-xl rounded-xl border ${
          theme === 'dark'
            ? 'bg-white/5 border-white/10'
            : 'bg-white/60 border-gray-200'
        }`}>
          <div className="grid grid-cols-3 gap-6 text-center">
            <div>
              <p className={`text-xl font-bold ${
                theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
              }`}>{totalRequests}</p>
              <p className={`text-sm ${t.text.muted}`}>Total Requests</p>
            </div>
            <div>
              <p className={`text-xl font-bold ${
                theme === 'dark' ? 'text-green-400' : 'text-green-600'
              }`}>
                {statusOptions.find(s => s.value === 'approved')?.count || 0}
              </p>
              <p className={`text-sm ${t.text.muted}`}>Approved</p>
            </div>
            <div>
              <p className={`text-xl font-bold ${
                theme === 'dark' ? 'text-indigo-400' : 'text-indigo-600'
              }`}>
                {statusOptions.find(s => s.value === 'shipped')?.count || 0}
              </p>
              <p className={`text-sm ${t.text.muted}`}>Shipped</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
