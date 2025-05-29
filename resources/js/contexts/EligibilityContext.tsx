// src/contexts/EligibilityContext.tsx
import React, { createContext, useContext, ReactNode, useState, useEffect } from 'react';
import { api } from '@/lib/api';

interface Verification {
  id: string;
  customer_id: string;
  request_date: string;
  patient_info: {
    first_name: string;
    last_name: string;
  };
  insurance_info: {
    payer_id: string;
  };
  status: 'active' | 'inactive' | 'pending' | 'error';
}

interface VerificationResult {
  id: string;
  status: 'active' | 'inactive' | 'pending' | 'error';
  eligibility_status?: string;
  coverage_details?: any;
  error_message?: string;
}

interface EligibilityContextType {
  verifications: Verification[];
  results: Record<string, VerificationResult>;
  loading: boolean;
  error: string | null;
  refreshVerifications: () => Promise<void>;
}

const EligibilityContext = createContext<EligibilityContextType | undefined>(undefined);

export const useEligibility = () => {
  const context = useContext(EligibilityContext);
  if (context === undefined) {
    throw new Error('useEligibility must be used within an EligibilityProvider');
  }
  return context;
};

export const EligibilityProvider: React.FC<{children: ReactNode}> = ({ children }) => {
  const [verifications, setVerifications] = useState<Verification[]>([]);
  const [results, setResults] = useState<Record<string, VerificationResult>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchVerifications = async () => {
    setLoading(true);
    setError(null);

    try {
      // Fetch eligibility history from API
      const history = await api.eligibility.getHistory();

      // Transform the data to match our interface
      const transformedVerifications: Verification[] = history.map((item: any) => ({
        id: item.id,
        customer_id: item.customer_id || 'unknown',
        request_date: item.created_at || item.request_date,
        patient_info: {
          first_name: item.patient_first_name || item.patient_info?.first_name || 'Unknown',
          last_name: item.patient_last_name || item.patient_info?.last_name || 'Patient'
        },
        insurance_info: {
          payer_id: item.payer_id || item.insurance_info?.payer_id || 'UNKNOWN'
        },
        status: item.status || 'pending'
      }));

      setVerifications(transformedVerifications);

      // Create results mapping
      const resultsMap: Record<string, VerificationResult> = {};
      transformedVerifications.forEach(verification => {
        resultsMap[verification.id] = {
          id: verification.id,
          status: verification.status,
          eligibility_status: verification.status === 'active' ? 'eligible' :
                             verification.status === 'inactive' ? 'not_eligible' : 'pending',
          coverage_details: null,
          error_message: verification.status === 'error' ? 'Verification failed' : undefined
        };
      });
      setResults(resultsMap);

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch eligibility data');
      console.error('Error fetching eligibility data:', err);

      // Fallback to empty data
      setVerifications([]);
      setResults({});
    } finally {
      setLoading(false);
    }
  };

  const refreshVerifications = async () => {
    await fetchVerifications();
  };

  // Load data on component mount
  useEffect(() => {
    fetchVerifications();
  }, []);

  return (
    <EligibilityContext.Provider value={{
      verifications,
      results,
      loading,
      error,
      refreshVerifications
    }}>
      {children}
    </EligibilityContext.Provider>
  );
};
