import { useState, useEffect } from 'react';
import axios from 'axios';

interface InsuranceRule {
  allowed_products: string[];
  message: string;
  requires_consultation: boolean;
  wound_size_range: {
    min: number | null;
    max: number | null;
  };
}

interface DiagnosisCode {
  code: string;
  description: string;
  specialty: string | null;
}

interface QuickRequestConfig {
  wound_types: Record<string, string>;
  mue_limits: Record<string, number>;
  docuseal_templates: Record<string, any>;
  docuseal_account_email: string;
}

interface MscContact {
  id: string;
  department: string;
  name: string;
  email: string;
  phone: string | null;
  purpose: string;
  is_primary: boolean;
}

export function useQuickRequestConfig() {
  const [config, setConfig] = useState<QuickRequestConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchConfig();
  }, []);

  const fetchConfig = async () => {
    try {
      const response = await axios.get('/api/v1/configuration/quick-request');
      setConfig(response.data);
      setError(null);
    } catch (err) {
      console.error('Error fetching quick request config:', err);
      setError('Failed to load configuration');
    } finally {
      setLoading(false);
    }
  };

  return { config, loading, error, refetch: fetchConfig };
}

export function useInsuranceProductRules(insuranceType: string, state?: string, woundSize?: number) {
  const [rules, setRules] = useState<InsuranceRule[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (insuranceType) {
      fetchRules();
    }
  }, [insuranceType, state, woundSize]);

  const fetchRules = async () => {
    try {
      const params: any = { insurance_type: insuranceType };
      if (state) params.state = state;
      if (woundSize !== undefined) params.wound_size = woundSize;

      const response = await axios.get('/api/v1/configuration/insurance-product-rules', { params });
      setRules(response.data);
      setError(null);
    } catch (err) {
      console.error('Error fetching insurance rules:', err);
      setError('Failed to load insurance rules');
      
      // Fallback to hardcoded rules if API fails
      // This ensures the app continues to work during transition
      setRules(getFallbackRules(insuranceType, state, woundSize));
    } finally {
      setLoading(false);
    }
  };

  return { rules, loading, error };
}

export function useDiagnosisCodes(category?: string) {
  const [codes, setCodes] = useState<Record<string, DiagnosisCode[]>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchCodes();
  }, [category]);

  const fetchCodes = async () => {
    try {
      const params = category ? { category } : {};
      const response = await axios.get('/api/v1/configuration/diagnosis-codes', { params });
      setCodes(response.data);
      setError(null);
    } catch (err) {
      console.error('Error fetching diagnosis codes:', err);
      setError('Failed to load diagnosis codes');
      
      // Fallback to hardcoded codes
      setCodes(getFallbackDiagnosisCodes());
    } finally {
      setLoading(false);
    }
  };

  return { codes, loading, error };
}

export function useMscContacts(department?: string, purpose?: string) {
  const [contacts, setContacts] = useState<MscContact[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchContacts();
  }, [department, purpose]);

  const fetchContacts = async () => {
    try {
      const params: any = {};
      if (department) params.department = department;
      if (purpose) params.purpose = purpose;

      const response = await axios.get('/api/v1/configuration/msc-contacts', { params });
      setContacts(response.data);
      setError(null);
    } catch (err) {
      console.error('Error fetching MSC contacts:', err);
      setError('Failed to load contacts');
      
      // Fallback contact
      setContacts([{
        id: 'fallback',
        department: 'admin',
        name: 'MSC Admin',
        email: 'admin@mscwoundcare.com',
        phone: null,
        purpose: 'general',
        is_primary: true
      }]);
    } finally {
      setLoading(false);
    }
  };

  return { contacts, loading, error };
}

// Fallback functions for graceful degradation
function getFallbackRules(insuranceType: string, state?: string, woundSize?: number): InsuranceRule[] {
  // This provides the same rules as before but from a function
  // This ensures the app works during database migration
  console.warn('Using fallback insurance rules - database not available');
  
  if (insuranceType === 'ppo' || insuranceType === 'commercial') {
    return [{
      allowed_products: ['Q4154'],
      message: 'PPO/Commercial insurance covers BioVance for any wound size',
      requires_consultation: false,
      wound_size_range: { min: null, max: null }
    }];
  }
  
  if (insuranceType === 'medicare' && woundSize !== undefined) {
    if (woundSize <= 250) {
      return [{
        allowed_products: ['Q4250', 'Q4290'],
        message: 'Medicare covers Amnio AMP or Membrane Wrap Hydro for wounds 0-250 sq cm',
        requires_consultation: false,
        wound_size_range: { min: 0, max: 250 }
      }];
    } else if (woundSize <= 450) {
      return [{
        allowed_products: ['Q4290'],
        message: 'Medicare covers only Membrane Wrap Hydro for wounds 251-450 sq cm',
        requires_consultation: false,
        wound_size_range: { min: 251, max: 450 }
      }];
    } else {
      return [{
        allowed_products: [],
        message: 'Wounds larger than 450 sq cm require consultation with MSC Admin',
        requires_consultation: true,
        wound_size_range: { min: 451, max: null }
      }];
    }
  }
  
  if (insuranceType === 'medicaid') {
    // Simplified fallback for Medicaid
    return [{
      allowed_products: ['Q4271', 'Q4154', 'Q4238'],
      message: 'Medicaid coverage varies by state',
      requires_consultation: false,
      wound_size_range: { min: null, max: null }
    }];
  }
  
  return [];
}

function getFallbackDiagnosisCodes(): Record<string, DiagnosisCode[]> {
  console.warn('Using fallback diagnosis codes - database not available');
  
  return {
    yellow: [
      { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer', specialty: 'diabetic' },
      { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer', specialty: 'diabetic' },
      { code: 'E10.621', description: 'Type 1 diabetes mellitus with foot ulcer', specialty: 'diabetic' },
    ],
    orange: [
      { code: 'L97.411', description: 'Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin', specialty: 'pressure' },
      { code: 'L97.412', description: 'Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed', specialty: 'pressure' },
      { code: 'L97.511', description: 'Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin', specialty: 'pressure' },
    ],
  };
}