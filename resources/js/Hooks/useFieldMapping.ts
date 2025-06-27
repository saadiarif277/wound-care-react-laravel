import { useState, useCallback, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { 
  mapFields, 
  validateFieldMapping, 
  calculateFieldCompleteness,
  applyManufacturerFieldMapping,
  type FieldMappingConfig,
  type ValidationResult,
  type CompletenessResult
} from '../utils/fieldMapping';

interface UseFieldMappingOptions {
  episodeId?: number;
  manufacturer?: string;
  autoFetch?: boolean;
}

interface FieldMappingState {
  isLoading: boolean;
  error: string | null;
  mappedData: Record<string, any> | null;
  validation: ValidationResult | null;
  completeness: CompletenessResult | null;
  manufacturerConfig: any | null;
}

interface UseFieldMappingReturn extends FieldMappingState {
  mapEpisodeData: (episodeId: number, manufacturer: string) => Promise<void>;
  validateData: (data: Record<string, any>, requiredFields: string[]) => ValidationResult;
  calculateCompleteness: (data: Record<string, any>, fields: string[]) => CompletenessResult;
  applyCustomMapping: (data: Record<string, any>, mapping: FieldMappingConfig[]) => Record<string, any>;
  refreshMapping: () => Promise<void>;
}

export function useFieldMapping(options: UseFieldMappingOptions = {}): UseFieldMappingReturn {
  const [state, setState] = useState<FieldMappingState>({
    isLoading: false,
    error: null,
    mappedData: null,
    validation: null,
    completeness: null,
    manufacturerConfig: null,
  });

  // Map episode data using the backend unified service
  const mapEpisodeData = useCallback(async (episodeId: number, manufacturer: string) => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch(`/api/field-mapping/episode/${episodeId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ manufacturer }),
      });

      if (!response.ok) {
        throw new Error('Failed to map episode data');
      }

      const result = await response.json();

      setState({
        isLoading: false,
        error: null,
        mappedData: result.data,
        validation: result.validation,
        completeness: result.completeness,
        manufacturerConfig: result.manufacturer,
      });
    } catch (error) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error instanceof Error ? error.message : 'An error occurred',
      }));
    }
  }, []);

  // Validate data on the frontend
  const validateData = useCallback((data: Record<string, any>, requiredFields: string[]): ValidationResult => {
    const result = validateFieldMapping(data, requiredFields);
    setState(prev => ({ ...prev, validation: result }));
    return result;
  }, []);

  // Calculate completeness on the frontend
  const calculateCompleteness = useCallback((data: Record<string, any>, fields: string[]): CompletenessResult => {
    const result = calculateFieldCompleteness(data, fields);
    setState(prev => ({ ...prev, completeness: result }));
    return result;
  }, []);

  // Apply custom mapping on the frontend
  const applyCustomMapping = useCallback((data: Record<string, any>, mapping: FieldMappingConfig[]): Record<string, any> => {
    return mapFields(data, mapping);
  }, []);

  // Refresh current mapping
  const refreshMapping = useCallback(async () => {
    if (options.episodeId && options.manufacturer) {
      await mapEpisodeData(options.episodeId, options.manufacturer);
    }
  }, [options.episodeId, options.manufacturer, mapEpisodeData]);

  // Auto-fetch on mount if options provided
  useMemo(() => {
    if (options.autoFetch && options.episodeId && options.manufacturer) {
      mapEpisodeData(options.episodeId, options.manufacturer);
    }
  }, [options.autoFetch, options.episodeId, options.manufacturer]);

  return {
    ...state,
    mapEpisodeData,
    validateData,
    calculateCompleteness,
    applyCustomMapping,
    refreshMapping,
  };
}

/**
 * Hook for managing DocuSeal submissions with unified field mapping
 */
export function useDocuSealSubmission(episodeId: number, manufacturer: string) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submissionStatus, setSubmissionStatus] = useState<string | null>(null);
  const fieldMapping = useFieldMapping({ episodeId, manufacturer, autoFetch: true });

  const createOrUpdateSubmission = useCallback(async (additionalData?: Record<string, any>) => {
    setIsSubmitting(true);
    setSubmissionStatus('creating');

    try {
      const response = await fetch(`/api/docuseal/submission/create`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          episode_id: episodeId,
          manufacturer,
          additional_data: additionalData,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to create DocuSeal submission');
      }

      const result = await response.json();
      setSubmissionStatus('created');
      
      // Refresh field mapping to get updated data
      await fieldMapping.refreshMapping();

      return result;
    } catch (error) {
      setSubmissionStatus('error');
      throw error;
    } finally {
      setIsSubmitting(false);
    }
  }, [episodeId, manufacturer, fieldMapping]);

  const sendForSigning = useCallback(async (submissionId: string, signers: Array<{ email: string; name?: string; role?: string }>) => {
    setIsSubmitting(true);
    setSubmissionStatus('sending');

    try {
      const response = await fetch(`/api/docuseal/submission/${submissionId}/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ signers }),
      });

      if (!response.ok) {
        throw new Error('Failed to send for signing');
      }

      const result = await response.json();
      setSubmissionStatus('sent');
      return result;
    } catch (error) {
      setSubmissionStatus('error');
      throw error;
    } finally {
      setIsSubmitting(false);
    }
  }, []);

  return {
    ...fieldMapping,
    isSubmitting,
    submissionStatus,
    createOrUpdateSubmission,
    sendForSigning,
  };
}

/**
 * Hook for field mapping analytics
 */
export function useFieldMappingAnalytics(manufacturer?: string) {
  const [analytics, setAnalytics] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(false);

  const fetchAnalytics = useCallback(async (dateRange?: { start: string; end: string }) => {
    setIsLoading(true);

    try {
      const params = new URLSearchParams();
      if (manufacturer) params.append('manufacturer', manufacturer);
      if (dateRange) {
        params.append('start_date', dateRange.start);
        params.append('end_date', dateRange.end);
      }

      const response = await fetch(`/api/field-mapping/analytics?${params}`, {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch analytics');
      }

      const data = await response.json();
      setAnalytics(data);
    } catch (error) {
      console.error('Failed to fetch field mapping analytics:', error);
    } finally {
      setIsLoading(false);
    }
  }, [manufacturer]);

  return {
    analytics,
    isLoading,
    fetchAnalytics,
  };
}