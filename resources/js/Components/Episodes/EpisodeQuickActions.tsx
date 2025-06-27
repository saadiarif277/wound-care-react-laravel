import React from 'react';
import { 
  Eye, 
  Send, 
  Phone, 
  FileText,
  CheckCircle
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { router } from '@inertiajs/react';

interface EpisodeQuickActionsProps {
  episodeId: string;
  status: string;
  onViewDetails: () => void;
  onRefresh?: () => void;
}

const EpisodeQuickActions: React.FC<EpisodeQuickActionsProps> = ({
  episodeId,
  status,
  onViewDetails,
  onRefresh
}) => {
  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme
  }

  const handleSendIVR = () => {
    // Handle IVR sending logic
    router.post(`/api/episodes/${episodeId}/send-ivr`, {}, {
      onSuccess: () => {
        if (onRefresh) onRefresh();
      }
    });
  };

  const handleContact = () => {
    // Handle contact logic
    router.visit(`/episodes/${episodeId}/contact`);
  };

  const handleGenerateDocuments = () => {
    // Handle document generation
    router.post(`/api/episodes/${episodeId}/generate-documents`, {}, {
      onSuccess: () => {
        if (onRefresh) onRefresh();
      }
    });
  };

  // Determine which actions to show based on status
  const getActions = () => {
    const baseActions = [
      {
        id: 'view',
        label: 'View Details',
        icon: Eye,
        onClick: onViewDetails,
        variant: 'primary',
        show: true
      }
    ];

    if (status === 'expired' || status === 'expiring-soon') {
      baseActions.push({
        id: 'send-ivr',
        label: 'Send IVR',
        icon: Send,
        onClick: handleSendIVR,
        variant: 'warning',
        show: true
      });
    }

    if (status === 'action-required') {
      baseActions.push({
        id: 'resolve',
        label: 'Resolve',
        icon: CheckCircle,
        onClick: onViewDetails,
        variant: 'danger',
        show: true
      });
    }

    baseActions.push(
      {
        id: 'contact',
        label: 'Contact',
        icon: Phone,
        onClick: handleContact,
        variant: 'secondary',
        show: true
      },
      {
        id: 'documents',
        label: 'Documents',
        icon: FileText,
        onClick: handleGenerateDocuments,
        variant: 'secondary',
        show: ['active', 'completed'].includes(status)
      }
    );

    return baseActions.filter(action => action.show);
  };

  const actions = getActions();

  // Button variant styles
  const getButtonStyle = (variant: string) => {
    const styles: Record<string, string> = {
      primary: cn(
        "bg-gradient-to-r from-blue-500 to-blue-600 text-white",
        "hover:from-blue-600 hover:to-blue-700",
        "focus:ring-2 focus:ring-blue-500/50"
      ),
      secondary: cn(
        theme === 'dark' 
          ? "bg-white/10 text-white hover:bg-white/20" 
          : "bg-gray-100 text-gray-700 hover:bg-gray-200",
        "focus:ring-2",
        theme === 'dark' ? "focus:ring-white/30" : "focus:ring-gray-300"
      ),
      warning: cn(
        "bg-gradient-to-r from-yellow-500 to-orange-500 text-white",
        "hover:from-yellow-600 hover:to-orange-600",
        "focus:ring-2 focus:ring-yellow-500/50"
      ),
      danger: cn(
        "bg-gradient-to-r from-red-500 to-red-600 text-white",
        "hover:from-red-600 hover:to-red-700",
        "focus:ring-2 focus:ring-red-500/50"
      )
    };

    return styles[variant] || styles.secondary;
  };

  return (
    <div className="flex items-center space-x-2">
      {actions.map((action) => {
        const Icon = action.icon;

        return (
          <button
            key={action.id}
            onClick={action.onClick}
            className={cn(
              "flex items-center space-x-1.5 px-3 py-2 rounded-lg",
              "text-sm font-medium transition-all duration-200",
              "transform hover:scale-105 active:scale-95",
              getButtonStyle(action.variant)
            )}
            title={action.label}
          >
            <Icon className="w-4 h-4" />
            <span className="hidden sm:inline">{action.label}</span>
          </button>
        );
      })}

    </div>
  );
};

export default EpisodeQuickActions;