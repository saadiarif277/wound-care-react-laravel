import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  ArrowPathIcon,
  BellAlertIcon,
  ChartBarIcon,
  SparklesIcon,
  MicrophoneIcon,
  DocumentIcon,
  ArrowDownTrayIcon
} from '@heroicons/react/24/outline';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface IVREpisodeStatusProps {
  ivrEpisode: any;
  readOnly?: boolean;
}

const statusConfig = {
  active: {
    icon: CheckCircleIcon,
    color: 'green',
    bgClass: 'bg-green-500/20',
    borderClass: 'border-green-500/30',
    textClass: 'text-green-600 dark:text-green-400',
    label: 'Active',
    checkAnimation: true
  },
  pending: {
    icon: ClockIcon,
    color: 'amber',
    bgClass: 'bg-amber-500/20',
    borderClass: 'border-amber-500/30',
    textClass: 'text-amber-600 dark:text-amber-400',
    label: 'Pending',
    pulseAnimation: true
  },
  expired: {
    icon: XCircleIcon,
    color: 'red',
    bgClass: 'bg-red-500/20',
    borderClass: 'border-red-500/30',
    textClass: 'text-red-600 dark:text-red-400',
    label: 'Expired',
    shakeAnimation: true
  },
  expiring: {
    icon: ExclamationTriangleIcon,
    color: 'orange',
    bgClass: 'bg-orange-500/20',
    borderClass: 'border-orange-500/30',
    textClass: 'text-orange-600 dark:text-orange-400',
    label: 'Expiring Soon',
    pulseAnimation: true
  }
};

const IVREpisodeStatus = ({ ivrEpisode, readOnly = false }: IVREpisodeStatusProps) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  if (!ivrEpisode) return null;

  const [showPredictiveAlert, setShowPredictiveAlert] = useState(false);
  const [voiceEnabled, setVoiceEnabled] = useState(false);

  const isExpired = ivrEpisode.verification_status === 'expired' ||
    (ivrEpisode.expiration_date && new Date(ivrEpisode.expiration_date) < new Date());

  const isExpiringSoon = !isExpired && ivrEpisode.expiration_date &&
    (new Date(ivrEpisode.expiration_date) < new Date(Date.now() + 7 * 24 * 60 * 60 * 1000));

  const docuseal = ivrEpisode.docuseal || {};

  const currentStatus = isExpired ? 'expired' : isExpiringSoon ? 'expiring' :
    (ivrEpisode.verification_status || 'pending');

  const config = statusConfig[currentStatus as keyof typeof statusConfig] || statusConfig.pending;
  const Icon = config.icon;

  // AI-powered predictive alerts
  useEffect(() => {
    if (isExpiringSoon && !isExpired) {
      setShowPredictiveAlert(true);
    }
  }, [isExpiringSoon, isExpired]);

  const handleVoiceCommand = () => {
    if ('speechSynthesis' in window) {
      const statusText = isExpired ? 'expired' : isExpiringSoon ? 'expiring soon' : 'active';
      const utterance = new SpeechSynthesisUtterance(
        `IVR Episode status is ${statusText}. ${
          ivrEpisode.expiration_date
            ? `Expires on ${new Date(ivrEpisode.expiration_date).toLocaleDateString()}`
            : ''
        }`
      );
      speechSynthesis.speak(utterance);
    }
  };

  const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-2xl p-6`}>
      {/* Header with Voice Control */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <SparklesIcon className="w-5 h-5 text-purple-500" />
          <h3 className={`${t.text.primary} text-lg font-semibold`}>IVR Episode Status</h3>
        </div>
        <button
          onClick={() => {
            setVoiceEnabled(!voiceEnabled);
            if (!voiceEnabled) handleVoiceCommand();
          }}
          className={`p-2 rounded-lg transition-all ${
            voiceEnabled
              ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400'
              : 'hover:bg-white/5'
          }`}
          aria-label="Toggle voice announcements"
        >
          <MicrophoneIcon className="w-5 h-5" />
        </button>
      </div>

      {/* Main Status Display */}
      <div className="relative">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className={`${config.bgClass} ${config.borderClass} border rounded-2xl p-6 mb-4`}
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="relative">
                <motion.div
                  animate={
                    (config as any).shakeAnimation ? { x: [-2, 2, -2, 2, 0] } :
                    (config as any).checkAnimation ? { scale: [1, 1.2, 1] } :
                    {}
                  }
                  transition={
                    (config as any).shakeAnimation ? { duration: 0.5 } :
                    { duration: 0.3 }
                  }
                >
                  <Icon className={`w-12 h-12 ${config.textClass}`} />
                </motion.div>
                {(config as any).pulseAnimation && (
                  <motion.div
                    className={`absolute inset-0 ${config.bgClass} rounded-full`}
                    animate={{ scale: [1, 1.5], opacity: [0.5, 0] }}
                    transition={{ duration: 2, repeat: Infinity }}
                  />
                )}
              </div>
              <div>
                <p className={`${config.textClass} text-2xl font-bold`}>{config.label}</p>
                <div className={`${t.text.muted} text-sm mt-1 space-y-1`}>
                  <p>Verified: {formatDate(ivrEpisode.verified_date)}</p>
                  <p>Expires: {formatDate(ivrEpisode.expiration_date)}</p>
                </div>
              </div>
            </div>

            {/* Docuseal Status Badge */}
            {docuseal.status && (
              <div className="text-right">
                <div className="flex items-center gap-2 mb-1">
                  <DocumentIcon className="w-4 h-4 text-purple-500" />
                  <span className={`${t.text.muted} text-sm`}>Docuseal</span>
                </div>
                <motion.span
                  initial={{ scale: 0.9 }}
                  animate={{ scale: 1 }}
                  className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${
                    docuseal.status === 'completed'
                      ? 'bg-green-500/20 text-green-600 dark:text-green-400'
                      : docuseal.status === 'pending'
                      ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                      : 'bg-gray-500/20 text-gray-600 dark:text-gray-400'
                  }`}
                >
                  {docuseal.status}
                </motion.span>
              </div>
            )}
          </div>
        </motion.div>

        {/* AI Predictive Alert */}
        <AnimatePresence>
          {showPredictiveAlert && isExpiringSoon && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              className="mb-4"
            >
              <div className="flex items-start gap-3 p-4 bg-orange-500/10 border border-orange-500/20 rounded-xl">
                <BellAlertIcon className="w-5 h-5 text-orange-500 mt-0.5" />
                <div className="flex-1">
                  <p className="text-orange-600 dark:text-orange-400 font-medium">
                    AI Alert: IVR Expiring Soon
                  </p>
                  <p className={`${t.text.muted} text-sm mt-1`}>
                    This IVR episode will expire in less than 7 days. Consider initiating renewal process.
                  </p>
                </div>
                <button
                  onClick={() => setShowPredictiveAlert(false)}
                  className="text-orange-500 hover:text-orange-600 transition-colors"
                >
                  <XCircleIcon className="w-5 h-5" />
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Signed Documents */}
        {docuseal.signed_documents && docuseal.signed_documents.length > 0 && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mb-4"
          >
            <h4 className={`${t.text.primary} text-sm font-medium mb-3`}>Signed Documents</h4>
            <div className="space-y-2">
              {docuseal.signed_documents.map((doc: any, idx: number) => (
                <motion.div
                  key={doc.id || idx}
                  initial={{ x: -20, opacity: 0 }}
                  animate={{ x: 0, opacity: 1 }}
                  transition={{ delay: idx * 0.1 }}
                  className={`flex items-center justify-between p-3 ${t.glass.card} ${t.glass.border} rounded-lg`}
                >
                  <div className="flex items-center gap-3">
                    <DocumentIcon className="w-5 h-5 text-blue-500" />
                    <span className={t.text.secondary}>
                      {doc.filename || doc.name || `Document ${idx + 1}`}
                    </span>
                  </div>
                  <a
                    href={doc.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-2 px-3 py-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors"
                  >
                    <ArrowDownTrayIcon className="w-4 h-4" />
                    <span className="text-sm">Download</span>
                  </a>
                </motion.div>
              ))}
            </div>
          </motion.div>
        )}

        {/* Audit Log Button */}
        {docuseal.audit_log_url && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="flex gap-3"
          >
            <motion.a
              href={docuseal.audit_log_url}
              target="_blank"
              rel="noopener noreferrer"
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              className="flex items-center gap-2 px-4 py-2 bg-purple-500/20 text-purple-600 dark:text-purple-400 rounded-lg hover:bg-purple-500/30 transition-colors"
            >
              <ChartBarIcon className="w-4 h-4" />
              <span className="text-sm font-medium">View Audit Log</span>
            </motion.a>

            {!readOnly && isExpiringSoon && (
              <motion.button
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
                className="flex items-center gap-2 px-4 py-2 bg-blue-500/20 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors"
              >
                <ArrowPathIcon className="w-4 h-4" />
                <span className="text-sm font-medium">Renew IVR</span>
              </motion.button>
            )}
          </motion.div>
        )}
      </div>
    </div>
  );
};

export default IVREpisodeStatus;
