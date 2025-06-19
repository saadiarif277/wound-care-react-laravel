import React from 'react';
import { 
  FileText, 
  Send, 
  CheckCircle, 
  Clock,
  Package,
  AlertCircle,
  Calendar
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { themes } from '@/theme/glass-theme';
import { formatDateTime } from '@/utils/formatters';

interface TimelineEvent {
  id: string;
  type: 'ivr_sent' | 'ivr_completed' | 'order_created' | 'order_shipped' | 'episode_created' | 'status_changed';
  date: string;
  description: string;
  metadata?: any;
}

interface EpisodeTimelineProps {
  episode: {
    id: string;
    created_at: string;
    verification_date?: string;
    last_order_date?: string;
    expiration_date?: string;
    recent_activities?: Array<{
      type: string;
      date: string;
      description: string;
    }>;
  };
  theme: 'dark' | 'light';
}

const EpisodeTimeline: React.FC<EpisodeTimelineProps> = ({ episode, theme }) => {
  const t = themes[theme];

  // Build timeline events from episode data
  const buildTimelineEvents = (): TimelineEvent[] => {
    const events: TimelineEvent[] = [];

    // Episode created
    events.push({
      id: 'created',
      type: 'episode_created',
      date: episode.created_at,
      description: 'Episode created'
    });

    // Verification date
    if (episode.verification_date) {
      events.push({
        id: 'verified',
        type: 'ivr_completed',
        date: episode.verification_date,
        description: 'IVR verification completed'
      });
    }

    // Last order
    if (episode.last_order_date) {
      events.push({
        id: 'last-order',
        type: 'order_created',
        date: episode.last_order_date,
        description: 'Most recent order placed'
      });
    }

    // Recent activities
    if (episode.recent_activities) {
      episode.recent_activities.forEach((activity, index) => {
        events.push({
          id: `activity-${index}`,
          type: activity.type as any,
          date: activity.date,
          description: activity.description
        });
      });
    }

    // Sort events by date (most recent first)
    return events.sort((a, b) => 
      new Date(b.date).getTime() - new Date(a.date).getTime()
    );
  };

  const events = buildTimelineEvents();

  // Icon mapping for event types
  const getEventIcon = (type: string) => {
    const iconMap: Record<string, any> = {
      'episode_created': Calendar,
      'ivr_sent': Send,
      'ivr_completed': CheckCircle,
      'order_created': Package,
      'order_shipped': Send,
      'status_changed': AlertCircle,
    };
    return iconMap[type] || Clock;
  };

  // Color mapping for event types
  const getEventColor = (type: string) => {
    const colorMap: Record<string, string> = {
      'episode_created': theme === 'dark' ? 'bg-blue-500' : 'bg-blue-600',
      'ivr_sent': theme === 'dark' ? 'bg-yellow-500' : 'bg-yellow-600',
      'ivr_completed': theme === 'dark' ? 'bg-green-500' : 'bg-green-600',
      'order_created': theme === 'dark' ? 'bg-purple-500' : 'bg-purple-600',
      'order_shipped': theme === 'dark' ? 'bg-indigo-500' : 'bg-indigo-600',
      'status_changed': theme === 'dark' ? 'bg-orange-500' : 'bg-orange-600',
    };
    return colorMap[type] || (theme === 'dark' ? 'bg-gray-500' : 'bg-gray-600');
  };

  if (events.length === 0) {
    return (
      <div className={cn("text-center py-4", t.text.secondary)}>
        <Clock className="w-8 h-8 mx-auto mb-2 opacity-50" />
        <p className="text-sm">No timeline events yet</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
        Episode Timeline
      </h4>
      
      <div className="relative">
        {/* Timeline line */}
        <div className={cn(
          "absolute left-4 top-0 bottom-0 w-0.5",
          theme === 'dark' ? 'bg-white/10' : 'bg-gray-200'
        )} />

        {/* Timeline events */}
        <div className="space-y-3">
          {events.map((event, index) => {
            const Icon = getEventIcon(event.type);
            const isLast = index === events.length - 1;

            return (
              <div key={event.id} className="relative flex items-start">
                {/* Event dot and icon */}
                <div className={cn(
                  "relative z-10 w-8 h-8 rounded-full flex items-center justify-center",
                  getEventColor(event.type),
                  "shadow-lg"
                )}>
                  <Icon className="w-4 h-4 text-white" />
                </div>

                {/* Event content */}
                <div className="ml-4 flex-1">
                  <div className={cn(
                    "p-3 rounded-lg",
                    theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
                  )}>
                    <p className={cn("text-sm font-medium", t.text.primary)}>
                      {event.description}
                    </p>
                    <p className={cn("text-xs mt-1", t.text.secondary)}>
                      {formatDateTime(event.date)}
                    </p>
                  </div>
                </div>

                {/* Hide line for last item */}
                {isLast && (
                  <div className={cn(
                    "absolute left-4 top-8 bottom-0 w-0.5 bg-gradient-to-b",
                    theme === 'dark' 
                      ? 'from-white/10 to-transparent' 
                      : 'from-gray-200 to-transparent'
                  )} />
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Expiration warning */}
      {episode.expiration_date && (
        <div className={cn(
          "mt-4 p-3 rounded-lg flex items-start space-x-2",
          theme === 'dark' ? 'bg-yellow-500/20' : 'bg-yellow-50'
        )}>
          <AlertCircle className={cn(
            "w-4 h-4 mt-0.5",
            theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
          )} />
          <div>
            <p className={cn(
              "text-sm font-medium",
              theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'
            )}>
              IVR Expires: {formatDateTime(episode.expiration_date)}
            </p>
            {new Date(episode.expiration_date) < new Date() && (
              <p className={cn(
                "text-xs mt-1",
                theme === 'dark' ? 'text-yellow-300' : 'text-yellow-700'
              )}>
                This episode requires re-verification
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default EpisodeTimeline;