import { useState, useCallback, useEffect, useMemo } from 'react';
import type { SubmitHandler } from 'react-hook-form';
import { useForm, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import { debounce } from 'lodash';
import {
  ClinicalBillingData,
  DiagnosisCode,
  PatientAddress,
} from '@/types/quickRequest';

// NPI validation regex
const NPI_REGEX = /^\d{10}$/;

// ICD-10 validation regex  
const ICD10_REGEX = /^[A-Z]\d{2}(\.\d{1,4})?$/;

// Validation schema
const clinicalBillingSchema = z.object({
  provider: z.object({
    npi: z.string().regex(NPI_REGEX, 'NPI must be 10 digits'),
    name: z.string().min(1, 'Provider name is required'),
    specialty: z.string().optional(),
    phone: z.string().regex(/^\d{3}-\d{3}-\d{4}$/, 'Invalid phone format').optional(),
    fax: z.string().regex(/^\d{3}-\d{3}-\d{4}$/, 'Invalid fax format').optional(),
  }),
  facility: z.object({
    id: z.string().min(1, 'Facility is required'),
    name: z.string().min(1, 'Facility name is required'),
    npi: z.string().regex(NPI_REGEX, 'Invalid facility NPI').optional(),
    address: z.object({
      use: z.enum(['home', 'work', 'temp', 'old', 'billing']),
      type: z.enum(['postal', 'physical', 'both']),
      line: z.array(z.string()).min(1),
      city: z.string().min(1),
      state: z.string().length(2),
      postalCode: z.string().regex(/^\d{5}(-\d{4})?$/),
      country: z.string().default('USA'),
    }),
    phone: z.string().regex(/^\d{3}-\d{3}-\d{4}$/),
    fax: z.string().regex(/^\d{3}-\d{3}-\d{4}$/).optional(),
    taxId: z.string().optional(),
  }),
  referral: z.object({
    referringProviderId: z.string().optional(),
    referralDate: z.string().optional(),
    referralNumber: z.string().optional(),
    authorizationNumber: z.string().optional(),
  }),
  diagnosis: z.object({
    primary: z.object({
      code: z.string().regex(ICD10_REGEX, 'Invalid ICD-10 code'),
      system: z.enum(['icd10', 'icd9', 'snomed']),
      display: z.string().min(1, 'Diagnosis description is required'),
      isPrimary: z.boolean().optional(),
      dateRecorded: z.string().optional(),
    }),
    secondary: z.array(
      z.object({
        code: z.string().regex(ICD10_REGEX, 'Invalid ICD-10 code'),
        system: z.enum(['icd10', 'icd9', 'snomed']),
        display: z.string().min(1),
        isPrimary: z.boolean().optional(),
        dateRecorded: z.string().optional(),
      })
    ),
  }),
  woundDetails: z.object({
    woundType: z.string().min(1, 'Wound type is required'),
    woundLocation: z.string().min(1, 'Wound location is required'),
    woundSize: z.object({
      length: z.number().positive('Length must be positive'),
      width: z.number().positive('Width must be positive'),
      depth: z.number().positive().optional(),
      unit: z.enum(['cm', 'mm', 'in']),
    }),
    woundStage: z.string().optional(),
    woundAge: z.string().optional(),
    drainageType: z.string().optional(),
    drainageAmount: z.string().optional(),
    periWoundCondition: z.string().optional(),
    treatmentGoal: z.string().optional(),
  }),
});

type ClinicalBillingFormData = z.infer<typeof clinicalBillingSchema>;

interface UseClinicalBillingProps {
  initialData?: Partial<ClinicalBillingData>;
  onSave?: (data: ClinicalBillingData) => void;
  onNext?: (data: ClinicalBillingData) => void;
  patientId?: string;
}

interface ProviderSearchResult {
  npi: string;
  name: string;
  specialty?: string;
  phone?: string;
  fax?: string;
}

interface FacilitySearchResult {
  id: string;
  name: string;
  npi?: string;
  address: PatientAddress;
  phone: string;
  fax?: string;
  taxId?: string;
}

interface DiagnosisSearchResult {
  code: string;
  system: string;
  display: string;
  category?: string;
}

export function useClinicalBilling({
  initialData,
  onSave,
  onNext,
  patientId,
}: UseClinicalBillingProps) {
  // Provider search
  const [isSearchingProvider, setIsSearchingProvider] = useState(false);
  const [providerSearchResults, setProviderSearchResults] = useState<ProviderSearchResult[]>([]);
  
  // Facility search
  const [isLoadingFacilities, setIsLoadingFacilities] = useState(false);
  const [facilities, setFacilities] = useState<FacilitySearchResult[]>([]);
  
  // Diagnosis search
  const [isSearchingDiagnosis, setIsSearchingDiagnosis] = useState(false);
  const [diagnosisSearchResults, setDiagnosisSearchResults] = useState<DiagnosisSearchResult[]>([]);
  
  // Wound type options
  const [woundTypes, setWoundTypes] = useState<string[]>([]);
  const [woundLocations, setWoundLocations] = useState<string[]>([]);

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    formState: { errors, isSubmitting, isDirty },
    trigger,
  } = useForm<ClinicalBillingFormData>({
    resolver: zodResolver(clinicalBillingSchema),
    defaultValues: initialData || {
      diagnosis: {
        primary: {
          system: 'icd10',
          isPrimary: true,
        },
        secondary: [],
      },
      woundDetails: {
        woundSize: {
          unit: 'cm',
        },
      },
    },
  });

  const { fields: secondaryDiagnoses, append, remove } = useFieldArray({
    control,
    name: 'diagnosis.secondary',
  });

  // Load facilities on mount
  useEffect(() => {
    setIsLoadingFacilities(true);
    axios
      .get('/api/v1/facilities')
      .then(response => {
        setFacilities(response.data.data);
      })
      .catch(error => {
        console.error('Failed to load facilities:', error);
      })
      .finally(() => {
        setIsLoadingFacilities(false);
      });
  }, []);

  // Load wound types and locations
  useEffect(() => {
    Promise.all([
      axios.get('/api/v1/value-sets/wound-types'),
      axios.get('/api/v1/value-sets/wound-locations'),
    ])
      .then(([typesResponse, locationsResponse]) => {
        setWoundTypes(typesResponse.data.data);
        setWoundLocations(locationsResponse.data.data);
      })
      .catch(error => {
        console.error('Failed to load value sets:', error);
      });
  }, []);

  // Provider search by NPI
  const searchProviderByNPI = useCallback(
    debounce(async (npi: string) => {
      if (!npi || npi.length !== 10) return;

      setIsSearchingProvider(true);
      try {
        const response = await axios.get(`/api/v1/providers/search`, {
          params: { npi },
        });
        setProviderSearchResults(response.data.data);
        
        // Auto-fill if single result
        if (response.data.data.length === 1) {
          const provider = response.data.data[0];
          setValue('provider.name', provider.name);
          setValue('provider.specialty', provider.specialty || '');
          setValue('provider.phone', provider.phone || '');
          setValue('provider.fax', provider.fax || '');
        }
      } catch (error) {
        console.error('Provider search failed:', error);
      } finally {
        setIsSearchingProvider(false);
      }
    }, 500),
    [setValue]
  );

  // Watch NPI for auto-search
  const providerNPI = watch('provider.npi');
  useEffect(() => {
    if (providerNPI?.length === 10) {
      searchProviderByNPI(providerNPI);
    }
  }, [providerNPI, searchProviderByNPI]);

  // Diagnosis search
  const searchDiagnosis = useCallback(
    debounce(async (query: string) => {
      if (!query || query.length < 3) return;

      setIsSearchingDiagnosis(true);
      try {
        const response = await axios.get('/api/v1/diagnoses/search', {
          params: { q: query, system: 'icd10' },
        });
        setDiagnosisSearchResults(response.data.data);
      } catch (error) {
        console.error('Diagnosis search failed:', error);
      } finally {
        setIsSearchingDiagnosis(false);
      }
    }, 300),
    []
  );

  // Set diagnosis from search result
  const setDiagnosis = useCallback(
    (diagnosis: DiagnosisSearchResult, isPrimary: boolean = false) => {
      if (isPrimary) {
        setValue('diagnosis.primary', {
          code: diagnosis.code,
          system: diagnosis.system as 'icd10' | 'icd9' | 'snomed',
          display: diagnosis.display,
          isPrimary: true,
          dateRecorded: new Date().toISOString().split('T')[0],
        });
      } else {
        append({
          code: diagnosis.code,
          system: diagnosis.system as 'icd10' | 'icd9' | 'snomed',
          display: diagnosis.display,
          isPrimary: false,
          dateRecorded: new Date().toISOString().split('T')[0],
        });
      }
    },
    [setValue, append]
  );

  // Validate wound care necessity based on diagnosis
  const validateWoundCareNecessity = useCallback(async () => {
    const primaryDiagnosis = watch('diagnosis.primary');
    if (!primaryDiagnosis?.code) return { valid: true };

    try {
      const response = await axios.post('/api/v1/medicare/validate-diagnosis', {
        diagnosisCode: primaryDiagnosis.code,
        woundType: watch('woundDetails.woundType'),
      });
      
      return response.data;
    } catch (error) {
      console.error('Diagnosis validation failed:', error);
      return { valid: false, message: 'Unable to validate diagnosis' };
    }
  }, [watch]);

  // Calculate wound area
  const woundArea = useMemo(() => {
    const size = watch('woundDetails.woundSize');
    if (!size?.length || !size?.width) return 0;
    
    const area = size.length * size.width;
    if (size.depth) {
      return area * size.depth;
    }
    return area;
  }, [watch('woundDetails.woundSize')]);

  // Handle form submission
  const toClinicalBillingData = (form: ClinicalBillingFormData): ClinicalBillingData => ({
    ...form,
  });


  

  const onSubmitHandler: SubmitHandler<ClinicalBillingFormData> = async (data) => {
    // Validate wound care necessity
    const necessityCheck = await validateWoundCareNecessity();
    if (!necessityCheck.valid) {
      const shouldContinue = window.confirm(
        `Warning: ${necessityCheck.message}. Do you want to continue?`
      );
      if (!shouldContinue) return;
    }

    // Save progress
    if (onSave) {
      await onSave(toClinicalBillingData(data));
    }

    // Proceed to next step
    if (onNext) {
      await onNext(toClinicalBillingData(data));
    }
  };

  // Auto-save functionality
  useEffect(() => {
    if (isDirty && onSave) {
      const saveTimer = setTimeout(() => {
        const formData = watch();
        onSave(toClinicalBillingData(formData as ClinicalBillingFormData));
      }, 3000);

      return () => clearTimeout(saveTimer);
    }
  }, [isDirty, watch, onSave]);

  // Load selected facility details
  const selectedFacility = useMemo(() => {
    const facilityId = watch('facility.id');
    return facilities.find(f => f.id === facilityId);
  }, [watch('facility.id'), facilities]);

  useEffect(() => {
    if (selectedFacility) {
      setValue('facility.name', selectedFacility.name);
      setValue('facility.npi', selectedFacility.npi || '');
      setValue('facility.address', {
        ...selectedFacility.address,
        // Ensure country is always defined to satisfy form schema
        country: selectedFacility.address.country ?? 'USA',
      });
      setValue('facility.phone', selectedFacility.phone);
      setValue('facility.fax', selectedFacility.fax || '');
      setValue('facility.taxId', selectedFacility.taxId || '');
    }
  }, [selectedFacility, setValue]);

  return {
    // Form methods
    register,
    handleSubmit: onsubmit,
    control,
    errors,
    isSubmitting,
    isDirty,
    watch,
    setValue,
    trigger,

    // Provider
    isSearchingProvider,
    providerSearchResults,
    searchProviderByNPI,

    // Facility
    isLoadingFacilities,
    facilities,
    selectedFacility,

    // Diagnosis
    isSearchingDiagnosis,
    diagnosisSearchResults,
    searchDiagnosis,
    setDiagnosis,
    secondaryDiagnoses,
    removeDiagnosis: remove,

    // Wound details
    woundTypes,
    woundLocations,
    woundArea,

    // Validation
    validateWoundCareNecessity,

    // Computed values
    canProceed: !Object.values(errors).length && !isSubmitting,
    hasSecondaryDiagnoses: secondaryDiagnoses.length > 0,
  };
}