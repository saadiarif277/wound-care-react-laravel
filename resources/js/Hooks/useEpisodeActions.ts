import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'react-hot-toast';

interface UseEpisodeActionsOptions {
  onSuccess?: (action: string, episodeId: string) => void;
  onError?: (error: any, action: string, episodeId: string) => void;
}

export const useEpisodeActions = (options: UseEpisodeActionsOptions = {}) => {
  const [loading, setLoading] = useState<Record<string, boolean>>({});

  /**
   * Send IVR verification request
   */
  const sendIVR = useCallback(async (episodeId: string) => {
    const loadingKey = `sendIVR-${episodeId}`;
    setLoading(prev => ({ ...prev, [loadingKey]: true }));

    try {
      await router.post(`/api/episodes/${episodeId}/send-ivr`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('IVR verification sent successfully');
          options.onSuccess?.('sendIVR', episodeId);
        },
        onError: (errors) => {
          toast.error('Failed to send IVR verification');
          options.onError?.(errors, 'sendIVR', episodeId);
        },
        onFinish: () => {
          setLoading(prev => ({ ...prev, [loadingKey]: false }));
        }
      });
    } catch (error) {
      setLoading(prev => ({ ...prev, [loadingKey]: false }));
      toast.error('An unexpected error occurred');
      options.onError?.(error, 'sendIVR', episodeId);
    }
  }, [options]);

  /**
   * Generate episode documents
   */
  const generateDocuments = useCallback(async (episodeId: string) => {
    const loadingKey = `generateDocs-${episodeId}`;
    setLoading(prev => ({ ...prev, [loadingKey]: true }));

    try {
      await router.post(`/api/episodes/${episodeId}/generate-documents`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Documents generated successfully');
          options.onSuccess?.('generateDocuments', episodeId);
        },
        onError: (errors) => {
          toast.error('Failed to generate documents');
          options.onError?.(errors, 'generateDocuments', episodeId);
        },
        onFinish: () => {
          setLoading(prev => ({ ...prev, [loadingKey]: false }));
        }
      });
    } catch (error) {
      setLoading(prev => ({ ...prev, [loadingKey]: false }));
      toast.error('An unexpected error occurred');
      options.onError?.(error, 'generateDocuments', episodeId);
    }
  }, [options]);

  /**
   * Mark episode as resolved
   */
  const resolveEpisode = useCallback(async (episodeId: string) => {
    const loadingKey = `resolve-${episodeId}`;
    setLoading(prev => ({ ...prev, [loadingKey]: true }));

    try {
      await router.post(`/api/episodes/${episodeId}/resolve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Episode resolved successfully');
          options.onSuccess?.('resolveEpisode', episodeId);
        },
        onError: (errors) => {
          toast.error('Failed to resolve episode');
          options.onError?.(errors, 'resolveEpisode', episodeId);
        },
        onFinish: () => {
          setLoading(prev => ({ ...prev, [loadingKey]: false }));
        }
      });
    } catch (error) {
      setLoading(prev => ({ ...prev, [loadingKey]: false }));
      toast.error('An unexpected error occurred');
      options.onError?.(error, 'resolveEpisode', episodeId);
    }
  }, [options]);

  /**
   * Navigate to episode details
   */
  const viewDetails = useCallback((episodeId: string) => {
    router.visit(`/admin/episodes/${episodeId}`);
  }, []);

  /**
   * Navigate to contact page
   */
  const contactPatient = useCallback((episodeId: string) => {
    router.visit(`/episodes/${episodeId}/contact`);
  }, []);

  /**
   * Create a new order for the episode
   */
  const createOrder = useCallback((episodeId: string) => {
    router.visit(`/episodes/${episodeId}/orders/create`);
  }, []);

  /**
   * Export episode data
   */
  const exportEpisode = useCallback(async (episodeId: string, format: 'pdf' | 'csv' = 'pdf') => {
    const loadingKey = `export-${episodeId}`;
    setLoading(prev => ({ ...prev, [loadingKey]: true }));

    try {
      // For file downloads, we need to handle it differently
      window.location.href = `/api/episodes/${episodeId}/export?format=${format}`;
      
      setTimeout(() => {
        setLoading(prev => ({ ...prev, [loadingKey]: false }));
        toast.success(`Episode exported as ${format.toUpperCase()}`);
        options.onSuccess?.('exportEpisode', episodeId);
      }, 1000);
    } catch (error) {
      setLoading(prev => ({ ...prev, [loadingKey]: false }));
      toast.error('Failed to export episode');
      options.onError?.(error, 'exportEpisode', episodeId);
    }
  }, [options]);

  /**
   * Batch update episodes
   */
  const batchUpdate = useCallback(async (episodeIds: string[], updates: Record<string, any>) => {
    const loadingKey = 'batchUpdate';
    setLoading(prev => ({ ...prev, [loadingKey]: true }));

    try {
      await router.post('/api/episodes/batch-update', {
        episode_ids: episodeIds,
        updates
      }, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`${episodeIds.length} episodes updated successfully`);
          options.onSuccess?.('batchUpdate', episodeIds.join(','));
        },
        onError: (errors) => {
          toast.error('Failed to update episodes');
          options.onError?.(errors, 'batchUpdate', episodeIds.join(','));
        },
        onFinish: () => {
          setLoading(prev => ({ ...prev, [loadingKey]: false }));
        }
      });
    } catch (error) {
      setLoading(prev => ({ ...prev, [loadingKey]: false }));
      toast.error('An unexpected error occurred');
      options.onError?.(error, 'batchUpdate', episodeIds.join(','));
    }
  }, [options]);

  /**
   * Check if a specific action is loading
   */
  const isLoading = useCallback((action: string, episodeId?: string) => {
    const key = episodeId ? `${action}-${episodeId}` : action;
    return loading[key] || false;
  }, [loading]);

  return {
    sendIVR,
    generateDocuments,
    resolveEpisode,
    viewDetails,
    contactPatient,
    createOrder,
    exportEpisode,
    batchUpdate,
    isLoading,
    loading
  };
};