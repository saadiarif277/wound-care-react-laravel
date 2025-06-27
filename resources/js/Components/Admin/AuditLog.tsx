import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  ClockIcon,
  UserIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  FunnelIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  BellIcon,
  SparklesIcon,
  MicrophoneIcon,
  ChartBarIcon
} from '@heroicons/react/24/outline';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface AuditEntry {
  id: string;
  action: string;
  user: string;
  user_role?: string;
  timestamp: string;
  description: string;
  severity?: 'info' | 'warning' | 'error' | 'success';
  metadata?: Record<string, any>;
  ip_address?: string;
}

interface AuditLogProps {
  entries?: AuditEntry[];
  autoRefresh?: boolean;
}

const severityConfig = {
  info: {
    icon: InformationCircleIcon,
    color: 'blue',
    bgClass: 'bg-blue-500/10',
    textClass: 'text-blue-600 dark:text-blue-400',
    borderClass: 'border-blue-500/20'
  },
  warning: {
    icon: ExclamationTriangleIcon,
    color: 'amber',
    bgClass: 'bg-amber-500/10',
    textClass: 'text-amber-600 dark:text-amber-400',
    borderClass: 'border-amber-500/20'
  },
  error: {
    icon: ExclamationTriangleIcon,
    color: 'red',
    bgClass: 'bg-red-500/10',
    textClass: 'text-red-600 dark:text-red-400',
    borderClass: 'border-red-500/20'
  },
  success: {
    icon: ShieldCheckIcon,
    color: 'green',
    bgClass: 'bg-green-500/10',
    textClass: 'text-green-600 dark:text-green-400',
    borderClass: 'border-green-500/20'
  }
};

const AuditLog = ({ entries = [], autoRefresh = false }: AuditLogProps) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [filteredEntries, setFilteredEntries] = useState(entries);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSeverity, setSelectedSeverity] = useState<string>('all');
  const [showFilters, setShowFilters] = useState(false);
  const [newEntryAlert, setNewEntryAlert] = useState(false);
  const [voiceEnabled, setVoiceEnabled] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Filter entries based on search and severity
  useEffect(() => {
    let filtered = entries;

    if (searchTerm) {
      filtered = filtered.filter(entry =>
        entry.action.toLowerCase().includes(searchTerm.toLowerCase()) ||
        entry.user.toLowerCase().includes(searchTerm.toLowerCase()) ||
        entry.description.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    if (selectedSeverity !== 'all') {
      filtered = filtered.filter(entry => entry.severity === selectedSeverity);
    }

    setFilteredEntries(filtered);
  }, [entries, searchTerm, selectedSeverity]);

  // Simulate new entry alert for demo
  useEffect(() => {
    if (entries.length > 0 && autoRefresh) {
      const timer = setTimeout(() => {
        setNewEntryAlert(true);
        setTimeout(() => setNewEntryAlert(false), 5000);
      }, 10000);
      return () => clearTimeout(timer);
    }
    return undefined;
  }, [entries.length, autoRefresh]);

  const handleVoiceUpdate = () => {
    if ('speechSynthesis' in window && entries.length > 0) {
      const recentEntry = entries[0];
      if (recentEntry) {
        const utterance = new SpeechSynthesisUtterance(
          `Latest audit entry: ${recentEntry.action} by ${recentEntry.user} at ${new Date(recentEntry.timestamp).toLocaleTimeString()}`
        );
        speechSynthesis.speak(utterance);
      }
    }
  };

  const handleRefresh = () => {
    setIsRefreshing(true);
    // Simulate refresh
    setTimeout(() => {
      setIsRefreshing(false);
    }, 1000);
  };

  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
    
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getActionIcon = (action: string) => {
    if (action.toLowerCase().includes('create')) return '+';
    if (action.toLowerCase().includes('update')) return '↻';
    if (action.toLowerCase().includes('delete')) return '×';
    if (action.toLowerCase().includes('login')) return '→';
    if (action.toLowerCase().includes('logout')) return '←';
    return '•';
  };

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-2xl p-6`}>
      {/* Header with Controls */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <ClockIcon className="w-5 h-5 text-purple-500" />
          <h3 className={`${t.text.primary} text-lg font-semibold`}>Activity Audit Log</h3>
          {autoRefresh && (
            <motion.div
              animate={{ opacity: [0.5, 1, 0.5] }}
              transition={{ duration: 2, repeat: Infinity }}
              className="flex items-center gap-1"
            >
              <div className="w-2 h-2 bg-green-500 rounded-full" />
              <span className="text-xs text-green-500">Live</span>
            </motion.div>
          )}
        </div>
        
        <div className="flex items-center gap-2">
          {/* Analytics Button */}
          <button
            className="p-2 hover:bg-white/5 rounded-lg transition-colors"
            aria-label="View analytics"
          >
            <ChartBarIcon className="w-5 h-5" />
          </button>
          
          {/* Filter Toggle */}
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 rounded-lg transition-all ${
              showFilters ? 'bg-purple-500/20 text-purple-500' : 'hover:bg-white/5'
            }`}
            aria-label="Toggle filters"
          >
            <FunnelIcon className="w-5 h-5" />
          </button>
          
          {/* Refresh */}
          <button
            onClick={handleRefresh}
            className="p-2 hover:bg-white/5 rounded-lg transition-colors"
            aria-label="Refresh log"
          >
            <ArrowPathIcon className={`w-5 h-5 ${isRefreshing ? 'animate-spin' : ''}`} />
          </button>
          
          {/* Voice Control */}
          <button
            onClick={() => {
              setVoiceEnabled(!voiceEnabled);
              if (!voiceEnabled) handleVoiceUpdate();
            }}
            className={`p-2 rounded-lg transition-all ${
              voiceEnabled 
                ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400' 
                : 'hover:bg-white/5'
            }`}
            aria-label="Toggle voice updates"
          >
            <MicrophoneIcon className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* New Entry Alert */}
      <AnimatePresence>
        {newEntryAlert && (
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            className="mb-4 p-3 bg-purple-500/10 border border-purple-500/20 rounded-xl flex items-center gap-3"
          >
            <BellIcon className="w-5 h-5 text-purple-500" />
            <p className="text-purple-600 dark:text-purple-400 text-sm">
              New activity detected - Admin updated order status
            </p>
            <button
              onClick={() => setNewEntryAlert(false)}
              className="ml-auto text-purple-500 hover:text-purple-600"
            >
              <span className="text-sm">Dismiss</span>
            </button>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Filters */}
      <AnimatePresence>
        {showFilters && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="mb-4 overflow-hidden"
          >
            <div className="flex flex-col sm:flex-row gap-3">
              {/* Search */}
              <div className="flex-1 relative">
                <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search activities..."
                  className={`w-full pl-10 pr-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
                />
              </div>
              
              {/* Severity Filter */}
              <select
                value={selectedSeverity}
                onChange={(e) => setSelectedSeverity(e.target.value)}
                className={`px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
              >
                <option value="all">All Severities</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="success">Success</option>
              </select>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Activity Summary Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        {(['info', 'warning', 'error', 'success'] as const).map(severity => {
          const count = entries.filter(e => e.severity === severity).length;
          const config = severityConfig[severity];
          return (
            <motion.div
              key={severity}
              whileHover={{ scale: 1.02 }}
              className={`${config.bgClass} ${config.borderClass} border rounded-lg p-3 text-center`}
            >
              <p className={`${config.textClass} text-2xl font-bold`}>{count}</p>
              <p className={`${t.text.muted} text-xs capitalize`}>{severity}</p>
            </motion.div>
          );
        })}
      </div>

      {/* Log Entries */}
      {filteredEntries.length === 0 ? (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="text-center py-12"
        >
          <ClockIcon className={`w-16 h-16 mx-auto ${t.text.muted} mb-4`} />
          <p className={t.text.muted}>
            {searchTerm || selectedSeverity !== 'all' 
              ? 'No activities match your filters' 
              : 'No audit log entries found'}
          </p>
        </motion.div>
      ) : (
        <div className="space-y-2">
          <AnimatePresence>
            {filteredEntries.map((entry, idx) => {
              const severity = entry.severity || 'info';
              const config = severityConfig[severity];
              
              return (
                <motion.div
                  key={entry.id || idx}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: 20 }}
                  transition={{ delay: idx * 0.05 }}
                  className={`relative group`}
                >
                  <div className={`flex items-start gap-3 p-4 ${t.glass.card} ${t.glass.border} rounded-lg hover:shadow-md transition-all`}>
                    {/* Timeline Connector */}
                    {idx < filteredEntries.length - 1 && (
                      <div className="absolute left-7 top-12 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700" />
                    )}
                    
                    {/* Icon */}
                    <div className={`relative flex-shrink-0 w-10 h-10 ${config.bgClass} ${config.borderClass} border rounded-full flex items-center justify-center`}>
                      <span className={`${config.textClass} text-lg font-bold`}>
                        {getActionIcon(entry.action)}
                      </span>
                    </div>
                    
                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1">
                          <p className={`${t.text.primary} font-medium`}>
                            {entry.action}
                          </p>
                          <p className={`${t.text.muted} text-sm mt-0.5`}>
                            {entry.description}
                          </p>
                          <div className="flex items-center gap-4 mt-2 text-xs">
                            <div className="flex items-center gap-1">
                              <UserIcon className="w-3 h-3" />
                              <span className={t.text.muted}>{entry.user}</span>
                              {entry.user_role && (
                                <span className={`px-1.5 py-0.5 ${config.bgClass} ${config.textClass} rounded text-xs`}>
                                  {entry.user_role}
                                </span>
                              )}
                            </div>
                            {entry.ip_address && (
                              <span className={`${t.text.muted} text-xs`}>
                                IP: {entry.ip_address}
                              </span>
                            )}
                          </div>
                        </div>
                        
                        <div className="text-right flex-shrink-0">
                          <p className={`${t.text.muted} text-xs`}>
                            {formatTimestamp(entry.timestamp)}
                          </p>
                        </div>
                      </div>
                      
                      {/* Metadata (shown on hover) */}
                      {entry.metadata && Object.keys(entry.metadata).length > 0 && (
                        <motion.div
                          initial={{ height: 0, opacity: 0 }}
                          animate={{ height: 'auto', opacity: 1 }}
                          className="mt-2 pt-2 border-t border-white/10 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                          <div className="flex items-center gap-2 mb-1">
                            <SparklesIcon className="w-3 h-3 text-purple-500" />
                            <span className="text-xs text-purple-600 dark:text-purple-400">Additional Details</span>
                          </div>
                          <div className="grid grid-cols-2 gap-2 text-xs">
                            {Object.entries(entry.metadata).slice(0, 4).map(([key, value]) => (
                              <div key={key}>
                                <span className={t.text.muted}>{key}:</span>{' '}
                                <span className={t.text.secondary}>{String(value)}</span>
                              </div>
                            ))}
                          </div>
                        </motion.div>
                      )}
                    </div>
                  </div>
                </motion.div>
              );
            })}
          </AnimatePresence>
        </div>
      )}
      
      {/* Load More / Pagination */}
      {filteredEntries.length > 0 && (
        <motion.button
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
          className="w-full mt-4 px-4 py-2 bg-white/5 hover:bg-white/10 rounded-lg transition-colors text-sm"
        >
          Load More Activities
        </motion.button>
      )}
    </div>
  );
};

export default AuditLog;
