import { format, differenceInDays, isAfter, isBefore, addDays } from 'date-fns';

export interface Episode {
  id: string;
  status: string;
  ivr_status: string;
  expiration_date?: string;
  verification_date?: string;
  created_at: string;
  action_required?: boolean;
}

/**
 * Calculate the priority level of an episode based on various factors
 */
export const getEpisodePriority = (episode: Episode): 'critical' | 'high' | 'medium' | 'low' => {
  if (episode.action_required) return 'critical';
  
  if (episode.ivr_status === 'expired' || episode.status === 'expired') {
    return 'critical';
  }

  if (episode.expiration_date) {
    const daysUntilExpiry = differenceInDays(new Date(episode.expiration_date), new Date());
    
    if (daysUntilExpiry < 0) return 'critical'; // Already expired
    if (daysUntilExpiry <= 3) return 'high';
    if (daysUntilExpiry <= 7) return 'medium';
  }

  return 'low';
};

/**
 * Get a human-readable status label for an episode
 */
export const getEpisodeStatusLabel = (episode: Episode): string => {
  if (episode.action_required) return 'Action Required';
  
  if (episode.ivr_status === 'expired') return 'IVR Expired';
  if (episode.status === 'expired') return 'Episode Expired';
  
  if (episode.expiration_date) {
    const daysUntilExpiry = differenceInDays(new Date(episode.expiration_date), new Date());
    
    if (daysUntilExpiry < 0) return 'Expired';
    if (daysUntilExpiry === 0) return 'Expires Today';
    if (daysUntilExpiry === 1) return 'Expires Tomorrow';
    if (daysUntilExpiry <= 7) return `Expires in ${daysUntilExpiry} days`;
  }

  switch (episode.status) {
    case 'active':
      return 'Active';
    case 'pending':
      return 'Pending';
    case 'completed':
      return 'Completed';
    case 'cancelled':
      return 'Cancelled';
    default:
      return 'Unknown';
  }
};

/**
 * Calculate the percentage of episode lifecycle completed
 */
export const getEpisodeProgress = (episode: Episode): number => {
  if (!episode.expiration_date) return 0;

  const startDate = new Date(episode.created_at);
  const endDate = new Date(episode.expiration_date);
  const currentDate = new Date();

  if (isAfter(currentDate, endDate)) return 100;
  if (isBefore(currentDate, startDate)) return 0;

  const totalDays = differenceInDays(endDate, startDate);
  const elapsedDays = differenceInDays(currentDate, startDate);

  return Math.round((elapsedDays / totalDays) * 100);
};

/**
 * Group episodes by their status for dashboard display
 */
export const groupEpisodesByStatus = (episodes: Episode[]) => {
  const groups = {
    actionRequired: [] as Episode[],
    expiringSoon: [] as Episode[],
    active: [] as Episode[],
    expired: [] as Episode[],
    completed: [] as Episode[],
    other: [] as Episode[]
  };

  episodes.forEach(episode => {
    if (episode.action_required) {
      groups.actionRequired.push(episode);
    } else if (episode.ivr_status === 'expired' || episode.status === 'expired') {
      groups.expired.push(episode);
    } else if (episode.expiration_date) {
      const daysUntilExpiry = differenceInDays(new Date(episode.expiration_date), new Date());
      if (daysUntilExpiry > 0 && daysUntilExpiry <= 7) {
        groups.expiringSoon.push(episode);
      } else if (episode.status === 'active') {
        groups.active.push(episode);
      } else if (episode.status === 'completed') {
        groups.completed.push(episode);
      } else {
        groups.other.push(episode);
      }
    } else if (episode.status === 'active') {
      groups.active.push(episode);
    } else if (episode.status === 'completed') {
      groups.completed.push(episode);
    } else {
      groups.other.push(episode);
    }
  });

  return groups;
};

/**
 * Sort episodes by priority and date
 */
export const sortEpisodesByPriority = (episodes: Episode[]): Episode[] => {
  return [...episodes].sort((a, b) => {
    // First sort by priority
    const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
    const aPriority = priorityOrder[getEpisodePriority(a)];
    const bPriority = priorityOrder[getEpisodePriority(b)];
    
    if (aPriority !== bPriority) {
      return aPriority - bPriority;
    }

    // Then sort by expiration date (soonest first)
    if (a.expiration_date && b.expiration_date) {
      return new Date(a.expiration_date).getTime() - new Date(b.expiration_date).getTime();
    }

    // Finally sort by creation date (newest first)
    return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
  });
};

/**
 * Filter episodes based on search query
 */
export const filterEpisodes = (
  episodes: Episode[], 
  query: string, 
  filters: {
    status?: string;
    ivr_status?: string;
    manufacturer?: string;
    priority?: string;
  } = {}
): Episode[] => {
  let filtered = [...episodes];

  // Apply text search
  if (query) {
    const searchLower = query.toLowerCase();
    filtered = filtered.filter(episode => {
      // Search in various fields (extend as needed)
      const searchableFields = [
        episode.id,
        episode.status,
        episode.ivr_status,
        // Add more searchable fields as needed
      ].filter(Boolean);

      return searchableFields.some(field => 
        field.toString().toLowerCase().includes(searchLower)
      );
    });
  }

  // Apply filters
  if (filters.status) {
    filtered = filtered.filter(e => e.status === filters.status);
  }

  if (filters.ivr_status) {
    filtered = filtered.filter(e => e.ivr_status === filters.ivr_status);
  }

  if (filters.priority) {
    filtered = filtered.filter(e => getEpisodePriority(e) === filters.priority);
  }

  return filtered;
};

/**
 * Get suggested actions for an episode
 */
export const getEpisodeSuggestedActions = (episode: Episode): string[] => {
  const actions: string[] = [];

  if (episode.action_required) {
    actions.push('Review and resolve pending issues');
  }

  if (episode.ivr_status === 'expired') {
    actions.push('Send new IVR verification to patient');
  }

  if (episode.expiration_date) {
    const daysUntilExpiry = differenceInDays(new Date(episode.expiration_date), new Date());
    
    if (daysUntilExpiry <= 7 && daysUntilExpiry > 0) {
      actions.push('Schedule IVR renewal');
      actions.push('Contact patient for re-verification');
    }
  }

  if (episode.status === 'pending') {
    actions.push('Complete episode setup');
  }

  return actions;
};

/**
 * Format episode dates for display
 */
export const formatEpisodeDate = (date: string | Date, includeTime = false): string => {
  if (!date) return 'N/A';
  
  const dateObj = typeof date === 'string' ? new Date(date) : date;
  
  if (includeTime) {
    return format(dateObj, 'MMM d, yyyy h:mm a');
  }
  
  return format(dateObj, 'MMM d, yyyy');
};

/**
 * Calculate episode statistics
 */
export const calculateEpisodeStats = (episodes: Episode[]) => {
  const total = episodes.length;
  const groups = groupEpisodesByStatus(episodes);
  
  return {
    total,
    actionRequired: groups.actionRequired.length,
    expiringSoon: groups.expiringSoon.length,
    active: groups.active.length,
    expired: groups.expired.length,
    completed: groups.completed.length,
    actionRequiredPercentage: total > 0 ? (groups.actionRequired.length / total) * 100 : 0,
    expiringPercentage: total > 0 ? (groups.expiringSoon.length / total) * 100 : 0,
  };
};