import React, { useState } from 'react';
import {
  Clock,
  Package,
  DollarSign,
  AlertCircle,
  ChevronDown,
  ChevronUp,
  Send,
  Eye,
  Phone,
  Calendar,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Timer
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { formatCurrency } from '@/utils/formatters';
import { router } from '@inertiajs/react';
import EpisodeTimeline from './EpisodeTimeline';
import EpisodeQuickActions from './EpisodeQuickActions';
import MacValidationPanel from './MacValidationPanel';
import type { Episode } from '@/types/episode';

interface EpisodeCardProps {
  episode: Episode;
  onRefresh?: () => void;
  viewMode?: 'compact' | 'expanded';
  showMacValidation?: boolean;
}

const EpisodeCard: React.FC<EpisodeCardProps> = ({
  episode,
  onRefresh,
  viewMode = 'compact',
  showMacValidation = true
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  // Calculate episode status
  const getEpisodeStatus = () => {
    if (episode.action_required) return 'action-required';
    if (episode.ivr_status === 'expired') return 'expired';

    // Check if expiring soon (within 7 days)
    if (episode.expiration_date) {
      try {
        const expirationDate = new Date(episode.expiration_date);
        const currentDate = new Date();

        if (isNaN(expirationDate.getTime()) || isNaN(currentDate.getTime())) {
          // If dates are invalid, skip expiration check
        } else {
          const daysUntilExpiry = Math.ceil(
            (expirationDate.getTime() - currentDate.getTime()) / (1000 * 60 * 60 * 24)
          );
          if (daysUntilExpiry <= 7 && daysUntilExpiry > 0) return 'expiring-soon';
        }
      } catch (error) {
        // Handle invalid date strings gracefully
        console.warn('Invalid expiration date:', episode.expiration_date);
      }
    }

    // Map episode status to display status
    if (episode.status === 'completed') return 'completed';
    if (episode.status === 'ready_for_review') return 'pending';
    if (episode.ivr_status === 'verified') return 'active';
    return 'active';
  };

  const episodeStatus = getEpisodeStatus();

  // Status configuration
  const statusConfig = {
    'action-required': {
      color: theme === 'dark' ? 'text-red-400' : 'text-red-600',
      bg: theme === 'dark' ? 'bg-red-500/20' : 'bg-red-100',
      border: theme === 'dark' ? 'border-red-500/30' : 'border-red-300',
      icon: AlertTriangle,
      label: 'Action Required',
      pulse: true
    },
    'expired': {
      color: theme === 'dark' ? 'text-red-400' : 'text-red-600',
      bg: theme === 'dark' ? 'bg-red-500/20' : 'bg-red-100',
      border: theme === 'dark' ? 'border-red-500/30' : 'border-red-300',
      icon: XCircle,
      label: 'Expired',
      pulse: false
    },
    'expiring-soon': {
      color: theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600',
      bg: theme === 'dark' ? 'bg-yellow-500/20' : 'bg-yellow-100',
      border: theme === 'dark' ? 'border-yellow-500/30' : 'border-yellow-300',
      icon: Timer,
      label: 'Expiring Soon',
      pulse: true
    },
    'active': {
      color: theme === 'dark' ? 'text-green-400' : 'text-green-600',
      bg: theme === 'dark' ? 'bg-green-500/20' : 'bg-green-100',
      border: theme === 'dark' ? 'border-green-500/30' : 'border-green-300',
      icon: CheckCircle,
      label: 'Active',
      pulse: false
    },
    'pending': {
      color: theme === 'dark' ? 'text-blue-400' : 'text-blue-600',
      bg: theme === 'dark' ? 'bg-blue-500/20' : 'bg-blue-100',
      border: theme === 'dark' ? 'border-blue-500/30' : 'border-blue-300',
      icon: Clock,
      label: 'Processing',
      pulse: false
    },
    'inactive': {
      color: theme === 'dark' ? 'text-gray-400' : 'text-gray-600',
      bg: theme === 'dark' ? 'bg-gray-500/20' : 'bg-gray-100',
      border: theme === 'dark' ? 'border-gray-500/30' : 'border-gray-300',
      icon: XCircle,
      label: 'Inactive',
      pulse: false
    },
    'completed': {
      color: theme === 'dark' ? 'text-green-400' : 'text-green-600',
      bg: theme === 'dark' ? 'bg-green-500/20' : 'bg-green-100',
      border: theme === 'dark' ? 'border-green-500/30' : 'border-green-300',
      icon: CheckCircle,
      label: 'Completed',
      pulse: false
    }
  };

  const status = statusConfig[episodeStatus];
  const StatusIcon = status.icon;

  // Calculate days active - handle invalid dates
  const calculateDaysActive = () => {
    try {
      if (!episode.created_at) return 0;

      const createdDate = new Date(episode.created_at);
      const currentDate = new Date();

      if (isNaN(createdDate.getTime()) || isNaN(currentDate.getTime())) {
        return 0;
      }

      const diffTime = currentDate.getTime() - createdDate.getTime();
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      return diffDays > 0 ? diffDays : 0;
    } catch (error) {
      console.warn('Invalid created_at date:', episode.created_at);
      return 0;
    }
  };

  const daysActive = calculateDaysActive();

  const handleViewDetails = () => {
    router.visit(`/admin/episodes/${episode.id}`);
  };

  return (
    <div
      className={cn(
        "group relative overflow-hidden rounded-xl transition-all duration-300",
        theme === 'dark' ? t.glass.card : 'bg-white shadow-sm',
        "border",
        status.border,
        "hover:shadow-lg hover:scale-[1.01]",
        viewMode === 'expanded' && "col-span-full"
      )}
    >
      {/* Status pulse animation */}
      {status.pulse && (
        <div className="absolute top-4 right-4">
          <div className={cn("w-2 h-2 rounded-full animate-ping", status.bg)}></div>
        </div>
      )}

      {/* Main Card Content */}
      <div className="p-5">
        {/* Header Section */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-start space-x-3">
            {/* Manufacturer Logo/Initial */}
            <div className={cn(
              "w-12 h-12 rounded-lg flex items-center justify-center text-white font-bold text-lg",
              "bg-gradient-to-br from-[#1925c3] to-[#c71719]"
            )}>
              {episode.manufacturer.logo ? (
                <img src={episode.manufacturer.logo} alt={episode.manufacturer.name} className="w-8 h-8 object-contain" />
              ) : (
                episode.manufacturer.name.charAt(0)
              )}
            </div>

            {/* Patient Info */}
            <div>
              <h3 className={cn("font-semibold text-lg", t.text.primary)}>
                {episode.patient_display_id}
              </h3>
              <p className={cn("text-sm", t.text.secondary)}>
                {episode.manufacturer.name}
              </p>
            </div>
          </div>

          {/* Status Badge */}
          <div className={cn(
            "flex items-center space-x-2 px-3 py-1.5 rounded-full",
            status.bg,
            status.color
          )}>
            <StatusIcon className="w-4 h-4" />
            <span className="text-sm font-medium">{status.label}</span>
          </div>
        </div>

        {/* Key Metrics */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          <div className={cn(
            "p-3 rounded-lg",
            theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
          )}>
            <div className="flex items-center space-x-2 mb-1">
              <Package className={cn("w-4 h-4", t.text.secondary)} />
              <span className={cn("text-xs", t.text.secondary)}>Orders</span>
            </div>
            <p className={cn("text-lg font-semibold", t.text.primary)}>
              {episode.orders_count || 0}
            </p>
          </div>

          <div className={cn(
            "p-3 rounded-lg",
            theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
          )}>
            <div className="flex items-center space-x-2 mb-1">
              <DollarSign className={cn("w-4 h-4", t.text.secondary)} />
              <span className={cn("text-xs", t.text.secondary)}>Value</span>
            </div>
            <p className={cn("text-lg font-semibold", t.text.primary)}>
              {formatCurrency(episode.total_order_value || 0)}
            </p>
          </div>

          <div className={cn(
            "p-3 rounded-lg",
            theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
          )}>
            <div className="flex items-center space-x-2 mb-1">
              <Calendar className={cn("w-4 h-4", t.text.secondary)} />
              <span className={cn("text-xs", t.text.secondary)}>Days Active</span>
            </div>
            <p className={cn("text-lg font-semibold", t.text.primary)}>
              {daysActive}
            </p>
          </div>
        </div>

        {/* MAC Validation Indicator - Always Visible */}
        {showMacValidation && (
          <div className={cn(
            "mt-3 p-2 rounded-lg flex items-center justify-between",
            theme === 'dark' ? 'bg-blue-500/10' : 'bg-blue-50'
          )}>
            <div className="flex items-center space-x-2">
              <AlertCircle className={cn("w-4 h-4", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
              <span className={cn("text-xs font-medium", t.text.secondary)}>
                MAC Validation Status
              </span>
            </div>
            <span className={cn("text-xs", t.text.secondary)}>
              Click to expand for details
            </span>
          </div>
        )}

        {/* Quick Actions */}
        <EpisodeQuickActions
          episodeId={episode.id}
          status={episodeStatus}
          onViewDetails={handleViewDetails}
          onRefresh={onRefresh}
        />

        {/* Expandable Section */}
        {isExpanded && (
          <div className={cn(
            "mt-4 pt-4 border-t",
            theme === 'dark' ? 'border-white/10' : 'border-gray-200'
          )}>
            <div className="space-y-4">
              {/* MAC Validation Section */}
              {showMacValidation && (
                <MacValidationPanel
                  episodeId={episode.id}
                  orders={episode.orders}
                  facilityState={episode.orders?.[0]?.facility?.state}
                  className="mb-4"
                />
              )}
              
              {/* Timeline Section */}
              <EpisodeTimeline
                episode={episode}
                theme={theme}
              />
            </div>
          </div>
        )}

        {/* Expand/Collapse Button */}
        <button
          onClick={() => setIsExpanded(!isExpanded)}
          className={cn(
            "mt-3 w-full py-2 flex items-center justify-center space-x-1 rounded-lg transition-colors",
            theme === 'dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50',
            t.text.secondary
          )}
        >
          <span className="text-sm">
            {isExpanded ? 'Show Less' : showMacValidation ? 'Show MAC Validation & Timeline' : 'Show Timeline'}
          </span>
          {isExpanded ? (
            <ChevronUp className="w-4 h-4" />
          ) : (
            <ChevronDown className="w-4 h-4" />
          )}
        </button>
      </div>

      {/* Hover Overlay for Quick Info */}
      <div className={cn(
        "absolute inset-0 bg-gradient-to-t opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none",
        theme === 'dark' ? 'from-blue-500/10 to-transparent' : 'from-blue-50/50 to-transparent'
      )} />
    </div>
  );
};

export default EpisodeCard;
