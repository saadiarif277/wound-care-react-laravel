import React from 'react';
import { 
  FileCheck, 
  AlertCircle, 
  CheckCircle, 
  Clock,
  FileText,
  Download,
  ExternalLink,
  Calendar,
  Timer
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { formatDate } from '@/utils/formatters';

interface IVREpisode {
  id: string;
  verification_status: string;
  verified_date?: string;
  expiration_date?: string;
  docuseal?: {
    status?: string;
    signed_documents?: Array<{
      id: string;
      url: string;
      filename?: string;
      name?: string;
    }>;
    audit_log_url?: string;
  };
}

interface IVREpisodeStatusProps {
  ivrEpisode: IVREpisode | null;
  readOnly?: boolean;
}

const IVREpisodeStatus: React.FC<IVREpisodeStatusProps> = ({ 
  ivrEpisode, 
  readOnly = false 
}) => {
  if (!ivrEpisode) return null;

  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const isExpired = ivrEpisode.verification_status === 'expired' || 
    (ivrEpisode.expiration_date && new Date(ivrEpisode.expiration_date) < new Date());
  
  const isExpiringSoon = !isExpired && 
    ivrEpisode.expiration_date && 
    (new Date(ivrEpisode.expiration_date) < new Date(Date.now() + 7 * 24 * 60 * 60 * 1000));

  const docuseal = ivrEpisode.docuseal || {};

  // Status configuration
  const getStatusConfig = () => {
    if (isExpired) {
      return {
        icon: AlertCircle,
        color: theme === 'dark' ? 'text-red-400' : 'text-red-600',
        bg: theme === 'dark' ? 'bg-red-500/20' : 'bg-red-100',
        label: 'Expired',
        message: 'This IVR episode has expired and requires re-verification.'
      };
    }
    
    if (isExpiringSoon) {
      return {
        icon: Timer,
        color: theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600',
        bg: theme === 'dark' ? 'bg-yellow-500/20' : 'bg-yellow-100',
        label: 'Expiring Soon',
        message: 'This IVR episode is expiring soon. Schedule re-verification.'
      };
    }

    if (ivrEpisode.verification_status === 'active') {
      return {
        icon: CheckCircle,
        color: theme === 'dark' ? 'text-green-400' : 'text-green-600',
        bg: theme === 'dark' ? 'bg-green-500/20' : 'bg-green-100',
        label: 'Active',
        message: 'IVR verification is current and active.'
      };
    }

    return {
      icon: Clock,
      color: theme === 'dark' ? 'text-gray-400' : 'text-gray-600',
      bg: theme === 'dark' ? 'bg-gray-500/20' : 'bg-gray-100',
      label: 'Pending',
      message: 'IVR verification is pending.'
    };
  };

  const statusConfig = getStatusConfig();
  const StatusIcon = statusConfig.icon;

  // DocuSeal status mapping
  const getDocuSealStatus = (status?: string) => {
    const statusMap = {
      'completed': {
        icon: FileCheck,
        color: theme === 'dark' ? 'text-green-400' : 'text-green-600',
        bg: theme === 'dark' ? 'bg-green-500/20' : 'bg-green-100',
        label: 'Completed'
      },
      'pending': {
        icon: Clock,
        color: theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600',
        bg: theme === 'dark' ? 'bg-yellow-500/20' : 'bg-yellow-100',
        label: 'Pending'
      }
    };

    return statusMap[status as keyof typeof statusMap] || {
      icon: FileText,
      color: theme === 'dark' ? 'text-gray-400' : 'text-gray-600',
      bg: theme === 'dark' ? 'bg-gray-500/20' : 'bg-gray-100',
      label: status || 'Unknown'
    };
  };

  return (
    <div className={cn(
      "rounded-xl p-6",
      theme === 'dark' ? t.glass.card : 'bg-white shadow-sm border border-gray-200'
    )}>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h3 className={cn("text-lg font-semibold", t.text.primary)}>
          IVR Episode Status
        </h3>
        <div className={cn(
          "flex items-center space-x-2 px-3 py-1.5 rounded-full",
          statusConfig.bg,
          statusConfig.color
        )}>
          <StatusIcon className="w-4 h-4" />
          <span className="text-sm font-medium">{statusConfig.label}</span>
        </div>
      </div>

      {/* Status Message */}
      <div className={cn(
        "p-4 rounded-lg mb-6",
        theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
      )}>
        <p className={cn("text-sm", t.text.secondary)}>
          {statusConfig.message}
        </p>
      </div>

      {/* Timeline Information */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div className={cn(
          "p-4 rounded-lg",
          theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
        )}>
          <div className="flex items-center space-x-2 mb-2">
            <CheckCircle className={cn("w-4 h-4", t.text.secondary)} />
            <span className={cn("text-sm font-medium", t.text.secondary)}>
              Verified Date
            </span>
          </div>
          <p className={cn("text-sm font-semibold", t.text.primary)}>
            {ivrEpisode.verified_date ? formatDate(ivrEpisode.verified_date) : 'Not verified'}
          </p>
        </div>

        <div className={cn(
          "p-4 rounded-lg",
          theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
        )}>
          <div className="flex items-center space-x-2 mb-2">
            <Calendar className={cn("w-4 h-4", t.text.secondary)} />
            <span className={cn("text-sm font-medium", t.text.secondary)}>
              Expiration Date
            </span>
          </div>
          <p className={cn("text-sm font-semibold", t.text.primary)}>
            {ivrEpisode.expiration_date ? formatDate(ivrEpisode.expiration_date) : 'No expiration'}
          </p>
        </div>
      </div>

      {/* DocuSeal Status */}
      {docuseal.status && (
        <div className="mb-6">
          <div className="flex items-center justify-between mb-3">
            <h4 className={cn("text-sm font-medium", t.text.primary)}>
              DocuSeal Status
            </h4>
            {(() => {
              const dsStatus = getDocuSealStatus(docuseal.status);
              const DSIcon = dsStatus.icon;
              return (
                <div className={cn(
                  "flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs",
                  dsStatus.bg,
                  dsStatus.color
                )}>
                  <DSIcon className="w-3 h-3" />
                  <span>{dsStatus.label}</span>
                </div>
              );
            })()}
          </div>

          {/* Signed Documents */}
          {docuseal.signed_documents && docuseal.signed_documents.length > 0 && (
            <div className={cn(
              "p-4 rounded-lg space-y-2",
              theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
            )}>
              <p className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                Signed Documents
              </p>
              <div className="space-y-2">
                {docuseal.signed_documents.map((doc, idx) => (
                  <a
                    key={doc.id || idx}
                    href={doc.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={cn(
                      "flex items-center justify-between p-2 rounded-lg transition-colors",
                      theme === 'dark' 
                        ? 'hover:bg-white/10' 
                        : 'hover:bg-gray-100'
                    )}
                  >
                    <div className="flex items-center space-x-2">
                      <FileText className={cn("w-4 h-4", t.text.secondary)} />
                      <span className={cn("text-sm", t.text.primary)}>
                        {doc.filename || doc.name || `Document ${idx + 1}`}
                      </span>
                    </div>
                    <ExternalLink className={cn("w-4 h-4", t.text.secondary)} />
                  </a>
                ))}
              </div>
            </div>
          )}

          {/* Audit Log */}
          {docuseal.audit_log_url && (
            <div className="mt-4">
              <a
                href={docuseal.audit_log_url}
                target="_blank"
                rel="noopener noreferrer"
                className={cn(
                  "inline-flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all",
                  "bg-gradient-to-r from-blue-500 to-blue-600 text-white",
                  "hover:from-blue-600 hover:to-blue-700",
                  "transform hover:scale-105 active:scale-95"
                )}
              >
                <Download className="w-4 h-4" />
                <span>View Audit Log</span>
              </a>
            </div>
          )}
        </div>
      )}

      {/* Action buttons for non-readonly mode */}
      {!readOnly && (isExpired || isExpiringSoon) && (
        <div className={cn(
          "mt-6 p-4 rounded-lg border",
          theme === 'dark' 
            ? 'bg-yellow-500/10 border-yellow-500/30' 
            : 'bg-yellow-50 border-yellow-200'
        )}>
          <p className={cn(
            "text-sm font-medium mb-3",
            theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'
          )}>
            Action Required
          </p>
          <button className={cn(
            "px-4 py-2 rounded-lg font-medium transition-all",
            "bg-gradient-to-r from-yellow-500 to-orange-500 text-white",
            "hover:from-yellow-600 hover:to-orange-600",
            "transform hover:scale-105 active:scale-95"
          )}>
            Send Re-verification Request
          </button>
        </div>
      )}
    </div>
  );
};

export default IVREpisodeStatus;