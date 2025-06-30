import { useState, useEffect } from 'react';
import axios from 'axios';

export interface ManufacturerField {
  name: string;
  label: string;
  type: string;
  required: boolean;
  options?: Array<{ value: string; label: string }>;
  placeholder?: string;
}

export interface ManufacturerData {
  id: number;
  name: string;
  signature_required: boolean;
  email_recipients: string[];
  docuseal_template_id: string | null;
  docuseal_folder_id: string | null;
  template_name: string | null;
  field_mapping: Record<string, string>;
  custom_fields: ManufacturerField[];
  products: string[];
  active: boolean;
  // Order form properties
  has_order_form?: boolean;
  order_form_template_id?: string;
  // IVR properties
  supports_insurance_upload_in_ivr?: boolean;
}

interface UseManufacturersResult {
  manufacturers: ManufacturerData[];
  loading: boolean;
  error: string | null;
  getManufacturerByName: (name: string) => ManufacturerData | undefined;
  getManufacturerById: (id: number) => ManufacturerData | undefined;
  refreshManufacturers: () => Promise<void>;
}

export function useManufacturers(): UseManufacturersResult {
  const [manufacturers, setManufacturers] = useState<ManufacturerData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchManufacturers = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await axios.get('/api/v1/manufacturers');

      // Validate response structure
      if (response.data && response.data.data && Array.isArray(response.data.data)) {
        setManufacturers(response.data.data);
        console.log('✅ Manufacturers loaded successfully:', response.data.data.length);
      } else {
        console.error('Invalid response structure:', response.data);
        setError('Invalid manufacturer data format');
      }
    } catch (err: any) {
      console.error('Error fetching manufacturers:', err);

      // Enhanced error handling
      if (err.response?.status === 401) {
        setError('Authentication required. Please log in.');
      } else if (err.response?.status === 403) {
        setError('Access denied. Insufficient permissions.');
      } else if (err.response?.status >= 500) {
        setError('Server error. Please try again later.');
      } else if (err.response?.data?.message) {
        setError(err.response.data.message);
      } else {
        setError('Failed to load manufacturer data');
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchManufacturers();
  }, []);

  const getManufacturerByName = (name: string): ManufacturerData | undefined => {
    return manufacturers.find(m => m.name.toLowerCase() === name.toLowerCase());
  };

  const getManufacturerById = (id: number): ManufacturerData | undefined => {
    return manufacturers.find(m => m.id === id);
  };

  return {
    manufacturers,
    loading,
    error,
    getManufacturerByName,
    getManufacturerById,
    refreshManufacturers: fetchManufacturers
  };
}

// Hook for getting a single manufacturer
export function useManufacturer(manufacturerIdOrName: string | number) {
  const [manufacturer, setManufacturer] = useState<ManufacturerData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchManufacturer = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await axios.get(`/api/v1/manufacturers/${manufacturerIdOrName}`);
        setManufacturer(response.data.data);
      } catch (err) {
        console.error('Error fetching manufacturer:', err);
        setError('Failed to load manufacturer data');
      } finally {
        setLoading(false);
      }
    };

    if (manufacturerIdOrName) {
      fetchManufacturer();
    }
  }, [manufacturerIdOrName]);

  return { manufacturer, loading, error };
}
