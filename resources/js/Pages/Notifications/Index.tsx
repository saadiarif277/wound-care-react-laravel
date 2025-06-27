import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import {
  Bell,
  Check,
  CheckCircle,
  AlertTriangle,
  Info,
  Filter,
  Search,
  Trash2,
  Eye,
  Clock,
  Settings,
  X,
  Archive,
  MoreVertical,
} from 'lucide-react';

interface Notification {
  id: string;
  type: 'order_update' | 'ivr_reminder' | 'system' | 'alert';
  title: string;
  message: string;
  read: boolean;
  created_at: string;
  action_url?: string;
  icon: string;
  priority: 'low' | 'medium' | 'high';
}

interface Props {
  notifications: Notification[];
  unreadCount: number;
  filter: string;
}

export default function NotificationsIndex({ notifications, unreadCount, filter: initialFilter }: Props) {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [selectedNotifications, setSelectedNotifications] = useState<string[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [currentFilter, setCurrentFilter] = useState(initialFilter);

  const handleMarkAsRead = (notificationId: string) => {
    router.post(route('notifications.mark-read', notificationId), {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Optionally show success message
      }
    });
  };

  const handleMarkAllAsRead = () => {
    router.post(route('notifications.mark-all-read'), {}, {
      preserveScroll: true,
    });
  };

  const handleDelete = (notificationId: string) => {
    router.delete(route('notifications.destroy', notificationId), {
      preserveScroll: true,
    });
  };

  const handleBulkDelete = () => {
    // In production, implement bulk delete
    setSelectedNotifications([]);
  };

  const handleFilterChange = (newFilter: string) => {
    setCurrentFilter(newFilter);
    router.get(route('notifications'), { filter: newFilter }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const getIcon = (notification: Notification) => {
    switch (notification.icon) {
      case 'check-circle':
        return <CheckCircle className="w-5 h-5 text-green-500" />;
      case 'alert-triangle':
        return <AlertTriangle className="w-5 h-5 text-yellow-500" />;
      case 'info':
        return <Info className="w-5 h-5 text-blue-500" />;
      default:
        return <Bell className="w-5 h-5 text-gray-500" />;
    }
  };

  const filteredNotifications = notifications.filter(n => 
    searchQuery === '' || 
    n.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    n.message.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);
    
    if (diffInHours < 1) {
      const diffInMinutes = Math.floor(diffInHours * 60);
      return `${diffInMinutes}m ago`;
    } else if (diffInHours < 24) {
      return `${Math.floor(diffInHours)}h ago`;
    } else if (diffInHours < 48) {
      return 'Yesterday';
    } else {
      return date.toLocaleDateString();
    }
  };

  return (
      <>
        <MainLayout>
          <Head title="Notifications | MSC Healthcare" />

      <div className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'} p-6`}>
        {/* Header */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mb-6`}>
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center space-x-3 mb-4 lg:mb-0">
              <Bell className="w-6 h-6 text-blue-500" />
              <div>
                <h1 className={`text-2xl font-bold ${t.text.primary}`}>Notifications</h1>
                <p className={`${t.text.secondary} mt-1`}>
                  {unreadCount > 0 ? `${unreadCount} unread` : 'All caught up!'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              {unreadCount > 0 && (
                <button
                  onClick={handleMarkAllAsRead}
                  className={`${t.button.secondary} px-4 py-2 flex items-center space-x-2`}
                >
                  <Check className="w-4 h-4" />
                  <span>Mark All Read</span>
                </button>
              )}

              <button
                              onClick={() => router.visit(route('notifications.preferences'))}
                              className={`${t.button.ghost} p-2`}
                              aria-label="Preferences"
                            >
                              <Settings className="w-4 h-4" />
                            </button>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6`}>
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div className="flex items-center gap-2">
              <button
                onClick={() => handleFilterChange('all')}
                className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                  currentFilter === 'all' 
                    ? `${t.button.primary}` 
                    : `${t.button.ghost}`
                }`}
              >
                All
              </button>
              <button
                onClick={() => handleFilterChange('unread')}
                className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                  currentFilter === 'unread' 
                    ? `${t.button.primary}` 
                    : `${t.button.ghost}`
                }`}
              >
                Unread
              </button>
              <button
                onClick={() => handleFilterChange('read')}
                className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                  currentFilter === 'read' 
                    ? `${t.button.primary}` 
                    : `${t.button.ghost}`
                }`}
              >
                Read
              </button>
            </div>

            <div className="relative flex-1 max-w-md">
              <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
              <input
                type="text"
                placeholder="Search notifications..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-10 pr-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
              />
            </div>
          </div>

          {selectedNotifications.length > 0 && (
            <div className="flex items-center justify-between mt-4 p-3 bg-blue-500/10 rounded-lg">
              <span className={`text-sm ${t.text.primary}`}>
                {selectedNotifications.length} selected
              </span>
              <div className="flex items-center gap-2">
                <button
                  onClick={handleBulkDelete}
                  className={`${t.button.danger} px-3 py-1.5 text-sm`}
                >
                  Delete Selected
                </button>
                <button
                  onClick={() => setSelectedNotifications([])}
                  className={`${t.button.ghost} px-3 py-1.5 text-sm`}
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Notifications List */}
        <div className="space-y-3">
          {filteredNotifications.map((notification) => (
            <div
              key={notification.id}
              className={`${t.glass.card} ${t.glass.border} p-4 hover:shadow-lg transition-all ${
                !notification.read ? 'border-l-4 border-l-blue-500' : ''
              }`}
            >
              <div className="flex items-start justify-between">
                <div className="flex items-start space-x-3 flex-1">
                  <input
                    type="checkbox"
                    checked={selectedNotifications.includes(notification.id)}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedNotifications([...selectedNotifications, notification.id]);
                      } else {
                        setSelectedNotifications(selectedNotifications.filter(id => id !== notification.id));
                      }
                    }}
                    className="mt-1 rounded"
                  />

                  <div className="p-2 rounded-lg bg-gray-100 dark:bg-gray-800">
                    {getIcon(notification)}
                  </div>

                  <div className="flex-1">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h3 className={`font-semibold ${t.text.primary} ${!notification.read ? 'font-bold' : ''}`}>
                          {notification.title}
                        </h3>
                        <p className={`${t.text.secondary} text-sm mt-1`}>
                          {notification.message}
                        </p>
                        <div className="flex items-center gap-4 mt-2">
                          <span className={`text-xs ${t.text.muted} flex items-center`}>
                            <Clock className="w-3 h-3 mr-1" />
                            {formatTime(notification.created_at)}
                          </span>
                          {notification.priority === 'high' && (
                            <span className="text-xs text-red-500 font-medium">High Priority</span>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 mt-3">
                      {notification.action_url && (
                        <button
                          onClick={() => router.visit((notification.action_url)!)}
                          className={`${t.button.secondary} px-3 py-1.5 text-sm`}
                        >
                          View Details
                        </button>
                      )}
                      {!notification.read && (
                        <button
                          onClick={() => handleMarkAsRead(notification.id)}
                          className={`${t.button.ghost} px-3 py-1.5 text-sm flex items-center space-x-1`}
                        >
                          <Eye className="w-3 h-3" />
                          <span>Mark as Read</span>
                        </button>
                      )}
                    </div>
                  </div>
                </div>

                  <button className={`${t.button.ghost} p-1`} aria-label="More options">
                    <MoreVertical className="w-4 h-4" />
                  </button>
                  {/* Dropdown menu would go here */}
                </div>
            </div>
          ))}
        </div>

          {filteredNotifications.length === 0 && (
          <div className={`${t.glass.card} ${t.glass.border} p-12 text-center`}>
            <Bell className={`w-12 h-12 ${t.text.muted} mx-auto mb-4`} />
            <p className={`${t.text.secondary}`}>
              {searchQuery ? 'No notifications found matching your search' : 'No notifications to display'}
            </p>
          </div>
        )}
      </div>
    </MainLayout>
    </>
  );
}